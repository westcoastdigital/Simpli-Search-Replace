<?php
/**
 * Database Processing Engine
 *
 * Handles preview and live search/replace operations.
 *
 * @package Simpli_Search_Replace
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SSR_Processor {

    /**
     * Serializer engine.
     *
     * @var SSR_Serializer
     */
    private $serializer;

    /**
     * Protected columns that should NEVER be modified.
     *
     * @var array
     */
    private $protected_columns = [
        'ID',
        'id',
        'option_id',
        'option_name',
        'meta_id',
        'meta_key',
        'user_id',
        'post_id',
        'term_id',
        'comment_id',
        'link_id',
        'slug',
        'post_name',
        'user_login',
        'user_email',
        'user_pass',
        'user_activation_key',
    ];

    /**
     * Cache primary keys per table.
     *
     * @var array
     */
    private $primary_key_cache = [];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->serializer = new SSR_Serializer();
    }

    /**
     * Run search/replace process.
     *
     * @param array $data
     * @param bool  $dry_run
     *
     * @return array
     */
    public function process( $data, $dry_run = true ) {

        global $wpdb;

        if ( empty( $data['search'] ) || empty( $data['tables'] ) ) {
            return [];
        }

        $search         = sanitize_text_field( $data['search'] );
        $replace        = isset( $data['replace'] ) ? sanitize_text_field( $data['replace'] ) : '';
        $tables         = array_map( 'sanitize_text_field', (array) $data['tables'] );
        $case_sensitive = ! empty( $data['case_sensitive'] );
        $replace_guids  = ! empty( $data['replace_guids'] );

        // Safety check: warn about potentially dangerous patterns
        $this->validate_search_pattern( $search );

        $results = [];
        $total_changes = 0;

        foreach ( $tables as $table ) {

            // Ensure table exists
            $table_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $table
                )
            );

            if ( $table_exists !== $table ) {
                continue;
            }

            $columns = $wpdb->get_results(
                "SHOW COLUMNS FROM `" . esc_sql( $table ) . "`"
            );

            if ( empty( $columns ) ) {
                continue;
            }

            foreach ( $columns as $column ) {

                $column_name = $column->Field;

                // Skip protected key columns
                if ( in_array( $column_name, $this->protected_columns, true ) ) {
                    continue;
                }

                // Skip GUID unless explicitly enabled
                if ( $column_name === 'guid' && ! $replace_guids ) {
                    continue;
                }

                // Only process textual columns
                $type = strtolower( $column->Type );

                if (
                    strpos( $type, 'char' ) === false &&
                    strpos( $type, 'text' ) === false &&
                    strpos( $type, 'longtext' ) === false &&
                    strpos( $type, 'mediumtext' ) === false
                ) {
                    continue;
                }

                // Prepare LIKE query safely
                $like = '%' . $wpdb->esc_like( $search ) . '%';

                $query = $wpdb->prepare(
                    "SELECT * FROM `" . esc_sql( $table ) . "` WHERE `" . esc_sql( $column_name ) . "` LIKE %s",
                    $like
                );

                $rows = $wpdb->get_results( $query, ARRAY_A );

                if ( empty( $rows ) ) {
                    continue;
                }

                $primary_key = $this->get_primary_key_column( $table );

                foreach ( $rows as $row ) {

                    if ( ! isset( $row[ $column_name ] ) ) {
                        continue;
                    }

                    $original = $row[ $column_name ];

                    $replaced = $this->serializer->recursive_replace(
                        $search,
                        $replace,
                        $original,
                        $case_sensitive
                    );

                    if ( $original === $replaced ) {
                        continue;
                    }

                    // Additional safety: skip if replacement seems dangerous
                    if ( ! $dry_run && $this->is_dangerous_replacement( $original, $replaced, $column_name ) ) {
                        continue;
                    }

                    $total_changes++;

                    // Limit preview results to prevent overwhelming the UI
                    if ( $dry_run && $total_changes > 500 ) {
                        $results[] = [
                            'table'    => 'LIMIT_REACHED',
                            'column'   => '',
                            'primary'  => '',
                            'original' => '',
                            'new'      => 'Preview limited to 500 changes. Total changes may be higher.',
                        ];
                        break 3; // Break out of all loops
                    }

                    $results[] = [
                        'table'    => esc_html( $table ),
                        'column'   => esc_html( $column_name ),
                        'primary'  => esc_html( $row[ $primary_key ] ?? '' ),
                        'original' => esc_html( $original ),
                        'new'      => esc_html( $replaced ),
                    ];

                    // Skip DB write if preview
                    if ( $dry_run ) {
                        continue;
                    }

                    // Live update
                    if ( isset( $row[ $primary_key ] ) ) {
                        $wpdb->update(
                            $table,
                            [ $column_name => $replaced ],
                            [ $primary_key => $row[ $primary_key ] ],
                            [ '%s' ],
                            [ '%d' ]
                        );
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Validate search pattern for potentially dangerous operations.
     *
     * @param string $search
     * @return void
     */
    private function validate_search_pattern( $search ) {
        // This is a passive check - we don't block anything, just log concerns
        // In a production environment, you might want to add more sophisticated checks
        
        // Check for very short search terms that might cause mass replacements
        if ( strlen( $search ) < 3 ) {
            // Very short search terms are risky
            error_log( 'SSR Warning: Very short search term used: ' . $search );
        }
    }

    /**
     * Check if a replacement seems dangerous.
     *
     * @param string $original
     * @param string $replaced
     * @param string $column_name
     * @return bool
     */
    private function is_dangerous_replacement( $original, $replaced, $column_name ) {
        
        // Don't allow emptying critical fields
        $critical_fields = [ 'post_title', 'post_content', 'comment_content', 'option_value', 'meta_value' ];
        
        if ( in_array( $column_name, $critical_fields, true ) && empty( $replaced ) && ! empty( $original ) ) {
            error_log( 'SSR: Blocked dangerous replacement - emptying critical field: ' . $column_name );
            return true;
        }

        return false;
    }

    /**
     * Get primary key column for table.
     *
     * @param string $table
     * @return string
     */
    private function get_primary_key_column( $table ) {

        global $wpdb;

        if ( isset( $this->primary_key_cache[ $table ] ) ) {
            return $this->primary_key_cache[ $table ];
        }

        $primary = $wpdb->get_row(
            "SHOW KEYS FROM `" . esc_sql( $table ) . "` WHERE Key_name = 'PRIMARY'"
        );

        if ( ! empty( $primary->Column_name ) ) {
            $this->primary_key_cache[ $table ] = $primary->Column_name;
            return $primary->Column_name;
        }

        // Fallback
        $this->primary_key_cache[ $table ] = 'ID';
        return 'ID';
    }
}