document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('order-form');
  const total = document.getElementById('estimated-total');
  if (form && total) {
    const updateTotal = () => {
      let value = 0;
      form.querySelectorAll('.order-row').forEach(row => {
        const price = parseFloat(row.dataset.price || '0');
        const input = row.querySelector('.quantity-input');
        const qty = Math.max(0, parseInt(input?.value || '0', 10) || 0);
        value += price * qty;
      });
      total.textContent = '$' + value.toFixed(2);
    };
    form.querySelectorAll('.quantity-input').forEach(input => input.addEventListener('input', updateTotal));
    updateTotal();
  }
});
