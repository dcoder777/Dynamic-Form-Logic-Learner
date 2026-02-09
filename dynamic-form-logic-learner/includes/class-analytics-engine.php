<?php

if (! defined('ABSPATH')) {
    exit;
}

class DFLL_Analytics_Engine
{
    private DFLL_Data_Store $data_store;

    public function __construct(DFLL_Data_Store $data_store)
    {
        $this->data_store = $data_store;
    }

    public function get_dashboard_data(string $form_id): array
    {
        $field_analytics = $this->data_store->get_field_analytics($form_id);
        $top_skips       = $this->data_store->get_top_skip_fields($form_id);

        return [
            'abandonment'       => $field_analytics,
            'avg_time_spent'    => array_sum(array_column($field_analytics, 'avg_time_spent_ms')),
            'most_skipped'      => $top_skips,
            'suggested_changes' => $this->generate_suggestions($form_id),
        ];
    }

    public function generate_suggestions(string $form_id): array
    {
        $submissions = $this->data_store->get_submissions($form_id, 400);
        if (count($submissions) < 20) {
            return [
                [
                    'type'       => 'data_warning',
                    'message'    => __('Collect at least 20 submissions before using adaptive suggestions.', 'dynamic-form-logic-learner'),
                    'confidence' => 0,
                ],
            ];
        }

        $payloads     = array_map(static fn(array $row): array => (array) json_decode($row['payload'] ?? '[]', true), $submissions);
        $all_fields   = $this->collect_fields($payloads);
        $suggestions  = [];

        foreach ($all_fields as $source_field) {
            foreach ($all_fields as $target_field) {
                if ($source_field === $target_field) {
                    continue;
                }

                $correlation = $this->conditional_correlation($payloads, $source_field, $target_field);

                if ($correlation['confidence'] >= 0.72) {
                    $suggestions[] = [
                        'type'         => 'conditional_logic',
                        'form_id'      => $form_id,
                        'source_field' => $source_field,
                        'operator'     => '=',
                        'source_value' => $correlation['source_value'],
                        'target_field' => $target_field,
                        'action'       => 'show',
                        'confidence'   => $correlation['confidence'],
                        'metadata'     => [
                            'support' => $correlation['support'],
                            'lift'    => $correlation['lift'],
                        ],
                        'message'      => sprintf(
                            __('Show %1$s only when %2$s is %3$s (confidence: %4$s%%).', 'dynamic-form-logic-learner'),
                            $target_field,
                            $source_field,
                            $correlation['source_value'],
                            round($correlation['confidence'] * 100)
                        ),
                    ];
                }
            }
        }

        return array_slice($suggestions, 0, 20);
    }

    private function collect_fields(array $payloads): array
    {
        $fields = [];
        foreach ($payloads as $payload) {
            foreach (array_keys($payload) as $field_key) {
                $fields[] = sanitize_key((string) $field_key);
            }
        }

        return array_values(array_unique(array_filter($fields)));
    }

    private function conditional_correlation(array $payloads, string $source_field, string $target_field): array
    {
        $value_counts      = [];
        $target_presence   = 0;
        $co_occurrence     = [];
        $total             = count($payloads);

        foreach ($payloads as $payload) {
            $source_value = $payload[$source_field] ?? null;
            $has_target   = ! empty($payload[$target_field]);

            if ($has_target) {
                $target_presence++;
            }

            if ($source_value !== null && $source_value !== '') {
                $source_key = (string) $source_value;
                $value_counts[$source_key] = ($value_counts[$source_key] ?? 0) + 1;

                if (! isset($co_occurrence[$source_key])) {
                    $co_occurrence[$source_key] = 0;
                }

                if ($has_target) {
                    $co_occurrence[$source_key]++;
                }
            }
        }

        if ($target_presence === 0 || empty($value_counts)) {
            return ['confidence' => 0, 'source_value' => '', 'support' => 0, 'lift' => 0];
        }

        $best_value      = '';
        $best_confidence = 0.0;
        $best_support    = 0;
        $best_lift       = 0.0;

        foreach ($value_counts as $value => $count) {
            $confidence = ($co_occurrence[$value] ?? 0) / max($count, 1);
            $support    = ($co_occurrence[$value] ?? 0) / max($total, 1);
            $baseline   = $target_presence / max($total, 1);
            $lift       = $baseline > 0 ? ($confidence / $baseline) : 0;

            if ($confidence > $best_confidence && $support >= 0.1 && $count >= 5) {
                $best_value      = $value;
                $best_confidence = $confidence;
                $best_support    = $support;
                $best_lift       = $lift;
            }
        }

        return [
            'confidence'   => $best_confidence,
            'source_value' => $best_value,
            'support'      => $best_support,
            'lift'         => $best_lift,
        ];
    }
}
