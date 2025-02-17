<?php

namespace JetFB\ExportImport\Import;

use JetFB\ExportImport\Data\CsvHandler;
use JetFB\ExportImport\Data\DatabaseHandler;
use JetFB\ExportImport\Data\DataTransformer;
use JetFB\ExportImport\Data\RecordProcessor;

class Handler extends RecordProcessor
{
    private $csv_handler;
    private $db_handler;
    private $transformer;

    public function __construct()
    {
        $this->csv_handler = new CsvHandler();
        $this->db_handler = new DatabaseHandler();
        $this->transformer = new DataTransformer();
    }

    public function preview_import($file)
    {
        if (!current_user_can('manage_options')) {
            return ['error' => 'Unauthorized'];
        }

        if (!$file || !file_exists($file)) {
            return ['error' => 'No file uploaded'];
        }

        try {
            $records = $this->csv_handler->parse_csv($file);
            $form_ids = array_unique(array_column(array_column($records, 'main'), 'form_id'));

            return [
                'success' => true,
                'records' => $records,
                'summary' => [
                    'total_records' => count($records),
                    'forms' => $form_ids
                ]
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function process()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('jetfb_import_action', 'jetfb_import_nonce');

        if (!isset($_POST['confirmed_import'])) {
            wp_die('Import not confirmed');
        }

        $import_data = unserialize(base64_decode($_POST['import_data']));
        if (!$import_data) {
            wp_die('Invalid import data');
        }

        $this->db_handler->import_records($import_data);

        wp_safe_redirect(
            add_query_arg('imported', '1', admin_url('admin.php?page=jetfb-export-import'))
        );
        exit;
    }
}
