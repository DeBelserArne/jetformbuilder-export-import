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

        add_action('admin_post_jetfb_export_records', [$this->export_handler, 'process']);
        add_action('admin_post_jetfb_import_records', [$this->import_handler, 'process']);
    }
}
