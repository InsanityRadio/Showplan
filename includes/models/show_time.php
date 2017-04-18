<?php
namespace Showplan\Models;
use Showplan\Controller;

require_once 'model.php';

class ShowTime extends Model {

	protected static $table_name = 'show_times';
	protected static $columns = ['id', 'week', 'day', 'hour', 'minute', 'length', 'show_id', 'term_id'];

	public $offset = 0;

	/**
	 * Limitations: does not really take start or end of term into account
	 */
	public static function get_show_at ($utc_timestamp, $term = null) {

		global $wpdb;

		if (is_numeric($term)) {
			$term = Term::find($term);
		} else if ($term == null) {
			$term = Term::get_current_term($utc_timestamp);
		}

		// TODO: CHECK FOR OVERRIDES

		// Fake timestamp from the start of the template

		$_secs_into_weeks = ($utc_timestamp - $term->first_day) % (604800 * $term->total_weeks);

		$_rows = $wpdb->get_results($_q = $wpdb->prepare(
			'SELECT * FROM `' . Controller::$prefix . static::$table_name . '`
				WHERE term_id = %d AND
				(week * 604800 + day * 86400 + hour * 3600 + minute * 60) <= %d AND
				(week * 604800 + day * 86400 + hour * 3600 + (length + minute) * 60) > %d;',
				$term->id, $_secs_into_weeks, $_secs_into_weeks),
		'ARRAY_A');

		return sizeof($_rows) ? new static($_rows[0]) : false;

	}

	public static function sustainer ($timestamp, $term = null, $cache = true) {

		if (is_numeric($term)) {
			$term = Term::find($term);
		}

		$timestamp = $timestamp - ($timestamp % 3600);

		$_sustainer = Sustainer::at($timestamp, $cache);

		$_fake = ShowTime::create();


		$_secs_into_weeks = $term == null ? 
			($timestamp - strtotime('last monday', $timestamp + 86400)) :
			($timestamp - $term->first_day) % (604800 * $term->total_weeks);

		$_fake->week = $_secs_into_weeks / 604800 | 0;
		$_fake->day = ($_secs_into_weeks - ($_fake->week * 604800)) / 86400 | 0;
		$_fake->hour = ($_secs_into_weeks - ($_fake->day * 86400 + $_fake->week * 604800)) / 3600 | 0 ;
		$_fake->minute = ($_secs_into_weeks - ($_fake->hour * 3600 + $_fake->day * 86400 + $_fake->week * 604800)) / 60 | 0;

		$_fake->length = 60;
		$_fake->show = $_sustainer;

		return $_fake;

	}

	public function make_copy ($offset) {

		$object = clone $this;
		$object->offset = $offset;
		return $object;

	}

	public function get_start_offset () {
		return $this->week * 604800 + $this->day * 86400 + $this->hour * 3600 + $this->minute * 60 + $this->offset;
	}

	public function get_end_offset () {
		return $this->week * 604800 + $this->day * 86400 + $this->hour * 3600 + ($this->minute + $this->length) * 60 + $this->offset;
	}

	public function get_show () {
		return $this->id != NULL ? Show::find($this->show_id) : $this->_data['show'];
	}

	public function set_show ($show) {
		if (!is_a($show, '\Showplan\Models\Show')) {
			throw new \Exception('Tried to pass the wrong type of Show');
		}
		$this->show_id = $show->id;
		$this->_data['show'] = $show;
	}

	public function get_term () {
		return Term::find($this->term_id);
	}

	public function set_term ($term) {
		if (!is_a($term, '\Showplan\Models\Term')) {
			throw new \Exception('Tried to pass the wrong type of Term');
		}
		$this->term_id = $term->id;
	}
	
}