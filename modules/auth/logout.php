<?php
// modules/auth/logout.php
session_start();
session_unset();
session_destroy();
header("Location: index.php?module=auth&action=login");
exit();
?>
