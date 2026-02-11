<?php
if (! defined('ABSPATH')) {
    exit;
}

class SSR_Admin
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        add_action('wp_ajax_ssr_preview', [$this, 'ajax_preview']);
        add_action('wp_ajax_ssr_replace', [$this, 'ajax_replace']);
    }

    public function register_menu()
    {
        add_management_page(
            __('Simpli Search Replace', 'simpli'),
            __('Simpli Search Replace', 'simpli'),
            'manage_options',
            'simpli-search-replace',
            [$this, 'render_page']
        );
    }

    public function enqueue_assets($hook)
    {
        if ($hook !== 'tools_page_simpli-search-replace') {
            return;
        }

        wp_enqueue_script(
            'ssr-admin',
            SSR_URL . 'assets/admin.js',
            ['jquery'],
            SSR_VERSION,
            true
        );

        // Translatable JS strings
        $js_strings = [
            'ajax_url'          => admin_url('admin-ajax.php'),
            'nonce'             => wp_create_nonce('ssr_nonce'),
            'select_table_error' => __('Please select at least one table.', 'simpli'),
            'enter_search_error' => __('Please enter a search term.', 'simpli'),
            'processing'        => __('Processing...', 'simpli'),
            'no_matches'        => __('No matches found.', 'simpli'),
            'preview_notice'    => __('This is a preview only. No changes have been made to the database.', 'simpli'),
            'replacement_done'  => __('Replacement completed successfully!', 'simpli'),
            'run_confirm'       => __('Are you sure you want to run this replacement?', 'simpli'),
            'permanent_modify'  => __('⚠️ This will PERMANENTLY modify your database!', 'simpli'),
            'cannot_undo'       => __('⚠️ This action CANNOT be undone!', 'simpli'),
            'critical_warning'  => __('⚠️⚠️⚠️ WARNING: You have selected CRITICAL SYSTEM TABLES!', 'simpli'),
            'critical_info'     => __('Modifying these tables can break your entire site!', 'simpli'),
            'backed_up'         => __('Have you backed up your database?', 'simpli'),
            'type_yes'          => __('Type YES to confirm:', 'simpli'),
            'cancelled'         => __('Replacement cancelled. You must type YES to proceed.', 'simpli'),
            'preview_required'  => __('← Preview required (form was changed)', 'simpli'),
            'before_label'      => __('Before:', 'simpli'),
            'after_label'       => __('After:', 'simpli'),
            'table_label'       => __('Table:', 'simpli'),
            'column_label'      => __('Column:', 'simpli'),
            'id_label'          => __('ID:', 'simpli'),
            'results_title'   => __('Results', 'simpli'),
            'changes_found'   => __('changes found', 'simpli'),
            'changes_done'    => __('changes done', 'simpli'),
            'error_occurred'  => __('An error occurred. Please try again.', 'simpli'),
            'tables_selected' => __('Tables selected:', 'simpli'),
        ];

        wp_localize_script('ssr-admin', 'SSR_Ajax', $js_strings);

        wp_enqueue_style(
            'ssr-admin',
            SSR_URL . 'assets/admin.css',
            [],
            SSR_VERSION
        );
    }


    public function render_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $tables = $wpdb->get_col('SHOW TABLES');

        $critical_tables = [
            $wpdb->users,
            $wpdb->usermeta,
        ];
?>
        <div class="wrap">
            <h1><?php echo esc_html__('Simpli Search Replace', 'simpli'); ?></h1>

            <div class="notice notice-warning ssr-notice">
                <p><strong>⚠️ <?php echo __('CRITICAL WARNING:', 'simpli'); ?></strong></p>
                <ul>
                    <li><strong><?php echo __('ALWAYS backup your database before running any replacement!', 'simpli'); ?></strong></li>
                    <li><?php echo __('Test with Preview first - never run replacements without previewing', 'simpli'); ?></li>
                    <li><?php echo __('Be especially careful with user tables and serialised data', 'simpli'); ?></li>
                    <li><?php echo __('Replacing URLs or paths can break your site if done incorrectly', 'simpli'); ?></li>
                    <li><?php echo __('This tool cannot be undone - only a database backup can restore your data', 'simpli'); ?></li>
                </ul>
            </div>

            <form id="ssr-form">
                <?php wp_nonce_field('ssr_nonce', 'ssr_nonce_field'); ?>

                <div class="search-wrapper">
                    <div class="left">
                        <h4><?php echo esc_html__('Search For', 'simpli'); ?></h4>
                        <input type="text" name="search" id="ssr-search" class="regular-text" required>
                        <p class="description"><?php echo esc_html__('The text/URL you want to find and replace', 'simpli'); ?></p>
                    </div>
                    <div class="right">
                        <h4><?php echo esc_html__('Replace With', 'simpli'); ?></h4>
                        <input type="text" name="replace" id="ssr-replace" class="regular-text">
                        <p class="description"><?php echo esc_html__('The new text/URL (leave empty to delete the search text)', 'simpli'); ?></p>
                    </div>
                </div>

                <div class="table-wrapper">
                    <h2><?php echo esc_html__('Select Tables', 'simpli'); ?></h2>
                    <p class="description">
                        <?php echo esc_html__('Use Ctrl+Click to select multiple tables, or Shift+Click to select a range.', 'simpli'); ?>
                        <?php echo esc_html__('Tables marked with ⚠️ are critical system tables.', 'simpli'); ?>
                    </p>

                    <p>
                        <button type="button" class="button" id="ssr-select-all"><?php echo esc_html__('Select All Tables', 'simpli'); ?></button>
                        <button type="button" class="button" id="ssr-deselect-all"><?php echo esc_html__('Deselect All', 'simpli'); ?></button>
                        <button type="button" class="button" id="ssr-select-safe"><?php echo esc_html__('Select Safe Tables Only', 'simpli'); ?></button>
                    </p>

                    <select name="tables[]" id="ssr-table-selector" class="ssr-table-selector" multiple size="15" required>
                        <?php
                        foreach ($tables as $table) :
                            $is_critical = in_array($table, $critical_tables);
                            $label = $is_critical
                                ? sprintf('%s ⚠️ (%s)', $table, __('Critical', 'simpli'))
                                : $table;
                        ?>
                            <option value="<?php echo esc_attr($table); ?>" data-critical="<?php echo $is_critical ? '1' : '0'; ?>">
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="table-wrapper">
                    <h2><?php echo esc_html__('Options', 'simpli'); ?></h2>
                    <p>
                        <label>
                            <input type="checkbox" name="case_sensitive" checked>
                            <strong><?php echo esc_html__('Case Sensitive', 'simpli'); ?></strong> - <?php echo esc_html__('Match exact capitalization (recommended for URLs)', 'simpli'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="replace_guids">
                            <strong><?php echo esc_html__('Replace GUIDs', 'simpli'); ?></strong> - ⚠️ <?php echo esc_html__('Only enable if you know what you\'re doing! GUIDs should normally not be changed.', 'simpli'); ?>
                        </label>
                    </p>
                </div>

                <div class="table-wrapper">
                    <h2><?php echo esc_html__('Run Operation', 'simpli'); ?></h2>
                    <p class="description">
                        <strong><?php echo esc_html__('Important:', 'simpli'); ?></strong> <?php echo esc_html__('You MUST preview your changes before running the replacement!', 'simpli'); ?>
                    </p>

                    <p>
                        <button type="button" class="button button-large" id="ssr-preview">
                            <span class="dashicons dashicons-search" style="margin-top: 3px;"></span> <?php echo esc_html__('Preview Changes', 'simpli'); ?>
                        </button>
                        <button type="button" class="button button-primary button-large" id="ssr-run" disabled>
                            <span class="dashicons dashicons-database-import" style="margin-top: 3px;"></span> <?php echo esc_html__('Run Replacement', 'simpli'); ?>
                        </button>
                        <span id="ssr-preview-required" style="color: #d63638; margin-left: 10px;">
                            ← <?php echo esc_html__('Preview required before running replacement', 'simpli'); ?>
                        </span>
                    </p>
                </div>
            </form>

            <div id="ssr-results"></div>
        </div>
<?php
    }

    public function ajax_preview()
    {
        check_ajax_referer('ssr_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'simpli'));
        }

        $processor = new SSR_Processor();
        $results   = $processor->process($_POST, true);

        wp_send_json_success($results);
    }

    public function ajax_replace()
    {
        check_ajax_referer('ssr_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'simpli'));
        }

        $processor = new SSR_Processor();
        $results   = $processor->process($_POST, false);

        wp_send_json_success($results);
    }
}
