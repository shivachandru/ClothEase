param([string]$SourceImagePath = '')

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing

$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$sourcePath = if ([string]::IsNullOrWhiteSpace($SourceImagePath)) {
    Join-Path $projectRoot 'public\images\clothease-app-icon.png'
} else {
    [System.IO.Path]::GetFullPath($SourceImagePath)
}
$iconPath = Join-Path $PSScriptRoot 'ClothEase.ico'
$faviconPath = Join-Path $projectRoot 'public\favicon.ico'
if (-not (Test-Path -LiteralPath $sourcePath)) { throw "Logo image not found: $sourcePath" }

$sizes = @(16, 24, 32, 48, 64, 128, 256)
$images = New-Object System.Collections.Generic.List[byte[]]
$sourceImage = [System.Drawing.Image]::FromFile($sourcePath)
try {
    foreach ($size in $sizes) {
        $bitmap = New-Object System.Drawing.Bitmap($size, $size, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
        $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
        try {
            $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
            $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
            $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
            $graphics.Clear([System.Drawing.Color]::White)
            $scale = [Math]::Min($size / [double]$sourceImage.Width, $size / [double]$sourceImage.Height)
            $width = [single]($sourceImage.Width * $scale)
            $height = [single]($sourceImage.Height * $scale)
            $left = [single](($size - $width) / 2)
            $top = [single](($size - $height) / 2)
            $graphics.DrawImage($sourceImage, $left, $top, $width, $height)

            $stream = New-Object System.IO.MemoryStream
            try {
                $bitmap.Save($stream, [System.Drawing.Imaging.ImageFormat]::Png)
                $images.Add($stream.ToArray())
            } finally { $stream.Dispose() }
        } finally {
            $graphics.Dispose()
            $bitmap.Dispose()
        }
    }
    foreach ($size in @(192, 512)) {
        $bitmap = New-Object System.Drawing.Bitmap($size, $size, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
        $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
        try {
            $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
            $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
            $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
            $graphics.Clear([System.Drawing.Color]::White)
            $scale = [Math]::Min($size / [double]$sourceImage.Width, $size / [double]$sourceImage.Height)
            $width = [single]($sourceImage.Width * $scale)
            $height = [single]($sourceImage.Height * $scale)
            $left = [single](($size - $width) / 2)
            $top = [single](($size - $height) / 2)
            $graphics.DrawImage($sourceImage, $left, $top, $width, $height)
            $pngPath = Join-Path $projectRoot "public\images\clothease-app-icon-$size.png"
            $bitmap.Save($pngPath, [System.Drawing.Imaging.ImageFormat]::Png)
        } finally {
            $graphics.Dispose()
            $bitmap.Dispose()
        }
    }
} finally { $sourceImage.Dispose() }

$output = New-Object System.IO.MemoryStream
$writer = New-Object System.IO.BinaryWriter($output)
try {
    $writer.Write([UInt16]0)
    $writer.Write([UInt16]1)
    $writer.Write([UInt16]$sizes.Count)
    $offset = 6 + (16 * $sizes.Count)
    for ($i = 0; $i -lt $sizes.Count; $i++) {
        $dimension = if ($sizes[$i] -eq 256) { 0 } else { [byte]$sizes[$i] }
        $writer.Write([byte]$dimension)
        $writer.Write([byte]$dimension)
        $writer.Write([byte]0)
        $writer.Write([byte]0)
        $writer.Write([UInt16]1)
        $writer.Write([UInt16]32)
        $writer.Write([UInt32]$images[$i].Length)
        $writer.Write([UInt32]$offset)
        $offset += $images[$i].Length
    }
    foreach ($image in $images) { $writer.Write($image) }
    [System.IO.File]::WriteAllBytes($iconPath, $output.ToArray())
    [System.IO.File]::WriteAllBytes($faviconPath, $output.ToArray())
} finally {
    $writer.Dispose()
    $output.Dispose()
}

Write-Host "Created Windows app icon: $iconPath"
Write-Host "Created browser favicon: $faviconPath"
