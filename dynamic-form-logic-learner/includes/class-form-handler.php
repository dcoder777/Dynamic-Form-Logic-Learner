<?php

if (! defined('ABSPATH')) {
    exit;
}

class DFLL_Form_Handler
{
    private DFLL_Data_Store $data_store;

    public function __construct(DFLL_Data_Store $data_store)
    {
        $this->data_store = $data_store;
    }

    public function record_submission(array $submission, array $field_metrics = []): int
    {
        $submission_id = $this->data_store->record_submission([
            'form_id'           => sanitize_text_field((string) ($submission['form_id'] ?? '')),
            'user_id'           => absint($submission['user_id'] ?? get_current_user_id()),
            'session_id'        => sanitize_text_field((string) ($submission['session_id'] ?? wp_generate_uuid4())),
            'variation_id'      => sanitize_text_field((string) ($submission['variation_id'] ?? 'default')),
            'completion_status' => sanitize_text_field((string) ($submission['completion_status'] ?? 'completed')),
            'submitted_at'      => current_time('mysql'),
            'payload'           => wp_json_encode((array) ($submission['payload'] ?? [])),
        ]);

        if (! empty($field_metrics) && ! empty($submission['form_id'])) {
            $this->data_store->record_field_metrics($submission_id, sanitize_text_field($submission['form_id']), $field_metrics);
        }

        return $submission_id;
    }
}
