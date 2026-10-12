# Guía rápida: phpMyAdmin + SQL + pruebas de reservas

## 1) Ejecutar SQL en phpMyAdmin
1. Abre phpMyAdmin.
2. Selecciona la base de datos **usuarios**.
3. Ve a la pestaña **SQL**.
4. Copia y ejecuta el contenido de `LOGIN/instalar.sql`.
5. Verifica que existan tablas:
   - `usuarios`
   - `reservas`

---

## 2) Insert de usuario de prueba (opcional)
```sql
INSERT INTO usuarios (nombre, email, password, rol, failed_attempts, locked_until)
VALUES ('Usuario Prueba', 'usuario@example.com', '$2y$10$abcdefghijklmnopqrstuv1234567890abcdefghijklmnopqrstu', 'externo', 0, NULL);
```
> Nota: el hash debe ser uno real de `password_hash` si quieres login funcional por SQL manual.

---

## 3) Crear reserva (API)
Endpoint:
- `POST /api/nueva_reserva.php`

Payload JSON:
```json
{
  "fecha": "2026-05-10",
  "hora_inicio": "09:00",
  "hora_fin": "11:00",
  "espacio": "B1",
  "requisitos_adicionales": "Video beam"
}
```

---

## 4) Consultar mis reservas
Endpoint:
- `GET /api/mis_reservas.php`

Devuelve solo reservas del usuario autenticado por sesión.

---

## 5) Consultar disponibilidad
Endpoint:
- `GET /api/disponibilidad.php?fecha=2026-05-10`

No expone datos personales de otros usuarios.
Estados visuales esperados:
- Verde: disponible
- Rojo: ocupado
- Gris: no permitido

---

## 6) Reglas de negocio clave
- Solape de horarios no permitido por espacio.
- Si reservas **B3**, se valida disponibilidad simultánea de **B1** y **B2**.
- Nueva reserva se guarda en estado **Pendiente**.
