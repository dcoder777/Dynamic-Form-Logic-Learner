<?php

if (! defined('ABSPATH')) {
    exit;
}

class DFLL_Admin_UI
{
    private DFLL_Data_Store $data_store;
    private DFLL_Analytics_Engine $analytics;

    public function __construct(DFLL_Data_Store $data_store, DFLL_Analytics_Engine $analytics)
    {
        $this->data_store = $data_store;
        $this->analytics  = $analytics;

        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_post_dfll_export_report', [$this, 'export_report']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('Dynamic Logic Learner', 'dynamic-form-logic-learner'),
            __('Logic Learner', 'dynamic-form-logic-learner'),
            'edit_posts',
            'dfll-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-randomize'
        );
    }

    public function enqueue_assets(string $hook): void
    {
        if ($hook !== 'toplevel_page_dfll-dashboard') {
            return;
        }

        wp_enqueue_style('dfll-admin', DFLL_PLUGIN_URL . 'assets/css/admin.css', [], DFLL_VERSION);
        wp_enqueue_script('dfll-flow-editor', DFLL_PLUGIN_URL . 'assets/js/flow-editor.js', [], DFLL_VERSION, true);

        wp_localize_script('dfll-flow-editor', 'dfllApp', [
            'apiRoot' => esc_url_raw(rest_url('dfll/v1')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    public function render_dashboard(): void
    {
        if (! current_user_can('edit_posts')) {
            wp_die(esc_html__('You are not allowed to access this page.', 'dynamic-form-logic-learner'));
        }

        $form_id = sanitize_text_field((string) ($_GET['form_id'] ?? 'demo_form'));
        $data    = $this->analytics->get_dashboard_data($form_id);

        include DFLL_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }

    public function export_report(): void
    {
        if (! current_user_can('edit_posts') || ! check_admin_referer('dfll_export_report')) {
            wp_die(esc_html__('Unauthorized request.', 'dynamic-form-logic-learner'));
        }

        $form_id = sanitize_text_field((string) ($_POST['form_id'] ?? ''));
        $rows    = $this->data_store->get_field_analytics($form_id);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="dfll-report-' . $form_id . '.csv"');

        $handle = fopen('php://output', 'w');
        fputcsv($handle, ['field_key', 'avg_time_spent_ms', 'skipped_count', 'appearances', 'abandonment_rate']);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
        exit;
    }
}
