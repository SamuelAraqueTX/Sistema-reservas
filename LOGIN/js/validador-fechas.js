// Mejorador del formulario de nueva reserva
// Añade validación en tiempo real sobre fechas y días bloqueados

document.addEventListener('DOMContentLoaded', () => {
    const fechaInput = document.querySelector('#nueva-reserva-form input[name="fecha"]');
    
    if (!fechaInput) return; // Si no existe el formulario, salir
    
    // Crear elemento de advertencia
    const advertencia = document.createElement('div');
    advertencia.id = 'fecha-advertencia';
    advertencia.style.cssText = `
        margin-top: 8px;
        padding: 10px 12px;
        border-radius: 6px;
        font-size: 13px;
        display: none;
        animation: slideDown 0.3s ease;
    `;
    
    // Insertar advertencia después del input
    fechaInput.parentNode.insertBefore(advertencia, fechaInput.nextSibling);
    
    function getNombreDia(fecha) {
        const date = new Date(fecha + 'T00:00:00');
        const dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        return dias[date.getDay()];
    }

    // Escuchar cambios en el input de fecha
    fechaInput.addEventListener('change', async () => {
        const fecha = fechaInput.value;

        if (!fecha) {
            advertencia.style.display = 'none';
            return;
        }

        const nombreDia = getNombreDia(fecha);
        advertencia.style.display = 'block';
        advertencia.style.background = '#dcfce7';
        advertencia.style.borderLeft = '4px solid #16a34a';
        advertencia.style.color = '#166534';
        advertencia.innerHTML = `✅ <strong>Fecha válida</strong> - ${nombreDia}`;

        try {
            const res = await fetch(`api/disponibilidad.php?fecha=${encodeURIComponent(fecha)}`);
            const data = await res.json();
            if (data && data.success && data.dia_bloqueado) {
                const motivo = data.motivo_bloqueo ? ` (${data.motivo_bloqueo})` : '';
                advertencia.style.background = '#fee2e2';
                advertencia.style.borderLeft = '4px solid #dc2626';
                advertencia.style.color = '#991b1b';
                advertencia.innerHTML = `⚠️ <strong>Día bloqueado</strong> - ${nombreDia}${motivo}`;
            }
        } catch (error) {
            console.warn('Error consultando disponibilidad:', error);
        }

        // Desaparecer después de 3 segundos
        setTimeout(() => {
            if (advertencia.style.display !== 'none') {
                advertencia.style.display = 'none';
            }
        }, 3000);
    });

    // Nota: no bloqueamos ningún día en el frontend; las reglas reales viven en backend.
    const form = document.getElementById('nueva-reserva-form');
    if (form) {
        // Sin validación de días bloqueados aquí
    }
});

// Agregar estilos CSS globales
const style = document.createElement('style');
style.textContent = `
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(style);
