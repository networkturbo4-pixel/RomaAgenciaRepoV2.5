<?php
// modules/forms/ajax_submit_form.php — Public endpoint, no auth required
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'error'=>'Método no permitido']); exit(); }

$token = $_POST['token'] ?? '';
$data_json = $_POST['data_json'] ?? '';
$respondent_name = trim($_POST['respondent_name'] ?? '');
$respondent_email = trim($_POST['respondent_email'] ?? '');

if (empty($token) || empty($data_json)) {
    echo json_encode(['success'=>false,'error'=>'Datos incompletos']); exit();
}

try {
    $stmt = $db->prepare("SELECT * FROM form_templates WHERE public_token=? AND status='active'");
    $stmt->execute([$token]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$template) { echo json_encode(['success'=>false,'error'=>'Formulario no encontrado o inactivo']); exit(); }

    // Generate correlativo
    $stmtLast = $db->query("SELECT id FROM form_submissions ORDER BY id DESC LIMIT 1");
    $last = $stmtLast->fetch(PDO::FETCH_ASSOC);
    $nextId = $last ? ($last['id'] + 1) : 1;
    $correlativo = 'BRIEF-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

    // Month
    $meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $submission_month = $meses[date('n') - 1] . '-' . date('Y');

    $dataArr = json_decode($data_json, true) ?: [];
    $driveFileId = null;

    try {
        require_once 'includes/GoogleDriveHelper.php';
        $drive = new GoogleDriveHelper();
        
        if ($drive->isConfigured() && $template['drive_folder_id']) {
            // 1. Get or create month subfolder
            $monthFolder = null;
            $subfolders = $drive->listFolders($template['drive_folder_id']);
            if ($subfolders) {
                foreach ($subfolders as $sf) {
                    if (strtolower(trim($sf->name)) === strtolower($submission_month)) { $monthFolder = $sf->id; break; }
                }
            }
            if (!$monthFolder) $monthFolder = $drive->createFolder($submission_month, $template['drive_folder_id']);

            // 2. Create Submission Folder
            $subFolderName = $correlativo . ($respondent_name ? ' ' . $respondent_name : '');
            $correlativoFolder = $drive->createFolder($subFolderName, $monthFolder);

            if ($correlativoFolder) {
                $refFolder = null; // We only create "referencias" if there are files
                
                // Process temp files from async upload
                $hasFiles = false;
                $fieldsToProcess = [];
                
                foreach ($dataArr as $key => $val) {
                    if (strpos($key, 'temp_file_') === 0) {
                        $hasFiles = true;
                        $fieldId = str_replace(['temp_file_', '[]'], '', $key);
                        $fieldsToProcess[$fieldId]['paths'] = (array)$val;
                        // Clean up the temp_file key from dataArr
                        unset($dataArr[$key]);
                    }
                    if (strpos($key, 'temp_name_') === 0) {
                        $fieldId = str_replace(['temp_name_', '[]'], '', $key);
                        $fieldsToProcess[$fieldId]['names'] = (array)$val;
                        unset($dataArr[$key]);
                    }
                }

                if ($hasFiles) {
                    $refFolder = $drive->createFolder('referencias', $correlativoFolder);
                    
                    foreach ($fieldsToProcess as $fId => $fData) {
                        $urls = [];
                        $names = [];
                        $paths = $fData['paths'] ?? [];
                        $origNames = $fData['names'] ?? [];
                        
                        foreach ($paths as $idx => $path) {
                            $realPath = __DIR__ . '/../../' . $path;
                            $origName = $origNames[$idx] ?? basename($path);
                            
                            if (file_exists($realPath)) {
                                $fileName = $correlativo . '_' . $origName;
                                $uploaded = $drive->uploadFile($realPath, $fileName, $refFolder);
                                if ($uploaded && isset($uploaded['webViewLink'])) {
                                    $urls[] = $uploaded['webViewLink'];
                                    $names[] = $origName;
                                }
                                @unlink($realPath); // cleanup
                            }
                        }
                        
                        if (!empty($urls)) {
                            // Store back to DB data mapping
                            $dataArr['field_' . $fId . '_drive_url'] = count($urls) > 1 ? $urls : $urls[0];
                            $dataArr['field_' . $fId . '_file_name'] = count($names) > 1 ? $names : $names[0];
                        }
                    }
                }

                // 3. Upload JSON summary to the correlativo folder
                $jsonContent = json_encode([
                    'correlativo' => $correlativo,
                    'formulario' => $template['title'],
                    'respondente' => $respondent_name,
                    'email' => $respondent_email,
                    'fecha' => date('Y-m-d H:i:s'),
                    'respuestas' => $dataArr
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                $tmpFile = tempnam(sys_get_temp_dir(), 'brief_');
                file_put_contents($tmpFile, $jsonContent);
                $uploadedJson = $drive->uploadFile($tmpFile, $correlativo . '.json', $correlativoFolder);
                if ($uploadedJson) $driveFileId = $uploadedJson['id'];
                @unlink($tmpFile);
            }
        }
    } catch (Exception $e) { 
        error_log("Drive process error: " . $e->getMessage()); 
    }

    // Save to DB
    $finalDataJson = json_encode($dataArr, JSON_UNESCAPED_UNICODE);
    $stmtInsert = $db->prepare("INSERT INTO form_submissions (template_id, correlativo, respondent_name, respondent_email, data_json, drive_file_id, submission_month) VALUES (?,?,?,?,?,?,?)");
    $stmtInsert->execute([$template['id'], $correlativo, $respondent_name, $respondent_email, $finalDataJson, $driveFileId, $submission_month]);

    // Send push notification to admin (user_id 1)
    try {
        require_once 'vendor/autoload.php';
        $vapidPublic = 'BAhu9ZcA2cypGC--dbgdXicyU_K4cvZUdRhP4nQ7Y4t8M2LN156sVAWKg1swXA6KIyjBZvZkeIKqTZxxNpdNksI';
        $vapidPrivate = 'QaRTxhVHLghTyAGwSw63Bw3sYMqPRpZi8wmvAqR0YWA';
        $auth = ['VAPID' => ['subject' => 'mailto:admin@example.com', 'publicKey' => $vapidPublic, 'privateKey' => $vapidPrivate]];
        $webPush = new Minishlink\WebPush\WebPush($auth);
        $payload = json_encode([
            'title' => '📋 Nuevo Brief Recibido',
            'body' => ($respondent_name ?: 'Anónimo') . ' completó "' . $template['title'] . '" — ' . $correlativo,
            'icon' => '/assets/img/default-icon.png',
            'url' => 'index.php?module=forms&action=submissions&id=' . $template['id']
        ]);
        $subs = $db->query("SELECT endpoint, p256dh, auth_token FROM push_subscriptions WHERE user_id = 1")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($subs as $sub) {
            $subscription = Minishlink\WebPush\Subscription::create(['endpoint' => $sub['endpoint'], 'publicKey' => $sub['p256dh'], 'authToken' => $sub['auth_token']]);
            $webPush->queueNotification($subscription, $payload);
        }
        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                $db->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?")->execute([$report->getEndpoint()]);
            }
        }
    } catch (Exception $e) { error_log("Push notification error: " . $e->getMessage()); }

    echo json_encode(['success'=>true, 'correlativo'=>$correlativo]);

} catch (Exception $e) {
    echo json_encode(['success'=>false, 'error'=>'Error: ' . $e->getMessage()]);
}
