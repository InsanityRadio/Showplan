<?php
namespace Showplan;

// Init WordPress if we're including just this library. 
if (!defined('ABSPATH')) {

	define('SHORTINIT', true);
	define('ABSPATH', dirname(dirname(dirname(dirname(__FILE__)))) . '/');

	require_once ABSPATH . 'wp-load.php';

	if ($_GET['site_id']) {
		switch_to_blog((int) $_GET['site_id']);
	}

	class Controller {
		public static $prefix;
	}

	Controller::$prefix = $wpdb->prefix . 'showplan_';

}


require_once 'includes/data.php';

$start = microtime(true);

$_station_id = (int) $_GET['station_id'];
$_method = $_GET['method'];

$_days = (int) $_GET['days'];

$_data = new \Showplan\Data();

$_result = null;

if ($_station_id && $_method && substr($_method, 0, 4) == 'get_') {

	$_result = call_user_func_array(array($_data, $_method), [$_station_id, $_days]);

}

header('Content-Type: application/json');

echo json_encode(array(
	'station' => $_data->get_station($_station_id),
	'execute_time' => microtime(true) - $start,
	'body' => $_result));
