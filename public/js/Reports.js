$(document).ready(function () {
    let report = null;
    let request = null;
    let requestNumber = 0;

    function text(value) { return value == null ? '' : String(value); }
    function escapeHtml(value) { return $('<div>').text(text(value)).html(); }
    function decimal(value) { return Number(value || 0).toFixed(2); }
    function money(value) { return '\u20b1' + decimal(value); }
    function spreadsheetText(value) {
        const result = text(value);
        return /^[\s\u0000-\u001f]*[=+\-@]/.test(result) ? "'" + result : result;
    }
    function renderText(value, type) {
        return type === 'display' ? escapeHtml(value) : text(value);
    }
    function renderMoney(value, type) {
        if (value == null) return type === 'display' ? 'Not recorded' : '';
        return type === 'display' ? money(value) : decimal(value);
    }
    function exportOptions(forPrint, products) {
        function output(value) { return forPrint ? escapeHtml(value) : spreadsheetText(value); }
        return {
            columns: products ? [0, 1, 2] : [0, 1, 2, 3, 4, 5, 6], orthogonal: 'export',
            modifier: {search: 'none', order: 'applied', page: 'all', selected: null},
            stripHtml: false, decodeEntities: false, stripNewlines: false, trim: false,
            format: {
                header: function (value, column, node) { return output($(node).text()); },
                body: function (value) { return output(value); },
                footer: function (value, column) {
                    return output(!report ? '' : column === 0 ? report.label + ' total'
                        : products && column === 1 ? text(report.total_quantity)
                        : column === (products ? 2 : 4) ? decimal(report.total_amount) : '');
                }
            }
        };
    }
    function reportButtons(products) {
        return [
            {extend: 'csvHtml5', text: 'Export CSV', footer: true, bom: true,
                filename: function () {
                    return report ? (products ? 'product-sales-' + report.period : report.period + '-sales') + '-' + report.start_date
                        + (report.period === 'daily' ? '' : '-to-' + report.end_date) : 'sales-report';
                },
                exportOptions: exportOptions(false, products)},
            {extend: 'print', text: 'Print report', footer: true,
                title: function () { return report ? escapeHtml(report.label) : 'Sales report'; },
                messageTop: function () { return report ? 'Transactions: ' + Number(report.transaction_count) : ''; },
                exportOptions: exportOptions(true, products)}
        ];
    }

    const table = $('#sales-report-table').DataTable({
        data: [], autoWidth: false, searching: false, pageLength: 25,
        order: [[1, 'asc'], [0, 'asc']], dom: 'Brtip',
        language: {emptyTable: 'No completed transactions for this period.'},
        columns: [
            {data: 'sale_id', render: renderText},
            {data: 'sale_date', render: renderText},
            {data: 'customer_name', defaultContent: '', render: function (value, type) { return renderText(value || 'Walk-in Customer', type); }},
            {data: 'user_name', defaultContent: '', render: function (value, type) { return renderText(value || 'Unknown', type); }},
            {data: 'total_amount', render: renderMoney},
            {data: 'amount_paid', defaultContent: '', render: renderMoney},
            {data: 'change_amount', defaultContent: '', render: renderMoney}
        ],
        buttons: reportButtons(false)
    });
    const productTable = $('#product-report-table').DataTable({
        data: [], autoWidth: false, searching: false, pageLength: 25, order: [[0, 'asc']], dom: 'Brtip',
        language: {emptyTable: 'No product sales for this period.'},
        columns: [
            {data: 'product_name', render: renderText},
            {data: 'quantity_sold', render: renderText},
            {data: 'total_sales', render: renderMoney}
        ],
        buttons: reportButtons(true)
    });
    table.buttons().disable();
    productTable.buttons().disable();

    function clearReport() {
        report = null;
        table.clear().draw();
        table.buttons().disable();
        productTable.clear().draw();
        productTable.buttons().disable();
        $('#sales-report-results').prop('hidden', true);
        $('#report-label, #report-count, #report-total, #report-footer-total, #product-footer-quantity, #product-footer-total').text('');
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
        const date = $(period === 'weekly' ? '#report-week' : period === 'monthly' ? '#report-month' : '#report-date').val();
        if (!date || !$('#sales-report-form')[0].checkValidity()) {
            showError(period === 'weekly' ? 'Choose a valid report week.'
                : period === 'monthly' ? 'Choose a valid report month.' : 'Choose a valid report date.');
            $('#generate-sales-report').prop('disabled', false);
            return;
        }
        $('#report-message').text('Loading report...');
        $('#generate-sales-report').prop('disabled', true);
        request = $.ajax({url: `${base_url}/api/Reports/${products ? 'products' : 'sales'}`, type: 'GET', dataType: 'json',
            data: {period: period, date: date}})
            .done(function (response) {
                if (number !== requestNumber) return;
                if (response.status !== 'success' || !response.data || !Array.isArray(response.data.rows)) {
                    showError(response.message || 'Unable to load the sales report.');
                    return;
                }
                report = response.data;
                const activeTable = products ? productTable : table;
                activeTable.rows.add(report.rows).draw();
                $('#report-label').text(report.label);
                $('#report-count').text(Number(report.transaction_count) + (Number(report.transaction_count) === 1 ? ' transaction' : ' transactions'));
                $('#report-total, #report-footer-total').text(money(report.total_amount));
                $('#product-footer-quantity').text(text(report.total_quantity));
                $('#product-footer-total').text(money(report.total_amount));
                $('#sales-report-wrapper').prop('hidden', products);
                $('#product-report-wrapper').prop('hidden', !products);
                $('#sales-report-results').prop('hidden', false);
                $('#report-message').text(report.rows.length ? '' : 'No completed transactions for this period.');
                activeTable.buttons().enable();
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
        $('#report-date').prop('disabled', period !== 'daily').prop('required', period === 'daily');
        $('#report-week').prop('disabled', period !== 'weekly').prop('required', period === 'weekly');
        $('#report-month').prop('disabled', period !== 'monthly').prop('required', period === 'monthly');
        $('#report-date-field').prop('hidden', period !== 'daily');
        $('#report-week-field').prop('hidden', period !== 'weekly');
        $('#report-month-field').prop('hidden', period !== 'monthly');
    }
    $('#report-period').on('change', function () { updatePeriodFields(); clearSelection(); });
    $('#report-type').on('change', clearSelection);
    $('#sales-report-form').on('submit', function (event) { event.preventDefault(); loadReport(); });
    updatePeriodFields();
    loadReport();
});
