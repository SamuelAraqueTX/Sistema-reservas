document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ========================================
    // VERIFICAR PROTOCOLO
    // ========================================
    if (window.location.protocol === 'file:') {
        alert('ERROR: Debes abrir con http://localhost o 127.0.0.1, no file://');
        return;
    }
    
    // ========================================
    // ELEMENTOS DEL DOM
    // ========================================
    var signUpContainer = document.querySelector('.sign-up-container');
    var signInContainer = document.querySelector('.sign-in-container');
    var okAccountLinks = document.querySelectorAll('.ok-account');
    var noAccountLinks = document.querySelectorAll('.no-account');
    var notifyCheck = document.querySelector('.check_notify');
    var notifyError = document.querySelector('.error_notify');
    
    // ========================================
    // FUNCIONES GLOBALES
    // ========================================
    function showNotification(element, message) {
        if (!element) return;
        element.textContent = message;
        element.classList.add('active');
        setTimeout(function() {
            element.classList.remove('active');
        }, 5000);
    }
    
    function showSuccess(message) {
        if (notifyCheck) {
            showNotification(notifyCheck, message);
        }
    }
    
    function showError(message) {
        if (notifyError) {
            showNotification(notifyError, message);
        }
    }
    
    // ========================================
    // TOGGLE CONTRASEÑA
    // ========================================
    document.querySelectorAll('.toggle-password').forEach(function(eye) {
        eye.addEventListener('click', function(e) {
            e.preventDefault();
            var inputId = eye.getAttribute('data-target');
            var input = document.getElementById(inputId);
            if (!input) return;
            
            if (input.type === 'password') {
                input.type = 'text';
                eye.textContent = '🙈';
            } else {
                input.type = 'password';
                eye.textContent = '👁️';
            }
        });
    });
    
    // ========================================
    // ALTERNAR ENTRE LOGIN Y REGISTRO
    // ========================================
    okAccountLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            if (signUpContainer) signUpContainer.style.display = 'none';
            if (signInContainer) signInContainer.style.display = 'block';
        });
    });
    
    noAccountLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            if (signInContainer) signInContainer.style.display = 'none';
            if (signUpContainer) signUpContainer.style.display = 'block';
        });
    });
    
    // ========================================
    // FORMULARIO LOGIN
    // ========================================
    var loginForm = document.querySelector('.formulario');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var email = document.getElementById('email') ? document.getElementById('email').value.trim() : '';
            var password = document.getElementById('password') ? document.getElementById('password').value : '';
            var loginErrorMsg = document.getElementById('login-error-msg');
            
            if (!email || !password) {
                showError('Ingresa tu correo y contraseña.');
                return;
            }
            
            var submitBtn = loginForm.querySelector('.submit');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.value = 'Conectando...';
            }
            
            fetch('api/login.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email: email, password: password})
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Error ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.value = 'Iniciar sesión';
                }
                
                if (data.success) {
                    if (loginErrorMsg) loginErrorMsg.style.display = 'none';
                    showSuccess('¡Inicio de sesión exitoso!');
                    setTimeout(function() {
                        window.location.href = data.redirect || 'dashboard.php';
                    }, 700);
                } else {
                    var message = data.message || 'Credenciales incorrectas.';
                    if (loginErrorMsg) {
                        loginErrorMsg.textContent = message;
                        loginErrorMsg.style.display = 'block';
                    }
                    showError(message);
                }
            })
            .catch(function(err) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.value = 'Iniciar sesión';
                }
                console.error('Error:', err);
                showError('Error de conexión. Verifica que el servidor esté activo.');
            });
        });
    }
    
    // ========================================
    // RECUPERAR CONTRASEÑA
    // ========================================
    var forgotLink = document.querySelector('.forgot-password');
    var forgotStep1 = document.getElementById('forgot-step1');
    var forgotStep2 = document.getElementById('forgot-step2');
    var btnForgotCancel = document.getElementById('btn-forgot-cancel');
    var btnForgotBack = document.getElementById('btn-forgot-back');
    var btnEnviarCodigo = document.getElementById('btn-enviar-codigo');
    var btnReenviarCodigo = document.getElementById('btn-reenviar-codigo');
    var btnForgotSubmit = document.getElementById('btn-forgot-submit');
    
    function hideForgotForms() {
        if (forgotStep1) forgotStep1.classList.remove('active');
        if (forgotStep2) forgotStep2.classList.remove('active');
        if (loginForm) loginForm.style.display = '';
        ['forgot-email', 'forgot-codigo', 'forgot-new-pass', 'forgot-confirm-pass'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
    }
    
    function showForgotStep1() {
        if (forgotStep2) forgotStep2.classList.remove('active');
        if (forgotStep1) forgotStep1.classList.add('active');
        if (loginForm) loginForm.style.display = 'none';
    }
    
    function showForgotStep2(email) {
        if (forgotStep1) forgotStep1.classList.remove('active');
        if (forgotStep2) forgotStep2.classList.add('active');
        var emailLabel = document.getElementById('forgot-email-label');
        if (emailLabel) emailLabel.textContent = email;
        var codigoInput = document.getElementById('forgot-codigo');
        if (codigoInput) codigoInput.value = '';
    }
    
    if (forgotLink) {
        forgotLink.addEventListener('click', function(e) {
            e.preventDefault();
            var typedEmail = document.getElementById('email') ? document.getElementById('email').value.trim() : '';
            if (typedEmail) {
                var forgotEmailInput = document.getElementById('forgot-email');
                if (forgotEmailInput) forgotEmailInput.value = typedEmail;
            }
            showForgotStep1();
        });
    }
    
    if (btnForgotCancel) {
        btnForgotCancel.addEventListener('click', function(e) {
            e.preventDefault();
            hideForgotForms();
        });
    }
    
    if (btnForgotBack) {
        btnForgotBack.addEventListener('click', function(e) {
            e.preventDefault();
            showForgotStep1();
        });
    }
    
    if (btnEnviarCodigo) {
        btnEnviarCodigo.addEventListener('click', function(e) {
            e.preventDefault();
            var email = document.getElementById('forgot-email') ? document.getElementById('forgot-email').value.trim() : '';
            
            if (!email) {
                alert('Ingresa tu correo electrónico.');
                return;
            }
            
            btnEnviarCodigo.disabled = true;
            btnEnviarCodigo.textContent = 'Enviando...';
            
            fetch('api/enviar_codigo.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email: email})
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                btnEnviarCodigo.disabled = false;
                btnEnviarCodigo.textContent = 'Enviar código';
                
                if (data.success) {
                    showForgotStep2(email);
                    showSuccess(data.message || 'Código enviado exitosamente.');
                } else {
                    showError(data.message || 'Error al enviar el código.');
                }
            })
            .catch(function(err) {
                btnEnviarCodigo.disabled = false;
                btnEnviarCodigo.textContent = 'Enviar código';
                console.error('Error:', err);
                showError('Error de conexión.');
            });
        });
    }
    
    if (btnReenviarCodigo) {
        btnReenviarCodigo.addEventListener('click', function(e) {
            e.preventDefault();
            var email = document.getElementById('forgot-email') ? document.getElementById('forgot-email').value.trim() : '';
            if (!email) return;
            
            fetch('api/enviar_codigo.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email: email})
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    showSuccess('Código reenviado.');
                } else {
                    showError(data.message || 'Error al reenviar.');
                }
            })
            .catch(function(err) {
                console.error('Error:', err);
                showError('Error de conexión.');
            });
        });
    }
    
    // Solo números en código
    var forgotCodigoInput = document.getElementById('forgot-codigo');
    if (forgotCodigoInput) {
        forgotCodigoInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
        });
    }
    
    if (btnForgotSubmit) {
        btnForgotSubmit.addEventListener('click', function(e) {
            e.preventDefault();
            
            var email = document.getElementById('forgot-email') ? document.getElementById('forgot-email').value.trim() : '';
            var codigo = document.getElementById('forgot-codigo') ? document.getElementById('forgot-codigo').value.trim() : '';
            var newPass = document.getElementById('forgot-new-pass') ? document.getElementById('forgot-new-pass').value : '';
            var confirmPass = document.getElementById('forgot-confirm-pass') ? document.getElementById('forgot-confirm-pass').value : '';
            
            if (!codigo || codigo.length !== 6) {
                alert('Ingresa el código de 6 dígitos.');
                return;
            }
            if (!newPass) {
                alert('Ingresa la nueva contraseña.');
                return;
            }
            if (newPass.length < 8) {
                alert('La contraseña debe tener al menos 8 caracteres.');
                return;
            }
            if (newPass !== confirmPass) {
                alert('Las contraseñas no coinciden.');
                return;
            }
            
            btnForgotSubmit.disabled = true;
            btnForgotSubmit.textContent = 'Verificando...';
            
            fetch('api/recuperar_password.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    email: email,
                    codigo: codigo,
                    new_password: newPass,
                    confirm_password: confirmPass
                })
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                btnForgotSubmit.disabled = false;
                btnForgotSubmit.textContent = 'Cambiar contraseña';
                
                if (data.success) {
                    showSuccess('Contraseña actualizada. Ya puedes iniciar sesión.');
                    setTimeout(function() {
                        hideForgotForms();
                    }, 1800);
                } else {
                    showError(data.message || 'Error al cambiar contraseña.');
                }
            })
            .catch(function(err) {
                btnForgotSubmit.disabled = false;
                btnForgotSubmit.textContent = 'Cambiar contraseña';
                console.error('Error:', err);
                showError('Error de conexión.');
            });
        });
    }
    
    // ========================================
    // REGISTRO
    // ========================================
    var regDatos = {};
    var btnEnviarVerif = document.getElementById('btn-enviar-verif');
    var btnReenviarVerif = document.getElementById('btn-reenviar-verif');
    var btnRegVolver = document.getElementById('btn-reg-volver');
    var btnRegSubmit = document.getElementById('btn-reg-submit');
    var regStep1 = document.getElementById('reg-step1');
    var regStep2 = document.getElementById('reg-step2');
    
    function mostrarRegStep1() {
        if (regStep2) regStep2.style.display = 'none';
        if (regStep1) regStep1.style.display = 'block';
    }
    
    function mostrarRegStep2(email) {
        if (regStep1) regStep1.style.display = 'none';
        if (regStep2) regStep2.style.display = 'block';
        var emailLabel = document.getElementById('reg-email-label');
        if (emailLabel) emailLabel.textContent = email;
        var codigoInput = document.getElementById('reg-codigo');
        if (codigoInput) {
            codigoInput.value = '';
            codigoInput.focus();
        }
    }
    
    if (btnEnviarVerif) {
        btnEnviarVerif.addEventListener('click', function(e) {
            e.preventDefault();
            
            var nombre = document.getElementById('name') ? document.getElementById('name').value.trim() : '';
            var email = document.getElementById('email2') ? document.getElementById('email2').value.trim() : '';
            var password = document.getElementById('password2') ? document.getElementById('password2').value : '';
            var confirmPassword = document.getElementById('confirm_password') ? document.getElementById('confirm_password').value : '';
            var rol = document.getElementById('rol') ? document.getElementById('rol').value.trim() : '';
            
            if (!nombre || !email || !password || !confirmPassword || !rol) {
                alert('Todos los campos son obligatorios.');
                return;
            }
            if (password !== confirmPassword) {
                alert('Las contraseñas no coinciden.');
                return;
            }
            if (password.length < 8) {
                alert('La contraseña debe tener al menos 8 caracteres.');
                return;
            }
            
            btnEnviarVerif.disabled = true;
            btnEnviarVerif.textContent = 'Enviando...';
            
            fetch('api/enviar_verificacion_registro.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email: email})
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                btnEnviarVerif.disabled = false;
                btnEnviarVerif.textContent = 'Verificar correo';
                
                if (data.success) {
                    regDatos = {nombre: nombre, email: email, password: password, confirmPassword: confirmPassword, rol: rol};
                    mostrarRegStep2(email);
                    showSuccess(data.message || 'Código de verificación enviado.');
                } else {
                    showError(data.message || 'Error al enviar el código.');
                }
            })
            .catch(function(err) {
                btnEnviarVerif.disabled = false;
                btnEnviarVerif.textContent = 'Verificar correo';
                console.error('Error:', err);
                showError('Error de conexión.');
            });
        });
    }
    
    if (btnReenviarVerif) {
        btnReenviarVerif.addEventListener('click', function(e) {
            e.preventDefault();
            if (!regDatos.email) return;
            
            fetch('api/enviar_verificacion_registro.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email: regDatos.email})
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    showSuccess('Código reenviado.');
                } else {
                    showError(data.message || 'Error al reenviar.');
                }
            })
            .catch(function(err) {
                console.error('Error:', err);
                showError('Error de conexión.');
            });
        });
    }
    
    if (btnRegVolver) {
        btnRegVolver.addEventListener('click', function(e) {
            e.preventDefault();
            mostrarRegStep1();
        });
    }
    
    // Solo números en código de registro
    var regCodigoInput = document.getElementById('reg-codigo');
    if (regCodigoInput) {
        regCodigoInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
        });
    }
    
    if (btnRegSubmit) {
        btnRegSubmit.addEventListener('click', function(e) {
            e.preventDefault();
            
            var codigo = document.getElementById('reg-codigo') ? document.getElementById('reg-codigo').value.trim() : '';
            
            if (!codigo || codigo.length !== 6) {
                alert('Ingresa el código de 6 dígitos.');
                return;
            }
            
            btnRegSubmit.disabled = true;
            btnRegSubmit.textContent = 'Creando cuenta...';
            
            fetch('api/registro.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    nombre: regDatos.nombre,
                    email: regDatos.email,
                    password: regDatos.password,
                    confirm_password: regDatos.confirmPassword,
                    rol: regDatos.rol,
                    codigo_verificacion: codigo
                })
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                btnRegSubmit.disabled = false;
                btnRegSubmit.textContent = 'Crear cuenta';
                
                if (data.success) {
                    showSuccess(data.message || '¡Registro exitoso! Ya puedes iniciar sesión.');
                    regDatos = {};
                    ['name', 'email2', 'password2', 'confirm_password', 'rol'].forEach(function(id) {
                        var el = document.getElementById(id);
                        if (el) el.value = '';
                    });
                    mostrarRegStep1();
                    setTimeout(function() {
                        if (signInContainer) signInContainer.style.display = 'none';
                        if (signUpContainer) signUpContainer.style.display = 'block';
                    }, 1600);
                } else {
                    showError(data.message || 'Error al registrar.');
                }
            })
            .catch(function(err) {
                btnRegSubmit.disabled = false;
                btnRegSubmit.textContent = 'Crear cuenta';
                console.error('Error:', err);
                showError('Error de conexión.');
            });
        });
    }
    
});
