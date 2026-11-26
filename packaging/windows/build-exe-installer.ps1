param(
    [string]$OutputPath = '',
    [string]$PackageVersion = '0.2.4',
    [string]$InstallPasskey = '',
    [string]$ClientName = 'ClothEase'
)

$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$distRoot = Join-Path $projectRoot 'dist'
$archive = Join-Path $distRoot 'ClothEase-Test-Installer.zip'
$source = Join-Path $PSScriptRoot 'ClothEaseSetupLauncher.cs'
$installerSource = Join-Path $PSScriptRoot 'Install-ClothEase.ps1'
$uninstallerSource = Join-Path $PSScriptRoot 'Uninstall-ClothEase.ps1'
$iconSource = Join-Path $PSScriptRoot 'ClothEase.ico'
$iconGenerator = Join-Path $PSScriptRoot 'New-ClothEaseIcon.ps1'
$compiler = Join-Path $env:WINDIR 'Microsoft.NET\Framework64\v4.0.30319\csc.exe'
$output = if ([string]::IsNullOrWhiteSpace($OutputPath)) { Join-Path $distRoot 'ClothEase.exe' } else { [System.IO.Path]::GetFullPath($OutputPath) }

if ($PackageVersion -notmatch '^\d+\.\d+\.\d+$') { throw 'PackageVersion must use major.minor.patch format, for example 0.2.0.' }
if ([string]::IsNullOrWhiteSpace($InstallPasskey)) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'
    $randomBytes = New-Object byte[] 24
    $random = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $random.GetBytes($randomBytes) } finally { $random.Dispose() }
    $keyBuilder = New-Object System.Text.StringBuilder
    foreach ($byte in $randomBytes) { [void]$keyBuilder.Append($alphabet[[int]$byte % $alphabet.Length]) }
    $InstallPasskey = $keyBuilder.ToString()
}
if ($InstallPasskey.Length -lt 16) { throw 'InstallPasskey must be at least 16 characters.' }
$sha = [System.Security.Cryptography.SHA256]::Create()
try {
    $keyHash = [BitConverter]::ToString($sha.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($InstallPasskey))).Replace('-', '').ToLowerInvariant()
} finally { $sha.Dispose() }

if (-not (Test-Path $archive)) { throw "Test installer ZIP not found: $archive" }
if (-not (Test-Path $source)) { throw "Launcher source not found: $source" }
if (-not (Test-Path $installerSource)) { throw "Installer script not found: $installerSource" }
if (-not (Test-Path $uninstallerSource)) { throw "Uninstaller script not found: $uninstallerSource" }
if (-not (Test-Path $iconGenerator)) { throw "ClothEase icon generator not found: $iconGenerator" }
if (-not (Test-Path $iconSource)) { throw "ClothEase icon not found: $iconSource (run New-ClothEaseIcon.ps1 first)" }
if (-not (Test-Path $compiler)) { throw 'The .NET Framework C# compiler was not found.' }

& $iconGenerator

Add-Type -AssemblyName System.IO.Compression.FileSystem
$package = [System.IO.Compression.ZipFile]::OpenRead($archive)
try {
    $entryNames = @($package.Entries | ForEach-Object { $_.FullName.Replace('\', '/') })
    if ('Install-ClothEase.ps1' -notin $entryNames -or 'App/artisan' -notin $entryNames) {
        throw 'The test installer ZIP is incomplete.'
    }
    if (-not $package.GetEntry('Install-ClothEase.ps1')) { throw 'The ZIP installer script is missing.' }
} finally {
    $package.Dispose()
}

New-Item -ItemType Directory -Path $distRoot -Force | Out-Null
if (Test-Path $output) { Remove-Item -LiteralPath $output -Force }
$generatedSource = Join-Path $env:TEMP ('ClothEaseSetupLauncher-' + [guid]::NewGuid().ToString('N') + '.cs')
$sourceText = Get-Content -LiteralPath $source -Raw
$sourceText = $sourceText.Replace('__PACKAGE_VERSION__', $PackageVersion).Replace('__INSTALL_KEY_HASH__', $keyHash).Replace('__CLIENT_NAME__', $ClientName)
[System.IO.File]::WriteAllText($generatedSource, $sourceText, [System.Text.UTF8Encoding]::new($false))
$compilerArgs = @(
    '/nologo',
    '/target:winexe',
    '/platform:anycpu',
    '/optimize+',
    "/out:$output",
    '/reference:System.Windows.Forms.dll',
    '/reference:System.IO.Compression.dll',
    '/reference:System.IO.Compression.FileSystem.dll',
    "/win32icon:$iconSource",
    "/resource:$archive,ClothEase-Test-Installer.zip",
    "/resource:$installerSource,ClothEase-Install.ps1",
    "/resource:$uninstallerSource,ClothEase-Uninstall.ps1",
    "/resource:$iconSource,ClothEase-App.ico",
    $generatedSource
)
try {
    & $compiler @compilerArgs
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path $output)) {
        throw "Could not compile the ClothEase test installer (exit code $LASTEXITCODE)."
    }
} finally {
    Remove-Item -LiteralPath $generatedSource -Force -ErrorAction SilentlyContinue
}

Write-Host "Created $output (version $PackageVersion)"
Write-Host "Install key (save this; it is required to install or update): $InstallPasskey"
