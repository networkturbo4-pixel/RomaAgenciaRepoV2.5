<?php
$f = 'c:/xampp/htdocs/CESARMENDOZA/modules/chat/chat.js';
$c = file_get_contents($f);
$c = str_replace(['Cotizacin', 'CotizaciÃ³n'], 'Cotización', $c);
$c = str_replace(['Asignacin', 'AsignaciÃ³n'], 'Diseño Gráfico', $c);
$c = str_replace(['Presentacin', 'PresentaciÃ³n'], 'Presentación', $c);
$c = str_replace(['Diseo', 'DiseÃ±o'], 'Publicación', $c);
$c = str_replace("type === 'post' ? 'Concepto'", "type === 'post' ? 'Publicación'", $c);
file_put_contents($f, $c);
echo "Fixed chat.js\n";

$f2 = 'c:/xampp/htdocs/CESARMENDOZA/modules/chat/index.php';
$c2 = file_get_contents($f2);
$c2 = str_replace('<i class="ph ph-check-square"></i> Asignaci', '<i class="ph ph-palette"></i> Diseño', $c2);
file_put_contents($f2, $c2);
echo "Fixed index.php\n";
