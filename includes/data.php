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
use \Showplan\Models\Show;

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

	public static function get_midnight ($opposite_days = 0) {
		$_ts = Compiler::invert_timestamp_localised(time(), get_option('timezone_string'));
		return $_ts - ($_ts % 86400) - 86400 * $opposite_days;
	}

	public function get_schedule ($station, $days = 7, $sustainer = true) {

		$_schedule = $this->get_guide($station);

		// Work out what day it is and get its start in GMT
		// in BST, at 00:00, we should thus get 
		$_start = self::get_midnight(3);
		$_end = $_start + 86400 * $days;

		$_result = [];

		foreach ($_schedule as $_sch) {
			if ($_sch->start_time < $_start
					|| $_sch->start_time > $_end
					|| (!$sustainer && $_sch->show->category_id == 0)) {
				continue;
			}
			$_day = $_sch->start_time - ($_sch->start_time % 86400);
			$_result[$_day][] = $_sch;
		}

		ksort($_result);

		return $_result;

	}

	public function bootstrap () {
		$that = $this;
		function boot ($that, $atts) {
			$atts = shortcode_atts(array('station' => 1), $atts);
			if (!$that->_cache['upcoming']) {
				$that->_cache['upcoming'] = $that->get_upcoming($atts['station']);
			}
		}

		add_shortcode( 'showplan-schedule', function ($atts) use ($that) {
			
			$atts = shortcode_atts(array('station' => 1, 'days' => 10, 'sustainer' => 1, 'images' => 0), $atts);

			\Showplan\Frontend::enqueue_script('showplan_front', plugins_url('js/tabs.js', dirname(__FILE__)), false);
			\Showplan\Frontend::enqueue_style('showplan_front', plugins_url('css/tabs.css', dirname(__FILE__)));
			
			if (($data = apply_filters('showplan_schedule_inject', $atts)) !== null) {
				return $data;
			}

			$_schedule = $that->get_schedule($atts['station'], $atts['days'], $atts['sustainer']);
			$_midnight = Data::get_midnight();

			$_data = '<div class="showplan-schedule-container' . ($atts['images'] ? '' : ' no-images') . '">';
			$_data .= '<table class="showplan-schedule-menu"><tr>';
			$_days = [0];
			foreach ($_schedule as $_day => $_shows) {

				$_dow = $_day == $_midnight ? 'Today' : gmdate("D", $_day);

				$_data .= '<td class="showplan-schedule-tab' . ($_dow == 'Today' ? ' today' : '') . '" for=".showplan-day-' . $_day . '">
					<span>' . $_dow . '</span><br />
					<span>' . gmdate("j M", $_day) . '</span>
				</td>';

			}

			$_data .= '	</tr></table>';
			$_data .= '<div class="showplan-tabs">';

			foreach ($_schedule as $_day => $_shows) {

				$_today = $_day == $_midnight ? ' today' : '';
				$_data .= '<div class="showplan-tab showplan-day-' . $_day . $_today . '">';

				foreach ($_shows as $_show) {

					$_show_name = apply_filters('showplan_schedule_widget_title', esc_html($_show->show->name), $_show);
					$_show_hosts = apply_filters('showplan_schedule_widget_hosts', esc_html($_show->show->hosts), $_show);
					$_show_description = apply_filters('showplan_schedule_widget_description', esc_html($_show->show->description), $_show);

					$_oa = ($_show->start_time_local <= time() && $_show->end_time_local > time());
					$_data .= '<div data-start-time="' . $_show->start_time . '" data-end-time="' . $_show->end_time . '" class="showplan-show' . ($_oa ? ' showplan-on-air' : '') . ' showplan-category-' . ($_show->show->category_id) . '"><div>';
					$_data .= '<div>' . gmdate("H:i", $_show->start_time) . '<span class="showplan-end-time">- ' . gmdate("H:i", $_show->end_time) . '</span>';
					if ($_oa) {
						$_data .= '<div class="showplan-on-air-indicator">ON AIR</div>';
					}
					$_data .= '</div>';
					if ($atts["images"] == "1" && $_show->show->media_url) {
						$_data .= '<div style="vertical-align:middle;padding-right:15px"><img src="' . esc_attr($_show->show->media_url) . '" /></div>';
					} else {
						$_data .= '<div></div>';
					}
					$_data .= '<div>';
					$_data .= '<h3>' . $_show_name . '</h3>';
					$_data .= '<span>' . $_show_hosts . '</span>';
					$_data .= '<p>' . $_show_description . '</p>';
					$_data .= '</div>';
					$_data .= '</div></div>';
				}

				$_data .= '</div>';

			}

			$_data .= '</div></div>';
			return $_data;
		});
		// Ugh. 

		add_shortcode( 'showplan-now-title', function ($atts) use ($that) {
			boot($that, $atts);
			return $that->_cache['upcoming'][0]->show->name;
		});
		
		add_shortcode( 'showplan-now-image', function ($atts) use ($that) {
			boot($that, $atts);
			return '<img class="showplan-show-image" src="' . $that->_cache['upcoming'][0]->show->media_url . '" />';
 		});

		add_shortcode( 'showplan-now-description', function ($atts) use ($that) {
			boot($that, $atts);
			return $that->_cache['upcoming'][0]->show->description;
		});

		add_shortcode( 'showplan-now-hosts', function ($atts) use ($that) {
			boot($that, $atts);
			return $that->_cache['upcoming'][0]->show->hosts;
		});

		add_shortcode( 'showplan-now-start', function ($atts) use ($that) {
			boot($that, $atts);
			return substr("00" . (($that->_cache['upcoming'][0]->start_time % 86400) / 3600 | 0), -2, 2) . ":00";
		});

		add_shortcode( 'showplan-now-end', function ($atts) use ($that) {
			boot($that, $atts);
			return substr("00" . (($that->_cache['upcoming'][0]->end_time % 86400) / 3600 | 0), -2, 2) . ":00";
		});



		add_shortcode( 'showplan-next-title', function ($atts) use ($that) {
			boot($that, $atts);
			return $that->_cache['upcoming'][1]->show->name;
		});
		
		add_shortcode( 'showplan-now-image', function ($atts) use ($that) {
			boot($that, $atts);
			return '<img class="showplan-show-image" src="' . $that->_cache['upcoming'][1]->show->media_url . '" />';
 		});

		add_shortcode( 'showplan-next-description', function ($atts) use ($that) {
			boot($that, $atts);
			return $that->_cache['upcoming'][1]->show->description;
		});

		add_shortcode( 'showplan-next-hosts', function ($atts) use ($that) {
			boot($that, $atts);
			return $that->_cache['upcoming'][1]->show->hosts;
		});

		add_shortcode( 'showplan-next-start', function ($atts) use ($that) {
			boot($that, $atts);
			return substr("00" . (($that->_cache['upcoming'][1]->start_time % 86400) / 3600 | 0), -2, 2) . ":00";
		});

		add_shortcode( 'showplan-next-end', function ($atts) use ($that) {
			boot($that, $atts);
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

		// Strip our timezone, we don't care about it. 
		$_now = Compiler::invert_timestamp_localised(time(), $tz);

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

	public function get_show ($station) {
		$show = Show::find((int) $_GET['show_id']);
		return $show;
	}

}
