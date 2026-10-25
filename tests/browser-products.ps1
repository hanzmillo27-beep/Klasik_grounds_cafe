param([string]$BrowserPath = '', [string]$PhpPath = '', [int]$DebugPort = 0)

# Offline regression for the actual products PHP view, DataTables, and Products.js.
# The renderer requires the isolated SQLite test fixture, never app configuration.
# Product list/delete/status/availability requests use offline fixtures only.
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
$artifactDirectory = Join-Path $env:TEMP ('klasik-products-browser-' + [guid]::NewGuid().ToString('N'))
$null = New-Item -ItemType Directory -Path $artifactDirectory
$htmlPath = Join-Path $artifactDirectory 'products.html'
$rendererPath = Join-Path $artifactDirectory 'render-products.php'
$profilePath = Join-Path $artifactDirectory 'edge-profile'
$fixturePath = (Join-Path $projectRoot 'tests/product_fixture.php').Replace('\', '/').Replace("'", "\'")
$viewPath = (Join-Path $projectRoot 'app/views/Products/index.php').Replace('\', '/').Replace("'", "\'")
$phpRenderer = "<?php require '" + $fixturePath + "'; require '" + $viewPath + "';"
[IO.File]::WriteAllText($rendererPath, $phpRenderer, [Text.UTF8Encoding]::new($false))
$renderedView = (& $PhpPath -f $rendererPath | Out-String)
if ($LASTEXITCODE -ne 0) { throw 'Rendering the products view with the isolated PHP fixture failed.' }

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
$productsUri = ([uri](Join-Path $projectRoot 'public/js/Products.js')).AbsoluteUri
$styleTags = foreach ($relativePath in @(
    'public/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css',
    'public/plugins/datatables-responsive/css/responsive.bootstrap4.min.css',
    'public/dist/css/adminlte.min.css',
    'public/css/cafe-theme.css'
)) {
    $assetPath = Join-Path $projectRoot $relativePath
    if (-not (Test-Path -LiteralPath $assetPath)) { throw ('Missing browser style: ' + $relativePath) }
    '<link rel="stylesheet" href="' + ([uri]$assetPath).AbsoluteUri + '">'
}
$fixtures = @'
window.base_url = 'http://products-fixture.invalid';
window.__productsHarness = {
    errors: [], requests: [], messages: [], dialogs: [], confirmations: [], deleteResponses: [],
    settledDialogs: 0, listCompleted: 0,
    hostileName: '<img src=x onerror="window.__productInjected=true">'
};
const h = __productsHarness;
h.products = Array.from({length: 16}, (_, index) => ({product_id: index + 1,
    product_name: index === 0 ? h.hostileName : index === 1 ? 'Inactive Espresso' : 'Product ' + String(index + 1).padStart(2, '0'),
    category_id: 1, category_name: 'Coffee', product_price: 125, product_image_url: null,
    is_active: index !== 1, is_available: true, can_order: index !== 1}));
window.addEventListener('error', event => h.errors.push(event.message || 'Browser resource error.'));
window.addEventListener('unhandledrejection', event => h.errors.push(String(event.reason)));
window.alert = message => h.errors.push('Unexpected browser alert: ' + message);
window.msgBox = (status, message) => h.messages.push({status: status, message: message});
window.handleAjaxError = xhr => h.messages.push({status: 'error', message: xhr.responseJSON && xhr.responseJSON.message || 'Request failed.'});
window.Swal = {fire: function (options) {
    h.dialogs.push(options);
    const fixture = h.confirmations.shift() || {isConfirmed: true};
    return new Promise(resolve => setTimeout(function () {
        h.settledDialogs++;
        resolve({isConfirmed: fixture.isConfirmed !== false});
    }, fixture.delay || 0));
}};
$.ajax = function (options) {
    const path = new URL(options.url).pathname, method = String(options.type || 'GET').toUpperCase();
    h.requests.push({path: path, method: method, data: options.data || null});
    function requireFixture(condition, message) { if (!condition) { h.errors.push(message); throw new Error(message); } }
    let response, fixture = {}, beforeResolve = null;
    if (path === '/api/Products/all' && method === 'GET') response = {data: JSON.parse(JSON.stringify(h.products))};
    else if (path === '/api/Products/categories' && method === 'GET') response = {data: [{category_id: 1, category_name: 'Coffee'}]};
    else if (path === '/api/Products/inventory' && method === 'GET') response = {data: []};
    else if (/^\/api\/Products\/delete\/(1|2|3|6)$/.test(path) && method === 'POST') {
        const id = Number(path.split('/').pop());
        requireFixture(h.products.some(product => product.product_id === id), 'The fixture product was already removed.');
        fixture = h.deleteResponses.shift() || {};
        response = fixture.response || {status: 'success', message: 'Product deleted. Sales history was kept.'};
        if (!fixture.failure && response.status === 'success') beforeResolve = function () {
            h.products = h.products.filter(product => product.product_id !== id);
        };
        if (fixture.delay == null) fixture.delay = 100;
    } else if (/^\/api\/Products\/status\/3$/.test(path) && method === 'POST') {
        const product = h.products.find(product => product.product_id === 3);
        requireFixture([0, 1].includes(Number(options.data.is_active)), 'Status must submit its active flag.');
        product.is_active = !!Number(options.data.is_active); product.can_order = product.is_active && product.is_available;
        response = {status: 'success', message: 'Product status updated.'};
    } else if (path === '/api/Products/availability/3' && method === 'POST') {
        const product = h.products.find(product => product.product_id === 3);
        requireFixture([0, 1].includes(Number(options.data.is_available)), 'Availability must submit its availability flag.');
        product.is_available = !!Number(options.data.is_available); product.can_order = product.is_active && product.is_available;
        response = {status: 'success', message: 'Product availability updated.'};
    } else {
        h.errors.push('Disallowed request: ' + method + ' ' + path);
        throw new Error('Only allowlisted offline Products fixtures may be requested.');
    }
    const deferred = $.Deferred(), xhr = deferred.promise();
    xhr.readyState = 1;
    xhr.abort = function () { xhr.readyState = 0; deferred.reject(xhr, 'abort'); };
    setTimeout(function () {
        if (xhr.readyState === 0) return;
        xhr.readyState = 4;
        if (fixture.failure) {
            Object.assign(xhr, fixture.failure);
            if (options.error) options.error(xhr, 'error');
            deferred.reject(xhr, 'error');
        } else {
            if (beforeResolve) beforeResolve();
            if (path === '/api/Products/all') h.listCompleted++;
            if (options.success) options.success(response, 'success', xhr);
            deferred.resolve(response, 'success', xhr);
        }
        if (options.complete) options.complete(xhr);
    }, fixture.delay || 10);
    return xhr;
};
'@
$html = @"
<!doctype html><html><head><meta charset="utf-8">
<meta http-equiv="Content-Security-Policy" content="connect-src 'none';">
<title>Offline products browser regression</title>
$($styleTags -join [Environment]::NewLine)
</head>
<body data-manage-products="1">
$renderedView
$($assetTags -join [Environment]::NewLine)
<script>$fixtures</script><script src="$productsUri"></script>
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
        $state = Evaluate-Js '(() => { const h = window.__productsHarness; return h ? {ready: document.readyState === "complete" && $.fn.dataTable.isDataTable("#products-table") && $("#products-table").DataTable().rows().count() === 16, errors: h.errors} : null; })()'
        if ($state -and $state.errors.Count) { throw ('Products browser errors: ' + ($state.errors -join '; ')) }
        if ($state -and $state.ready) { $ready = $true; break }
        Start-Sleep -Milliseconds 100
    }
    if (-not $ready) { throw 'The offline Products DataTable did not load.' }
    $checks = @'
(async () => {
    const h = __productsHarness, table = $('#products-table').DataTable();
    function assert(condition, message) { if (!condition) throw new Error(message); }
    async function waitFor(condition, message) {
        for (let attempt = 0; attempt < 100; attempt++) {
            if (condition()) return;
            await new Promise(resolve => setTimeout(resolve, 20));
        }
        throw new Error(message);
    }
    function button(id, action = 'delete') { return document.querySelector('#products-table .btn-' + action + '[data-id="' + id + '"]'); }
    function deleteCount() { return h.requests.filter(request => request.path.includes('/delete/')).length; }
    function click(id, action = 'delete') { const control = button(id, action); assert(control, 'Missing product action: ' + id + '/' + action); control.click(); }
    function rowData(id) { return table.rows().data().toArray().find(product => product.product_id === id); }
    table.order([]).page.len(5).draw();
    assert(button(1) && button(2) && button(1).classList.contains('btn-danger') && button(1).textContent.trim() === 'Delete',
        'Owner must have a red Delete action for active and inactive products.');
    assert(button(1, 'status').textContent.trim() === 'Deactivate' && button(2, 'status').textContent.trim() === 'Reactivate',
        'Delete must retain the existing Deactivate and Reactivate actions.');
    assert($('#products-table tbody').text().includes(h.hostileName) && !$('#products-table img').length && !window.__productInjected,
        'Product names must render as safe table text.');
    const beforeCancel = deleteCount(), beforeDialogs = h.dialogs.length, beforeSettled = h.settledDialogs;
    h.confirmations.push({isConfirmed: false, delay: 80});
    click(1);
    const dialog = h.dialogs[h.dialogs.length - 1];
    assert(dialog.title === 'Delete product?' && dialog.confirmButtonText === 'Delete product' && !dialog.html
        && dialog.text.includes(h.hostileName) && dialog.text.includes('catalog') && dialog.text.includes('sales history'),
        'Delete confirmation must use the product name as text and explain catalog removal and retained sales history.');
    assert(Array.from(document.querySelectorAll('#products-table button[data-id="1"]')).every(control => control.disabled),
        'Product controls must lock while its Delete confirmation is pending.');
    const requestsBeforeGuards = h.requests.length;
    for (const action of ['delete', 'status', 'availability', 'edit']) $(button(1, action)).prop('disabled', false).trigger('click');
    assert(h.dialogs.length === beforeDialogs + 1 && h.requests.length === requestsBeforeGuards,
        'A forced same-product action escaped the pending Delete guard.');
    await waitFor(() => h.settledDialogs > beforeSettled && !button(1).disabled, 'Cancelling Delete did not restore product controls.');
    assert(deleteCount() === beforeCancel && rowData(1), 'Cancelling Delete sent a request or removed its product.');
    const beforeFailure = deleteCount(), beforeFailureRefresh = h.listCompleted, beforeMessages = h.messages.length;
    h.deleteResponses.push({failure: {status: 409, responseJSON: {message: 'This product is referenced by a protected order.'}}});
    click(3);
    await waitFor(() => deleteCount() === beforeFailure + 1, 'Confirmed Delete did not POST its product ID.');
    const requestDialogs = h.dialogs.length;
    $(button(3)).prop('disabled', false).trigger('click');
    assert(deleteCount() === beforeFailure + 1 && h.dialogs.length === requestDialogs, 'A pending deletion allowed another confirmation or POST.');
    await waitFor(() => h.listCompleted > beforeFailureRefresh && button(3) && !button(3).disabled,
        'Failed Delete did not refresh and restore the existing row.');
    assert(rowData(3) && table.rows().count() === 16
        && h.messages.slice(beforeMessages).some(message => message.status === 'error' && message.message.includes('protected order')),
        'A rejected deletion must retain the product and show the server reason.');
    const beforeBodyError = deleteCount(), beforeBodyRefresh = h.listCompleted;
    h.deleteResponses.push({response: {status: 'error', message: 'Product could not be deleted.'}});
    click(3);
    await waitFor(() => deleteCount() === beforeBodyError + 1 && h.listCompleted > beforeBodyRefresh, 'A failed JSON deletion did not finish.');
    assert(rowData(3) && table.rows().count() === 16, 'An unsuccessful JSON response removed a product.');
    // Existing status and availability actions retain their original endpoints/behavior.
    const beforeDeactivate = h.listCompleted;
    click(3, 'status');
    await waitFor(() => h.listCompleted > beforeDeactivate && button(3, 'status').textContent.trim() === 'Reactivate', 'Deactivate no longer changes only product status.');
    assert(rowData(3) && !rowData(3).is_active, 'Deactivation removed its product.');
    const beforeReactivate = h.listCompleted;
    click(3, 'status');
    await waitFor(() => h.listCompleted > beforeReactivate && button(3, 'status').textContent.trim() === 'Deactivate', 'Reactivate no longer restores the product.');
    const beforeAvailability = h.listCompleted;
    click(3, 'availability');
    await waitFor(() => h.listCompleted > beforeAvailability && !rowData(3).is_available, 'Availability settings no longer work.');
    table.page(1).draw('page');
    assert(button(6), 'The second product page did not render.');
    const beforeActiveDelete = deleteCount(), beforeActiveRefresh = h.listCompleted;
    click(6);
    await waitFor(() => deleteCount() === beforeActiveDelete + 1 && h.listCompleted > beforeActiveRefresh && !rowData(6),
        'Successful active-product Delete did not refresh and remove its catalog row.');
    assert(table.rows().count() === 15 && table.page.info().page === 1, 'Delete must retain the current DataTable page.');
    table.page(0).draw('page');
    const beforeInactiveDelete = deleteCount(), beforeInactiveRefresh = h.listCompleted;
    click(2);
    await waitFor(() => deleteCount() === beforeInactiveDelete + 1 && h.listCompleted > beforeInactiveRefresh && !rowData(2),
        'Successful inactive-product Delete did not remove its catalog row.');
    assert(table.rows().count() === 14 && h.messages.some(message => message.status === 'success' && message.message.includes('deleted')),
        'Successful Delete must report success and leave the other products intact.');
    $('body').data('manage-products', false);
    const beforeReadOnly = h.listCompleted;
    table.ajax.reload(null, false);
    await waitFor(() => h.listCompleted > beforeReadOnly && !$('#products-table .btn-delete').length,
        'Read-only users were given Delete controls.');
    assert(!$('#products-table .btn-status, #products-table .btn-availability, #products-table .btn-edit').length,
        'Read-only product action visibility regressed.');
    const beforeForcedReadOnly = deleteCount(), beforeReadOnlyDialogs = h.dialogs.length;
    $('#products-table tbody tr:first td:last').append('<button class="btn-delete" data-id="1">Injected control</button>');
    $('#products-table .btn-delete').trigger('click');
    assert(deleteCount() === beforeForcedReadOnly && h.dialogs.length === beforeReadOnlyDialogs,
        'A forced read-only Delete action opened a dialog or sent a request.');
    assert(!h.errors.length && h.requests.filter(request => request.path.includes('/delete/')).every(request => request.method === 'POST'),
        'Product deletion used a disallowed request or raised browser errors: ' + h.errors.join('; '));
    return 'Products browser passed: safe owner Delete UI, cancel/confirmation, busy guards, HTTP/JSON errors, active/inactive deletion, page-preserving refresh, read-only access, and unchanged status/availability actions.';
})()
'@
    Write-Output (Evaluate-Js $checks)
} finally {
    if ($socket.State -eq [Net.WebSockets.WebSocketState]::Open) {
        try { $null = Send-Cdp 'Browser.close' } catch { }
    }
    $socket.Dispose()
    if ($browserProcess -and -not $browserProcess.HasExited) { Stop-Process -Id $browserProcess.Id -ErrorAction SilentlyContinue }
    Write-Output ('Offline products artifacts: ' + $artifactDirectory)
}
