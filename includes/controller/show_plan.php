<?php
namespace Showplan\Controller;
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once 'controller.php';

use Showplan\Compiler;
use Showplan\Models\Category;
use ShowPlan\Models\CompiledTime;
use Showplan\Models\Show;
use Showplan\Models\ShowTime;
use Showplan\Models\Station;
use Showplan\Models\Term;
use \Exception;

/**
 * The Station controller allows us to manage 
 */
class ShowPlan extends Controller {

	public function __construct () {
		
		parent::__construct('showplan-show-times');
		add_submenu_page('showplan-override', 'Schedule Template', 'Schedule Template', 'manage_options', 'showplan-show-times', array($this, 'render'));

	}

	private function get_data ($_term) {

		$_template = $_term->show_times;

		return json_encode(array_map(function ($a) {
			$b = $a->__array();
			$b['show'] = $a->show->__array();
			return $b;
		}, $_template));

	}

	public function render_compile () {

		if (!\Showplan\Frontend::verify_nonce($_GET['k'], 'showplan-compile')) {
			wp_die('Access Denied');
		}

		$_c = new Compiler(Station::find(1));
		$_c->compileDefaults();

		$this->render_home();

	}

	public function render_dump () {

		$_a = CompiledTime::all();

		usort($_a, function ($a, $b) { return $a->start_time > $b->start_time; });

		for ($i = 0; $i < sizeof($_a); $i++) {
			$_comp = $_a[$i];
			echo "<p>" . $_comp->show->name . ' : ' . gmdate(DATE_RFC2822, $_comp->start_time) . "; " . ($_comp->length / 3600) . " hours";

			if($i != sizeof($_a) - 1 && $_comp->end_time != $_a[$i + 1]->start_time) {
				echo "<B>LINE-UP FAIL</b>";
			}

			echo "</p>";
		}

	}

	public function render_home () {
		echo '<meta http-equiv="refresh" content="0;url=' . esc_attr($this->get_uri(false)) . '&action=assign" />';
		return;
	}

	public function render_assign () {

		echo '<div class="wrap">';

		$_shows = Show::all();
		$_categories = Category::all();

		$_term = $_GET['id'] ? Term::find((int) $_GET['id']) : Term::get_current_term();
?>

		<h2>Edit Schedule Template: <b><?php echo esc_html($_term->reference); ?></b></h2>
		<div class="showplan-tools">

			<a href="<?php echo \Showplan\Frontend::nonce_url('./admin.php?page=showplan-show-times&action=compile', 'showplan-compile', 'k'); ?>" class="button" style="float: left; margin-right: 5px;">Publish</a> <p style="margin: -5px 0 0 0; font-size: 9pt;">Auto-publishing in<br /><span id="showplan-publish-countdown">00:30:00</span></p>

			<div id="showplan-tools-assign" style="display: none">

				<h2>Assign Show</h2>

				<form action="" method="post">

					<?php \Showplan\Frontend::nonce_field('showplan_template_edit'); ?>
					<input type="hidden" name="action" value="assign" />
					<input type="hidden" name="type" value="template" />
					<input type="hidden" name="term[id]" value="<?php echo $_term->id; ?>" />
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

					<?php \Showplan\Frontend::nonce_field('showplan_template_edit'); ?>
					<input type="hidden" name="action" value="create;assign" />
					<input type="hidden" name="type" value="template" />
					<input type="hidden" name="term[id]" value="<?php echo $_term->id; ?>" />
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
				<a href="admin.php?page=showplan-shows&action=edit&id=1"><button class="button">Edit Show</button></a>
				<form action="" method="post">

					<?php \Showplan\Frontend::nonce_field('showplan_template_delete'); ?>
					<input type="hidden" name="action" value="delete" />
					<input type="hidden" name="type" value="template" />
					<input type="hidden" id="showplan-tools-remove-ids" name="ids" value="" />

					<button role="submit" class="button">Remove</button>

				</form>

			</div>

		</div>

		<table class="showplan-table-head">
			<thead>
				<tr>
					<th></th>
					<th>Monday</th>
					<th>Tuesday</th>
					<th>Wednesday</th>
					<th>Thursday</th>
					<th>Friday</th>
					<th>Saturday</th>
					<th>Sunday</th>
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
						<td id="showplan-schedule-<?php echo $j . 'T' . $i . 'Z'; ?>"></td>
<?php endfor; ?>
					</tr>
<?php endfor; ?>

				</tbody>

			</table>
		</div>
		<br />
		<em>Click a grid section to fll it. Leave hours unassigned for automation. Press Shift to select a range. Double click to deselect.</em>

		<script type="text/javascript">
			var showData = <?php echo $this->get_data($_term); ?>, showplanTable = true;
		</script>

<?php
		echo '</div>';

	}

	public function post_assign () {

		switch ($_POST['action']) {
			case 'assign':
				return $this->post_assign_assign();
			case 'delete':
				return $this->post_assign_delete();

			case 'create;assign':
				$_show = Show::create();
				\Showplan\Controller\Shows::update($_show);
				$_POST['show']['id'] = $_show->id;
				return $this->post_assign_assign();

			default:
				\Showplan\Frontend::_die('Unrecognised action');
		}

	}

	public function post_assign_assign () {

		if (!\Showplan\Frontend::verify_nonce($_REQUEST['_wpnonce'], 'showplan_template_edit')) {
			\Showplan\Frontend::_die('Security fail!');
		}

		$_the_show = Show::find((int) $_POST['show']['id']);
		$_the_term = Term::find((int) $_POST['term']['id']);
		$_raw_times = explode(";", $_POST['times']);

		$_times = array();
		foreach ($_raw_times as &$_time) {
			$_matches = array();
			if (!preg_match('/^([0-7])T([01]?[0-9]|2[0-3])Z$/', $_time, $_matches)) {
				throw new Exception('Illegal data passed');
			}
			$_matches[1]; $_matches[2];
			if (!$_times[$_matches[1]])
				$_times[$_matches[1]] = array();

			$_times[$_matches[1]][$_matches[2]] = $_the_show;
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
					$_show_times[$_label]->length = $_hours * 60;
				} else {
					$_show_times[$_label] = ShowTime::create();
					$_show_times[$_label]->show = $_show;
					$_show_times[$_label]->week = 0; // TODO
					$_show_times[$_label]->day = $_day;
					$_show_times[$_label]->hour = $_hour;
					$_show_times[$_label]->minute = 0;
					$_show_times[$_label]->term = $_the_term;
					$_show_times[$_label]->length = 60;
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

	public function post_assign_delete () {

		if (!\Showplan\Frontend::verify_nonce($_REQUEST['_wpnonce'], 'showplan_template_delete')) {
			\Showplan\Frontend::_die('Security fail!');
		}

		$_ids = explode(";", $_POST['ids']);

		foreach ($_ids as $_id) {
			$_model = ShowTime::find((int) $_id);
			$_model->remove();
		}

		// echo '<meta http-equiv="refresh" content="0;url=' . esc_attr($this->get_uri(false)) . '" />';
		echo '<meta http-equiv="refresh" content="0" />';
		exit;

	}


}

