<?php

namespace JetFB\ExportImport\Data;

class QueryBuilder
{
    private $wpdb;
    private $select = [];
    private $from = '';
    private $where = '';
    private $params = [];

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function select($columns)
    {
        $this->select[] = $columns;
        return $this;
    }

    public function addJsonField($alias, $config)
    {
        $subquery = $this->buildJsonSubquery($config);
        $this->select[] = "({$subquery}) as {$alias}";
        return $this;
    }

    public function from($table, $alias)
    {
        $this->from = $this->wpdb->prefix . $table . ' ' . $alias;
        return $this;
    }

    public function where($condition, $params = [])
    {
        $this->where = $condition;
        $this->params = $params;
        return $this;
    }

    private function buildJsonSubquery($config)
    {
        $columns = implode(', ', $config['columns']);
        $jsonPairs = [];
        foreach ($config['json_mapping'] as $key => $value) {
            $jsonPairs[] = "'$key', $value";
        }
        $jsonObject = implode(', ', $jsonPairs);

        return "SELECT JSON_ARRAYAGG(
                    JSON_OBJECT({$jsonObject})
                ) FROM (
                    SELECT DISTINCT {$columns}
                    FROM {$this->wpdb->prefix}{$config['table']}
                    WHERE record_id = r.id
                ) as unique_{$config['table']}";
    }

    public function build()
    {
        $query = "SELECT " . implode(", ", $this->select) .
            " FROM {$this->from}" .
            ($this->where ? " WHERE {$this->where}" : "");

        if (empty($this->params)) {
            return $query;
        }

        return $this->wpdb->prepare($query, ...$this->params);
    }
}
