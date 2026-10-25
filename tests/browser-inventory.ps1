param([string]$BrowserPath = '', [string]$PhpPath = '', [int]$DebugPort = 0)

# Offline regression for the actual inventory PHP view, DataTables, and Inventory.js.
# The renderer requires the isolated SQLite test fixture, never app configuration.
# AJAX permits only list/history GET fixtures; no CRUD or application DB is used.
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
$artifactDirectory = Join-Path $env:TEMP ('klasik-inventory-browser-' + [guid]::NewGuid().ToString('N'))
$null = New-Item -ItemType Directory -Path $artifactDirectory
$htmlPath = Join-Path $artifactDirectory 'inventory.html'
$rendererPath = Join-Path $artifactDirectory 'render-inventory.php'
$profilePath = Join-Path $artifactDirectory 'edge-profile'
$fixturePath = (Join-Path $projectRoot 'tests/product_fixture.php').Replace('\', '/').Replace("'", "\'")
$viewPath = (Join-Path $projectRoot 'app/views/Inventory/index.php').Replace('\', '/').Replace("'", "\'")
$phpRenderer = "<?php require '" + $fixturePath + "'; require '" + $viewPath + "';"
[IO.File]::WriteAllText($rendererPath, $phpRenderer, [Text.UTF8Encoding]::new($false))
$renderedView = (& $PhpPath -f $rendererPath | Out-String)
if ($LASTEXITCODE -ne 0) { throw 'Rendering the inventory view with the isolated PHP fixture failed.' }

$assetTags = foreach ($relativePath in @(
    'public/plugins/jquery/jquery.min.js',
    'public/plugins/bootstrap/js/bootstrap.bundle.min.js',
    'public/plugins/datatables/jquery.dataTables.min.js',
    'public/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js',
    'public/plugins/datatables-responsive/js/dataTables.responsive.min.js',
    'public/plugins/datatables-responsive/js/responsive.bootstrap4.min.js'
)) {
    $assetPath = Join-Path $projectRoot $relativePath
    if (-not (Test-Path -LiteralPath $assetPath)) { throw ('Missing browser asset: ' + $relativePath) }
    '<script src="' + ([uri]$assetPath).AbsoluteUri + '"></script>'
}
$inventoryUri = ([uri](Join-Path $projectRoot 'public/js/Inventory.js')).AbsoluteUri
$fixtures = @'
window.base_url = 'http://inventory-fixture.invalid';
window.__inventoryHarness = {
    errors: [], requests: [], nextAmount: '2.345', listFetches: 0, historyResponses: [],
    hostileName: '<img src=x onerror="window.__inventoryInjected=true">',
    hostileUnit: '<b data-injected="unit">kg</b>'
};
const h = __inventoryHarness;
h.history = [
    {history_id: 20, inv_id: 1, inv_name: 'Trace stock', unit: 'kg', movement_type: 'sale',
        amount_before: '2.346', quantity_change: '-0.001', amount_after: '2.345', actor_name: '<b data-injected="actor">Cashier</b>',
        sale_id: 77, created_at: '2026-10-25 12:00:00'},
    {history_id: 19, inv_id: 3, inv_name: h.hostileName, unit: h.hostileUnit, movement_type: 'adjustment',
        amount_before: '1.234', quantity_change: '0.000', amount_after: '1.234', actor_name: 'Owner', sale_id: null,
        created_at: '2026-10-25 11:00:00', previous_inv_name: '<b data-injected="old-name">Old name</b>',
        previous_unit: 'g', previous_min_stock: '0.001', min_stock: '1.000'},
    {history_id: 18, inv_id: 1, inv_name: 'Trace stock', unit: 'kg', movement_type: 'restock',
        amount_before: '0.000', quantity_change: '2.345', amount_after: '2.345', actor_name: 'Owner', sale_id: null, created_at: '2026-10-25 10:00:00'},
    {history_id: 17, inv_id: 1, inv_name: 'Trace stock', unit: 'kg', movement_type: 'created',
        amount_before: '0.000', quantity_change: '0.001', amount_after: '0.001', actor_name: 'Owner', sale_id: null, created_at: '2026-10-25 09:00:00'},
    {history_id: 16, inv_id: 9, inv_name: 'Retired ingredient', unit: 'g', movement_type: 'deleted',
        amount_before: '1.000', quantity_change: '-1.000', amount_after: '0.000', actor_name: 'Owner', sale_id: null, created_at: '2026-10-24 12:00:00'}
].concat(Array.from({length: 12}, (_, index) => ({history_id: 15 - index, inv_id: 2, inv_name: 'Zero stock',
    unit: 'g', movement_type: 'restock', amount_before: index, quantity_change: '1.000', amount_after: index + 1,
    actor_name: 'Staff', sale_id: null, created_at: '2026-10-23 10:00:00'})));
window.addEventListener('error', event => __inventoryHarness.errors.push(event.message || 'Browser resource error.'));
window.addEventListener('unhandledrejection', event => __inventoryHarness.errors.push(String(event.reason)));
window.alert = message => __inventoryHarness.errors.push('Unexpected browser alert: ' + message);
window.msgBox = function () { __inventoryHarness.errors.push('Unexpected CRUD message.'); };
window.handleAjaxError = function () { __inventoryHarness.errors.push('Unexpected AJAX failure.'); };
$.ajax = function (options) {
    const h = __inventoryHarness, path = new URL(options.url).pathname;
    const method = String(options.type || options.method || 'GET').toUpperCase();
    h.requests.push({path: path, method: method, data: JSON.parse(JSON.stringify(options.data || {}))});
    if (!['/api/Inventory/all', '/api/Inventory/history'].includes(path) || method !== 'GET') {
        h.errors.push('Disallowed request: ' + method + ' ' + path);
        throw new Error('Only offline inventory list/history GET fixtures are allowed.');
    }
    const deferred = $.Deferred(), xhr = deferred.promise();
    xhr.readyState = 1;
    xhr.abort = function () { xhr.readyState = 0; deferred.reject(xhr, 'abort', 'abort'); };
    let response, fixture = {};
    if (path === '/api/Inventory/all') {
        h.listFetches++;
        response = {data: [
        {inv_id: 1, inv_name: 'Trace stock', amount: h.listFetches === 1 ? '0.001' : h.nextAmount,
            unit: 'kg', min_stock: '0.001', is_low: h.listFetches === 1},
        {inv_id: 2, inv_name: 'Zero stock', amount: '0.000', unit: 'g', min_stock: '0.000', is_low: true},
        {inv_id: 3, inv_name: h.hostileName, amount: '1.234', unit: h.hostileUnit, min_stock: '1.000', is_low: false}
        ]};
    } else {
        const data = options.data;
        fixture = h.historyResponses.shift() || {};
        const scoped = h.history.filter(row => !data.inv_id || Number(row.inv_id) === Number(data.inv_id));
        const query = String(data.search && data.search.value || '').toLowerCase();
        const filtered = scoped.filter(row => (!data.movement_type || row.movement_type === data.movement_type)
            && (!query || Object.values(row).some(value => String(value).toLowerCase().includes(query))));
        const start = Number(data.start || 0), length = Number(data.length || 10);
        response = {draw: Number(data.draw), recordsTotal: scoped.length, recordsFiltered: filtered.length,
            data: filtered.slice(start, start + length)};
    }
    setTimeout(function () {
        if (xhr.readyState === 0) return;
        xhr.readyState = 4;
        if (fixture.failure) {
            Object.assign(xhr, fixture.failure);
            if (typeof options.error === 'function') options.error(xhr, 'error');
            deferred.reject(xhr, 'error');
            if (typeof options.complete === 'function') options.complete(xhr, 'error');
            return;
        }
        if (typeof options.success === 'function') options.success(response, 'success', xhr);
        deferred.resolve(response, 'success', xhr);
        if (typeof options.complete === 'function') options.complete(xhr, 'success');
    }, 10);
    return xhr;
};
'@
$html = @"
<!doctype html><html><head><meta charset="utf-8">
<meta http-equiv="Content-Security-Policy" content="connect-src 'none';">
<title>Offline inventory browser regression</title></head>
<body data-manage-inventory="1" data-restock-inventory="1">
$renderedView
$($assetTags -join [Environment]::NewLine)
<script>$fixtures</script><script src="$inventoryUri"></script>
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
        $state = Evaluate-Js '(() => { const h = window.__inventoryHarness; return h ? {ready: document.readyState === "complete" && !!window.jQuery && $.fn.dataTable.isDataTable("#inventory-table") && $("#inventory-table").DataTable().rows().count() === 3, errors: h.errors} : null; })()'
        if ($state -and $state.errors.Count) { throw ('Inventory browser errors: ' + ($state.errors -join '; ')) }
        if ($state -and $state.ready) { $ready = $true; break }
        Start-Sleep -Milliseconds 100
    }
    if (-not $ready) { throw 'The offline inventory DataTable did not load.' }
    $checks = @'
(async () => {
    const h = __inventoryHarness;
    function assert(condition, message) { if (!condition) throw new Error(message); }
    function row(name) {
        const found = Array.from(document.querySelectorAll('#inventory-table tbody tr'))
            .find(row => row.querySelector('td') && row.querySelector('td').textContent === name);
        assert(found, 'Inventory row not rendered: ' + name);
        return Array.from(found.querySelectorAll('td'));
    }
    function safeText() {
        const cells = row(h.hostileName);
        assert(cells[0].textContent === h.hostileName && cells[2].textContent === h.hostileUnit,
            'Inventory name and unit must display hostile markup as literal text.');
        assert(!document.querySelector('#inventory-table img, #inventory-table [data-injected]')
            && !window.__inventoryInjected, 'Inventory text created injected markup or executed code.');
    }
    const tiny = new Intl.NumberFormat(undefined, {minimumFractionDigits: 3, maximumFractionDigits: 3}).format(0.001);
    assert(row('Trace stock')[1].textContent.trim() === tiny, 'Stock 0.001 was rounded away or lost its third decimal.');
    assert(/^0(?:[.,]0{1,3})?$/.test(row('Zero stock')[1].textContent.trim()), 'Zero stock must remain visible.');
    const minimum = document.querySelector('#min_stock');
    assert(minimum && minimum.type === 'number' && Number(minimum.value) === 10,
        'The minimum-stock input with its default threshold is missing.');
    assert(minimum.min === '0' && minimum.max === '999999999.999' && minimum.step === '0.001',
        'Minimum stock must support nonnegative quantities with three decimal places.');
    const headings = Array.from(document.querySelectorAll('#inventory-table thead th')).map(cell => cell.textContent.trim());
    assert(headings[3] === 'Minimum stock' && headings[4] === 'Status', 'Minimum stock and status columns are missing.');
    assert(row('Trace stock')[3].textContent.trim() === tiny && row('Trace stock')[4].textContent.trim() === 'Low stock',
        'Stock equal to its positive threshold must display Low stock.');
    assert(/^0(?:[.,]0{1,3})?$/.test(row('Zero stock')[3].textContent.trim())
        && row('Zero stock')[4].textContent.trim() === 'Low stock', 'Zero stock at a zero threshold must display Low stock.');
    assert(/^1(?:[.,]0{1,3})?$/.test(row(h.hostileName)[3].textContent.trim())
        && row(h.hostileName)[4].textContent.trim() === 'In stock', 'An item above its own threshold must display In stock.');
    safeText();
    assert(h.requests.length === 1, 'Initial inventory load must make one list request.');
    const refresh = document.querySelector('#btnRefreshInventory');
    assert(refresh && !refresh.disabled, 'An enabled inventory Refresh button is missing.');
    refresh.click();
    const updated = new Intl.NumberFormat(undefined, {minimumFractionDigits: 3, maximumFractionDigits: 3}).format(2.345);
    for (let attempt = 0; attempt < 100; attempt++) {
        if (h.requests.length === 2 && row('Trace stock')[1].textContent.trim() === updated) break;
        await new Promise(resolve => setTimeout(resolve, 20));
    }
    assert(h.requests.length === 2 && row('Trace stock')[1].textContent.trim() === updated,
        'Refresh must fetch and display the latest inventory quantity.');
    assert(row('Trace stock')[3].textContent.trim() === tiny && row('Trace stock')[4].textContent.trim() === 'In stock',
        'Refresh must update stock status while preserving the item threshold.');
    safeText();
    assert(!h.errors.length && h.requests.every(request => request.method === 'GET' && request.path === '/api/Inventory/all'),
        'History must not be requested until it is opened: ' + h.errors.join('; '));
    async function waitFor(condition, message) {
        for (let attempt = 0; attempt < 100; attempt++) {
            if (condition()) return;
            await new Promise(resolve => setTimeout(resolve, 20));
        }
        throw new Error(message);
    }
    function historyRequests() { return h.requests.filter(request => request.path === '/api/Inventory/history'); }
    function lastHistoryRequest() { return historyRequests()[historyRequests().length - 1].data; }
    function closeHistory() {
        return new Promise(resolve => $('#inventory-history-modal').one('hidden.bs.modal', resolve).modal('hide'));
    }
    const historyButton = document.querySelector('#btnInventoryHistory');
    assert(historyButton && !historyButton.disabled && $('#inventory-table .btn-history').length === 3,
        'Global and per-item inventory history actions are missing.');
    historyButton.click();
    await waitFor(() => $.fn.dataTable.isDataTable('#inventory-history-table')
        && $('#inventory-history-table').DataTable().rows().count() === 10, 'All inventory history did not load.');
    const history = $('#inventory-history-table').DataTable();
    assert(history.settings()[0].oFeatures.bServerSide && !lastHistoryRequest().inv_id
        && Number(lastHistoryRequest().start) === 0 && Number(lastHistoryRequest().length) === 10,
        'History must request its first server-side page without an item filter.');
    assert(lastHistoryRequest().draw > 0 && !history.settings()[0].oFeatures.bSort
        && lastHistoryRequest().order.length === 0, 'History must retain the server newest-first order and send its DataTables draw.');
    assert($('#inventory-history-table_filter input').attr('maxlength') === '150', 'History search must enforce the server search length limit.');
    const historyText = $('#inventory-history-table tbody').text();
    assert(historyText.includes('-0.001') && historyText.includes('+2.345') && historyText.includes('2.346')
        && historyText.includes('2.345') && historyText.includes('Sale #77'), 'History lost signed stock precision, before/after values, or sale reference.');
    assert(historyText.includes('Details updated') && historyText.includes('Stock unchanged')
        && historyText.includes('<b data-injected="old-name">Old name</b>'), 'Metadata-only adjustments must explain the change without changing stock.');
    assert(historyText.includes(h.hostileName) && historyText.includes(h.hostileUnit)
        && historyText.includes('<b data-injected="actor">Cashier</b>')
        && !$('#inventory-history-table img, #inventory-history-table [data-injected]').length
        && !window.__inventoryInjected, 'History text or metadata created unsafe HTML.');
    assert($('#inventory-history-modal').text().includes('Earlier stock changes are not included'),
        'History must explain that earlier changes are not recorded retrospectively.');
    history.page('next').draw('page');
    await waitFor(() => Number(lastHistoryRequest().start) === 10 && history.rows().count() === 7,
        'History pagination did not request the next server page.');
    history.search('Retired ingredient').draw();
    await waitFor(() => history.rows().count() === 1 && history.row(0).data().inv_id === 9,
        'Server-side history search did not find a deleted inventory item.');
    assert(lastHistoryRequest().search.value === 'Retired ingredient' && Number(lastHistoryRequest().start) === 0,
        'History search must send its term and reset pagination.');
    history.search('');
    $('#history-movement-type').val('sale').trigger('change');
    await waitFor(() => history.rows().count() === 1 && history.row(0).data().movement_type === 'sale',
        'The movement-type filter did not restrict history.');
    assert(lastHistoryRequest().movement_type === 'sale', 'The movement type was not sent to the history endpoint.');
    const beforeError = historyRequests().length;
    h.historyResponses.push({failure: {status: 503, responseJSON: {message: '<b data-injected="error">History unavailable</b>'}}});
    $('#btnRefreshInventoryHistory').trigger('click');
    await waitFor(() => historyRequests().length > beforeError && !$('#inventory-history-error').prop('hidden')
        && history.rows().count() === 0, 'History failure did not clear stale records and show an error.');
    assert($('#inventory-history-error').text() === '<b data-injected="error">History unavailable</b>'
        && !$('#inventory-history-error [data-injected]').length, 'History server errors must render as safe text.');
    $('#btnRefreshInventoryHistory').trigger('click');
    await waitFor(() => $('#inventory-history-error').prop('hidden') && history.rows().count() === 1,
        'Refreshing history did not recover after a failed request.');
    history.search('No such movement').draw();
    await waitFor(() => history.rows().count() === 0 && $('#inventory-history-table tbody').text().includes('No matching history'),
        'A history search with no results needs an empty state.');
    await closeHistory();
    const beforeItem = historyRequests().length;
    $('#inventory-table .btn-history[data-id="3"]').trigger('click');
    await waitFor(() => historyRequests().length > beforeItem && history.rows().count() === 1
        && history.row(0).data().inv_id === 3, 'Per-item history did not send the inventory item filter.');
    assert(Number(lastHistoryRequest().inv_id) === 3 && !lastHistoryRequest().movement_type
        && lastHistoryRequest().search.value === '', 'Opening an item must reset old search and movement filters.');
    assert($('#inventory-history-context').text() === 'Item: ' + h.hostileName
        && !$('#inventory-history-context img').length, 'History item context must render its name safely.');
    const beforeRefresh = historyRequests().length;
    h.history.unshift({history_id: 21, inv_id: 3, inv_name: h.hostileName, unit: h.hostileUnit,
        movement_type: 'restock', amount_before: '1.234', quantity_change: '0.001', amount_after: '1.235',
        actor_name: 'Owner', sale_id: null, created_at: '2026-10-25 13:00:00'});
    $('#btnRefreshInventoryHistory').trigger('click');
    await waitFor(() => historyRequests().length > beforeRefresh && history.rows().count() === 2,
        'Refresh did not fetch the newest item history.');
    assert(Number(lastHistoryRequest().inv_id) === 3 && $('#inventory-history-table tbody').text().includes('+0.001'),
        'Refresh lost the item context or tiny new stock movement.');
    await closeHistory();
    historyButton.click();
    await waitFor(() => !lastHistoryRequest().inv_id && history.rows().count() === 10,
        'Reopening global history retained the previous item filter.');
    await closeHistory();
    // Viewing history remains available without inventory mutation permissions.
    $('body').data('manage-inventory', false).data('restock-inventory', false);
    const beforeReadOnly = h.listFetches;
    refresh.click();
    await waitFor(() => h.listFetches > beforeReadOnly && $('#inventory-table .btn-history').length === 3
        && !$('#inventory-table .btn-edit, #inventory-table .btn-delete, #inventory-table .btn-restock').length,
        'Read-only inventory viewers must retain each history action.');
    const beforeReadOnlyHistory = historyRequests().length;
    $('#inventory-table .btn-history[data-id="1"]').trigger('click');
    await waitFor(() => historyRequests().length > beforeReadOnlyHistory && history.rows().count() === 3,
        'A read-only inventory viewer could not open item history.');
    assert(!h.errors.length && h.requests.every(request => request.method === 'GET'
        && ['/api/Inventory/all', '/api/Inventory/history'].includes(request.path)),
        'The browser must use only offline inventory/history GET fixtures: ' + h.errors.join('; '));
    return 'Inventory browser checks passed: stock precision/status, safe text, global/item history, server search/paging/filter, metadata details, error/empty recovery, refresh, and read-only history access. No CRUD or external requests.';
})()
'@
    Write-Output (Evaluate-Js $checks)
} finally {
    if ($socket.State -eq [Net.WebSockets.WebSocketState]::Open) {
        try { $null = Send-Cdp 'Browser.close' } catch { }
    }
    $socket.Dispose()
    if ($browserProcess -and -not $browserProcess.HasExited) { Stop-Process -Id $browserProcess.Id -ErrorAction SilentlyContinue }
    Write-Output ('Offline inventory artifacts: ' + $artifactDirectory)
}
