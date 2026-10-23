$(document).ready(function () {
    let request = null;
    let requestNumber = 0;

    function text(value) { return value == null ? '' : String(value); }
    function escapeHtml(value) { return $('<div>').text(text(value)).html(); }
    function decimal(value) { return Number(value || 0).toFixed(2); }
    function money(value) { return '\u20b1' + decimal(value); }
    function renderText(value, type) {
        return type === 'display' ? escapeHtml(value) : text(value);
    }
    function renderMoney(value, type) {
        if (value == null) return type === 'display' ? 'Not recorded' : '';
        return type === 'display' ? money(value) : decimal(value);
    }
    function renderQuantity(value, type) {
        return type === 'display' ? Number(value || 0).toLocaleString(undefined, {maximumFractionDigits: 3})
            : Number(value || 0).toFixed(3);
    }
    const table = $('#sales-report-table').DataTable({
        data: [], autoWidth: false, searching: false, pageLength: 25,
        order: [[1, 'asc'], [0, 'asc']], dom: 'rtip',
        language: {emptyTable: 'No completed transactions for this period.'},
        columns: [
            {data: 'sale_id', render: renderText},
            {data: 'sale_date', render: renderText},
            {data: 'customer_name', defaultContent: '', render: function (value, type) { return renderText(value || 'Walk-in Customer', type); }},
            {data: 'user_name', defaultContent: '', render: function (value, type) { return renderText(value || 'Unknown', type); }},
            {data: 'total_amount', render: renderMoney},
            {data: 'amount_paid', defaultContent: '', render: renderMoney},
            {data: 'change_amount', defaultContent: '', render: renderMoney}
        ]
    });
    const productTable = $('#product-report-table').DataTable({
        data: [], autoWidth: false, searching: false, pageLength: 25, order: [[0, 'asc']], dom: 'rtip',
        language: {emptyTable: 'No product sales for this period.'},
        columns: [
            {data: 'product_name', render: renderText},
            {data: 'quantity_sold', render: renderText},
            {data: 'total_sales', render: renderMoney}
        ]
    });
    const inventoryTable = $('#inventory-report-table').DataTable({
        data: [], autoWidth: false, searching: false, pageLength: 25, order: [[0, 'asc']], dom: 'rtip',
        language: {emptyTable: 'No inventory items found.'},
        columns: [
            {data: 'inv_name', render: renderText},
            {data: 'amount', render: renderQuantity},
            {data: 'unit', render: renderText},
            {data: 'min_stock', render: renderQuantity},
            {data: 'is_low', render: function (low, type) { return renderText(low ? 'Low stock' : 'In stock', type); }}
        ]
    });

    function clearReport() {
        table.clear().draw();
        productTable.clear().draw();
        inventoryTable.clear().draw();
        $('#sales-report-results').prop('hidden', true);
        $('#report-label, #report-count, #report-total, #report-footer-total, #product-footer-quantity, #product-footer-total, #inventory-low-count, #inventory-footer-count, #inventory-footer-low').text('');
    }
    function showError(message) {
        clearReport();
        $('#report-message').text('');
        $('#report-error').text(message).prop('hidden', false);
    }
    function loadReport() {
        const number = ++requestNumber;
        if (request && request.abort) request.abort();
        clearReport();
        $('#report-error').text('').prop('hidden', true);
        const period = $('#report-period').val();
        const products = $('#report-type').val() === 'products';
        const inventory = $('#report-type').val() === 'inventory';
        const date = $(period === 'weekly' ? '#report-week' : period === 'monthly' ? '#report-month' : '#report-date').val();
        if ((!inventory && !date) || !$('#sales-report-form')[0].checkValidity()) {
            showError(period === 'weekly' ? 'Choose a valid report week.'
                : period === 'monthly' ? 'Choose a valid report month.' : 'Choose a valid report date.');
            $('#generate-sales-report').prop('disabled', false);
            return;
        }
        $('#report-message').text('Loading report...');
        $('#generate-sales-report').prop('disabled', true);
        request = $.ajax({url: `${base_url}/api/Reports/${inventory ? 'inventory' : products ? 'products' : 'sales'}`, type: 'GET', dataType: 'json',
            data: inventory ? {} : {period: period, date: date}})
            .done(function (response) {
                if (number !== requestNumber) return;
                if (response.status !== 'success' || !response.data || !Array.isArray(response.data.rows)) {
                    showError(response.message || 'Unable to load the sales report.');
                    return;
                }
                const report = response.data;
                const activeTable = inventory ? inventoryTable : products ? productTable : table;
                activeTable.rows.add(report.rows).draw();
                $('#report-label').text(report.label);
                $('#report-count').text(inventory ? Number(report.total_items) + ' items'
                    : Number(report.transaction_count) + (Number(report.transaction_count) === 1 ? ' transaction' : ' transactions'));
                $('#report-money-summary').prop('hidden', inventory);
                $('#inventory-low-count').prop('hidden', !inventory).text(inventory ? ' | ' + Number(report.low_stock_count) + ' low stock' : '');
                $('#report-total, #report-footer-total').text(inventory ? '' : money(report.total_amount));
                $('#product-footer-quantity').text(text(report.total_quantity));
                $('#product-footer-total').text(inventory ? '' : money(report.total_amount));
                $('#sales-report-wrapper').prop('hidden', products || inventory);
                $('#product-report-wrapper').prop('hidden', !products);
                $('#inventory-report-wrapper').prop('hidden', !inventory);
                $('#inventory-footer-count').text(inventory ? 'Items: ' + Number(report.total_items) : '');
                $('#inventory-footer-low').text(inventory ? 'Low stock: ' + Number(report.low_stock_count) : '');
                $('#sales-report-results').prop('hidden', false);
                $('#report-message').text(report.rows.length ? '' : inventory ? 'No inventory items found.' : 'No completed transactions for this period.');
                activeTable.columns.adjust();
            })
            .fail(function (xhr, status) {
                if (number !== requestNumber || status === 'abort') return;
                showError(xhr.responseJSON && xhr.responseJSON.message || 'Unable to load the sales report. Please try again.');
            })
            .always(function () {
                if (number !== requestNumber) return;
                request = null;
                $('#generate-sales-report').prop('disabled', false);
            });
    }
    function clearSelection() {
        ++requestNumber;
        if (request && request.abort) request.abort();
        request = null;
        clearReport();
        $('#report-error').text('').prop('hidden', true);
        $('#report-message').text('Choose a period and generate the report.');
        $('#generate-sales-report').prop('disabled', false);
    }
    function updatePeriodFields() {
        const period = $('#report-period').val();
        const inventory = $('#report-type').val() === 'inventory';
        $('#report-period-controls').prop('hidden', inventory);
        $('#report-period').prop('disabled', inventory);
        $('#report-date').prop('disabled', inventory || period !== 'daily').prop('required', !inventory && period === 'daily');
        $('#report-week').prop('disabled', inventory || period !== 'weekly').prop('required', !inventory && period === 'weekly');
        $('#report-month').prop('disabled', inventory || period !== 'monthly').prop('required', !inventory && period === 'monthly');
        $('#report-date-field').prop('hidden', period !== 'daily');
        $('#report-week-field').prop('hidden', period !== 'weekly');
        $('#report-month-field').prop('hidden', period !== 'monthly');
    }
    $('#report-period').on('change', function () { updatePeriodFields(); clearSelection(); });
    $('#report-type').on('change', function () { updatePeriodFields(); clearSelection(); });
    $('#sales-report-form').on('submit', function (event) { event.preventDefault(); loadReport(); });
    updatePeriodFields();
    loadReport();
});
