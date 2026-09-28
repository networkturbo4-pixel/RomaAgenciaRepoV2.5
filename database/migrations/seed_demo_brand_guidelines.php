<?php
// database/migrations/seed_demo_brand_guidelines.php
require_once __DIR__ . '/../../config/database.php';

try {
    $db = (new Database())->getConnection();

    // Check if demo already exists
    $stmt = $db->query("SELECT COUNT(*) FROM brand_guidelines WHERE slug = 'roma-agencia'");
    if ($stmt->fetchColumn() == 0) {
        $colors = [
            [
                'name' => 'Azul Roma',
                'role' => 'Primario',
                'hex' => '#2563EB',
                'rgb' => '37, 99, 235',
                'cmyk' => '84, 58, 0, 8',
                'pantone' => 'PMS 2174 C'
            ],
            [
                'name' => 'Magenta Neón',
                'role' => 'Acento',
                'hex' => '#EC4899',
                'rgb' => '236, 72, 153',
                'cmyk' => '0, 69, 35, 7',
                'pantone' => 'PMS 219 C'
            ],
            [
                'name' => 'Índigo Profundo',
                'role' => 'Secundario',
                'hex' => '#1E1B4B',
                'rgb' => '30, 27, 75',
                'cmyk' => '60, 64, 0, 71',
                'pantone' => 'PMS 2765 C'
            ],
            [
                'name' => 'Titanio Oscuro',
                'role' => 'Texto',
                'hex' => '#0F172A',
                'rgb' => '15, 23, 42',
                'cmyk' => '64, 45, 0, 84',
                'pantone' => 'PMS Black 6 C'
            ],
            [
                'name' => 'Blanco Nube',
                'role' => 'Fondo',
                'hex' => '#F8FAFC',
                'rgb' => '248, 250, 252',
                'cmyk' => '2, 1, 0, 1',
                'pantone' => 'PMS Cool Gray 1 C'
            ]
        ];

        $fonts = [
            [
                'role' => 'Titulares Principales',
                'name' => 'Inter',
                'weights' => '700, 800, 900',
                'usage' => 'Uso en encabezados, titulares de campañas y destacados de marca.'
            ],
            [
                'role' => 'Cuerpo y Soporte',
                'name' => 'Inter',
                'weights' => '400, 500, 600',
                'usage' => 'Párrafos de texto corrido, tablas de datos y material editorial.'
            ]
        ];

        $incorrectUses = [
            [
                'title' => 'No distorsionar ni estirar',
                'desc' => 'Mantén siempre la proporción original del isotipo y logotipo sin deformarlo en ningún eje horizontal ni vertical.'
            ],
            [
                'title' => 'No alterar la paleta cromática',
                'desc' => 'Usa únicamente los colores oficiales aprobados en esta guía corporativa. No inventes nuevas combinaciones.'
            ],
            [
                'title' => 'No aplicar sombras o biseles',
                'desc' => 'Evita aplicar sombras paralelas duras, efectos 3D, relieves o biseles ajenos a la estética plana y limpia.'
            ],
            [
                'title' => 'No rotar el logotipo',
                'desc' => 'El logo siempre debe colocarse en posición horizontal estándar, nunca en diagonal o invertido.'
            ]
        ];

        $variations = [
            [
                'name' => 'Versión Horizontal Oficial',
                'desc' => 'Para aplicaciones en membretes, cabeceras web y firma corporativa.',
                'url' => 'assets/img/default-logo.svg',
                'bg_type' => 'light'
            ]
        ];

        $icons = [
            [
                'name' => 'Favicon Web Oficial',
                'desc' => 'Icono 32x32 / 64x64 para pestañas de navegador',
                'url' => 'assets/img/icon-192x192.png'
            ],
            [
                'name' => 'App Icon / PWA',
                'desc' => 'Icono cuadrado con esquinas redondeadas para móviles y Web Apps',
                'url' => 'assets/img/icon-512x512.png'
            ]
        ];

        $ins = $db->prepare("
            INSERT INTO brand_guidelines (
                brand_name, slug, tagline, description, mission, vision, tone_of_voice,
                logo_primary, logo_symbol, logo_variations_json, icons_json,
                safe_zone_rules, min_size_rules, incorrect_uses_json, colors_json, fonts_json,
                allow_asset_download, is_public, access_password, views_count, created_by
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?
            )
        ");

        $ins->execute([
            'Roma Agencia Creativa',
            'roma-agencia',
            'Estrategia, diseño y tecnología para marcas que lideran su industria',
            'Roma es una agencia creativa especializada en consultoría de marca, diseño de experiencias visuales y desarrollo de plataformas digitales de alto impacto.',
            'Transformar ideas y modelos de negocio en identidades visuales memorables, funcionales y altamente rentables.',
            'Ser el estándar indiscutible de excelencia creativa, innovación visual y consultoría estratégica de marca en la región.',
            'Innovador, Cercano, Seguro, Sofisticado y Directo.',
            'assets/img/default-logo.png',
            'assets/img/icon-192x192.png',
            json_encode($variations, JSON_UNESCAPED_UNICODE),
            json_encode($icons, JSON_UNESCAPED_UNICODE),
            'El espacio de protección perimetral mínimo debe equivaler al 100% de la altura de la letra inicial o isotipo, garantizando máxima legibilidad libre de bordes o textos ajenos.',
            'Digital: 80px de ancho mínimo para pantalla. Impreso: 25mm de ancho para impresión offset. Para dimensiones inferiores utilizar exclusivamente el isotipo.',
            json_encode($incorrectUses, JSON_UNESCAPED_UNICODE),
            json_encode($colors, JSON_UNESCAPED_UNICODE),
            json_encode($fonts, JSON_UNESCAPED_UNICODE),
            1, // allow_asset_download
            1, // is_public
            null, // access_password
            12, // initial views
            1 // created_by
        ]);

        echo "Demo brand guideline created successfully!\n";
    } else {
        echo "Demo brand guideline already exists.\n";
    }
} catch (Exception $e) {
    echo "Seeder error: " . $e->getMessage() . "\n";
}
