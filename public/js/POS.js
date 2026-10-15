
$(document).ready(function () {

    const POSModule = (function () {
        const s = {
            productSearch: '#product-search',
            productList: '#product-list',
            cartList: '#cart-list',
            cartTotal: '#cart-total',
            customerName: '#customer_name',
            checkoutBtn: '#btnCheckout',
            clearBtn: '#btnClear',

            transactionsTable: '#transactions-table',
            refreshTransactionsBtn: '#btn-refresh-transactions',

            transactionModal: '#transactionDetailsModal',
            transactionDetails: '#transaction-details'
        };

        const storage = {
            cart: 'pos_cart',
            customerName: 'pos_customer_name',
            kioskOrderId: 'pos_kiosk_order_id'
        };

        let products = [];
        let cart = [];
        let kioskOrderId = null;

        function init() {
            loadSavedCart();
            loadSavedCustomerName();
            bindEvents();
            loadProducts();
            loadTransactions();
            renderCart();
        }

        function bindEvents() {
            $(s.productSearch).on('input', handleProductSearch);
            $(s.productList).on('click', '.btn-add-product', handleAddProduct);

            $(s.cartList).on('click', '.btn-increase', handleIncrease);
            $(s.cartList).on('click', '.btn-decrease', handleDecrease);
            $(s.cartList).on('click', '.btn-remove', handleRemove);

            $(s.clearBtn).on('click', handleClearCart);
            $(s.checkoutBtn).on('click', handleCheckout);

            $(s.customerName).on('input', saveCustomerName);

            $(s.transactionsTable).on(
                'click',
                '.btn-view-transaction',
                handleViewTransaction
            );

            $(s.refreshTransactionsBtn).on('click', loadTransactions);

            $('#btn-load-kiosk-order').on('click', handleLoadKioskOrder);
        }

        function loadSavedCart() {
            const savedKioskOrderId = localStorage.getItem(storage.kioskOrderId);
            kioskOrderId = savedKioskOrderId ? Number(savedKioskOrderId) : null;

            const savedCart = localStorage.getItem(storage.cart);

            if (!savedCart) {
                return;
            }

            try {
                const parsedCart = JSON.parse(savedCart);

                if (Array.isArray(parsedCart)) {
                    cart = parsedCart;
                }
            } catch (e) {
                localStorage.removeItem(storage.cart);
                cart = [];
            }
        }

        function saveCart() {
            localStorage.setItem(
                storage.cart,
                JSON.stringify(cart)
            );

            if (kioskOrderId !== null) {
                localStorage.setItem(storage.kioskOrderId, String(kioskOrderId));
            } else {
                localStorage.removeItem(storage.kioskOrderId);
            }
        }

        function loadSavedCustomerName() {
            const customerName = localStorage.getItem(
                storage.customerName
            );

            if (customerName !== null) {
                $(s.customerName).val(customerName);
            }
        }

        function saveCustomerName() {
    const customerInput = $(s.customerName).first();

    if (!customerInput.length) {
        return;
    }

    localStorage.setItem(
        storage.customerName,
        String(customerInput.val() ?? '')
    );
}

        function clearSavedCart() {
            localStorage.removeItem(storage.cart);
            localStorage.removeItem(storage.kioskOrderId);
        }

        function clearSavedCustomerName() {
            localStorage.removeItem(storage.customerName);
        }

        function loadProducts() {
            $.ajax({
                url: `${base_url}/api/POS/products`,
                type: 'GET',
                dataType: 'json'
            })
                .done((res) => {
                    products = res.data || [];
                    renderProducts(products);
                })
                .fail((xhr) => {
                    handleAjaxError(xhr);

                    $(s.productList).html(`
                        <div class="text-center text-danger py-4">
                            Unable to load products.
                        </div>
                    `);
                });
        }

        function renderProducts(items) {
            if (!items.length) {
                $(s.productList).html(`
                    <div class="text-center text-muted py-4">
                        No products found.
                    </div>
                `);

                return;
            }

            $(s.productList).html(
                items.map((product) => `
                    <div class="col-md-4 col-lg-3 mb-3">
                        <div class="card h-100 product-card">
                            ${product.product_image_url ? `<img src="${escapeHtml(product.product_image_url)}" alt="${escapeHtml(product.product_name)}" class="card-img-top" style="height:150px;object-fit:cover;">` : ''}
                            <div class="card-body">
                                <h6 class="fw-bold mb-1">
                                    ${escapeHtml(product.product_name)}
                                </h6>

                                <div class="text-muted small mb-2">
                                    ${escapeHtml(
                                        product.category_name || ''
                                    )}
                                </div>

                                <div class="fw-bold mb-3">
                                    ${formatMoney(product.product_price)}
                                </div>

                                <button
                                    type="button"
                                    class="btn btn-primary btn-sm w-100 btn-add-product"
                                    data-id="${product.product_id}"
                                >
                                    <i class="fa fa-cart-plus me-1"></i>
                                    Add
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('')
            );
        }

        function handleProductSearch() {
            const search = $(s.productSearch)
                .val()
                .toLowerCase()
                .trim();

            const filtered = products.filter((product) =>
                product.product_name
                    .toLowerCase()
                    .includes(search)
            );

            renderProducts(filtered);
        }

        function handleAddProduct(e) {
            e.preventDefault();

            const id = $(this).data('id');

            const product = products.find(
                (item) =>
                    Number(item.product_id) === Number(id)
            );

            if (!product) {
                return;
            }

            addToCart(product);
        }

        function addToCart(product) {
            const existing = cart.find(
                (item) =>
                    Number(item.product_id) ===
                    Number(product.product_id)
            );

            if (existing) {
                existing.quantity++;
            } else {
                cart.push({
                    product_id: Number(product.product_id),
                    product_name: product.product_name,
                    product_price: Number(product.product_price),
                    quantity: 1
                });
            }

            saveCart();
            renderCart();
        }

        function renderCart() {
            if (!cart.length) {
                $(s.cartList).html(`
                    <div class="text-center text-muted py-4">
                        Cart is empty.
                    </div>
                `);

                updateTotal();
                return;
            }

            $(s.cartList).html(
                cart.map((item) => `
                    <div class="border rounded p-3 mb-2">
                        <div class="d-flex justify-content-between gap-3">

                            <div>
                                <div class="fw-bold">
                                    ${escapeHtml(item.product_name)}
                                </div>

                                <div class="small text-muted">
                                    ${formatMoney(item.product_price)} each
                                </div>
                            </div>

                            <div class="text-end">
                                <div class="fw-bold">
                                    ${formatMoney(
                                        item.product_price *
                                        item.quantity
                                    )}
                                </div>

                                <div class="btn-group btn-group-sm mt-2">

                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary btn-decrease"
                                        data-id="${item.product_id}"
                                    >
                                        -
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary"
                                        disabled
                                    >
                                        ${item.quantity}
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary btn-increase"
                                        data-id="${item.product_id}"
                                    >
                                        +
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-outline-danger btn-remove"
                                        data-id="${item.product_id}"
                                    >
                                        <i class="fa fa-trash"></i>
                                    </button>

                                </div>
                            </div>

                        </div>
                    </div>
                `).join('')
            );

            updateTotal();
        }

        function updateTotal() {
            const total = cart.reduce(
                (sum, item) =>
                    sum +
                    Number(item.product_price) *
                    Number(item.quantity),
                0
            );

            $(s.cartTotal).text(formatMoney(total));
        }

        function handleIncrease(e) {
            e.preventDefault();

            changeQuantity(
                $(this).data('id'),
                1
            );
        }

        function handleDecrease(e) {
            e.preventDefault();

            changeQuantity(
                $(this).data('id'),
                -1
            );
        }

        function handleRemove(e) {
            e.preventDefault();

            const id = $(this).data('id');

            cart = cart.filter(
                (item) =>
                    Number(item.product_id) !== Number(id)
            );

            saveCart();
            renderCart();
        }

        function changeQuantity(id, amount) {
            const item = cart.find(
                (item) =>
                    Number(item.product_id) === Number(id)
            );

            if (!item) {
                return;
            }

            item.quantity += amount;

            if (item.quantity <= 0) {
                cart = cart.filter(
                    (item) =>
                        Number(item.product_id) !== Number(id)
                );
            }

            saveCart();
            renderCart();
        }

        function handleClearCart(e) {
            e.preventDefault();

            if (!cart.length) {
                return;
            }

            isDelete((confirmed) => {
                if (!confirmed) {
                    return;
                }

                cart = [];
                kioskOrderId = null;

                clearSavedCart();

                renderCart();
            });
        }

        function handleLoadKioskOrder(e) {
            e.preventDefault();

            const reference = String(
                $('#kiosk-reference-input').val() || ''
            ).trim().toUpperCase();

            if (!reference) {
                msgBox('error', 'Enter a kiosk order reference.');
                return;
            }

            if (cart.length > 0) {
                msgBox('error', 'Clear the current cart before retrieving a kiosk order.');
                return;
            }

            $.ajax({
                url: `${base_url}/api/Kiosk/claim/${encodeURIComponent(reference)}`,
                type: 'POST',
                dataType: 'json'
            }).done(function (response) {
                if (response.status !== 'success') {
                    msgBox('error', response.message || 'Unable to retrieve kiosk order.');
                    return;
                }

                const order = response.data;

                if (
                    !order ||
                    order.status !== 'claimed' ||
                    !Array.isArray(order.items) ||
                    !order.items.length
                ) {
                    msgBox('error', 'The kiosk order has no available items.');
                    return;
                }

                cart = order.items.map(function (item) {
                    return {
                        product_id: Number(item.product_id),
                        product_name: item.product_name,
                        product_price: Number(item.unit_price),
                        quantity: Number(item.quantity)
                    };
                });

                kioskOrderId = Number(order.kiosk_order_id);

                $(s.customerName).val(order.customer_name || '');
                $('#kiosk-reference-input').val(order.reference_code);

                saveCart();
                renderCart();

                msgBox('success', 'Kiosk order loaded. Verify it before checkout.');
            }).fail(function (xhr) {
                handleAjaxError(xhr);
            });
        }

        function handleCheckout(e) {
            e.preventDefault();

            console.log('Checkout button clicked');


            if (!cart.length) {
                msgBox('error', 'Cart is empty.');
                return;
            }

            const fd = new FormData();

            const customerInput = $(s.customerName).first();

                const customerName = customerInput.length
                    ? String(customerInput.val() ?? '').trim()
                    : '';

                fd.append('customer_name', customerName);

            fd.append(
                'items',
                JSON.stringify(
                    cart.map((item) => ({
                        product_id: item.product_id,
                        quantity: item.quantity
                    }))
                )
            );

            if (kioskOrderId !== null) {
            fd.append('kiosk_order_id', kioskOrderId);
}

            $(s.checkoutBtn).prop('disabled', true);

            $.ajax({
                url: `${base_url}/api/POS/checkout`,
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json'
            })
                .done((res) => {
                    const status = res.status || res.result;

                    msgBox(status, res.message);

                    if (status === 'success') {

                        cart = [];

                        kioskOrderId = null;
                        $('#kiosk-reference-input').val('');

                        clearSavedCart();
                        clearSavedCustomerName();

                        $(s.customerName).val('');

                        renderCart();

                        loadProducts();
                        loadTransactions();
                    }
                })
                .fail((xhr) => {
                    handleAjaxError(xhr);
                })
                .always(() => {
                    $(s.checkoutBtn).prop('disabled', false);
                });
        }

        function loadTransactions() {
            $(s.transactionsTable).html(`
                <tr>
                    <td
                        colspan="7"
                        class="text-center text-muted py-4"
                    >
                        Loading transactions...
                    </td>
                </tr>
            `);

            $.ajax({
                url: `${base_url}/api/POS/transactions`,
                type: 'GET',
                dataType: 'json'
            })
                .done((res) => {
                    renderTransactions(res.data || []);
                })
                .fail((xhr) => {
                    handleAjaxError(xhr);

                    $(s.transactionsTable).html(`
                        <tr>
                            <td
                                colspan="7"
                                class="text-center text-danger py-4"
                            >
                                Unable to load transactions.
                            </td>
                        </tr>
                    `);
                });
        }

        function renderTransactions(transactions) {
            if (!transactions.length) {
                $(s.transactionsTable).html(`
                    <tr>
                        <td
                            colspan="7"
                            class="text-center text-muted py-4"
                        >
                            No transactions found.
                        </td>
                    </tr>
                `);

                return;
            }

            $(s.transactionsTable).html(
                transactions.map((transaction) => `
                    <tr>

                        <td class="fw-bold">
                            #${transaction.sale_id}
                        </td>

                        <td>
                            ${escapeHtml(
                                transaction.customer_name ||
                                'Walk-in Customer'
                            )}
                        </td>

                        <td>
                            ${escapeHtml(
                                transaction.user_name ||
                                'Unknown'
                            )}
                        </td>

                        <td>
                            ${formatDate(transaction.sale_date)}
                        </td>

                        <td>
                            ${transaction.item_count || 0}
                        </td>

                        <td class="fw-bold">
                            ${formatMoney(transaction.total_amount)}
                        </td>

                        <td class="text-end">
                            <button
                                type="button"
                                class="btn btn-primary btn-sm btn-view-transaction"
                                data-id="${transaction.sale_id}"
                            >
                                <i class="fa fa-eye me-1"></i>
                                View Details
                            </button>
                        </td>

                    </tr>
                `).join('')
            );
        }

        function handleViewTransaction(e) {
            e.preventDefault();

            const id = $(this).data('id');

            viewTransaction(id);
        }

        function viewTransaction(id) {
            $(s.transactionDetails).html(`
                <div class="text-center text-muted py-4">
                    Loading transaction details...
                </div>
            `);

            $(s.transactionModal).modal('show');

            $.ajax({
                url: `${base_url}/api/POS/transaction/${id}`,
                type: 'GET',
                dataType: 'json'
            })
                .done((res) => {
                    const status = res.status || res.result;

                    if (status !== 'success') {
                        $(s.transactionDetails).html(`
                            <div class="alert alert-danger">
                                ${escapeHtml(
                                    res.message ||
                                    'Unable to load transaction.'
                                )}
                            </div>
                        `);

                        return;
                    }

                    renderTransactionDetails(res.data);
                })
                .fail((xhr) => {
                    handleAjaxError(xhr);

                    $(s.transactionDetails).html(`
                        <div class="alert alert-danger">
                            Unable to load transaction details.
                        </div>
                    `);
                });
        }

        function renderTransactionDetails(sale) {
            const items = sale.items || [];

            $(s.transactionDetails).html(`
                <div class="row mb-4">

                    <div class="col-md-6">
                        <div class="small text-muted">
                            Transaction #
                        </div>

                        <div class="fw-bold fs-5">
                            #${sale.sale_id}
                        </div>
                    </div>

                    <div class="col-md-6 text-md-end">
                        <div class="small text-muted">
                            Date
                        </div>

                        <div class="fw-bold">
                            ${formatDate(sale.sale_date)}
                        </div>
                    </div>

                    <div class="col-md-6 mt-3">
                        <div class="small text-muted">
                            Customer
                        </div>

                        <div class="fw-bold">
                            ${escapeHtml(
                                sale.customer_name ||
                                'Walk-in Customer'
                            )}
                        </div>
                    </div>

                    <div class="col-md-6 mt-3 text-md-end">
                        <div class="small text-muted">
                            Cashier
                        </div>

                        <div class="fw-bold">
                            ${escapeHtml(
                                sale.user_name ||
                                'Unknown'
                            )}
                        </div>
                    </div>

                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">

                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>

                        <tbody>
                            ${
                                items.length
                                    ? items.map((item) => `
                                        <tr>

                                            <td>
                                                ${escapeHtml(
                                                    item.product_name ||
                                                    'Deleted Product'
                                                )}
                                            </td>

                                            <td class="text-center">
                                                ${item.quantity}
                                            </td>

                                            <td class="text-end">
                                                ${formatMoney(
                                                    item.unit_price
                                                )}
                                            </td>

                                            <td class="text-end">
                                                ${formatMoney(
                                                    item.subtotal
                                                )}
                                            </td>

                                        </tr>
                                    `).join('')
                                    : `
                                        <tr>
                                            <td
                                                colspan="4"
                                                class="text-center text-muted"
                                            >
                                                No items found.
                                            </td>
                                        </tr>
                                    `
                            }
                        </tbody>

                        <tfoot>
                            <tr>
                                <th
                                    colspan="3"
                                    class="text-end"
                                >
                                    Grand Total
                                </th>

                                <th class="text-end fs-5">
                                    ${formatMoney(sale.total_amount)}
                                </th>
                            </tr>
                        </tfoot>

                    </table>
                </div>
            `);
        }

        function formatMoney(value) {
            return '₱' + Number(value || 0).toFixed(2);
        }

        function formatDate(value) {
            if (!value) {
                return '';
            }

            const date = new Date(
                String(value).replace(' ', 'T')
            );

            if (isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleString();
        }

        function escapeHtml(value) {
            return $('<div>').text(value ?? '').html();
        }

        return {
            init
        };

    })();

    POSModule.init();

});

