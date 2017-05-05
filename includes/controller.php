<?php
namespace Showplan;
require_once 'helpers/wordpress.php';

require_once 'compiler.php';
require_once 'data.php';

require_once 'controller/categories.php';
require_once 'controller/override.php';
require_once 'controller/shows.php';
require_once 'controller/show_plan.php';
require_once 'controller/stations.php';
require_once 'controller/terms.php';

require_once 'models/category.php';
require_once 'models/compiled_time.php';
require_once 'models/override.php';
require_once 'models/show.php';
require_once 'models/show_time.php';
require_once 'models/station.php';
require_once 'models/sustainer.php';
require_once 'models/term.php';

use \Showplan\Compiler;
use \Showplan\Data;
use \Showplan\Models\Category;
use \Showplan\Models\Station;
use \Showplan\Models\Term;
use \Showplan\Frontend;


/**
 * Controller manages all of the WordPress-specifics. 
 */
class Controller {
	
	public static $prefix;

	/**
	 * Make all necessary WordPress calls to do stuff
	 */
	public static function bootstrap ($FILE) {

		global $wpdb;
		self::$prefix = $wpdb->prefix . 'showplan_';

		register_activation_hook($FILE, array('\Showplan\Controller', 'install'));
		add_action('admin_menu', array('\Showplan\Controller', 'admin_menu'));
		add_action('showtime_compile_timetable', array('\Showplan\Controller', 'compile'));
		add_action('admin_enqueue_scripts', array('\Showplan\Controller', 'admin_link'));

		$_data = new Data();
		$_data->bootstrap();

		$args = [false];
		if (!\Showplan\Frontend::next_scheduled('showplan_compile_timetable', $args)) {
			\Showplan\Frontend::schedule_event(strtotime('00:00 UTC'), 'daily', 'showtime_compile_timetable', $args);
		}

	}

	public static function admin_link ($hook) {
		if ($hook !== 'showplan_page_showplan-show-times' && $hook !== 'toplevel_page_showplan-override') {
			return;
		}
		\Showplan\Frontend::enqueue_script('showplan_table', plugins_url('js/admin.js', dirname(__FILE__)), false);
		\Showplan\Frontend::enqueue_style('showplan_table', plugins_url('css/admin.css', dirname(__FILE__)));
	}

	/**
	 * Daily cron to generate the timetable for the next fortnight.
	 * Runs at around midnight. 
	 */
	public static function compile () {

		$_stations = Station::all();
		foreach ($_stations as $_station) {
			$_c = new Compiler($_station);
			$_c->compileDefaults();
		}

	}

	public static function admin_menu () {

		// TODO
		add_menu_page('Showplan Schedule', 'Showplan', 'manage_options', 'showplan-override', null);
		new Controller\Overrides();
		new Controller\ShowPlan();
		new Controller\Shows();
		new Controller\Terms();
		new Controller\Categories();
		new Controller\Stations();

	}

	public static function render () {
	}

	public static function install ($FILE) {

		global $wpdb;
		$_query = self::generate_tables();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$wpdb->query('SET sql_mode=\'NO_AUTO_VALUE_ON_ZERO\';');
		dbDelta($_query);

		if (!sizeof(Category::all())) {
			array_map(function ($a) use ($wpdb) { $wpdb->query($a); }, self::generate_default_data());
		}

	}

	/**
	 * prefix_stations: Stores a list of stations that are available
	 * 
	 * prefix_categories: Stores a list of show categories and metadata, etc.
	 * 
	 * prefix_terms: Scheduling periods (called terms because of Uni terms, duh)
	 *		id, station_id
	 * 		start_time When to start this calendar
	 * 		end_time When to finish this calendar
	 * 		total_weeks How many weeks are in this calendar (ie. repeats weekly, A/B, etc.)
	 * 	
	 * prefix_shows
	 *		id, name, description, hosts
	 *		public The 
	 * 		category_id The category under which this 
	 * 
	 * prefix_show_times
	 *		id
	 * 		week The week under which this show is timed (see prefix_terms(total_weeks).)
	 *		day, hour, minute The start time of the show (day 0 = Monday)
	 * 		length The show length in minutes
	 * 		show_id / term_id
	 * 
	 * prefix_overrides
	 *		id
	 *		week Number of weeks since the start of this schedule to make effective
	 *		day, hour, minute The start time of the show (day 0 = Monday)
	 * 		length The show length in minutes
	 *		show_id / term_id
	 *		
	 * prefix_compiled_times
	 *		id
	 * 		iteration_id
	 *		start_date, end_date A UNIX timestamp (in UTC) f
	 * 		length The show length in minutes. Should be aware of TZ changes. 
	 * 		show_id / term_id
	 * 
	 */

	public static function generate_tables () {

		$_tables  = 'CREATE TABLE `' . self::$prefix . 'stations` (
			id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
			reference VARCHAR(255) UNIQUE KEY,
			current_iteration_id INT,
			name TEXT,
			description TEXT
		);';

		$_tables .= 'CREATE TABLE `' . self::$prefix . 'categories` (
			id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
			reference VARCHAR(255),
			name TEXT,
			description TEXT
		);';

		$_tables .= 'CREATE TABLE `' . self::$prefix . 'terms` (
			id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
			reference VARCHAR(255) NOT NULL UNIQUE KEY,
			station_id INT NOT NULL REFERENCES `' . self::$prefix . 'stations`(`id`)
				ON DELETE CASCADE ON UPDATE CASCADE,
			start_time BIGINT NOT NULL,
			end_time BIGINT NOT NULL,
			total_weeks INT NOT NULL DEFAULT 1
		);';

		$_tables .= 'CREATE TABLE `' . self::$prefix . 'shows` (
			id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
			name TEXT NOT NULL,
			description TEXT NOT NULL,
			hosts TEXT NOT NULL,
			public INT DEFAULT 1,
			one_liner VARCHAR(255),
			category_id INT NOT NULL REFERENCES `' . self::$prefix . 'categories`(`id`)
				ON DELETE CASCADE ON UPDATE CASCADE
		);';

		$_tables .= 'CREATE TABLE `' . self::$prefix . 'sustainers` (
			id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
			hour INT NOT NULL UNIQUE,
			show_id INT NOT NULL REFERENCES `' . self::$prefix . 'shows`(`id`)
				ON DELETE CASCADE ON UPDATE CASCADE
		);';

		$_tables .= 'CREATE TABLE `' . self::$prefix . 'show_times` (
			id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
			week INT DEFAULT 0,
			day INT DEFAULT 0,
			hour INT DEFAULT 0,
			minute INT DEFAULT 0,
			length INT DEFAULT 60,
			show_id INT NOT NULL REFERENCES `' . self::$prefix . 'shows`(`id`)
				ON DELETE CASCADE ON UPDATE CASCADE,
			term_id INT NOT NULL REFERENCES `' . self::$prefix . 'terms`(`id`)
				ON DELETE CASCADE ON UPDATE CASCADE
		);';

		$_tables .= 'CREATE TABLE `' . self::$prefix . 'overrides` (
			id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
'/*			week INT DEFAULT 0,
			day INT DEFAULT 0,
			hour INT DEFAULT 0,
			minute INT DEFAULT 0,
			length INT DEFAULT 60, */ . '
			start_time BIGINT,
			end_time BIGINT,
			
			show_id INT NOT NULL REFERENCES `' . self::$prefix . 'shows`(`id`)
				ON DELETE CASCADE ON UPDATE CASCADE,
			station_id INT NOT NULL REFERENCES `' . self::$prefix . 'stations`(`id`)
				ON DELETE CASCADE ON UPDATE CASCADE
		);';

		// This allows changes to be published after updates are made and is faster. :-)
		// Also helps lots with timezone adjustments. 
		$_tables .= 'CREATE TABLE `' . self::$prefix . 'compiled_times` (
			id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
			iteration_id INT NOT NULL,
			start_time BIGINT,
			end_time BIGINT,
			start_time_local BIGINT,
			end_time_local BIGINT,
			show_id INT NOT NULL REFERENCES `' . self::$prefix . 'shows`(`id`)
				ON DELETE CASCADE ON UPDATE CASCADE,
			station_id INT NOT NULL REFERENCES `' . self::$prefix . 'stations`(`id`)
				ON DELETE CASCADE ON UPDATE CASCADE
		);';

		return $_tables;

	}

	public static function generate_default_data () {

		$_tables[] = 'INSERT INTO `' . self::$prefix . 'categories` VALUES (0, "sustainer", "Sustainer", "Full Automation");';
		$_tables[] = 'INSERT INTO `' . self::$prefix . 'categories` VALUES (1, "chart", "Entertainment", "Entertainment");';
		$_tables[] = 'INSERT INTO `' . self::$prefix . 'categories` VALUES (2, "topic", "Topical", "Talk Show");';
		$_tables[] = 'INSERT INTO `' . self::$prefix . 'categories` VALUES (3, "specialist", "Specialist", "Specialist Music Show");';

		$_tables[] = 'INSERT INTO `' . self::$prefix . 'shows` VALUES (1, "Music Through The Night", "", "", 1, "", 0);';
		$_tables[] = 'INSERT INTO `' . self::$prefix . 'shows` VALUES (2, "Music Through The Morning", "", "", 1, "", 0);';
		$_tables[] = 'INSERT INTO `' . self::$prefix . 'shows` VALUES (3, "Music Through The Afternoon", "", "", 1, "", 0);';
		$_tables[] = 'INSERT INTO `' . self::$prefix . 'shows` VALUES (4, "Music Through The Evening", "", "", 1, "", 0);';

		// $_tables .= 'INSERT INTO `' . self::$prefix . 'sustainers` VALUES (4, 0, 1);';
		$_tables[] = 'INSERT INTO `' . self::$prefix . 'sustainers` VALUES (1, 5, 2);';
		$_tables[] = 'INSERT INTO `' . self::$prefix . 'sustainers` VALUES (2, 12, 3);';
		$_tables[] = 'INSERT INTO `' . self::$prefix . 'sustainers` VALUES (3, 17, 4);';
		$_tables[] = 'INSERT INTO `' . self::$prefix . 'sustainers` VALUES (4, 22, 1);';

		return $_tables;
	}

}
