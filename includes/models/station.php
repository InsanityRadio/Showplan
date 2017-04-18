<?php
namespace Showplan\Models;
require_once 'model.php';

class Station extends Model {

	protected static $table_name = 'stations';
	protected static $columns = ['id', 'reference', 'current_iteration_id', 'name', 'description'];

	/*
	@has_many
	*/
	public function get_overrides () {

		return Override::where('station_id', $this->id)->get();

	}
	
}