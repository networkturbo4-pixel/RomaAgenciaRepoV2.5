-- ==============================================================================
-- ACTUALIZACIÓN: TABLA Y PLANTILLAS DE SUPER-PROMPTS PARA ROMITA AI
-- Sistema: Roma Agencia SaaS
-- Compatible con MySQL 5.7+, 8.0+ y MariaDB (cPanel / phpMyAdmin)
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `romita_super_prompts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT 'general',
  `prompt` text NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'ph-lightning',
  `is_agency_template` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_prompts` (`user_id`),
  KEY `idx_agency_prompts` (`is_agency_template`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar plantillas predefinidas de la agencia si no existen
INSERT INTO `romita_super_prompts` (`user_id`, `title`, `category`, `prompt`, `description`, `icon`, `is_agency_template`, `created_at`)
SELECT NULL, 'Guion de Reels de Alta Retención (9:16)', 'Redes Sociales', 
'Actúa como Director Creativo Audiovisual. Redacta un guion para un Reel de 45 segundos sobre [TEMA / PRODUCTO] para la marca activa. Divide el contenido en 4 partes con minutaje exacto:\n1. Gancho Visual & Auditivo (0-3s): ¿Qué ve y escucha el espectador para no deslizar?\n2. Retención & Quiebre (3-15s): Presenta el problema común de forma intrigante.\n3. Núcleo de Valor (15-38s): 3 soluciones prácticas paso a paso.\n4. Llamado a la Acción (38-45s): CTA claro invitando a comentar una palabra clave para recibir información.',
'Estructura probada: Gancho 3s + Quiebre de patrón + Valor + CTA', 'ph-video-camera', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `romita_super_prompts` WHERE `title` = 'Guion de Reels de Alta Retención (9:16)');

INSERT INTO `romita_super_prompts` (`user_id`, `title`, `category`, `prompt`, `description`, `icon`, `is_agency_template`, `created_at`)
SELECT NULL, 'Carrusel B2B Educativo (10 Slides)', 'Contenido Educativo',
'Diseña el guion completo de un carrusel educativo de 10 láminas sobre [TEMA] adaptado al sector de la marca activa.\nEstructura cada slide con:\n- Título conciso (máximo 6 palabras).\n- Cuerpo de texto en viñetas directas (máximo 25 palabras).\n- Indicación de diseño gráfico / elemento visual para el equipo de diseño.\n- Slide 1: Portada magnética con promesa irresistible.\n- Slide 2: El error número 1 que todos cometen.\n- Slides 3 a 8: Metodología paso a paso desglosada.\n- Slide 9: Resumen de takeaways en 3 viñetas.\n- Slide 10: Portada final con llamado a guardar y compartir.',
'Formato de micro-aprendizaje para LinkedIn e Instagram', 'ph-slideshow', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `romita_super_prompts` WHERE `title` = 'Carrusel B2B Educativo (10 Slides)');

INSERT INTO `romita_super_prompts` (`user_id`, `title`, `category`, `prompt`, `description`, `icon`, `is_agency_template`, `created_at`)
SELECT NULL, 'Fórmula PAS para Copywriting Publicitario', 'Copywriting & Ads',
'Genera 3 variaciones de copy publicitario para Meta Ads (Facebook/Instagram) usando la fórmula PAS (Problema - Agitación - Solución) enfocado en [OFERTA / SERVICIO].\nEstructura cada variación:\n1. Problema: El dolor real y frustrante de la audiencia objetivo.\n2. Agitación: Qué pasa si no resuelven ese problema hoy.\n3. Solución: Cómo nuestro servicio elimina el dolor de raíz.\n4. Oferta irresistible con CTA claro.\nEntrega Variación A (Enfoque directo al grano), Variación B (Enfoque storytelling testimonial), y Variación C (Enfoque de autoridad y datos).',
'Problema, Agitación y Solución con 3 variaciones de tono', 'ph-lightning', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `romita_super_prompts` WHERE `title` = 'Fórmula PAS para Copywriting Publicitario');

INSERT INTO `romita_super_prompts` (`user_id`, `title`, `category`, `prompt`, `description`, `icon`, `is_agency_template`, `created_at`)
SELECT NULL, 'Simulador de Objeciones de Clientes (Role-Play)', 'Ventas & Clientes',
'Iniciemos una sesión de ROLE-PLAY de ventas.\nTú actuarás como un cliente escéptico y exigente interesado en contratar los servicios de nuestra agencia.\nPresenta una primera objeción realista y difícil (por ejemplo: "Su tarifa es demasiado cara, otra agencia me cobra la mitad" o "No creo que las redes sociales funcionen para mi nicho").\nQuédate en personaje. Espera mi respuesta y luego califícame del 1 al 10 con feedback constructivo sobre cómo mejorar mi argumento de venta, y luego lanza la siguiente objeción más difícil.',
'Entrenamiento de objeciones duras en tiempo real', 'ph-users-three', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `romita_super_prompts` WHERE `title` = 'Simulador de Objeciones de Clientes (Role-Play)');

INSERT INTO `romita_super_prompts` (`user_id`, `title`, `category`, `prompt`, `description`, `icon`, `is_agency_template`, `created_at`)
SELECT NULL, 'Auditoría Rápida de Competencia & Benchmark', 'Estrategia & Research',
'Realiza un análisis competitivo rápido tipo Benchmark para la marca activa frente a sus 3 principales competidores en el mercado.\nEntrega una tabla comparativa con:\n- Pilares de contenido principales.\n- Frecuencia y formatos dominantes (Reels, Fotos, Carruseles).\n- Tono de comunicación.\n- Calidad de comunidad y engagement promedio.\n- "Gaps" o brechas de oportunidad que nuestra agencia puede capitalizar para destacar.',
'Matriz comparativa de 3 competidores clave con brechas de oportunidad', 'ph-chart-bar', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `romita_super_prompts` WHERE `title` = 'Auditoría Rápida de Competencia & Benchmark');

INSERT INTO `romita_super_prompts` (`user_id`, `title`, `category`, `prompt`, `description`, `icon`, `is_agency_template`, `created_at`)
SELECT NULL, 'Plan de Contenidos 30 Días (Matriz Mensual)', 'Planificación',
'Genera la matriz estratégica de contenidos para los próximos 30 días de la marca seleccionada.\nConsidera 3 publicaciones semanales (12 en total) balanceando los 4 pilares:\n- 30% Educativo / Valor técnico.\n- 30% Autoridad / Casos de éxito y testimonios.\n- 20% Conexión humana / Behind the scenes de la agencia.\n- 20% Venta directa / Promoción de servicios.\nPresenta todo en una tabla detallada con Día, Formato (Reel, Carrusel, Estático), Objetivo, Gancho y Llamado a la Acción.',
'Distribución estratégica de 12 a 16 piezas para el mes', 'ph-calendar-check', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `romita_super_prompts` WHERE `title` = 'Plan de Contenidos 30 Días (Matriz Mensual)');

SET FOREIGN_KEY_CHECKS = 1;
