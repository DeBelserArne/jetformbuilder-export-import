<?php

namespace JetFB\ExportImport\Data;

abstract class RecordProcessor
{
    protected $required_headers = [
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

    protected function validate_headers($headers)
    {
        $missing = array_diff($this->required_headers, $headers);
        if (!empty($missing)) {
            throw new \Exception('Invalid CSV format. Missing headers: ' . implode(', ', $missing));
        }
    }

    protected function extract_main_record($record)
    {
        unset($record['id']);
        return array_intersect_key($record, array_flip($this->required_headers));
    }
}
