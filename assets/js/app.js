/* Sipariş oluşturma - "Satın Al" butonuna basılınca çalışır */

function addProducts(productId, gameId) {
    const quantityInput = document.getElementById('quantity_' + productId);
    const quantity = quantityInput ? parseInt(quantityInput.value, 10) : 0;

    if (!quantity || quantity < 1) {
        Swal.fire({ icon: 'warning', title: 'Uyarı', text: 'Lütfen geçerli bir adet girin.' });
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
                Swal.fire({
                    icon: 'success',
                    title: 'Sipariş Başarılı',
                    html: 'Sipariş No: <b>' + result.order.order_no + '</b><br>Tutar: ' + result.order.total
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Sipariş Başarısız',
                    text: result.error_message || result.message || 'Bir hata oluştu.'
                });
            }
        })
        .catch(() => {
            Swal.fire({ icon: 'error', title: 'Hata', text: 'Sunucuya ulaşılamadı.' });
        });
}