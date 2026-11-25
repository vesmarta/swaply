# Starts MySQL, Mailpit and the Swaply dev server (only what is not already running).
$ErrorActionPreference = 'SilentlyContinue'

function Test-Port([int]$port) {
    return [bool](Get-NetTCPConnection -LocalPort $port -State Listen)
}

if (-not (Test-Port 3306)) {
    Write-Host 'Starting MySQL...'
    Start-Process -FilePath 'C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe' `
        -ArgumentList '--defaults-file=C:\ProgramData\MySQL\my.ini' -WindowStyle Hidden
    Start-Sleep -Seconds 5
}

if (-not (Test-Port 1025)) {
    Write-Host 'Starting Mailpit...'
    Start-Process -FilePath "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\axllent.mailpit_Microsoft.Winget.Source_8wekyb3d8bbwe\mailpit.exe" `
        -ArgumentList '--smtp 127.0.0.1:1025 --listen 127.0.0.1:8025' -WindowStyle Hidden
}

Set-Location (Join-Path $PSScriptRoot '..')
symfony serve -d --no-tls --port 8000 | Out-Null

Write-Host ''
Write-Host 'Swaply ready:'
Write-Host '  App:     http://127.0.0.1:8000'
Write-Host '  Emails:  http://127.0.0.1:8025  (Mailpit)'
