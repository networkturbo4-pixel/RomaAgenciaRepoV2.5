<?php
$logFile = 'C:\Users\CESAR\.gemini\antigravity\brain\d29e7149-f23d-41e7-af55-c27b3fd9a5aa\.system_generated\logs\transcript.jsonl';
$lines = file($logFile);
$chatJsModifications = [];

foreach ($lines as $line) {
    $data = json_decode($line, true);
    if (isset($data['tool_calls'])) {
        foreach ($data['tool_calls'] as $tc) {
            if (isset($tc['name']) && $tc['name'] === 'multi_replace_file_content') {
                $args = $tc['args'];
                if (isset($args['TargetFile']) && strpos($args['TargetFile'], 'chat.js') !== false) {
                    $chatJsModifications[] = $args;
                }
            }
        }
    }
}

file_put_contents('c:\xampp\htdocs\CESARMENDOZA\scratch\chat_js_mods.json', json_encode($chatJsModifications, JSON_PRETTY_PRINT));
echo "Found " . count($chatJsModifications) . " modifications to chat.js\n";
?>
