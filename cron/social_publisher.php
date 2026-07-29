<?php
// cron/social_publisher.php
// Este script debe ser ejecutado por un cronjob (ej. cada 5 minutos)
// php /ruta/cron/social_publisher.php

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$pdo = $db->getConnection();

echo "Iniciando Cron de Publicación Social - " . date('Y-m-d H:i:s') . "\n";

try {
    // Buscar posts programados cuya fecha ya pasó
    $stmt = $pdo->prepare("SELECT id, post_date FROM month_posts WHERE social_status = 'scheduled' AND post_date <= NOW()");
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($posts)) {
        echo "No hay posts programados para enviar ahora.\n";
        exit();
    }

    foreach ($posts as $post) {
        $post_id = $post['id'];
        echo "Procesando Post ID: $post_id ... ";
        
        // SIMULAR LLAMADA A LA API (Sandbox)
        sleep(1);
        $fake_api_id = "cron_post_" . md5(uniqid());

        // Actualizar estado
        $stmtUp = $pdo->prepare("UPDATE month_posts SET social_status = 'published', social_post_id = ? WHERE id = ?");
        $stmtUp->execute([$fake_api_id, $post_id]);
        
        echo "¡Publicado! (ID API: $fake_api_id)\n";
    }

    echo "Cron finalizado con éxito.\n";

} catch (Exception $e) {
    echo "Error en cron: " . $e->getMessage() . "\n";
}
