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
        if (empty($items)) return;

        // If we get a concatenated JSON string, split it into objects
        if (is_string($items[0])) {
            $json_objects = array_filter(array_map(function ($str) {
                // Try to parse each potential JSON object
                $cleaned = trim($str, " ,\n\r\t");
                $decoded = json_decode($cleaned, true);
                return ($decoded && json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
            }, preg_split('/},\s*{/', trim($items[0], '{}'))));

            $items = $json_objects;
        }

        // Remove duplicates by serializing arrays and using array_unique
        $unique_items = [];
        $seen = [];

        foreach ($items as $item) {
            if (empty($item) || !is_array($item)) continue;

            // Create a key from the relevant fields based on table type
            $key = '';
            switch ($table) {
                case $this->wpdb->prefix . 'jet_fb_records_fields':
                    if (!isset($item['field_name'])) {
                        break;
                    }
                    $key = $item['field_name'] . '|' . ($item['field_value'] ?? '');
                    break;

                case $this->wpdb->prefix . 'jet_fb_records_actions':
                    if (!isset($item['action_slug'])) {
                        break;
                    }
                    $key = $item['action_slug'] . '|' . ($item['action_id'] ?? '');
                    break;

                case $this->wpdb->prefix . 'jet_fb_records_errors':
                    $key = ($item['name'] ?? '') . '|' . ($item['message'] ?? '');
                    break;
            }

            if (!empty($key) && !isset($seen[$key])) {
                $seen[$key] = true;
                $unique_items[] = $item;
            }
        }

        // Process only unique items
        foreach ($unique_items as $item) {
            $data = ['record_id' => $record_id];
            $success = false;

            // Determine table schema and prepare data
            switch ($table) {
                case $this->wpdb->prefix . 'jet_fb_records_fields':
                    if (!isset($item['field_name'])) {
                        break;
                    }
                    $data['field_name'] = $item['field_name'];
                    $data['field_value'] = $item['field_value'] ?? '';
                    $data['field_type'] = $item['field_type'] ?? 'text';
                    $data['field_attrs'] = $item['field_attrs'] ?? '';
                    $success = true;
                    break;

                case $this->wpdb->prefix . 'jet_fb_records_actions':
                    if (!isset($item['action_slug'])) {
                        break;
                    }
                    $data['action_slug'] = $item['action_slug'];
                    $data['action_id'] = $item['action_id'] ?? 0;
                    $data['on_event'] = $item['on_event'] ?? 'PROCESS';
                    $data['status'] = $item['status'] ?? '';
                    $success = true;
                    break;

                case $this->wpdb->prefix . 'jet_fb_records_errors':
                    $data['name'] = $item['name'] ?? '';
                    $data['message'] = $item['message'] ?? '';
                    $success = true;
                    break;
            }

            if ($success) {
                $result = $this->wpdb->insert($table, $data);
                if ($result === false) {
                    error_log('Failed to insert into ' . $table);
                    error_log('Data: ' . print_r($data, true));
                    error_log('Last error: ' . $this->wpdb->last_error);
                }
            }
        }
    }

    private function build_select_query($form_ids)
    {
        $placeholders = implode(',', array_fill(0, count($form_ids), '%d'));

        return $this->wpdb->prepare(
            "SELECT r.*, 
                    JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'field_name', f.field_name,
                            'field_value', f.field_value,
                            'field_type', f.field_type,
                            'field_attrs', f.field_attrs
                        )
                    ) as fields,
                    JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'action_slug', a.action_slug,
                            'action_id', a.action_id,
                            'on_event', a.on_event,
                            'status', a.status
                        )
                    ) as actions,
                    JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'name', e.name,
                            'message', e.message
                        )
                    ) as errors
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
