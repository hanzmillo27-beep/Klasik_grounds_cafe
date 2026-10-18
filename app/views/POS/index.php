<div class="row">

    <div class="col-12 col-lg-8 mb-4 mb-lg-0">

        <div class="card card-primary card-outline">

            <div class="card-header d-flex flex-wrap align-items-center">

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

    <div class="col-12 col-lg-4">

        <div class="card card-primary card-outline">

            <div class="card-header d-flex flex-wrap align-items-center">

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

            <div class="card-footer d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center">

                <button
                    type="button"
                    class="btn btn-danger mb-2 mb-sm-0"
                    id="btnClear"
                >
                    <i class="fas fa-trash"></i>
                    New order
                </button>

                <button
                    type="button"
                    class="btn btn-success ml-sm-auto"
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
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <h5 class="mb-0 font-weight-bold">
                <i class="bi bi-receipt mr-2"></i>
                Transaction History
            </h5>

            <button
                type="button"
                class="btn btn-outline-primary btn-sm"
                id="btn-refresh-transactions"
            >
                <i class="bi bi-arrow-clockwise mr-1"></i>
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
                        <th class="text-right">Option</th>
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
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title font-weight-bold">
                    Transaction Details
                </h5>

                <button
                    type="button"
                    class="close"
                    data-dismiss="modal"
                    aria-label="Close"
                ><span aria-hidden="true">&times;</span></button>
            </div>

            <div class="modal-body" id="transaction-details">
                <div class="text-center text-muted py-4">
                    Loading...
                </div>
            </div>

        </div>
    </div>
</div>
