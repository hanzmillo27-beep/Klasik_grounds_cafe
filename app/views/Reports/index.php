<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title">Daily Sales Report</h3>
    </div>
    <div class="card-body">
        <form id="sales-report-form" class="form-inline mb-3">
            <label for="report-date" class="mr-2">Sales date</label>
            <input type="date" id="report-date" name="date" class="form-control mr-sm-2 mb-2 mb-sm-0" min="1000-01-01" max="9998-12-31"
                value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>" required>
            <button type="submit" id="generate-sales-report" class="btn btn-primary">Generate report</button>
        </form>
        <p id="report-message" class="text-muted" role="status" aria-live="polite"></p>
        <div id="report-error" class="alert alert-danger" role="alert" hidden></div>
        <section id="sales-report-results" hidden>
            <h4 id="report-label" class="h5"></h4>
            <p><span id="report-count"></span> &middot; Total sales: <strong id="report-total"></strong></p>
            <div class="table-responsive">
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
        </section>
    </div>
</div>
