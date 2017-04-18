<?php
namespace Showplan\Models;
use Showplan\Controller;

require_once 'model.php';

class Override extends Model {

	protected static $table_name = 'overrides';
	protected static $columns = ['id', 'start_time', 'end_time', 'show_id', 'station_id'];
	protected $_start_offset;


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

	public function set_length ($length) {
		$this->end_time = $this->start_time + $length;
	}

	public function get_start_offset () {
		return $this->_start_offset;
	}

	public function set_start_offset ($length) {
		$this->_start_offset = $length;
	}

	public function get_end_offset () {
		return $this->_start_offset + $this->length;
	}
	
}