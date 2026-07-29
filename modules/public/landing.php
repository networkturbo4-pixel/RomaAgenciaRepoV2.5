<?php
// modules/public/landing.php
require_once 'config/database.php';
$is_public = true;

// Fetch Global Settings
$global_settings = [];
$stmt = $db->query("SELECT * FROM settings");
foreach ($stmt->fetchAll() as $row) {
    $global_settings[$row['setting_key']] = $row['setting_value'];
}

$site_name = htmlspecialchars($global_settings['site_name'] ?? 'Roma Agencia');
$primary_color = htmlspecialchars($global_settings['primary_color'] ?? '#004e36');
$company_phone = preg_replace('/[^0-9]/', '', $global_settings['company_phone'] ?? '');

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <base href="<?php echo rtrim($base_url, '/\\') . '/'; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $site_name; ?> | Gestión Integral para su Empresa</title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="Eleve su productividad al siguiente nivel. Gestione sus proyectos, analice datos en tiempo real y coordine a su equipo con <?php echo $site_name; ?>.">
    <meta name="keywords" content="CRM, Gestión de Proyectos, Productividad, <?php echo $site_name; ?>">
    <meta name="author" content="<?php echo $site_name; ?>">
    <meta name="robots" content="index, follow">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: '<?php echo $primary_color; ?>',
                        primaryLight: 'color-mix(in srgb, <?php echo $primary_color; ?>, white 85%)',
                        primaryHover: 'color-mix(in srgb, <?php echo $primary_color; ?>, black 10%)',
                    }
                }
            }
        }
    </script>
    
    <style>
        .hero-pattern {
            background-color: #f8fafc;
            background-image: radial-gradient(var(--primaryLight) 2px, transparent 2px);
            background-size: 30px 30px;
        }
        .blob {
            position: absolute;
            filter: blur(80px);
            z-index: 0;
            opacity: 0.4;
        }
        /* Reveal animation */
        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s ease-out;
        }
        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body class="font-sans text-slate-800 antialiased overflow-x-hidden">

    <!-- Navigation -->
    <nav class="fixed w-full z-50 bg-white/80 backdrop-blur-md border-b border-slate-200 transition-all duration-300" id="navbar">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex-shrink-0 flex items-center gap-2">
                    <?php if(!empty($global_settings['logo_light'])): ?>
                        <img src="<?php echo htmlspecialchars($global_settings['logo_light']); ?>" alt="Logo" class="h-8">
                    <?php else: ?>
                        <span class="text-2xl font-extrabold text-primary tracking-tight"><?php echo $site_name; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#servicios" class="text-slate-600 hover:text-primary font-medium transition-colors">Servicios</a>
                    <a href="#beneficios" class="text-slate-600 hover:text-primary font-medium transition-colors">Beneficios</a>
                    <a href="catalogo" class="text-slate-600 hover:text-primary font-medium transition-colors">Catálogo</a>
                    
                    <a href="index.php?module=auth&action=login" class="inline-flex items-center justify-center px-6 py-2.5 border border-transparent text-sm font-semibold rounded-full text-white bg-primary hover:bg-primaryHover transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5">
                        Iniciar Sesión
                        <i class="ph-bold ph-arrow-right ml-2"></i>
                    </a>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden flex items-center">
                    <button id="mobile-menu-btn" class="text-slate-600 hover:text-primary focus:outline-none p-2">
                        <i class="ph ph-list text-3xl"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-slate-100 absolute w-full shadow-xl">
            <div class="px-4 pt-2 pb-6 space-y-2">
                <a href="#servicios" class="block px-3 py-3 rounded-md text-base font-medium text-slate-700 hover:text-primary hover:bg-slate-50">Servicios</a>
                <a href="#beneficios" class="block px-3 py-3 rounded-md text-base font-medium text-slate-700 hover:text-primary hover:bg-slate-50">Beneficios</a>
                <a href="catalogo" class="block px-3 py-3 rounded-md text-base font-medium text-slate-700 hover:text-primary hover:bg-slate-50">Catálogo Público</a>
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <a href="index.php?module=auth&action=login" class="w-full flex items-center justify-center px-4 py-3 border border-transparent rounded-xl shadow-sm text-base font-medium text-white bg-primary hover:bg-primaryHover">
                        Iniciar Sesión
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden hero-pattern min-h-[90vh] flex items-center">
        <!-- Blobs for background -->
        <div class="blob bg-primary/20 w-96 h-96 rounded-full top-0 left-[-10%]"></div>
        <div class="blob bg-blue-300/30 w-96 h-96 rounded-full bottom-[-10%] right-[-10%]"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primaryLight text-primary font-medium text-sm mb-8 animate-fade-in-up">
                <span class="relative flex h-2.5 w-2.5">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-primary"></span>
                </span>
                Plataforma de Gestión Avanzada
            </div>
            
            <h1 class="text-5xl md:text-6xl lg:text-7xl font-extrabold text-slate-900 tracking-tight mb-8 leading-[1.1] animate-fade-in-up" style="animation-delay: 0.1s;">
                Eleve su productividad al <br class="hidden md:block" />
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-blue-600">siguiente nivel</span>
            </h1>
            
            <p class="mt-4 max-w-2xl text-lg md:text-xl text-slate-600 mx-auto mb-10 animate-fade-in-up" style="animation-delay: 0.2s;">
                Gestione sus proyectos, analice datos en tiempo real y coordine a su equipo en una plataforma diseñada para la excelencia operativa.
            </p>
            
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center animate-fade-in-up" style="animation-delay: 0.3s;">
                <a href="catalogo" class="w-full sm:w-auto px-8 py-4 bg-primary text-white font-semibold rounded-full hover:bg-primaryHover transition-all shadow-lg hover:shadow-xl hover:-translate-y-1 text-lg flex items-center justify-center">
                    Ver Servicios
                </a>
                <?php if($company_phone): ?>
                <a href="https://wa.me/<?php echo $company_phone; ?>" target="_blank" class="w-full sm:w-auto px-8 py-4 bg-white text-slate-700 font-semibold rounded-full border border-slate-200 hover:border-primary hover:text-primary transition-all shadow-sm hover:shadow-md text-lg flex items-center justify-center gap-2">
                    <i class="ph-fill ph-whatsapp-logo text-green-500 text-2xl"></i>
                    Contactar Ventas
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Dashboard Preview / Mockup -->
            <div class="mt-20 mx-auto max-w-5xl relative animate-fade-in-up" style="animation-delay: 0.5s;">
                <div class="rounded-2xl shadow-2xl overflow-hidden border border-slate-200 bg-white">
                    <div class="bg-slate-100 px-4 py-3 border-b border-slate-200 flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-red-400"></div>
                        <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                        <div class="w-3 h-3 rounded-full bg-green-400"></div>
                    </div>
                    <!-- Mockup content mimicking dashboard -->
                    <div class="p-4 md:p-8 grid grid-cols-1 md:grid-cols-3 gap-6 text-left">
                        <div class="col-span-1 md:col-span-2 space-y-4">
                            <div class="h-8 w-1/3 bg-slate-200 rounded-lg animate-pulse"></div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="h-24 bg-primaryLight/50 rounded-xl border border-primary/10"></div>
                                <div class="h-24 bg-blue-50 rounded-xl border border-blue-100"></div>
                            </div>
                            <div class="h-48 bg-slate-50 rounded-xl border border-slate-100 mt-4"></div>
                        </div>
                        <div class="col-span-1 space-y-4">
                            <div class="h-32 bg-slate-50 rounded-xl border border-slate-100"></div>
                            <div class="h-48 bg-slate-50 rounded-xl border border-slate-100"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services / Features Section -->
    <section id="servicios" class="py-24 bg-white relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16 reveal">
                <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4">Todo lo que necesitas para crecer</h2>
                <p class="text-lg text-slate-600">Nuestras herramientas están diseñadas para integrarse perfectamente en tu flujo de trabajo y maximizar tu eficiencia.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="bg-slate-50 rounded-3xl p-8 border border-slate-100 hover:shadow-xl transition-all hover:-translate-y-1 group reveal">
                    <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center shadow-sm text-primary mb-6 group-hover:scale-110 transition-transform">
                        <i class="ph-fill ph-kanban text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Gestión de Proyectos</h3>
                    <p class="text-slate-600 leading-relaxed">Organiza tareas, asigna responsables y haz seguimiento del progreso en tiempo real. Tableros Kanban y vistas de lista integradas.</p>
                </div>
                
                <!-- Feature 2 -->
                <div class="bg-slate-50 rounded-3xl p-8 border border-slate-100 hover:shadow-xl transition-all hover:-translate-y-1 group reveal" style="transition-delay: 0.1s">
                    <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center shadow-sm text-primary mb-6 group-hover:scale-110 transition-transform">
                        <i class="ph-fill ph-chart-line-up text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Análisis y Reportes</h3>
                    <p class="text-slate-600 leading-relaxed">Toma decisiones basadas en datos. Visualiza el rendimiento financiero, horas trabajadas y rentabilidad por proyecto al instante.</p>
                </div>
                
                <!-- Feature 3 -->
                <div class="bg-slate-50 rounded-3xl p-8 border border-slate-100 hover:shadow-xl transition-all hover:-translate-y-1 group reveal" style="transition-delay: 0.2s">
                    <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center shadow-sm text-primary mb-6 group-hover:scale-110 transition-transform">
                        <i class="ph-fill ph-users-three text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Portal de Clientes</h3>
                    <p class="text-slate-600 leading-relaxed">Ofrece a tus clientes un espacio seguro para revisar avances, aprobar diseños, descargar archivos y realizar pagos en línea.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="py-24 bg-slate-900 relative overflow-hidden">
        <div class="absolute inset-0 bg-primary/20"></div>
        <div class="absolute w-96 h-96 bg-primary rounded-full blur-3xl opacity-20 -top-20 -right-20"></div>
        <div class="absolute w-96 h-96 bg-blue-600 rounded-full blur-3xl opacity-20 -bottom-20 -left-20"></div>
        
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center reveal">
            <h2 class="text-3xl md:text-5xl font-bold text-white mb-6 leading-tight">¿Listo para transformar tu manera de trabajar?</h2>
            <p class="text-xl text-slate-300 mb-10 max-w-2xl mx-auto">Únete y descubre cómo nuestra plataforma puede automatizar tus procesos y aumentar tu rentabilidad.</p>
            <a href="index.php?module=auth&action=login" class="inline-flex items-center justify-center px-8 py-4 text-lg font-bold rounded-full text-slate-900 bg-white hover:bg-slate-50 transition-all shadow-xl hover:shadow-2xl hover:scale-105 gap-2">
                Acceder a mi cuenta
                <i class="ph-bold ph-arrow-right"></i>
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 pt-16 pb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
                <div class="col-span-1 md:col-span-2">
                    <span class="text-2xl font-extrabold text-primary tracking-tight block mb-4"><?php echo $site_name; ?></span>
                    <p class="text-slate-500 max-w-md">La solución integral para agencias y empresas que buscan optimizar su gestión, facturación y relación con los clientes en un solo lugar.</p>
                </div>
                <div>
                    <h4 class="font-bold text-slate-900 mb-4">Enlaces Útiles</h4>
                    <ul class="space-y-3">
                        <li><a href="#servicios" class="text-slate-500 hover:text-primary transition-colors">Características</a></li>
                        <li><a href="catalogo" class="text-slate-500 hover:text-primary transition-colors">Catálogo Público</a></li>
                        <li><a href="index.php?module=auth&action=login" class="text-slate-500 hover:text-primary transition-colors">Portal Cliente</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold text-slate-900 mb-4">Contacto</h4>
                    <ul class="space-y-3">
                        <?php if($company_phone): ?>
                        <li>
                            <a href="https://wa.me/<?php echo $company_phone; ?>" target="_blank" class="text-slate-500 hover:text-primary transition-colors flex items-center gap-2">
                                <i class="ph-fill ph-whatsapp-logo text-xl text-green-500"></i> WhatsApp
                            </a>
                        </li>
                        <?php endif; ?>
                        <li>
                            <a href="#" class="text-slate-500 hover:text-primary transition-colors flex items-center gap-2">
                                <i class="ph-fill ph-envelope-simple text-xl"></i> Soporte
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-slate-200 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-slate-400 text-sm">
                    &copy; <?php echo date('Y'); ?> <?php echo $site_name; ?>. Todos los derechos reservados.
                </p>
                <div class="flex space-x-6 text-slate-400">
                    <!-- Redes sociales aquí si se requieren -->
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <style>
        /* Initial state for fade-in-up animation used in Hero */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            opacity: 0;
            animation: fadeInUp 0.8s ease-out forwards;
        }
    </style>
    <script>
        // Mobile menu toggle
        const btn = document.getElementById('mobile-menu-btn');
        const menu = document.getElementById('mobile-menu');
        
        btn.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });

        // Close mobile menu on click
        menu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                menu.classList.add('hidden');
            });
        });

        // Navbar blur effect on scroll
        window.addEventListener('scroll', () => {
            const nav = document.getElementById('navbar');
            if (window.scrollY > 20) {
                nav.classList.add('shadow-sm');
                nav.classList.replace('bg-white/80', 'bg-white/95');
            } else {
                nav.classList.remove('shadow-sm');
                nav.classList.replace('bg-white/95', 'bg-white/80');
            }
        });

        // Scroll reveal animation
        const revealElements = document.querySelectorAll('.reveal');
        
        const revealCallback = function(entries, observer) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                    observer.unobserve(entry.target);
                }
            });
        };
        
        const revealObserver = new IntersectionObserver(revealCallback, {
            threshold: 0.15,
            rootMargin: '0px 0px -50px 0px'
        });
        
        revealElements.forEach(el => revealObserver.observe(el));
    </script>
</body>
</html>
