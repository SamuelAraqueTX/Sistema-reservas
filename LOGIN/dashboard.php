<?php
require_once 'session.php';
require_auth();

$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Reservas Poli de Auditorios</title>
    <link rel="stylesheet" href="Style.css">
</head>
<?php
// Alias visual (UI) y normalización de rol para activar vistas.
// Importante: no cambia permisos backend; solo hace consistentes las condiciones de render del frontend.
$rolSesionBruto = (string)($user['rol'] ?? '');
$rolSesionNorm  = mb_strtolower(trim($rolSesionBruto), 'UTF-8');
$rolSesionNorm  = str_replace(['_', ' '], '-', $rolSesionNorm);

$rolVisible = match ($rolSesionNorm) {
    'practicante'       => 'Practicante',
    default => $rolSesionBruto
};
?>
<body class="dashboard-body" data-user-role="<?php echo htmlspecialchars($rolVisible, ENT_QUOTES, 'UTF-8'); ?>" data-user-rol-real="<?php echo htmlspecialchars($rolSesionNorm, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Toast notifications container -->
    <div class="toast-container" id="toast-container"></div>
    
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="brand-box">
                <img src="assets/img/logo-institucional.png" alt="Logo institucional" class="brand-logo" onerror="this.style.display='none'">
<h2>Reservas Poli</h2>
            </div>
            <p class="welcome">Hola, <?php echo htmlspecialchars($user['nombre']); ?></p>

<nav class="menu">
                <button class="menu-item active" data-view="inicio">Inicio</button>
<?php if (in_array($rolSesionNorm, ['docente', 'externo', 'administrativo', 'practicante'], true)): ?>
                <button class="menu-item" data-view="mis-reservas">Mis reservas</button>
                <button class="menu-item" data-view="nueva-reserva">Nueva reserva</button>
                <?php endif; ?>
                <button class="menu-item" data-view="disponibilidad">Ver disponibilidad</button>

<?php if (in_array($rolSesionNorm, ['administrativo', 'practicante'], true)): ?>
                <button class="menu-item" data-view="coordinador-dashboard">📊 Dashboard avanzado</button>
                <button class="menu-item admin-view" data-view="admin-reservas">Gestión de reservas</button>
                <button class="menu-item admin-view" data-view="admin-cobros">Cobros</button>
                <button class="menu-item admin-view" data-view="admin-usuarios">Usuarios</button>
                <button class="menu-item admin-view" data-view="admin-recursos">Recursos</button>
                <button class="menu-item admin-view" data-view="admin-dias">Días bloqueados</button>
                <?php endif; ?>
                <div class="menu-dropdown">
                    <button class="menu-item dropdown-toggle" type="button">Opciones</button>
                    <div class="dropdown-content">
                        <button class="menu-item submenu-item" data-view="perfil">Mi perfil</button>
                        <button class="menu-item submenu-item" data-view="password">Cambiar contraseña</button>
<a class="menu-item submenu-item logout-link" href="api/logout.php" onclick="return confirm('¿Estás seguro que quieres cerrar sesión?')">Cerrar sesión</a>
                    </div>
                </div>
            </nav>
        </aside>

        <main class="main-content">
            <!-- ── VISTA: INICIO ───────────────────────────────────── -->
            <section id="view-inicio" class="view active">

                <!-- Banner de bienvenida mejorado -->
                <div class="welcome-banner">
                    <div>
                        <h2>Bienvenido, <?php echo htmlspecialchars($user['nombre']); ?> 👋</h2>
                        <p>Panel de gestión de auditorios · Politécnico Grancolombiano</p>
                    </div>
<span class="welcome-badge"><?php echo htmlspecialchars($rolVisible); ?></span>
                </div>

                <!-- Stats del usuario -->
                <h3 style="font-size:0.85rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px;font-weight:600;">Mis reservas</h3>
                <div class="stats-grid" id="mis-stats-grid">
                    <div class="stat-card stat-total">
                        <span class="stat-num" id="stat-total">—</span>
                        <span class="stat-label">Total reservas</span>
                    </div>
                    <div class="stat-card stat-pendiente">
                        <span class="stat-num" id="stat-pendientes">—</span>
                        <span class="stat-label">Pendientes</span>
                    </div>
                    <div class="stat-card stat-aprobada">
                        <span class="stat-num" id="stat-aprobadas">—</span>
                        <span class="stat-label">Aprobadas</span>
                    </div>
                    <div class="stat-card stat-rechazada">
                        <span class="stat-num" id="stat-rechazadas">—</span>
                        <span class="stat-label">Rechazadas</span>
                    </div>
                </div>

                <!-- Próxima reserva -->
                <div id="proxima-reserva-card" class="proxima-card" style="display:none;">
                    <p class="proxima-title">📅 Próxima reserva aprobada</p>
                    <div class="proxima-body" id="proxima-body"></div>
                </div>

<!-- Stats del sistema (admin y practicante) -->
<?php if (in_array($rolSesionNorm, ['administrativo', 'practicante'], true)): ?>
                <h3 style="font-size:0.85rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin:24px 0 12px;font-weight:600;">Estado del sistema</h3>
                <div class="stats-grid" id="sistema-stats-grid">
                    <div class="stat-card stat-total">
                        <span class="stat-num" id="sys-total">—</span>
                        <span class="stat-label">Total reservas</span>
                    </div>
                    <div class="stat-card stat-pendiente">
                        <span class="stat-num" id="sys-pendientes">—</span>
                        <span class="stat-label">Pendientes aprobación</span>
                    </div>
                    <div class="stat-card stat-aprobada">
                        <span class="stat-num" id="sys-aprobadas">—</span>
                        <span class="stat-label">Aprobadas</span>
                    </div>
                    <div class="stat-card stat-hoy">
                        <span class="stat-num" id="sys-hoy">—</span>
                        <span class="stat-label">Reservas hoy</span>
                    </div>
                </div>
                <div class="espacios-grid" id="espacios-grid"></div>
                <?php endif; ?>
            </section>

<?php if (in_array($rolSesionNorm, ['docente', 'externo', 'administrativo', 'practicante'], true)): ?>
            <section id="view-mis-reservas" class="view active">
                <h1 class="section-header">
                    Mis reservas
                    <div class="calendar-nav">
                        <button id="prev-week" class="week-btn">← Anterior</button>
                        <button id="today-week" class="week-btn week-btn-today">Hoy</button>
                        <button id="next-week" class="week-btn">Siguiente →</button>
                    </div>
                </h1>
                <div id="mis-reservas-calendar" class="calendar-grid"></div>
                <div id="mis-reservas-list" class="card-list"></div>
            </section>

            <section id="view-nueva-reserva" class="view">
                <h1>
                    Nueva reserva
<button id="btn-calificar-servicio" class="rate-btn">★ Calificar Servicio</button>
                </h1>
                <div class="form-card centered">
                    <form id="nueva-reserva-form">
                        <label>Fecha</label>
                        <input type="date" name="fecha" required>

                        <label>Hora inicio</label>
                        <input type="time" name="hora_inicio" min="08:00" max="21:00" required>

                        <label>Hora fin</label>
                        <input type="time" name="hora_fin" min="08:00" max="21:00" required>

                        <label>Espacio</label>
                        <select name="espacio" required>
                            <option value="">Seleccione</option>
<option value="B1">B1 - Capacidad (50)</option>
                            <option value="B2">B2 - Capacidad (50)</option>
                            <option value="B3">B3 - Capacidad (100)</option>
                        </select>

                        <?php if ($rolSesionNorm !== 'externo'): ?>
                            <label>Tipo de ocupación</label>
                            <select name="tipo_ocupacion" required>
                                <option value="">Seleccione tipo</option>
                                <option value="Clases particulares">Clases particulares</option>
                                <option value="Evento">Evento</option>
                                <?php if ($rolSesionNorm !== 'docente'): ?>
                                    <option value="Ocupación de logística">Ocupación de logística</option>
                                    <option value="Ocupación de infraestructura">Ocupación de infraestructura</option>
                                    <option value="Ocupación de medios audiovisuales">Ocupación de medios audiovisuales</option>
                                <?php endif; ?>
                            </select>
                        <?php else: ?>
                            <input type="hidden" name="tipo_ocupacion" value="Evento">
                        <?php endif; ?>

                        <p id="cobro-monto-preview" class="form-msg" style="margin:10px 0 0;"></p>
                        <input type="hidden" name="precio_reserva" id="precio_reserva_input" value="0">

<label>Notas adicionales (opcional)</label>
                        <textarea name="requisitos_adicionales" rows="2" placeholder="Indicaciones especiales..."></textarea>

                        <div id="recursos-seccion" style="display:none;">
                            <label style="margin-top:6px;">Recursos a solicitar</label>
                            <div id="recursos-lista" class="recursos-check-list"></div>
                        </div>

                        <button type="submit" class="primary-btn">Finalizar reserva</button>
                    </form>
                    <p id="nueva-reserva-msg" class="form-msg"></p>
                </div>
            </section>
            <?php endif; ?>

            <section id="view-disponibilidad" class="view">
                <h1>Disponibilidad general</h1>
                <p class="section-desc">Consulta únicamente el estado de los espacios. La edición de tipo de ocupación se realiza desde Gestión de reservas.</p>
                <div class="disp-filter-bar">
                    <label for="disp-date-filter"><strong>Fecha:</strong></label>
                    <input type="date" id="disp-date-filter"
                           value="<?php echo date('Y-m-d'); ?>"
                           min="<?php echo date('Y-m-d'); ?>">
                    <button id="disp-ver-btn" class="primary-btn" style="padding:8px 18px;">Ver disponibilidad</button>
                </div>
                <p class="legend">
                    <span class="badge available">Verde: disponible</span>
                    <span class="badge occupied">Rojo: ocupado</span>
                    <span class="badge blocked">Gris: bloqueado por dependencia B1/B2/B3</span>
                </p>
                <div id="disponibilidad-calendar" class="calendar-grid"></div>
            </section>

            <div id="modal-disponibilidad-info" class="modal" style="display:none;">
                <div class="modal-content">
                    <span class="modal-close" data-modal="modal-disponibilidad-info">&times;</span>
                    <h2>Detalle de disponibilidad</h2>
                    <div id="disp-info-body" style="margin-top:16px; color:#1f2937; font-size:0.95rem;"></div>
                    <div id="disp-edit-section" style="margin-top:20px; display:none;">
                        <label style="font-weight:600; margin-bottom:8px; display:block;">Editar tipo de ocupación</label>
                        <select id="disp-edit-tipo" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #d3daf7; background:#f9faff; font-size:0.95rem;">
                            <option value="Clases particulares">Clases particulares</option>
                            <option value="Evento">Evento</option>
                            <option value="Ocupación de logística">Ocupación de logística</option>
                            <option value="Ocupación de infraestructura">Ocupación de infraestructura</option>
                            <option value="Ocupación de medios audiovisuales">Ocupación de medios audiovisuales</option>
                        </select>
                    </div>
                    <div class="modal-actions" style="margin-top:20px; justify-content:flex-end; gap:10px;">
                        <button type="button" class="btn-secondary" data-modal="modal-disponibilidad-info">Cerrar</button>
                        <button id="disp-save-edit" type="button" class="primary-btn" style="display:none;">Guardar</button>
                    </div>
                </div>
            </div>

<section id="view-perfil" class="view">
                <h1>Mi perfil</h1>
                <div class="info-card">
                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($user['nombre']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
<p><strong>Rol:</strong> <?php echo htmlspecialchars($rolVisible); ?></p>
                </div>
                <h2 style="margin-top:20px;">Historial de reservas</h2>
<div class="filter-bar">
                    <label for="historial-filtro">Filtrar por estado:</label>
                    <select id="historial-filtro">
                        <option value="todas">Todas</option>
                        <option value="Aprobada">Aprobadas</option>
                        <option value="Rechazada">Rechazadas</option>
                        <option value="Pendiente">Pendientes</option>
                        <option value="Cancelada">Canceladas</option>
                    </select>
                </div>
                <div id="perfil-historial" class="card-list"></div>
            </section>

            <section id="view-password" class="view">
                <h1>Cambiar contraseña</h1>
                <div class="form-card centered">
                    <form id="cambiar-password-form">
                        <div class="password-wrapper">
                            <input type="password" id="current-password" placeholder="Contraseña actual" required>
                            <span class="toggle-password" data-target="current-password">👁️</span>
                        </div>
                        <div class="password-wrapper">
                            <input type="password" id="new-password" placeholder="Nueva contraseña" required>
                            <span class="toggle-password" data-target="new-password">👁️</span>
                        </div>
                        <div class="password-wrapper">
                            <input type="password" id="confirm-password" placeholder="Confirmar nueva contraseña" required>
                            <span class="toggle-password" data-target="confirm-password">👁️</span>
                        </div>
                        <button type="submit" class="primary-btn">Cambiar contraseña</button>
                    </form>
                    <p id="password-msg" class="form-msg"></p>
                </div>
            </section>

<?php if (in_array($rolSesionNorm, ['administrativo', 'practicante'], true)): ?>
            <!-- ── VISTA: DASHBOARD AVANZADO (ADMINISTRATIVO / PRACTICANTE) ───────── -->
            <section id="view-coordinador-dashboard" class="view">
                <h1>Dashboard avanzado</h1>
                <div class="coord-dashboard">
                    <!-- KPIs -->
                    <div class="coord-kpis">
                        <div class="coord-kpi kpi-blue">
                            <span class="coord-kpi-icon">📋</span>
                            <span class="coord-kpi-val" id="ck-total">—</span>
                            <span class="coord-kpi-lbl">Total reservas</span>
                        </div>
                        <div class="coord-kpi kpi-green">
                            <span class="coord-kpi-icon">✅</span>
                            <span class="coord-kpi-val" id="ck-tasa">—</span>
                            <span class="coord-kpi-lbl">Tasa de aprobación</span>
                        </div>
                        <div class="coord-kpi kpi-orange">
                            <span class="coord-kpi-icon">🏆</span>
                            <span class="coord-kpi-val" id="ck-espacio">—</span>
                            <span class="coord-kpi-lbl">Espacio más usado</span>
                        </div>
                        <div class="coord-kpi kpi-purple">
                            <span class="coord-kpi-icon">⏰</span>
                            <span class="coord-kpi-val" id="ck-hora-pico">—</span>
                            <span class="coord-kpi-lbl">Hora pico</span>
                        </div>
                        <div class="coord-kpi kpi-pink">
                            <span class="coord-kpi-icon">⭐</span>
                            <span class="coord-kpi-val" id="ck-calificacion">—</span>
                            <span class="coord-kpi-lbl">Calificación promedio</span>
                        </div>
                    </div>

                    <!-- Gráficas fila 1 -->
                    <div class="coord-charts-row">
                        <div class="coord-chart-card">
                            <h3>Reservas por espacio</h3>
                            <canvas id="chart-por-espacio"></canvas>
                        </div>
                        <div class="coord-chart-card">
                            <h3>Reservas por día de semana</h3>
                            <canvas id="chart-por-dia"></canvas>
                        </div>
                    </div>

                    <!-- Gráfica tendencia + por rol -->
                    <div class="coord-charts-row">
                        <div class="coord-chart-card">
                            <h3>Tendencia últimas 8 semanas</h3>
                            <canvas id="chart-tendencia"></canvas>
                        </div>
                        <div class="coord-chart-card">
                            <h3>Reservas por tipo de usuario</h3>
                            <canvas id="chart-por-rol"></canvas>
                        </div>
                    </div>

                    <!-- Heatmap hora × día -->
                    <div class="coord-heatmap-wrap">
                        <h3>Heatmap de ocupación (hora × día de semana)</h3>
                        <div id="coord-heatmap"></div>
                    </div>

                    <!-- Herramientas de decisión -->
                    <div class="coord-decision-card">
                        <h3>🧠 Herramientas de decisión</h3>
                        <div class="decision-items" id="coord-decisions"></div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

<?php if (in_array($rolSesionNorm, ['administrativo', 'practicante'], true)): ?>
            <section id="view-admin-cobros" class="view">
                <div class="crud-header">
                    <h1>Cobros</h1>
                </div>

                <p class="section-desc">Ajusta el precio global de la reserva según el espacio.</p>

                <div class="form-card" style="max-width:520px;">
                    <form id="form-admin-cobros" class="crud-form">
                        <div class="crud-form-grid" style="grid-template-columns:1fr 1fr;">
                            <div class="field-group">
                                <label>Precio B1</label>
                                <input type="number" step="0.01" min="0" id="cobros-precio-b1" required>
                            </div>
                            <div class="field-group">
                                <label>Precio B2</label>
                                <input type="number" step="0.01" min="0" id="cobros-precio-b2" required>
                            </div>
                            <div class="field-group">
                                <label>Precio B3</label>
                                <input type="number" step="0.01" min="0" id="cobros-precio-b3" required>
                            </div>
                            <div class="field-group" style="display:flex;align-items:flex-end;">
                                <button type="submit" class="primary-btn" style="width:100%;">Guardar cobros</button>
                            </div>
                        </div>
                        <p id="admin-cobros-msg" class="form-msg"></p>
                    </form>
                </div>

                <div style="margin-top:26px;">
                    <div class="crud-header" style="margin-bottom:12px;">
                        <h1 style="font-size:1.05rem;">Facturas</h1>
                    </div>

                    <div class="filter-bar" style="margin-top:-6px; margin-bottom:14px;">
                        <div class="filter-group">
                            <label>Estado</label>
                            <select id="cobros-filtro-estado">
                                <option value="">Todos</option>
                                <option value="Pendiente">Pendiente</option>
                                <option value="Pagado">Pagado</option>
                                <option value="No pagado">No pagado</option>
                                <option value="Cancelado">Cancelado</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table class="crud-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Usuario</th>
                                    <th>Espacio</th>
                                    <th>Fecha</th>
                                    <th>Horario</th>
                                    <th>Precio</th>
                                    <th>Estado pago</th>
                                </tr>
                            </thead>
                            <tbody id="cobros-facturas-tbody">
                                <tr><td colspan="7" class="table-empty">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="view-admin-dias" class="view">
                <div class="crud-header">
                    <h1>Días bloqueados</h1>
                </div>
<p class="section-desc">Bloquea fechas específicas en las que no se podrán hacer reservas.</p>

                <div class="form-card" style="max-width:480px;margin-bottom:20px;">
                    <div class="crud-form-grid" style="grid-template-columns:1fr 1fr;">
                        <div class="field-group">
                            <label>Fecha a bloquear</label>
                            <input type="date" id="dia-fecha" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="field-group">
                            <label>Motivo (opcional)</label>
                            <input type="text" id="dia-motivo" placeholder="Ej: Mantenimiento">
                        </div>

                        <!-- Antiguo bloqueo por semana eliminado: ahora se usa 'Bloqueo indefinido' -->
                    </div>
                    <p id="dia-fecha-aviso" class="form-msg" style="margin-top:8px; display:none;"></p>
                    <p id="dias-form-msg" class="form-msg" style="margin-top:8px;"></p>
                    <div style="display:flex;gap:8px;margin-top:12px;">
                        <button class="primary-btn" id="btn-bloquear-dia">Bloquear día</button>
                        <button class="btn-danger" id="btn-bloqueo-indefinido">Bloqueo indefinido</button>
                    </div>
                </div>

                <p id="admin-dias-msg" class="form-msg"></p>
                <div id="admin-dias-semanal" style="margin-bottom:12px;"></div>
                <div class="table-wrapper">
                    <table class="crud-table">
                        <thead><tr><th>Fecha</th><th>Día</th><th>Motivo</th><th>Acción</th></tr></thead>
                        <tbody id="admin-dias-tbody">
                            <tr><td colspan="4" class="table-empty">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="view-admin-reservas" class="view">
                <div class="crud-header">
                    <h1>Gestión de reservas</h1>
                    <span id="admin-reservas-count" class="table-count"></span>
                </div>

                <div class="filter-bar filter-bar--admin">
                    <div class="filter-group">
                        <label>Estado</label>
                        <select id="filtro-estado">
                            <option value="">Todos</option>
                            <option value="Pendiente">Pendiente</option>
                            <option value="Aprobada">Aprobada</option>
                            <option value="Rechazada">Rechazada</option>
                            <option value="Cancelada">Cancelada</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Espacio</label>
                        <select id="filtro-espacio">
                            <option value="">Todos</option>
                            <option value="B1">B1</option>
                            <option value="B2">B2</option>
                            <option value="B3">B3</option>
                        </select>
                    </div>
                    <button class="primary-btn" id="btn-filtrar-reservas" style="align-self:flex-end;">Filtrar</button>
                </div>

                <p id="admin-reservas-msg" class="form-msg"></p>

                <div class="table-wrapper">
                    <table class="crud-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Usuario</th>
                                <th>Espacio</th>
                                <th>Fecha</th>
                                <th>Horario</th>
                                <th>Estado</th>
                                <th>Tipo</th>
                                <th>Precio</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="admin-reservas-tbody">
                            <tr><td colspan="8" class="table-empty">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="view-admin-usuarios" class="view">
                <div class="crud-header">
                    <h1>Gestión de usuarios</h1>
                    <button class="primary-btn" id="btn-nuevo-usuario">+ Nuevo usuario</button>
                </div>

                <!-- SEARCH & FILTER BAR -->
                <div class="usuarios-search-filter-bar">
                    <div class="search-filter-wrapper">
                        <div class="filter-group">
                            <label for="usuarios-filtro-rol">Rol:</label>
                            <select id="usuarios-filtro-rol" class="filter-select">
                                <option value="">Todos</option>
                                <option value="administrativo">Administrativo</option>
                                <option value="practicante">Practicante</option>
                                <option value="docente">Docente</option>
                                <option value="logística">Logística</option>
                                <option value="infraestructura">Infraestructura</option>
                                <option value="medios audiovisuales">Medios audiovisuales</option>
                                <option value="externo">Externo</option>
                            </select>
                        </div>
                        <div class="search-group">
                            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                            <input type="text" id="usuarios-buscar" class="search-input" placeholder="Buscar por nombre, email..." autocomplete="off">
                            <button id="usuarios-limpiar-busca" class="limpiar-btn" style="display:none;">✕</button>
                        </div>
                    </div>
                    <div class="filter-info">
                        <span id="usuarios-contador">0 usuarios</span>
                    </div>
                </div>

                <p id="admin-usuarios-msg" class="form-msg"></p>
                <div class="table-wrapper">
                    <table class="crud-table" id="admin-usuarios-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Creado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="admin-usuarios-tbody">
                            <tr><td colspan="6" class="table-empty">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="view-admin-recursos" class="view">
                <div class="crud-header">
                    <h1>Gestión de recursos</h1>
                    <button class="primary-btn" id="btn-nuevo-recurso">+ Nuevo recurso</button>
                </div>
                <p id="admin-recursos-msg" class="form-msg"></p>
                <div class="table-wrapper">
                    <table class="crud-table" id="admin-recursos-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="admin-recursos-tbody">
                            <tr><td colspan="5" class="table-empty">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>
        </main>
    </div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="js/dashboard.js"></script>
<script src="js/validador-fechas.js"></script>

<!-- Modal para Calificar Servicio -->
<div id="modal-calificar" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <h2>⭐ Calificar Servicio</h2>
        <p>Selecciona tu calificación del 1 al 10:</p>
        <div class="rating-buttons">
            <button class="rating-btn" data-value="1">1</button>
            <button class="rating-btn" data-value="2">2</button>
            <button class="rating-btn" data-value="3">3</button>
            <button class="rating-btn" data-value="4">4</button>
            <button class="rating-btn" data-value="5">5</button>
            <button class="rating-btn" data-value="6">6</button>
            <button class="rating-btn" data-value="7">7</button>
            <button class="rating-btn" data-value="8">8</button>
            <button class="rating-btn" data-value="9">9</button>
            <button class="rating-btn" data-value="10">10</button>
        </div>
        <div id="rating-selection" style="margin:15px 0;font-size:18px;text-align:center;font-weight:bold;"></div>
        <label>Comentario (opcional):</label>
        <textarea id="rating-comment" rows="3" placeholder="Escribe tu comentario..."></textarea>
        <button id="btn-enviar-calificacion" class="primary-btn" disabled>Enviar Calificación</button>
        <p id="rating-msg" class="form-msg"></p>
    </div>
</div>

<script src="js/servicio-cliente.js"></script>

<!-- Modal editar reserva (admin) -->
<div id="modal-editar-reserva" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="modal-close" data-modal="modal-editar-reserva">&times;</span>
        <h2>Editar reserva</h2>
        <form id="form-editar-reserva" class="crud-form">
            <input type="hidden" id="er-id">
            <div class="crud-form-grid">
                <div class="field-group">
                    <label>Usuario</label>
                    <input type="text" id="er-usuario" disabled>
                </div>
                <div class="field-group">
                    <label>Espacio</label>
                    <select id="er-espacio" required>
                        <option value="B1">B1 – Capacidad 50</option>
                        <option value="B2">B2 – Capacidad 50</option>
                        <option value="B3">B3 – Capacidad 100</option>
                    </select>
                </div>
                <div class="field-group">
                    <label>Tipo de ocupación</label>
                    <select id="er-tipo-ocupacion" required>
                        <option value="Clases particulares">Clases particulares</option>
                        <option value="Evento">Evento</option>
                        <option value="Ocupación de logística">Ocupación de logística</option>
                        <option value="Ocupación de infraestructura">Ocupación de infraestructura</option>
                        <option value="Ocupación de medios audiovisuales">Ocupación de medios audiovisuales</option>
                    </select>
                </div>
                <div class="field-group">
                    <label>Fecha</label>
                    <input type="date" id="er-fecha" required>
                </div>
                <div class="field-group">
                    <label>Estado</label>
                    <select id="er-estado" required>
                        <option value="Pendiente">Pendiente</option>
                        <option value="Aprobada">Aprobada</option>
                        <option value="Rechazada">Rechazada</option>
                        <option value="Cancelada">Cancelada</option>
                    </select>
                </div>
                <div class="field-group">
                    <label>Hora inicio</label>
                    <input type="time" id="er-hora-inicio" required>
                </div>
                <div class="field-group">
                    <label>Hora fin</label>
                    <input type="time" id="er-hora-fin" required>
                </div>
                <div class="field-group field-group--full">
                    <label>Recursos adicionales</label>
                    <textarea id="er-requisitos" rows="2" placeholder="Proyector, sonido, etc."></textarea>
                </div>
            </div>
            <p id="modal-er-msg" class="form-msg"></p>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-modal="modal-editar-reserva">Cancelar</button>
                <button type="submit" class="primary-btn">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal CRUD usuarios -->
<div id="modal-usuario" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="modal-close" data-modal="modal-usuario">&times;</span>
        <h2 id="modal-usuario-title">Nuevo usuario</h2>
        <form id="form-usuario" class="crud-form" autocomplete="off">
            <input type="hidden" id="usuario-id">
            <div class="crud-form-grid">
                <div class="field-group">
                    <label>Nombre completo</label>
                    <input type="text" id="usuario-nombre" placeholder="Nombre completo" required>
                </div>
                <div class="field-group">
                    <label>Email</label>
                    <input type="email" id="usuario-email" placeholder="correo@ejemplo.com" required>
                </div>
                <div class="field-group">
                    <label>Contraseña <span id="pass-hint" class="field-hint">(mínimo 8 caracteres)</span></label>
                    <input type="password" id="usuario-password" placeholder="Contraseña" autocomplete="new-password">
                </div>
                <div class="field-group">
                    <label>Rol</label>
                    <select id="usuario-rol" required>
                        <option value="" disabled selected>Selecciona rol</option>
                        <option value="administrativo">Administrativo</option>
                        <option value="practicante">Practicante</option>
                        <option value="docente">Docente</option>
                        <option value="logística">Logística</option>
                        <option value="infraestructura">Infraestructura</option>
                        <option value="medios audiovisuales">Medios audiovisuales</option>
                        <option value="externo">Externo</option>
                    </select>
                </div>
            </div>
            <p id="modal-usuario-msg" class="form-msg"></p>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-modal="modal-usuario">Cancelar</button>
                <button type="submit" class="primary-btn">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal CRUD recursos -->
<div id="modal-recurso" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="modal-close" data-modal="modal-recurso">&times;</span>
        <h2 id="modal-recurso-title">Nuevo recurso</h2>
        <form id="form-recurso" class="crud-form">
            <input type="hidden" id="recurso-id">
            <div class="crud-form-grid">
                <div class="field-group">
                    <label>Nombre</label>
                    <input type="text" id="recurso-nombre" placeholder="Ej: Proyector" required>
                </div>
                <div class="field-group">
                    <label>Cantidad</label>
                    <input type="number" id="recurso-cantidad" placeholder="0" min="0" required>
                </div>
                <div class="field-group field-group--full">
                    <label>Descripción</label>
                    <textarea id="recurso-descripcion" rows="3" placeholder="Descripción del recurso..."></textarea>
                </div>
            </div>
            <p id="modal-recurso-msg" class="form-msg"></p>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-modal="modal-recurso">Cancelar</button>
                <button type="submit" class="primary-btn">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Bloqueo Indefinido (selección por día de la semana) -->
<div id="modal-bloqueo-indefinido" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="modal-close" data-modal="modal-bloqueo-indefinido">&times;</span>
        <h2>Bloqueo indefinido</h2>
        <p>Seleccione el día o los días que desea bloquear indefinidamente:</p>

        <form id="form-bloqueo-indefinido" class="crud-form">
            <div class="weekday-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:18px;">
                <label class="weekday-chip weekday-1"><input type="checkbox" name="dias-semana" value="1"><span>Lunes</span></label>
                <label class="weekday-chip weekday-2"><input type="checkbox" name="dias-semana" value="2"><span>Martes</span></label>
                <label class="weekday-chip weekday-3"><input type="checkbox" name="dias-semana" value="3"><span>Miércoles</span></label>
                <label class="weekday-chip weekday-4"><input type="checkbox" name="dias-semana" value="4"><span>Jueves</span></label>
                <label class="weekday-chip weekday-5"><input type="checkbox" name="dias-semana" value="5"><span>Viernes</span></label>
                <label class="weekday-chip weekday-6"><input type="checkbox" name="dias-semana" value="6"><span>Sábado</span></label>
                <label class="weekday-chip weekday-0"><input type="checkbox" name="dias-semana" value="0"><span>Domingo</span></label>
            </div>
            <p id="modal-bloqueo-msg" class="form-msg" style="margin-top:16px;"></p>
            <div class="modal-actions" style="justify-content:space-between; gap:10px; margin-top:16px;">
                <button type="button" class="primary-btn" id="btn-unblock-weekly">Desbloquear días seleccionados</button>
                <div style="display:flex;gap:10px;">
                    <button type="button" class="btn-secondary" data-modal="modal-bloqueo-indefinido">Cerrar</button>
                    <button type="submit" class="primary-btn">Guardar cambios</button>
                </div>
            </div>
        </form>
    </div>
</div>
</body>
</html>
