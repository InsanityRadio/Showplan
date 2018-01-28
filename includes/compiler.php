<?php
namespace Showplan;

use DateTimeZone;

use Showplan\Models\CompiledTime;
use Showplan\Models\Override;
use Showplan\Models\Show;
use Showplan\Models\ShowTime;
use Showplan\Models\Term;

class Compiler {
	
	/**
	 * Takes a timestamp in UTC and converts it into a timestamp for the 'same' time in a separate timezone.
	 * If a timezone changes, this may be useful for not breaking your schedule. 
	 * Essentially the same as below, but assumes we're in the daylight savings period of the timestamp.
	 * Necessary for generating the 'local' timestamp in the compiled table
	 */
	public static function timestamp_to_future_localised ($utc_timestamp, $timezone) {

		if (is_string($timezone)) {
			$timezone = new DateTimeZone($timezone);
		}

		$_transition = $timezone->getTransitions($utc_timestamp, $utc_timestamp);
		return $utc_timestamp - $_transition[0]['offset'];

	}

	/**
	 * Takes a UTC timestamp and makes it appear exactly like it would now in a separate timezone.
	 * For instance, if we're in BST takes strtotime(2017-01-02 00:00:00) and returns
	 *   strtotime(2017-01-01 23:00:00), which would show as 00:00:00 BST.
	 * Useful for compensating live etc. if your server adjusts timezones. 
	 */
	public static function timestamp_to_localised ($utc_timestamp, $timezone) {

		if (is_string($timezone)) {
			$timezone = new DateTimeZone($timezone);
		}

		$_transition = $timezone->getTransitions(time(), time());
		return $utc_timestamp - $_transition[0]['offset'];		

	}

	public static function invert_timestamp_localised ($utc_timestamp, $timezone) {

		if (is_string($timezone)) {
			$timezone = new DateTimeZone($timezone);
		}

		$_transition = $timezone->getTransitions(time(), time());
		return $utc_timestamp + $_transition[0]['offset'];		

	}

	public function __construct ($station) {

		$this->station = $station;

	}

	/**
	 * Compiles the timetable between two timestamps. 
	 * TO-DO: per-station
	 */
	public function compile ($start, $end) {

		global $wpdb;
		// Wrap everything in a transaction to make sure that we don't hit any race conditions
		$wpdb->query('START TRANSACTION;');
		try {

			$_terms = Term::open_between($start, $end);

			// Delete everything that wasn't in the past 7 days.
			$wpdb->query('DELETE FROM `' . Controller::$prefix . 'compiled_times` WHERE start_time > ' . $start); 
			$wpdb->query('DELETE FROM `' . Controller::$prefix . 'compiled_times` WHERE end_time < ' . ($start - 86400*7));

			foreach ($_terms as $i => $_term) {
				$this->compile_term($_term, $start, $end, $i == 0);
			}

			$wpdb->query('COMMIT;');

		} catch (Exception $_e) {

			$wpdb->query('ROLLBACK;');
			throw $_e;

		}

	}

	/**
	 * Compile the timetable for the next fortnight (starting from Monday).
	 */
	public function compileDefaults () {

		$_start = strtotime('7 days ago 00:00 UTC');
		$_end = $_start + 86400*21;
		$this->compile($_start, $_end);

	}

	private function compile_term ($term, $start, $end, $first_term) {

		// Step through the hours from its start to end
		$_start = max($start - ($start % 604800), $term->first_day);
		$_end = max(604800 + $term->first_day, min($end, $term->end_time));

		$_offset  = $_end - $_start;
		$_hours = max(ceil($_offset / 3600), 168);

		$_cycles = ceil(ceil(($term->end_time - $term->first_day) / 604800) / $term->total_weeks);

		$_shows = [];

		// 1. Find every show in the first cycle
		for ($i = 0; $i < $_hours; $i++) {

			$_show = $this->get_show_at_time($term, $_start + $i * 3600);
			$_shows[$_show->start_offset] = array($_show, $_show->end_offset - $_show->start_offset);

		}

		// 2. Collapse repeating hours to make the next few steps less complex
		foreach ($_shows as $_show) {
			if ($_shows[$_show[0]->end_offset] && $_shows[$_show[0]->end_offset][0]->show_id == $_show[0]->show_id) {
				unset($_shows[$_show[0]->end_offset]);
			}
		}

		ksort($_shows);

		$_expanded_shows = $_shows;

		// 3. Copy the first cycle to create the other cycles

		for ($c = 1; $c <= $_cycles; $c++) {
			foreach ($_shows as $_show) {
				$_copy = $_show[0]->make_copy($c * 604800);
				$_expanded_shows[$_copy->start_offset] = array($_copy, $_show[1]);
			}
		}


		// 4. Insert overrides
		$_overrides = Override::where('station_id', $term->station->id)->get();

		$_keys = array_keys($_expanded_shows);
		sort($_keys);

		foreach ($_overrides as $_override) {

			if ($_override->start_time < $_start || $_override->start_time > $_end) continue;

			$_start_offset = $_override->start_offset = $_override->start_time - $term->first_day;
			$_expanded_shows[$_start_offset] = array($_override, $_override->length * 60);

			// Delete anything between the time periods
			foreach ($_keys as $_key) {
				if ($_key > $_start_offset && $_key < ($_start_offset + $_override->length)) {
					unset($_expanded_shows[$_key]);
				}
			}

		}

		ksort($_expanded_shows);

		// 5. Collapse for a second time
		foreach ($_expanded_shows as $_show) {
			if ($_expanded_shows[$_show[0]->end_offset] && $_expanded_shows[$_show[0]->end_offset][0]->show_id == $_show[0]->show_id) {
				unset($_expanded_shows[$_show[0]->end_offset]);
			}
		}

		// 6. Finally, update the end offsets so they are correct
		$_keys = array_keys($_expanded_shows);
		sort($_keys);

		for ($i = 0; $i < sizeof($_keys); $i++) {
			
			$_key = $_keys[$i];
			$_value = &$_expanded_shows[$_key];

			if ($i == sizeof($_keys) - 1) {
				$_value[0]->length = (86400 - ($_value[0]->start_offset % 86400)) / 60;
			} else {
				$_value[0]->length = ($_keys[$i + 1] - $_value[0]->start_offset) / 60;
			}

		}

		return $this->commit_term($term, $_expanded_shows, $start, $end, $first_term);

	}

	/**
	 * Stores a term in a database given its processed form
	 */
	private function commit_term ($term, $shows, $start, $end, $first_term) {

		$_tz = new DateTimeZone(get_option('timezone_string'));

		$_term_start_offset = 0; //$term->start_time - $term->first_day;
		$_last = null;

		foreach ($shows as $_el) {

			list ($_show, $_length) = $_el;

			$_start_time = $term->first_day + $_show->start_offset;
			$_end_time = $_start_time + $_show->length;

			// Ensure no overlap outside this term
			if ($term->start_time > $_start_time || $term->end_time < $_start_time) {
				continue;
			}

			// Ensure no overlap outside the time we want to schedule
			if ($_start_time < $start) {
				$_last = $_el;
				continue;
			}
			if ($_start_time > $end) {
				continue;
			}

			$this->commit_show($_show, $_show->length, $term, $_start_time, $_tz);

		}

		// In case of overlap, store the last show before the valid time starts.
		// That way if a show starts at 1AM on the starting day, we'd otherwise have a 1 hour gap before. 
		if ($_last && $first_term) {
			$_start_time = $term->first_day + $_last[0]->start_offset;
			$this->commit_show($_last[0], $_last[0]->length, $term, $_start_time, $_tz);
		}

	}

	private function commit_show ($show, $length, $term, $start_time, $tz) {

		$_comp = CompiledTime::create();
		$_comp->show_id = $show->show->id;
		$_comp->station_id = $term->station_id;

		$_comp->start_time = $start_time;
		$_comp->end_time = $start_time + $length * 60;

		$_comp->start_time_local = self::timestamp_to_future_localised($_comp->start_time, $tz);
		$_comp->end_time_local = self::timestamp_to_future_localised($_comp->end_time, $tz);

		$_comp->save();

	}

	private function get_show_at_time ($term, $timestamp) {

		// Work out what show is going on at this time. Look at overrides first, but TO-DO
		$_show = ShowTime::get_show_at($timestamp, $term);

		return $_show ? $_show : ShowTime::sustainer($timestamp, $term);


	}

	public function insert_or_update () {

	}

}
