<?php

namespace JetFB\ExportImport\Import;

class Handler
{
    public function process()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('jetfb_import_action', 'jetfb_import_nonce');

        if (!isset($_FILES['import_file'])) {
            wp_die('No file uploaded');
        }

        $file = $_FILES['import_file'];
        if ($file['error'] || $file['type'] !== 'text/csv') {
            wp_die('Invalid file');
        }

        $records = $this->parse_csv($file['tmp_name']);
        $this->import_records($records);

        wp_safe_redirect(
            add_query_arg(
                'imported',
                '1',
                admin_url('admin.php?page=jetfb-export-import')
            )
        );
        exit;
    }

    private function parse_csv($file)
    {
        $records = [];
        if (($handle = fopen($file, "r")) !== FALSE) {
            $headers = fgetcsv($handle);
            while (($data = fgetcsv($handle)) !== FALSE) {
                $records[] = array_combine($headers, $data);
            }
            fclose($handle);
        }
        return $records;
    }

    private function import_records($records)
    {
        global $wpdb;
        foreach ($records as $record) {
            $wpdb->insert(
                $wpdb->prefix . 'jet_fb_records',
                $record
            );
        }
    }
}
