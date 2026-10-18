<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title">Sales Reports</h3>
    </div>
    <div class="card-body">
        <form id="sales-report-form" class="form-inline mb-3">
            <label for="report-type" class="mr-2">Report</label>
            <select id="report-type" class="form-control mr-sm-3 mb-2 mb-sm-0">
                <?php if (!($inventoryOnly ?? false)): ?>
                <option value="sales">Sales transactions</option><option value="products">Product sales</option>
                <?php endif; ?>
                <option value="inventory">Inventory</option>
            </select>
            <span id="report-period-controls" class="form-inline">
            <label for="report-period" class="mr-2">Period</label>
            <select id="report-period" class="form-control mr-sm-3 mb-2 mb-sm-0">
                <option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option>
            </select>
            <span id="report-date-field" class="form-inline">
                <label for="report-date" class="mr-2">Sales date</label>
                <input type="date" id="report-date" name="date" class="form-control mr-sm-2 mb-2 mb-sm-0" min="1000-01-01" max="9998-12-31"
                    value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>" required>
            </span>
            <span id="report-week-field" class="form-inline" hidden>
                <label for="report-week" class="mr-2">Sales week</label>
                <input type="week" id="report-week" class="form-control mr-sm-2 mb-2 mb-sm-0"
                    value="<?= htmlspecialchars($week, ENT_QUOTES, 'UTF-8') ?>" disabled>
            </span>
            <span id="report-month-field" class="form-inline" hidden>
                <label for="report-month" class="mr-2">Sales month</label>
                <input type="month" id="report-month" class="form-control mr-sm-2 mb-2 mb-sm-0" min="1000-01" max="9998-12"
                    value="<?= htmlspecialchars($month, ENT_QUOTES, 'UTF-8') ?>" disabled>
            </span>
            </span>
            <button type="submit" id="generate-sales-report" class="btn btn-primary">Generate report</button>
        </form>
        <p id="report-message" class="text-muted" role="status" aria-live="polite"></p>
        <div id="report-error" class="alert alert-danger" role="alert" hidden></div>
        <section id="sales-report-results" hidden>
            <h4 id="report-label" class="h5"></h4>
            <p><span id="report-count"></span><span id="report-money-summary"> &middot; Total sales: <strong id="report-total"></strong></span>
                <span id="inventory-low-count" hidden></span></p>
            <div id="sales-report-wrapper" class="table-responsive">
                <table id="sales-report-table" class="table table-bordered table-striped w-100">
                    <thead><tr>
                        <th>Transaction #</th><th>Date and time</th><th>Customer</th><th>Cashier</th>
                        <th>Total (PHP)</th><th>Paid (PHP)</th><th>Change (PHP)</th>
                    </tr></thead>
                    <tbody></tbody>
                    <tfoot><tr>
                        <th colspan="4">Total</th><th id="report-footer-total"></th><th></th><th></th>
                    </tr></tfoot>
                </table>
            </div>
            <div id="product-report-wrapper" class="table-responsive" hidden>
                <table id="product-report-table" class="table table-bordered table-striped w-100">
                    <thead><tr><th>Product</th><th>Quantity sold</th><th>Sales (PHP)</th></tr></thead>
                    <tbody></tbody>
                    <tfoot><tr><th>Total</th><th id="product-footer-quantity"></th><th id="product-footer-total"></th></tr></tfoot>
                </table>
            </div>
            <div id="inventory-report-wrapper" class="table-responsive" hidden>
                <table id="inventory-report-table" class="table table-bordered table-striped w-100">
                    <thead><tr><th>Name</th><th>Current stock</th><th>Unit</th><th>Minimum stock</th><th>Status</th></tr></thead>
                    <tbody></tbody>
                    <tfoot><tr><th colspan="3">Snapshot</th><th id="inventory-footer-count"></th><th id="inventory-footer-low"></th></tr></tfoot>
                </table>
            </div>
        </section>
    </div>
</div>
