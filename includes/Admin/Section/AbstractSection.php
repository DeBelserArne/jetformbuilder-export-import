<?php

namespace JetFB\ExportImport\Admin\Section;

abstract class AbstractSection
{
    protected $debug;

    public function __construct($debug = false)
    {
        $this->debug = $debug;
    }

    abstract public function render();
}
