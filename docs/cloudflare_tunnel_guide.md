# Cloudflare Tunnel - Guía de Uso

Este documento explica cómo crear y gestionar túneles de Cloudflare para exponer tu servidor local a internet.

## Requisitos Previos

1. **cloudflared.exe** - Descárgalo de: https://github.com/cloudflare/cloudflared/releases
   - Busca la versión para Windows (amd64 .exe)
   - Guarda el executable en la carpeta raíz del proyecto

2. **XAMPP** (u otro servidor PHP) instalado

---

## Método 0: Iniciar local y crear túnel con un solo comando

### Ejecutar el script desde PowerShell:
```powershell
.\run_local_and_tunnel.ps1
```

### Ejecutar rápido desde Windows (doble clic o terminal):
```cmd
.\quick_tunnel.bat
```

### Qué hace este script automáticamente:
1. Detiene cualquier servidor PHP o túnel Cloudflare activo
2. Inicia el servidor PHP en `LOGIN` usando el puerto 8080
3. Verifica que el servidor local responda
4. Crea un túnel Cloudflare público hacia `http://127.0.0.1:8080`
5. Guarda las URLs en `runtime_links.json`
6. Actualiza `tunnel_live.txt` y `tunnel_history.txt`

### Cómo ver tus links cuando quieras:
- Ejecuta `.un_local_and_tunnel.ps1` para crear un túnel nuevo.
- Ejecuta `.
un_local_and_tunnel.ps1 -Abrir` para abrir el enlace local y el enlace público en el navegador.
- Ejecuta `.
un_local_and_tunnel.ps1` de nuevo si quieres regenerar una URL pública nueva.
- Usa `.
ew_tunnel.ps1` si prefieres crear solo el túnel sobre el servidor PHP existente.
- Usa `.
un_local_and_tunnel.ps1` siempre para exponer tu proyecto local en `http://127.0.0.1:8080`.

### Mostrar el link guardado:
```powershell
.\show_links.ps1
```

### Abrir el local + público desde el último enlace guardado:
```powershell
.\show_links.ps1 -Abrir
```

---

## Método 1: Crear Túnel Automáticamente

### Ejecutar el script:
```powershell
.\new_tunnel.ps1
```

Esto automáticamente:
1. Cierra túneles existentes
2. Asegura que PHP esté corriendo
3. Crea un nuevo túnel Cloudflare
4. Guarda la URL en `runtime_links.json`

---

## Método 2: Crear Túnel Manualmente (para IA o humano)

### Paso 1: Iniciar PHP manualmente
```powershell
# En una terminal (PowerShell o CMD):
php -S 127.0.0.1:8080 -t "C:\ruta\a\tu\proyecto\LOGIN"
```

O usa el script:
```powershell
.\start_php.ps1
```

### Paso 2:Crear el túnel Cloudflare (terminal nueva)
```bash
# Ejecuta en una terminal CMD (no PowerShell):
cloudflared tunnel --url http://127.0.0.1:8080
```

### Paso 3: Copiar la URL
El script mostrará una URL como:
```
https://your-random-subdomain.trycloudflare.com
```

Copia esa URL y úsala para acceder a tu aplicación desde cualquier lugar.

---

## Comandos Útiles de Cloudflare

### Ver estado del túnel
```bash
cloudflared tunnel info
```

### Listar túneles existentes
```bash
cloudflared tunnel list
```

### Eliminar un túnel
```bash
cloudflared tunnel delete <nombre-del-tunel>
```

### Crear un túnel con nombre específico
```bash
cloudflared tunnel create "mi-reservas"
cloudflared tunnel route ip add --principal your-email@domain.com 127.0.0.1/32 --identifier <tunnel-id>
```

---

## Solución de Problemas

### "cloudflared no se reconoce"
- Asegúrate de que cloudflared.exe esté en el PATH o usa la ruta completa

### "No se puede iniciar PHP"
- Verifica que XAMPP esté instalado y el servicio iniciado
- O inicia PHP manualmente: `php -S 127.0.0.1:8080`

### "URL no aparece"
- Verifica tu conexión a internet
- Revisa el archivo de logs: `cloudflared_new.log`
- Intenta con el protocolo http2: `cloudflared tunnel --url http://127.0.0.1:8080 --protocol http2`

### Túnel se cierra solo
- Mantén la terminal abierta o usa `-WindowStyle Hidden` para background
- El token de autenticación puede ayudar (avanzado)

---

## Notas para Desarrolladores

### Estructura de runtime_links.json
```json
{
    "local_base": "http://127.0.0.1:8080",
    "public_base": "https://xxx.trycloudflare.com",
    "updated_at": "2025-01-15T10:30:00"
}
```

### Puerto alternativo
Si el puerto 8080 está ocupado, cambia en:
- `start_php.ps1`: línea 9 (`127.0.0.1:8080`)
- `new_tunnel.ps1`: línea 16 (`--url http://127.0.0.1:8080`)

### Producción
Para producción, considera:
- Usar Cloudflare Zero Trust con autenticación
- Configurar un dominio propio
- Usar Docker para consistencia
