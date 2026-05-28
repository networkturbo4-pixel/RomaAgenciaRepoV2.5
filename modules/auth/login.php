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
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($global_settings['site_name'] ?? 'ROMA SaaS'); ?></title>
    <?php if(!empty($global_settings['favicon'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($global_settings['favicon']); ?>">
    <?php endif; ?>
    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <!-- Load selected fonts -->
    <?php
        $fonts = array_unique([
            $global_settings['font_titles'] ?? 'Inter',
            $global_settings['font_text'] ?? 'Inter',
            $global_settings['font_links'] ?? 'Inter',
            $global_settings['font_buttons'] ?? 'Inter'
        ]);
        foreach($fonts as $font) {
            $font_url = str_replace(' ', '+', $font);
            echo "<link href='https://fonts.googleapis.com/css2?family={$font_url}:wght@300;400;500;600;700&display=swap' rel='stylesheet'>\n";
        }
    ?>

    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($global_settings['primary_color'] ?? '#4f46e5'); ?>;
            --primary-hover: <?php 
                // Simple function to darken color slightly for hover if needed
                echo htmlspecialchars($global_settings['primary_color'] ?? '#4338ca'); 
            ?>;
        }
        h1, h2, h3, h4, h5, h6, .sidebar-header {
            font-family: '<?php echo htmlspecialchars($global_settings['font_titles'] ?? 'Inter'); ?>', sans-serif !important;
        }
        a, .nav-item {
            font-family: '<?php echo htmlspecialchars($global_settings['font_links'] ?? 'Inter'); ?>', sans-serif !important;
        }
        .btn {
            font-family: '<?php echo htmlspecialchars($global_settings['font_buttons'] ?? 'Inter'); ?>', sans-serif !important;
        }
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: block;
            background-color: var(--bg-surface);
            font-family: '<?php echo htmlspecialchars($global_settings['font_text'] ?? 'Inter'); ?>', sans-serif;
        }
        
        .login-split {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        .login-left {
            flex: 1.2;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4rem;
            background-color: var(--bg-surface);
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(135deg, var(--primary-color) 0%, transparent 100%);
            opacity: 0.12;
            pointer-events: none;
        }

        .left-content {
            position: relative;
            z-index: 1;
            max-width: 520px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 4rem;
        }

        .left-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .left-header .logo-icon {
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .left-header .logo-text {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        
        .left-header .logo-text small {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .left-header img {
            max-height: 40px;
        }

        .left-body {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .main-headline {
            font-size: 3.5rem;
            line-height: 1.1;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
            letter-spacing: -0.02em;
        }

        .main-headline .highlight {
            color: var(--primary-color);
        }

        .main-subheadline {
            font-size: 1.125rem;
            line-height: 1.6;
            color: var(--text-muted);
            margin: 0;
            max-width: 90%;
        }

        .trust-pill {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            background-color: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            padding: 0.5rem 1.5rem 0.5rem 0.5rem;
            border-radius: 9999px;
            border: 1px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            width: fit-content;
        }

        .trust-pill .avatars {
            display: flex;
        }

        .trust-pill .avatars img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid white;
            margin-right: -12px;
            object-fit: cover;
        }
        .trust-pill .avatars img:last-child {
            margin-right: 0;
        }

        .trust-pill .trust-text {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .login-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background-color: var(--bg-surface);
        }

        .right-content {
            width: 100%;
            max-width: 420px;
            display: flex;
            flex-direction: column;
        }

        .right-header {
            margin-bottom: 2.5rem;
        }

        .right-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 0.5rem;
            letter-spacing: -0.01em;
        }

        .right-header p {
            color: var(--text-muted);
            font-size: 1rem;
            margin: 0;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }

        .label-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .label-row label {
            margin-bottom: 0;
        }

        .forgot-link {
            font-size: 0.875rem;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: opacity var(--transition-fast);
        }
        .forgot-link:hover {
            opacity: 0.8;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i.input-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.25rem;
            pointer-events: none;
        }

        .input-with-icon .toggle-password {
            position: absolute;
            right: 1.25rem;
            left: auto;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.25rem;
            cursor: pointer;
            transition: color var(--transition-fast);
        }
        
        .input-with-icon .toggle-password:hover {
            color: var(--text-main);
        }

        .input-with-icon .form-control {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3.25rem;
            border: 1px solid var(--border-color);
            border-radius: 9999px;
            font-size: 1rem;
            background-color: var(--bg-surface);
            color: var(--text-main);
            transition: all var(--transition-fast);
            box-sizing: border-box;
        }

        .input-with-icon .toggle-password ~ .form-control {
            padding-right: 3.25rem;
        }

        .input-with-icon .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.05); /* Subtle shadow like image */
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-top: 0.5rem;
            margin-bottom: 2rem;
        }

        .custom-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 0.875rem;
            color: var(--text-muted);
            font-weight: 400 !important;
            user-select: none;
        }

        .custom-checkbox input {
            width: 16px;
            height: 16px;
            accent-color: var(--primary-color);
            cursor: pointer;
            margin: 0;
        }

        .login-btn {
            padding: 0.875rem;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 9999px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            cursor: pointer;
            transition: all var(--transition-fast);
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-btn:hover {
            filter: brightness(0.9);
            transform: translateY(-1px);
        }

        .error-message {
            background: #fee2e2;
            color: #ef4444;
            padding: 0.875rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .right-footer {
            margin-top: 3rem;
            text-align: center;
            font-size: 0.875rem;
            color: var(--text-muted);
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        @media (max-width: 992px) {
            .login-left {
                display: none;
            }
            .login-right {
                padding: 2rem;
            }
        }
        
        /* Dark mode tweaks if applicable */
        [data-theme="dark"] .trust-pill {
            background-color: rgba(30, 41, 59, 0.7);
            border-color: rgba(51, 65, 85, 0.9);
        }
        [data-theme="dark"] .trust-pill .avatars img {
            border-color: var(--bg-surface);
        }

        /* Segmented Control */
        .segmented-control {
            display: flex;
            background-color: var(--border-color);
            border-radius: 9999px;
            padding: 4px;
            margin-bottom: 2rem;
            position: relative;
        }

        .segment-btn {
            flex: 1;
            text-align: center;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-muted);
            border-radius: 9999px;
            cursor: pointer;
            z-index: 1;
            transition: color 0.3s ease;
        }

        .segment-btn.active {
            color: var(--primary-color);
        }

        .segment-slider {
            position: absolute;
            top: 4px;
            bottom: 4px;
            left: 4px;
            width: calc(50% - 4px);
            background-color: var(--bg-surface);
            border-radius: 9999px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            z-index: 0;
        }

        /* Keypad Styles */
        .dni-display {
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: 4px;
            text-align: center;
            margin-bottom: 1rem;
            height: 3.5rem;
            color: var(--primary-color);
            border-bottom: 2px solid var(--border-color);
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .keypad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            width: 100%;
        }

        .keypad-btn {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-main);
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            transition: all 0.1s;
        }

        .keypad-btn:active {
            transform: scale(0.95);
            background: var(--border-color);
        }

        .keypad-btn.action {
            font-size: 1.5rem;
            color: var(--text-muted);
        }

        .keypad-btn.submit-btn {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            font-size: 1.75rem;
        }

        .keypad-btn.submit-btn:active {
            transform: scale(0.95);
            background: var(--primary-hover);
        }

        .form-section {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        .form-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<div class="login-split">
    <!-- Left Side: Branding -->
    <div class="login-left">
        <div class="left-content">
            <div class="left-header">
                <?php if(!empty($global_settings['logo_light'])): ?>
                    <img src="<?php echo htmlspecialchars($global_settings['logo_light']); ?>" alt="Logo">
                <?php else: ?>
                    <div class="logo-icon"><i class="ph-fill ph-rocket-launch"></i></div>
                    <div class="logo-text">
                        <?php echo htmlspecialchars($global_settings['site_name'] ?? 'Roma Agencia'); ?>
                        <small>Enterprise Edition</small>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="left-body">
                <h1 class="main-headline">
                    Eleve su productividad al <span class="highlight">siguiente nivel.</span>
                </h1>
                <p class="main-subheadline">
                    Gestione sus proyectos, analice datos en tiempo real y coordine a su equipo en una plataforma diseñada para la excelencia operativa.
                </p>
            </div>
            
            <div class="left-footer">
                <div class="trust-pill">
                    <div class="avatars">
                        <!-- Using UI faces for avatars like in the image -->
                        <img src="https://i.pravatar.cc/150?u=a042581f4e29026704d" alt="User">
                        <img src="https://i.pravatar.cc/150?u=a042581f4e29026024d" alt="User">
                        <img src="https://i.pravatar.cc/150?u=a04258a2462d826712d" alt="User">
                    </div>
                    <span class="trust-text">+2,000 empresas ya confían en nosotros.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Side: Login Form -->
    <div class="login-right">
        <div class="right-content">
            <div class="right-header">
                <h2>Bienvenido de nuevo</h2>
                <p>Por favor, introduzca sus credenciales para acceder.</p>
            </div>

            <div class="segmented-control" id="login-tabs">
                <div class="segment-slider" id="segment-slider"></div>
                <div class="segment-btn active" onclick="switchTab('agency', this)" id="tab-agency">Agencia</div>
                <div class="segment-btn" onclick="switchTab('client', this)" id="tab-client">Soy Cliente</div>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="ph-fill ph-warning-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div id="form-agency" class="form-section active">
                <form action="index.php?module=auth&action=login" method="POST">
                    <div class="form-group">
                    <label for="email">Correo Electrónico</label>
                    <div class="input-with-icon">
                        <i class="ph ph-envelope input-icon"></i>
                        <input type="email" id="email" name="email" class="form-control" placeholder="nombre@empresa.com" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="label-row">
                        <label for="password">Contraseña</label>
                        <a href="#" class="forgot-link">¿Olvidó su contraseña?</a>
                    </div>
                    <div class="input-with-icon">
                        <i class="ph ph-lock-key input-icon"></i>
                        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                        <i class="ph ph-eye toggle-password" id="togglePassword"></i>
                    </div>
                </div>

                <div class="form-group checkbox-group">
                    <label class="custom-checkbox">
                        <input type="checkbox" name="remember">
                        Mantener sesión iniciada durante 30 días
                    </label>
                </div>

                <button type="submit" class="login-btn">
                    Iniciar Sesión
                </button>
            </form>
            </div>

            <!-- Client Form -->
            <div id="form-client" class="form-section">
                <p style="text-align:center; color:var(--text-muted); margin-bottom: 1.5rem;">Ingresa tu DNI para acceder a tu portal.</p>
                <div style="display:flex; justify-content:center;">
                    <div class="dni-display" id="dni-input"></div>
                </div>
                
                <div style="display:flex; justify-content:center;">
                    <div class="keypad">
                        <div class="keypad-btn" onclick="typeDigit(1)">1</div>
                        <div class="keypad-btn" onclick="typeDigit(2)">2</div>
                        <div class="keypad-btn" onclick="typeDigit(3)">3</div>
                        <div class="keypad-btn" onclick="typeDigit(4)">4</div>
                        <div class="keypad-btn" onclick="typeDigit(5)">5</div>
                        <div class="keypad-btn" onclick="typeDigit(6)">6</div>
                        <div class="keypad-btn" onclick="typeDigit(7)">7</div>
                        <div class="keypad-btn" onclick="typeDigit(8)">8</div>
                        <div class="keypad-btn" onclick="typeDigit(9)">9</div>
                        <div class="keypad-btn action" onclick="deleteDigit()"><i class="ph ph-backspace"></i></div>
                        <div class="keypad-btn" onclick="typeDigit(0)">0</div>
                        <div class="keypad-btn submit-btn" onclick="submitClientLogin()"><i class="ph ph-arrow-right"></i></div>
                    </div>
                </div>
            </div>

            <div class="right-footer">
                &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($global_settings['site_name'] ?? 'Roma Agencia'); ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle password visibility
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');

    togglePassword.addEventListener('click', function (e) {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.classList.toggle('ph-eye');
        this.classList.toggle('ph-eye-slash');
    });

    // Tab Switching Logic
    function switchTab(tab, element) {
        document.querySelectorAll('.segment-btn').forEach(btn => btn.classList.remove('active'));
        element.classList.add('active');
        
        const slider = document.getElementById('segment-slider');
        if (tab === 'client') {
            slider.style.transform = 'translateX(100%)';
            document.getElementById('form-agency').classList.remove('active');
            document.getElementById('form-client').classList.add('active');
        } else {
            slider.style.transform = 'translateX(0)';
            document.getElementById('form-client').classList.remove('active');
            document.getElementById('form-agency').classList.add('active');
        }
    }

    // Keypad Logic
    let dniValue = '';
    const dniDisplay = document.getElementById('dni-input');

    function updateDniDisplay() {
        if (dniValue.length === 0) {
            dniDisplay.innerHTML = '<span style="color:var(--text-muted); opacity:0.5;">------</span>';
        } else {
            dniDisplay.innerText = dniValue;
        }
    }

    function typeDigit(digit) {
        if (dniValue.length < 15) { // Assuming up to 15 chars for DNI/RUC
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
        
        const btn = document.querySelector('.keypad-btn.submit-btn');
        const oldContent = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i>';
        
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
</script>

</body>
</html>
