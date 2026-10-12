$phpExe = "C:\xampp\php\php.exe"
$docRoot = Join-Path $PSScriptRoot "LOGIN"

Get-Process php -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 1

Start-Process -FilePath $phpExe `
    -ArgumentList @("-S","127.0.0.1:8080","-t","`"$docRoot`"") `
    -WorkingDirectory $PSScriptRoot `
    -WindowStyle Hidden `
    -RedirectStandardOutput "php_server.out" `
    -RedirectStandardError  "php_server.err"

Start-Sleep -Seconds 4

Write-Output "=== PHP PROCESSES ==="
Get-Process php -ErrorAction SilentlyContinue | Select-Object Id,ProcessName | Format-Table | Out-String | Write-Output

Write-Output "=== LOCAL TEST ==="
try {
    $r = Invoke-WebRequest -Uri "http://127.0.0.1:8080/index.html" -UseBasicParsing -TimeoutSec 10
    Write-Output ("LOCAL_STATUS=" + $r.StatusCode)
} catch {
    Write-Output "LOCAL_STATUS=ERR"
    Write-Output $_.Exception.Message
}

$localBase = "http://127.0.0.1:8080"
$publicBase = ""
if (Test-Path "tunnel_live.txt") {
    $publicBase = (Get-Content tunnel_live.txt -Raw).Trim()
}

$runtime = @{
    local_base = $localBase
    public_base = $publicBase
    updated_at = (Get-Date).ToString("s")
}
$runtime | ConvertTo-Json | Set-Content "runtime_links.json"

Write-Output "=== PUBLIC TEST ==="
Write-Output ("URL=" + $publicBase)
if ($publicBase -ne "") {
    try {
        $r = Invoke-WebRequest -Uri ($publicBase + "/index.html") -UseBasicParsing -TimeoutSec 30
        Write-Output ("PUBLIC_INDEX=" + $r.StatusCode)
    } catch {
        if ($_.Exception.Response) {
            Write-Output ("PUBLIC_INDEX=" + [int]$_.Exception.Response.StatusCode)
        } else {
            Write-Output "PUBLIC_INDEX=ERR"
        }
    }
} else {
    Write-Output "PUBLIC_INDEX=NO_URL"
}
