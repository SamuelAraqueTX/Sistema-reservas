param(
    [switch]$Abrir
)

$baseDir = $PSScriptRoot
$urlFile = Join-Path $baseDir "runtime_links.json"

if (-not (Test-Path $urlFile)) {
    Write-Host "No existe runtime_links.json. Primero ejecuta run_local_and_tunnel.ps1." -ForegroundColor Yellow
    exit 1
}

try {
    $data = Get-Content $urlFile -Raw | ConvertFrom-Json
} catch {
    Write-Host "No se pudo leer runtime_links.json. Revisa el archivo." -ForegroundColor Red
    exit 1
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "        LINKS DISPONIBLES" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Local:   $($data.local_base)" -ForegroundColor White
Write-Host "Pública: $($data.public_base)" -ForegroundColor Cyan
Write-Host "Actualizado: $($data.updated_at)" -ForegroundColor Gray
Write-Host ""

if ($Abrir) {
    Start-Process $data.local_base
    if ($data.public_base) {
        Start-Process $data.public_base
    }
}
