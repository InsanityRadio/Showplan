<?php
namespace Showplan;

require_once 'models/category.php';
require_once 'models/compiled_time.php';
require_once 'models/override.php';
require_once 'models/show.php';
require_once 'models/show_time.php';
require_once 'models/station.php';
require_once 'models/sustainer.php';
require_once 'models/term.php';

require_once 'compiler.php';

use \Showplan\Compiler;
use \Showplan\Models\CompiledTime;
use \Showplan\Models\ShowTime;
use \Showplan\Models\Station;

// Init WordPress if we're including just this library. 
if (!defined('ABSPATH')) {

	define('SHORTINIT', true);
	define('ABSPATH', dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/');

	require_once ABSPATH . 'wp-load.php';

	if ($_GET['site_id']) {
		switch_to_blog($_GET['site_id']);
	}

	class Controller {
		public static $prefix;
	}

	Controller::$prefix = $wpdb->prefix . 'showplan_';

}

/**
 * Data allows you to retrieve information to display on the frontend. 
 * It does n
 */
class Data {

	public function bootstrap () {
		$that = $this;
		function boot ($that) {
			$atts = shortcode_atts(array('station' => 1), $atts);
			if (!$that->_cache['upcoming']) {
				$that->_cache['upcoming'] = $that->get_upcoming($atts['station']);
			}
		}

		// Ugh. 

		add_shortcode( 'showplan-now-title', function ($atts) use ($that) {
			boot($that);
			return $that->_cache['upcoming'][0]->show->name;
		});

		add_shortcode( 'showplan-now-description', function ($atts) use ($that) {
			boot($that);
			return $that->_cache['upcoming'][0]->show->description;
		});

		add_shortcode( 'showplan-now-hosts', function ($atts) use ($that) {
			boot($that);
			return $that->_cache['upcoming'][0]->show->hosts;
		});

		add_shortcode( 'showplan-now-start', function ($atts) use ($that) {
			boot($that);
			return substr("00" . (($that->_cache['upcoming'][0]->start_time % 86400) / 3600 | 0), -2, 2) . ":00";
		});

		add_shortcode( 'showplan-now-end', function ($atts) use ($that) {
			boot($that);
			return substr("00" . (($that->_cache['upcoming'][0]->end_time % 86400) / 3600 | 0), -2, 2) . ":00";
		});



		add_shortcode( 'showplan-next-title', function ($atts) use ($that) {
			boot($that);
			return $that->_cache['upcoming'][1]->show->name;
		});

		add_shortcode( 'showplan-next-description', function ($atts) use ($that) {
			boot($that);
			return $that->_cache['upcoming'][1]->show->description;
		});

		add_shortcode( 'showplan-next-hosts', function ($atts) use ($that) {
			boot($that);
			return $that->_cache['upcoming'][1]->show->hosts;
		});

		add_shortcode( 'showplan-next-start', function ($atts) use ($that) {
			boot($that);
			return substr("00" . (($that->_cache['upcoming'][1]->start_time % 86400) / 3600 | 0), -2, 2) . ":00";
		});

		add_shortcode( 'showplan-next-end', function ($atts) use ($that) {
			boot($that);
			return substr("00" . (($that->_cache['upcoming'][1]->end_time % 86400) / 3600 | 0), -2, 2) . ":00";
		});

	}

	public function get_station ($station) {

		if (is_numeric($station)) {
			$station = Station::find($station);
		}

		return $station;

	}

	public function get_guide ($station) {

		if (is_numeric($station)) {
			$station = Station::find($station);
		}

		$_compiled_times = CompiledTime::where('station_id', $station->id)->get();

		return $_compiled_times;

	}

	/**
	 * Returns the upcoming show, or the default sustainers. If there is no content available, will return a sustainer
	 * It's not (too) slow
	 * @return Array featuring the current, next, and later show. 
	 */
	public function get_upcoming ($station) {

		if (is_numeric($station)) {
			$station = Station::find($station);
		}

		$tz = new \DateTimeZone(get_option('timezone_string'));

		$_now = Compiler::timestamp_to_future_localised(time(), $tz);

		$_count = 3;
		$_shows = [CompiledTime::get_show_at($_now, $station) ?: CompiledTime::sustainer($_now, null, false, $tz)];

		for ($i = 1; $i < $_count; $i++) {
			$_last = end($_shows);
			if ($_last == null) {
				break;
			}

			$_show = CompiledTime::get_show_at($_last->end_time, $station) ?: CompiledTime::sustainer($_last->end_time, null, false, $tz);
			$_shows[] = $_show;
		}

		return $_shows;

	}

}
