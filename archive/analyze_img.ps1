[Reflection.Assembly]::LoadWithPartialName("System.Drawing") | Out-Null
$imgPath = "C:\Users\guian\.gemini\antigravity\brain\a47d9088-47b4-441e-9fd1-08cc1e9a6713\media__1780234045557.png"
$img = New-Object System.Drawing.Bitmap($imgPath)
$w = $img.Width
$h = $img.Height
$y = [int]($h / 2)
$steps = 40
$line = ""
for ($i = 0; $i -lt $steps; $i++) {
    $x = [int]($i * $w / $steps)
    $pixel = $img.GetPixel($x, $y)
    $r = $pixel.R
    $g = $pixel.G
    $b = $pixel.B
    # Check if pixel is white/light vs dark
    if ($r -gt 200 -and $g -gt 200 -and $b -gt 200) {
        $line += "W" # White
    } elseif ($r -lt 50 -and $g -lt 50 -and $b -lt 50) {
        $line += "." # Dark
    } else {
        $line += "?" # Midtone
    }
}
Write-Output "Image size: ${w}x${h}"
Write-Output "Profile: $line"
$img.Dispose()
