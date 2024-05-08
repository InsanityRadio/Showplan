<?php
namespace Showplan\Models;
require_once 'model.php';

use Showplan\Controller;
use \Exception;

class Term extends Model {

	protected static $table_name = 'terms';
	protected static $columns = ['id', 'station_id', 'reference', 'start_time', 'end_time', 'total_weeks'];

	public function get_station () {
		return Station::find($this->station_id);
	}

	/*
	@has_many
	*/
	public function get_show_times () {

		return ShowTime::where('term_id', $this->id)->get();

	}

	/**
	 * Returns the timestamp at 00:00 on the Monday of the first week that this timetable is working.
	 * This is NOT the effective start date (unless the schedule starts on a Monday)
	 * Assumptions: start_time is midnight
	 */
	public function get_first_day () {

		// Work out how many days in this is
		$_day = (idate('w', $this->start_time) - 1) % 7;
		if ($_day < 0) {
			$_day += abs(7);
		}

		$_start_midnight = $this->start_time - ($this->start_time % 86400);

		return $_start_midnight - $_day * 86400;

	}

	public function set_station ($station) {
		if (!is_a($station, 'Station')) {
			throw new Exception('Tried to pass the wrong type of Station');
		}
		$this->station_id = $station->id;
	}

	public function set_start_time ($time) {
		
		$time = strtoutctime($time);
		$open = array_filter(self::open_between($time, $time), function ($a) { return $a->id != $this->id; });
		if (count($open)) {
			throw new Exception('Cannot have overlapping terms');
		}
		if (($time % 86400) != 0) {
			throw new Exception('Start time MUST be midnight');
		}
		$this->_data['start_time'] = $time;

	}

	public function set_end_time ($time) {

		$_converted_time = strtoutctime($time);

		$open = array_filter(self::open_between($_converted_time, $_convertedtime), function ($a) { return $a->id != $this->id; });
		if (count($open)) {
			throw new Exception('Cannot have overlapping terms');
		}

		if (($_converted_time % 86400) != 0) {
			throw new Exception('End time MUST be midnight');
		}
		$this->_data['end_time'] = $_converted_time;

	}

	public function set_total_weeks ($total) {
		if ($total != 1) {
			throw new Exception('Values that aren\'t 1 are not supported yet!');
		}
		$this->_data['total_weeks'] = $total;
	}

	/**
	 * Checks if there are any terms that overlap with the given times
	 */
	public static function open_between ($start, $end) {

		global $wpdb;
		$_rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Controller::$prefix . static::$table_name . '
			WHERE (end_time > %d AND start_time <= %d)
		;', $start, $end), 'ARRAY_A');

		return array_map(function ($a) { return new static($a); }, $_rows);

	}

	public static function get_current_term ($time = null) {

		if (!$time) {
			$time = time();
		}

		global $wpdb;
		$_rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Controller::$prefix . static::$table_name . '
			WHERE start_time <= %d AND end_time > %d
		;', $time, $time), 'ARRAY_A');

		if (count($_rows) == 0) {
			throw new Exception('There is no current calendar term');
		}

		return new static($_rows[0]);

	}
	
}
