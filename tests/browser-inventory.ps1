param([string]$BrowserPath = '', [string]$PhpPath = '', [int]$DebugPort = 0)

# Offline regression for the actual inventory PHP view, DataTables, and Inventory.js.
# The renderer requires the isolated SQLite test fixture, never app configuration.
# AJAX permits only the list GET; no form submission or CRUD endpoint is exercised.
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
    errors: [], requests: [], nextAmount: '2.345',
    hostileName: '<img src=x onerror="window.__inventoryInjected=true">',
    hostileUnit: '<b data-injected="unit">kg</b>'
};
window.addEventListener('error', event => __inventoryHarness.errors.push(event.message || 'Browser resource error.'));
window.addEventListener('unhandledrejection', event => __inventoryHarness.errors.push(String(event.reason)));
window.alert = message => __inventoryHarness.errors.push('Unexpected browser alert: ' + message);
window.msgBox = function () { __inventoryHarness.errors.push('Unexpected CRUD message.'); };
window.handleAjaxError = function () { __inventoryHarness.errors.push('Unexpected AJAX failure.'); };
$.ajax = function (options) {
    const h = __inventoryHarness, path = new URL(options.url).pathname;
    const method = String(options.type || options.method || 'GET').toUpperCase();
    h.requests.push({path: path, method: method});
    if (path !== '/api/Inventory/all' || method !== 'GET') {
        h.errors.push('Disallowed request: ' + method + ' ' + path);
        throw new Error('Only the offline inventory list GET is allowed.');
    }
    const deferred = $.Deferred(), xhr = deferred.promise();
    xhr.readyState = 1;
    xhr.abort = function () { xhr.readyState = 0; deferred.reject(xhr, 'abort', 'abort'); };
    const response = {data: [
        {inv_id: 1, inv_name: 'Trace stock', amount: h.requests.length === 1 ? '0.001' : h.nextAmount,
            unit: 'kg', min_stock: '0.001', is_low: h.requests.length === 1},
        {inv_id: 2, inv_name: 'Zero stock', amount: '0.000', unit: 'g', min_stock: '0.000', is_low: true},
        {inv_id: 3, inv_name: h.hostileName, amount: '1.234', unit: h.hostileUnit, min_stock: '1.000', is_low: false}
    ]};
    setTimeout(function () {
        xhr.readyState = 4;
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
        'The browser must use only the offline inventory list GET: ' + h.errors.join('; '));
    return 'Inventory browser checks passed: stock precision, per-item thresholds, equality/zero/above-threshold status, safe text, and Refresh. No CRUD or external requests.';
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
