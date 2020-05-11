<?php
namespace Showplan\Controller;
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once 'controller.php';

use Showplan\Models\Station;

/**
 * The Station controller allows us to manage 
 */
class Stations extends Controller {

	public function __construct () {
		
		parent::__construct('showplan-stations');
		add_submenu_page('showplan-override', 'Stations', 'Stations', 'manage_options', 'showplan-stations', array($this, 'render'));
		$this->table = new StationListTable();

	}

	public function render_home () {

		echo '<div class="wrap"><h2>Radio Stations</h2>';
		echo '<a href="?page=showplan-stations&action=create" style="float: right" class="button">Create</a>';

		$this->table->prepare();
		$this->table->display();

		echo '</div>';

	}

	public function render_edit ($station = null) {

		if (!$station)
			$station = Station::find((int) $_GET['id']);
?>
		
		<div class='wrap'>
			<h2>Edit Station Settings</h2>
			<form action="" method="post">
				<?php \Showplan\Frontend::nonce_field('showplan_stations_edit'); ?>
				<table class="form-table">
						<tr valign="top">
							<th scope="row">Reference</th>
							<td>
								<input type="text" name="station[reference]" value="<?php echo esc_attr($station->reference); ?>"/>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">Reference</th>
							<td>
								<input type="text" name="station[reference]" value="<?php echo esc_attr($station->reference); ?>"/>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">Station Name</th>
							<td>
								<input type="text" name="station[name]" value="<?php echo esc_attr($station->name); ?>"/>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">Station Description</th>
							<td>
								<input type="text" name="station[description]" value="<?php echo esc_attr($station->description); ?>"/>
							</td>
						</tr>

				</table>
				<input type="submit" value="Save" />

			</form>
		</div>
		
<?php
	}

	public function render_create () {

		$this->render_edit(Station::create());

	}

	private function update ($station) {
		$station->name = sanitize_text_field($_POST['station']['name']);
		$station->reference = sanitize_text_field($_POST['station']['reference']);
		$station->description = sanitize_text_field($_POST['station']['description']);
		$station->save();
	}

	public function post_edit () {

		if (!\Showplan\Frontend::verify_nonce($_REQUEST['_wpnonce'], 'showplan_stations_edit')) {
		 \Showplan\Frontend::_die('Security fail!');
		}

		$this->update(Station::find((int) $_GET['id']));
		echo '<meta http-equiv="refresh" content="0;url=' . esc_attr($this->get_uri(false)) . '" />';
		exit;

	}

	public function post_create () {

		if (!\Showplan\Frontend::verify_nonce($_REQUEST['_wpnonce'], 'showplan_stations_edit')) {
		 \Showplan\Frontend::_die('Security fail!');
		}

		$this->update(Station::create());

		echo '<meta http-equiv="refresh" content="0;url=' . esc_attr($this->get_uri(false)) . '" />';
		exit;

	}

}

class StationListTable extends \Showplan\List_Table {

	public function prepare () {

		$this->_column_headers = array(
			$this->get_columns(),
			$this->get_hidden_columns(),
			$this->get_sortable_columns()
		);

		$this->items = array_map(function($a) {
			return $a->__array();
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
			'name' => 'Station Name',
			'description' => 'Description'
		);
	}

	public function get_hidden_columns () {
		return ['current_iteration_id'];
	}

	public function get_sortable_columns () {
		return [];
	}

	private function table_data () {

		return Station::all();

	}

	public function column_default ($item, $column) {

		return $item[$column];

	}

	public function column_reference ($item) {
		$_actions = array(
			'edit' => sprintf('<a href="?page=%s&action=%s&id=%s">Edit</a>', esc_attr($_REQUEST['page']), 'edit', $item['id']),
			'delete' => sprintf('<a href="?page=%s&action=%s&id=%s">Delete</a>', esc_attr($_REQUEST['page']), 'delete', $item['id']),
		);
		return sprintf('%s %s', $item['reference'], $this->row_actions($_actions));
	}

}
