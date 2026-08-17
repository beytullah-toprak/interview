/* Sipariş oluşturma - "Satın Al" butonuna basılınca çalışır */

function addProducts(productId, gameId, button) {
    const L = window.LANG || {};
    const quantityInput = document.getElementById('quantity_' + productId);
    const quantity = quantityInput ? parseInt(quantityInput.value, 10) : 0;
    const min = quantityInput && quantityInput.min !== '' ? parseInt(quantityInput.min, 10) : NaN;
    const max = quantityInput && quantityInput.max !== '' ? parseInt(quantityInput.max, 10) : NaN;

    if (!quantity || quantity < 1) {
        Swal.fire({ icon: 'warning', title: L.warning, text: L.invalid_quantity });
        return;
    }

    if (!isNaN(min) && quantity < min) {
        Swal.fire({ icon: 'warning', title: L.warning, text: (L.quantity_too_low || '').replace(':min', min) });
        return;
    }

    if (!isNaN(max) && max > 0 && quantity > max) {
        Swal.fire({ icon: 'warning', title: L.warning, text: (L.quantity_too_high || '').replace(':max', max) });
        return;
    }

    if (button) {
        button.disabled = true;
    }

    // Çift gönderim engelleme: her sipariş öncesi tek kullanımlık bir token alınır.
    fetch('/order-token')
        .then(response => response.json())
        .then(tokenResult => {
            const formData = new FormData();
            formData.append('game_id', gameId);
            formData.append('product_id', productId);
            formData.append('quantity', quantity);
            formData.append('order_token', tokenResult.token);

            return fetch('/order', { method: 'POST', body: formData });
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: L.order_success,
                    html: (L.order_no || '') + ': <b>' + result.order.order_no + '</b><br>' + (L.total || '') + ': ' + result.order.total
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: L.order_failed,
                    text: result.error_message || result.message || L.unknown_error
                });
            }
        })
        .catch(() => {
            Swal.fire({ icon: 'error', title: L.error, text: L.network_error });
        })
        .finally(() => {
            if (button) {
                button.disabled = false;
            }
        });
}
