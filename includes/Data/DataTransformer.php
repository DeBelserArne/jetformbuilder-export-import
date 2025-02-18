<?php

namespace JetFB\ExportImport\Data;

/**
 * Handles data transformation for CSV import/export
 * Provides methods to safely encode and decode complex data structures
 */
class DataTransformer
{
    /**
     * Encodes data for safe storage in CSV format
     * 1. Converts any string input to array
     * 2. JSON encodes with Unicode support
     * 3. Base64 encodes to prevent CSV formatting issues
     * 
     * @param mixed $data Array or JSON string to encode
     * @return string Base64 encoded string prefixed with 'base64:'
     */
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

    /**
     * Decodes data from CSV storage back to original format
     * 1. Checks for base64: prefix
     * 2. Decodes base64 to JSON string
     * 3. Parses JSON into array
     * 
     * @param string $value Base64 encoded string from CSV
     * @return array Decoded data or empty array on failure
     */
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

    /**
     * Parses a JSON field that was previously encoded for CSV
     * Handles double encoding cases (JSON within base64 within JSON)
     * 
     * @param string $json_string Encoded JSON string
     * @return array Parsed data or empty array on failure
     */
    public function parse_json_field($json_string)
    {
        $data = json_decode($this->decode_from_csv($json_string), true);
        return is_array($data) ? $data : [];
    }
}
