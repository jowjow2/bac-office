$cloudflared = Join-Path $PSScriptRoot 'cloudflared.exe'

Get-Process | Where-Object {
    $_.Path -eq $cloudflared
} | Stop-Process -Force

Write-Output 'Cloudflare tunnel stopped.'
