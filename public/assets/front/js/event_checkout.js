"use strict";

(function () {
  const root = document.querySelector('[data-checkout-flow]');
  const form = document.getElementById('payment-form');
  if (!root || !form) return;

  const config = window.eventCheckoutConfig || {};
  const panels = Array.from(root.querySelectorAll('[data-checkout-panel]'));
  const steps = Array.from(root.querySelectorAll('[data-step-target]'));
  const lines = Array.from(root.querySelectorAll('.checkout-step-line'));
  const detailFields = ['fname', 'lname', 'email', 'phone']
    .map((name) => form.elements.namedItem(name))
    .filter(Boolean);
  let currentStep = Number(root.dataset.initialStep || 1);
  let maxVisited = Math.max(1, currentStep);
  let stripe = null;
  let stripeCard = null;
  let stripeSubmitting = false;

  root.classList.add('checkout-enhanced');

  function track(name, properties) {
    if (typeof window.ticaTrack === 'function') {
      window.ticaTrack(name, Object.assign({ event_id: Number(config.eventId || 0) }, properties || {}));
    }
  }

  function renderStep(step, options) {
    const settings = Object.assign({ focus: true, track: true }, options || {});
    currentStep = Math.min(3, Math.max(1, Number(step)));
    maxVisited = Math.max(maxVisited, currentStep);

    panels.forEach((panel) => {
      const active = Number(panel.dataset.checkoutPanel) === currentStep;
      panel.hidden = !active;
      panel.setAttribute('aria-hidden', active ? 'false' : 'true');
    });

    steps.forEach((button) => {
      const buttonStep = Number(button.dataset.stepTarget);
      button.classList.toggle('is-active', buttonStep === currentStep);
      button.classList.toggle('is-complete', buttonStep < currentStep);
      button.disabled = buttonStep > maxVisited;
      if (buttonStep === currentStep) button.setAttribute('aria-current', 'step');
      else button.removeAttribute('aria-current');
    });

    lines.forEach((line, index) => line.classList.toggle('is-complete', index + 1 < currentStep));

    if (currentStep === 3) updateReview();
    if (settings.track) track(currentStep === 3 ? 'checkout_review' : 'checkout_step_view', { step: currentStep });

    if (settings.focus) {
      const heading = panels.find((panel) => !panel.hidden)?.querySelector('h2');
      if (heading) {
        heading.setAttribute('tabindex', '-1');
        heading.focus({ preventScroll: true });
      }
      root.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    }
  }

  function validateDetails() {
    for (const field of detailFields) {
      if (!field.checkValidity()) {
        renderStep(2, { focus: false });
        field.reportValidity();
        field.focus();
        return false;
      }
    }
    return true;
  }

  function updateReview() {
    const value = (name) => String(form.elements.namedItem(name)?.value || '').trim();
    const nameOutput = root.querySelector('[data-review-name]');
    const contactOutput = root.querySelector('[data-review-contact]');
    if (nameOutput) nameOutput.textContent = `${value('fname')} ${value('lname')}`.trim();
    if (contactOutput) contactOutput.textContent = [value('email'), value('phone')].filter(Boolean).join(' · ');
  }

  root.addEventListener('click', (event) => {
    const next = event.target.closest('[data-checkout-next]');
    const back = event.target.closest('[data-checkout-back]');
    const direct = event.target.closest('[data-step-target]');

    if (next) {
      const target = Number(next.dataset.checkoutNext);
      if (target === 3 && !validateDetails()) return;
      renderStep(target);
    } else if (back) {
      renderStep(Number(back.dataset.checkoutBack));
    } else if (direct && !direct.disabled) {
      const target = Number(direct.dataset.stepTarget);
      if (target === 3 && !validateDetails()) return;
      renderStep(target);
    }
  });

  function showGatewayDetails() {
    const gateway = form.elements.namedItem('gateway');
    if (!gateway) return;
    const value = String(gateway.value || '');
    root.querySelectorAll('.offline-gateway-info').forEach((element) => element.classList.add('d-none'));
    const stripeElement = document.getElementById('stripe-element');
    if (stripeElement) stripeElement.classList.toggle('d-none', value !== 'stripe');
    if (/^\d+$/.test(value)) document.getElementById(`offline-gateway-${value}`)?.classList.remove('d-none');
  }

  const gateway = form.elements.namedItem('gateway');
  if (gateway) {
    gateway.addEventListener('change', showGatewayDetails);
    showGatewayDetails();
  }

  if (config.stripeKey && window.Stripe && document.getElementById('stripe-element')) {
    stripe = window.Stripe(config.stripeKey);
    const elements = stripe.elements();
    stripeCard = elements.create('card', {
      style: { base: { color: '#101828', fontWeight: '500', lineHeight: '52px', '::placeholder': { color: '#667085' } } }
    });
    stripeCard.mount('#stripe-element');
  }

  async function applyCoupon() {
    const input = document.getElementById('coupon-code');
    const status = root.querySelector('[data-coupon-status]');
    const code = String(input?.value || '').trim();
    if (!code) {
      if (status) status.textContent = 'Enter a discount code first.';
      input?.focus();
      return;
    }

    if (status) status.textContent = 'Checking code…';
    try {
      const response = await fetch(config.couponUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
          'Accept': 'application/json'
        },
        body: new URLSearchParams({ coupon_code: code })
      });
      const result = await response.json();
      if (status) status.textContent = result.message || (result.status === 'success' ? 'Code applied.' : 'That code could not be applied.');
      if (result.status === 'success') {
        input.value = '';
        const page = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const html = await page.text();
        const documentCopy = new DOMParser().parseFromString(html, 'text/html');
        const freshSummary = documentCopy.getElementById('couponReload');
        const currentSummary = document.getElementById('couponReload');
        if (freshSummary && currentSummary) currentSummary.innerHTML = freshSummary.innerHTML;
        track('checkout_coupon_applied');
      }
    } catch (error) {
      if (status) status.textContent = 'We could not check that code. Please try again.';
    }
  }

  root.querySelector('.base-btn')?.addEventListener('click', applyCoupon);
  document.getElementById('coupon-code')?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      applyCoupon();
    }
  });

  form.addEventListener('submit', async (event) => {
    if (stripeSubmitting) return;

    if (!validateDetails()) {
      event.preventDefault();
      return;
    }

    if (!form.checkValidity()) {
      event.preventDefault();
      renderStep(3, { focus: false });
      form.reportValidity();
      return;
    }

    const selectedGateway = String(form.elements.namedItem('gateway')?.value || '');
    track('checkout_submit', { gateway: selectedGateway || 'free' });

    if (selectedGateway === 'stripe' && stripe && stripeCard) {
      event.preventDefault();
      const result = await stripe.createToken(stripeCard);
      if (result.error) {
        const errors = document.getElementById('stripe-errors');
        if (errors) errors.textContent = result.error.message;
        return;
      }
      const token = document.createElement('input');
      token.type = 'hidden';
      token.name = 'stripeToken';
      token.value = result.token.id;
      form.appendChild(token);
      stripeSubmitting = true;
      form.submit();
    }
  });

  renderStep(currentStep, { focus: false, track: false });
  track('checkout_details', { step: currentStep });
  root.querySelector('[data-checkout-error-summary]')?.focus();
})();
