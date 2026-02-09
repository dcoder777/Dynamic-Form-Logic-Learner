<?php

if (! defined('ABSPATH')) {
    exit;
}

class DFLL_Data_Store
{
    private wpdb $wpdb;
    private string $submissions_table;
    private string $metrics_table;
    private string $rules_table;
    private string $ab_tests_table;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb             = $wpdb;
        $this->submissions_table = $wpdb->prefix . 'dfll_submissions';
        $this->metrics_table     = $wpdb->prefix . 'dfll_field_metrics';
        $this->rules_table       = $wpdb->prefix . 'dfll_logic_rules';
        $this->ab_tests_table    = $wpdb->prefix . 'dfll_ab_tests';
    }

    public function record_submission(array $submission): int
    {
        $defaults = [
            'form_id'            => '',
            'user_id'            => get_current_user_id() ?: null,
            'session_id'         => '',
            'variation_id'       => 'default',
            'completion_status'  => 'completed',
            'submitted_at'       => current_time('mysql'),
            'payload'            => wp_json_encode([]),
        ];

        $data = wp_parse_args($submission, $defaults);

        $this->wpdb->insert($this->submissions_table, $data, ['%s', '%d', '%s', '%s', '%s', '%s', '%s']);

        return (int) $this->wpdb->insert_id;
    }

    public function record_field_metrics(int $submission_id, string $form_id, array $metrics): void
    {
        foreach ($metrics as $row) {
            $this->wpdb->insert(
                $this->metrics_table,
                [
                    'submission_id' => $submission_id,
                    'form_id'       => $form_id,
                    'field_key'     => sanitize_key((string) ($row['field_key'] ?? '')),
                    'field_type'    => sanitize_text_field((string) ($row['field_type'] ?? 'text')),
                    'time_spent_ms' => absint($row['time_spent_ms'] ?? 0),
                    'skipped'       => ! empty($row['skipped']) ? 1 : 0,
                    'step_index'    => absint($row['step_index'] ?? 0),
                    'created_at'    => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
            );
        }
    }

    public function get_field_analytics(string $form_id): array
    {
        $query = $this->wpdb->prepare(
            "SELECT field_key,
                    AVG(time_spent_ms) AS avg_time_spent_ms,
                    SUM(skipped) AS skipped_count,
                    COUNT(*) AS appearances,
                    ROUND((SUM(skipped) / COUNT(*)) * 100, 2) AS abandonment_rate
             FROM {$this->metrics_table}
             WHERE form_id = %s
             GROUP BY field_key
             ORDER BY abandonment_rate DESC",
            $form_id
        );

        return (array) $this->wpdb->get_results($query, ARRAY_A);
    }

    public function get_abandonment_by_field(string $form_id): array
    {
        return $this->get_field_analytics($form_id);
    }

    public function get_top_skip_fields(string $form_id, int $limit = 5): array
    {
        $query = $this->wpdb->prepare(
            "SELECT field_key, SUM(skipped) AS skipped_count
             FROM {$this->metrics_table}
             WHERE form_id = %s
             GROUP BY field_key
             ORDER BY skipped_count DESC
             LIMIT %d",
            $form_id,
            $limit
        );

        return (array) $this->wpdb->get_results($query, ARRAY_A);
    }

    public function get_submissions(string $form_id, int $limit = 200): array
    {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->submissions_table}
             WHERE form_id = %s
             ORDER BY submitted_at DESC
             LIMIT %d",
            $form_id,
            $limit
        );

        return (array) $this->wpdb->get_results($query, ARRAY_A);
    }

    public function get_existing_rules(string $form_id): array
    {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->rules_table} WHERE form_id = %s ORDER BY updated_at DESC",
            $form_id
        );

        return (array) $this->wpdb->get_results($query, ARRAY_A);
    }

    public function save_suggested_rule(string $form_id, array $rule): int
    {
        $now = current_time('mysql');
        $this->wpdb->insert(
            $this->rules_table,
            [
                'form_id'       => $form_id,
                'source_field'  => sanitize_key($rule['source_field'] ?? ''),
                'operator'      => sanitize_text_field($rule['operator'] ?? '='),
                'source_value'  => sanitize_text_field($rule['source_value'] ?? ''),
                'target_field'  => sanitize_key($rule['target_field'] ?? ''),
                'action'        => sanitize_text_field($rule['action'] ?? 'show'),
                'confidence'    => (float) ($rule['confidence'] ?? 0),
                'status'        => sanitize_text_field($rule['status'] ?? 'suggested'),
                'metadata'      => wp_json_encode($rule['metadata'] ?? []),
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s']
        );

        return (int) $this->wpdb->insert_id;
    }

    public function update_rule_status(int $rule_id, string $status): bool
    {
        return (bool) $this->wpdb->update(
            $this->rules_table,
            ['status' => sanitize_text_field($status), 'updated_at' => current_time('mysql')],
            ['id' => $rule_id],
            ['%s', '%s'],
            ['%d']
        );
    }

    public function record_ab_test(string $form_id, string $variation_id, array $config): int
    {
        $this->wpdb->insert(
            $this->ab_tests_table,
            [
                'form_id'      => $form_id,
                'variation_id' => $variation_id,
                'config'       => wp_json_encode($config),
                'started_at'   => current_time('mysql'),
                'is_active'    => 1,
            ],
            ['%s', '%s', '%s', '%s', '%d']
        );

        return (int) $this->wpdb->insert_id;
    }
}
