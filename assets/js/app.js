function addProducts(productId, gameId) {
    const quantityInput = document.getElementById('quantity_' + productId);
    const quantity = quantityInput ? parseInt(quantityInput.value, 10) : 0;

    if (!quantity || quantity < 1) {
        alert('Lütfen geçerli bir adet girin.');
        return;
    }

    const formData = new FormData();
    formData.append('game_id', gameId);
    formData.append('product_id', productId);
    formData.append('quantity', quantity);

    fetch('/order', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('Sipariş Başarılı! Sipariş No: ' + result.order.order_no);
            } else {
                alert('Sipariş Başarısız: ' + (result.error_message || result.message));
            }
        })
        .catch(() => alert('Sunucuya ulaşılamadı.'));
}
