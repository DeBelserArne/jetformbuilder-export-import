<?php

namespace JetFB\ExportImport\Export;

class Handler
{
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

        $records = $this->get_records($form_ids);
        $this->export_to_csv($records);
    }

    private function get_records($form_ids)
    {
        global $wpdb;

        // Base records query
        $records_query = $wpdb->prepare(
            "SELECT r.*, 
                    GROUP_CONCAT(DISTINCT f.field_name, ':', f.field_value) as fields,
                    GROUP_CONCAT(DISTINCT a.action_slug, ':', a.status) as actions,
                    GROUP_CONCAT(DISTINCT e.message) as errors
             FROM {$wpdb->prefix}jet_fb_records r
             LEFT JOIN {$wpdb->prefix}jet_fb_records_fields f ON r.id = f.record_id
             LEFT JOIN {$wpdb->prefix}jet_fb_records_actions a ON r.id = a.record_id
             LEFT JOIN {$wpdb->prefix}jet_fb_records_errors e ON r.id = e.record_id
             WHERE r.form_id IN (" . implode(',', array_fill(0, count($form_ids), '%d')) . ")
             GROUP BY r.id",
            ...$form_ids
        );

        return $wpdb->get_results($records_query);
    }

    private function export_to_csv($records)
    {
        if (empty($records)) {
            wp_die('No records found for the selected forms');
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=jetfb-records-' . date('Y-m-d') . '.csv');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $output = fopen('php://output', 'w');

        // Define CSV headers based on all possible columns
        $headers = [
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

        fputcsv($output, $headers);

        foreach ($records as $record) {
            $row = [];
            foreach ($headers as $header) {
                $row[] = isset($record->$header) ? $record->$header : '';
            }
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
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
