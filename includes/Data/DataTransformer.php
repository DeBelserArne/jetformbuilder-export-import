<?php

namespace JetFB\ExportImport\Data;

class DataTransformer
{
    public function encode_for_csv($data)
    {
        if (empty($data)) {
            return '';
        }

        // First, ensure we have a clean array
        if (is_string($data)) {
            $data = json_decode($data, true) ?? [];
        }

        // Encode as JSON with options to make it safe for CSV
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Base64 encode to make it safe for CSV storage
        return 'base64:' . base64_encode($json);
    }

    public function decode_from_csv($value)
    {
        if (empty($value)) {
            return [];
        }

        try {
            // Check if it's base64 encoded
            if (strpos($value, 'base64:') === 0) {
                // Remove the prefix and decode
                $json = base64_decode(substr($value, 7));
                if ($json === false) {
                    error_log('Failed to decode base64 data');
                    return [];
                }

                // Decode JSON
                $data = json_decode($json, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log('JSON decode error: ' . json_last_error_msg());
                    error_log('JSON string: ' . substr($json, 0, 100));
                    return [];
                }

                return $data;
            }
        } catch (\Exception $e) {
            error_log('Error decoding data: ' . $e->getMessage());
        }

        return [];
    }

    public function parse_json_field($json_string)
    {
        $data = json_decode($this->decode_from_csv($json_string), true);
        return is_array($data) ? $data : [];
    }
}
