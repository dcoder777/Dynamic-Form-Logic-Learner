<?php

if (! defined('ABSPATH')) {
    exit;
}

class DFLL_Integration
{
    private DFLL_Form_Handler $handler;
    private DFLL_Data_Store $data_store;

    public function __construct(DFLL_Form_Handler $handler, DFLL_Data_Store $data_store)
    {
        $this->handler    = $handler;
        $this->data_store = $data_store;

        add_action('wpforms_process_complete', [$this, 'capture_wpforms_submission'], 10, 4);
        add_action('gform_after_submission', [$this, 'capture_gravity_submission'], 10, 2);
        add_action('fluentform_submission_inserted', [$this, 'capture_fluent_submission'], 10, 3);

        add_action('rest_api_init', [$this, 'register_capture_endpoint']);
    }

    public function register_capture_endpoint(): void
    {
        register_rest_route('dfll/v1', '/capture', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'capture_generic_submission'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function capture_wpforms_submission(array $fields, array $entry, array $form_data): void
    {
        $payload = [];
        foreach ($fields as $field) {
            $payload[$field['name'] ?? 'field_' . ($field['id'] ?? wp_rand())] = $field['value'] ?? '';
        }

        $this->handler->record_submission([
            'form_id'      => 'wpforms_' . ($form_data['id'] ?? 'unknown'),
            'payload'      => $payload,
            'session_id'   => sanitize_text_field((string) ($_COOKIE['wordpress_logged_in_'] ?? wp_generate_uuid4())),
            'variation_id' => sanitize_text_field((string) ($_REQUEST['dfll_variation'] ?? 'default')),
        ], $this->collect_basic_metrics($payload));
    }

    public function capture_gravity_submission(array $entry, array $form): void
    {
        $payload = [];
        foreach ($form['fields'] ?? [] as $field) {
            $key = 'gf_' . $field->id;
            $payload[$key] = rgar($entry, (string) $field->id);
        }

        $this->handler->record_submission([
            'form_id'      => 'gravity_' . ($form['id'] ?? 'unknown'),
            'payload'      => $payload,
            'variation_id' => sanitize_text_field((string) ($_REQUEST['dfll_variation'] ?? 'default')),
        ], $this->collect_basic_metrics($payload));
    }

    public function capture_fluent_submission(int $entry_id, array $form_data, array $form): void
    {
        $this->handler->record_submission([
            'form_id'      => 'fluent_' . ($form['id'] ?? 'unknown'),
            'payload'      => $form_data,
            'variation_id' => sanitize_text_field((string) ($_REQUEST['dfll_variation'] ?? 'default')),
        ], $this->collect_basic_metrics($form_data));
    }

    public function capture_generic_submission(WP_REST_Request $request): WP_REST_Response
    {
        $params        = $request->get_json_params();
        $token         = (string) ($params['token'] ?? '');
        $stored_token  = (string) get_option('dfll_capture_token', '');

        if ($stored_token === '' || $token === '' || ! hash_equals($stored_token, $token)) {
            return new WP_REST_Response(['message' => 'Unauthorized token'], 403);
        }

        $submission_id = $this->handler->record_submission([
            'form_id'      => sanitize_text_field((string) ($params['form_id'] ?? 'unknown')),
            'payload'      => (array) ($params['payload'] ?? []),
            'variation_id' => sanitize_text_field((string) ($params['variation_id'] ?? 'default')),
            'session_id'   => sanitize_text_field((string) ($params['session_id'] ?? wp_generate_uuid4())),
        ], (array) ($params['field_metrics'] ?? []));

        return new WP_REST_Response(['submission_id' => $submission_id], 201);
    }

    private function collect_basic_metrics(array $payload): array
    {
        $metrics = [];
        $index   = 0;

        foreach ($payload as $field => $value) {
            $metrics[] = [
                'field_key'     => $field,
                'field_type'    => 'text',
                'time_spent_ms' => 500 + ($index * 80),
                'skipped'       => $value === '' || $value === null,
                'step_index'    => (int) floor($index / 3),
            ];
            $index++;
        }

        return $metrics;
    }
}
