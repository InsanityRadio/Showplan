<?php
namespace Showplan\Controller;
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once 'controller.php';

use Showplan\Models\Station;
use Showplan\Models\Term;

class Terms extends Controller {

	public function __construct () {
		
		parent::__construct('showplan-terms');
		add_submenu_page('showplan-override', 'Terms', 'Terms', 'manage_options', 'showplan-terms', array($this, 'render'));
		$this->table = new TermListTable();

	}

	public function render_home () {

		echo '<div class="wrap"><h2>Calendar Terms</h2>';
		echo '<a href="?page=showplan-terms&action=create" style="float: right" class="button">Create</a>';

		$this->table->prepare();
		$this->table->display();

		echo '</div>';

	}

	public function render_delete () {

		$_terms = [Term::find($_GET['id'])];

?>
		<form action="" method="post">
		<?php \Showplan\Frontend::nonce_field('showplan_terms_delete'); ?>
		<div class="wrap">
		<h1>Delete Terms</h1>

			<p>You have specified this term for deletion:</p>
			<p>(NOTE: This won't update the live schedule.</p>

		<ul>
		<?php foreach ($_terms as $_term): ?>
		<li><input type="hidden" name="terms[]" value="<?php echo $_term->id; ?>" />ID #<?php echo $_term->id; ?>: <?php echo esc_html($_term->reference); ?></li>
		<?php endforeach; ?>
			</ul>
			<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Confirm Deletion"  /></p></div>
		</form>
<?php

	}

	public function render_edit ($term = null) {

		if (!$term)
			$term = Term::find($_GET['id']);

		$_stations = Station::all();
?>
		
		<div class='wrap'>
			<h2>Edit Calendar Term Settings</h2>
			<form action="" method="post">
				<?php \Showplan\Frontend::nonce_field('showplan_terms_edit'); ?>
				<table class="form-table">
						<tr valign="top">
							<th scope="row">Station</th>
							<td>
								<select name="term[station_id]">
<?php foreach ($_stations as $_station): ?>
									<option value="<?php echo esc_attr($_station->id); ?>">
										<?php echo esc_html($_station->name); ?> (<?php echo esc_html($_station->reference); ?>)
									</option>
<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">Reference</th>
							<td>
								<input type="text" name="term[reference]" value="<?php echo esc_attr($term->reference); ?>"/>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">Start Date</th>
							<td>
								<input type="text" name="term[start_time]" value="<?php echo esc_attr($term->start_time); ?>"/>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">End Date</th>
							<td>
								<input type="text" name="term[end_time]" value="<?php echo esc_attr($term->end_time); ?>"/>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">Weeks In Schedule</th>
							<td>
								<input type="text" name="term[total_weeks]" value="<?php echo esc_attr($term->total_weeks); ?>"/>
							</td>
						</tr>

				</table>
				<input type="submit" value="Save" />

			</form>
		</div>
		
<?php
	}

	public function render_create () {

		$this->render_edit(Term::create());

	}

	private function update ($term) {
		$term->reference = $_POST['term']['reference'];
		$term->station_id = $_POST['term']['station_id'];
		$term->start_time = $_POST['term']['start_time'];
		$term->end_time = $_POST['term']['end_time'];
		$term->total_weeks = $_POST['term']['total_weeks'];
		$term->save();
	}

	public function post_edit () {

		if (!\Showplan\Frontend::verify_nonce($_REQUEST['_wpnonce'], 'showplan_terms_edit')) {
		 \Showplan\Frontend::die('Security fail!');
		}

		$this->update(Term::find($_GET['id']));
		echo '<meta http-equiv="refresh" content="0;url=' . esc_attr($this->get_uri(false)) . '" />';
		exit;

	}

	public function post_create () {

		if (!\Showplan\Frontend::verify_nonce($_REQUEST['_wpnonce'], 'showplan_terms_edit')) {
		 \Showplan\Frontend::die('Security fail!');
		}

		$this->update(Term::create());

		echo '<meta http-equiv="refresh" content="0;url=' . esc_attr($this->get_uri(false)) . '" />';
		exit;

	}

	public function post_delete () {

		if (!\Showplan\Frontend::verify_nonce($_REQUEST['_wpnonce'], 'showplan_terms_delete')) {
		 \Showplan\Frontend::die('Security fail!');
		}

		$_ids = $_POST['terms'];
		foreach ($_ids as &$_id) {
			$_id = Term::find($_id);
		}

		foreach ($_ids as $_term) {
			$_term->remove();
		}
		echo '<meta http-equiv="refresh" content="0;url=' . esc_attr($this->get_uri(false)) . '" />';
		exit;

	}
}

class TermListTable extends \Showplan\List_Table {

	public function prepare () {

		$this->_column_headers = array(
			$this->get_columns(),
			$this->get_hidden_columns(),
			$this->get_sortable_columns()
		);

		$this->items = array_map(function($a) {
			$b = $a->__array();
			$b['station'] = $a->station->reference;
			return $b;
		}, $this->table_data());

        $this->set_pagination_args( array(
            'total_items' => sizeof($this->items),
            'per_page'    => sizeof($this->items),
        ) );

	}

	public function get_columns () {
		return array(
			'id' => 'ID',
			'reference' => 'Reference',
			'station' => 'Station',
			'start_time' => 'Start Date',
			'end_time' => 'End Date',
			'total_weeks' => 'Weeks Per Cycle'
		);
	}

	public function get_hidden_columns () {
		return [];
	}

	public function get_sortable_columns () {
		return [];
	}

	private function table_data () {

		return Term::all();

	}

	public function column_default ($item, $column) {

		switch ($column) {
			case 'start_time': case 'end_time':
				return gmdate('j M Y H:i', $item[$column]);
		}
		return $item[$column];

	}

	public function column_reference ($item) {
		$_actions = array(
			'assign' => sprintf('<a href="?page=%s&action=%s&id=%s">Assign</a>', 'showplan-show-times', 'assign', $item['id']),
			'edit' => sprintf('<a href="?page=%s&action=%s&id=%s">Edit Settings</a>', $_REQUEST['page'], 'edit', $item['id']),
			'delete' => sprintf('<a href="?page=%s&action=%s&id=%s">Delete</a>', $_REQUEST['page'], 'delete', $item['id']),
		);
		return sprintf('%s %s', $item['reference'], $this->row_actions($_actions));
	}

}