<?php
namespace Showplan\Controller;

/**
 * The Station controller allows us to manage 
 */
abstract class Controller {

	private $path;

	public function __construct ($path) {
		$this->path = $path;
	}

	public function render () {

		$_type = $_SERVER['REQUEST_METHOD'] == 'POST' ? 'post' : 'render';
		$_action = $this->get_action();

		$_POST = array_map('stripslashes_deep', $_POST);

		if (method_exists($this, $_type . '_' . $_action)) {
			call_user_func(array($this, $_type . '_' . $_action));
		} else {
			$this->render_home();
		}

	}

	public function get_action () {
		return $_GET['action'] ? $_GET['action'] : 'home';
	}

	public function get_uri ($_action = true) {
		return './admin.php?page=' . $this->path . ($_action ? '&action=' . $this->get_action() : '');
	}

	abstract public function render_home ();

}