<?php

namespace JetFB\ExportImport\Admin\Section;

use JetFB\ExportImport\Export\Handler as ExportHandler;

class ExportSection extends AbstractSection
{
    public function render()
    {
        $export_handler = new ExportHandler();
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
                                <input type="checkbox" name="form_ids[]" value="<?php echo esc_attr($form['id']); ?>">
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
}
