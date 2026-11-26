param(
    [string]$OutputDirectory = (Join-Path (Split-Path -Parent (Split-Path -Parent $PSScriptRoot)) 'dist'),
    [string]$PackageVersion = '0.2.4',
    [string]$InstallPasskey = '',
    [string]$ClientName = 'ClothEase'
)

$ErrorActionPreference = 'Stop'
$phpRuntime = 'C:\xampp\php'
if (-not (Test-Path (Join-Path $phpRuntime 'php.exe'))) {
    throw 'This test build script expects PHP from C:\xampp\php.'
}
$env:Path = "$phpRuntime;$env:Path"
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$OutputDirectory = [System.IO.Path]::GetFullPath($OutputDirectory)
$buildId = [guid]::NewGuid().ToString('N')
$stageRoot = Join-Path $env:TEMP ('ClothEase-Test-Installer-Stage-' + $buildId)
$appRoot = Join-Path $stageRoot 'App'
$stagedArchive = Join-Path $env:TEMP ('ClothEase-Test-Installer-' + $buildId + '.zip')
$archive = Join-Path $OutputDirectory 'ClothEase-Test-Installer.zip'

New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null
New-Item -ItemType Directory -Path $appRoot -Force | Out-Null

$excludedDirectories = @('.git', 'node_modules', 'tests', 'storage', 'dist', 'packaging')
$excludedFiles = @('.env', '.env.example', '.gitignore', '.phpunit.result.cache', 'phpunit.xml', 'package.json', 'package-lock.json', 'vite.config.js')
& robocopy $projectRoot $appRoot /E /R:1 /W:1 /XD $excludedDirectories /XF $excludedFiles /NFL /NDL /NJH /NJS /NP /NC /NS
if ($LASTEXITCODE -ge 8) { throw "Could not stage the app files (robocopy exit code $LASTEXITCODE)." }

Remove-Item -LiteralPath (Join-Path $appRoot 'database\database.sqlite') -Force -ErrorAction SilentlyContinue
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'Install-ClothEase.ps1') -Destination $stageRoot
@'
ClothEase Windows Test Installer

1. Extract this ZIP to a folder you can write to.
2. Right-click Install-ClothEase.ps1 and choose Run with PowerShell.
   If Windows blocks it, open PowerShell in this folder and run:
   powershell -NoProfile -ExecutionPolicy Bypass -File .\Install-ClothEase.ps1
3. The installer uses PHP 8.2+ already installed on this PC. XAMPP's PHP is
   detected at C:\xampp\php\php.exe. It must include mbstring, openssl,
   and pdo_sqlite.
4. Start the app from the ClothEase desktop shortcut.

This is a test package, not a production release. It binds the local web server
to 127.0.0.1 only. App files are installed under LocalAppData\Programs\ClothEase;
the environment, database, sessions, logs, and backups stay under
LocalAppData\ClothEase and survive reinstall/update. To reset test data, first
back it up, then remove that data folder yourself.
'@ | Set-Content -LiteralPath (Join-Path $stageRoot 'README.txt') -Encoding utf8

if (Test-Path $stagedArchive) { Remove-Item -LiteralPath $stagedArchive -Force }
Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory(
    $stageRoot,
    $stagedArchive,
    [System.IO.Compression.CompressionLevel]::NoCompression,
    $false
)
Copy-Item -LiteralPath $stagedArchive -Destination $archive -Force
Write-Host "Created $archive"
Remove-Item -LiteralPath $stagedArchive -Force
Remove-Item -LiteralPath $stageRoot -Recurse -Force

 $safeClientName = [regex]::Replace($ClientName, '[^A-Za-z0-9-]+', '-')
& (Join-Path $PSScriptRoot 'build-exe-installer.ps1') -OutputPath (Join-Path $OutputDirectory "$safeClientName-Setup-$PackageVersion.exe") -PackageVersion $PackageVersion -InstallPasskey $InstallPasskey -ClientName $ClientName
if ($LASTEXITCODE -and $LASTEXITCODE -ne 0) {
    throw 'The ZIP was built, but the ClothEase.exe launcher build failed.'
}
