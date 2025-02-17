<?php

namespace JetFB\ExportImport\Export;

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

    public function process()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('jetfb_export_action', 'jetfb_export_nonce');

        // Get selected form IDs from POST
        $form_ids = isset($_POST['form_ids']) ? array_map('intval', $_POST['form_ids']) : [];

        if (empty($form_ids)) {
            wp_die('Please select at least one form to export');
        }

        $records = $this->db_handler->get_form_records($form_ids);
        $this->export_to_csv($records);
    }

    private function export_to_csv($records)
    {
        if (empty($records)) {
            wp_die('No records found for the selected forms');
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=jetfb-records-' . date('Y-m-d') . '.csv');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $transformed_records = $this->transform_records($records);
        $headers = $this->get_csv_headers();

        $this->csv_handler->write($transformed_records, $headers);
        exit;
    }

    private function transform_records($records)
    {
        $transformed = [];
        foreach ($records as $record) {
            $row = [
                'id' => $record->id,
                'form_id' => $record->form_id,
                'user_id' => $record->user_id,
                'from_content_id' => $record->from_content_id,
                'from_content_type' => $record->from_content_type,
                'status' => $record->status,
                'ip_address' => $record->ip_address,
                'user_agent' => $record->user_agent,
                'referrer' => $record->referrer,
                'submit_type' => $record->submit_type,
                'is_viewed' => $record->is_viewed,
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
                'fields' => $this->transformer->encode_for_csv($record->fields),
                'actions' => $this->transformer->encode_for_csv($record->actions),
                'errors' => $this->transformer->encode_for_csv($record->errors)
            ];
            $transformed[] = $row;
        }
        return $transformed;
    }

    private function get_csv_headers()
    {
        return [
            'id',
            'form_id',
            'user_id',
            'from_content_id',
            'from_content_type',
            'status',
            'ip_address',
            'user_agent',
            'referrer',
            'submit_type',
            'is_viewed',
            'created_at',
            'updated_at',
            'fields',
            'actions',
            'errors'
        ];
    }

    public function get_available_forms()
    {
        $forms = get_posts([
            'post_type' => 'jet-form-builder',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        return array_map(function ($form) {
            return [
                'id' => $form->ID,
                'title' => $form->post_title
            ];
        }, $forms);
    }
}
