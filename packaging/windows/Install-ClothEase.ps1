param(
    [string]$PhpPath = '',
    [string]$PackageVersion = '',
    [string]$IconPath = ''
)

$ErrorActionPreference = 'Stop'
$InstallRoot = Join-Path $env:LOCALAPPDATA 'Programs\ClothEase'
$DataRoot = Join-Path $env:LOCALAPPDATA 'ClothEase'
$packageRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$payloadRoot = Join-Path $packageRoot 'App'

if (-not (Test-Path (Join-Path $payloadRoot 'artisan'))) {
    throw 'The installer package is incomplete: App\artisan was not found.'
}

if ([string]::IsNullOrWhiteSpace($PhpPath)) {
    $phpCandidates = @(
        (Join-Path $env:ProgramFiles 'PHP\php.exe'),
        'C:\xampp\php\php.exe'
    )
    $phpCommand = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($phpCommand) { $phpCandidates += $phpCommand.Source }
    $PhpPath = $phpCandidates | Where-Object { $_ -and (Test-Path $_) } | Select-Object -First 1
}
if (-not $PhpPath -or -not (Test-Path $PhpPath)) {
    throw "PHP 8.2+ was not found. Download XAMPP (recommended): https://www.apachefriends.org/download.html or PHP for Windows: https://www.php.net/downloads.php?os=windows&osvariant=windows-downloads"
}
$phpPath = $PhpPath

$phpVersion = & $phpPath -r 'echo PHP_VERSION_ID;'
if ($LASTEXITCODE -ne 0) {
    throw "Could not run the selected PHP runtime. Reinstall XAMPP or PHP from https://www.apachefriends.org/download.html or https://www.php.net/downloads.php?os=windows&osvariant=windows-downloads"
}
if ([int]$phpVersion -lt 80200) {
    throw "ClothEase requires PHP 8.2 or newer. Download XAMPP: https://www.apachefriends.org/download.html or PHP for Windows: https://www.php.net/downloads.php?os=windows&osvariant=windows-downloads"
}
$modules = & $phpPath -m
$missingModules = @('mbstring', 'openssl', 'pdo_sqlite') | Where-Object { $modules -notcontains $_ }
if ($missingModules.Count -gt 0) {
    throw "PHP is missing required extensions: $($missingModules -join ', '). Install XAMPP, or enable these extensions in php.ini and run setup again. XAMPP: https://www.apachefriends.org/download.html PHP: https://www.php.net/downloads.php?os=windows&osvariant=windows-downloads"
}

$InstallRoot = [System.IO.Path]::GetFullPath($InstallRoot)
$DataRoot = [System.IO.Path]::GetFullPath($DataRoot)
$parsedPackageVersion = $null
if (-not [Version]::TryParse($PackageVersion, [ref]$parsedPackageVersion)) {
    throw 'The installer package version is missing or invalid.'
}
$installStatePath = Join-Path $DataRoot 'install-state.txt'
$programStatePath = Join-Path $InstallRoot 'install-state.txt'
foreach ($statePath in @($installStatePath, $programStatePath)) {
    if (-not (Test-Path $statePath)) { continue }
    $parsedInstalledVersion = $null
    $installedVersionText = (Get-Content -LiteralPath $statePath -Raw).Trim()
    if ([Version]::TryParse($installedVersionText, [ref]$parsedInstalledVersion) -and $parsedInstalledVersion -ge $parsedPackageVersion) {
        throw "ClothEase version $installedVersionText is already installed. Reinstallation with this package is blocked."
    }
}
New-Item -ItemType Directory -Path $InstallRoot -Force | Out-Null
if (-not [string]::IsNullOrWhiteSpace($IconPath) -and (Test-Path -LiteralPath $IconPath)) {
    Copy-Item -LiteralPath $IconPath -Destination (Join-Path $InstallRoot 'ClothEase.ico') -Force
}
New-Item -ItemType Directory -Path $DataRoot -Force | Out-Null

# Stop this application's PHP server before touching files that it may have open.
$serverPidPath = Join-Path $DataRoot 'server.pid'
$installRootPattern = [regex]::Escape($InstallRoot)
if (Test-Path $serverPidPath) {
    $savedPid = 0
    if ([int]::TryParse((Get-Content -LiteralPath $serverPidPath -Raw), [ref]$savedPid)) {
        $savedProcess = Get-CimInstance Win32_Process -Filter "ProcessId=$savedPid" -ErrorAction SilentlyContinue
        if ($savedProcess -and $savedProcess.ExecutablePath -eq $phpPath -and $savedProcess.CommandLine -match 'artisan serve') {
            Stop-Process -Id $savedPid -Force -ErrorAction SilentlyContinue
        }
    }
}

# The PID file may be missing after a failed update, so also find app processes
# by their executable and command line. Do not stop unrelated PHP applications.
$phpProcesses = Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue
foreach ($process in $phpProcesses) {
    $isClothEaseProcess = $process.ExecutablePath -eq $phpPath -and
        $process.CommandLine -match $installRootPattern -and
        ($process.CommandLine -match 'artisan serve' -or $process.CommandLine -match 'server\.php')
    if ($isClothEaseProcess) {
        Stop-Process -Id $process.ProcessId -Force -ErrorAction SilentlyContinue
    }
}

for ($attempt = 0; $attempt -lt 20; $attempt++) {
    $listeners = @(Get-NetTCPConnection -LocalPort 8765 -State Listen -ErrorAction SilentlyContinue)
    if ($listeners.Count -eq 0) { break }
    foreach ($connection in $listeners) {
        $listener = Get-CimInstance Win32_Process -Filter "ProcessId=$($connection.OwningProcess)" -ErrorAction SilentlyContinue
        $isClothEaseListener = $listener -and $listener.ExecutablePath -eq $phpPath -and
            $listener.CommandLine -match 'server\.php' -and $listener.CommandLine -match $installRootPattern
        if (-not $isClothEaseListener) {
            throw 'Port 8765 is in use by another program or the ClothEase server could not be verified. Close the running ClothEase server or other program on this port, then run setup again.'
        }
        $manager = Get-CimInstance Win32_Process -Filter "ProcessId=$($listener.ParentProcessId)" -ErrorAction SilentlyContinue
        if ($manager -and $manager.ExecutablePath -eq $phpPath -and $manager.CommandLine -match 'artisan serve') {
            Stop-Process -Id $manager.ProcessId -Force -ErrorAction SilentlyContinue
        }
        Stop-Process -Id $listener.ProcessId -Force -ErrorAction SilentlyContinue
    }
    Start-Sleep -Milliseconds 250
}
if (@(Get-NetTCPConnection -LocalPort 8765 -State Listen -ErrorAction SilentlyContinue).Count -gt 0) {
    throw 'The ClothEase server is still running. Close it, wait a few seconds, and run setup again.'
}
Remove-Item -LiteralPath $serverPidPath -Force -ErrorAction SilentlyContinue

# Merge application files in place; never delete the live app tree wholesale.
& robocopy.exe $payloadRoot $InstallRoot /E /COPY:DAT /DCOPY:DAT /R:2 /W:1 /XJ /NFL /NDL /NJH /NJS /NP
if ($LASTEXITCODE -ge 8) {
    throw "Could not copy ClothEase program files (robocopy exit code $LASTEXITCODE). Close ClothEase and try setup again."
}

New-Item -ItemType Directory -Path (Join-Path $DataRoot 'storage\app\private\backups') -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $DataRoot 'storage\framework\cache\data') -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $DataRoot 'storage\framework\sessions') -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $DataRoot 'storage\framework\views') -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $DataRoot 'storage\logs') -Force | Out-Null
$database = Join-Path $DataRoot 'database.sqlite'
if (-not (Test-Path $database)) { New-Item -ItemType File -Path $database | Out-Null }

$envFile = Join-Path $DataRoot '.env'
if (-not (Test-Path $envFile)) {
    $dbPath = $database.Replace('\', '/')
    @"
APP_NAME="ClothEase POS"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=Asia/Kolkata
APP_URL=http://127.0.0.1:8765
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
LOG_CHANNEL=single
LOG_LEVEL=warning
DB_CONNECTION=sqlite
DB_DATABASE="$dbPath"
SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
"@ | Set-Content -LiteralPath $envFile -Encoding utf8
}

$env:CLOTHEASE_DATA_PATH = $DataRoot
Push-Location $InstallRoot
try {
    $keyIsEmpty = Select-String -LiteralPath $envFile -Pattern '^APP_KEY=$' -Quiet
    if ($keyIsEmpty) {
        & $phpPath artisan key:generate --force --no-interaction
        if ($LASTEXITCODE -ne 0) { throw 'Could not create the local application key.' }
    }
    & $phpPath artisan config:clear --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Could not clear cached application configuration.' }
    & $phpPath artisan migrate --force --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Database setup failed.' }
} finally {
    Pop-Location
    Remove-Item Env:\CLOTHEASE_DATA_PATH -ErrorAction SilentlyContinue
}

$launcher = Join-Path $InstallRoot 'Start-ClothEase.ps1'
 $launcherContent = @'
$ErrorActionPreference = 'Stop'
$installRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$dataRoot = Join-Path $env:LOCALAPPDATA 'ClothEase'
$phpPath = '__PHP_PATH__'
$env:CLOTHEASE_DATA_PATH = $dataRoot
$port = 8765
$existing = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
if ($existing) {
    $owner = Get-CimInstance Win32_Process -Filter "ProcessId=$($existing[0].OwningProcess)" -ErrorAction SilentlyContinue
    $savedPid = 0
    $savedPidFile = Join-Path $dataRoot 'server.pid'
    if (Test-Path $savedPidFile) { [void][int]::TryParse((Get-Content -LiteralPath $savedPidFile -Raw), [ref]$savedPid) }
    $serverManager = if ($savedPid) { Get-CimInstance Win32_Process -Filter "ProcessId=$savedPid" -ErrorAction SilentlyContinue }
    if (-not $owner -or $owner.ExecutablePath -ne $phpPath -or $owner.CommandLine -notlike '*server.php*' -or -not $serverManager -or $serverManager.ExecutablePath -ne $phpPath -or $serverManager.CommandLine -notlike '*artisan serve*') {
        throw "Port $port is already in use. Close the other program and try again."
    }
} else {
    $server = Start-Process -FilePath $phpPath -ArgumentList @('artisan','serve','--host=127.0.0.1',"--port=$port") -WorkingDirectory $installRoot -WindowStyle Hidden -PassThru
    Set-Content -LiteralPath (Join-Path $dataRoot 'server.pid') -Value $server.Id
}
$appUrl = "http://127.0.0.1:$port"
$ready = $false
for ($attempt = 0; $attempt -lt 30; $attempt++) {
    try {
        $response = Invoke-WebRequest -Uri $appUrl -UseBasicParsing -TimeoutSec 2
        if ($response.StatusCode -ge 200) { $ready = $true; break }
    } catch { }
    Start-Sleep -Seconds 1
}
if (-not $ready) { throw 'ClothEase did not start in time. Try opening the desktop shortcut again.' }

$chromeCandidates = @(
    (Join-Path $env:ProgramFiles 'Google\Chrome\Application\chrome.exe'),
    (Join-Path ${env:ProgramFiles(x86)} 'Google\Chrome\Application\chrome.exe'),
    (Join-Path $env:LOCALAPPDATA 'Google\Chrome\Application\chrome.exe')
)
$chromeCommand = Get-Command chrome.exe -ErrorAction SilentlyContinue
if ($chromeCommand) { $chromeCandidates += $chromeCommand.Source }
$chromePath = $chromeCandidates | Where-Object { $_ -and (Test-Path -LiteralPath $_) } | Select-Object -First 1
if ($chromePath) {
    Start-Process -FilePath $chromePath -ArgumentList @('--new-window', $appUrl)
} else {
    Start-Process $appUrl
}
'@
$launcherContent = $launcherContent.Replace('__PHP_PATH__', $phpPath.Replace("'", "''"))
$launcherContent | Set-Content -LiteralPath $launcher -Encoding utf8

$shortcutPath = Join-Path ([Environment]::GetFolderPath('Desktop')) 'ClothEase.lnk'
$shell = New-Object -ComObject WScript.Shell
$shortcut = $shell.CreateShortcut($shortcutPath)
$shortcut.TargetPath = 'powershell.exe'
$shortcut.Arguments = "-NoProfile -ExecutionPolicy Bypass -File `"$launcher`""
$shortcut.WorkingDirectory = $InstallRoot
$shortcut.Description = 'Start ClothEase on this computer'
$shortcut.IconLocation = (Join-Path $InstallRoot 'ClothEase.ico') + ',0'
$shortcut.Save()
Set-Content -LiteralPath $installStatePath -Value $PackageVersion -Encoding Ascii
Set-Content -LiteralPath $programStatePath -Value $PackageVersion -Encoding Ascii

Write-Host "ClothEase test installation completed (version $PackageVersion)."
Write-Host "Program files: $InstallRoot"
Write-Host "Shop data:     $DataRoot"
Write-Host "Use the Desktop shortcut to start ClothEase in Chrome."
