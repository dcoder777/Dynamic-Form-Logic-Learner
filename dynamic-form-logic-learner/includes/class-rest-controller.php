<?php

if (! defined('ABSPATH')) {
    exit;
}

class DFLL_REST_Controller
{
    private DFLL_Data_Store $data_store;
    private DFLL_Analytics_Engine $analytics;

    public function __construct(DFLL_Data_Store $data_store, DFLL_Analytics_Engine $analytics)
    {
        $this->data_store = $data_store;
        $this->analytics  = $analytics;

        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('dfll/v1', '/suggestions/(?P<form_id>[a-zA-Z0-9_-]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_suggestions'],
            'permission_callback' => [$this, 'can_manage_forms'],
        ]);

        register_rest_route('dfll/v1', '/analytics/(?P<form_id>[a-zA-Z0-9_-]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_analytics'],
            'permission_callback' => [$this, 'can_manage_forms'],
        ]);

        register_rest_route('dfll/v1', '/rules/(?P<rule_id>\d+)/status', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'update_rule_status'],
            'permission_callback' => [$this, 'can_manage_forms'],
        ]);
    }

    public function can_manage_forms(): bool
    {
        return current_user_can('manage_options') || current_user_can('edit_posts');
    }

    public function get_suggestions(WP_REST_Request $request): WP_REST_Response
    {
        $form_id = sanitize_text_field((string) $request['form_id']);
        $items   = $this->analytics->generate_suggestions($form_id);

        return new WP_REST_Response(['form_id' => $form_id, 'items' => $items]);
    }

    public function get_analytics(WP_REST_Request $request): WP_REST_Response
    {
        $form_id = sanitize_text_field((string) $request['form_id']);

        return new WP_REST_Response($this->analytics->get_dashboard_data($form_id));
    }

    public function update_rule_status(WP_REST_Request $request): WP_REST_Response
    {
        $rule_id = absint($request['rule_id']);
        $status  = sanitize_text_field((string) $request->get_param('status'));
        $rule    = (array) $request->get_param('rule');

        if (! in_array($status, ['accepted', 'rejected', 'auto_applied', 'suggested'], true)) {
            return new WP_REST_Response(['message' => 'Invalid status'], 400);
        }

        if ($rule_id === 0 && ! empty($rule['form_id'])) {
            $rule['status'] = $status;
            $rule_id        = $this->data_store->save_suggested_rule(sanitize_text_field((string) $rule['form_id']), $rule);

            return new WP_REST_Response(['updated' => true, 'rule_id' => $rule_id], 201);
        }

        $saved = $this->data_store->update_rule_status($rule_id, $status);

        return new WP_REST_Response(['updated' => $saved, 'rule_id' => $rule_id]);
    }
}
