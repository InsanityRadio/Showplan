<?php
namespace Showplan\Controller;
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once 'controller.php';

use Showplan\Models\Category;
use Showplan\Models\Show;

class Shows extends Controller {

	public function __construct () {
		
		parent::__construct('showplan-shows');
		add_submenu_page('showplan-override', 'Shows', 'Shows', 'manage_options', 'showplan-shows', array($this, 'render'));
		$this->table = new ShowListTable();

	}

	public function render_home () {

		echo '<div class="wrap"><h2>Shows</h2>';
		echo '<a href="?page=showplan-shows&action=create" style="float: right" class="button">Create</a>';

		$this->table->prepare();
		$this->table->display();

		echo '</div>';

	}

	public function render_delete () {

		$_shows = [Show::find($_GET['id'])];

?>
		<form action="" method="post">
		<?php \Showplan\Frontend::nonce_field('showplan_shows_delete'); ?>
		<div class="wrap">
		<h1>Delete Shows</h1>

			<p>You have specified this show for deletion:</p>
			<p>(NOTE: If it is future-scheduled, it will be deleted leaving a schedule blank)</p>

		<ul>
		<?php foreach ($_shows as $_show): ?>
		<li><input type="hidden" name="shows[]" value="<?php echo $_show->id; ?>" />ID #<?php echo $_show->id; ?>: <?php echo esc_html($_show->name); ?></li>
		<?php endforeach; ?>
			</ul>
			<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Confirm Deletion"  /></p></div>
		</form>
<?php

	}
	public function render_edit ($show = null) {

		if (!$show)
			$show = Show::find($_GET['id']);

		$_categories = Category::all();

?>
		
		<div class='wrap'>
			<h2>Edit Show Settings: <b><?php echo esc_html($show->name); ?></b></h2>
			<form action="" method="post">
				<?php \Showplan\Frontend::nonce_field('showplan_shows_edit'); ?>
				<table class="form-table">

						<tr valign="top">
							<th scope="row">Name</th>
							<td>
								<input type="text" name="show[name]" value="<?php echo esc_attr($show->name); ?>"/>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">Description</th>
							<td>
								<input type="text" name="show[description]" value="<?php echo esc_attr($show->description); ?>"/>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">Hosts</th>
							<td>
								<input type="text" name="show[hosts]" value="<?php echo esc_attr($show->hosts); ?>"/>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">RDS Text</th>
							<td>
								<input type="text" name="show[one_liner]" value="<?php echo esc_attr($show->one_liner); ?>"/>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">Category</th>
							<td>
								<select name="show[category_id]">
<?php foreach ($_categories as $_cat): ?>
									<option<?php if ($show->category->id == $_cat->id) echo ' selected'; ?> value="<?php echo esc_attr($_cat->id); ?>">
										<?php echo esc_html($_cat->name); ?> (<?php echo esc_html($_cat->reference); ?>)
									</option>
<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">Hidden</th>
							<td>
								<input type="checkbox" name="show[hidden]" <?php echo ($show->id == null || $show->public) ? '' : 'checked '; ?>value="1" id="show-hidden" />
								<label for="show-hidden">
									Hide From Public
								</label>
							</td>
						</tr>
				</table>
				<input type="submit" value="Save" />

			</form>
		</div>
		
<?php
	}

	public function render_create () {

		$this->render_edit(Show::create());

	}

	public function update ($show) {
		$show->name = trim($_POST['show']['name']);
		$show->description = trim($_POST['show']['description']);
		$show->hosts = trim($_POST['show']['hosts']);
		$show->category_id = (int) $_POST['show']['category_id'];
		$show->public = $_POST['show']['hidden'] ? 0 : 1;
		$show->one_liner = trim($_POST['show']['one_liner']);
		$show->save();
	}

	public function post_edit () {

		if (!\Showplan\Frontend::verify_nonce($_REQUEST['_wpnonce'], 'showplan_shows_edit')) {
		 \Showplan\Frontend::_die('Security fail!');
		}

		$this->update(Show::find($_GET['id']));
		echo '<meta http-equiv="refresh" content="0;url=' . esc_attr($this->get_uri(false)) . '" />';
		exit;

	}

	public function post_create () {

		if (!\Showplan\Frontend::verify_nonce($_REQUEST['_wpnonce'], 'showplan_shows_edit')) {
		 \Showplan\Frontend::_die('Security fail!');
		}

		$this->update(Show::create());

		echo '<meta http-equiv="refresh" content="0;url=' . esc_attr($this->get_uri(false)) . '" />';
		exit;

	}

	public function post_delete () {

		if (!\Showplan\Frontend::verify_nonce($_REQUEST['_wpnonce'], 'showplan_shows_delete')) {
		 \Showplan\Frontend::_die('Security fail!');
		}

		$_ids = $_POST['shows'];
		foreach ($_ids as &$_id) {
			$_id = Show::find($_id);
		}

		foreach ($_ids as $_show) {
			$_show->remove();
		}
		echo '<meta http-equiv="refresh" content="0;url=' . esc_attr($this->get_uri(false)) . '" />';
		exit;

	}
}

class ShowListTable extends \Showplan\List_Table {

	public function prepare () {

		$this->_column_headers = array(
			$this->get_columns(),
			$this->get_hidden_columns(),
			$this->get_sortable_columns()
		);

		$this->items = array_map(function($a) {
			$b = $a->__array();
			$b['category'] = $a->category->reference;
			$b['public'] = !$a->public ? 'Yes' : 'No';
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
			'name' => 'Name',
			'description' => 'Description',
			'hosts' => 'Hosts',
			'category' => 'Category',
			'public' => 'Hidden'
		);
	}

	public function get_hidden_columns () {
		return [];
	}

	public function get_sortable_columns () {
		return [];
	}

	private function table_data () {

		return Show::all();

	}

	public function column_default ($item, $column) {

		return $item[$column];

	}

	public function column_name ($item) {
		$_actions = array(
			'edit' => sprintf('<a href="?page=%s&action=%s&id=%s">Update</a>', $_REQUEST['page'], 'edit', $item['id']),
			'delete' => sprintf('<a href="?page=%s&action=%s&id=%s">Delete</a>', $_REQUEST['page'], 'delete', $item['id']),
		);
		return sprintf('%s %s', $item['name'], $this->row_actions($_actions));
	}

}