<?php

/**
 * Plugin Name: JetFormBuilder Export Import
 * Description: Export and Import JetFormBuilder records between sites
 * Version: 1.0.0
 * Author: De Belser Arne
 * Text Domain: jetfb-export-import
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('JETFB_EI_VERSION', '1.0.0');
define('JETFB_EI_FILE', __FILE__);
define('JETFB_EI_PATH', plugin_dir_path(__FILE__));
define('JETFB_EI_URL', plugin_dir_url(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'JetFB\\ExportImport\\';
    $base_dir = JETFB_EI_PATH . 'includes/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize plugin
function jetfb_ei()
{
    return \JetFB\ExportImport\Plugin::get_instance();
}

// Activation/Deactivation hooks
register_activation_hook(__FILE__, [jetfb_ei(), 'activate']);
register_deactivation_hook(__FILE__, [jetfb_ei(), 'deactivate']);

// Boot the plugin
add_action('plugins_loaded', [jetfb_ei(), 'boot']);
