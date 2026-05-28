<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    die("No autorizado");
}

$postId = $_GET['post_id'] ?? 0;
if (!$postId) die("Post ID requerido");

$database = new Database();
$db = $database->getConnection();

$stmt = $db->prepare("SELECT concept, presenter_notes FROM month_posts WHERE id = ?");
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) die("Post no encontrado");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Notas - <?php echo htmlspecialchars($post['concept']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #0f172a;
            color: #f8fafc;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            height: 100vh;
            box-sizing: border-box;
        }
        h2 {
            margin-top: 0;
            font-size: 1.2rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 10px;
        }
        textarea {
            flex: 1;
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: white;
            padding: 15px;
            border-radius: 8px;
            font-family: inherit;
            font-size: 1rem;
            resize: none;
            outline: none;
            box-sizing: border-box;
        }
        textarea:focus {
            border-color: #3b82f6;
        }
        .controls {
            margin-top: 15px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        button {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-save {
            background: #3b82f6;
            color: white;
        }
        .btn-save:hover {
            background: #2563eb;
        }
        #status {
            display: none;
            color: #10b981;
            align-items: center;
        }
    </style>
</head>
<body>
    <h2>Notas del Presentador</h2>
    <textarea id="notes_text" placeholder="Escribe tus notas aquí (solo tú las verás)..."><?php echo htmlspecialchars($post['presenter_notes'] ?? ''); ?></textarea>
    <div class="controls">
        <span id="status">Guardado</span>
        <button class="btn-save" onclick="saveNotes()">Guardar Notas</button>
    </div>

    <script>
        function saveNotes() {
            const notes = document.getElementById('notes_text').value;
            const fd = new FormData();
            fd.append('post_id', <?php echo $postId; ?>);
            fd.append('notes', notes);

            fetch('ajax_save_presenter_notes.php', {
                method: 'POST',
                body: fd
            }).then(r => r.json()).then(res => {
                if (res.success) {
                    const st = document.getElementById('status');
                    st.style.display = 'inline-flex';
                    setTimeout(() => st.style.display = 'none', 2000);
                } else {
                    alert("Error: " + res.error);
                }
            }).catch(e => alert("Error al guardar"));
        }
    </script>
</body>
</html>
