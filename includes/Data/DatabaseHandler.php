<?php

namespace JetFB\ExportImport\Data;

class DatabaseHandler
{
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function get_form_records($form_ids)
    {
        return $this->wpdb->get_results($this->build_select_query($form_ids));
    }

    public function insert_record($record)
    {
        $this->wpdb->query('START TRANSACTION');

        try {
            // Remove id field if it exists in main record
            if (isset($record['main']['id'])) {
                unset($record['main']['id']);
            }
            // Remove any BOM from field names
            $cleaned_main = [];
            foreach ($record['main'] as $key => $value) {
                $clean_key = str_replace("\xEF\xBB\xBF", '', $key);
                $cleaned_main[$clean_key] = $value;
            }

            $this->wpdb->insert(
                $this->wpdb->prefix . 'jet_fb_records',
                $cleaned_main
            );
            $record_id = $this->wpdb->insert_id;

            $this->insert_related_data('jet_fb_records_fields', $record['fields'], $record_id);
            $this->insert_related_data('jet_fb_records_actions', $record['actions'], $record_id);
            $this->insert_related_data('jet_fb_records_errors', $record['errors'], $record_id);

            $this->wpdb->query('COMMIT');
            return true;
        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    public function import_records($records)
    {
        foreach ($records as $record) {
            $this->wpdb->query('START TRANSACTION');

            try {
                // Insert main record
                $this->wpdb->insert(
                    $this->wpdb->prefix . 'jet_fb_records',
                    $record['main']
                );
                $record_id = $this->wpdb->insert_id;

                // Insert related data
                if (!empty($record['fields'])) {
                    $this->insert_related_data('jet_fb_records_fields', $record['fields'], $record_id);
                }

                if (!empty($record['actions'])) {
                    $this->insert_related_data('jet_fb_records_actions', $record['actions'], $record_id);
                }

                if (!empty($record['errors'])) {
                    $this->insert_related_data('jet_fb_records_errors', $record['errors'], $record_id);
                }

                $this->wpdb->query('COMMIT');
            } catch (\Exception $e) {
                $this->wpdb->query('ROLLBACK');
                throw new \Exception('Import failed for record: ' . $e->getMessage());
            }
        }

        return true;
    }

    private function insert_related_data($table, $items, $record_id)
    {
        foreach ($items as $item) {
            $item['record_id'] = $record_id;
            $this->wpdb->insert($this->wpdb->prefix . $table, $item);
        }
    }

    private function build_select_query($form_ids)
    {
        $placeholders = implode(',', array_fill(0, count($form_ids), '%d'));

        return $this->wpdb->prepare(
            "SELECT r.*, 
                JSON_ARRAYAGG(JSON_OBJECT('field_name', f.field_name, 'field_value', f.field_value, 'field_type', f.field_type, 'field_attrs', f.field_attrs)) as fields,
                JSON_ARRAYAGG(DISTINCT JSON_OBJECT('action_slug', a.action_slug, 'action_id', a.action_id, 'on_event', a.on_event, 'status', a.status)) as actions,
                JSON_ARRAYAGG(DISTINCT JSON_OBJECT('name', e.name, 'message', e.message, 'file', e.file, 'line', e.line)) as errors
            FROM {$this->wpdb->prefix}jet_fb_records r
            LEFT JOIN {$this->wpdb->prefix}jet_fb_records_fields f ON r.id = f.record_id
            LEFT JOIN {$this->wpdb->prefix}jet_fb_records_actions a ON r.id = a.record_id
            LEFT JOIN {$this->wpdb->prefix}jet_fb_records_errors e ON r.id = e.record_id
            WHERE r.form_id IN ($placeholders)
            GROUP BY r.id",
            ...$form_ids
        );
    }
}
