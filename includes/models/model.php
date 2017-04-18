<?php
namespace Showplan\Models;

use \Showplan\Controller;
use Exception;

abstract class Model implements \JsonSerializable {

	protected static $table_name;
	protected static $columns;

	protected $_data = null;

	public function __construct ($_row = []) {
		$this->_data = $_row;
	}

	public static function find ($key) {

		global $wpdb;

		$_row = $wpdb->get_row(
			$wpdb->prepare('SELECT * FROM `' . Controller::$prefix . static::$table_name . '` WHERE id = %d;', $key),
			'ARRAY_A'
		);

		if (!$_row) {
			throw new Exception('Row does not exist');
		}

		return new static($_row);

	}

	public static function create () {

		return new static(array_fill_keys(static::$columns, null));

	}

	public static function all () {

		global $wpdb;

		$_rows = $wpdb->get_results('SELECT * FROM `' . Controller::$prefix . static::$table_name . '`;', 'ARRAY_A');

		$_rows = array_map(function($row) { return new static($row); }, $_rows);
		return $_rows;

	}

	public static function where ($key, $value) {

		return new QueryStub(Controller::$prefix . static::$table_name, get_called_class(), $key, $value);

	}

	public function save () {

		global $wpdb;
		$table = Controller::$prefix . static::$table_name;

		$_data = array_intersect_key($this->_data, array_flip(static::$columns));

		if ($this->id === NULL) {
			$wpdb->insert($table, $_data);
			$this->id = $wpdb->insert_id;
		} else {
			$wpdb->update($table, $_data, array('id' => $this->id));
		}

	}

	public function remove () {

		global $wpdb;
		if ($this->id == NULL) {
			return;
		}

		$table = Controller::$prefix . static::$table_name;
		$wpdb->delete($table, array('id' => $this->id));
		$this->id = NULL;

	}

	public function __get ($name) {

		if (method_exists($this, 'get_' . $name)) {
			return call_user_func(array($this, 'get_' . $name));
		}

		if (!array_key_exists($name, $this->_data)) {
			throw new Exception('Property ' . $name . ' does not exist on ' . get_class());
		}

		return $this->_data[$name];

	}

	public function __set ($name, $value) {
		if (method_exists($this, 'set_' . $name)) {
			return call_user_func(array($this, 'set_' . $name), $value);
		}

		$this->_data[$name] = $value;
	}

	public function __array () {
		return $this->_data;
	}

	public function __json () {
		return $this->_data;
	}

	public function jsonSerialize () {

		return $this->__json();

	}
	
}

class QueryStub {

	public $parent = null;

	public function __construct ($table_name, $class, $key, $value, $parent = null) {
		$this->table_name = $table_name;
		$this->class = $class;
		$this->key = $key;
		$this->value = $value;
		$this->parent = $parent;
	}

	public function where ($key, $value) {

		$instance = new self($this->table_name, $class, $key, $value, $this);

		return $instance;

	}

	public function get () {

		global $wpdb;

		$_class = $this->class;

		$_args = array(array('SELECT * FROM `' . $this->table_name . '` WHERE '));

		$_top = $this;
		// Add arguments to our array of parameters
		while ($_top != null) {
			$_args[0][] = '`' . $_top->key . '` = %s';
			$_args[] = $_top->value;
			$_top = $_top->parent;
		}
		// Join all the query bits with ANDs

		$_args[0] = $_args[0][0] . join(' AND ', array_slice($_args[0], 1)) . ';';
		$_query = call_user_func_array(array($wpdb, 'prepare'), $_args);

		$_rows = $wpdb->get_results($_query, 'ARRAY_A');
		$_rows = array_map(function($row) use ($_class) { return new $_class($row); }, $_rows);
		return $_rows;

	}

	public function first () {
		return $this->get()[0];
	}

	public function first_or_fail () {

		$_result = $this->get();

		if (sizeof($_result) == 0) {
			throw new \Exception('Query returned an empty resultset');
		}

		return $_result[0];

	}

}

function strtoutctime($time) {
	return is_numeric($time) ? $time : strtotime($time . ' UTC');
}
