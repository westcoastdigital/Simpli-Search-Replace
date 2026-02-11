<?php
/*
Plugin Name:  Simpli Search Replace
Plugin URI:   https://gist.github.com/westcoastdigital
Description:  Serialized-safe database search & replace with preview functionality.
Version:      1.0.1
Author:       Jon Mather
Author URI:   https://jonmather.au
License:      GPL v2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  simpli
Domain Path:  /languages
*/


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SSR_PATH', plugin_dir_path( __FILE__ ) );
define( 'SSR_URL', plugin_dir_url( __FILE__ ) );
define( 'SSR_VERSION', '1.0.1' );

require_once SSR_PATH . 'includes/class-ssr-serializer.php';
require_once SSR_PATH . 'includes/class-ssr-processor.php';
require_once SSR_PATH . 'includes/class-ssr-admin.php';

// Include the updater class
require_once plugin_dir_path(__FILE__) . 'github-updater.php';

// For private repos, uncomment and add your token:
// define('SW_GITHUB_ACCESS_TOKEN', 'your_token_here');

if (class_exists('SimpliWeb_GitHub_Updater')) {
    $updater = new SimpliWeb_GitHub_Updater(__FILE__);
    $updater->set_username('westcoastdigital'); // Update Username
    $updater->set_repository('Simpli-Search-Replace'); // Update plugin slug
    
    if (defined('GITHUB_ACCESS_TOKEN')) {
      $updater->authorize(SW_GITHUB_ACCESS_TOKEN);
    }
    
    $updater->initialize();
}

function ssr_init_plugin() {
    new SSR_Admin();
}
add_action( 'plugins_loaded', 'ssr_init_plugin' );