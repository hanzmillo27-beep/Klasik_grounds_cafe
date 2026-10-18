param(
    [string]$BrowserPath = '',
    [string]$PhpPath = '',
    [int]$DebugPort = 0
)

# Offline browser regression for the real POS view and JavaScript.
# All AJAX is stubbed. Checkout and unrecognized requests fail immediately;
# this script never loads app/config/init.php or connects to an application DB.
$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if (-not $BrowserPath) {
    $BrowserPath = @(
        'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
        'C:\Program Files\Microsoft\Edge\Application\msedge.exe'
    ) | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
}
if (-not $BrowserPath -or -not (Test-Path -LiteralPath $BrowserPath)) {
    throw 'Microsoft Edge was not found. Supply -BrowserPath.'
}
if (-not $PhpPath) {
    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCommand) { $PhpPath = $phpCommand.Source }
    elseif (Test-Path -LiteralPath 'C:\xampp\php\php.exe') { $PhpPath = 'C:\xampp\php\php.exe' }
}
if (-not $PhpPath -or -not (Test-Path -LiteralPath $PhpPath)) {
    throw 'PHP CLI was not found. Supply -PhpPath.'
}
if ($DebugPort -eq 0) {
    $listener = New-Object Net.Sockets.TcpListener([Net.IPAddress]::Loopback, 0)
    $listener.Start()
    try { $DebugPort = $listener.LocalEndpoint.Port } finally { $listener.Stop() }
}
if ($DebugPort -lt 1 -or $DebugPort -gt 65535) { throw 'Invalid -DebugPort.' }

$artifactDirectory = Join-Path $env:TEMP ('klasik-pos-browser-' + [guid]::NewGuid().ToString('N'))
$null = New-Item -ItemType Directory -Path $artifactDirectory
$htmlPath = Join-Path $artifactDirectory 'pos.html'
$rendererPath = Join-Path $artifactDirectory 'render-pos.php'
$profilePath = Join-Path $artifactDirectory 'edge-profile'
$viewPath = (Join-Path $projectRoot 'app/views/POS/index.php').Replace('\', '/').Replace("'", "\'")
Set-Content -LiteralPath $rendererPath -Encoding UTF8 -Value ("<?php require '" + $viewPath + "';")
$renderedView = (& $PhpPath -f $rendererPath | Out-String)
if ($LASTEXITCODE -ne 0) { throw 'Rendering the POS view with PHP failed.' }

function Asset-Uri([string]$relativePath) {
    $assetPath = Join-Path $projectRoot $relativePath
    if (-not (Test-Path -LiteralPath $assetPath)) { throw ('Missing browser asset: ' + $relativePath) }
    return ([uri]$assetPath).AbsoluteUri
}
$jqueryUri = Asset-Uri 'public/plugins/jquery/jquery.min.js'
$bootstrapUri = Asset-Uri 'public/plugins/bootstrap/js/bootstrap.bundle.min.js'
$posUri = Asset-Uri 'public/js/POS.js'
$fixtures = @'
window.base_url = 'http://pos-fixture.invalid';
window.__posHarness = {
    generation: String(Date.now()) + Math.random(),
    errors: [], requests: [], messages: [],
    products: [
        {product_id: 1, product_name: 'Current Americano', product_price: 999, category_name: 'Coffee'},
        {product_id: 2, product_name: 'Current Latte', product_price: 160, category_name: 'Coffee'},
        {product_id: 3, product_name: 'Cookie', product_price: 50, category_name: 'Food'},
        {product_id: 4, product_name: 'Fraction A', product_price: 0.10, category_name: 'Test fractions'},
        {product_id: 5, product_name: 'Fraction B', product_price: 0.20, category_name: 'Test fractions'}
    ],
    order: {
        kiosk_order_id: 42, reference_code: 'KG-BROWSER', status: 'claimed',
        customer_name: 'Kiosk customer', total_amount: 391,
        items: [
            {product_id: 1, product_name: 'Original Americano', unit_price: 125.50, quantity: 2, subtotal: 251},
            {product_id: 2, product_name: 'Original Latte', unit_price: 140, quantity: 1, subtotal: 140}
        ]
    }
};
window.addEventListener('error', function (event) {
    __posHarness.errors.push(event.message || 'A browser resource failed to load.');
});
window.addEventListener('unhandledrejection', function (event) {
    __posHarness.errors.push(String(event.reason));
});
window.msgBox = function (status, message) { __posHarness.messages.push({status: status, message: message}); };
window.handleAjaxError = function () { __posHarness.errors.push('Unexpected AJAX failure.'); };
// Confirm New order deterministically; the test exercises POS behavior, not SweetAlert.
window.Swal = {fire: function () { return Promise.resolve({isConfirmed: true}); }};
$.ajax = function (options) {
    var deferred = $.Deferred(), path = new URL(options.url).pathname;
    __posHarness.requests.push({path: path, method: options.type || 'GET'});
    var response, delay = 0;
    if (path === '/api/POS/products') response = {data: __posHarness.products};
    else if (path === '/api/POS/transactions') response = {data: []};
    else if (path === '/api/Kiosk/claim/KG-BROWSER') {
        response = {status: 'success', data: __posHarness.order};
        delay = 100;
    } else {
        __posHarness.errors.push('Disallowed request: ' + path);
        throw new Error('Only offline menu, history, and claim fixtures may be requested: ' + path);
    }
    setTimeout(function () { deferred.resolve(JSON.parse(JSON.stringify(response))); }, delay);
    return deferred.promise();
};
'@
$html = @"
<!doctype html>
<html><head><meta charset="utf-8">
<meta http-equiv="Content-Security-Policy" content="connect-src 'none';">
<title>Offline POS browser regression</title></head><body>
$renderedView
<script src="$jqueryUri"></script>
<script src="$bootstrapUri"></script>
<script>$fixtures</script>
<script src="$posUri"></script>
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
function Wait-PosReady([string]$previousGeneration = '') {
    for ($attempt = 0; $attempt -lt 100; $attempt++) {
        try {
            $state = Evaluate-Js '(() => { const h = window.__posHarness; return h ? {generation: h.generation, ready: document.readyState === "complete" && document.querySelectorAll(".btn-add-product").length === h.products.length, errors: h.errors} : null; })()'
            if ($state -and $state.errors.Count) { throw ('POS browser errors: ' + ($state.errors -join '; ')) }
            if ($state -and $state.ready -and $state.generation -ne $previousGeneration) { return $state.generation }
        } catch {
            if ($_.Exception.Message -like 'POS browser errors:*') { throw }
            if ($attempt -eq 99) { throw }
        }
        Start-Sleep -Milliseconds 100
    }
    throw 'The offline POS page did not become ready.'
}

$assertions = @'
const h = window.__posHarness;
function assert(condition, message) { if (!condition) throw new Error(message); }
function click(selector) {
    const button = document.querySelector(selector);
    assert(button, 'Missing control: ' + selector);
    button.click();
}
function cart() { return JSON.parse(localStorage.getItem('pos_cart') || '[]'); }
function assertCart(expected, total) {
    const actual = cart();
    assert(actual.length === expected.length, 'Cart line count differs: ' + JSON.stringify(actual));
    expected.forEach(function (item) {
        const found = actual.find(x => Number(x.product_id) === item[0]);
        assert(found && Number(found.quantity) === item[1] && Number(found.product_price) === item[2],
            'Unexpected cart item: ' + JSON.stringify({expected: item, actual: found}));
        assert(document.querySelector('#cart-list').textContent.includes(found.product_name), 'Cart item name is not displayed.');
    });
    assert(document.querySelectorAll('#cart-list .btn-remove').length === expected.length, 'Visible cart does not match persisted cart.');
    const displayed = Number(document.querySelector('#cart-total').textContent.replace(/[^0-9.-]/g, ''));
    assert(Math.abs(displayed - total) < 0.001, 'Displayed total differs: ' + displayed + ' versus ' + total);
    assert(document.querySelector('#cart-total').textContent.endsWith(total.toFixed(2)), 'Total must display exactly two decimal places.');
}
function assertOffline() {
    assert(!h.errors.length, 'Browser errors: ' + h.errors.join('; '));
    assert(!h.requests.some(x => x.path.includes('checkout')), 'Checkout must never be requested.');
}
'@

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
    $generation = Wait-PosReady
    $phaseOne = @'
assertCart([], 0);
click('.btn-add-product[data-id="4"]');
click('.btn-increase[data-id="4"]');
click('.btn-increase[data-id="4"]');
click('.btn-add-product[data-id="5"]');
assertCart([[4, 3, 0.10], [5, 1, 0.20]], 0.50);
click('.btn-increase[data-id="5"]');
click('.btn-increase[data-id="5"]');
assertCart([[4, 3, 0.10], [5, 3, 0.20]], 0.90);
click('.btn-decrease[data-id="4"]');
click('.btn-decrease[data-id="4"]');
assertCart([[4, 1, 0.10], [5, 3, 0.20]], 0.70);
click('.btn-remove[data-id="4"]');
assertCart([[5, 3, 0.20]], 0.60);
click('.btn-remove[data-id="5"]');
assertCart([], 0);
click('.btn-add-product[data-id="1"]');
click('.btn-increase[data-id="1"]');
click('.btn-add-product[data-id="2"]');
assertCart([[1, 2, 999], [2, 1, 160]], 2158);
click('.btn-decrease[data-id="1"]');
assertCart([[1, 1, 999], [2, 1, 160]], 1159);
click('.btn-remove[data-id="1"]');
assertCart([[2, 1, 160]], 160);
click('.btn-remove[data-id="2"]');
assertCart([], 0);
click('.btn-add-product[data-id="1"]');
click('.btn-decrease[data-id="1"]');
assertCart([], 0);
$('#customer_name').val('Stale name').trigger('input');
$('#kiosk-reference-input').val('KG-BROWSER');
click('#btn-load-kiosk-order');
click('.btn-add-product[data-id="3"]');
click('#btn-load-kiosk-order');
assertCart([], 0);
assert(h.requests.filter(x => x.path.includes('/claim/')).length === 1, 'Duplicate kiosk retrieval was allowed.');
for (let attempt = 0; attempt < 100 && cart().length !== 2; attempt++) await new Promise(r => setTimeout(r, 20));
assertCart([[1, 2, 125.5], [2, 1, 140]], 391);
assert($('#customer_name').val() === 'Kiosk customer', 'Kiosk customer was not displayed.');
assert(localStorage.getItem('pos_customer_name') === 'Kiosk customer', 'Retrieved customer was not persisted.');
click('.btn-remove[data-id="1"]');
assertCart([[2, 1, 140]], 140);
click('.btn-add-product[data-id="1"]');
assertCart([[1, 1, 125.5], [2, 1, 140]], 265.5);
assert(cart().find(x => x.product_id === 1).product_name === 'Original Americano', 'Re-added kiosk product lost its historical name.');
click('.btn-increase[data-id="1"]');
assertCart([[1, 2, 125.5], [2, 1, 140]], 391);
click('.btn-decrease[data-id="1"]');
assertCart([[1, 1, 125.5], [2, 1, 140]], 265.5);
click('.btn-remove[data-id="2"]');
click('.btn-remove[data-id="1"]');
assertCart([], 0);
assert(localStorage.getItem('pos_kiosk_order_id') === '42', 'Removing the final kiosk product lost the order context.');
click('.btn-add-product[data-id="1"]');
assertCart([[1, 1, 125.5]], 125.5);
$('#customer_name').val('Edited kiosk customer').trigger('input');
assertOffline();
return 'Decimal-price totals, normal and kiosk add, quantity, individual removal, last-item removal, historical re-add, and pending-claim protection passed.';
'@
    Write-Output (Evaluate-Js ('(async () => {' + $assertions + $phaseOne + '})()'))
    $null = Send-Cdp 'Page.reload' @{ignoreCache = $true}
    $null = Wait-PosReady $generation
    $phaseTwo = @'
assertCart([[1, 1, 125.5]], 125.5);
assert($('#customer_name').val() === 'Edited kiosk customer', 'Customer edit did not survive reload.');
assert(localStorage.getItem('pos_kiosk_order_id') === '42', 'Kiosk context did not survive reload.');
click('.btn-remove[data-id="1"]');
assertCart([], 0);
click('.btn-add-product[data-id="1"]');
assertCart([[1, 1, 125.5]], 125.5);
assert(cart()[0].product_name === 'Original Americano', 'Historical product name did not survive reload/re-add.');
click('.btn-remove[data-id="1"]');
click('.btn-add-product[data-id="3"]');
assertCart([[3, 1, 50]], 50);
click('#btnClear');
await new Promise(r => setTimeout(r, 0));
assertCart([], 0);
assert(!localStorage.getItem('pos_kiosk_order_id') && !localStorage.getItem('pos_kiosk_items'), 'New order retained kiosk metadata.');
assert(!localStorage.getItem('pos_customer_name') && !$('#customer_name').val(), 'New order retained a customer name.');
click('.btn-add-product[data-id="1"]');
assertCart([[1, 1, 999]], 999);
click('.btn-remove[data-id="1"]');
assertCart([], 0);
assertOffline();
return 'Reload preserves edited cart, kiosk context, historical price/name, and customer; New order clears context. No checkout request was made.';
'@
    Write-Output (Evaluate-Js ('(async () => {' + $assertions + $phaseTwo + '})()'))
    Write-Output 'POS browser regression passed using the real PHP view, jQuery, Bootstrap, and POS.js.'
} finally {
    if ($socket.State -eq [Net.WebSockets.WebSocketState]::Open) {
        try { $null = Send-Cdp 'Browser.close' } catch { }
    }
    $socket.Dispose()
    if ($browserProcess -and -not $browserProcess.HasExited) {
        Stop-Process -Id $browserProcess.Id -ErrorAction SilentlyContinue
    }
    # Retain the uniquely named fixture and browser profile for failed-run diagnosis.
    Write-Output ('Offline browser artifacts: ' + $artifactDirectory)
}
