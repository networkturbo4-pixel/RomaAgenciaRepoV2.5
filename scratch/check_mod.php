<?php
$m = json_decode(file_get_contents('C:\xampp\htdocs\CESARMENDOZA\scratch\chat_js_mods.json'), true);
foreach ($m as $mod) {
    if ($mod['Description'] == '"Render group avatar in channel list"') {
        print_r($mod);
    }
}
?>
