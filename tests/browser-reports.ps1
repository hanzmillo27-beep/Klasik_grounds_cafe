param([string]$BrowserPath = '', [string]$PhpPath = '', [int]$DebugPort = 0)

# Real report view, DataTables, and Reports.js; every request is an offline GET fixture.
$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if (-not $BrowserPath) {
    $BrowserPath = @('C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
        'C:\Program Files\Microsoft\Edge\Application\msedge.exe') |
        Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
}
if (-not $BrowserPath -or -not (Test-Path -LiteralPath $BrowserPath)) { throw 'Edge not found. Supply -BrowserPath.' }
if (-not $PhpPath) {
    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCommand) { $PhpPath = $phpCommand.Source }
    elseif (Test-Path -LiteralPath 'C:\xampp\php\php.exe') { $PhpPath = 'C:\xampp\php\php.exe' }
}
if (-not $PhpPath -or -not (Test-Path -LiteralPath $PhpPath)) { throw 'PHP CLI not found. Supply -PhpPath.' }
if ($DebugPort -eq 0) {
    $listener = New-Object Net.Sockets.TcpListener([Net.IPAddress]::Loopback, 0)
    $listener.Start()
    try { $DebugPort = $listener.LocalEndpoint.Port } finally { $listener.Stop() }
}
if ($DebugPort -lt 1 -or $DebugPort -gt 65535) { throw 'Invalid -DebugPort.' }
$artifactDirectory = Join-Path $env:TEMP ('klasik-reports-browser-' + [guid]::NewGuid().ToString('N'))
$null = New-Item -ItemType Directory -Path $artifactDirectory
$htmlPath = Join-Path $artifactDirectory 'reports.html'
$rendererPath = Join-Path $artifactDirectory 'render-reports.php'
$profilePath = Join-Path $artifactDirectory 'edge-profile'
$viewPath = (Join-Path $projectRoot 'app/views/Reports/index.php').Replace('\', '/').Replace("'", "\'")
$phpRenderer = '<?php $today = ''2026-10-19''; $week = ''2026-W43''; $month = ''2026-10''; require ''' + $viewPath + ''';'
[IO.File]::WriteAllText($rendererPath, $phpRenderer, [Text.UTF8Encoding]::new($false))
$renderedView = (& $PhpPath -f $rendererPath | Out-String)
if ($LASTEXITCODE -ne 0) { throw 'Rendering the reports view failed.' }
$assetTags = foreach ($relativePath in @(
    'public/plugins/jquery/jquery.min.js',
    'public/plugins/bootstrap/js/bootstrap.bundle.min.js',
    'public/plugins/datatables/jquery.dataTables.min.js',
    'public/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js',
    'public/plugins/datatables-buttons/js/dataTables.buttons.min.js',
    'public/plugins/datatables-buttons/js/buttons.bootstrap4.min.js',
    'public/plugins/datatables-buttons/js/buttons.html5.min.js',
    'public/plugins/datatables-buttons/js/buttons.print.min.js'
)) {
    $assetPath = Join-Path $projectRoot $relativePath
    if (-not (Test-Path -LiteralPath $assetPath)) { throw ('Missing browser asset: ' + $relativePath) }
    '<script src="' + ([uri]$assetPath).AbsoluteUri + '"></script>'
}
$reportsUri = ([uri](Join-Path $projectRoot 'public/js/Reports.js')).AbsoluteUri
$fixtures = @'
window.base_url = 'http://reports-fixture.invalid';
window.__reportsHarness = {errors: [], requests: [], hostile: '<img src=x onerror="window.__reportInjected=true">'};
window.addEventListener('error', event => __reportsHarness.errors.push(event.message || 'Browser resource error.'));
window.addEventListener('unhandledrejection', event => __reportsHarness.errors.push(String(event.reason)));
window.alert = message => __reportsHarness.errors.push('Unexpected alert: ' + message);
window.print = function () { throw new Error('The test must not open a print dialog.'); };
$.ajax = function (options) {
    const h = __reportsHarness, url = new URL(options.url);
    const query = Object.assign(Object.fromEntries(url.searchParams), options.data || {});
    const method = String(options.type || options.method || 'GET').toUpperCase();
    h.requests.push({path: url.pathname, method: method, period: query.period, date: query.date, queryKeys: Object.keys(query)});
    if (!['/api/Reports/sales', '/api/Reports/products', '/api/Reports/inventory'].includes(url.pathname) || method !== 'GET'
        || (url.pathname !== '/api/Reports/inventory' && !['daily', 'weekly', 'monthly'].includes(query.period))) {
        h.errors.push('Disallowed report request.'); throw new Error('Only offline daily/weekly/monthly report GETs are allowed.');
    }
    const deferred = $.Deferred(), xhr = deferred.promise();
    xhr.readyState = 1;
    xhr.abort = function () { xhr.readyState = 0; deferred.reject(xhr, 'abort', 'abort'); };
    setTimeout(function () {
        if (xhr.readyState === 0) return;
        xhr.readyState = 4;
        let response;
        if (url.pathname === '/api/Reports/inventory') {
            response = {status: 'success', data: {report_type: 'inventory', generated_at: '2026-10-19 12:34:56',
                label: 'Inventory report: 2026-10-19 12:34:56', total_items: 3, low_stock_count: 2, rows: [
                    {inv_id: 1, inv_name: '=Trace stock', amount: '0.001', unit: '@kg', min_stock: '0.010', is_low: true},
                    {inv_id: 2, inv_name: 'Zero stock', amount: '0.000', unit: 'g', min_stock: '0.000', is_low: true},
                    {inv_id: 3, inv_name: h.hostile, amount: '2.345', unit: '<b>kg</b>', min_stock: '1.000', is_low: false}
                ]}};
        } else if (url.pathname === '/api/Reports/products') {
            const weekly = query.period === 'weekly';
            if ((!weekly && query.date !== '2026-10-19') || (weekly && query.date !== '2026-W43')) throw new Error('Unexpected product-report fixture period.');
            response = {status: 'success', data: {report_type: 'products', period: query.period,
                start_date: '2026-10-19', end_date: weekly ? '2026-10-25' : '2026-10-19',
                label: 'Product sales: 2026-10-19' + (weekly ? ' to 2026-10-25' : ''),
                total_quantity: weekly ? 9 : 6, total_amount: weekly ? '230.00' : '130.00',
                transaction_count: weekly ? 3 : 2, rows: [
                    {product_id: 1, product_name: '=SUM(1,1)', quantity_sold: weekly ? 6 : 3, total_sales: weekly ? '225.50' : '125.50'},
                    {product_id: 2, product_name: h.hostile, quantity_sold: 2, total_sales: '0.30'},
                    {product_id: null, product_name: 'Deleted Product', quantity_sold: 1, total_sales: '4.20'}
                ]}};
        } else if (query.period === 'monthly') {
            if (query.date !== '2024-02') throw new Error('Unexpected monthly fixture value.');
            response = {status: 'success', data: {period: 'monthly', start_date: '2024-02-01', end_date: '2024-02-29',
                label: 'Monthly sales: 2024-02-01 to 2024-02-29', transaction_count: 2, total_amount: '40.40', rows: [
                    {sale_id: 20, sale_date: '2024-02-01 00:00:00', customer_name: 'First day', user_name: 'Owner',
                        total_amount: '10.10', amount_paid: '20.00', change_amount: '9.90'},
                    {sale_id: 21, sale_date: '2024-02-29 23:59:59', customer_name: 'Leap day', user_name: 'Owner',
                        total_amount: '30.30', amount_paid: '40.00', change_amount: '9.70'}
                ]}};
        } else if (query.period === 'weekly') {
            if (query.date !== '2026-W43') throw new Error('Unexpected weekly fixture value.');
            response = {status: 'success', data: {period: 'weekly', start_date: '2026-10-19', end_date: '2026-10-25',
                label: 'Weekly sales: 2026-10-19 to 2026-10-25', transaction_count: 2, total_amount: '30.30', rows: [
                    {sale_id: 10, sale_date: '2026-10-19 00:00:00', customer_name: 'Monday sale', user_name: 'Owner',
                        total_amount: '20.10', amount_paid: '30.00', change_amount: '9.90'},
                    {sale_id: 11, sale_date: '2026-10-25 23:59:59', customer_name: 'Sunday sale', user_name: 'Owner',
                        total_amount: '10.20', amount_paid: '20.00', change_amount: '9.80'}
                ]}};
        } else if (query.date === '2026-10-21') response = {status: 'error', message: '<b>Report unavailable</b>'};
        else {
            const rows = query.date === '2026-10-19' ? [
                {sale_id: 1, sale_date: '2026-10-19 09:00:00', customer_name: '=SUM(1,1)', user_name: h.hostile,
                    total_amount: '125.50', amount_paid: '200.00', change_amount: '74.50'},
                {sale_id: 2, sale_date: '2026-10-19 10:00:00', customer_name: '+Formula', user_name: '@Cashier',
                    total_amount: '0.30', amount_paid: '1.00', change_amount: '0.70'}
            ] : [
                {sale_id: 3, sale_date: '2026-10-20 10:00:00', customer_name: 'Next day', user_name: 'Owner',
                    total_amount: '10.25', amount_paid: '20.00', change_amount: '9.75'}
            ];
            response = {status: 'success', data: {period: 'daily', start_date: query.date, end_date: query.date,
                label: 'Daily sales: ' + query.date, rows: rows, transaction_count: rows.length,
                total_amount: query.date === '2026-10-19' ? '125.80' : '10.25'}};
        }
        if (typeof options.success === 'function') options.success(response, 'success', xhr);
        deferred.resolve(response, 'success', xhr);
        if (typeof options.complete === 'function') options.complete(xhr, 'success');
    }, 30);
    return xhr;
};
'@
$html = @"
<!doctype html><html><head><meta charset="utf-8">
<meta http-equiv="Content-Security-Policy" content="connect-src 'none';">
<title>Offline sales reports</title></head><body>
$renderedView
$($assetTags -join [Environment]::NewLine)
<script>$fixtures</script><script src="$reportsUri"></script>
</body></html>
"@
Set-Content -LiteralPath $htmlPath -Encoding UTF8 -Value $html
$staffRendererPath = Join-Path $artifactDirectory 'render-staff-reports.php'
$staffHtmlPath = Join-Path $artifactDirectory 'staff-reports.html'
$staffPhpRenderer = '<?php $today = ''2026-10-19''; $week = ''2026-W43''; $month = ''2026-10''; $inventoryOnly = true; require ''' + $viewPath + ''';'
[IO.File]::WriteAllText($staffRendererPath, $staffPhpRenderer, [Text.UTF8Encoding]::new($false))
$staffView = (& $PhpPath -f $staffRendererPath | Out-String)
if ($LASTEXITCODE -ne 0) { throw 'Rendering the staff reports view failed.' }
Set-Content -LiteralPath $staffHtmlPath -Encoding UTF8 -Value ($html.Replace($renderedView, $staffView))
$socket = New-Object Net.WebSockets.ClientWebSocket
$browserProcess = $null
$script:requestId = 0
function Send-Cdp([string]$method, [hashtable]$parameters = @{}) {
    $script:requestId++
    $requestId = $script:requestId
    $json = @{id = $requestId; method = $method; params = $parameters} | ConvertTo-Json -Depth 20 -Compress
    $bytes = [Text.Encoding]::UTF8.GetBytes($json)
    $timeout = New-Object Threading.CancellationTokenSource
    $timeout.CancelAfter(20000)
    try {
        $null = $socket.SendAsync([ArraySegment[byte]]::new($bytes), [Net.WebSockets.WebSocketMessageType]::Text, $true, $timeout.Token).GetAwaiter().GetResult()
        do {
            $stream = New-Object IO.MemoryStream
            try {
                do {
                    $buffer = New-Object byte[] 65536
                    $received = $socket.ReceiveAsync([ArraySegment[byte]]::new($buffer), $timeout.Token).GetAwaiter().GetResult()
                    if ($received.MessageType -eq [Net.WebSockets.WebSocketMessageType]::Close) { throw 'Browser closed the CDP socket.' }
                    $stream.Write($buffer, 0, $received.Count)
                } while (-not $received.EndOfMessage)
                $reply = [Text.Encoding]::UTF8.GetString($stream.ToArray()) | ConvertFrom-Json
            } finally { $stream.Dispose() }
        } while ($reply.id -ne $requestId)
        if ($reply.error) { throw ($reply.error | ConvertTo-Json -Depth 8 -Compress) }
        return $reply.result
    } finally { $timeout.Dispose() }
}
function Evaluate-Js([string]$expression) {
    $result = Send-Cdp 'Runtime.evaluate' @{expression = $expression; awaitPromise = $true; returnByValue = $true}
    if ($result.exceptionDetails) { throw ($result.exceptionDetails | ConvertTo-Json -Depth 8 -Compress) }
    return $result.result.value
}
try {
    $browserProcess = Start-Process -FilePath $BrowserPath -ArgumentList @(
        '--headless=new', '--disable-gpu', '--disable-extensions', '--no-first-run',
        '--no-default-browser-check', '--disable-background-networking',
        '--remote-debugging-address=127.0.0.1', ('--remote-debugging-port=' + $DebugPort),
        ('--user-data-dir="' + $profilePath + '"'), 'about:blank'
    ) -WindowStyle Hidden -PassThru
    $tabs = $null
    for ($attempt = 0; $attempt -lt 50; $attempt++) {
        try { $tabs = Invoke-RestMethod -Uri ('http://127.0.0.1:' + $DebugPort + '/json/list') -TimeoutSec 1; break }
        catch { Start-Sleep -Milliseconds 100 }
    }
    $page = @($tabs | Where-Object { $_.type -eq 'page' }) | Select-Object -First 1
    if (-not $page) { throw 'Headless Edge did not provide a page target.' }
    $socket.ConnectAsync([uri]$page.webSocketDebuggerUrl, [Threading.CancellationToken]::None).GetAwaiter().GetResult()
    $null = Send-Cdp 'Page.enable'
    $null = Send-Cdp 'Page.navigate' @{url = ([uri]$htmlPath).AbsoluteUri}
    $ready = $false
    for ($attempt = 0; $attempt -lt 100; $attempt++) {
        $state = Evaluate-Js '(() => { const h = window.__reportsHarness; return h ? {ready: document.readyState === "complete" && $.fn.dataTable.isDataTable("#sales-report-table") && $("#sales-report-table").DataTable().rows().count() === 2, errors: h.errors} : null; })()'
        if ($state -and $state.errors.Count) { throw ('Report browser errors: ' + ($state.errors -join '; ')) }
        if ($state -and $state.ready) { $ready = $true; break }
        Start-Sleep -Milliseconds 100
    }
    if (-not $ready) { throw 'The daily sales report did not load.' }
    $checks = @'
(async () => {
    const h = __reportsHarness, table = $('#sales-report-table').DataTable();
    function assert(condition, message) { if (!condition) throw new Error(message); }
    function assertNoOutputControls() {
        assert(!$('.dt-buttons, .buttons-csv, .buttons-print').length, 'Reports must not display CSV or print controls.');
    }
    async function waitFor(condition, message) {
        for (let attempt = 0; attempt < 100; attempt++) { if (condition()) return; await new Promise(r => setTimeout(r, 20)); }
        throw new Error(message);
    }
    const peso = String.fromCharCode(0x20b1);
    assert($('#report-date').val() === '2026-10-19' && h.requests[0].date === '2026-10-19', 'Initial report must use the server-supplied date.');
    assert($('#report-period').val() === 'daily' && !$('#report-date').prop('disabled') && $('#report-date').prop('required')
        && $('#report-week').prop('disabled') && !$('#report-week').prop('required')
        && $('#report-month').prop('disabled') && !$('#report-month').prop('required'), 'Only the daily picker must be active initially.');
    assert(!$('#sales-report-results').prop('hidden') && $('#report-total').text() === peso + '125.80'
        && $('#report-count').text() === '2 transactions', 'Daily report summary differs from its rows.');
    assert($('#sales-report-table tbody').text().includes(h.hostile)
        && !$('#sales-report-table img').length && !window.__reportInjected, 'Report text must render literally without injected markup.');
    assert($('#sales-report-table tbody').text().includes('=SUM(1,1)')
        && $('#sales-report-table tbody').text().includes('+Formula')
        && $('#sales-report-table tbody').text().includes('@Cashier')
        && $('#report-footer-total').text() === peso + '125.80', 'Daily rows must preserve literal text and show the loaded total.');
    assertNoOutputControls();
    $('#report-date').val('2026-10-20');
    assert($('#report-label').text() === 'Daily sales: 2026-10-19', 'Editing the date must not relabel an already-loaded report.');
    $('#sales-report-form').trigger('submit');
    assert($('#sales-report-results').prop('hidden') && table.rows().count() === 0, 'Generating a report must hide and clear stale results.');
    await waitFor(() => table.rows().count() === 1 && !$('#generate-sales-report').prop('disabled'), 'Selected-day report did not load.');
    assert(h.requests[1].date === '2026-10-20' && $('#report-label').text() === 'Daily sales: 2026-10-20'
        && $('#report-total').text() === peso + '10.25', 'The selected report date or total differs.');
    const selected = table.rows().data().toArray();
    assert(selected.length === 1 && selected[0].sale_id === 3 && $('#report-footer-total').text() === peso + '10.25',
        'Rows and footer must use only the newly loaded period.');
    $('#report-date').val('2026-10-21');
    $('#sales-report-form').trigger('submit');
    await waitFor(() => !$('#report-error').prop('hidden') && !$('#generate-sales-report').prop('disabled'), 'Report errors were not displayed.');
    assert($('#report-error').text() === '<b>Report unavailable</b>' && !$('#report-error b').length,
        'Report error text must be escaped.');
    assert($('#sales-report-results').prop('hidden') && table.rows().count() === 0 && $('#report-total').text() === '',
        'A failed report must not retain stale rows or totals.');
    assertNoOutputControls();
    $('#report-period').val('weekly').trigger('change');
    assert($('#report-date').prop('disabled') && !$('#report-date').prop('required')
        && !$('#report-week').prop('disabled') && $('#report-week').prop('required')
        && $('#report-week').val() === '2026-W43' && $('#report-month').prop('disabled')
        && !$('#report-month').prop('required'), 'Weekly selection must activate only the ISO-week picker and preserve its default.');
    $('#sales-report-form').trigger('submit');
    await waitFor(() => table.rows().count() === 2 && !$('#generate-sales-report').prop('disabled'), 'Weekly report did not load.');
    assert(h.requests[3].period === 'weekly' && h.requests[3].date === '2026-W43'
        && $('#report-label').text() === 'Weekly sales: 2026-10-19 to 2026-10-25'
        && $('#report-total').text() === peso + '30.30', 'The weekly query, Monday/Sunday boundaries, or total differs.');
    const weekly = table.rows().data().toArray();
    assert(weekly.length === 2 && weekly[0].sale_date === '2026-10-19 00:00:00'
        && weekly[1].sale_date === '2026-10-25 23:59:59' && $('#report-footer-total').text() === peso + '30.30',
        'Weekly rows and footer must use the loaded week and its exact total.');
    $('#report-period').val('monthly').trigger('change');
    assert($('#report-month').attr('type') === 'month' && $('#report-month').val() === '2026-10'
        && !$('#report-month').prop('disabled') && $('#report-month').prop('required')
        && $('#report-date').prop('disabled') && !$('#report-date').prop('required')
        && $('#report-week').prop('disabled') && !$('#report-week').prop('required'), 'Only the monthly picker must be active in monthly mode.');
    $('#report-month').val('2024-02');
    $('#sales-report-form').trigger('submit');
    await waitFor(() => $('#report-label').text() === 'Monthly sales: 2024-02-01 to 2024-02-29'
        && !$('#generate-sales-report').prop('disabled'), 'Leap-month report did not load.');
    assert(h.requests[4].period === 'monthly' && h.requests[4].date === '2024-02'
        && $('#report-total').text() === peso + '40.40', 'Monthly query or total differs.');
    const monthly = table.rows().data().toArray();
    assert(monthly.length === 2 && monthly[0].sale_date === '2024-02-01 00:00:00'
        && monthly[1].sale_date === '2024-02-29 23:59:59' && $('#report-footer-total').text() === peso + '40.40',
        'Monthly rows and footer must include the leap-day boundary and loaded total.');
    $('#report-period').val('weekly').trigger('change');
    assert($('#report-month').val() === '2024-02' && $('#report-month').prop('disabled')
        && !$('#report-month').prop('required') && $('#report-week').val() === '2026-W43', 'Switching from monthly must preserve both month and week selections.');
    $('#sales-report-form').trigger('submit');
    await waitFor(() => $('#report-label').text() === 'Weekly sales: 2026-10-19 to 2026-10-25'
        && !$('#generate-sales-report').prop('disabled'), 'Weekly report did not recover after monthly selection.');
    assert($('#report-total').text() === peso + '30.30' && $('#report-footer-total').text() === peso + '30.30',
        'Returning to weekly must replace monthly totals.');
    $('#report-period').val('daily').trigger('change');
    assert($('#report-date').val() === '2026-10-21' && $('#report-week').val() === '2026-W43'
        && !$('#report-date').prop('disabled') && $('#report-date').prop('required')
        && $('#report-week').prop('disabled') && !$('#report-week').prop('required')
        && $('#report-month').val() === '2024-02' && $('#report-month').prop('disabled')
        && !$('#report-month').prop('required'), 'Switching back must preserve all picker values and validate only the daily date.');
    $('#report-date').val('2026-10-19');
    $('#sales-report-form').trigger('submit');
    await waitFor(() => $('#report-label').text() === 'Daily sales: 2026-10-19' && !$('#generate-sales-report').prop('disabled'), 'Returning to daily reports did not load the selected date.');
    assert($('#report-total').text() === peso + '125.80' && $('#report-footer-total').text() === peso + '125.80',
        'Returning to daily reports must replace the weekly total.');
    const productTable = $('#product-report-table').DataTable();
    $('#report-type').val('products').trigger('change');
    assert($('#sales-report-results').prop('hidden') && table.rows().count() === 0 && productTable.rows().count() === 0,
        'Switching report types must immediately clear old results.');
    $('#sales-report-form').trigger('submit');
    await waitFor(() => productTable.rows().count() === 3 && !$('#generate-sales-report').prop('disabled'), 'Product sales report did not load.');
    assert(h.requests[7].path === '/api/Reports/products' && h.requests[7].period === 'daily' && h.requests[7].date === '2026-10-19'
        && $('#sales-report-wrapper').prop('hidden') && !$('#product-report-wrapper').prop('hidden'), 'Product report must use the selected period and its own table.');
    assert($('#report-label').text() === 'Product sales: 2026-10-19' && $('#report-total').text() === peso + '130.00'
        && $('#report-count').text() === '2 transactions' && $('#product-footer-quantity').text() === '6'
        && $('#product-footer-total').text() === peso + '130.00', 'Product summary totals differ.');
    const productRows = productTable.rows().data().toArray();
    assert(productRows.find(row => row.product_id === 1).quantity_sold === 3
        && productRows.find(row => row.product_id === 1).product_name === '=SUM(1,1)'
        && productRows.find(row => row.product_id === 1).total_sales === '125.50'
        && productRows.find(row => row.product_id === null).product_name === 'Deleted Product', 'Product aggregates or historical deleted-product rows were not retained.');
    assert($('#product-report-table tbody').text().includes(h.hostile) && !$('#product-report-table img').length && !window.__reportInjected,
        'Product names must display literally.');
    assertNoOutputControls();
    $('#report-period').val('weekly').trigger('change');
    assert(productTable.rows().count() === 0 && $('#sales-report-results').prop('hidden'), 'Changing product periods must clear stale results.');
    $('#sales-report-form').trigger('submit');
    await waitFor(() => $('#report-label').text() === 'Product sales: 2026-10-19 to 2026-10-25'
        && !$('#generate-sales-report').prop('disabled'), 'Weekly product report did not load.');
    assert(h.requests[8].path === '/api/Reports/products' && h.requests[8].date === '2026-W43'
        && $('#product-footer-quantity').text() === '9' && $('#product-footer-total').text() === peso + '230.00'
        && $('#report-total').text() === peso + '230.00'
        && productTable.rows().count() === 3, 'Weekly product rows and totals must replace the daily report.');
    $('#sales-report-form').trigger('submit');
    $('#report-type').val('sales').trigger('change');
    await new Promise(resolve => setTimeout(resolve, 60));
    assert(table.rows().count() === 0 && productTable.rows().count() === 0 && $('#sales-report-results').prop('hidden')
        && !$('#generate-sales-report').prop('disabled'), 'Changing report type must abort pending data without restoring stale rows.');
    $('#report-period').val('daily').trigger('change');
    $('#sales-report-form').trigger('submit');
    await waitFor(() => $('#report-label').text() === 'Daily sales: 2026-10-19' && !$('#generate-sales-report').prop('disabled'), 'Sales report did not recover after product reports.');
    assert(table.rows().count() === 2 && productTable.rows().count() === 0 && !$('#sales-report-wrapper').prop('hidden')
        && $('#product-report-wrapper').prop('hidden') && $('#report-total').text() === peso + '125.80', 'Switching back must restore only the selected sales report.');
    const inventoryTable = $('#inventory-report-table').DataTable();
    $('#report-type').val('inventory').trigger('change');
    assert($('#report-period-controls').prop('hidden') && $('#report-period').prop('disabled')
        && ['#report-date', '#report-week', '#report-month'].every(id => $(id).prop('disabled') && !$(id).prop('required')),
        'Inventory snapshots must hide and disable all period/date controls.');
    $('#sales-report-form').trigger('submit');
    await waitFor(() => inventoryTable.rows().count() === 3 && !$('#generate-sales-report').prop('disabled'), 'Inventory report did not load.');
    assert(h.requests[11].path === '/api/Reports/inventory' && h.requests[11].queryKeys.length === 0,
        'Inventory reports must request the current snapshot without date or period filters.');
    assert($('#report-label').text() === 'Inventory report: 2026-10-19 12:34:56' && $('#report-count').text() === '3 items'
        && $('#inventory-low-count').text().includes('2 low stock') && !$('#inventory-low-count').prop('hidden')
        && $('#report-money-summary').prop('hidden') && $('#report-total').text() === '', 'Inventory summary must show counts and snapshot time without summing mixed units.');
    assert(!$('#inventory-report-wrapper').prop('hidden') && $('#sales-report-wrapper').prop('hidden')
        && $('#product-report-wrapper').prop('hidden') && table.rows().count() === 0 && productTable.rows().count() === 0,
        'Inventory must use its own table and clear financial rows.');
    assert($('#inventory-report-table tbody').text().includes(h.hostile)
        && $('#inventory-report-table tbody').text().includes('<b>kg</b>')
        && !$('#inventory-report-table img, #inventory-report-table b').length && !window.__reportInjected,
        'Inventory names and units must render as literal text.');
    const inventoryRows = inventoryTable.rows().data().toArray();
    assert(inventoryRows.some(row => row.inv_name === '=Trace stock' && row.amount === '0.001'
            && row.unit === '@kg' && row.min_stock === '0.010' && row.is_low)
        && inventoryRows.some(row => row.inv_name === 'Zero stock' && row.amount === '0.000'
            && row.min_stock === '0.000' && row.is_low)
        && $('#inventory-report-table tbody').text().includes('Low stock')
        && $('#inventory-report-table tbody').text().includes('In stock'), 'Inventory rows must preserve fractional and zero quantities, literal units, and stock status.');
    assert($('#inventory-footer-count').text() === 'Items: 3' && $('#inventory-footer-low').text() === 'Low stock: 2',
        'Inventory footers must show item and low-stock counts.');
    assertNoOutputControls();
    $('#report-type').val('sales').trigger('change');
    assert(!$('#report-period').prop('disabled') && !$('#report-date').prop('disabled') && $('#report-date').prop('required'),
        'Returning to sales must restore the preserved active date picker.');
    $('#sales-report-form').trigger('submit');
    await waitFor(() => table.rows().count() === 2 && !$('#generate-sales-report').prop('disabled'), 'Sales reports did not recover after inventory.');
    assert(inventoryTable.rows().count() === 0 && $('#inventory-report-wrapper').prop('hidden')
        && !$('#report-money-summary').prop('hidden') && $('#report-total').text() === peso + '125.80', 'Inventory snapshot data must not persist into a sales report.');
    assert(!h.errors.length && h.requests.length === 13 && h.requests.every(request => request.method === 'GET'), 'Unexpected browser error or external request.');
    assertNoOutputControls();
    return 'Owner report checks passed: daily/weekly/monthly sales, product aggregates, inventory snapshots, safe display, picker preservation, stale/pending clearing, and no CSV/print controls.';
})()
'@
    Write-Output (Evaluate-Js $checks)
    $null = Send-Cdp 'Page.navigate' @{url = ([uri]$staffHtmlPath).AbsoluteUri}
    $staffReady = $false
    for ($attempt = 0; $attempt -lt 100; $attempt++) {
        try {
            $state = Evaluate-Js '(() => { const h = window.__reportsHarness; return h ? {ready: document.readyState === "complete" && $("#report-type option").length === 1 && $.fn.dataTable.isDataTable("#inventory-report-table") && $("#inventory-report-table").DataTable().rows().count() === 3, errors: h.errors} : null; })()'
            if ($state -and $state.errors.Count) { throw ('Staff browser errors: ' + ($state.errors -join '; ')) }
            if ($state -and $state.ready) { $staffReady = $true; break }
        } catch { if ($_.Exception.Message -like 'Staff browser errors:*' -or $attempt -eq 99) { throw } }
        Start-Sleep -Milliseconds 100
    }
    if (-not $staffReady) { throw 'The staff inventory-only report did not load.' }
    Write-Output (Evaluate-Js @'
(() => {
    const h = __reportsHarness;
    function assert(condition, message) { if (!condition) throw new Error(message); }
    assert($('#report-type option').length === 1 && $('#report-type').val() === 'inventory'
        && $('#report-type option').text() === 'Inventory', 'Staff must see only the Inventory report option.');
    assert($('#report-period-controls').prop('hidden') && $('#report-period').prop('disabled')
        && ['#report-date', '#report-week', '#report-month'].every(id => $(id).prop('disabled') && !$(id).prop('required')),
        'Staff inventory reports must not require financial date/period inputs.');
    assert(h.requests.length === 1 && h.requests[0].path === '/api/Reports/inventory'
        && h.requests[0].method === 'GET' && h.requests[0].queryKeys.length === 0 && !h.errors.length,
        'The staff view must make only the inventory snapshot request.');
    assert($('#inventory-report-table').DataTable().rows().count() === 3
        && $('#sales-report-table').DataTable().rows().count() === 0
        && $('#product-report-table').DataTable().rows().count() === 0
        && $('#sales-report-wrapper').prop('hidden') && $('#product-report-wrapper').prop('hidden')
        && $('#report-money-summary').prop('hidden') && $('#report-total').text() === '', 'Staff must receive only inventory data and counts.');
    assert(!$('.dt-buttons, .buttons-csv, .buttons-print').length, 'Staff reports must not display CSV or print controls.');
    return 'Staff reports passed: Inventory is the only option; snapshot loads without period/date inputs, financial requests, or CSV/print controls.';
})()
'@)
} finally {
    if ($socket.State -eq [Net.WebSockets.WebSocketState]::Open) { try { $null = Send-Cdp 'Browser.close' } catch { } }
    $socket.Dispose()
    if ($browserProcess -and -not $browserProcess.HasExited) { Stop-Process -Id $browserProcess.Id -ErrorAction SilentlyContinue }
    Write-Output ('Offline report artifacts: ' + $artifactDirectory)
}
