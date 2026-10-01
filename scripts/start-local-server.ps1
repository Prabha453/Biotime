param([string]$PhpPath = $env:BIOTIME_PHP, [switch]$CheckOnly)
$ErrorActionPreference = 'Stop'
$projectPath = Split-Path -Parent $PSScriptRoot
if (-not $PhpPath) {
    $command = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($command) { $PhpPath = $command.Source }
}
if (-not $PhpPath) {
    $candidates = foreach ($drive in Get-PSDrive -PSProvider FileSystem) {
        foreach ($pattern in @('xampp*\php\php.exe', 'laragon\bin\php\*\php.exe', 'wamp64\bin\php\*\php.exe', 'php\php.exe')) {
            Get-ChildItem -Path (Join-Path $drive.Root $pattern) -File -ErrorAction SilentlyContinue
        }
    }
    foreach ($candidate in ($candidates | Sort-Object FullName -Descending)) {
        & $candidate.FullName -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' 2>$null
        if ($LASTEXITCODE -eq 0) { $PhpPath = $candidate.FullName; break }
    }
}
if (-not $PhpPath -or -not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) {
    throw 'PHP 8.2+ was not found. Add PHP to PATH, set BIOTIME_PHP, or supply -PhpPath with the full path to php.exe.'
}
& $PhpPath -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);'
if ($LASTEXITCODE -ne 0) { throw 'PHP 8.2 or newer is required.' }
if (-not (Test-Path -LiteralPath (Join-Path $projectPath 'vendor\autoload.php'))) {
    throw 'Run composer install in the project folder first.'
}
$configOutput = & $PhpPath (Join-Path $PSScriptRoot 'local-server-config.php')
if ($LASTEXITCODE -ne 0) { throw 'Could not read the project .env settings.' }
$settings = $configOutput | ConvertFrom-Json
$serverUri = $null
if (-not [Uri]::TryCreate($settings.url, [UriKind]::Absolute, [ref]$serverUri) -or
    $serverUri.Scheme -ne 'http' -or $serverUri.AbsolutePath -ne '/' -or
    $serverUri.Query -or $serverUri.Fragment -or $serverUri.UserInfo) {
    throw 'For this local launcher, app.baseURL must be an HTTP root URL such as http://localhost:8080/. Use Apache or IIS for HTTPS or subfolder hosting.'
}
$serverAddress = $serverUri.DnsSafeHost
$serverPort = $serverUri.Port
Write-Output "Project URL: $($settings.url)"
if ($CheckOnly) { exit 0 }
$client = New-Object System.Net.Sockets.TcpClient
try {
    $connected = $client.ConnectAsync($serverAddress, $serverPort)
    if ($connected.Wait(1000) -and $client.Connected) { exit 0 }
} catch {
    # A closed port means the project server needs to start.
} finally { $client.Dispose() }
$logPath = Join-Path $projectPath 'writable\logs'
New-Item -ItemType Directory -Path $logPath -Force | Out-Null
Start-Process -FilePath $PhpPath `
    -ArgumentList @('spark', 'serve', '--host', $serverAddress, '--port', "$serverPort") `
    -WorkingDirectory $projectPath -WindowStyle Hidden `
    -RedirectStandardOutput (Join-Path $logPath 'local-server-output.log') `
    -RedirectStandardError (Join-Path $logPath 'local-server-error.log')
