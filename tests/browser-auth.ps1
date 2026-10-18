param([string]$BrowserPath = '', [string]$PhpPath = '', [int]$DebugPort = 0)

# Render the real Auth view/layout with local assets and synthetic session messages.
# No application configuration, database, credentials, or login submissions are used.
$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if (-not $BrowserPath) {
    $BrowserPath = @('C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
        'C:\Program Files\Microsoft\Edge\Application\msedge.exe') |
        Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
}
if (-not $BrowserPath -or -not (Test-Path -LiteralPath $BrowserPath)) { throw 'Edge not found. Supply -BrowserPath.' }
if (-not $PhpPath) { $PhpPath = (Get-Command php -ErrorAction Stop).Source }
if (-not (Test-Path -LiteralPath $PhpPath)) { throw 'PHP CLI not found. Supply -PhpPath.' }
if ($DebugPort -eq 0) {
    $listener = New-Object Net.Sockets.TcpListener([Net.IPAddress]::Loopback, 0)
    $listener.Start()
    try { $DebugPort = $listener.LocalEndpoint.Port } finally { $listener.Stop() }
}
if ($DebugPort -lt 1 -or $DebugPort -gt 65535) { throw 'Invalid -DebugPort.' }
$artifactDirectory = Join-Path $env:TEMP ('klasik-auth-browser-' + [guid]::NewGuid().ToString('N'))
$null = New-Item -ItemType Directory -Path $artifactDirectory
$rendererPath = Join-Path $artifactDirectory 'render-auth.php'
$profilePath = Join-Path $artifactDirectory 'edge-profile'
$publicPath = (Join-Path $projectRoot 'public').Replace('\', '/').Replace("'", "\'")
$publicUri = ([uri](Join-Path $projectRoot 'public')).AbsoluteUri.TrimEnd('/').Replace("'", "\'")
$viewPath = (Join-Path $projectRoot 'app/views/Auth/index.php').Replace('\', '/').Replace("'", "\'")
$layoutPath = (Join-Path $projectRoot 'app/views/Layout/auth.php').Replace('\', '/').Replace("'", "\'")
$renderer = @'
<?php
if (PHP_SAPI !== 'cli') exit;
define('BASE_URL', '__PUBLIC_URI__'); define('PUBLIC_PATH', '__PUBLIC_PATH__');
$_SESSION = ($argv[1] ?? '') === 'error' ? ['error' => 'Offline invalid login <strong>fixture</strong>'] : [];
$title = 'Login'; ob_start(); require '__VIEW__'; $content = ob_get_clean(); require '__LAYOUT__';
'@
$renderer = $renderer.Replace('__PUBLIC_URI__', $publicUri).Replace('__PUBLIC_PATH__', $publicPath).
    Replace('__VIEW__', $viewPath).Replace('__LAYOUT__', $layoutPath)
[IO.File]::WriteAllText($rendererPath, $renderer, [Text.UTF8Encoding]::new($false))
$offlineHead = @'
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src file: 'unsafe-inline'; style-src file: 'unsafe-inline'; img-src file: data:; font-src file:; connect-src 'none'; form-action 'none';">
<script>
window.__authHarness = {errors: [], submissions: 0};
window.addEventListener('error', event => __authHarness.errors.push(event.message || 'Browser resource error.'));
window.addEventListener('unhandledrejection', event => __authHarness.errors.push(String(event.reason)));
document.addEventListener('submit', event => { event.preventDefault(); __authHarness.submissions++; }, true);
window.fetch = () => { throw new Error('Offline Auth must not fetch network data.'); };
XMLHttpRequest.prototype.open = () => { throw new Error('Offline Auth must not make AJAX requests.'); };
</script>
'@
foreach ($mode in @('normal', 'error')) {
    $html = (& $PhpPath -f $rendererPath $mode | Out-String)
    if ($LASTEXITCODE -ne 0) { throw 'Rendering the actual Auth view/layout failed.' }
    # Remove optional external font/icon stylesheets from this offline artifact.
    $html = [regex]::Replace($html, '<link[^>]+href="https?://[^"]+"[^>]*>', '')
    $html = $html.Replace('<head>', '<head>' + $offlineHead)
    [IO.File]::WriteAllText((Join-Path $artifactDirectory ($mode + '.html')), $html, [Text.UTF8Encoding]::new($false))
}

$socket = New-Object Net.WebSockets.ClientWebSocket
$browserProcess = $null
$script:requestId = 0
function Send-Cdp([string]$method, [hashtable]$parameters = @{}) {
    $script:requestId++
    $requestId = $script:requestId
    $bytes = [Text.Encoding]::UTF8.GetBytes((@{id = $requestId; method = $method; params = $parameters} | ConvertTo-Json -Depth 20 -Compress))
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
function Open-AuthPage([string]$mode) {
    $targetUri = ([uri](Join-Path $artifactDirectory ($mode + '.html'))).AbsoluteUri
    $null = Send-Cdp 'Page.navigate' @{url = $targetUri}
    $expectedUri = $targetUri | ConvertTo-Json -Compress
    for ($attempt = 0; $attempt -lt 100; $attempt++) {
        $ready = Evaluate-Js ('window.location.href === ' + $expectedUri + ' && !!(window.__authHarness && window.jQuery && $.fn.modal && document.readyState === "complete" && ($._data($("[data-login-open]")[0], "events") || {}).click)')
        if ($ready) { return }
        Start-Sleep -Milliseconds 100
    }
    throw 'The actual Auth view, jQuery, and Bootstrap did not load.'
}
try {
    $browserProcess = Start-Process -FilePath $BrowserPath -ArgumentList @(
        '--headless=new', '--disable-gpu', '--disable-extensions', '--no-first-run',
        '--no-default-browser-check', '--disable-background-networking', '--remote-debugging-address=127.0.0.1',
        ('--remote-debugging-port=' + $DebugPort), ('--user-data-dir="' + $profilePath + '"'), 'about:blank'
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
    $null = Send-Cdp 'Network.enable'
    $null = Send-Cdp 'Network.setBlockedURLs' @{urls = @('http://*', 'https://*')}
    Open-AuthPage 'normal'
    Write-Output (Evaluate-Js @'
(async () => {
    function assert(ok, message) { if (!ok) throw new Error(message); }
    const $dialog = $('#login-dialog');
    assert(!$dialog.hasClass('show'), 'The sign-in card must start closed.');
    const shown = new Promise(resolve => $dialog.one('shown.bs.modal', resolve));
    $('[data-login-open]').first().trigger('click'); await shown;
    assert($dialog.hasClass('show') && $dialog.data('bs.modal'), 'Sign in must open the actual Bootstrap popup.');
    assert(document.activeElement.id === 'username', 'Opening Sign in must focus the username field.');
    assert(getComputedStyle($('.kg-login-card')[0]).backgroundColor === 'rgb(25, 25, 28)'
        && getComputedStyle(document.body).backgroundColor === 'rgb(5, 5, 5)', 'The Auth page and popup must retain their dark theme.');
    $('#password').val('offline-placeholder');
    return 'Auth popup passed: Sign in opens, username receives focus, and the dark theme is preserved.';
})()
'@)
    $null = Send-Cdp 'Input.dispatchKeyEvent' @{type = 'keyDown'; key = 'Escape'; code = 'Escape'; windowsVirtualKeyCode = 27; nativeVirtualKeyCode = 27}
    $null = Send-Cdp 'Input.dispatchKeyEvent' @{type = 'keyUp'; key = 'Escape'; code = 'Escape'; windowsVirtualKeyCode = 27; nativeVirtualKeyCode = 27}
    Write-Output (Evaluate-Js @'
(async () => {
    function assert(ok, message) { if (!ok) throw new Error(message); }
    for (let n = 0; n < 100 && ($('body').hasClass('modal-open') || $('#password').val()); n++)
        await new Promise(resolve => setTimeout(resolve, 20));
    assert(!$('#login-dialog').hasClass('show') && !$('body').hasClass('modal-open') && $('#password').val() === '',
        'Escape must close the popup and clear the password.');
    const $dialog = $('#login-dialog');
    const shown = new Promise(resolve => $dialog.one('shown.bs.modal', resolve));
    $('[data-login-open]').last().trigger('click'); await shown;
    $('#password').val('offline-placeholder');
    const hidden = new Promise(resolve => $dialog.one('hidden.bs.modal', resolve));
    $dialog.find('[data-dismiss="modal"]').trigger('click'); await hidden;
    assert(!$('#login-dialog').hasClass('show') && $('#password').val() === '', 'The close button must close and clear the password.');
    assert(!__authHarness.errors.length && __authHarness.submissions === 0, 'Offline Auth must have no browser errors or login submissions.');
    return 'Auth closing passed: Escape and close button work; reopening works; passwords clear; no login was submitted.';
})()
'@)
    Open-AuthPage 'error'
    Write-Output (Evaluate-Js @'
(async () => {
    function assert(ok, message) { if (!ok) throw new Error(message); }
    for (let n = 0; n < 100 && document.activeElement.id !== 'login-error'; n++)
        await new Promise(resolve => setTimeout(resolve, 20));
    assert($('#login-dialog').hasClass('show') && document.activeElement.id === 'login-error',
        'A synthetic login error must automatically open the popup and focus the error.');
    assert($('#login-error').text().trim() === 'Offline invalid login <strong>fixture</strong>'
        && !$('#login-error strong').length, 'The error message must render safely as literal text.');
    assert(!__authHarness.errors.length && __authHarness.submissions === 0, 'Error rendering must not submit a login or produce browser errors.');
    return 'Auth error popup passed: automatic opening, accessible error focus, and escaped text. No credentials or application network requests.';
})()
'@)
} finally {
    if ($socket.State -eq [Net.WebSockets.WebSocketState]::Open) { try { $null = Send-Cdp 'Browser.close' } catch { } }
    $socket.Dispose()
    if ($browserProcess -and -not $browserProcess.HasExited) { Stop-Process -Id $browserProcess.Id -ErrorAction SilentlyContinue }
    Write-Output ('Offline Auth artifacts: ' + $artifactDirectory)
}
