<?php

namespace JetFB\ExportImport\Admin\Section;

class ImportSection extends AbstractSection
{
    private $preview_data = null;

    public function set_preview($data)
    {
        $this->preview_data = $data;
    }

    public function render()
    {
?>
        <div class="card">
            <h2><?php esc_html_e('Import Records', 'jetfb-export-import'); ?></h2>
            <?php
            if ($this->debug) {
                $this->render_debug_mode();
            } else {
                $this->render_production_mode();
            }
            ?>
        </div>
    <?php
    }

    private function render_debug_mode()
    {
        if ($this->preview_data) {
            if (isset($this->preview_data['error'])) {
                $this->render_error_message($this->preview_data['error']);
            } else {
                $this->render_preview_data();
            }
        } else {
            $this->render_preview_form();
        }
    }

    private function render_production_mode()
    {
    ?>
        <form method="post"
            action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
            enctype="multipart/form-data"
            class="jetfb-upload-form">

            <?php wp_nonce_field('jetfb_import_action', 'jetfb_import_nonce'); ?>
            <input type="hidden" name="action" value="jetfb_import_records">

            <div class="jetfb-upload-zone">
                <div class="jetfb-upload-content">
                    <span class="dashicons dashicons-upload"></span>
                    <div class="jetfb-upload-text">
                        <strong><?php esc_html_e('Drop CSV file here', 'jetfb-export-import'); ?></strong>
                        <span><?php esc_html_e('or', 'jetfb-export-import'); ?></span>
                        <button type="button" class="button jetfb-browse-button">
                            <?php esc_html_e('Browse Files', 'jetfb-export-import'); ?>
                        </button>
                    </div>
                    <input type="file"
                        name="import_file"
                        accept=".csv"
                        required
                        class="jetfb-file-input">
                </div>
                <div class="jetfb-file-preview"></div>
            </div>

            <p class="submit">
                <button type="submit" class="button button-primary button-hero">
                    <span class="dashicons dashicons-database-import"></span>
                    <?php esc_html_e('Import Records', 'jetfb-export-import'); ?>
                </button>
            </p>
        </form>

        <style>
            .jetfb-upload-form {
                max-width: 600px;
                margin: 20px auto;
            }

            .jetfb-upload-zone {
                border: 2px dashed #b4b9be;
                border-radius: 4px;
                padding: 30px;
                text-align: center;
                background: #f8f8f8;
                transition: all 0.3s ease;
                margin-bottom: 20px;
            }

            .jetfb-upload-zone.dragover {
                background: #fff;
                border-color: #2271b1;
            }

            .jetfb-upload-content {
                position: relative;
            }

            .jetfb-upload-content .dashicons {
                font-size: 48px;
                width: 48px;
                height: 48px;
                color: #2271b1;
                margin-bottom: 10px;
            }

            .jetfb-upload-text {
                margin: 10px 0;
            }

            .jetfb-upload-text strong {
                display: block;
                font-size: 16px;
                margin-bottom: 5px;
            }

            .jetfb-upload-text span {
                display: block;
                margin: 5px 0;
                color: #646970;
            }

            .jetfb-browse-button {
                margin-top: 10px !important;
            }

            .jetfb-file-input {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                opacity: 0;
                cursor: pointer;
            }

            .jetfb-file-preview {
                display: none;
                margin-top: 15px;
                padding: 10px;
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 4px;
            }

            .jetfb-file-preview.has-file {
                display: block;
            }

            .submit {
                text-align: center;
            }

            .submit .button-hero {
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .submit .button-hero .dashicons {
                margin-top: 2px;
            }
        </style>

        <script>
            jQuery(document).ready(function($) {
                const $zone = $('.jetfb-upload-zone');
                const $input = $('.jetfb-file-input');
                const $preview = $('.jetfb-file-preview');
                const $form = $('.jetfb-upload-form');
                const $browseBtn = $('.jetfb-browse-button');

                // Handle drag and drop events
                $zone.on('dragover dragenter', function(e) {
                    e.preventDefault();
                    $zone.addClass('dragover');
                });

                $zone.on('dragleave dragend drop', function(e) {
                    e.preventDefault();
                    $zone.removeClass('dragover');
                });

                // Handle file selection
                $input.on('change', function() {
                    const file = this.files[0];
                    if (file) {
                        $preview.html(
                            '<strong><?php esc_html_e('Selected File:', 'jetfb-export-import'); ?></strong> ' +
                            file.name + ' (' + Math.round(file.size / 1024) + 'KB)'
                        ).addClass('has-file');
                    }
                });

                // Handle browse button click
                $browseBtn.on('click', function() {
                    $input.click();
                });
            });
        </script>
    <?php
    }

    private function render_preview_form()
    {
    ?>
        <form method="post" enctype="multipart/form-data">
            <p>
                <input type="file" name="import_file" accept=".csv" required>
            </p>
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Preview Import', 'jetfb-export-import'); ?>
                </button>
            </p>
        </form>
    <?php
    }

    private function render_preview_data()
    {
    ?>
        <div class="import-preview">
            <h3><?php esc_html_e('Preview Import Data', 'jetfb-export-import'); ?></h3>

            <p>
                <strong><?php esc_html_e('Total Records:', 'jetfb-export-import'); ?></strong>
                <?php echo count($this->preview_data['records']); ?><br>

                <strong><?php esc_html_e('Forms:', 'jetfb-export-import'); ?></strong>
                <?php echo implode(', ', $this->preview_data['summary']['forms']); ?>
            </p>

            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Form ID', 'jetfb-export-import'); ?></th>
                        <th><?php esc_html_e('Fields', 'jetfb-export-import'); ?></th>
                        <th><?php esc_html_e('Actions', 'jetfb-export-import'); ?></th>
                        <th><?php esc_html_e('Errors', 'jetfb-export-import'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->preview_data['records'] as $record): ?>
                        <tr>
                            <td><?php echo esc_html($record['main']['form_id']); ?></td>
                            <td>
                                <pre><?php print_r($record['fields']); ?></pre>
                            </td>
                            <td>
                                <pre><?php print_r($record['actions']); ?></pre>
                            </td>
                            <td>
                                <pre><?php print_r($record['errors']); ?></pre>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('jetfb_import_action', 'jetfb_import_nonce'); ?>
                <input type="hidden" name="action" value="jetfb_import_records">
                <input type="hidden" name="import_data" value="<?php echo esc_attr(base64_encode(serialize($this->preview_data['records']))); ?>">

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Confirm Import', 'jetfb-export-import'); ?>
                    </button>
                </p>
            </form>
        </div>
    <?php
    }

    private function render_error_message($message)
    {
    ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($message); ?></p>
        </div>
<?php
        $this->render_preview_form();
    }
}
