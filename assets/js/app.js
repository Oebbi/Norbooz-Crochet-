document.addEventListener('DOMContentLoaded', () => {
  // Order page: calculate an estimated total in the browser for convenience.
  // The PHP server recalculates the authoritative total before saving the order.
  const orderForm = document.getElementById('order-form');
  const total = document.getElementById('estimated-total');

  if (orderForm && total) {
    const updateTotal = () => {
      let value = 0;
      orderForm.querySelectorAll('.order-row').forEach((row) => {
        const price = Number.parseFloat(row.dataset.price || '0');
        const input = row.querySelector('.quantity-input');
        const quantity = Math.max(0, Number.parseInt(input?.value || '0', 10) || 0);
        value += price * quantity;
      });
      total.textContent = '$' + value.toFixed(2);
    };

    orderForm.querySelectorAll('.quantity-input').forEach((input) => {
      input.addEventListener('input', updateTotal);
    });
    updateTotal();
  }

  // Product page: simple client-side search. No customer data is transmitted.
  const search = document.getElementById('product-search');
  const grid = document.getElementById('product-grid');
  const noResults = document.getElementById('no-search-results');

  if (search && grid) {
    const cards = [...grid.querySelectorAll('.product-card')];
    const filterProducts = () => {
      const term = search.value.trim().toLowerCase();
      let visible = 0;

      cards.forEach((card) => {
        const match = !term || (card.dataset.search || '').includes(term);
        card.classList.toggle('hidden', !match);
        if (match) visible += 1;
      });

      if (noResults) noResults.classList.toggle('hidden', visible !== 0);
    };

    search.addEventListener('input', filterProducts);
  }
});
