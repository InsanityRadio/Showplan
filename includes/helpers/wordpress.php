<?php
namespace Showplan;
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * Essentially a wrapper around all WordPress stdlib functions
 */
class Frontend {
	
	public static function nonce_url ($url, $action, $name) {
		return wp_nonce_url($url, $action, $name);
	}

	public static function nonce_field ($name) {
		return wp_nonce_field($name);
	}

	public static function _die ($message) {
		return wp_die($message);
	}

	public static function verify_nonce ($nonce, $name) {
		return wp_verify_nonce($nonce, $name);
	}

	public static function enqueue_script ($a, $b, $c) {
		return wp_enqueue_script($a, $b, $c);
	}

	public static function enqueue_style ($a, $b) {
		return wp_enqueue_style($a, $b);
	}

	public static function cache_add ($a, $b, $c) {
		return wp_cache_add($a, $b, $c);
	}

	public static function cache_get ($a, $b) {
		return wp_cache_get ($a, $b);
	}

	public static function next_scheduled ($a) {
		return wp_next_scheduled($a);
	}

	public static function schedule_event ($a, $b, $c, $d = array()) {
		return wp_schedule_event($a, $b, $c, $d);
	}

}

abstract class List_Table extends \WP_List_Table {
}
