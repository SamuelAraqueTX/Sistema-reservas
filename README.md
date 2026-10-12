# Reservas Poli

Sistema web para reservar los espacios del auditorio de la institución: B1, B2 y B3. Los usuarios piden una reserva, la administración la aprueba o la rechaza, y todo queda registrado con sus correos, recibos y estadísticas.

Una regla propia del auditorio: B3 es la unión de B1 y B2. Si alguien reserva B3 se ocupan los dos espacios, y si alguien reserva B1 o B2, B3 queda bloqueado en ese horario.

## Equipo

Proyecto académico hecho por cinco personas con metodología Scrum.

- Samuel Araque, Scrum Master. Coordinó al equipo, hizo seguimiento de los roles y entregables y armó el modelo inicial de la base de datos sobre el que se construyó el sistema. También organizó y publicó este repositorio.
- Product Owner. Definió y priorizó los requisitos.
- Programador. Implementó el sistema y la conexión con la base de datos, con ayuda de herramientas de IA para ampliar el esquema.
- Documentador.
- Tester.

La limpieza de seguridad del repositorio y la redacción de este README se hicieron con ayuda de una herramienta de IA.

## Tecnologías

El backend está hecho en PHP 8 con una API de archivos sueltos que responden JSON, y la base de datos es MySQL o MariaDB, accedida con PDO (con mysqli como respaldo). El frontend usa HTML, CSS y JavaScript sin frameworks, y las gráficas del panel salen de Chart.js. Los correos se envían con PHPMailer por SMTP de Gmail. Para mostrar el sistema sin desplegarlo hay scripts que abren un túnel público con Cloudflare.

## Estructura

    Reservas-Poli/
      LOGIN/                  la aplicación (raíz del servidor web)
        index.html            inicio de sesión, registro y recuperación de clave
        dashboard.php         panel principal, cambia según el rol
        recibo_reserva.php    recibo imprimible
        session.php           sesión y control de roles
        config.php            conexión a la base de datos
        api/                  endpoints y envío de correos
        db/                   triggers y tablas adicionales
        js/ y Style.css       interfaz
        instalar.sql          esquema base
      docs/                   guía del túnel de Cloudflare y de phpMyAdmin
      scripts del túnel       .ps1 y .bat para publicar el sistema en local
      cloudflared.exe         (no incluido) opcional, solo para el túnel; descárgalo de github.com/cloudflare/cloudflared/releases y ponlo en la raíz

## Cómo ejecutarlo

Necesitas PHP 8 con las extensiones pdo_mysql, mysqli y mbstring, y MySQL 5.7 o MariaDB 10.3 en adelante. Lo más simple en Windows es instalar XAMPP, que trae todo.

### 1. Base de datos

Crea una base llamada `usuarios` con cotejamiento `utf8mb4_unicode_ci` y ejecuta estos tres pasos en orden.

1. Importa `LOGIN/instalar.sql`, que crea usuarios y reservas.
2. Importa `LOGIN/db/completar_tablas.sql`, que crea el resto: recursos, días bloqueados, recuperación de contraseña, calificaciones y contactos.
3. Ejecuta las migraciones desde la carpeta `LOGIN`:

        php api/ejecutar_migracion.php

   Con XAMPP el ejecutable es `C:\xampp\php\php.exe`. Esto crea las tablas de cobros, códigos y recibos.

Opcionalmente puedes importar `LOGIN/db/triggers_prevent_conflicts.sql`, que repite en la base de datos la validación de choques entre espacios.

Si vas a usar los roles logística, infraestructura o medios audiovisuales, amplía la columna de roles, que en el esquema base es un ENUM sin ellos:

    ALTER TABLE usuarios MODIFY rol VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'externo';

Hazlo después de las migraciones, porque una de ellas vuelve a definir la columna.

### 2. Configuración

Las credenciales no están en el código. Copia `LOGIN/config.local.example.php` como `LOGIN/config.local.php` y completa tus datos. Ese archivo está en `.gitignore` y nunca debe subirse.

Para la base de datos se usan DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASS, con valores por defecto pensados para XAMPP (127.0.0.1, 3306, usuarios, root, sin clave). Para el correo se usan MAIL_USER y MAIL_PASS, que debe ser una contraseña de aplicación de Google y no la clave normal de la cuenta. RATING_TO_EMAIL es opcional y recibe las calificaciones del servicio. VISITORS_EMAIL y SERVICEDESK_URL también son opcionales: si se definen, los correos de reserva muestran el buzón para registrar visitantes y el enlace al portal de solicitudes de medios audiovisuales.

Si no configuras el correo, el sistema funciona igual en modo simulación: no envía nada y deja una copia de cada mensaje en un archivo temporal, algo muy útil para ver los códigos de verificación mientras desarrollas.

### 3. Arrancar

Con XAMPP, copia la carpeta `LOGIN` a `htdocs` y abre `http://localhost/LOGIN/index.html`. Sin XAMPP, desde la carpeta `LOGIN`:

    php -S 127.0.0.1:8080

y abre `http://127.0.0.1:8080/index.html`. Para compartirlo por internet un rato, usa `quick_tunnel.bat` o `run_local_and_tunnel.ps1`.

### 4. Primer administrador

Los roles administrativo y practicante no se pueden registrar desde la página, así que el primero se crea a mano. Genera el hash de la clave:

    php -r "echo password_hash('TuClave', PASSWORD_BCRYPT);"

y guárdalo en la base de datos:

    INSERT INTO usuarios (nombre, email, password, rol)
    VALUES ('Admin', 'admin@example.com', 'EL_HASH', 'administrativo');

Desde ese usuario se crean los demás en la vista Usuarios.

## Qué incluye

Cuentas. Inicio de sesión con bloqueo temporal tras varios intentos fallidos, registro con código de verificación por correo, recuperación de contraseña con código que vence a los 10 minutos, cambio de contraseña y perfil con historial de reservas.

Reservas. Se elige fecha, horario, espacio, tipo de ocupación y recursos como video beam o sonido. La vista de disponibilidad muestra por día qué está libre, ocupado o bloqueado. Cada usuario ve sus reservas y puede cancelar las pendientes. El sistema envía correos de confirmación, aprobación, rechazo y cancelación, y genera un recibo con número propio para las reservas con costo.

Administración. Los administrativos y practicantes aprueban o rechazan reservas, las editan, manejan usuarios con filtros y búsqueda, administran el inventario de recursos y fijan las tarifas con historial de cambios. También bloquean fechas sueltas o días de la semana completos, avisando por correo a quienes quedan afectados, responden los mensajes del servicio al cliente y consultan un panel con estadísticas: reservas totales, tasa de aprobación, espacio más usado y distribución por día y por rol.

## Roles

Administrativo y practicante tienen permisos completos de administración. Docente y externo pueden reservar; el externo es el único que paga y su ocupación siempre es evento. Logística, infraestructura y medios audiovisuales consultan la disponibilidad. Los permisos se validan en el servidor en cada endpoint, no solo escondiendo botones.

## Reglas de las reservas

- Horario de 8:00 a 21:00, con duración de una a cuatro horas.
- Con al menos 24 horas de anticipación y máximo tres meses.
- No se puede reservar en días bloqueados ni en horarios ya ocupados. Solo cuentan las reservas pendientes y aprobadas.
- B3 exige B1 y B2 libres, y B1 o B2 exigen B3 libre.
- Una persona no puede tener dos reservas que se crucen el mismo día.
- Los recursos pedidos no pueden superar el inventario.
- El precio lo decide el servidor según el rol, aunque el navegador envíe otro valor.
- Una reserva pasa de pendiente a aprobada o rechazada, y solo su dueño puede cancelarla mientras esté pendiente.

## Seguridad

Las contraseñas se guardan con bcrypt y todas las consultas son preparadas. El login responde igual si el correo existe o no, para no revelar usuarios. Al reservar se usa una transacción con bloqueo de filas para que dos personas no tomen el mismo horario a la vez. Las migraciones solo se pueden ejecutar por consola o con un administrador con sesión, y los errores internos no se muestran al navegador fuera de localhost.

Quedan pendientes antes de ponerlo en producción: servirlo siempre por HTTPS con cookies de sesión seguras, y agregar protección CSRF y límite de intentos por IP en login, registro y recuperación.

## Pendientes conocidos

El formulario de editar reserva todavía no permite cambiar los recursos asociados; solo los muestra. En tablas con descripciones muy largas, los botones de editar y eliminar pueden quedar encimados. El módulo de cobros tiene aún parte de su lista de pruebas sin marcar en `LOGIN/TODO.md`. Además de este README, el módulo de usuarios tiene guías propias dentro de `LOGIN`: `GUIA_BUSQUEDA_USUARIOS.md` y `MEJORA_USUARIOS_INFO.md`.

La instalación desde cero y los flujos principales (login, reservas, regla B3, aprobación, días bloqueados, recuperación de contraseña, calificaciones y permisos por rol) se verificaron contra MariaDB 10.11.
