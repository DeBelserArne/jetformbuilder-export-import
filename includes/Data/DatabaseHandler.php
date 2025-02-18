<?php

namespace JetFB\ExportImport\Data;

/**
 * Handles all database operations for form records including import/export
 * Uses transactions to ensure data integrity
 */
class DatabaseHandler
{
    /** @var wpdb WordPress database connection */
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Retrieves form records and their related data (fields, actions, errors)
     * @param array $form_ids Array of form IDs to retrieve records for
     * @return array Records with their related data in JSON format
     */
    public function get_form_records($form_ids)
    {
        $query = $this->build_select_query($form_ids);
        //g('JetFB Export Query: ' . $query);

        $results = $this->wpdb->get_results($query);
        $count = count($results);
        //error_log('JetFB Export Records Count: ' . $count);
        //error_log('JetFB Export Form IDs: ' . print_r($form_ids, true));

        // Log the actual data returned
        //error_log('JetFB Export Data: ' . print_r($results, true));

        return $results;
    }

    /**
     * Inserts a single form record with all its related data
     * Uses transaction to ensure all data is inserted consistently
     * @param array $record Record data including main record and related data (fields, actions, errors)
     * @return bool True on success, throws Exception on failure
     */
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

    /**
     * Batch imports multiple form records
     * Each record is imported in its own transaction
     * @param array $records Array of records to import
     * @return bool True on success
     * @throws \Exception When import fails
     */
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

    /**
     * Inserts related data for a form record
     * Handles parsing, deduplication and validation before insertion
     * @param string $table Base table name without prefix
     * @param mixed $items Array or JSON string of items to insert
     * @param int $record_id Parent record ID
     */
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

    /**
     * Parses items from various formats into a clean array
     * Handles:
     * - Direct arrays
     * - JSON strings
     * - Base64 encoded JSON
     * - Arrays with JSON strings
     * @param mixed $items Data to parse
     * @return array Parsed items or empty array on failure
     */
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

    /**
     * Removes duplicate items based on unique identifiers per table type
     * Maintains only the first occurrence of each unique item
     * @param string $table Full table name with prefix
     * @param array $items Items to deduplicate
     * @return array Unique items
     */
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

    /**
     * Generates a unique key for an item based on table schema
     * Fields: unique by field_name
     * Actions: unique by action_slug + action_id
     * Errors: unique by name + message combination
     * @param string $table Full table name
     * @param array $item Item to generate key for
     * @return string Unique key
     */
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

    /**
     * Prepares data for database insertion according to table schema
     * Handles default values and required fields validation
     * @param string $table Full table name
     * @param array $item Data to prepare
     * @return array|false Prepared data or false if required fields are missing
     */
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

    /**
     * Builds SQL query to select form records with their related data
     * Uses JSON_ARRAYAGG to combine related records into JSON format
     * @param array $form_ids Form IDs to include in query
     * @return string Prepared SQL query
     */
    private function build_select_query($form_ids)
    {
        $query = new QueryBuilder($this->wpdb);
        return $query
            ->select('r.*')
            ->addJsonField('fields', $this->buildFieldsSubquery())
            ->addJsonField('actions', $this->buildActionsSubquery())
            ->addJsonField('errors', $this->buildErrorsSubquery())
            ->from('jet_fb_records', 'r')
            ->where('form_id IN (' . implode(',', array_fill(0, count($form_ids), '%d')) . ')', $form_ids)
            ->build();
    }

    private function buildFieldsSubquery()
    {
        return [
            'table' => 'jet_fb_records_fields',
            'columns' => ['field_name', 'field_value', 'field_type', 'field_attrs'],
            'json_mapping' => [
                'field_name' => 'field_name',
                'field_value' => 'field_value',
                'field_type' => 'field_type',
                'field_attrs' => 'field_attrs'
            ]
        ];
    }

    private function buildActionsSubquery()
    {
        return [
            'table' => 'jet_fb_records_actions',
            'columns' => ['action_slug', 'action_id', 'on_event', 'status'],
            'json_mapping' => [
                'action_slug' => 'action_slug',
                'action_id' => 'action_id',
                'on_event' => 'on_event',
                'status' => 'status'
            ]
        ];
    }

    private function buildErrorsSubquery()
    {
        return [
            'table' => 'jet_fb_records_errors',
            'columns' => ['name', 'message'],
            'json_mapping' => [
                'name' => 'name',
                'message' => 'message'
            ]
        ];
    }
}
