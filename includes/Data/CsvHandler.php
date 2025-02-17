<?php

namespace JetFB\ExportImport\Data;

class CsvHandler
{
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
            $record = [
                'main' => $this->extract_main_fields($row),
                'fields' => $this->parse_json_data($row['fields']),
                'actions' => $this->parse_json_data($row['actions']),
                'errors' => $this->parse_json_data($row['errors'])
            ];
            $records[] = $record;
        }

        return $records;
    }

    private function extract_main_fields($row)
    {
        $main_fields = array_diff_key($row, array_flip(['fields', 'actions', 'errors']));
        return array_filter($main_fields, function ($value) {
            return $value !== '';
        });
    }

    private function parse_json_data($data)
    {
        if (empty($data)) return [];

        if (strpos($data, 'base64:') === 0) {
            $data = base64_decode(substr($data, 7));
        }

        $decoded = json_decode($data, true);
        return is_array($decoded) ? $decoded : [];
    }
}
