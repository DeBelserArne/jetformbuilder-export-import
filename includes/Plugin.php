<?php

namespace JetFB\ExportImport;

class Plugin
{
    private static $instance = null;
    private $admin_page;
    private $export_handler;
    private $import_handler;

    private function __construct()
    {
        $this->admin_page = new Admin\Page();
        $this->export_handler = new Export\Handler();
        $this->import_handler = new Import\Handler();
    }

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {
        // Admin menu
        add_action('admin_menu', function () {
            add_menu_page(
                'JetFB Export Import',
                'JetFB Export Import',
                'manage_options',
                'jetfb-export-import',
                [$this->admin_page, 'render'],
                'dashicons-database-export'
            );
        });

        // Export/Import handlers
        add_action('admin_post_jetfb_export_records', [$this->export_handler, 'process']);
        add_action('admin_post_jetfb_import_records', [$this->import_handler, 'process']);
    }

    public function activate()
    {
        // Create necessary database tables if needed
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'jet_fb_records';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            data longtext NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function deactivate()
    {
        // Cleanup if needed
    }
}
