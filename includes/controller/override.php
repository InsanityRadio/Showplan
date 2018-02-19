<?php
namespace Showplan\Controller;
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once 'controller.php';

use Showplan\Compiler;
use Showplan\Models\Category;
use ShowPlan\Models\CompiledTime;
use Showplan\Models\Override;
use Showplan\Models\Show;
use Showplan\Models\ShowTime;
use Showplan\Models\Station;
use Showplan\Models\Term;
use \Exception;

/**
 * The Station controller allows us to manage 
 */
class Overrides extends Controller {

	public function __construct () {
		
		parent::__construct('showplan-override');
		add_submenu_page('showplan-override', 'Schedule Override', 'Overrides', 'manage_options', 'showplan-override', array($this, 'render'));

	}

	private function get_data ($station) {

		$_template = $station->overrides;

		return json_encode(array_map(function ($a) {
			$b = $a->__array();
			try {
				$b['show'] = $a->show->__array();
			} catch (Exception $e) {
				$b['show'] = null;
			}
			return $b;
		}, $_template));

	}

	private function get_ghost_data ($station, $base) {

		$_data = [];
		for ($i = 0; $i < 168; $i++) {
			$_show = ShowTime::get_show_at($base + $i * 3600);
			if (!$_show) {
				continue;
			}
			$b = $_show->__array();
			$b['show'] = $_show->show->__array();
			$_data[] = $b;
		}

		return json_encode($_data);

	}

	public function render_home () {
		return $this->render_assign();
	}

	public function render_assign () {

		echo '<div class="wrap">';

		$_shows = Show::all();
		$_categories = Category::all();

		$_station = $_GET['id'] ? Station::find($_GET['id']) : Station::find(1);

		$_start = $_GET['date'] ?: time();
		$_base = strtotime('last monday', $_start + 86400); // start_of_week;
?>

		<h2>Edit Overrides: <b><?php echo esc_html($_station->reference); ?></b></h2>
		<div class="showplan-tools">

			<a href="<?php echo \Showplan\Frontend::nonce_url('./admin.php?page=showplan-show-times&action=compile', 'showplan-compile', 'k'); ?>" class="button" style="float: left; margin-right: 5px;">Publish</a> <p style="margin: -5px 0 0 0; font-size: 9pt;">Auto-publishing in<br /><span id="showplan-publish-countdown">00:30:00</span></p>

			<div id="showplan-tools-assign" style="display: none">

				<h2>Assign Show</h2>

				<form action="" method="post">

					<?php \Showplan\Frontend::nonce_field('showplan_override_edit'); ?>
					<input type="hidden" name="action" value="assign" />
					<input type="hidden" name="type" value="template" />
					<input type="hidden" name="station[id]" value="<?php echo $_station->id; ?>" />
					<input type="hidden" name="times" id="showplan-tools-times" value="" />

					<select name="show[id]" id="showplan-tools-select-show">
<?php foreach ($_shows as $_show): ?>
						<option value="<?php echo esc_attr($_show->id); ?>"><?php echo esc_html($_show->name); ?></option>
<?php endforeach; ?>
					</select><br />
					<button class="button" role="submit">Save</button>

				</form>

				<p>
					<b>OR</b> 
					Quick Create Show
				</p>

				<form action="" method="post">

					<?php \Showplan\Frontend::nonce_field('showplan_override_edit'); ?>
					<input type="hidden" name="action" value="create;assign" />
					<input type="hidden" name="type" value="template" />
					<input type="hidden" name="station[id]" value="<?php echo $_station->id; ?>" />
					<input type="hidden" name="times" id="showplan-tools-times2" value="" />
					<p>
						<b>Show Name</b><br />
						<input type="text" name="show[name]" id="showplan-tools-show-name" />
					</p>

					<p>
						<b>Description</b><br />
						<input type="text" name="show[description]" id="showplan-tools-show-desc" />
					</p>

					<p>
						<b>Hosts</b><br />
						<input type="text" name="show[hosts]" id="showplan-tools-show-hosts" />
					</p>

					<p>
						<b>Category</b><br />
						<select name="show[category_id]" id="showplan-tools-show-category">
<?php foreach ($_categories as $_cat): ?>
							<option value="<?php echo esc_attr($_cat->id); ?>"><?php echo esc_html($_cat->name); ?></option>
<?php endforeach; ?>
						</select>
					</p>

					<p>
						<b>Hidden</b><br />
						<input type="checkbox" name="schedule[hidden]" id="showplan-tools-show-hide" />
						<label for="showplan-tools-show-hide">Hide Show From Public (Internal Only)</label>
					</p>
					<button class="button" role="submit">Create Show</button>
				</form>
			</div>

			<div id="showplan-tools-remove" style="display: none">

				<h2>Edit</h2>

				<form action="" method="post">

					<?php \Showplan\Frontend::nonce_field('showplan_template_delete'); ?>
					<input type="hidden" name="action" value="delete" />
					<input type="hidden" name="type" value="template" />
					<input type="hidden" name="station[id]" value="<?php echo $_station->id; ?>" />
					<input type="hidden" id="showplan-tools-remove-ids" name="ids" value="" />

					<button role="submit" class="button">Remove</button>

				</form>

			</div>

		</div>

		<table class="showplan-table-head">
			<thead>
				<tr>
					<th>
						<a href="?page=showplan-override&amp;id=<?php echo $_station->id; ?>&amp;date=<?php echo $_base - 7*86400;?>">
							&lt;
						</a>&nbsp;&nbsp;
						<a href="?page=showplan-override&amp;id=<?php echo $_station->id; ?>&amp;date=<?php echo $_base + 7*86400;?>">
							&gt;
						</a></th>
<?php for ($i = 0; $i < 7; $i++): ?>
					<th><?php echo gmdate("l<\\b\\r />j M", $_base + $i*86400); ?></th>
<?php endfor; ?>
				</tr>
			</thead>
		</table>

		<div class="showplan-table-wrap">
			
			<table>

				<tbody id="showplan-schedule-table" week="0">

<?php for ($i = 0; $i < 24; $i++): ?>
					<tr>
						<td><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>:00</td>
<?php for ($j = 0; $j < 7; $j++): ?>
						<td id="showplan-schedule-<?php echo $j . 'T' . $i . 'Z'; ?>" date="<?php echo $_base + 3600 * $i + 86400 * $j; ?>"></td>
<?php endfor; ?>
					</tr>
<?php endfor; ?>

				</tbody>

			</table>
		</div>
		<br />
		<em>Click a grid section to fll it. Leave hours unassigned for automation. Press Shift to select a range. Double click to deselect.</em>

		<script type="text/javascript">
		<?php try { ?>
			var ghostData = <?php echo $this->get_ghost_data($_station, $_base); ?>;
		<?php } catch (Exception $e) {
			// same.
		?>
			[];
		<?php }; ?>
			var showData = <?php echo $this->get_data($_station); ?>, showplanTable = true, showplanStyle = 'dates';
		</script>

<?php
		echo '</div>';

	}

	public function post_home () {

		switch ($_POST['action']) {
			case 'assign':
				return $this->post_home_assign();
			case 'delete':
				return $this->post_home_delete();

			case 'create;assign':
				$_show = Show::create();
				\Showplan\Controller\Shows::update($_show);
				$_POST['show']['id'] = $_show->id;
				return $this->post_home_assign();

			default:
			 \Showplan\Frontend::_die('Unrecognised action');
		}

	}

	public function post_home_assign () {

		if (!\Showplan\Frontend::verify_nonce($_REQUEST['_wpnonce'], 'showplan_override_edit')) {
		 \Showplan\Frontend::_die('Security fail!');
		}

		$_the_show = Show::find($_POST['show']['id']);
		$_the_station = Station::find($_POST['station']['id']);

		$_raw_times = explode(";", $_POST['times']);

		$_times = array();
		foreach ($_raw_times as &$_time) {
			$_matches = array();
			if (!preg_match('/^([0-9]+)U$/', $_time, $_matches)) {
				throw new Exception('Illegal data passed');
			}
			$_day = $_matches[1] - ($_matches[1] % 86400);
			$_hour = floor($_matches[1] - $_day) / 3600;

			if (!$_times[$_matches[1]])
				$_times[$_matches[1]] = array();

			$_times[$_day][$_hour] = $_the_show;
		}

		$_show_times = array();
		foreach ($_times as $_day => $_time) {
			foreach ($_time as $_hour => $_show) {

				$_hours = 1;
				while ($_hour > 0 && $_time[$_hour - 1]->id == $_show->id) {
					$_hour --;
					$_hours ++;
				}
				$_label = $_day . 'T' . $_hour . 'Z';

				if ($_show_times[$_label]) {
					$_show_times[$_label]->end_time = $_show_times[$_label]->start_time + $_hours * 3600;
				} else {
					$_show_times[$_label] = Override::create();
					$_show_times[$_label]->show = $_show;
					$_show_times[$_label]->station = $_the_station;
					$_show_times[$_label]->start_time = $_day + ($_hour * 3600);
					$_show_times[$_label]->end_time = $_show_times[$_label]->start_time + 3600;
				}
			}
		}

		foreach ($_show_times as $_show_time) {
			$_show_time->save();
		}

		// echo '<meta http-equiv="refresh" content="0;url=' . esc_attr($this->get_uri(false)) . '" />';
		echo '<meta http-equiv="refresh" content="0" />';
		exit;

	}

	public function post_home_delete () {

		if (!\Showplan\Frontend::verify_nonce($_REQUEST['_wpnonce'], 'showplan_template_delete')) {
		 \Showplan\Frontend::_die('Security fail!');
		}

		$_ids = explode(";", $_POST['ids']);

		foreach ($_ids as $_id) {
			$_model = Override::find($_id);
			$_model->remove();
		}

		// echo '<meta http-equiv="refresh" content="0;url=' . esc_attr($this->get_uri(false)) . '" />';
		echo '<meta http-equiv="refresh" content="0" />';
		exit;

	}


}

