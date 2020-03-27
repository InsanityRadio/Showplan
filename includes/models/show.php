<?php
namespace Showplan\Models;
require_once 'model.php';

use \Exception;

class Show extends Model {

	protected static $table_name = 'shows';
	protected static $columns = ['id', 'name', 'description', 'hosts', 'one_liner', 'public', 'category_id', 'media_id', 'media_url'];

	public static function default_one_liner ($show) {

		$_option = get_option("showtime-oneliner-default-pattern", "{show_name}{show_hosts_prefix}");

		$_variables = array(
			"{show_name}" => $show->name,
			"{show_description}" => $show->description,
			"{show_hosts_prefix}" => $show->hosts == '' ? '' : ' with ' . $show->hosts,
			"{show_hosts}" => $show->hosts
		);

		return strtr($_option, $_variables);
	}

	public function get_category () {
		return Category::find($this->category_id);
	}

	public function set_one_liner ($value) {

		$this->_data['one_liner'] = $value == '' ? Show::default_one_liner($this) : $value;

	}

	public function set_media_id ($media_id) {
		$this->_data['media_id'] = $media_id;
		$this->_data['media_url'] = wp_get_attachment_url($media_id);
	}
	
	public function get_media_id () {
		return $this->_data['media_id'];
	}

	public function set_public ($public) {
		if ($public == 0 && $this->category_id == 0) {
			throw new Exception('Sustainers must not be hidden');
		}
		$this->_data['public'] = $public ? 1 : 0;
	}

	public function set_category ($category) {
		if (!is_a($category, 'Category')) {
			throw new Exception('Tried to pass the wrong type of Category');
		}
		$this->category_id = $category->id;
	}

	public function getData () {
		return $this->_data;
	}

	public function __json () {

		return array(
			'name' => $this->_data['name'] ?: $this->_data['hosts'],
			'description' => $this->_data['description'],
			'hosts' => $this->_data['hosts'],
			'summary' => $this->_data['one_liner'],
			'category' => $this->category->reference,
			// 'media_id' => $this->_data['media_id'],
			'media_url' => $this->_data['media_url']
		);

	}	
}
