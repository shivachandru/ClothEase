$ErrorActionPreference = 'Stop'
$InstallRoot = [System.IO.Path]::GetFullPath((Join-Path $env:LOCALAPPDATA 'Programs\ClothEase'))
$DataRoot = [System.IO.Path]::GetFullPath((Join-Path $env:LOCALAPPDATA 'ClothEase'))
$rootPattern = [regex]::Escape($InstallRoot)
$pidFile = Join-Path $DataRoot 'server.pid'
$phpPaths = @('C:\xampp\php\php.exe', (Join-Path $env:ProgramFiles 'PHP\php.exe'))
$phpCommand = Get-Command php.exe -ErrorAction SilentlyContinue
if ($phpCommand) { $phpPaths += $phpCommand.Source }
$phpPaths = @($phpPaths | Where-Object { $_ -and (Test-Path $_) } | Select-Object -Unique)

Write-Host 'Stopping the ClothEase server...'
if (Test-Path $pidFile) {
    $serverPid = 0
    if ([int]::TryParse((Get-Content -LiteralPath $pidFile -Raw), [ref]$serverPid)) {
        $manager = Get-CimInstance Win32_Process -Filter "ProcessId=$serverPid" -ErrorAction SilentlyContinue
        if ($manager -and $phpPaths -contains $manager.ExecutablePath -and $manager.CommandLine -match 'artisan serve') {
            Stop-Process -Id $serverPid -Force -ErrorAction SilentlyContinue
        }
    }
}

# The app may have been launched before its PID file was created, or the old
# setup may have removed it after a failed update. Match the app path so other
# local PHP applications are not stopped.
$phpProcesses = Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue
foreach ($process in $phpProcesses) {
    if ($phpPaths -contains $process.ExecutablePath -and $process.CommandLine -match $rootPattern -and
        ($process.CommandLine -match 'artisan serve' -or $process.CommandLine -match 'server\.php')) {
        Stop-Process -Id $process.ProcessId -Force -ErrorAction SilentlyContinue
    }
}

for ($attempt = 0; $attempt -lt 20; $attempt++) {
    $remaining = @(Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue | Where-Object {
        $phpPaths -contains $_.ExecutablePath -and $_.CommandLine -match $rootPattern -and
        ($_.CommandLine -match 'artisan serve' -or $_.CommandLine -match 'server\.php')
    })
    if ($remaining.Count -eq 0) { break }
    foreach ($process in $remaining) { Stop-Process -Id $process.ProcessId -Force -ErrorAction SilentlyContinue }
    Start-Sleep -Milliseconds 250
}

$remaining = @(Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue | Where-Object {
    $phpPaths -contains $_.ExecutablePath -and $_.CommandLine -match $rootPattern -and
    ($_.CommandLine -match 'artisan serve' -or $_.CommandLine -match 'server\.php')
})
if ($remaining.Count -gt 0) {
    throw 'The ClothEase server is still running. Close it and try uninstalling again.'
}

$desktopShortcut = Join-Path ([Environment]::GetFolderPath('Desktop')) 'ClothEase.lnk'
Remove-Item -LiteralPath $desktopShortcut -Force -ErrorAction SilentlyContinue
Remove-Item -LiteralPath $pidFile -Force -ErrorAction SilentlyContinue

Write-Host 'Removing ClothEase program files...'
for ($attempt = 0; $attempt -lt 4; $attempt++) {
    if (-not (Test-Path $InstallRoot)) { break }
    Remove-Item -LiteralPath $InstallRoot -Recurse -Force -ErrorAction SilentlyContinue
    if (-not (Test-Path $InstallRoot)) { break }
    Start-Sleep -Seconds 1
}
if (Test-Path $InstallRoot) {
    throw 'Windows still has ClothEase program files open. Restart the computer and run Uninstall again.'
}

# Retain the database, environment, sessions, logs, and backups. Remove only
# the installation markers so a later reinstall is recognized as an explicit
# reinstall of an existing data set.
Remove-Item -LiteralPath (Join-Path $DataRoot 'install-state.txt') -Force -ErrorAction SilentlyContinue
Write-Host 'ClothEase was uninstalled. Shop data and backups were kept.'
Write-Host "Shop data: $DataRoot"
