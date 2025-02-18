<?php

namespace JetFB\ExportImport\Admin;

use JetFB\ExportImport\Admin\Section\ExportSection;
use JetFB\ExportImport\Admin\Section\HeaderSection;
use JetFB\ExportImport\Admin\Section\ImportSection;

class Page
{
    private $debug;
    private $header_section;
    private $export_section;
    private $import_section;

    public function __construct($debug = false)
    {
        $this->debug = $debug;
        $this->header_section = new HeaderSection();
        $this->export_section = new ExportSection();
        $this->import_section = new ImportSection();
    }

    public function render()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if ($this->debug && isset($_FILES['import_file'])) {
            $import_handler = new \JetFB\ExportImport\Import\Handler();
            $this->import_section->set_preview(
                $import_handler->preview_import($_FILES['import_file']['tmp_name'])
            );
        }

        $this->header_section->render();
        $this->export_section->render();
        $this->import_section->render();
        echo '</div>'; // Close wrap div
    }
}
