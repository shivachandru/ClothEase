$ErrorActionPreference = 'Stop'
$payload = [Console]::In.ReadToEnd() | ConvertFrom-Json
$printerName = [string]$payload.printer
$patterns = @{
    '0'='nnnwwnwnn'; '1'='wnnwnnnnw'; '2'='nnwwnnnnw'; '3'='wnwwnnnnn'; '4'='nnnwwnnnw'; '5'='wnnwwnnnn'; '6'='nnwwwnnnn'; '7'='nnnwnnwnw'; '8'='wnnwnnwnn'; '9'='nnwwnnwnn'
    'A'='wnnnnwnnw'; 'B'='nnwnnwnnw'; 'C'='wnwnnwnnn'; 'D'='nnnnwwnnw'; 'E'='wnnnwwnnn'; 'F'='nnwnwwnnn'; 'G'='nnnnnwwnw'; 'H'='wnnnnwwnn'; 'I'='nnwnnwwnn'; 'J'='nnnnwwwnn'
    'K'='wnnnnnnww'; 'L'='nnwnnnnww'; 'M'='wnwnnnnwn'; 'N'='nnnnwnnww'; 'O'='wnnnwnnwn'; 'P'='nnwnwnnwn'; 'Q'='nnnnnnwww'; 'R'='wnnnnnwwn'; 'S'='nnwnnnwwn'; 'T'='nnnnwnwwn'
    'U'='wwnnnnnnw'; 'V'='nwwnnnnnw'; 'W'='wwwnnnnnn'; 'X'='nwnnwnnnw'; 'Y'='wwnnwnnnn'; 'Z'='nwwnwnnnn'
    '-'='nwnnnnwnw'; '.'='wwnnnnwnn'; ' '='nwwnnnwnn'; '$'='nwnwnwnnn'; '/'='nwnwnnnwn'; '+'='nwnnnwnwn'; '%'='nnnwnwnwn'; '*'='nwnnwnwnn'
}

Add-Type -AssemblyName System.Drawing
$document = New-Object System.Drawing.Printing.PrintDocument
$document.DocumentName = 'ClothEase barcode labels'
$document.PrinterSettings.PrinterName = $printerName
if (-not $document.PrinterSettings.IsValid) { throw "Windows printer '$printerName' is not installed or available." }
$document.DefaultPageSettings.PaperSize = New-Object System.Drawing.Printing.PaperSize('ClothEase 100x25 mm', 394, 98)
$document.DefaultPageSettings.Landscape = $false
$document.DefaultPageSettings.Margins = New-Object System.Drawing.Printing.Margins(0, 0, 0, 0)
$document.OriginAtMargins = $false
$state = [pscustomobject]@{ Row = 0 }
$onPrintPage = {
    param($sender, $eventArgs)
    $graphics = $eventArgs.Graphics
    $graphics.PageUnit = [System.Drawing.GraphicsUnit]::Millimeter
    $graphics.PageScale = 1
    $black = [System.Drawing.Brushes]::Black
    $textFormat = New-Object System.Drawing.StringFormat
    $textFormat.Alignment = [System.Drawing.StringAlignment]::Center
    $textFormat.LineAlignment = [System.Drawing.StringAlignment]::Center
    $textFormat.Trimming = [System.Drawing.StringTrimming]::EllipsisCharacter
    $leftFormat = New-Object System.Drawing.StringFormat
    $leftFormat.Alignment = [System.Drawing.StringAlignment]::Near
    $leftFormat.LineAlignment = [System.Drawing.StringAlignment]::Center
    $leftFormat.Trimming = [System.Drawing.StringTrimming]::EllipsisCharacter
    $titleFont = New-Object System.Drawing.Font('Arial', 10, [System.Drawing.FontStyle]::Bold, [System.Drawing.GraphicsUnit]::Point)
    $detailFont = New-Object System.Drawing.Font('Arial', 8, [System.Drawing.FontStyle]::Bold, [System.Drawing.GraphicsUnit]::Point)
    $priceFont = New-Object System.Drawing.Font('Arial', 8, [System.Drawing.FontStyle]::Bold, [System.Drawing.GraphicsUnit]::Point)
    $codeFont = New-Object System.Drawing.Font('Arial', 6.5, [System.Drawing.FontStyle]::Bold, [System.Drawing.GraphicsUnit]::Point)
    $code = [string]$payload.code
    $price = 'Rs. {0:N2}' -f [decimal]$payload.price
    $encoded = '*' + $code + '*'
    $units = 15 * $encoded.Length + ($encoded.Length - 1)
    $narrow = 44.0 / $units
    $barHeight = 8.7

    for ($column = 0; $column -lt 2; $column++) {
        $left = $column * 50.0
        $graphics.DrawString([string]$payload.name, $titleFont, $black, [System.Drawing.RectangleF]::new($left + 1, 2.2, 48, 4.2), $textFormat)
        $graphics.DrawString([string]$payload.variant, $detailFont, $black, [System.Drawing.RectangleF]::new($left + 3, 6.8, 23, 3.2), $leftFormat)
        $graphics.DrawString($price, $priceFont, $black, [System.Drawing.RectangleF]::new($left + 27, 6.8, 22, 3.2), $textFormat)

        $barcodeWidth = $units * $narrow
        $x = $left + (50.0 - $barcodeWidth) / 2
        foreach ($character in $encoded.ToCharArray()) {
            $pattern = $patterns[[string]$character]
            for ($index = 0; $index -lt $pattern.Length; $index++) {
                $width = if ($pattern[$index] -eq 'w') { $narrow * 3 } else { $narrow }
                if (($index % 2) -eq 0) {
                    $graphics.FillRectangle($black, [single]$x, [single]12.4, [single]$width, [single]$barHeight)
                }
                $x += $width
            }
            $x += $narrow
        }
        $graphics.DrawString($code, $codeFont, $black, [System.Drawing.RectangleF]::new($left + 1, 21.7, 48, 2.7), $textFormat)
    }

    $titleFont.Dispose()
    $detailFont.Dispose()
    $priceFont.Dispose()
    $codeFont.Dispose()
    $textFormat.Dispose()
    $leftFormat.Dispose()
    $state.Row++
    $eventArgs.HasMorePages = $state.Row -lt [int]$payload.rows
}.GetNewClosure()

$document.add_PrintPage([System.Drawing.Printing.PrintPageEventHandler]$onPrintPage)
try {
    $document.Print()
} finally {
    $document.Dispose()
}
