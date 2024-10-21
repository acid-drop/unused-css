<?php
/*
Plugin Name: Unused CSS
Description: Removes unused CSS from WordPress sites
Author: Leon Chevalier
Version: 0.01.0
Text Domain: unused-css
Author URI: https://github.com/acid-drop
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

//The composer autoload function 
require_once dirname(__FILE__) . '/vendor/autoload.php';

// Use get_file_data() to read the version from the plugin header
$plugin_data = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
$plugin_version_full = $plugin_data['Version'];

// Define constants
define('UCSS_VER', $plugin_version_full);
define('UCSS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UCSS_PLUGIN_DIR', plugin_dir_path(__FILE__));

//Run the init functions
UCSS\App\Config::init();
UCSS\RestApi::init();
UCSS\Speed::init();


//Run last as other classes and feed $display var here
UCSS\App\Menu::init();