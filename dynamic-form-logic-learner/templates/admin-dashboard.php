<?php
/** @var string $form_id */
/** @var array $data */
?>
<div class="wrap dfll-dashboard">
    <h1><?php esc_html_e('Dynamic Form Logic Learner', 'dynamic-form-logic-learner'); ?></h1>

    <form method="get" class="dfll-form-picker">
        <input type="hidden" name="page" value="dfll-dashboard" />
        <label for="dfll-form-id"><?php esc_html_e('Form ID', 'dynamic-form-logic-learner'); ?></label>
        <input type="text" id="dfll-form-id" name="form_id" value="<?php echo esc_attr($form_id); ?>" />
        <button class="button button-primary"><?php esc_html_e('Load Analytics', 'dynamic-form-logic-learner'); ?></button>
    </form>

    <section class="dfll-panel-grid">
        <article class="dfll-card">
            <h2><?php esc_html_e('Field Abandonment', 'dynamic-form-logic-learner'); ?></h2>
            <table class="widefat striped">
                <thead><tr><th>Field</th><th>Abandonment %</th><th>Avg Time (ms)</th></tr></thead>
                <tbody>
                <?php foreach ($data['abandonment'] as $row) : ?>
                    <tr>
                        <td><?php echo esc_html($row['field_key']); ?></td>
                        <td><?php echo esc_html((string) $row['abandonment_rate']); ?></td>
                        <td><?php echo esc_html((string) round((float) $row['avg_time_spent_ms'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </article>

        <article class="dfll-card">
            <h2><?php esc_html_e('Suggested Logic Rules', 'dynamic-form-logic-learner'); ?></h2>
            <ul class="dfll-suggestion-list">
                <?php foreach ($data['suggested_changes'] as $suggestion) : ?>
                    <li>
                        <p><?php echo esc_html($suggestion['message'] ?? ''); ?></p>
                        <?php if (! empty($suggestion['source_field']) && ! empty($suggestion['target_field'])) : ?>
                            <button class="button dfll-accept" data-rule='<?php echo esc_attr(wp_json_encode($suggestion)); ?>'><?php esc_html_e('Accept', 'dynamic-form-logic-learner'); ?></button>
                            <button class="button dfll-reject" data-rule='<?php echo esc_attr(wp_json_encode($suggestion)); ?>'><?php esc_html_e('Reject', 'dynamic-form-logic-learner'); ?></button>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </article>
    </section>

    <section class="dfll-card">
        <h2><?php esc_html_e('Branching Logic Visual Editor', 'dynamic-form-logic-learner'); ?></h2>
        <p><?php esc_html_e('Drag nodes to prototype multi-step flow and branching rules.', 'dynamic-form-logic-learner'); ?></p>
        <div id="dfll-flow-editor" data-form-id="<?php echo esc_attr($form_id); ?>"></div>
    </section>

    <section class="dfll-card">
        <h2><?php esc_html_e('A/B Testing', 'dynamic-form-logic-learner'); ?></h2>
        <p><?php esc_html_e('Use variation parameters (?dfll_variation=A) to compare completion rates by variant.', 'dynamic-form-logic-learner'); ?></p>
    </section>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('dfll_export_report'); ?>
        <input type="hidden" name="action" value="dfll_export_report" />
        <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>" />
        <button type="submit" class="button button-secondary"><?php esc_html_e('Export Analytics CSV', 'dynamic-form-logic-learner'); ?></button>
    </form>
</div>
