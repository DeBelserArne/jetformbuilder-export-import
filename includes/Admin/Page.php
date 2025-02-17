<?php

namespace JetFB\ExportImport\Admin;

class Page
{
    public function render()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $this->render_header();
        $this->render_export_section();
        $this->render_import_section();
        $this->render_footer();
    }

    private function render_header()
    {
?>
        <div class="wrap">
            <h1><?php echo esc_html__('JetFormBuilder Export Import', 'jetfb-export-import'); ?></h1>
            <?php if (isset($_GET['imported']) && $_GET['imported'] == '1'): ?>
                <div class="notice notice-success">
                    <p><?php esc_html_e('Records imported successfully!', 'jetfb-export-import'); ?></p>
                </div>
            <?php endif; ?>
        <?php
    }

    private function render_export_section()
    {
        $export_handler = new \JetFB\ExportImport\Export\Handler();
        $available_forms = $export_handler->get_available_forms();
        ?>
            <div class="card">
                <h2><?php esc_html_e('Export Records', 'jetfb-export-import'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('jetfb_export_action', 'jetfb_export_nonce'); ?>
                    <input type="hidden" name="action" value="jetfb_export_records">

                    <div class="form-field">
                        <label><strong><?php esc_html_e('Select Forms to Export:', 'jetfb-export-import'); ?></strong></label>
                        <div style="margin: 10px 0;">
                            <?php foreach ($available_forms as $form): ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox"
                                        name="form_ids[]"
                                        value="<?php echo esc_attr($form['id']); ?>">
                                    <?php echo esc_html($form['title']); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e('Export to CSV', 'jetfb-export-import'); ?>
                        </button>
                    </p>
                </form>
            </div>
        <?php
    }

    private function render_import_section()
    {
        ?>
            <div class="card">
                <h2><?php esc_html_e('Import Records', 'jetfb-export-import'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field('jetfb_import_action', 'jetfb_import_nonce'); ?>
                    <input type="hidden" name="action" value="jetfb_import_records">
                    <p>
                        <input type="file" name="import_file" accept=".csv" required>
                    </p>
                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e('Import from CSV', 'jetfb-export-import'); ?>
                        </button>
                    </p>
                </form>
            </div>
    <?php
    }

    private function render_footer()
    {
        echo '</div>';
    }
}
