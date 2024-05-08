<?php
namespace Showplan\Controller;

/**
 * The Station controller allows us to manage 
 */
abstract class Controller {

	protected $path;

	public function __construct ($path) {
		$this->path = $path;
	}

	public function render () {

		$_type = $_SERVER['REQUEST_METHOD'] == 'POST' ? 'post' : 'render';
		$_action = $this->get_action();

		// $_POST = array_map('stripslashes_deep', $_POST);

		if (method_exists($this, $_type . '_' . $_action)) {
			try {
				call_user_func(array($this, $_type . '_' . $_action));
			} catch (\Exception $e) {
				wp_die("Fatal error: " . $e->getMessage());
			}
		} else {
			$this->render_home();
		}

	}

	public function get_action () {
		return $_GET['action'] ? $_GET['action'] : 'home';
	}

	public function get_uri ($_action = true) {
		$_ret_id = $_GET['return'];
		return './admin.php?page=' . $this->path . ($_action ? '&action=' . $this->get_action() : '') . ($_ret_id ? '&id=' . urlencode($_ret_id) : '');
	}

	abstract public function render_home ();

}
