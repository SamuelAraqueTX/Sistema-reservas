// Widget de Servicio al Cliente Flotante
class ServicioClienteWidget {
    constructor() {
        this.isOpen = false;
        this.apiUrl = this.detectarRutaAPI();
        this.init();
    }

    // Detectar automáticamente la ruta del API
    detectarRutaAPI() {
        const path = window.location.pathname;
        const loginIndex = path.indexOf('/LOGIN');
        
        if (loginIndex !== -1) {
            return window.location.origin + path.substring(0, loginIndex) + '/LOGIN/api/servicio_cliente.php';
        }
        
        // Fallback
        return '/LOGIN/api/servicio_cliente.php';
    }

    init() {
        this.createWidget();
        this.attachListeners();
    }

    createWidget() {
        // Estilos
        const style = document.createElement('style');
        style.textContent = `
            .servicio-cliente-widget {
                position: fixed;
                bottom: 20px;
                right: 20px;
                font-family: Arial, Helvetica, sans-serif;
                z-index: 9999;
            }

            .sc-button {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 28px;
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                transition: all 0.3s ease;
                position: relative;
            }

            .sc-button:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 16px rgba(102, 126, 234, 0.6);
            }

            .sc-button.active {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            }

            .sc-notification-badge {
                position: absolute;
                top: -5px;
                right: -5px;
                background: #f5576c;
                color: white;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                font-weight: bold;
                display: none;
            }

            .sc-window {
                position: absolute;
                bottom: 80px;
                right: 0;
                width: 420px;
                height: 580px;
                background: white;
                border-radius: 12px;
                box-shadow: 0 5px 40px rgba(0, 0, 0, 0.16);
                display: none;
                flex-direction: column;
                overflow: hidden;
                animation: slideUp 0.3s ease;
            }

            .sc-window.active {
                display: flex;
            }

            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .sc-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 16px;
                font-size: 16px;
                font-weight: bold;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .sc-header-close {
                background: none;
                border: none;
                color: white;
                cursor: pointer;
                font-size: 20px;
                padding: 0;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .sc-content {
                flex: 1;
                overflow-y: auto;
                padding: 16px;
                background: #f9f9f9;
            }

            .sc-content::-webkit-scrollbar {
                width: 6px;
            }

            .sc-content::-webkit-scrollbar-track {
                background: #f1f1f1;
            }

            .sc-content::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 3px;
            }

            .sc-content::-webkit-scrollbar-thumb:hover {
                background: #555;
            }

            .sc-message {
                margin-bottom: 12px;
                padding: 12px;
                border-radius: 8px;
                font-size: 13px;
                line-height: 1.5;
                word-wrap: break-word;
            }

            .sc-message.bot {
                background: #e8f4fd;
                color: #1a237e;
                border-left: 4px solid #667eea;
                text-align: left;
            }

            .sc-message.user {
                background: #667eea;
                color: white;
                margin-left: 20px;
                text-align: right;
                border-left: none;
                border-radius: 8px;
            }

            .sc-form {
                padding: 12px;
                background: white;
                border-top: 1px solid #e0e0e0;
                max-height: 200px;
                overflow-y: auto;
            }

            .sc-form::-webkit-scrollbar {
                width: 6px;
            }

            .sc-form::-webkit-scrollbar-track {
                background: #f1f1f1;
            }

            .sc-form::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 3px;
            }

            .sc-input {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 6px;
                font-size: 13px;
                margin-bottom: 8px;
                font-family: Arial, sans-serif;
            }

            .sc-input:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }

            .sc-textarea {
                resize: none;
                font-family: Arial, sans-serif;
            }

            .sc-button-send {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-weight: bold;
                font-size: 14px;
                transition: all 0.3s ease;
                margin-top: 8px;
            }

            .sc-button-send:hover:not(:disabled) {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            }

            .sc-button-send:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }

            .sc-faq {
                margin-top: 8px;
                padding-top: 8px;
                border-top: 1px solid #ddd;
                max-height: 200px;
                overflow-y: auto;
            }

            .sc-faq::-webkit-scrollbar {
                width: 4px;
            }

            .sc-faq::-webkit-scrollbar-track {
                background: #f1f1f1;
            }

            .sc-faq::-webkit-scrollbar-thumb {
                background: #bbb;
                border-radius: 2px;
            }

            .sc-faq-item {
                padding: 10px;
                background: #f0f4ff;
                border-left: 3px solid #667eea;
                margin-bottom: 8px;
                cursor: pointer;
                border-radius: 4px;
                font-size: 12px;
                transition: all 0.2s ease;
                line-height: 1.4;
            }

            .sc-faq-item:hover {
                background: #e8f0ff;
                transform: translateX(4px);
            }

            @media (max-width: 480px) {
                .sc-window {
                    width: 90vw;
                    height: 70vh;
                    bottom: 70px;
                    right: 10px;
                }
            }
        `;
        document.head.appendChild(style);

        // HTML
        const container = document.createElement('div');
        container.className = 'servicio-cliente-widget';
        container.innerHTML = `
            <button class="sc-button" id="sc-btn-main" title="Servicio al cliente">
                💬
                <span class="sc-notification-badge" id="sc-badge">1</span>
            </button>

            <div class="sc-window" id="sc-window">
                <div class="sc-header">
                    <div>Servicio al Cliente</div>
                    <button class="sc-header-close" id="sc-btn-close">✕</button>
                </div>

                <div class="sc-content" id="sc-messages">
                    <div class="sc-message bot">
                        👋 <strong>¡Hola! Soy POLI-Asistente</strong><br><br>
                        🤖 <em>Selecciona una pregunta abajo para obtener información al instante</em>
                    </div>
                </div>

                <div class="sc-form">
                    <div class="sc-faq" id="sc-faq">
                        <strong style="font-size:13px; display:block; margin-bottom:8px;">📋 PREGUNTAS FRECUENTES:</strong>
                        
                        <div class="sc-faq-item">📊 ¿Cuál es la capacidad de B1, B2 y B3?</div>
                        <div class="sc-faq-item">🔗 ¿Cómo funciona B3?</div>
                        <div class="sc-faq-item">⏰ ¿Cuál es el horario de operación?</div>
                        <div class="sc-faq-item">⏳ ¿Con cuántas horas de anticipación debo reservar?</div>
                        <div class="sc-faq-item">🟡 ¿Qué significa estado Pendiente?</div>
                        <div class="sc-faq-item">🟢 ¿Qué significa estado Aprobada?</div>
                        <div class="sc-faq-item">🔴 ¿Qué significa estado Rechazada?</div>
                        <div class="sc-faq-item">❌ ¿Puedo cancelar mi reserva?</div>
                        <div class="sc-faq-item">⚠️ ¿Por qué dice "no disponible"?</div>
                        <div class="sc-faq-item">📅 ¿Cuánto en el futuro puedo reservar?</div>
                        <div class="sc-faq-item">� ¿Dónde veo mi reserva?</div>
                        <div class="sc-faq-item">📝 ¿Cómo solicito recursos adicionales?</div>
                        <div class="sc-faq-item">🔄 ¿Puedo cambiar la fecha de mi reserva?</div>
                        <div class="sc-faq-item">🚪 ¿Qué pasa si llego tarde al espacio?</div>
                        <div class="sc-faq-item">👥 ¿Puedo reservar B1 y B2 juntos?</div>
                        <div class="sc-faq-item">�📞 ¿Cómo contacto al administrador?</div>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(container);
    }

    attachListeners() {
        const btnMain = document.getElementById('sc-btn-main');
        const btnClose = document.getElementById('sc-btn-close');
        const window = document.getElementById('sc-window');
        const messagesDiv = document.getElementById('sc-messages');
        const faqItems = document.querySelectorAll('.sc-faq-item');

        // Abrir/cerrar
        btnMain.addEventListener('click', () => {
            this.isOpen = !this.isOpen;
            window.classList.toggle('active');
            btnMain.classList.toggle('active');
        });

        btnClose.addEventListener('click', () => {
            this.isOpen = false;
            window.classList.remove('active');
            btnMain.classList.remove('active');
        });

        // FAQs - Click en preguntas
        faqItems.forEach(item => {
            item.addEventListener('click', () => {
                const pregunta = item.textContent.trim();
                this.responderPregunta(pregunta);
            });
        });
    }

    responderPregunta(preguntaTexto) {
        const messagesDiv = document.getElementById('sc-messages');

        // Mostrar pregunta del usuario
        const msgUser = document.createElement('div');
        msgUser.className = 'sc-message user';
        msgUser.innerHTML = '👤 <strong>Tú:</strong><br>' + this.escapeHtml(preguntaTexto);
        messagesDiv.appendChild(msgUser);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;

        // Obtener respuesta automática
        const respuesta = this.obtenerRespuesta(preguntaTexto);

        // Mostrar respuesta del bot
        setTimeout(() => {
            const msgBot = document.createElement('div');
            msgBot.className = 'sc-message bot';
            msgBot.style.background = '#dcfce7';
            msgBot.style.color = '#166534';
            msgBot.style.borderLeft = '4px solid #16a34a';
            msgBot.innerHTML = '🤖 <strong>POLI-Asistente:</strong><br>' + respuesta;
            messagesDiv.appendChild(msgBot);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }, 500);
    }

    obtenerRespuesta(pregunta) {
        const p = pregunta.toLowerCase();

        // B3 (FUNCIONAMIENTO)
        if (p.includes('b3') && p.includes('cómo')) {
            return `🔗 <strong>¿CÓMO FUNCIONA B3?</strong><br>
                    B3 = <strong>B1 + B2 combinados</strong> (100 personas)<br><br>
                    ⚠️ <strong>REQUISITO IMPORTANTE:</strong><br>
                    Para reservar <strong>B3, AMBOS espacios deben estar LIBRES</strong><br><br>
                    ❌ Si B1 está ocupado → <strong>NO puedes</strong> B3<br>
                    ❌ Si B2 está ocupado → <strong>NO puedes</strong> B3<br>
                    ✅ Si AMBOS están libres → <strong>SÍ puedes</strong> B3<br><br>
                    Si apartas B3, B1 y B2 se bloquean en ese horario. 🚫`;
        }

        // CAPACIDAD
        if (p.includes('capacidad') || p.includes('personas')) {
            return `👥 <strong>CAPACIDAD DE ESPACIOS</strong><br><br>
                    <strong>B1:</strong> 50 personas 🏢<br>
                    <strong>B2:</strong> 50 personas 🏢<br>
                    <strong>B3:</strong> 100 personas (B1+B2) 🏢🏢<br><br>
                    Selecciona según tus necesidades.`;
        }

        // HORARIO
        if (p.includes('horario') || p.includes('operación')) {
            return `🕐 <strong>HORARIO DE OPERACIÓN</strong><br><br>
                    ⏰ <strong>Abre:</strong> 08:00 AM<br>
                    ⏰ <strong>Cierra:</strong> 09:00 PM (21:00)<br><br>
                    📅 <strong>Días permitidos:</strong><br>
                    Lunes, Miércoles, Viernes, Sábado y Domingo<br><br>
                    ❌ <strong>Cerrado:</strong> Martes y Jueves`;
        }

        // ANTICIPACIÓN
        if (p.includes('anticipación') || p.includes('cuántas horas') || p.includes('cuánto tiempo')) {
            return `⏳ <strong>TIEMPO MÍNIMO REQUERIDO</strong><br><br>
                    ⚠️ <strong>MÍNIMO 24 HORAS de anticipación</strong><br><br>
                    ❌ <strong>NO permitido:</strong><br>
                    • Hoy<br>
                    • Mañana<br><br>
                    ✅ <strong>PERMITIDO:</strong><br>
                    • Pasado mañana en adelante<br><br>
                    <strong>Ejemplo:</strong><br>
                    Si hoy es lunes → Puedes reservar desde miércoles.`;
        }

        // ESTADO PENDIENTE
        if (p.includes('pendiente')) {
            return `🟡 <strong>ESTADO PENDIENTE</strong><br><br>
                    <strong>¿Qué significa?</strong><br>
                    Tu reserva está esperando aprobación del <strong>administrador</strong>.<br><br>
                    ⏳ <strong>Acciones posibles:</strong><br>
                    • Esperar revisión<br>
                    • Cancelarla si cambias de opinión<br><br>
                    💬 <strong>Notificación:</strong><br>
                    Recibirás email cuando sea aprobada o rechazada.`;
        }

        // ESTADO APROBADA
        if (p.includes('aprobada') && p.includes('qué')) {
            return `🟢 <strong>ESTADO APROBADA</strong><br><br>
                    ✅ <strong>¡Tu reserva fue aceptada!</strong><br><br>
                    ✅ <strong>Significa que:</strong><br>
                    • La reserva está confirmada<br>
                    • El espacio está reservado para ti<br>
                    • Ya aparece en calendario<br><br>
                    📧 <strong>Recibiste email de confirmación</strong><br><br>
                    Puedes ver detalles en: Dashboard → Mis reservas`;
        }

        // ESTADO RECHAZADA
        if (p.includes('rechazada') && p.includes('qué')) {
            return `🔴 <strong>ESTADO RECHAZADA</strong><br><br>
                    ❌ <strong>La reserva NO fue aprobada</strong><br><br>
                    🤔 <strong>Posibles razones:</strong><br>
                    • Conflicto con otra reserva<br>
                    • Horario no disponible<br>
                    • Requisitos no cumplidos<br><br>
                    📧 <strong>Email enviado</strong> con motivo del rechazo<br><br>
                    💡 <strong>Solución:</strong> Intenta con otra fecha/horario`;
        }

        // CANCELAR
        if (p.includes('cancelar') || p.includes('eliminar')) {
            return `❌ <strong>¿PUEDO CANCELAR?</strong><br><br>
                    ✅ <strong>SÍ, si está PENDIENTE</strong><br>
                    Dashboard → Mis reservas → Botón "Cancelar"<br><br>
                    🚫 <strong>NO, si está:</strong><br>
                    • Aprobada<br>
                    • Rechazada<br>
                    • Cancelada<br><br>
                    📞 Para aprobadas, contacta al admin.`;
        }

        // NO DISPONIBLE
        if (p.includes('no disponible') || p.includes('ocupado')) {
            return `⚠️ <strong>HORARIO NO DISPONIBLE</strong><br><br>
                    <strong>Razones:</strong><br>
                    • Otra reserva aprobada<br>
                    • Otra reserva pendiente<br>
                    • B3 bloquea B1/B2<br>
                    • B1/B2 bloquean B3<br><br>
                    💡 <strong>Soluciones:</strong><br>
                    • Prueba otra hora<br>
                    • Prueba otro día<br>
                    • Prueba otro espacio<br><br>
                    Ver: Dashboard → Ver disponibilidad`;
        }

        // VER MI RESERVA
        if (p.includes('dónde veo') || p.includes('ver mi reserva') || p.includes('mi reserva')) {
            return `📌 <strong>VER TU RESERVA</strong><br><br>
                    Ve a <strong>Dashboard → Mis reservas</strong>.<br>
                    Allí puedes ver el estado, fecha y hora de tus reservas.<br>
                    Si está pendiente, espera la aprobación del administrador.`;
        }

        // RECURSOS
        if (p.includes('recursos') || p.includes('solicitar recursos')) {
            return `📝 <strong>SOLICITAR RECURSOS</strong><br><br>
                    En <strong>Nueva reserva</strong> puedes seleccionar recursos disponibles.<br>
                    Si tienes una petición especial, escríbela en "Notas adicionales".`;
        }

        // CAMBIAR FECHA
        if (p.includes('cambiar la fecha') || p.includes('cambiar reserva') || p.includes('fecha de mi reserva')) {
            return `🔄 <strong>CAMBIAR FECHA</strong><br><br>
                    Si necesitas otra fecha, cancela tu reserva pendiente y crea una nueva.<br>
                    Si ya está aprobada, contacta al administrador para apoyarte.`;
        }

        // LLEGAR TARDE
        if (p.includes('llegar tarde') || p.includes('tarde') || p.includes('retraso')) {
            return `⏰ <strong>SI LLEGAS TARDE</strong><br><br>
                    La reserva sigue vigente, pero tu acceso puede depender del administrador del espacio.<br>
                    Lo mejor es avisar antes si crees que vas a llegar tarde.`;
        }

        // B1 Y B2 JUNTOS
        if ((p.includes('b1') && p.includes('b2')) || p.includes('juntos') || p.includes('reservar b1 y b2')) {
            return `👥 <strong>B1 Y B2</strong><br><br>
                    B1 y B2 pueden reservarse por separado.<br>
                    Si necesitas ambos al mismo tiempo, reserva el espacio <strong>B3</strong>.<br>
                    Recuerda: reservar B3 bloquea B1 y B2 en ese horario.`;
        }

        // FUTURO
        if (p.includes('futuro') || p.includes('meses') || p.includes('3 meses')) {
            return `📅 <strong>LÍMITE DE RESERVA FUTURA</strong><br><br>
                    📆 <strong>Máximo 3 MESES en el futuro</strong><br><br>
                    <strong>Ejemplo:</strong><br>
                    • Hoy: 8 de mayo<br>
                    • Puedes reservar: Hasta 8 de agosto<br>
                    • NO puedes: Después de 8 de agosto<br><br>
                    ✅ <strong>Válido:</strong> Próximos 3 meses<br>
                    ❌ <strong>No válido:</strong> Más allá de 3 meses`;
        }

        // CONTACTO
        if (p.includes('contacto') || p.includes('administrador') || p.includes('admin')) {
            return `📞 <strong>CONTACTAR AL ADMINISTRADOR</strong><br><br>
                    📧 <strong>Email:</strong><br>
                    correo de la oficina administrativa<br><br>
                    📍 <strong>Ubicación:</strong><br>
                    Bloque administrativo<br><br>
                    💬 <strong>Este chat:</strong><br>
                    Tus preguntas se envían automáticamente<br><br>
                    ☎️ <strong>Teléfono:</strong><br>
                    Pregunta en recepción`;
        }

        // RESPUESTA POR DEFECTO
        return `🤔 <strong>INFORMACIÓN GENERAL</strong><br><br>
                📋 <strong>Sistema de Reservas Polígran</strong><br><br>
                ✅ <strong>Pasos para reservar:</strong><br>
                1. Dashboard → Nueva reserva<br>
                2. Selecciona fecha (no martes/jueves)<br>
                3. Selecciona hora (8:00 - 21:00)<br>
                4. Selecciona espacio (B1, B2 o B3)<br>
                5. Click "Finalizar reserva"<br><br>
                🟡 Estado inicial: <strong>Pendiente</strong><br>
                ⏳ Admin revisará en 24h<br><br>
                ¿Otra pregunta? Selecciona abajo 👇`;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Inicializar cuando esté listo el DOM
document.addEventListener('DOMContentLoaded', () => {
    new ServicioClienteWidget();
});
