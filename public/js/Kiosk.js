$(document).ready(function () {
    const productContainer = $('#kiosk-products');
    const cartContainer = $('#kiosk-cart');

    let products = [];
    let cart = [];
    let submitting = false;

    function money(value) {
        return '₱' + Number(value || 0).toFixed(2);
    }

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    function showMessage(type, message) {
        $('#kiosk-message').html(`
            <div class="alert alert-${type} alert-dismissible fade show">
                ${escapeHtml(message)}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        `);
    }

    function loadProducts() {
        $.ajax({
            url: `${base_url}/api/Kiosk/products`,
            type: 'GET',
            dataType: 'json'
        }).done(function (response) {
            products = response.data || [];
            renderProducts(products);
        }).fail(function () {
            productContainer.html(`
                <div class="col-12 text-danger text-center">
                    Unable to load the menu. Please try again.
                </div>
            `);
        });
    }

    function renderProducts(items) {
        if (!items.length) {
            productContainer.html(`
                <div class="col-12 text-muted text-center">
                    No products available.
                </div>
            `);
            return;
        }

        productContainer.html(items.map(function (product) {
            return `
                <div class="col-md-6 col-xl-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="font-weight-bold">
                                ${escapeHtml(product.product_name)}
                            </h5>
                            <div class="text-muted mb-2">
                                ${escapeHtml(product.category_name || '')}
                            </div>
                            <div class="h5 text-primary">
                                ${money(product.product_price)}
                            </div>
                            <button type="button"
                                class="btn btn-primary btn-lg btn-block kiosk-add"
                                data-id="${product.product_id}">
                                Add to Order
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join(''));
    }

    function renderCart() {
        if (!cart.length) {
            cartContainer.html(`
                <div class="text-center text-muted py-4">
                    Your cart is empty.
                </div>
            `);
        } else {
            cartContainer.html(cart.map(function (item) {
                return `
                    <div class="border rounded p-3 mb-2">
                        <div class="font-weight-bold">
                            ${escapeHtml(item.product_name)}
                        </div>
                        <div>${money(item.product_price)} each</div>
                        <div class="d-flex align-items-center mt-2">
                            <button type="button"
                                class="btn btn-outline-secondary kiosk-minus"
                                data-id="${item.product_id}">−</button>
                            <span class="mx-3">${item.quantity}</span>
                            <button type="button"
                                class="btn btn-outline-secondary kiosk-plus"
                                data-id="${item.product_id}">+</button>
                            <button type="button"
                                class="btn btn-outline-danger ml-auto kiosk-remove"
                                data-id="${item.product_id}">
                                Remove
                            </button>
                        </div>
                        <div class="text-right font-weight-bold mt-2">
                            ${money(item.product_price * item.quantity)}
                        </div>
                    </div>
                `;
            }).join(''));
        }

        const total = cart.reduce(function (sum, item) {
            return sum + Number(item.product_price) * item.quantity;
        }, 0);

        $('#kiosk-total').text(money(total));
        $('#kiosk-submit').prop('disabled', !cart.length || submitting);
    }

    function addProduct(id) {
        const product = products.find(function (item) {
            return Number(item.product_id) === Number(id);
        });

        if (!product) return;

        const existing = cart.find(function (item) {
            return Number(item.product_id) === Number(id);
        });

        if (existing) {
            if (existing.quantity >= 99) return;
            existing.quantity++;
        } else {
            cart.push({
                product_id: Number(product.product_id),
                product_name: product.product_name,
                product_price: Number(product.product_price),
                quantity: 1
            });
        }

        renderCart();
    }

    productContainer.on('click', '.kiosk-add', function () {
        addProduct($(this).data('id'));
    });

    cartContainer.on('click', '.kiosk-plus', function () {
        const item = cart.find(x =>
            Number(x.product_id) === Number($(this).data('id'))
        );

        if (item && item.quantity < 99) item.quantity++;
        renderCart();
    });

    cartContainer.on('click', '.kiosk-minus', function () {
        const id = Number($(this).data('id'));
        const item = cart.find(x => Number(x.product_id) === id);

        if (!item) return;

        item.quantity--;

        if (item.quantity <= 0) {
            cart = cart.filter(x => Number(x.product_id) !== id);
        }

        renderCart();
    });

    cartContainer.on('click', '.kiosk-remove', function () {
        const id = Number($(this).data('id'));
        cart = cart.filter(x => Number(x.product_id) !== id);
        renderCart();
    });

    $('#kiosk-clear').on('click', function () {
        cart = [];
        renderCart();
        $('#kiosk-message').empty();
    });

    $('#kiosk-search').on('input', function () {
        const search = String($(this).val() || '').toLowerCase().trim();

        renderProducts(products.filter(function (product) {
            return String(product.product_name).toLowerCase().includes(search);
        }));
    });

    $('#kiosk-submit').on('click', function () {
        if (submitting || !cart.length) return;

        submitting = true;
        renderCart();

        const formData = new FormData();

        formData.append('customer_name', $('#kiosk-customer-name').val().trim());
        formData.append('items', JSON.stringify(cart.map(function (item) {
            return {
                product_id: item.product_id,
                quantity: item.quantity
            };
        })));

        $.ajax({
            url: `${base_url}/api/Kiosk/submit`,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            if (response.status !== 'success') {
                showMessage('danger', response.message || 'Unable to submit order.');
                return;
            }

            $('#kiosk-reference').text(response.reference_code);
            $('#kiosk-confirmation-total').text(money(response.total_amount));
            $('#kiosk-ordering').addClass('d-none');
            $('#kiosk-confirmation').removeClass('d-none');

            cart = [];
            $('#kiosk-customer-name').val('');
            renderCart();
        }).fail(function (xhr) {
            const message = xhr.responseJSON?.message ||
                'Unable to submit your order. Please try again.';
            showMessage('danger', message);
        }).always(function () {
            submitting = false;
            renderCart();
        });
    });

    $('#kiosk-new-order').on('click', function () {
        $('#kiosk-confirmation').addClass('d-none');
        $('#kiosk-ordering').removeClass('d-none');
        $('#kiosk-reference').empty();
        $('#kiosk-confirmation-total').empty();
        $('#kiosk-message').empty();
    });

    loadProducts();
    renderCart();
});