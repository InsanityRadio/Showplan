<?php
namespace Showplan\Models;
use Showplan\Controller;
use Showplan\Compiler;

require_once 'model.php';

class CompiledTime extends Model {

	protected static $table_name = 'compiled_times';
	protected static $columns = ['id', 'start_time', 'end_time', 'start_time_local', 'end_time_local', 'show_id', 'station_id'];

	/**
	 * Limitations: does not really take start or end of term into account
	 */
	public static function get_show_at ($utc_timestamp, $station) {

		global $wpdb;

		if (is_numeric($station)) {
			$station = Station::find($station);
		}

		$_rows = $wpdb->get_results($_q = $wpdb->prepare(
			'SELECT * FROM `' . Controller::$prefix . static::$table_name . '`
				WHERE station_id=%d AND start_time <= %d AND end_time > %d;',
				$station->id, $utc_timestamp, $utc_timestamp),
		'ARRAY_A');

		return sizeof($_rows) ? new static($_rows[0]) : false;

	}

	public static function sustainer ($timestamp, $term = null, $cache = true, $tz = null) {

		$_sustainer = Sustainer::full_at($timestamp, $cache);
		$timestamp = (time() - time() % 86400) + $_sustainer[1] * 3600;

		$_length = (($_sustainer[2] - $_sustainer[1] + 24) % 24) * 3600;

		$_fake = CompiledTime::create();

		$_fake->start_time = $timestamp;
		$_fake->end_time = $_fake->start_time + $_length;
		$_fake->start_time_local = Compiler::timestamp_to_future_localised($_fake->start_time, $tz);
		$_fake->end_time_local = $_fake->start_time_local + $_length;

		$_fake->show = $_sustainer[0];

		return $_fake;

	}
	public function get_show () {
		return Show::find($this->show_id);
	}

	public function set_show ($show) {
		if (!is_a($show, '\Showplan\Models\Show')) {
			throw new \Exception('Tried to pass the wrong type of Show');
		}
		$this->show_id = $show->id;
		$this->_data['show'] = $show;
	}

	public function get_station () {
		return Station::find($this->station_id);
	}

	public function set_station ($station) {
		if (!is_a($station, '\Showplan\Models\Station')) {
			throw new \Exception('Tried to pass the wrong type of Station');
		}
		$this->station_id = $station->id;
	}
	
	public function get_length () {
		return $this->end_time - $this->start_time;
	}

	public function get_date ($format = 'j M H:i') {
		return gmdate($format, $this->start_time);
	}

	public function __json () {

		return array(
			'start_time' => (int) $this->_data['start_time'],
			'end_time' => (int) $this->_data['end_time'],
			'start_time_local' => (int) $this->_data['start_time_local'],
			'end_time_local' => (int) $this->_data['end_time_local'],
			'time_display' => gmdate('j M Y H:i', $this->_data['start_time']),
			'episode_key' => $this->_data['station_id'] . '-' . $this->_data['start_time'],
			'show' => $this->show
		);

	}
	
}
