# Matar cloudflared y php actuales
Get-Process cloudflared -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 2

# Asegurar PHP corriendo
$phpRunning = Get-Process php -ErrorAction SilentlyContinue
if (-not $phpRunning) {
    $phpExe = "C:\xampp\php\php.exe"
    $docRoot = Join-Path $PSScriptRoot "LOGIN"
    Start-Process -FilePath $phpExe `
        -ArgumentList @("-S","127.0.0.1:8080","-t","`"$docRoot`"") `
        -WorkingDirectory $PSScriptRoot `
        -WindowStyle Hidden `
        -RedirectStandardOutput "php_server.out" `
        -RedirectStandardError  "php_server.err"
    Start-Sleep -Seconds 3
}

# Limpiar log anterior
Remove-Item cloudflared_new.log -ErrorAction SilentlyContinue

# Lanzar cloudflared nuevo (Quick Tunnel)
Start-Process -FilePath ".\cloudflared.exe" `
    -ArgumentList @("tunnel","--url","http://127.0.0.1:8080","--logfile","cloudflared_new.log") `
    -WorkingDirectory $PSScriptRoot `
    -WindowStyle Hidden `
    -RedirectStandardOutput "cloudflared_new.out"

# Esperar a que aparezca la URL
$url = $null
for ($i = 0; $i -lt 30; $i++) {
    Start-Sleep -Seconds 2
    $combined = ""
    if (Test-Path cloudflared_new.log) { $combined += (Get-Content cloudflared_new.log -Raw -ErrorAction SilentlyContinue) }
    if (Test-Path cloudflared_new.out) { $combined += (Get-Content cloudflared_new.out -Raw -ErrorAction SilentlyContinue) }
    if ($combined -match 'https://[a-z0-9-]+\.trycloudflare\.com') {
        $url = $matches[0]
        break
    }
}

if ($url) {
    # Guardar URL nueva y archivar la anterior
    if (Test-Path tunnel_live.txt) {
        $old = Get-Content tunnel_live.txt -Raw
        Add-Content tunnel_history.txt ("[DEAD] " + (Get-Date) + " " + $old.Trim())
    }
    Set-Content tunnel_live.txt $url -NoNewline

    $runtime = @{
        local_base = "http://127.0.0.1:8080"
        public_base = $url
        updated_at = (Get-Date).ToString("s")
    }
    $runtime | ConvertTo-Json | Set-Content "runtime_links.json"

    Write-Output ("NEW_URL=" + $url)

    # Verificar que responde
    Start-Sleep -Seconds 5
    try {
        $r = Invoke-WebRequest -Uri ($url + "/index.html") -UseBasicParsing -TimeoutSec 30
        Write-Output ("PUBLIC_STATUS=" + $r.StatusCode)
    } catch {
        if ($_.Exception.Response) {
            Write-Output ("PUBLIC_STATUS=" + [int]$_.Exception.Response.StatusCode)
        } else {
            Write-Output "PUBLIC_STATUS=ERR"
        }
    }
} else {
    $runtime = @{
        local_base = "http://127.0.0.1:8080"
        public_base = ""
        updated_at = (Get-Date).ToString("s")
    }
    $runtime | ConvertTo-Json | Set-Content "runtime_links.json"

    Write-Output "NEW_URL=NOT_FOUND"
    Write-Output "--- LOG ERR ---"
    Get-Content cloudflared_new.log -ErrorAction SilentlyContinue
    Write-Output "--- LOG OUT ---"
    Get-Content cloudflared_new.out -ErrorAction SilentlyContinue
}
