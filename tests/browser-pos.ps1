param(
    [string]$BrowserPath = '',
    [string]$PhpPath = '',
    [int]$DebugPort = 0
)

# Offline browser regression for the real POS view and JavaScript.
# All AJAX is stubbed. Only the final receipt test permits one offline checkout;
# all earlier checkout attempts and unrecognized requests fail immediately.
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
    errors: [], requests: [], messages: [], allowCheckout: false,
    products: [
        {product_id: 1, product_name: 'Current Americano', product_price: 999, category_name: 'Coffee'},
        {product_id: 2, product_name: 'Current Latte', product_price: 160, category_name: 'Coffee'},
        {product_id: 3, product_name: 'Cookie', product_price: 50, category_name: 'Food'},
        {product_id: 4, product_name: 'Fraction A', product_price: 0.10, category_name: 'Test fractions'},
        {product_id: 5, product_name: 'Fraction B', product_price: 0.20, category_name: 'Test fractions'},
        {product_id: 6, product_name: 'Stock-short drink', product_price: 5, category_name: 'Coffee',
            is_available: true, stock_available: false, can_order: false, availability_reason: '<b>Not enough beans</b>'}
    ],
    order: {
        kiosk_order_id: 42, reference_code: 'KG-BROWSER', status: 'claimed',
        customer_name: 'Kiosk customer', total_amount: 391,
        items: [
            {product_id: 1, product_name: 'Original Americano', unit_price: 125.50, quantity: 2, subtotal: 251},
            {product_id: 2, product_name: 'Original Latte', unit_price: 140, quantity: 1, subtotal: 140}
        ]
    },
    receipt: {
        sale_id: 77, customer_name: 'Browser receipt customer', user_name: 'Browser cashier',
        sale_date: '2026-10-19 10:00:00', total_amount: 50.30, amount_paid: 100, change_amount: 49.70,
        items: [
            {product_id: 4, product_name: 'Fraction A', quantity: 3, unit_price: 0.10, subtotal: 0.30},
            {product_id: 3, product_name: 'Cookie', quantity: 1, unit_price: 50, subtotal: 50}
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
    if (path === '/api/POS/products' || path === '/api/Kiosk/products') response = {data: __posHarness.products};
    else if (path === '/api/POS/transactions') response = {data: []};
    else if (path === '/api/Kiosk/claim/KG-BROWSER') {
        response = {status: 'success', data: __posHarness.order};
        delay = 100;
    } else if (path === '/api/POS/checkout' && __posHarness.allowCheckout) {
        __posHarness.allowCheckout = false;
        function requireFixture(condition, message) {
            if (!condition) { __posHarness.errors.push(message); throw new Error(message); }
        }
        requireFixture(options.type === 'POST' && options.data instanceof FormData, 'Checkout must POST FormData.');
        const data = options.data, expected = __posHarness.expectedCheckout;
        requireFixture(expected && data.get('amount_paid') === '100.00' && data.get('expected_total') === '50.30',
            'Checkout did not send the entered payment and displayed total.');
        requireFixture(data.get('checkout_key') === expected.key && /^[a-zA-Z0-9_-]{8,80}$/.test(expected.key),
            'Checkout did not send its stable order key.');
        requireFixture(data.get('customer_name') === 'Browser receipt customer' && !data.has('kiosk_order_id'),
            'Checkout contains the wrong customer or leftover kiosk context.');
        const items = JSON.parse(data.get('items'));
        requireFixture(Array.isArray(items) && items.length === 2
            && items.every(item => Object.keys(item).sort().join(',') === 'product_id,quantity'),
            'Checkout must send canonical product IDs and quantities.');
        const canonical = items.map(item => [Number(item.product_id), Number(item.quantity)]).sort((a,b) => a[0] - b[0]);
        requireFixture(JSON.stringify(canonical) === '[[3,1],[4,3]]', 'Checkout items differ from the visible cart.');
        __posHarness.checkoutCaptured = true;
        response = {status: 'success', message: 'Sale completed successfully.', sale_id: 77,
            total_amount: 50.30, amount_paid: 100, change_amount: 49.70, loyalty_point_earned: false};
        delay = 20;
    } else if (path === '/api/POS/transaction/77' && __posHarness.checkoutCaptured) {
        response = {status: 'success', data: __posHarness.receipt};
    } else {
        __posHarness.errors.push('Disallowed request: ' + path);
        throw new Error('Only allowlisted offline POS fixtures may be requested: ' + path);
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
$kioskRendererPath = Join-Path $artifactDirectory 'render-kiosk.php'
$kioskHtmlPath = Join-Path $artifactDirectory 'kiosk.html'
$kioskViewPath = (Join-Path $projectRoot 'app/views/Kiosk/index.php').Replace('\', '/').Replace("'", "\'")
$kioskRenderer = '<?php define(''BASE_URL'', ''http://pos-fixture.invalid''); $username = ''''; $isLoyalCustomer = false; require ''' + $kioskViewPath + ''';'
[IO.File]::WriteAllText($kioskRendererPath, $kioskRenderer, [Text.UTF8Encoding]::new($false))
$kioskView = (& $PhpPath -f $kioskRendererPath | Out-String)
if ($LASTEXITCODE -ne 0) { throw 'Rendering the offline kiosk view failed.' }
$kioskUri = Asset-Uri 'public/js/Kiosk.js'
Set-Content -LiteralPath $kioskHtmlPath -Encoding UTF8 -Value ($html.Replace($renderedView, $kioskView).Replace($posUri, $kioskUri))

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
async function waitFor(condition, message) {
    for (let attempt = 0; attempt < 100; attempt++) { if (condition()) return; await new Promise(r => setTimeout(r, 20)); }
    throw new Error(message);
}
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
function assertOffline(allowedCheckoutCount = 0) {
    assert(!h.errors.length, 'Browser errors: ' + h.errors.join('; '));
    assert(h.requests.filter(x => x.path.includes('checkout')).length === allowedCheckoutCount,
        'An unexpected checkout request was made.');
}
function assertChange(expected) {
    const change = document.querySelector('#payment-change');
    assert(change && !change.isContentEditable, 'A read-only change preview is missing.');
    const text = typeof expected === 'number'
        ? String.fromCharCode(0x20b1) + expected.toFixed(2)
        : expected === null ? String.fromCharCode(0x2014) : expected;
    assert(change.textContent === text, 'Change preview differs: ' + change.textContent + ' versus ' + text);
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
const shortCard = $('.btn-add-product[data-id="6"]').closest('.product-card');
assert($('.btn-add-product[data-id="6"]').prop('disabled') && shortCard.find('.badge').text() === 'Unavailable',
    'Stock-short POS products must remain visible with disabled Add controls.');
assert(shortCard.text().includes('<b>Not enough beans</b>') && !shortCard.find('b').length, 'POS availability reasons must render as literal text.');
$('.btn-add-product[data-id="6"]').prop('disabled', false).trigger('click');
assertCart([], 0);
h.products.find(product => product.product_id === 6).can_order = true;
h.products.find(product => product.product_id === 6).stock_available = true;
$(window).trigger('focus');
await waitFor(() => !$('.btn-add-product[data-id="6"]').prop('disabled'), 'POS focus refresh did not restore a restocked product.');
click('.btn-add-product[data-id="6"]');
assertCart([[6, 1, 5]], 5);
h.products.find(product => product.product_id === 6).can_order = false;
h.products.find(product => product.product_id === 6).stock_available = false;
$(window).trigger('focus');
await waitFor(() => cart()[0].unavailable && $('.btn-add-product[data-id="6"]').prop('disabled'), 'POS refresh did not flag a newly stock-short cart item.');
$('.btn-increase[data-id="6"]').prop('disabled', false).trigger('click');
assertCart([[6, 1, 5]], 5);
$('#amount_paid').val('5.00');
click('#btnCheckout');
assertOffline();
click('.btn-remove[data-id="6"]');
$('#amount_paid').val('');
assertCart([], 0);
const paidInput = document.querySelector('#amount_paid');
assert(paidInput && paidInput.type === 'number' && paidInput.required, 'A required numeric amount-paid input is missing.');
assert(paidInput.min === '0' && paidInput.max === '99999999.99' && paidInput.step === '0.01', 'Payment input bounds or cent precision differ.');
assert(!paidInput.disabled && paidInput.value === '', 'Payment input must start enabled and empty.');
paidInput.value = '12.34';
assert(paidInput.checkValidity(), 'A payment with two decimal places must be valid.');
paidInput.value = '12.345';
assert(paidInput.validity.stepMismatch, 'Sub-cent payments must violate input precision.');
paidInput.value = '-0.01';
assert(paidInput.validity.rangeUnderflow, 'Negative payments must violate the minimum.');
paidInput.value = '100000000';
assert(paidInput.validity.rangeOverflow, 'Payments above the supported range must be invalid.');
paidInput.value = '';
click('.btn-add-product[data-id="4"]');
click('.btn-increase[data-id="4"]');
click('.btn-increase[data-id="4"]');
click('.btn-add-product[data-id="5"]');
assertCart([[4, 3, 0.10], [5, 1, 0.20]], 0.50);
assertChange(null);
$('#amount_paid').val('0.50').trigger('input');
assertChange(0);
$('#amount_paid').val('1.00').trigger('input');
assertChange(0.50);
$('#amount_paid').val('0.49').trigger('input');
assertChange('Insufficient payment');
$('#amount_paid').val('1.00').trigger('input');
assertChange(0.50);
click('.btn-increase[data-id="5"]');
click('.btn-increase[data-id="5"]');
assertCart([[4, 3, 0.10], [5, 3, 0.20]], 0.90);
assertChange(0.10);
click('.btn-decrease[data-id="4"]');
click('.btn-decrease[data-id="4"]');
assertCart([[4, 1, 0.10], [5, 3, 0.20]], 0.70);
assertChange(0.30);
click('.btn-remove[data-id="4"]');
assertCart([[5, 3, 0.20]], 0.60);
assertChange(0.40);
click('.btn-remove[data-id="5"]');
assertCart([], 0);
$('#amount_paid').val('').trigger('input');
assertChange(null);
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
$('#amount_paid').val('999.99');
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
assert($('#amount_paid').val() === '', 'Retrieving a kiosk order must clear a previous payment.');
assertChange(null);
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
assert($('#amount_paid').val() === '', 'A payment must not be restored from an earlier page load.');
click('.btn-remove[data-id="1"]');
assertCart([], 0);
click('.btn-add-product[data-id="1"]');
assertCart([[1, 1, 125.5]], 125.5);
assert(cart()[0].product_name === 'Original Americano', 'Historical product name did not survive reload/re-add.');
click('.btn-remove[data-id="1"]');
click('.btn-add-product[data-id="3"]');
assertCart([[3, 1, 50]], 50);
$('#amount_paid').val('100.00');
click('#btnClear');
await new Promise(r => setTimeout(r, 0));
assertCart([], 0);
assert(!localStorage.getItem('pos_kiosk_order_id') && !localStorage.getItem('pos_kiosk_items'), 'New order retained kiosk metadata.');
assert(!localStorage.getItem('pos_customer_name') && !$('#customer_name').val(), 'New order retained a customer name.');
assert($('#amount_paid').val() === '' && !$('#amount_paid').prop('disabled'), 'New order must clear the amount paid and leave its input enabled.');
assertChange(null);
click('.btn-add-product[data-id="1"]');
assertCart([[1, 1, 999]], 999);
click('.btn-remove[data-id="1"]');
assertCart([], 0);
assertOffline();
return 'Reload preserves edited cart, kiosk context, historical price/name, and customer; New order clears context and payment. No checkout request was made.';
'@
    Write-Output (Evaluate-Js ('(async () => {' + $assertions + $phaseTwo + '})()'))
    $receiptPhase = @'
assertCart([], 0);
click('.btn-add-product[data-id="4"]');
click('.btn-increase[data-id="4"]');
click('.btn-increase[data-id="4"]');
click('.btn-add-product[data-id="3"]');
assertCart([[4, 3, 0.10], [3, 1, 50]], 50.30);
$('#customer_name').val('Browser receipt customer').trigger('input');
$('#amount_paid').val('100.00').trigger('input');
assertChange(49.70);
const previousKey = localStorage.getItem('pos_checkout_key');
h.expectedCheckout = {key: previousKey};
h.allowCheckout = true;
click('#btnCheckout');
assert($('#amount_paid').prop('disabled') && $('#btnCheckout').prop('disabled'), 'Payment controls must lock during checkout.');
for (let attempt = 0; attempt < 100; attempt++) {
    if (document.querySelector('#transaction-details h4') && $('#transactionDetailsModal').hasClass('show')) break;
    await new Promise(r => setTimeout(r, 20));
}
assert(h.checkoutCaptured && !h.allowCheckout, 'Exactly one offline checkout fixture must be consumed.');
assert($('#transactionDetailsModal').hasClass('show'), 'Checkout did not automatically show its receipt modal.');
assert($('#transactionDetailsModal .modal-title').text().trim() === 'Sales Receipt', 'The modal must identify the sales receipt.');
const receipt = document.querySelector('#transaction-details');
assert(receipt.querySelector('h4') && receipt.querySelector('h4').textContent === 'Klasik Grounds Cafe', 'Receipt business header is missing.');
assert(receipt.textContent.includes('#77') && receipt.textContent.includes('Browser receipt customer')
    && receipt.textContent.includes('Browser cashier'), 'Receipt transaction, customer, or cashier differs.');
const lines = receipt.querySelectorAll('tbody tr');
assert(lines.length === 2, 'Receipt must show both purchased products.');
const peso = String.fromCharCode(0x20b1);
assert(Array.from(lines[0].querySelectorAll('td')).map(cell => cell.textContent.trim()).join('|')
    === 'Fraction A|3|' + peso + '0.10|' + peso + '0.30', 'Receipt decimal-price item differs.');
assert(Array.from(lines[1].querySelectorAll('td')).map(cell => cell.textContent.trim()).join('|')
    === 'Cookie|1|' + peso + '50.00|' + peso + '50.00', 'Receipt second item differs.');
function footer(label) {
    const row = Array.from(receipt.querySelectorAll('tfoot tr'))
        .find(row => row.querySelector('th').textContent.trim() === label);
    assert(row, 'Missing receipt total: ' + label);
    return row.querySelector('th:last-child').textContent.trim();
}
assert(footer('Amount paid') === peso + '100.00' && footer('Change') === peso + '49.70'
    && footer('Grand Total') === peso + '50.30', 'Receipt payment, change, or total differs.');
assertCart([], 0);
assert($('#amount_paid').val() === '' && !$('#amount_paid').prop('disabled') && !$('#btnCheckout').prop('disabled'),
    'Successful checkout must clear payment and unlock the controls.');
assert(localStorage.getItem('pos_checkout_key') !== previousKey, 'Successful checkout must start a fresh order key.');
assertOffline(1);
return 'One offline checkout sends the visible cart/payment/total/key, clears the cart, and automatically displays a complete sales receipt.';
'@
    Write-Output (Evaluate-Js ('(async () => {' + $assertions + $receiptPhase + '})()'))
    $null = Send-Cdp 'Page.navigate' @{url = ([uri]$kioskHtmlPath).AbsoluteUri}
    $kioskReady = $false
    for ($attempt = 0; $attempt -lt 100; $attempt++) {
        try {
            $state = Evaluate-Js '(() => { const h = window.__posHarness; return h ? {ready: document.readyState === "complete" && $("#kiosk-products .kiosk-add").length === h.products.length, errors: h.errors} : null; })()'
            if ($state -and $state.errors.Count) { throw ('Kiosk browser errors: ' + ($state.errors -join '; ')) }
            if ($state -and $state.ready) { $kioskReady = $true; break }
        } catch { if ($_.Exception.Message -like 'Kiosk browser errors:*' -or $attempt -eq 99) { throw } }
        Start-Sleep -Milliseconds 100
    }
    if (-not $kioskReady) { throw 'The offline kiosk menu did not load.' }
    Write-Output (Evaluate-Js @'
(async () => {
    const h = __posHarness;
    function assert(condition, message) { if (!condition) throw new Error(message); }
    async function waitFor(condition, message) {
        for (let attempt = 0; attempt < 100; attempt++) { if (condition()) return; await new Promise(r => setTimeout(r, 20)); }
        throw new Error(message);
    }
    const card = $('.kiosk-add[data-id="6"]').closest('.card');
    assert($('.kiosk-add[data-id="6"]').prop('disabled') && card.find('.badge').text() === 'Unavailable', 'Stock-short kiosk products must remain visible and disabled.');
    assert(card.text().includes('<b>Not enough beans</b>') && !card.find('b').length, 'Kiosk availability reasons must render literally.');
    $('.kiosk-add[data-id="6"]').prop('disabled', false).trigger('click');
    assert(!$('#kiosk-cart .kiosk-remove').length, 'Forced clicks must not add an unavailable kiosk product.');
    const product = h.products.find(product => product.product_id === 6);
    product.can_order = true; product.stock_available = true;
    $(window).trigger('focus');
    await waitFor(() => !$('.kiosk-add[data-id="6"]').prop('disabled'), 'Restocking did not re-enable the kiosk product after focus refresh.');
    $('.kiosk-add[data-id="6"]').trigger('click');
    assert($('#kiosk-cart .kiosk-remove').length === 1, 'A restocked kiosk product could not be added.');
    product.can_order = false; product.stock_available = false;
    $(window).trigger('focus');
    await waitFor(() => $('.kiosk-plus[data-id="6"]').prop('disabled') && $('#kiosk-submit').prop('disabled'), 'Kiosk refresh did not block the stock-short existing cart.');
    $('.kiosk-plus[data-id="6"]').prop('disabled', false).trigger('click');
    assert($('.kiosk-plus[data-id="6"]').siblings('span').text() === '1', 'A forced increase changed an unavailable kiosk quantity.');
    $('#kiosk-submit').prop('disabled', false).trigger('click');
    assert(!h.errors.length && h.requests.every(request => request.path === '/api/Kiosk/products' && request.method === 'GET'), 'Unavailable kiosk carts must not submit or issue external requests.');
    return 'POS/kiosk availability passed: shortage badges, safe reasons, disabled and guarded Add/quantity/checkout, and restock refresh on window focus.';
})()
'@)
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
