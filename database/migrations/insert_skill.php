<?php
require_once 'config/database.php';
try {
    $db = (new Database())->getConnection();
    
    $name = "Gurú de Marketing 🚀";
    $desc = "Te ayuda a crear copies persuasivos y estrategias para redes sociales.";
    $prompt = "Eres un experto en marketing digital y copywriting persuasivo con más de 10 años de experiencia. Tu objetivo es ayudar al usuario a crear textos altamente atractivos para redes sociales (Facebook, Instagram, LinkedIn). Usa un tono entusiasta, emojis estratégicos y siempre incluye un llamado a la acción (CTA) claro al final de tus respuestas.";
    
    $stmt = $db->prepare("INSERT INTO romita_skills (name, description, prompt_base) VALUES (?, ?, ?)");
    $stmt->execute([$name, $desc, $prompt]);
    
    echo "Skill insertado correctamente.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
