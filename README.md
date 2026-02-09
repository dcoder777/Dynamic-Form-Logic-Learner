# Dynamic Form Logic Learner (WordPress Plugin)

Dynamic Form Logic Learner extends WordPress form builders with analytics-backed logic recommendations and adaptive rule workflows.

## Features

- Dashboard analytics panel:
  - Field abandonment rate
  - Average time spent per field
  - Most skipped questions
- Logic suggestion engine (heuristic ML/statistical correlation):
  - Suggests conditional show/hide rules from historic submissions
  - Confidence/support/lift scoring
- Integrations:
  - WPForms (`wpforms_process_complete`)
  - Gravity Forms (`gform_after_submission`)
  - Fluent Forms (`fluentform_submission_inserted`)
  - Generic REST capture endpoint for Gutenberg/custom forms
- Rule governance:
  - Accept/reject suggested rules
  - Rule status updates via REST
- Multi-step flow builder UI:
  - Visual flowchart-style editor scaffold (JS admin component)
- A/B testing support:
  - Variation ID capture per submission (`dfll_variation`)
- Export:
  - CSV export of analytics reports

## Code Structure

```text
Dynamic-Form-Logic-Learner/
├── dynamic-form-logic-learner/
│   ├── dynamic-form-logic-learner.php      # Plugin bootstrap/main file
│   ├── includes/
│   │   ├── class-installer.php             # Activation/deactivation and custom table creation
│   │   ├── class-data-store.php            # Database access and persistence
│   │   ├── class-form-handler.php          # Submission recording and field metric ingestion
│   │   ├── class-analytics-engine.php      # Heuristic suggestion engine + dashboard data
│   │   ├── class-rest-controller.php       # REST API endpoints (analytics/suggestions/rule status)
│   │   ├── class-integration.php           # Hooks for form builder integrations + generic capture endpoint
│   │   └── class-admin-ui.php              # WP admin dashboard rendering + export action
│   ├── templates/
│   │   └── admin-dashboard.php             # Dashboard + suggestion controls + flow editor mount
│   └── assets/
│       ├── css/admin.css                   # Admin styles
│       └── js/flow-editor.js               # Flowchart component and suggestion action behavior
└── README.md
```

## Installation

1. Copy the `dynamic-form-logic-learner` directory into `wp-content/plugins/`.
2. Activate **Dynamic Form Logic Learner** from WordPress Admin → Plugins.
3. On activation, tables are created:
   - `{prefix}dfll_submissions`
   - `{prefix}dfll_field_metrics`
   - `{prefix}dfll_logic_rules`
   - `{prefix}dfll_ab_tests`
4. Open **Logic Learner** in WP Admin.

## Activation / Deactivation Hooks

- `register_activation_hook` triggers `DFLL_Installer::activate()` to create/upgrade tables.
- `register_deactivation_hook` keeps data for continuity.

## REST API Endpoints

- `GET /wp-json/dfll/v1/suggestions/{form_id}`
- `GET /wp-json/dfll/v1/analytics/{form_id}`
- `POST /wp-json/dfll/v1/rules/{rule_id}/status`
- `POST /wp-json/dfll/v1/capture` (generic submission ingestion)

## Example: Record Submission and Derive Suggestion

### 1) Record submission (generic endpoint)

```bash
curl -X POST https://example.com/wp-json/dfll/v1/capture \
  -H 'Content-Type: application/json' \
  -d '{
    "token": "YOUR_CAPTURE_TOKEN",
    "form_id": "lead_form_v1",
    "variation_id": "A",
    "session_id": "sess-123",
    "payload": {
      "employment_status": "self_employed",
      "company_size": "",
      "annual_revenue": "250000"
    },
    "field_metrics": [
      {"field_key":"employment_status","field_type":"select","time_spent_ms":2100,"skipped":false,"step_index":0},
      {"field_key":"company_size","field_type":"select","time_spent_ms":300,"skipped":true,"step_index":1},
      {"field_key":"annual_revenue","field_type":"number","time_spent_ms":1500,"skipped":false,"step_index":1}
    ]
  }'
```

### 2) Request suggestions

```bash
curl https://example.com/wp-json/dfll/v1/suggestions/lead_form_v1
```

Possible generated recommendation:

- “Show `annual_revenue` only when `employment_status = self_employed` (confidence: 84%).”

## Security & Capability Checks

- Admin dashboard access restricted to users with `edit_posts` (or stronger).
- REST analytics/suggestion endpoints require authenticated users with form-management capabilities.
- Generic capture endpoint validates a shared token (`dfll_capture_token` option).
- Inputs sanitized via WordPress sanitizers before persistence.
