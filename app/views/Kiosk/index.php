<div class="container-fluid py-4">
    <div class="text-center mb-4">
        <h1 class="font-weight-bold">Klasik Grounds Café</h1>
        <p class="text-muted">Order here and pay at the cashier.</p>
        <?php if (!empty($isLoyalCustomer)): ?>
            <p class="text-success">You are signed in. A point is added after checkout, up to one point per day.</p>
        <?php elseif (!empty($username)): ?>
            <p class="text-muted">You can order with this account. A Loyal Customer account is only needed to earn loyalty points.</p>
        <?php else: ?>
            <p class="text-muted">You can browse and order as a guest. <a href="<?= BASE_URL ?>/Auth">Sign in</a> with a Loyal Customer account if you want to earn points.</p>
        <?php endif; ?>
    </div>

    <div id="kiosk-message"></div>

    <div id="kiosk-confirmation" class="d-none text-center py-5">
        <div class="card mx-auto" style="max-width: 600px;">
            <div class="card-body p-5">
                <h2 class="font-weight-bold">Order Submitted</h2>
                <p class="text-muted">Show this reference number to the cashier.</p>
                <div id="kiosk-reference"
                     class="display-4 font-weight-bold text-primary my-4"></div>
                <h4 id="kiosk-confirmation-total"></h4>
                <p class="mt-3">Your order is not paid yet.</p>
                <button type="button" id="kiosk-new-order"
                        class="btn btn-primary btn-lg mt-3">
                    Start New Order
                </button>
            </div>
        </div>
    </div>

    <div id="kiosk-ordering" class="row">
        <div class="col-lg-8">
            <input type="text" id="kiosk-search"
                   class="form-control form-control-lg mb-3"
                   placeholder="Search menu...">

            <div class="row" id="kiosk-products">
                <div class="col-12 text-center py-5">Loading menu...</div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">Your Order</h3>
                </div>

                <div class="card-body">
                    <label for="kiosk-customer-name">Name (optional)</label>
                    <input type="text" id="kiosk-customer-name"
                           class="form-control mb-3"
                           maxlength="150"
                           value="<?= htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8') ?>">

                    <div id="kiosk-cart"></div>

                    <hr>

                    <div class="d-flex justify-content-between">
                        <strong>Total</strong>
                        <strong id="kiosk-total">₱0.00</strong>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="button" id="kiosk-clear"
                            class="btn btn-outline-danger">
                        Clear
                    </button>

                    <button type="button" id="kiosk-submit"
                            class="btn btn-success float-right">
                        Submit Order
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
