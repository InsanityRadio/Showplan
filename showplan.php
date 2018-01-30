<?php
/**
 * Plugin Name: Showplan
 * Plugin URI: https://github.com/insanityradio/showplan/
 * Version: 0.1
 * Description: Creates a simple yet powerful way to manage your station's schedule
 * Author: Jamie Woods & Insanity Tech
 * Author URI: https://insanityradio.com
 * License: GPL2
 *
 * This program is GPL but; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of.
 */

defined ('ABSPATH') or exit;

require_once 'includes/controller.php';
use Showplan\Controller;

Controller::bootstrap(__FILE__);
