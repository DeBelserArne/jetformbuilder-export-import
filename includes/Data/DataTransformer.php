<?php

namespace JetFB\ExportImport\Data;

class DataTransformer
{
    public function encode_for_csv($value)
    {
        if (empty($value)) return '';
        $value = is_string($value) ? $value : json_encode($value);
        return 'base64:' . base64_encode($value);
    }

    public function decode_from_csv($value)
    {
        if (empty($value)) return '';
        if (strpos($value, 'base64:') === 0) {
            return base64_decode(substr($value, 7));
        }
        return $value;
    }

    public function parse_json_field($json_string)
    {
        $data = json_decode($this->decode_from_csv($json_string), true);
        return is_array($data) ? $data : [];
    }
}
