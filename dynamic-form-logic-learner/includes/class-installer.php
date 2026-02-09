<?php

if (! defined('ABSPATH')) {
    exit;
}

class DFLL_Installer
{
    public static function activate(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $submissions     = $wpdb->prefix . 'dfll_submissions';
        $field_metrics   = $wpdb->prefix . 'dfll_field_metrics';
        $rules           = $wpdb->prefix . 'dfll_logic_rules';
        $ab_tests        = $wpdb->prefix . 'dfll_ab_tests';

        $sql = [];

        $sql[] = "CREATE TABLE {$submissions} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id VARCHAR(191) NOT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            session_id VARCHAR(191) DEFAULT NULL,
            variation_id VARCHAR(191) DEFAULT 'default',
            completion_status VARCHAR(20) NOT NULL DEFAULT 'completed',
            submitted_at DATETIME NOT NULL,
            payload LONGTEXT NOT NULL,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY completion_status (completion_status),
            KEY submitted_at (submitted_at)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$field_metrics} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            submission_id BIGINT UNSIGNED NOT NULL,
            form_id VARCHAR(191) NOT NULL,
            field_key VARCHAR(191) NOT NULL,
            field_type VARCHAR(50) DEFAULT NULL,
            time_spent_ms INT UNSIGNED DEFAULT 0,
            skipped TINYINT(1) DEFAULT 0,
            step_index SMALLINT DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY submission_id (submission_id),
            KEY form_field (form_id, field_key)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$rules} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id VARCHAR(191) NOT NULL,
            source_field VARCHAR(191) NOT NULL,
            operator VARCHAR(20) NOT NULL,
            source_value VARCHAR(191) NOT NULL,
            target_field VARCHAR(191) NOT NULL,
            action VARCHAR(20) NOT NULL DEFAULT 'show',
            confidence DECIMAL(5,2) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'suggested',
            metadata LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY form_status (form_id, status)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$ab_tests} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id VARCHAR(191) NOT NULL,
            variation_id VARCHAR(191) NOT NULL,
            config LONGTEXT NOT NULL,
            started_at DATETIME NOT NULL,
            ended_at DATETIME DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY form_variation (form_id, variation_id)
        ) {$charset_collate};";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        add_option('dfll_db_version', DFLL_VERSION);

        if (! get_option('dfll_capture_token')) {
            add_option('dfll_capture_token', wp_generate_password(32, false, false));
        }
    }

    public static function deactivate(): void
    {
        // Keep data for analytics continuity. A uninstall.php can remove data explicitly.
    }
}
