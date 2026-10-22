param([string]$OpenSslPath)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$certificatePath = Join-Path $projectRoot 'ssl\apps.crt'
$privateKeyPath = Join-Path $projectRoot 'ssl\apps.key'
if ((Test-Path -LiteralPath $certificatePath) -or (Test-Path -LiteralPath $privateKeyPath)) {
    throw 'Certificate files already exist. This script will not overwrite them.'
}
if (-not $OpenSslPath) {
    $command = Get-Command openssl -ErrorAction SilentlyContinue
    if ($command) { $OpenSslPath = $command.Source }
}
if (-not $OpenSslPath) {
    foreach ($candidate in @('C:\Program Files\Git\usr\bin\openssl.exe', 'C:\xampp_8.1\apache\bin\openssl.exe')) {
        if (Test-Path -LiteralPath $candidate) { $OpenSslPath = $candidate; break }
    }
}
if (-not $OpenSslPath) { throw 'Pass -OpenSslPath with the path to openssl.exe.' }
& $OpenSslPath req -x509 -newkey rsa:2048 -nodes -keyout $privateKeyPath -out $certificatePath -days 14 -subj '/CN=apps' -addext 'subjectAltName=DNS:apps,DNS:localhost,IP:127.0.0.1'
if ($LASTEXITCODE -ne 0) { throw 'OpenSSL certificate generation failed.' }
Write-Output 'Created an untrusted local development certificate, valid for 14 days. Use hospital-issued certificates in production.'
