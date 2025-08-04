<?php
/**
 * Plugin Name: Fines Manager
 * Plugin URI: https://github.com/erwansetyobudi/fines_manager
 * Description: To view, edit and delete fines
 * Version: 0.0.1
 * Author: Erwan Setyo Budi
 * Author URI: https://github.com/erwansetyobudi
 */

// get plugin instance
$plugin = \SLiMS\Plugins::getInstance();

// registering menus
$plugin->registerMenu('circulation', 'Fines Manager', __DIR__ . '/index.php');
