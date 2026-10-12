document.addEventListener('DOMContentLoaded', () => {
  const ci = document.querySelector('[name="check_in"]');
  const co = document.querySelector('[name="check_out"]');
  const today = new Date();
  const localToday = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
  if (ci) {
    ci.min = localToday;
    ci.addEventListener('change', () => {
      if (co) { co.min = ci.value; if (co.value && co.value <= ci.value) co.value = ''; }
    });
  }

  const overlay = document.getElementById('confirmOverlay');
  const title = document.getElementById('confirmTitle');
  const message = document.getElementById('confirmMessage');
  const proceed = document.getElementById('confirmProceed');
  const cancel = document.getElementById('confirmCancel');
  const icon = document.getElementById('confirmIcon');
  let pendingAction = null;
  let lastFocused = null;

  function openConfirm({text, heading, target, form, submitter}) {
    if (!overlay) return;
    pendingAction = () => {
      if (form) form.submit();
      else if (target instanceof HTMLAnchorElement) window.location.href = target.href;
      else if (typeof target === 'function') target();
    };
    lastFocused = document.activeElement;
    title.textContent = heading || 'Confirm this action';
    message.textContent = text || 'Please review this action before continuing.';
    const destructive = /delete|remove|decline|cancel|approve|confirm|change|update|save/i.test(`${heading || ''} ${text || ''}`);
    icon.textContent = destructive ? '!' : '?';
    icon.classList.toggle('danger', destructive);
    proceed.textContent = /delete|remove|decline|cancel/i.test(`${heading || ''} ${text || ''}`) ? 'Yes, continue' : 'Confirm';
    proceed.classList.toggle('btn-danger', /delete|remove|decline|cancel/i.test(`${heading || ''} ${text || ''}`));
    proceed.classList.toggle('btn-primary', !/delete|remove|decline|cancel/i.test(`${heading || ''} ${text || ''}`));
    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
    proceed.focus();
  }
  function closeConfirm() {
    overlay?.classList.remove('open');
    overlay?.setAttribute('aria-hidden', 'true');
    pendingAction = null;
    if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
  }
  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
      if (form.dataset.confirmed === 'yes') { delete form.dataset.confirmed; return; }
      event.preventDefault();
      openConfirm({text: form.dataset.confirm, heading: form.dataset.confirmTitle, form});
      pendingAction = () => { form.dataset.confirmed = 'yes'; form.submit(); };
    });
  });
  document.querySelectorAll('[data-confirm]:not(form)').forEach(el => {
    el.addEventListener('click', event => {
      if (el instanceof HTMLButtonElement && el.type === 'submit') {
        const form = el.form;
        if (form && form.dataset.confirm) return;
      }
      event.preventDefault();
      openConfirm({text: el.dataset.confirm, heading: el.dataset.confirmTitle, target: el});
    });
  });
  proceed?.addEventListener('click', () => { const action = pendingAction; closeConfirm(); if (action) action(); });
  cancel?.addEventListener('click', closeConfirm);
  overlay?.addEventListener('click', event => { if (event.target === overlay) closeConfirm(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && overlay?.classList.contains('open')) closeConfirm(); });
});
