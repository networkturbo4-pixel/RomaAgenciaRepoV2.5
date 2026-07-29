<?php
// modules/auth/login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        global $db;
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            header("Location: index.php?module=dashboard&action=index");
            exit();
        } else {
            $error = 'Credenciales inválidas. Por favor intente de nuevo.';
        }
    } else {
        $error = 'Por favor ingrese su correo y contraseña.';
    }
}
?>
<?php global $global_settings; ?>
<!DOCTYPE html>
<html class="light" lang="es">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport"/>
    <?php 
    $site_name_seo = $global_settings['site_name'] ?? 'Roma Agencia';
    $seo_title = $site_name_seo . ($global_settings['seo_title_suffix'] ?? ' | Gestión Integral para su Empresa');
    $seo_desc = $global_settings['seo_description'] ?? 'Eleve su productividad al siguiente nivel. Gestione sus proyectos, analice datos en tiempo real y coordine a su equipo.';
    $seo_keys = $global_settings['seo_keywords'] ?? 'CRM, Gestión de Proyectos, Análisis de Datos, Productividad, Agencia';
    ?>
    <title><?php echo htmlspecialchars($seo_title); ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo htmlspecialchars($seo_desc); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($seo_keys); ?>">
    <meta name="author" content="<?php echo htmlspecialchars($site_name_seo); ?>">
    <meta name="robots" content="index, follow">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars($seo_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($seo_desc); ?>">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:title" content="<?php echo htmlspecialchars($seo_title); ?>">
    <meta property="twitter:description" content="<?php echo htmlspecialchars($seo_desc); ?>">

    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="<?php echo htmlspecialchars($global_settings['primary_color'] ?? '#004e36'); ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars($global_settings['site_name'] ?? 'RomaAgencia'); ?>">
    <link rel="manifest" href="manifest.php">
    
    <?php if(!empty($global_settings['favicon'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($global_settings['favicon']); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($global_settings['favicon']); ?>">
    <?php endif; ?>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <!-- Material Symbols Outlined -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .soft-elevation {
            box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.04);
        }
        .transition-primary {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        /* Custom Checkbox Style */
        .custom-checkbox:checked {
            background-color: #004e36;
            border-color: #004e36;
        }
        /* Simple fade in animation */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out forwards;
        }
        /* Prevent scroll on body to ensure clean split view if desired */
        body {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        /* Animated Mesh Gradient (Stacked Gradients) */
        @keyframes meshGradient {
            0% {
                background-position: 
                    0% 0%, 
                    100% 100%, 
                    0% 100%, 
                    100% 0%;
            }
            33% {
                background-position: 
                    100% 100%, 
                    0% 50%, 
                    100% 0%, 
                    0% 100%;
            }
            66% {
                background-position: 
                    50% 0%, 
                    100% 0%, 
                    0% 0%, 
                    100% 100%;
            }
            100% {
                background-position: 
                    0% 0%, 
                    100% 100%, 
                    0% 100%, 
                    100% 0%;
            }
        }
        .bg-animated-gradient {
            background-color: #002115;
            background-image: 
                radial-gradient(circle at center, #84d7b2 0%, transparent 60%),
                radial-gradient(circle at center, #096c4d 0%, transparent 65%),
                radial-gradient(circle at center, #004e36 0%, transparent 70%),
                radial-gradient(circle at center, #00684a 0%, transparent 60%);
            background-size: 200% 200%, 250% 250%, 200% 200%, 300% 300%;
            background-repeat: no-repeat;
            animation: meshGradient 20s infinite ease-in-out;
        }
    </style>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "secondary-fixed-dim": "#c3c7c6",
                        "on-tertiary": "#ffffff",
                        "surface-tint": "#096c4d",
                        "tertiary-fixed": "#dde3eb",
                        "primary-fixed-dim": "#84d7b2",
                        "on-primary-fixed": "#002115",
                        "error": "#ba1a1a",
                        "on-secondary-fixed-variant": "#434847",
                        "on-background": "#1c1b1b",
                        "surface-container-highest": "#e5e2e1",
                        "primary-fixed": "#9ff4cd",
                        "surface-container-lowest": "#ffffff",
                        "surface-container-low": "#f6f3f2",
                        "on-primary-fixed-variant": "#005139",
                        "on-error-container": "#93000a",
                        "error-container": "#ffdad6",
                        "on-primary-container": "#90e4be",
                        "on-secondary-container": "#5d6260",
                        "on-surface-variant": "#3f4943",
                        "tertiary-fixed-dim": "#c1c7cf",
                        "on-tertiary-fixed-variant": "#41474e",
                        "surface-dim": "#dcd9d9",
                        "outline-variant": "#bec9c1",
                        "tertiary": "#3e444b",
                        "on-tertiary-container": "#ced4dc",
                        "primary": "#004e36",
                        "inverse-primary": "#84d7b2",
                        "surface-container-high": "#ebe7e7",
                        "inverse-on-surface": "#f3f0ef",
                        "primary-container": "#00684a",
                        "inverse-surface": "#313030",
                        "surface": "#fcf9f8",
                        "surface-variant": "#e5e2e1",
                        "secondary-container": "#dadedc",
                        "tertiary-container": "#555c63",
                        "on-tertiary-fixed": "#161c22",
                        "on-secondary": "#ffffff",
                        "outline": "#6f7a73",
                        "on-primary": "#ffffff",
                        "surface-bright": "#fcf9f8",
                        "secondary": "#5b5f5e",
                        "background": "#fcf9f8",
                        "on-secondary-fixed": "#181c1c",
                        "on-error": "#ffffff",
                        "surface-container": "#f0edec",
                        "on-surface": "#1c1b1b",
                        "secondary-fixed": "#dfe3e1"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "gutter": "24px",
                        "md": "24px",
                        "lg": "40px",
                        "sm": "12px",
                        "xl": "64px",
                        "container-max": "1280px",
                        "xs": "4px",
                        "base": "8px"
                    },
                    "fontFamily": {
                        "title-md": ["Inter"],
                        "display-lg": ["Inter"],
                        "headline-lg-mobile": ["Inter"],
                        "body-md": ["Inter"],
                        "label-sm": ["Inter"],
                        "headline-lg": ["Inter"],
                        "body-lg": ["Inter"]
                    },
                    "fontSize": {
                        "title-md": ["20px", {"lineHeight": "28px", "fontWeight": "600"}],
                        "display-lg": ["48px", {"lineHeight": "56px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                        "headline-lg-mobile": ["28px", {"lineHeight": "36px", "fontWeight": "700"}],
                        "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                        "label-sm": ["14px", {"lineHeight": "20px", "letterSpacing": "0.01em", "fontWeight": "500"}],
                        "headline-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.01em", "fontWeight": "700"}],
                        "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}]
                    }
                },
            },
        }
    </script>
</head>
<body class="bg-background font-body-md text-on-surface overflow-x-hidden md:overflow-hidden">
<!-- Main Container: Split Screen Layout -->
<main class="min-h-screen md:h-screen w-full flex flex-col md:flex-row">
    <!-- Left Pane: Hero Section -->
    <section class="hidden md:flex w-full md:w-1/2 bg-animated-gradient flex-col justify-end p-lg md:p-xl relative overflow-hidden order-2 md:order-1 min-h-[409px] md:h-full">
        <!-- Subtle atmospheric overlay -->
        <div class="absolute inset-0 opacity-10 pointer-events-none">
            <div class="absolute inset-0 bg-gradient-to-tr from-black/40 to-transparent"></div>
        </div>
        <!-- Content Container -->
        <div class="relative z-10 max-w-xl animate-fade-in-up" style="animation-delay: 0.1s;">
            <h1 class="font-display-lg text-display-lg text-white mb-md leading-tight md:text-[56px]">
                Eleve su productividad al siguiente nivel.
            </h1>
            <p class="font-body-lg text-body-lg text-on-primary-container mb-md opacity-90 max-w-lg">
                Gestione sus proyectos, analice datos en tiempo real y coordine a su equipo en una plataforma diseñada para la excelencia operativa.
            </p>
        </div>
        <!-- Branding Accent -->
        <div class="mt-xl hidden md:block">
            <div class="w-16 h-1 bg-white opacity-40 rounded-full"></div>
        </div>
    </section>

    <!-- Right Pane: Utility Zone (Form) -->
    <section class="flex-1 w-full md:w-1/2 bg-white flex flex-col items-center justify-center p-md md:p-xl order-1 md:order-2">
        <div class="w-full max-w-md space-y-xl">

            <!-- Auth Mode Switcher -->
            <div class="bg-surface-container-low p-1.5 rounded-xl flex items-center soft-elevation animate-fade-in-up" style="animation-delay: 0.1s;">
                <button class="flex-1 py-3 px-4 rounded-lg font-label-sm text-label-sm transition-primary bg-white text-primary soft-elevation" id="toggleAgencia" onclick="switchTab('agencia')">
                    Agencia
                </button>
                <button class="flex-1 py-3 px-4 rounded-lg font-label-sm text-label-sm transition-primary text-secondary hover:text-primary" id="toggleCliente" onclick="switchTab('cliente')">
                    Soy Cliente
                </button>
            </div>

            <?php if ($error): ?>
                <div class="bg-error-container text-on-error-container p-4 rounded-xl font-label-sm flex items-center gap-2 animate-fade-in-up">
                    <span class="material-symbols-outlined">error</span>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Form Section Agencia -->
            <form id="form-agency" action="index.php?module=auth&action=login" method="POST" class="space-y-lg animate-fade-in-up" style="animation-delay: 0.2s;">
                <div class="space-y-md">
                    <!-- Email Field -->
                    <div class="space-y-xs">
                        <label class="font-label-sm text-label-sm text-on-surface-variant block" for="email">Correo Electrónico</label>
                        <div class="relative group">
                            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline group-focus-within:text-primary transition-colors">
                                mail
                            </span>
                            <input class="w-full pl-12 pr-4 py-4 rounded-xl border border-outline-variant bg-surface focus:ring-2 focus:ring-primary/10 focus:border-primary outline-none transition-primary font-body-md text-on-surface" id="email" name="email" placeholder="ejemplo@roma.com" type="email" required/>
                        </div>
                    </div>
                    <!-- Password Field -->
                    <div class="space-y-xs">
                        <label class="font-label-sm text-label-sm text-on-surface-variant block" for="password">Contraseña</label>
                        <div class="relative group">
                            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline group-focus-within:text-primary transition-colors">
                                lock
                            </span>
                            <input class="w-full pl-12 pr-12 py-4 rounded-xl border border-outline-variant bg-surface focus:ring-2 focus:ring-primary/10 focus:border-primary outline-none transition-primary font-body-md text-on-surface" id="password" name="password" type="password" placeholder="••••••••" required/>
                            <button class="absolute right-4 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface transition-colors" onclick="togglePasswordVisibility()" type="button">
                                <span class="material-symbols-outlined" id="eyeIcon">visibility</span>
                            </button>
                        </div>
                    </div>
                    <!-- Keep Logged In -->
                    <div class="flex items-center space-x-base">
                        <input checked="" class="w-5 h-5 rounded border-outline-variant text-primary focus:ring-primary transition-all custom-checkbox" id="remember" name="remember" type="checkbox"/>
                        <label class="font-label-sm text-label-sm text-on-surface-variant cursor-pointer select-none" for="remember">Mantener sesión iniciada</label>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-md">
                    <button class="w-full py-4 bg-primary text-white font-label-sm text-label-sm rounded-xl hover:bg-primary-container active:scale-[0.98] transition-primary soft-elevation" type="submit">
                        Iniciar Sesión
                    </button>

                    <div class="relative py-2 flex items-center justify-center">
                        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-outline-variant"></div></div>
                        <span class="relative bg-white px-4 text-outline font-label-sm text-[12px] uppercase tracking-widest">O entrar con</span>
                    </div>

                    <button class="w-full py-4 border border-outline-variant text-primary font-label-sm text-label-sm rounded-xl flex items-center justify-center space-x-base hover:bg-surface-container-low transition-primary active:scale-[0.98]" type="button" onclick="loginWithBiometrics()">
                        <span class="material-symbols-outlined" data-weight="fill" style="font-variation-settings: 'FILL' 1;">fingerprint</span>
                        <span>Ingresar con Huella / FaceID</span>
                    </button>
                </div>
            </form>

            <!-- Form Section Cliente -->
            <div id="form-client" class="hidden space-y-lg animate-fade-in-up" style="animation-delay: 0.2s;">
                <p class="text-center text-outline font-label-sm">Ingresa tu DNI para acceder a tu portal.</p>
                
                <div class="flex justify-center">
                    <div id="dni-input" class="text-[32px] font-extrabold tracking-[8px] text-on-surface border-b-2 border-outline-variant w-full text-center h-16 flex items-center justify-center font-[tabular-nums]">
                        <span class="text-outline-variant">------</span>
                    </div>
                </div>
                
                <div class="flex justify-center mt-md">
                    <div class="grid grid-cols-3 gap-4 w-full max-w-[300px]">
                        <button class="h-16 rounded-2xl bg-surface-container hover:bg-surface-container-high active:scale-95 transition-primary font-display-lg text-[24px] text-on-surface soft-elevation" onclick="typeDigit(1)">1</button>
                        <button class="h-16 rounded-2xl bg-surface-container hover:bg-surface-container-high active:scale-95 transition-primary font-display-lg text-[24px] text-on-surface soft-elevation" onclick="typeDigit(2)">2</button>
                        <button class="h-16 rounded-2xl bg-surface-container hover:bg-surface-container-high active:scale-95 transition-primary font-display-lg text-[24px] text-on-surface soft-elevation" onclick="typeDigit(3)">3</button>
                        <button class="h-16 rounded-2xl bg-surface-container hover:bg-surface-container-high active:scale-95 transition-primary font-display-lg text-[24px] text-on-surface soft-elevation" onclick="typeDigit(4)">4</button>
                        <button class="h-16 rounded-2xl bg-surface-container hover:bg-surface-container-high active:scale-95 transition-primary font-display-lg text-[24px] text-on-surface soft-elevation" onclick="typeDigit(5)">5</button>
                        <button class="h-16 rounded-2xl bg-surface-container hover:bg-surface-container-high active:scale-95 transition-primary font-display-lg text-[24px] text-on-surface soft-elevation" onclick="typeDigit(6)">6</button>
                        <button class="h-16 rounded-2xl bg-surface-container hover:bg-surface-container-high active:scale-95 transition-primary font-display-lg text-[24px] text-on-surface soft-elevation" onclick="typeDigit(7)">7</button>
                        <button class="h-16 rounded-2xl bg-surface-container hover:bg-surface-container-high active:scale-95 transition-primary font-display-lg text-[24px] text-on-surface soft-elevation" onclick="typeDigit(8)">8</button>
                        <button class="h-16 rounded-2xl bg-surface-container hover:bg-surface-container-high active:scale-95 transition-primary font-display-lg text-[24px] text-on-surface soft-elevation" onclick="typeDigit(9)">9</button>
                        <button class="h-16 rounded-2xl bg-error-container text-on-error-container hover:bg-[#ffb4ab] active:scale-95 transition-primary flex items-center justify-center soft-elevation" onclick="deleteDigit()"><span class="material-symbols-outlined">backspace</span></button>
                        <button class="h-16 rounded-2xl bg-surface-container hover:bg-surface-container-high active:scale-95 transition-primary font-display-lg text-[24px] text-on-surface soft-elevation" onclick="typeDigit(0)">0</button>
                        <button class="h-16 rounded-2xl bg-primary text-white hover:bg-primary-container active:scale-95 transition-primary flex items-center justify-center soft-elevation submit-btn" onclick="submitClientLogin()"><span class="material-symbols-outlined">arrow_forward</span></button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    // Tab Switching Logic
    function switchTab(mode) {
        const btnAgencia = document.getElementById('toggleAgencia');
        const btnCliente = document.getElementById('toggleCliente');
        const formAgency = document.getElementById('form-agency');
        const formClient = document.getElementById('form-client');
        
        if (mode === 'agencia') {
            btnAgencia.classList.add('bg-white', 'text-primary', 'soft-elevation');
            btnAgencia.classList.remove('text-secondary');
            btnCliente.classList.remove('bg-white', 'text-primary', 'soft-elevation');
            btnCliente.classList.add('text-secondary');
            
            formClient.classList.add('hidden');
            formAgency.classList.remove('hidden');
        } else {
            btnCliente.classList.add('bg-white', 'text-primary', 'soft-elevation');
            btnCliente.classList.remove('text-secondary');
            btnAgencia.classList.remove('bg-white', 'text-primary', 'soft-elevation');
            btnAgencia.classList.add('text-secondary');
            
            formAgency.classList.add('hidden');
            formClient.classList.remove('hidden');
        }
    }

    // Toggle password visibility
    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.textContent = 'visibility_off';
        } else {
            passwordInput.type = 'password';
            eyeIcon.textContent = 'visibility';
        }
    }

    // Keypad Logic
    let dniValue = '';
    const dniDisplay = document.getElementById('dni-input');

    function updateDniDisplay() {
        if (dniValue.length === 0) {
            dniDisplay.innerHTML = '<span class="text-outline-variant">------</span>';
        } else {
            dniDisplay.innerText = dniValue;
        }
    }

    function typeDigit(digit) {
        if (dniValue.length < 15) { 
            dniValue += digit;
            updateDniDisplay();
        }
    }

    function deleteDigit() {
        if (dniValue.length > 0) {
            dniValue = dniValue.slice(0, -1);
            updateDniDisplay();
        }
    }

    function submitClientLogin() {
        if (dniValue.length < 5) {
            alert('Ingrese un DNI válido');
            return;
        }
        
        const btn = document.querySelector('.submit-btn');
        const oldContent = btn.innerHTML;
        btn.innerHTML = '<span class="material-symbols-outlined animate-spin" style="animation: spin 1s linear infinite;">refresh</span>';
        
        fetch('ajax_portal.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=login&dni=${dniValue}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'portal.php';
            } else {
                alert(data.error || 'DNI incorrecto o no autorizado');
                dniValue = '';
                updateDniDisplay();
                btn.innerHTML = oldContent;
            }
        })
        .catch(e => {
            alert('Error de conexión');
            btn.innerHTML = oldContent;
        });
    }

    // Initialize display
    updateDniDisplay();

    // --- WebAuthn Login Logic ---
    function base64ToArrayBuffer(base64) {
        var binary_string = window.atob(base64);
        var len = binary_string.length;
        var bytes = new Uint8Array(len);
        for (var i = 0; i < len; i++) {
            bytes[i] = binary_string.charCodeAt(i);
        }
        return bytes.buffer;
    }

    function arrayBufferToBase64(buffer) {
        var binary = '';
        var bytes = new Uint8Array(buffer);
        var len = bytes.byteLength;
        for (var i = 0; i < len; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    }

    function decodeArgs(obj) {
        const prefix = '=?BINARY?B?';
        const suffix = '?=';
        if (typeof obj === 'string') {
            if (obj.startsWith(prefix) && obj.endsWith(suffix)) {
                let b64 = obj.substring(prefix.length, obj.length - suffix.length);
                b64 = b64.replace(/-/g, '+').replace(/_/g, '/'); // ensure standard base64
                return base64ToArrayBuffer(b64);
            }
        } else if (typeof obj === 'object' && obj !== null) {
            for (let key in obj) {
                obj[key] = decodeArgs(obj[key]);
            }
        }
        return obj;
    }

    async function loginWithBiometrics() {
        if (!window.PublicKeyCredential) {
            alert("Autenticación biométrica no soportada en este navegador.");
            return;
        }

        try {
            const loginDataInit = new URLSearchParams();
            loginDataInit.append('action', 'get_login_args');
            
            const emailField = document.getElementById('email');
            if (emailField && emailField.value) {
                loginDataInit.append('email', emailField.value);
            }

            const res = await fetch('modules/auth/webauthn_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: loginDataInit.toString()
            });
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (err) {
                console.error("Respuesta del servidor no es JSON:", text);
                alert("Error del servidor: Revisa la consola (F12) para ver el error exacto.");
                return;
            }
            
            if (data.error) {
                alert(data.error); return;
            }

            const args = decodeArgs(data.args);
            
            const credential = await navigator.credentials.get(args);
            
            const loginData = new URLSearchParams();
            loginData.append('action', 'process_login');
            loginData.append('id', credential.id); // credential.id is base64url usually
            loginData.append('clientDataJSON', arrayBufferToBase64(credential.response.clientDataJSON));
            loginData.append('authenticatorData', arrayBufferToBase64(credential.response.authenticatorData));
            loginData.append('signature', arrayBufferToBase64(credential.response.signature));
            if (credential.response.userHandle) {
                loginData.append('userHandle', arrayBufferToBase64(credential.response.userHandle));
            }

            const verifyRes = await fetch('modules/auth/webauthn_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: loginData.toString()
            });
            
            const verifyText = await verifyRes.text();
            let verifyData;
            try {
                verifyData = JSON.parse(verifyText);
            } catch(err) {
                console.error("Verificación no es JSON:", verifyText);
                alert("Error al verificar en servidor. Revisa consola.");
                return;
            }

            if (verifyData.success) {
                window.location.href = 'index.php?module=dashboard&action=index';
            } else {
                alert(verifyData.error || 'Fallo la verificación biométrica.');
            }
        } catch (e) {
            console.error(e);
            if (e.name !== 'NotAllowedError') {
                alert('Error al verificar huella: ' + e.message);
            }
        }
    }
</script>
</body>
</html>
