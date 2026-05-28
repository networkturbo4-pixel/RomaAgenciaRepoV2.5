<?php
// Script para migrar localStorage
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = file_get_contents('php://input');
    file_put_contents('notas_migracion.json', $data);
    echo "OK";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Migrar Notas de Pago</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; text-align: center; background: #1a1a2e; color: white; }
        .box { background: #16213e; padding: 30px; border-radius: 10px; max-width: 500px; margin: 0 auto; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
        button { background: #0f3460; color: white; border: none; padding: 15px 30px; font-size: 16px; border-radius: 5px; cursor: pointer; margin: 10px 0; width: 100%; font-weight: bold; }
        button:hover { background: #e94560; }
        h1 { margin-top: 0; color: #e94560; }
        #msg { margin-top: 20px; font-weight: bold; color: #4ecca3; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Migrador de Notas de Pago</h1>
        
        <h3>Paso 1 (En tu Computadora)</h3>
        <p>Abre este archivo en tu <b>localhost</b> y haz clic aquí para extraer las notas de tu navegador:</p>
        <button onclick="exportar()">1. Exportar Mis Notas</button>
        <div id="msg"></div>

        <hr style="border-color: #0f3460; margin: 30px 0;">

        <h3>Paso 2 (En tu Servidor cPanel)</h3>
        <p>Abre este archivo en tu dominio (romaagencia.lat) y haz clic aquí para insertarlas:</p>
        <button onclick="importar()" style="background: #e94560;">2. Importar a Producción</button>
    </div>

    <script>
        function exportar() {
            let data = localStorage.getItem('payment_notes');
            if(!data || data === '[]') { 
                alert("No parece haber notas guardadas en este navegador."); 
                return; 
            }
            fetch('migrate_notas.php', { method: 'POST', body: data })
            .then(res => res.text())
            .then(res => { 
                document.getElementById('msg').innerHTML = "✅ ¡Exportado con éxito!<br><br>Ve a tu carpeta del proyecto, busca los archivos <b>migrate_notas.php</b> y <b>notas_migracion.json</b>, y súbelos a tu cPanel (en public_html)."; 
            });
        }

        function importar() {
            fetch('notas_migracion.json')
            .then(res => res.text())
            .then(data => {
                if(data && data.length > 5) {
                    localStorage.setItem('payment_notes', data);
                    alert("¡Importación exitosa! Ya puedes ver tus notas.");
                    window.location.href = 'index.php?module=admin&action=payment_notes';
                } else {
                    alert("Error: Asegúrate de haber subido el archivo notas_migracion.json a tu cPanel.");
                }
            }).catch(e => alert("Error: No se encontró el archivo notas_migracion.json. ¿Ya lo subiste a cPanel?"));
        }
    </script>
</body>
</html>
