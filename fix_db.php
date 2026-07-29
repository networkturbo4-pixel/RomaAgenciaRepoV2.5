<?php
require 'includes/db.php';
$db->query("UPDATE whiteboards SET canvas_data = NULL, snapshot = NULL");
echo "Todas las pizarras han sido limpiadas de datos corruptos.";
