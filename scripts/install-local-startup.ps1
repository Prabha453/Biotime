param([string]$PhpPath)
$ErrorActionPreference = 'Stop'
$launcher = Join-Path $PSScriptRoot 'start-local-server.ps1'
$powershellPath = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
$arguments = '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "' + $launcher + '"'
if ($PhpPath) {
    $PhpPath = (Resolve-Path -LiteralPath $PhpPath).Path
    $arguments += ' -PhpPath "' + $PhpPath + '"'
    & $powershellPath -NoProfile -ExecutionPolicy Bypass -File $launcher -PhpPath $PhpPath
} else {
    & $powershellPath -NoProfile -ExecutionPolicy Bypass -File $launcher
}
if ($LASTEXITCODE -ne 0) { throw 'Server setup failed. Fix the error above and run this installer again.' }
$shortcutPath = Join-Path ([Environment]::GetFolderPath('Startup')) 'BioTime Attendance.lnk'
$shell = New-Object -ComObject WScript.Shell
$shortcut = $shell.CreateShortcut($shortcutPath)
$shortcut.TargetPath = $powershellPath
$shortcut.Arguments = $arguments
$shortcut.WorkingDirectory = Split-Path -Parent $PSScriptRoot
$shortcut.WindowStyle = 7
$shortcut.Description = 'Start the attendance project using its .env URL at Windows sign-in'
$shortcut.Save()
Write-Output 'Startup installed for this Windows user. Open the project URL shown above.'
