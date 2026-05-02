$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$cloudflared = Join-Path $PSScriptRoot 'cloudflared.exe'
$stdoutLog = Join-Path $PSScriptRoot 'cloudflared.out'
$stderrLog = Join-Path $PSScriptRoot 'cloudflared.err'
$originUrl = 'http://localhost:8000/'

if (-not (Test-Path $cloudflared)) {
    throw "cloudflared.exe was not found at $cloudflared"
}

foreach ($file in @($stdoutLog, $stderrLog)) {
    if (Test-Path $file) {
        Remove-Item -LiteralPath $file -Force
    }
}

Get-Process | Where-Object {
    $_.Path -eq $cloudflared
} | Stop-Process -Force

$process = Start-Process -FilePath $cloudflared `
    -ArgumentList @('tunnel', '--url', $originUrl, '--no-autoupdate') `
    -WorkingDirectory $projectRoot `
    -WindowStyle Hidden `
    -RedirectStandardOutput $stdoutLog `
    -RedirectStandardError $stderrLog `
    -PassThru

$publicUrl = $null

for ($i = 0; $i -lt 30; $i++) {
    Start-Sleep -Seconds 1

    if (-not (Test-Path $stderrLog)) {
        continue
    }

    $match = Select-String -Path $stderrLog -Pattern 'https://[a-z0-9.-]+\.trycloudflare\.com' | Select-Object -First 1

    if ($match) {
        $publicUrl = $match.Matches[0].Value
        break
    }
}

if (-not $publicUrl) {
    throw "Tunnel started but no public URL was found yet. Check $stderrLog"
}

Write-Output "PID=$($process.Id)"
Write-Output "Open this on your phone:"
Write-Output "$publicUrl"
