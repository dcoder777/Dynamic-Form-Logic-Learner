<?php
/**
 * Plugin Name: Dynamic Form Logic Learner
 * Description: AI-driven logic suggestions and adaptive rules for WordPress form builders.
 * Version: 0.1.0
 * Author: Codex
 * Text Domain: dynamic-form-logic-learner
 */

if (! defined('ABSPATH')) {
    exit;
}

define('DFLL_VERSION', '0.1.0');
define('DFLL_PLUGIN_FILE', __FILE__);
define('DFLL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DFLL_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once DFLL_PLUGIN_DIR . 'includes/class-installer.php';
require_once DFLL_PLUGIN_DIR . 'includes/class-data-store.php';
require_once DFLL_PLUGIN_DIR . 'includes/class-analytics-engine.php';
require_once DFLL_PLUGIN_DIR . 'includes/class-form-handler.php';
require_once DFLL_PLUGIN_DIR . 'includes/class-rest-controller.php';
require_once DFLL_PLUGIN_DIR . 'includes/class-integration.php';
require_once DFLL_PLUGIN_DIR . 'includes/class-admin-ui.php';

register_activation_hook(DFLL_PLUGIN_FILE, ['DFLL_Installer', 'activate']);
register_deactivation_hook(DFLL_PLUGIN_FILE, ['DFLL_Installer', 'deactivate']);

add_action('plugins_loaded', static function () {
    $data_store = new DFLL_Data_Store();
    $analytics  = new DFLL_Analytics_Engine($data_store);
    $handler    = new DFLL_Form_Handler($data_store);

    new DFLL_REST_Controller($data_store, $analytics);
    new DFLL_Integration($handler, $data_store);
    new DFLL_Admin_UI($data_store, $analytics);
});
