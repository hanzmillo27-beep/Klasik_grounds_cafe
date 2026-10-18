param([string]$BrowserPath = '', [string]$PhpPath = '', [int]$DebugPort = 0)

# Real report view, DataTables/Buttons, and Reports.js; every request is an offline GET fixture.
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
$phpRenderer = '<?php $today = ''2026-10-19''; require ''' + $viewPath + ''';'
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
    h.requests.push({path: url.pathname, method: method, period: query.period, date: query.date});
    if (url.pathname !== '/api/Reports/sales' || method !== 'GET' || query.period !== 'daily') {
        h.errors.push('Disallowed report request.'); throw new Error('Only offline daily report GETs are allowed.');
    }
    const deferred = $.Deferred(), xhr = deferred.promise();
    xhr.readyState = 1;
    xhr.abort = function () { xhr.readyState = 0; deferred.reject(xhr, 'abort', 'abort'); };
    setTimeout(function () {
        if (xhr.readyState === 0) return;
        xhr.readyState = 4;
        let response;
        if (query.date === '2026-10-21') response = {status: 'error', message: '<b>Report unavailable</b>'};
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
    async function waitFor(condition, message) {
        for (let attempt = 0; attempt < 100; attempt++) { if (condition()) return; await new Promise(r => setTimeout(r, 20)); }
        throw new Error(message);
    }
    const peso = String.fromCharCode(0x20b1);
    assert($('#report-date').val() === '2026-10-19' && h.requests[0].date === '2026-10-19', 'Initial report must use the server-supplied date.');
    assert(!$('#sales-report-results').prop('hidden') && $('#report-total').text() === peso + '125.80'
        && $('#report-count').text() === '2 transactions', 'Daily report summary differs from its rows.');
    assert($('#sales-report-table tbody').text().includes(h.hostile)
        && !$('#sales-report-table img').length && !window.__reportInjected, 'Report text must render literally without injected markup.');
    const configs = table.settings()[0]._buttons[0].inst.s.buttons.map(button => button.conf);
    const csv = configs.find(config => config.className.includes('buttons-csv'));
    const print = configs.find(config => config.className.includes('buttons-print'));
    assert(csv && print && csv.footer && print.footer, 'CSV and print buttons must include the report total.');
    const csvData = table.buttons.exportData(csv.exportOptions);
    assert(csvData.body.length === 2 && csvData.body[0][2] === "'=SUM(1,1)"
        && csvData.body[1][2] === "'+Formula" && csvData.body[1][3] === "'@Cashier", 'CSV must contain the loaded rows and neutralize spreadsheet formulas.');
    assert(csvData.body[0][3] === h.hostile && csvData.footer[0] === 'Daily sales: 2026-10-19 total'
        && csvData.footer[4] === '125.80', 'CSV must preserve literal text and identify the selected period total.');
    const printData = table.buttons.exportData(print.exportOptions);
    assert(printData.body.length === 2 && printData.body[0][3].includes('&lt;img')
        && !printData.body[0][3].includes('<img') && print.title() === 'Daily sales: 2026-10-19', 'Print configuration must escape report text and identify the loaded date.');
    $('#report-date').val('2026-10-20');
    assert(csv.filename() === 'daily-sales-2026-10-19', 'Editing the date must not relabel an already-loaded export.');
    $('#sales-report-form').trigger('submit');
    assert($('#sales-report-results').prop('hidden') && table.rows().count() === 0, 'Generating a report must hide and clear stale results.');
    await waitFor(() => table.rows().count() === 1 && !$('#generate-sales-report').prop('disabled'), 'Selected-day report did not load.');
    assert(h.requests[1].date === '2026-10-20' && $('#report-label').text() === 'Daily sales: 2026-10-20'
        && $('#report-total').text() === peso + '10.25', 'The selected report date or total differs.');
    const selected = table.buttons.exportData(csv.exportOptions);
    assert(selected.body.length === 1 && selected.body[0][0] === '3' && selected.footer[4] === '10.25'
        && csv.filename() === 'daily-sales-2026-10-20', 'Exports must use only the newly loaded period.');
    $('#report-date').val('2026-10-21');
    $('#sales-report-form').trigger('submit');
    await waitFor(() => !$('#report-error').prop('hidden') && !$('#generate-sales-report').prop('disabled'), 'Report errors were not displayed.');
    assert($('#report-error').text() === '<b>Report unavailable</b>' && !$('#report-error b').length,
        'Report error text must be escaped.');
    assert($('#sales-report-results').prop('hidden') && table.rows().count() === 0 && $('#report-total').text() === '',
        'A failed report must not retain stale rows or totals.');
    assert(table.buttons().nodes().toArray().every(node => $(node).hasClass('disabled') || node.disabled), 'Report exports must be disabled after an error.');
    assert(!h.errors.length && h.requests.length === 3
        && h.requests.every(request => request.period === 'daily' && request.method === 'GET'), 'Unexpected browser error or external request.');
    return 'Daily report browser checks passed: date selection, rows/count/total, safe text, formula-safe CSV, escaped print configuration, exact-period exports, and stale-result clearing on error. No download or print dialog opened.';
})()
'@
    Write-Output (Evaluate-Js $checks)
} finally {
    if ($socket.State -eq [Net.WebSockets.WebSocketState]::Open) { try { $null = Send-Cdp 'Browser.close' } catch { } }
    $socket.Dispose()
    if ($browserProcess -and -not $browserProcess.HasExited) { Stop-Process -Id $browserProcess.Id -ErrorAction SilentlyContinue }
    Write-Output ('Offline report artifacts: ' + $artifactDirectory)
}
