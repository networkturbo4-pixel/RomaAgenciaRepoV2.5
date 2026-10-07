<?php
/**
 * Safe & Idempotent Migration: Add show_proposals and logo_proposals_json to brand_guidelines
 * Safe to execute on both Local and Production without data loss.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../modules/brand_guidelines/helpers.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("No se pudo conectar a la base de datos.");
    }
    
    bg_ensure_proposals_columns($db);
    
    echo "✓ Migración ejecutada con éxito. Columnas verificadas en 'brand_guidelines'.\n";
} catch (Throwable $e) {
    echo "✗ Error al ejecutar la migración: " . $e->getMessage() . "\n";
}
