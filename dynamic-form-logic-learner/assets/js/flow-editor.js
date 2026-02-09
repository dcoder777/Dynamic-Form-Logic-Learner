(() => {
  const root = document.getElementById('dfll-flow-editor');
  if (!root) return;

  const formId = root.dataset.formId;
  const nodes = [
    { id: 'start', label: 'Start' },
    { id: 'step1', label: 'Step 1' },
    { id: 'step2', label: 'Step 2 (Conditional)' },
    { id: 'submit', label: 'Submit' },
  ];

  root.innerHTML = `
    <div class="dfll-flowchart">
      ${nodes.map((node) => `<div class="dfll-node" draggable="true" data-id="${node.id}">${node.label}</div>`).join('<div class="dfll-arrow">→</div>')}
    </div>
    <p class="description">Use API endpoint <code>/wp-json/dfll/v1/suggestions/${formId}</code> for generated logic recommendations.</p>
  `;

  document.addEventListener('click', async (event) => {
    const button = event.target.closest('.dfll-accept, .dfll-reject');
    if (!button) return;

    const rule = JSON.parse(button.dataset.rule || '{}');
    const status = button.classList.contains('dfll-accept') ? 'accepted' : 'rejected';

    try {
      await fetch(`${dfllApp.apiRoot}/rules/${rule.id || 0}/status`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': dfllApp.nonce,
        },
        body: JSON.stringify({ status, rule: { ...rule, form_id: formId } }),
      });
      button.textContent = status === 'accepted' ? 'Accepted' : 'Rejected';
      button.disabled = true;
    } catch (error) {
      // Keep minimal UI fallback in admin.
      console.error('DFLL rule update failed', error);
    }
  });
})();
