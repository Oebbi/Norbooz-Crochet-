document.addEventListener('DOMContentLoaded', () => {
  // Order page: calculate an estimated total in the browser for convenience.
  // PHP recalculates the authoritative total before saving the order.
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
});
