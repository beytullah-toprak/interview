/* Sipariş oluşturma - "Satın Al" butonuna basılınca çalışır */

function fireAlert(options) {
    const L = window.LANG || {};
    return Swal.fire(Object.assign({ confirmButtonText: L.ok || 'OK' }, options));
}

function addProducts(productId, gameId, button) {
    const L = window.LANG || {};
    const quantityInput = document.getElementById('quantity_' + productId);
    const quantity = quantityInput ? parseInt(quantityInput.value, 10) : 0;
    const min = quantityInput && quantityInput.min !== '' ? parseInt(quantityInput.min, 10) : NaN;
    const max = quantityInput && quantityInput.max !== '' ? parseInt(quantityInput.max, 10) : NaN;

    if (!quantity || quantity < 1) {
        fireAlert({ icon: 'warning', title: L.warning, text: L.invalid_quantity });
        return;
    }

    if (!isNaN(min) && quantity < min) {
        fireAlert({ icon: 'warning', title: L.warning, text: (L.quantity_too_low || '').replace(':min', min) });
        return;
    }

    if (!isNaN(max) && max > 0 && quantity > max) {
        fireAlert({ icon: 'warning', title: L.warning, text: (L.quantity_too_high || '').replace(':max', max) });
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
                fireAlert({
                    icon: 'success',
                    title: L.order_success,
                    html: (L.order_no || '') + ': <b>' + result.order.order_no + '</b><br>' + (L.total || '') + ': ' + result.order.total
                });
            } else {
                fireAlert({
                    icon: 'error',
                    title: L.order_failed,
                    text: result.error_message || result.message || L.unknown_error
                });
            }
        })
        .catch(() => {
            fireAlert({ icon: 'error', title: L.error, text: L.network_error });
        })
        .finally(() => {
            if (button) {
                button.disabled = false;
            }
        });
}

document.addEventListener('DOMContentLoaded', function () {
    const errorBox = document.getElementById('page-error');
    if (errorBox && errorBox.dataset.message) {
        fireAlert({ icon: 'error', title: (window.LANG || {}).error, text: errorBox.dataset.message });
    }

    // Oyun değişimi sayfayı yeniden yüklüyor; kullanıcıya bekleme geri bildirimi ver.
    // setTimeout: disabled bir select form verisine dahil edilmez, bu yüzden
    // devre dışı bırakmayı form serialize edildikten sonraki tick'e erteliyoruz.
    const gameSelect = document.getElementById('games');
    if (gameSelect) {
        gameSelect.form.addEventListener('submit', function () {
            setTimeout(function () {
                gameSelect.disabled = true;
            }, 0);
        });
    }
});
