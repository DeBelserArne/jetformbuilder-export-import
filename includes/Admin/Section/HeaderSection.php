<?php

namespace JetFB\ExportImport\Admin\Section;

class HeaderSection extends AbstractSection
{
    public function render()
    {
?>
        <div class="wrap">
            <h1><?php echo esc_html__('JetFormBuilder Export Import', 'jetfb-export-import'); ?></h1>
            <div class="notice-container">
                <?php $this->render_notices(); ?>
            </div>
            <?php
        }

        private function render_notices()
        {
            if (isset($_GET['imported']) && $_GET['imported'] == '1') {
            ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Records imported successfully!', 'jetfb-export-import'); ?></p>
                </div>
            <?php
            }

            if (isset($_GET['error'])) {
            ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html(urldecode($_GET['error'])); ?></p>
                </div>
    <?php
            }
        }
    }
