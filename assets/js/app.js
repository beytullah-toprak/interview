/* Sipariş oluşturma - "Satın Al" butonuna basılınca çalışır */

function fireAlert(options) {
    const L = window.LANG || {};
    return Swal.fire(Object.assign({ confirmButtonText: L.ok || 'OK' }, options));
}

/**
 * Tutarları biçimlendirir.
 * @param {number} value - Tutar değeri
 * @returns {string} Biçimlenmiş tutar
 */
function formatAmount(value) {
    const L = window.LANG || {};
    const formatted = new Intl.NumberFormat(L.lang === 'en' ? 'en-US' : 'tr-TR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 4
    }).format(value);

    return formatted + ' ' + (L.currency || '');
}

/** Toplam fiyat güncelleme */
function updateLineTotal(quantityInput) {
    const target = document.getElementById(quantityInput.dataset.totalTarget);
    if (!target) {
        return;
    }

    const price = parseFloat(quantityInput.dataset.price);
    const quantity = parseInt(quantityInput.value, 10);

    // Adet 0 ise stokta yok tutar da 0 gösterilir; yalnızca alan tamamen
    // boş/geçersizken tire basılır.
    target.textContent = (isNaN(price) || isNaN(quantity) || quantity < 0)
        ? '-'
        : formatAmount(price * quantity);
}

/**
 * Baremli (kademeli) ürünlerde adet yerine girilen tutarı doğrular.
 * @returns {number|null} Geçerliyse tutar, geçersizse null (uyarı zaten gösterilir)
 */
function readAndValidateBarem(baremInput, L) {
    const value = parseFloat(baremInput.value);
    const min = parseFloat(baremInput.min);
    const max = parseFloat(baremInput.max);
    const step = parseFloat(baremInput.dataset.baremStep);

    if (isNaN(value)) {
        fireAlert({ icon: 'warning', title: L.warning, text: L.barem_required_error });
        return null;
    }

    if (value < min || value > max) {
        fireAlert({ icon: 'warning', title: L.warning, text: (L.barem_range_error || '').replace(':min', min).replace(':max', max) });
        return null;
    }

    const steps = (value - min) / step;
    if (Math.abs(steps - Math.round(steps)) > 0.0001) {
        fireAlert({ icon: 'warning', title: L.warning, text: (L.barem_step_error || '').replace(':step', step) });
        return null;
    }

    return value;
}

/**
 * Normal ürünlerde adet alanını doğrular.
 * @returns {number|null} Geçerliyse adet, geçersizse null (uyarı zaten gösterilir)
 */
function readAndValidateQuantity(quantityInput, L) {
    const quantity = quantityInput ? parseInt(quantityInput.value, 10) : 0;
    const min = quantityInput && quantityInput.min !== '' ? parseInt(quantityInput.min, 10) : NaN;
    const max = quantityInput && quantityInput.max !== '' ? parseInt(quantityInput.max, 10) : NaN;

    if (!quantity || quantity < 1) {
        fireAlert({ icon: 'warning', title: L.warning, text: L.invalid_quantity });
        return null;
    }

    if (!isNaN(min) && quantity < min) {
        fireAlert({ icon: 'warning', title: L.warning, text: (L.quantity_too_low || '').replace(':min', min) });
        return null;
    }

    if (!isNaN(max) && max > 0 && quantity > max) {
        fireAlert({ icon: 'warning', title: L.warning, text: (L.quantity_too_high || '').replace(':max', max) });
        return null;
    }

    return quantity;
}

/* Sipariş oluşturma işlemi */
function addProducts(productId, gameId, button) {
    const L = window.LANG || {};
    const quantityInput = document.getElementById('quantity_' + productId);
    const baremInput = document.getElementById('barem_' + productId);

    let quantity = 1;
    let barem = null;

    if (baremInput) {
        barem = readAndValidateBarem(baremInput, L);
        if (barem === null) {
            return;
        }
    } else {
        quantity = readAndValidateQuantity(quantityInput, L);
        if (quantity === null) {
            return;
        }
    }

    if (button) {
        button.disabled = true;
    }

    // İstek sürerken hiç modal göstermezsek, önceki modal kapanışıyla bu
    // modalın açılışı arasında SweetAlert2'nin sayfa üzerindeki scrollbar
    // kilidi (body overflow:hidden) kısa süreliğine kalkıyor ve footer
    // görünür şekilde yukarı kayıp sonuç gelince geri düzeliyordu. Kilidi
    // hiç bırakmamak için istek başlar başlamaz bir yükleniyor modalı açıp
    // sonucu geldiğinde fireAlert ile aynı modalın üzerine yazıyoruz.
    Swal.fire({
        title: L.loading || 'Loading...',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Çift gönderim engelleme: her sipariş öncesi tek kullanımlık bir token alınır.
    fetch('/order-token')
        .then(response => response.json())
        .then(tokenResult => {
            const formData = new FormData();
            formData.append('game_id', gameId);
            formData.append('product_id', productId);
            formData.append('quantity', quantity);
            if (barem !== null) {
                formData.append('barem', barem);
            }
            formData.append('order_token', tokenResult.token);

            return fetch('/order', { method: 'POST', body: formData });
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // Ön sipariş/barem ürünlerde sipariş hemen değil "Pending"
                // durumuyla oluşur; kullanıcıya bunu ayrıca belirtiyoruz.
                const pendingNote = result.order.pending
                    ? '<br><small class="text-muted">' + (L.order_pending || '') + '</small>'
                    : '';

                // Sipariş stoğu düşürüyor; "Tamam"a basınca sayfa yenilenip
                // güncel stok/ürün listesi tekrar API'den çekilsin.
                fireAlert({
                    icon: 'success',
                    title: L.order_success,
                    html: (L.order_no || '') + ': <b>' + result.order.order_no + '</b><br>'
                        + (L.total || '') + ': <b>' + formatAmount(result.order.total) + '</b>'
                        + pendingNote
                }).then(() => {
                    window.location.reload();
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

    // Oyun değişimi sayfayı yeniden yüklüyor; kullanıcıya bekleme geri bildirimi vert
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

    // Satır tutarları: sayfa açılışında bir kez hesapla, sonra adet
    // değiştikçe canlı güncelle.
    document.querySelectorAll('input[data-total-target]').forEach(function (input) {
        updateLineTotal(input);
        input.addEventListener('input', function () {
            updateLineTotal(input);
        });
    });
});
