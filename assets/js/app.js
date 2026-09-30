/*
 * Norbooz Crochet - progressive enhancement.
 * Every feature here is optional: the site works without JavaScript, and the server
 * always re-validates prices, stock and input.
 */
document.addEventListener('DOMContentLoaded', () => {
  const money = (cents) => '$' + (cents / 100).toFixed(2);

  // Mobile navigation toggle.
  const toggle = document.querySelector('.menu-toggle');
  const menu = document.getElementById('site-menu');
  if (toggle && menu) {
    const setOpen = (open) => {
      toggle.setAttribute('aria-expanded', String(open));
      menu.classList.toggle('is-open', open);
    };
    toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
    // Escape closes the menu and returns focus to the button (keyboard and switch users).
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
        setOpen(false);
        toggle.focus();
      }
    });
    // Reset when the window grows back to the desktop layout (rotation, zoom out).
    const desktop = window.matchMedia('(min-width: 1121px)');
    const reset = () => { if (desktop.matches) setOpen(false); };
    if (desktop.addEventListener) desktop.addEventListener('change', reset); else desktop.addListener(reset);
  }

  // Label each table cell with its column heading so tables can stack into cards on phones.
  document.querySelectorAll('.table-wrap table').forEach((table) => {
    const heads = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
    if (!heads.length) return;
    table.querySelectorAll('tbody tr').forEach((row) => {
      [...row.children].forEach((cell, i) => cell.setAttribute('data-label', heads[i] || ''));
    });
    table.classList.add('stack-table');
  });

  // Ask before destructive actions (cancel order, delete product/account).
  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm)) {
        event.preventDefault();
      }
    });
  });

  // Prevent double submission of important forms.
  document.querySelectorAll('button[data-once]').forEach((button) => {
    const form = button.form;
    if (!form) return;
    form.addEventListener('submit', () => {
      window.setTimeout(() => {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.textContent = 'Please wait...';
      }, 0);
    });
  });

  // Cart: live line totals and subtotal while quantities change.
  const cartForm = document.getElementById('cart-form');
  const subtotalEl = document.getElementById('cart-subtotal');
  if (cartForm && subtotalEl) {
    const update = () => {
      let subtotal = 0;
      cartForm.querySelectorAll('.cart-row').forEach((row) => {
        const unit = parseInt(row.dataset.unit || '0', 10);
        const input = row.querySelector('.quantity-input');
        const max = parseInt(input.max || '99', 10);
        let qty = Math.max(0, parseInt(input.value || '0', 10) || 0);
        if (qty > max) qty = max;
        const line = unit * qty;
        subtotal += line;
        const lineEl = row.querySelector('.line-total');
        if (lineEl) lineEl.textContent = money(line);
      });
      subtotalEl.textContent = money(subtotal);
    };
    cartForm.addEventListener('input', update);
  }

  // Checkout: update delivery fee and total when the delivery method changes.
  const checkout = document.getElementById('checkout-form');
  if (checkout) {
    const subtotal = parseInt(checkout.dataset.subtotal || '0', 10);
    const feeEl = document.getElementById('delivery-fee');
    const totalEl = document.getElementById('order-total');
    const address = document.getElementById('address');
    const refresh = () => {
      const chosen = checkout.querySelector('input[name="delivery_method"]:checked');
      const fee = chosen ? parseInt(chosen.dataset.fee || '0', 10) : 0;
      if (feeEl) feeEl.textContent = money(fee);
      if (totalEl) totalEl.textContent = money(subtotal + fee);
      if (address && chosen) address.required = chosen.value === 'post';
    };
    checkout.addEventListener('change', refresh);
    refresh();
  }

  // Admin: preview a product photo before uploading.
  document.querySelectorAll('input[type="file"][data-preview]').forEach((input) => {
    const preview = document.getElementById(input.dataset.preview);
    input.addEventListener('change', () => {
      const file = input.files && input.files[0];
      if (preview && file && file.type.startsWith('image/')) {
        preview.src = URL.createObjectURL(file);
      }
    });
  });
});
