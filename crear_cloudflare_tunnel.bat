@echo off
REM ============================================================
REM Cloudflare Tunnel - Script por Lotes (BAT)
REM Uso: hacer doble clic o ejecutar en CMD
REM ============================================================
REM Este script crea un tunel Cloudflare para exponer tu 
REM servidor local a internet de forma automatica.
REM ============================================================

echo.
echo ==================================================
echo   CREADOR DE TUNEL CLOUDFLARE
echo ==================================================
echo.

REM Verificar cloudflared
if not exist cloudflared.exe (
    echo [ERROR] No se encontro cloudflared.exe
    echo.
    echo DESCARGALO DESDE:
    echo   https://github.com/cloudflare/cloudflared/releases
    echo.
    echo INSTRUCCIONES:
    echo   1. Ve a la pagina de releases
    echo   2. Descarga la version para Windows (amd64)
    echo   3. Extrae cloudflared.exe a esta carpeta
    echo.
    pause
    exit /b 1
)

REM Puerto por defecto
set PUERTO=8080

REM Verificar parametros
if not "%1"=="" set PUERTO=%1

echo [INFO] Puerto: %PUERTO%

REM Cerrar tuneles anteriores
echo.
echo [INFO] Cerrando tuneles anteriores...
taskkill /F /IM cloudflared.exe 2>nul

REM Esperar un momento
timeout /t 2 /nobreak >nul

REM Crear el tunel
echo.
echo [INFO] Creando tunel Cloudflare...
echo [INFO] Ejecuta en navegador: http://127.0.0.1:%PUERTO%
echo.

start /b cloudflared.exe tunnel --url http://127.0.0.1:%PUERTO% --protocol http2

REM Esperar por la URL
set URL=
set CONTADOR=0

echo Buscando URL publica...

:buscar_url
timeout /t 2 /nobreak >nul
set CONTADOR=%CONTADOR%

for /f "tokens=*" %%a in ('findstr /C:"trycloudflare" cloudflared_tunnel.log 2^>nul') do set URL=%%a

if not "%URL%"=="" goto encontrado

if %CONTADOR% LSS 20 goto buscar_url

echo.
echo [ERROR] No se pudo obtener la URL
echo Intenta de nuevo o revisal cloudflared_tunnel.log
echo.
pause
exit /b 1

:encontrado
echo.
echo ==================================================
echo   TUNEL CREADO EXITOSAMENTE
echo ==================================================
echo.
echo URL PUBLICA: %URL%
echo URL LOCAL:   http://127.0.0.1:%PUERTO%
echo.
echo Listo para usar!
echo.

REM Guardar en JSON (usando PowerShell para formato correcto)
echo {"local_base":"http://127.0.0.1:%PUERTO%","public_base":"%URL%","updated_at":"%date% %time%"} > runtime_links.json

pause
