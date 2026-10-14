<div class="row">

    <div class="col-lg-8">

        <div class="card card-primary card-outline">

            <div class="card-header">

                <h3 class="card-title">
                    Point of Sale
                </h3>

            </div>

            <div class="card-body">

                <div class="form-group mb-3">

                    <input
                        type="text"
                        id="product-search"
                        class="form-control"
                        placeholder="Search product..."
                    >

                </div>

                <div
                    class="row"
                    id="product-list"
                ></div>

            </div>

        </div>

    </div>

    <div class="col-lg-4">

        <div class="card card-primary card-outline">

            <div class="card-header">

                <h3 class="card-title">
                    Current Sale
                </h3>

            </div>

            <div class="card-body">

                <div class="form-group mb-3">

                    <label for="customer_name">
                        Customer Name
                    </label>

                    <input
                        type="text"
                        id="customer_name"
                        class="form-control"
                        placeholder="Optional"
                    >

                </div>

                <div class="form-group mb-3">
    <label for="kiosk-reference-input">Kiosk Order Reference</label>

    <div class="input-group">
        <input
            type="text"
            id="kiosk-reference-input"
            class="form-control"
            placeholder="e.g. KG-7F29A1"
        >

        <div class="input-group-append">
            <button
                type="button"
                class="btn btn-primary"
                id="btn-load-kiosk-order"
            >
                Retrieve
            </button>
        </div>
    </div>
</div>

                <div
                    id="cart-list"
                    class="mb-3"
                ></div>

                <hr>

                <div class="d-flex justify-content-between">

                    <strong>
                        Total
                    </strong>

                    <strong id="cart-total">
                        ₱0.00
                    </strong>

                </div>

            </div>

            <div class="card-footer">

                <button
                    type="button"
                    class="btn btn-danger"
                    id="btnClear"
                >
                    <i class="fas fa-trash"></i>
                    Clear
                </button>

                <button
                    type="button"
                    class="btn btn-success float-right"
                    id="btnCheckout"
                >
                    <i class="fas fa-cash-register"></i>
                    Checkout
                </button>

            </div>

        </div>

    </div>

</div>

<div class="card mt-4">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-receipt me-2"></i>
                Transaction History
            </h5>

            <button
                type="button"
                class="btn btn-outline-primary btn-sm"
                id="btn-refresh-transactions"
            >
                <i class="bi bi-arrow-clockwise me-1"></i>
                Refresh
            </button>
        </div>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Transaction #</th>
                        <th>Customer</th>
                        <th>Cashier</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th class="text-end">Option</th>
                    </tr>
                </thead>

                <tbody id="transactions-table">
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            Loading transactions...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div
    class="modal fade"
    id="transactionDetailsModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    Transaction Details
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-dismiss="modal"
                    aria-label="Close"
                ></button>
            </div>

            <div class="modal-body" id="transaction-details">
                <div class="text-center text-muted py-4">
                    Loading...
                </div>
            </div>

        </div>
    </div>
</div>
