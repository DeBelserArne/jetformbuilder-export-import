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

        // Ensure table has prefix
        $table = $this->wpdb->prefix . $table;

        // Parse items if they're in string format
        $parsed_items = $this->parse_items($items);
        if (empty($parsed_items)) return;

        // Deduplicate items based on their unique characteristics
        $unique_items = $this->deduplicate_items($table, $parsed_items);

        // Insert unique items
        foreach ($unique_items as $item) {
            $data = ['record_id' => $record_id];

            // Prepare data based on table type
            $prepared_data = $this->prepare_table_data($table, $item);

            if ($prepared_data) {
                $data = array_merge($data, $prepared_data);
                $result = $this->wpdb->insert($table, $data);

                if ($result === false) {
                    error_log('Failed to insert into ' . $table);
                    error_log('Data: ' . print_r($data, true));
                    error_log('Last error: ' . $this->wpdb->last_error);
                }
            }
        }
    }

    private function parse_items($items)
    {
        // If items is already an array and not a JSON string
        if (is_array($items) && !is_string(reset($items))) {
            return $items;
        }

        // If items is a JSON string, decode it
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Handle case where first element is a JSON string
        if (is_array($items) && isset($items[0]) && is_string($items[0])) {
            try {
                $cleaned = stripslashes($items[0]);
                $decoded = json_decode($cleaned, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            } catch (\Exception $e) {
                error_log('JSON parsing error: ' . $e->getMessage());
            }
        }

        return [];
    }

    private function deduplicate_items($table, $items)
    {
        $unique_items = [];
        $seen = [];

        foreach ($items as $item) {
            if (empty($item) || !is_array($item)) continue;

            $key = $this->get_unique_key($table, $item);
            if (!empty($key) && !isset($seen[$key])) {
                $seen[$key] = true;
                $unique_items[] = $item;
            }
        }

        return $unique_items;
    }

    private function get_unique_key($table, $item)
    {
        switch ($table) {
            case $this->wpdb->prefix . 'jet_fb_records_fields':
                return isset($item['field_name']) ? $item['field_name'] : '';

            case $this->wpdb->prefix . 'jet_fb_records_actions':
                return isset($item['action_slug']) && isset($item['action_id'])
                    ? $item['action_slug'] . '_' . $item['action_id']
                    : '';

            case $this->wpdb->prefix . 'jet_fb_records_errors':
                return isset($item['name']) && isset($item['message'])
                    ? $item['name'] . '_' . $item['message']
                    : '';

            default:
                return '';
        }
    }

    private function prepare_table_data($table, $item)
    {
        switch ($table) {
            case $this->wpdb->prefix . 'jet_fb_records_fields':
                if (!isset($item['field_name'])) return false;
                return [
                    'field_name' => $item['field_name'],
                    'field_value' => $item['field_value'] ?? '',
                    'field_type' => $item['field_type'] ?? 'text',
                    'field_attrs' => $item['field_attrs'] ?? ''
                ];

            case $this->wpdb->prefix . 'jet_fb_records_actions':
                if (!isset($item['action_slug'])) return false;
                return [
                    'action_slug' => $item['action_slug'],
                    'action_id' => $item['action_id'] ?? 0,
                    'on_event' => $item['on_event'] ?? 'PROCESS',
                    'status' => $item['status'] ?? ''
                ];

            case $this->wpdb->prefix . 'jet_fb_records_errors':
                return [
                    'name' => $item['name'] ?? '',
                    'message' => $item['message'] ?? ''
                ];
        }
        return false;
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
