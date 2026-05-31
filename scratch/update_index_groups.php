<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\index.php';
$content = file_get_contents($file);

$search1 = '<div id="group-link-area" style="display:none; margin-bottom:1rem; padding:1rem; background:color-mix(in srgb, var(--primary-color) 10%, transparent); border-radius:var(--radius-md);">';

$replace1 = '<div class="form-group" style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem;">
                <input type="checkbox" id="group-requires-approval" style="width:18px; height:18px; cursor:pointer;">
                <div>
                    <label for="group-requires-approval" style="font-weight:600; cursor:pointer;">Aprobación requerida</label>
                    <div style="font-size:0.8rem; color:var(--text-muted);">Los nuevos miembros irán a una sala de espera.</div>
                </div>
            </div>
            
            <div class="form-group" style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem;">
                <input type="checkbox" id="group-is-secret" style="width:18px; height:18px; cursor:pointer;" onchange="document.getElementById(\'group-password-container\').style.display = this.checked ? \'block\' : \'none\'">
                <div>
                    <label for="group-is-secret" style="font-weight:600; cursor:pointer;">Chat Secreto (Bóveda)</label>
                    <div style="font-size:0.8rem; color:var(--text-muted);">Protege este chat con una contraseña.</div>
                </div>
            </div>
            <div id="group-password-container" style="display:none; margin-bottom:1rem; margin-left:2rem;">
                <input type="text" id="group-secret-password" class="form-control" placeholder="Contraseña de la bóveda">
            </div>

            <div id="group-link-area" style="display:none; margin-bottom:1rem; padding:1rem; background:color-mix(in srgb, var(--primary-color) 10%, transparent); border-radius:var(--radius-md);">';

$content = str_replace($search1, $replace1, $content);
file_put_contents($file, $content);
echo "Updated index.php with new group settings!";
?>
