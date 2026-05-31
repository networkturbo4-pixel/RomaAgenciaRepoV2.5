<?php
$a = null;
try {
    echo $a / 30;
} catch (Exception $e) {
    echo $e->getMessage();
} catch (Error $e) {
    echo $e->getMessage();
}
