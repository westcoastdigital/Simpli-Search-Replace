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
            'Simpli Search Replace',
            'Simpli Search Replace',
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

        wp_localize_script('ssr-admin', 'SSR_Ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ssr_nonce'),
        ]);

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

        // Identify critical WordPress tables
        $critical_tables = [
            $wpdb->users,
            $wpdb->usermeta,
        ];
?>
        <div class="wrap">
            <h1>Simpli Search Replace</h1>

            <div class="notice notice-warning ssr-notice">
                <p><strong>⚠️ CRITICAL WARNING:</strong></p>
                <ul>
                    <li><strong>ALWAYS backup your database before running any replacement!</strong></li>
                    <li>Test with Preview first - never run replacements without previewing</li>
                    <li>Be especially careful with user tables and serialised data</li>
                    <li>Replacing URLs or paths can break your site if done incorrectly</li>
                    <li>This tool cannot be undone - only a database backup can restore your data</li>
                </ul>
            </div>

            <form id="ssr-form">
                <?php wp_nonce_field('ssr_nonce', 'ssr_nonce_field'); ?>

                <div class="search-wrapper">
                    <div class="left">
                        <h4>Search For</h4>
                        <input type="text" name="search" id="ssr-search" class="regular-text" required>
                        <p class="description">The text/URL you want to find and replace</p>
                    </div>
                    <div class="right">
                        <h4>Replace With</h4>
                        <input type="text" name="replace" id="ssr-replace" class="regular-text">
                        <p class="description">The new text/URL (leave empty to delete the search text)</p>
                    </div>
                </div>

                <!-- <table class="form-table">
                    <tr>
                        <th>Search For</th>
                        <td>
                            <input type="text" name="search" id="ssr-search" class="regular-text" required>
                            <p class="description">The text/URL you want to find and replace</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Replace With</th>
                        <td>
                            <input type="text" name="replace" id="ssr-replace" class="regular-text">
                            <p class="description">The new text/URL (leave empty to delete the search text)</p>
                        </td>
                    </tr>
                </table> -->

                <div class="table-wrapper">
                    <h2>Select Tables</h2>
                    <p class="description">
                        Use Ctrl+Click to select multiple tables, or Shift+Click to select a range.
                        Tables marked with ⚠️ are critical system tables.
                    </p>

                    <p>
                        <button type="button" class="button" id="ssr-select-all">Select All Tables</button>
                        <button type="button" class="button" id="ssr-deselect-all">Deselect All</button>
                        <button type="button" class="button" id="ssr-select-safe">Select Safe Tables Only</button>
                    </p>

                    <select name="tables[]" id="ssr-table-selector" class="ssr-table-selector" multiple size="15" required>
                        <?php
                        foreach ($tables as $table) :
                            $is_critical = in_array($table, $critical_tables);
                            $label = $is_critical ? $table . ' ⚠️ (Critical)' : $table;
                        ?>
                            <option value="<?php echo esc_attr($table); ?>" data-critical="<?php echo $is_critical ? '1' : '0'; ?>">
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="table-wrapper">
                    <h2>Options</h2>
                    <p>
                        <label>
                            <input type="checkbox" name="case_sensitive" checked>
                            <strong>Case Sensitive</strong> - Match exact capitalization (recommended for URLs)
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="replace_guids">
                            <strong>Replace GUIDs</strong> - ⚠️ Only enable if you know what you're doing! GUIDs should normally not be changed.
                        </label>
                    </p>
                </div>

                <div class="table-wrapper">
                    <h2>Run Operation</h2>
                    <p class="description">
                        <strong>Important:</strong> You MUST preview your changes before running the replacement!
                    </p>

                    <p>
                        <button type="button" class="button button-large" id="ssr-preview">
                            <span class="dashicons dashicons-search" style="margin-top: 3px;"></span> Preview Changes
                        </button>
                        <button type="button" class="button button-primary button-large" id="ssr-run" disabled>
                            <span class="dashicons dashicons-database-import" style="margin-top: 3px;"></span> Run Replacement
                        </button>
                        <span id="ssr-preview-required" style="color: #d63638; margin-left: 10px;">
                            ← Preview required before running replacement
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
            wp_send_json_error('Permission denied');
        }

        $processor = new SSR_Processor();
        $results   = $processor->process($_POST, true);

        wp_send_json_success($results);
    }

    public function ajax_replace()
    {
        check_ajax_referer('ssr_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $processor = new SSR_Processor();
        $results   = $processor->process($_POST, false);

        wp_send_json_success($results);
    }
}