<?php
// create_knowledge_base_tables.php
require_once 'config/database.php';

try {
    $db = (new Database())->getConnection();

    // 1. Tabla de Categorías de la Base de Conocimiento
    $sqlCategories = "CREATE TABLE IF NOT EXISTS kb_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        slug VARCHAR(150) NOT NULL UNIQUE,
        description TEXT NULL,
        icon VARCHAR(50) DEFAULT 'ph-book-open',
        color VARCHAR(20) DEFAULT '#4f46e5',
        order_index INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->exec($sqlCategories);

    // 2. Tabla de Artículos y Videos de la Base de Conocimiento
    $sqlArticles = "CREATE TABLE IF NOT EXISTS kb_articles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        summary TEXT NULL,
        content LONGTEXT NULL,
        video_url VARCHAR(500) NULL,
        video_id VARCHAR(50) NULL,
        duration_minutes INT DEFAULT 5,
        audience ENUM('all', 'internal', 'clients') DEFAULT 'all',
        status ENUM('published', 'draft') DEFAULT 'published',
        views_count INT DEFAULT 0,
        helpful_yes INT DEFAULT 0,
        helpful_no INT DEFAULT 0,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (category_id),
        INDEX idx_audience (audience),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $db->exec($sqlArticles);

    // 3. Semilla de Categorías
    $stmtCheckCat = $db->query("SELECT COUNT(*) FROM kb_categories");
    if ($stmtCheckCat->fetchColumn() == 0) {
        $insertCat = $db->prepare("INSERT INTO kb_categories (name, slug, description, icon, color, order_index) VALUES (?, ?, ?, ?, ?, ?)");
        
        $insertCat->execute([
            'Procesos de Diseño',
            'procesos-de-diseno',
            'Metodología creativa, entrega de marcas, manuales de identidad y lineamientos visuales.',
            'ph-paint-brush-broad',
            '#8b5cf6',
            1
        ]);

        $insertCat->execute([
            'Inducción & Onboarding',
            'induccion-onboarding',
            'Cultura corporativa, configuración de herramientas de trabajo y bienvenida a nuevos miembros.',
            'ph-student',
            '#10b981',
            2
        ]);

        $insertCat->execute([
            'Atención & Portal de Clientes',
            'atencion-portal-clientes',
            'Guías para clientes sobre cómo solicitar revisiones, navegar en su portal y aprobar entregables.',
            'ph-users-three',
            '#3b82f6',
            3
        ]);

        $insertCat->execute([
            'Ventas & Cotizaciones',
            'ventas-cotizaciones',
            'Flujos comerciales, emisión de órdenes de servicio y seguimiento de prospectos.',
            'ph-receipt',
            '#f59e0b',
            4
        ]);
    }

    // 4. Obtener id de la categoría Procesos de Diseño
    $stmtDesignCat = $db->query("SELECT id FROM kb_categories WHERE slug = 'procesos-de-diseno' LIMIT 1");
    $designCatId = $stmtDesignCat->fetchColumn();

    // 5. Semilla de Artículos Demostrativos
    $stmtCheckArt = $db->query("SELECT COUNT(*) FROM kb_articles");
    if ($stmtCheckArt->fetchColumn() == 0 && $designCatId) {
        $insertArt = $db->prepare("INSERT INTO kb_articles 
            (category_id, title, slug, summary, content, video_url, video_id, duration_minutes, audience, status, views_count) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // Artículo 1: Procesos de Diseño para el Equipo (Interno)
        $contentInternal = '<h2>Metodología de Trabajo para Proyectos de Diseño</h2>
<p>Este protocolo establece las etapas obligatorias para el desarrollo de identidades visuales y piezas gráficas dentro de la agencia, garantizando coherencia y altos estándares de calidad.</p>

<h3>1. Recepción del Brief y Levantamiento de Requerimientos</h3>
<p>Antes de abrir Illustrator o Photoshop, es fundamental validar que el brief cuente con:</p>
<ul>
    <li>Objetivo de la marca o campaña.</li>
    <li>Público objetivo y arquetipo de cliente.</li>
    <li>Referencias visuales aprobadas por el cliente.</li>
    <li>Entregables pactados en la orden de servicio.</li>
</ul>

<h3>2. Creación del Moodboard y Dirección de Arte</h3>
<p>Se consolida un tablero de inspiración en Pinterest o Figma con tipografías, paletas cromáticas y estilos fotográficos antes de proponer bocetos finales.</p>

<h3>3. Fase de Bocetaje y Vectorización</h3>
<p>Se desarrollan mínimo <strong>2 caminos conceptuales distintos</strong>. Cada propuesta debe poder adaptarse a fondos claros, oscuros y en versión monocromática.</p>

<h3>4. Empaque de Entregables</h3>
<p>Los archivos finales deben subirse a la carpeta compartida de Google Drive siguiendo la estructura:</p>
<pre><code>01_Vectores_Editables/ (AI, EPS, PDF)
02_Imagenes_Exportadas/ (PNG fondos transparentes, JPG)
03_Manual_de_Marca/ (PDF interactivo)
04_Tipografias/ (Archivos OTF/TTF con licencias)</code></pre>';

        $insertArt->execute([
            $designCatId,
            'Protocolo Interno: Fases del Proceso de Diseño y Entrega',
            'protocolo-interno-fases-del-proceso-de-diseno-y-entrega',
            'Guía paso a paso sobre el flujo creativo interno: desde la lectura del brief hasta la exportación y orden en Google Drive.',
            $contentInternal,
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'dQw4w9WgXcQ',
            8,
            'internal',
            'published',
            14
        ]);

        // Artículo 2: Guía de Diseño para Clientes (Audiencia Clientes / All)
        $contentClient = '<h2>¿Cómo Revisar y Enviar Feedback a Tus Diseños?</h2>
<p>Queremos que el resultado final de tu marca o proyecto publicitario supere tus expectativas. Aquí te dejamos las mejores recomendaciones para brindarnos retroalimentación clara y efectiva.</p>

<h3>1. Revisa el Diseño en su Contexto Real</h3>
<p>Evalúa las propuestas imaginándolas en su aplicación final: en tu perfil de Instagram, en un rótulo exterior o en la pantalla de tu móvil.</p>

<h3>2. Sé Específico en Tus Comentarios</h3>
<ul>
    <li><strong>En lugar de:</strong> <em>"No me convence el diseño"</em>.</li>
    <li><strong>Mejor:</strong> <em>"Nos gustaría que la tipografía se vea más moderna y que el color azul sea un poco más vibrante como en la referencia X"</em>.</li>
</ul>

<h3>3. Consolida Tus Revisiones</h3>
<p>Para evitar retrabajos y demoras en tu cronograma, reúne todas las observaciones en una sola lista antes de enviárnoslas por el portal o WhatsApp corporativo.</p>

<h3>4. Aprobación Final y Descarga</h3>
<p>Una vez que des el visto bueno definitivo, habilitaremos en tu <strong>Portal de Cliente</strong> el enlace directo a tu carpeta de Google Drive con todos los archivos listos para imprenta y redes sociales.</p>';

        $insertArt->execute([
            $designCatId,
            'Guía para Clientes: Cómo Revisar y Aprobar Tus Propuestas de Diseño',
            'guia-para-clientes-como-revisar-y-aprobar-tus-propuestas-de-diseno',
            'Consejos prácticos para clientes sobre cómo evaluar las propuestas gráficas, enviar observaciones claras y acceder a los entregables finales.',
            $contentClient,
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'dQw4w9WgXcQ',
            5,
            'clients',
            'published',
            28
        ]);
    }

    echo "Knowledge Base tables and seed data created successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
