<?php
require_once __DIR__ . '/config/database.php';
$database = new Database();
$db = $database->getConnection();
try {
    $stmt = $db->query("SELECT id, chat_id, task_data FROM msg_messages WHERE id = 83");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $user_id = 1;
    $guest_id = null;
    $message_id = 83;
    $subtask_id = 1;
    $is_completed = true;

    if ($row && $row['chat_id']) {
        echo "Row found. Chat ID: " . $row['chat_id'] . "\n";
        $stmt = $db->prepare("SELECT id FROM msg_participants WHERE chat_id = ? AND (user_id = ? OR guest_id = ?)");
        $stmt->execute([$row['chat_id'], $user_id, $guest_id]);
        if ($stmt->fetch()) {
            echo "Participant found.\n";
            if ($row['task_data']) {
                echo "Task data exists.\n";
                $task = json_decode($row['task_data'], true);
                if ($task) {
                    echo "Task decoded.\n";
                    if ($subtask_id !== null) {
                        $all_completed = true;
                        if (isset($task['subtasks'])) {
                            foreach ($task['subtasks'] as &$st) {
                                if ($st['id'] == $subtask_id) {
                                    $st['completed'] = $is_completed;
                                }
                                if (!$st['completed']) {
                                    $all_completed = false;
                                }
                            }
                        }
                        $task['status'] = $all_completed ? 'completed' : 'in_progress';
                    }
                    
                    // $stmt = $db->prepare("UPDATE msg_messages SET task_data = ? WHERE id = ?");
                    // $stmt->execute([json_encode($task), $message_id]);
                    echo "Success: " . json_encode($task) . "\n";
                } else {
                    echo "JSON DECODE FAILED\n";
                }
            } else {
                echo "NO TASK DATA\n";
            }
        } else {
            echo "PARTICIPANT NOT FOUND\n";
        }
    } else {
        echo "ROW NOT FOUND\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

