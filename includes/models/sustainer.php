<?php
namespace Showplan\Models;
use Showplan\Controller;

require_once 'model.php';

class Sustainer extends Model {

	protected static $table_name = 'sustainers';
	protected static $columns = ['id', 'hour', 'show_id'];

	protected static $cache = [];

	private static function get_sustainers ($cache = true) {

		$_sustainers = $cache ? \Showplan\Frontend::cache_get('load-all', 'showplan-sustainer') : self::$cache;

		if ($_sustainers == null) {
			$_sustainers = Sustainer::all();
			$_sustainers = array_map(function ($a) { return array($a, $a->show); }, $_sustainers);

			usort($_sustainers, function ($a, $b) { return $a[0]->hour > $b[0]->hour; });

			self::$cache = $_sustainers;
			if ($cache) {
				\Showplan\Frontend::cache_add('load-all', $_sustainers, 'showplan-sustainer');
			}
		}

		if (sizeof($_sustainers) == 0) {
			throw new \Exception('No sustainers defined. Please add at least one.');
		}
		return $_sustainers;

	}

	public static function at ($timestamp, $cache = true) {

		$_sustainers = self::get_sustainers($cache);
		$_hour = ($timestamp % 86400) / 3600 | 0;

		// Always default to the latest sustainer
		$_the_show = $_sustainers[sizeof($_sustainers) - 1][1];

		foreach ($_sustainers as $_sus) {
			list ($_sustainer, $_show) = $_sus;
			if ($_sustainer->hour > $_hour) {
				break;
			}
			$_the_show = $_show;
		}

		return $_the_show;

	}

	/**
	 * @return [the show at the timestamp, the starting hour of that show (0-23), the ending hour of that show (0-23)]
	 */
	public static function full_at ($timestamp, $cache = true) {

		$_sustainers = self::get_sustainers($cache);
		$_hour = $_start_hour = ($timestamp % 86400) / 3600 | 0;
		$_end_hour = ($_start_hour + 1) % 24;

		$_the_show = $_sustainers[sizeof($_sustainers) - 1][1];

		for ($i = 0; $i < sizeof($_sustainers); $i++) {
			list ($_sustainer, $_show) = $_sustainers[$i];
			if ($_sustainer->hour > $_hour) {
				break;
			}
			$_start_hour = $_hour;
			$_end_hour = (int) $_sustainers[($i + 1) % sizeof($_sustainers)][0]->hour;
			$_the_show = $_show;
		}

		return [$_the_show, $_start_hour, $_end_hour];

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
	
}