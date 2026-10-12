# ============================================================
# Cloudflare Tunnel - Script Simplificado
# Uso: .\crear_cloudflare_tunnel.ps1
# ============================================================
# Este script crea un túnel Cloudflare para exponer tu 
# servidor local a internet de forma automática.
# ============================================================

param(
    [string]$Puerto = "8080",
    [switch]$Ayuda
)

# Función para mostrar ayuda
function Mostrar-Ayuda {
    Write-Host @"

╔═══════════════════════════════════════════════════════════╗
║     CLOUDFLARE TUNNEL - SCRIPT SIMPLIFICADO       ║
╚═══════════════════════════════════════════════════════════╝

USO:
    .\crear_cloudflare_tunnel.ps1              - Crear túnel (por defecto puerto 8080)
    .\crear_cloudflare_tunnel.ps1 -Puerto 9000 - Crear túnel en puerto 9000
    .\crear_cloudflare_tunnel.ps1 -Ayuda          - Mostrar esta ayuda

DESCRIPCIÓN:
    Este script automáticamente:
    1. Cierra túneles existentes de Cloudflare
    2. Verifica que PHP esté ejecutándose
    3. Crea un nuevo túnel público
    4. Guarda la URL en runtime_links.json

REQUISITOS:
    ✓ cloudflared.exe en la misma carpeta
    ✓ PHP corriendo (XAMPP o similar)
    ✓ Conexión a internet

EJEMPLOS DE USO:
    [Para humanos]:
        .\crear_cloudflare_tunnel.ps1

    [Para IA]:
        powershell -ExecutionPolicy Bypass -File crear_cloudflare_tunnel.ps1

NOTA: Para crear el túnel manualmente:
    1. Inicia PHP: php -S 127.0.0.1:8080 -t .\LOGIN
    2. En otra terminal: cloudflared tunnel --url http://127.0.0.1:8080
    3. Copia la URL mostrada

"@ -ForegroundColor Cyan
}

# Mostrar ayuda si se solicita
if ($Ayuda) {
    Mostrar-Ayuda
    exit 0
}

# ============================================================
# CONFIGURACIÓN
# ============================================================
$ErrorActionPreference = "Continue"
$baseDir = $PSScriptRoot
$cloudflaredExe = Join-Path $baseDir "cloudflared.exe"
$logFile = Join-Path $baseDir "cloudflared_tunnel.log"
$urlFile = Join-Path $baseDir "runtime_links.json"

Write-Host ""
Write-Host "═" * 50 -ForegroundColor Cyan
Write-Host "  CREADOR DE TÚNEL CLOUDFLARE" -ForegroundColor Cyan
Write-Host "═" * 50 -ForegroundColor Cyan
Write-Host ""

# ============================================================
# VERIFICACIONES INICIALES
# ============================================================

# Verificar cloudflared
if (-not (Test-Path $cloudflaredExe)) {
    Write-Host "[ERROR] No se encontró cloudflared.exe" -ForegroundColor Red
    Write-Host ""
    Write-Host "DESCARGA cloudflared desde:" -ForegroundColor Yellow
    Write-Host "  https://github.com/cloudcloudflare/cloudflared/releases"
    Write-Host ""
    Write-Host "Instructions:"
    Write-Host "  1. Ve a la página de releases"
    Write-Host "  2. Descarga la versión para Windows (amd64)"
    Write-Host "  3. Extrae cloudflared.exe a esta carpeta"
    Write-Host ""
    exit 1
}

# Verificar PHP
$phpProcess = Get-Process php -ErrorAction SilentlyContinue
if (-not $phpProcess) {
    Write-Host "[AVISO] PHP no está corriendo, intentando iniciar..." -ForegroundColor Yellow
    
    $phpExe = "C:\xampp\php\php.exe"
    $docRoot = Join-Path $baseDir "LOGIN"
    
    if (Test-Path $phpExe) {
        Start-Process -FilePath $phpExe -ArgumentList @("-S","127.0.0.1:$Puerto","-t","`"$docRoot`"") `
            -WorkingDirectory $baseDir -WindowStyle Hidden
        
        Start-Sleep -Seconds 3
        Write-Host "[OK] PHP iniciado en puerto $Puerto" -ForegroundColor Green
    } else {
        Write-Host "[ERROR] No se encontró PHP" -ForegroundColor Red
        Write-Host "Por favor inicia PHP manualmente luego intenta de nuevo"
        exit 1
    }
} else {
    Write-Host "[OK] PHP está ejecutándose" -ForegroundColor Green
}

# ============================================================
# LIMPIEZA DE TÚNELES ANTERIORES
# ============================================================
Write-Host ""
Write-Host "Limpiando túneles anteriores..." -ForegroundColor Yellow
Get-Process cloudflared -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 1

# Limpiar log anterior
Remove-Item $logFile -ErrorAction SilentlyContinue
New-Item -Path $logFile -ItemType File -Force | Out-Null

# ============================================================
# CREAR EL TÚNEL
# ============================================================
Write-Host ""
Write-Host "Creando túnel Cloudflare..." -ForegroundColor Cyan
Write-Host "  Puerto local: http://127.0.0.1:$Puerto" -ForegroundColor Gray
Write-Host ""

# Ejecutar cloudflared
$process = Start-Process -FilePath $cloudflaredExe `
    -ArgumentList @("tunnel","--url","http://127.0.0.1:$Puerto","--logfile",$logFile,"--protocol","http2") `
    -WorkingDirectory $baseDir `
    -PassThru `
    -WindowStyle Hidden

# Esperar por la URL
$url = $null
$maxIntentos = 25
Write-Host "Esperando URL pública..." -ForegroundColor Yellow

for ($i = 1; $i -le $maxIntentos; $i++) {
    Start-Sleep -Seconds 2
    $contenido = Get-Content $logFile -Raw -ErrorAction SilentlyContinue
    
    if ($contenido -match 'https://[a-z0-9\-]+\.trycloudflare\.com') {
        $url = $matches[0]
        break
    }
    
    Write-Host "  Intento $i de $maxIntentos..." -ForegroundColor Gray
}

# ============================================================
# RESULTADO
# ============================================================
Write-Host ""

if ($url) {
    Write-Host "╔════════════════════════════════════════════════╗" -ForegroundColor Green
    Write-Host "║        ✓ TÚNEL CREADO EXITOSAMENTE         ║" -ForegroundColor Green
    Write-Host "╚════════════════════════════════════════════════╝" -ForegroundColor Green
    Write-Host ""
    Write-Host "  URL PÚBLICA:" -ForegroundColor White
    Write-Host "  $url" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "  URL LOCAL:" -ForegroundColor White
    Write-Host "  http://127.0.0.1:$Puerto" -ForegroundColor Gray
    Write-Host ""
    
    # Guardar en archivo
    $datos = @{
        local_base = "http://127.0.0.1:$Puerto"
        public_base = $url
        updated_at = (Get-Date).ToString("yyyy-MM-ddTHH:mm:ss")
    }
    $datos | ConvertTo-Json | Set-Content $urlFile
    
    Write-Host "[OK] URLs guardadas en runtime_links.json" -ForegroundColor Green
    
} else {
    Write-Host "[ERROR] No se pudo crear el túnel" -ForegroundColor Red
    Write-Host ""
    Write-Host "POSIBLES SOLUCIONES:" -ForegroundColor Yellow
    Write-Host "  1. Verifica tu conexión a internet"
    Write-Host "  2. Revisa el log: $logFile"
    Write-Host "  3. Intenta de nuevo en unos segundos"
    Write-Host "  4. Verifica que el puerto $Puerto esté libre"
    Write-Host ""
    
    # Mostrar contenido del log si hay error
    if (Test-Path $logFile) {
        $logContent = Get-Content $logFile -Raw
        if ($logContent) {
            Write-Host "═══ CONTENIDO DEL LOG ═══" -ForegroundColor Yellow
            Write-Host $logContent.Substring([Math]::Max(0, $logContent.Length - 500)) -ForegroundColor Gray
        }
    }
}

Write-Host ""
Write-Host "Presiona Enter para salir..." -ForegroundColor Gray
Read-Host | Out-Null
