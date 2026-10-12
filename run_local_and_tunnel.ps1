param(
    [int]$Puerto = 8080,
    [switch]$Abrir
)

$ErrorActionPreference = 'Stop'
$baseDir = $PSScriptRoot
$cloudflaredExe = Join-Path $baseDir "cloudflared.exe"
$logFile = Join-Path $baseDir "cloudflared_run.log"
$phpOut = Join-Path $baseDir "php_server.out"
$phpErr = Join-Path $baseDir "php_server.err"
$runtimeFile = Join-Path $baseDir "runtime_links.json"
$tunnelLive = Join-Path $baseDir "tunnel_live.txt"
$tunnelHistory = Join-Path $baseDir "tunnel_history.txt"
$docRoot = Join-Path $baseDir "LOGIN"

function Info($text) {
    Write-Host $text -ForegroundColor Cyan
}

function Warn($text) {
    Write-Host $text -ForegroundColor Yellow
}

function Fail($text) {
    Write-Host $text -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "════════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  INICIAR LOCAL + TÚNEL CLOUDFLARE" -ForegroundColor Cyan
Write-Host "════════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

if (-not (Test-Path $cloudflaredExe)) {
    Fail "[ERROR] No se encontró cloudflared.exe en esta carpeta."
}

if (-not (Test-Path $docRoot)) {
    Fail "[ERROR] No se encontró la carpeta de la aplicación local: $docRoot"
}

$phpExe = (Get-Command php -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Path -ErrorAction SilentlyContinue)
if (-not $phpExe) {
    $phpExe = "C:\xampp\php\php.exe"
}

if (-not (Test-Path $phpExe)) {
    Fail "[ERROR] No se encontró PHP. Instala XAMPP o agrega PHP a tu PATH."
}

Info "Puerto local: http://127.0.0.1:$Puerto"
Info "Carpeta del proyecto: $docRoot"
Info "cloudflared: $cloudflaredExe"
Write-Host ""

Warn "Deteniendo procesos PHP y Cloudflare activos..."
Get-Process php -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Get-Process cloudflared -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 1

Remove-Item -Path $logFile,$phpOut,$phpErr -ErrorAction SilentlyContinue

Info "Iniciando servidor PHP..."
Start-Process -FilePath $phpExe `
    -ArgumentList @("-S", "127.0.0.1:$Puerto", "-t", $docRoot) `
    -WorkingDirectory $baseDir `
    -WindowStyle Hidden `
    -RedirectStandardOutput $phpOut `
    -RedirectStandardError $phpErr

$localReady = $false
for ($i = 1; $i -le 15; $i++) {
    try {
        $response = Invoke-WebRequest -Uri "http://127.0.0.1:$Puerto/index.html" -UseBasicParsing -TimeoutSec 5
        if ($response.StatusCode -eq 200) {
            $localReady = $true
            break
        }
    } catch {
        Write-Host "  Esperando servidor local... $i/15" -ForegroundColor Gray
        Start-Sleep -Seconds 1
    }
}

if (-not $localReady) {
    Fail "[ERROR] El servidor PHP no respondió en http://127.0.0.1:$Puerto. Revisa $phpErr"
}

Write-Host ""
Info "Servidor PHP listo en http://127.0.0.1:$Puerto"
Write-Host ""
Info "Creando túnel Cloudflare..."
$process = Start-Process -FilePath $cloudflaredExe `
    -ArgumentList @("tunnel", "--url", "http://127.0.0.1:$Puerto", "--logfile", $logFile, "--protocol", "http2") `
    -WorkingDirectory $baseDir `
    -PassThru `
    -WindowStyle Hidden

$url = $null
for ($i = 1; $i -le 25; $i++) {
    Start-Sleep -Seconds 2
    $content = Get-Content $logFile -Raw -ErrorAction SilentlyContinue
    if ($content -match 'https://[a-z0-9-]+\.trycloudflare\.com') {
        $url = $matches[0]
        break
    }
    Write-Host "  Esperando URL pública... intento $i/25" -ForegroundColor Gray
}

if (-not $url) {
    $errorText = "[ERROR] No se pudo obtener la URL pública de Cloudflare. Revisa $logFile y tu conexión a Internet."
    if ($process -and -not $process.HasExited) {
        $process | Stop-Process -Force -ErrorAction SilentlyContinue
    }
    Fail $errorText
}

Write-Host ""
Write-Host "════════════════════════════════════════════════════════════════" -ForegroundColor Green
Write-Host "  TÚNEL CREADO EXITOSAMENTE" -ForegroundColor Green
Write-Host "════════════════════════════════════════════════════════════════" -ForegroundColor Green
Write-Host ""
Write-Host "URL LOCAL:   http://127.0.0.1:$Puerto" -ForegroundColor White
Write-Host "URL PÚBLICA: $url" -ForegroundColor Cyan
Write-Host ""

if (Test-Path $tunnelLive) {
    $previous = Get-Content $tunnelLive -Raw
    if ($previous.Trim()) {
        Add-Content $tunnelHistory ("[DEAD] " + (Get-Date).ToString("yyyy-MM-dd HH:mm:ss") + " " + $previous.Trim())
    }
}
Set-Content $tunnelLive $url -NoNewline

$runtime = [ordered]@{
    local_base = "http://127.0.0.1:$Puerto"
    public_base = $url
    updated_at = (Get-Date).ToString("s")
}
$runtime | ConvertTo-Json | Set-Content $runtimeFile

Info "URLs guardadas en runtime_links.json"

if ($Abrir) {
    Start-Process "http://127.0.0.1:$Puerto"
    Start-Process $url
}

Write-Host ""
Info "Para revisar tu proyecto siempre usa: http://127.0.0.1:$Puerto"
Info "Para compartirlo usa la URL pública guardada en runtime_links.json"
