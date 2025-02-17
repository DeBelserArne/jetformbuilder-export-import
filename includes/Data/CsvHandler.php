<?php

namespace JetFB\ExportImport\Data;

class CsvHandler
{
    private $transformer;

    public function __construct()
    {
        $this->transformer = new DataTransformer();
    }

    public function read($file)
    {
        if (($handle = fopen($file, "r")) === FALSE) {
            throw new \Exception('Could not open file');
        }

        $headers = fgetcsv($handle);
        $data = [];

        while (($row = fgetcsv($handle)) !== FALSE) {
            $data[] = array_combine($headers, $row);
        }

        fclose($handle);
        return ['headers' => $headers, 'data' => $data];
    }

    public function write($data, $headers)
    {
        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM

        fputcsv($output, $headers);
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
    }

    public function parse_csv($file)
    {
        $csv_data = $this->read($file);
        $records = [];

        foreach ($csv_data['data'] as $row) {
            // Debug output
            error_log('Processing CSV row: ' . print_r($row, true));

            $record = [
                'main' => $this->extract_main_fields($row),
                'fields' => $this->parse_json_data($row['fields'] ?? ''),
                'actions' => $this->parse_json_data($row['actions'] ?? ''),
                'errors' => $this->parse_json_data($row['errors'] ?? '')
            ];

            // Debug output
            error_log('Processed record: ' . print_r($record, true));

            $records[] = $record;
        }

        return $records;
    }

    private function extract_main_fields($row)
    {
        // Remove id and other non-insertable fields
        $excluded_fields = ['id', 'fields', 'actions', 'errors'];
        $main_fields = array_diff_key($row, array_flip($excluded_fields));

        // Clean BOM from first field if present
        if (isset($main_fields["\xEF\xBB\xBF" . 'id'])) {
            unset($main_fields["\xEF\xBB\xBF" . 'id']);
        }

        return array_filter($main_fields, function ($value) {
            return $value !== '';
        });
    }

    private function parse_json_data($data)
    {
        return $this->transformer->decode_from_csv($data);
    }
}
