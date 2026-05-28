<?php
// includes/GoogleDriveHelper.php

require_once __DIR__ . '/../vendor/autoload.php';

class GoogleDriveHelper {
    private $client;
    private $service;
    private $isConfigured = false;

    public function __construct() {
        global $db;
        $this->client = new \Google_Client();
        
        try {
            $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('drive_client_id', 'drive_client_secret', 'drive_refresh_token')");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $clientId = $settings['drive_client_id'] ?? '';
            $clientSecret = $settings['drive_client_secret'] ?? '';
            $refreshToken = $settings['drive_refresh_token'] ?? '';
            
            if ($clientId && $clientSecret && $refreshToken) {
                $this->client->setClientId($clientId);
                $this->client->setClientSecret($clientSecret);
                $this->client->addScope(\Google_Service_Drive::DRIVE);
                $this->client->setAccessType('offline');
                
                // Fetch new access token with the refresh token
                $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
                
                if (!$this->client->isAccessTokenExpired()) {
                    $this->service = new \Google_Service_Drive($this->client);
                    $this->isConfigured = true;
                }
            }
        } catch (Exception $e) {
            error_log("Error al configurar Google Drive: " . $e->getMessage());
        }
    }

    public function isConfigured() {
        return $this->isConfigured;
    }

    public function getAccessToken() {
        if (!$this->isConfigured) return null;
        $token = $this->client->getAccessToken();
        return is_array($token) && isset($token['access_token']) ? $token['access_token'] : null;
    }

    /**
     * Crea una carpeta en Google Drive
     * @param string $folderName Nombre de la carpeta
     * @param string|null $parentFolderId ID de la carpeta padre (opcional)
     * @return string|false ID de la carpeta creada o false en caso de error
     */
    public function createFolder($folderName, $parentFolderId = null) {
        if (!$this->isConfigured) return false;

        $fileMetadata = new \Google_Service_Drive_DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder'
        ]);

        if ($parentFolderId) {
            $fileMetadata->setParents([$parentFolderId]);
        }

        try {
            $folder = $this->service->files->create($fileMetadata, ['fields' => 'id']);
            return $folder->id;
        } catch (Exception $e) {
            error_log("Error creando carpeta en Drive: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crea una presentación en blanco de Google Slides
     * @param string $title Nombre de la presentación
     * @param string|null $parentFolderId ID de la carpeta destino (opcional)
     * @return array|false Datos de la presentación (id, webViewLink) o false
     */
    public function createGoogleSlide($title, $parentFolderId = null) {
        if (!$this->isConfigured) return false;

        $fileMetadata = new \Google_Service_Drive_DriveFile([
            'name' => $title,
            'mimeType' => 'application/vnd.google-apps.presentation'
        ]);

        if ($parentFolderId) {
            $fileMetadata->setParents([$parentFolderId]);
        }

        try {
            $file = $this->service->files->create($fileMetadata, ['fields' => 'id, webViewLink']);
            
            // Hacerlo editable/público para la agencia si es necesario (opcional)
            $this->makePublicEditor($file->id);

            return [
                'id' => $file->id,
                'webViewLink' => $file->webViewLink
            ];
        } catch (Exception $e) {
            error_log("Error creando Google Slide: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Agrega una diapositiva en blanco a una presentación existente
     * @param string $presentationId ID de la presentación maestra
     * @return string|false ID del slide creado (objectId) o false en caso de error
     */
    public function appendSlideToPresentation($presentationId) {
        if (!$this->isConfigured) return false;

        try {
            $slidesService = new \Google_Service_Slides($this->client);
            
            // Generate a unique object ID for the new slide
            $slideObjectId = 'slide_' . uniqid();
            
            $requests = [
                new \Google_Service_Slides_Request([
                    'createSlide' => [
                        'objectId' => $slideObjectId,
                        'slideLayoutReference' => [
                            'predefinedLayout' => 'BLANK'
                        ]
                    ]
                ])
            ];
            
            $batchUpdateRequest = new \Google_Service_Slides_BatchUpdatePresentationRequest([
                'requests' => $requests
            ]);
            
            $response = $slidesService->presentations->batchUpdate($presentationId, $batchUpdateRequest);
            
            return $slideObjectId;
        } catch (Exception $e) {
            error_log("Error agregando diapositiva a Slides: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lista Unidades Compartidas (Shared Drives)
     */
    public function listSharedDrives() {
        if (!$this->isConfigured) return false;
        try {
            $optParams = [
                'pageSize' => 50,
                'fields' => 'drives(id, name)'
            ];
            $results = $this->service->drives->listDrives($optParams);
            return $results->getDrives();
        } catch (Exception $e) {
            error_log("Error listSharedDrives: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lista carpetas dentro de un padre
     */
    public function listFolders($parentId = 'root', $driveId = null) {
        if (!$this->isConfigured) return false;
        try {
            $query = "mimeType='application/vnd.google-apps.folder' and trashed=false and '" . $parentId . "' in parents";
            
            $optParams = [
                'q' => $query,
                'pageSize' => 100,
                'fields' => 'files(id, name, iconLink, webViewLink, createdTime)',
                'orderBy' => 'name',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true
            ];

            if ($driveId) {
                $optParams['corpora'] = 'drive';
                $optParams['driveId'] = $driveId;
            }

            $results = $this->service->files->listFiles($optParams);
            return $results->getFiles();
        } catch (Exception $e) {
            error_log("Google Drive listFolders Error: " . $e->getMessage());
            return false;
        }
    }

    public function listFiles($parentId) {
        if (!$this->isConfigured) return false;
        try {
            $query = "trashed=false and '" . $parentId . "' in parents";
            
            $optParams = [
                'q' => $query,
                'pageSize' => 100,
                'fields' => 'files(id, name, mimeType, iconLink, webViewLink, webContentLink, createdTime, thumbnailLink, hasThumbnail)',
                'orderBy' => 'folder, name',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true
            ];

            $results = $this->service->files->listFiles($optParams);
            
            $files = [];
            foreach ($results->getFiles() as $file) {
                $files[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'mimeType' => $file->getMimeType(),
                    'iconLink' => $file->getIconLink(),
                    'webViewLink' => $file->getWebViewLink(),
                    'webContentLink' => $file->getWebContentLink(),
                    'createdTime' => $file->getCreatedTime(),
                    'hasThumbnail' => $file->getHasThumbnail(),
                    'thumbnailLink' => $file->getThumbnailLink()
                ];
            }
            return $files;
        } catch (Exception $e) {
            error_log("Google Drive listFiles Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene información de una sola carpeta (útil para breadcrumbs)
     */
    public function getFolderInfo($folderId) {
        if (!$this->isConfigured) return false;
        try {
            $optParams = [
                'supportsAllDrives' => true,
                'fields' => 'id, name, parents'
            ];
            return $this->service->files->get($folderId, $optParams);
        } catch (Exception $e) {
            error_log("Error getFolderInfo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Hace público un archivo con permisos de edición
     */
    public function makePublicEditor($fileId) {
        if (!$this->isConfigured) return false;
        try {
            $permission = new \Google_Service_Drive_Permission([
                'type' => 'anyone',
                'role' => 'writer'
            ]);
            $this->service->permissions->create($fileId, $permission);
            return true;
        } catch (Exception $e) {
            error_log("Error cambiando permisos de edición en Drive: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Hace público un archivo con permisos de lectura
     */
    public function makePublicViewer($fileId) {
        if (!$this->isConfigured) return false;
        try {
            $permission = new \Google_Service_Drive_Permission([
                'type' => 'anyone',
                'role' => 'reader'
            ]);
            $this->service->permissions->create($fileId, $permission);
            return true;
        } catch (Exception $e) {
            error_log("Error cambiando permisos de lectura en Drive: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sube un archivo a Google Drive
     * @param string $filePath Ruta local del archivo
     * @param string $fileName Nombre con el que se guardará
     * @param string|null $parentFolderId ID de la carpeta destino (opcional)
     * @return array|false Datos del archivo subido (id, webViewLink) o false
     */
    public function uploadFile($filePath, $fileName, $parentFolderId = null) {
        if (!$this->isConfigured || !file_exists($filePath)) return false;

        $fileMetadata = new \Google_Service_Drive_DriveFile([
            'name' => $fileName
        ]);

        if ($parentFolderId) {
            $fileMetadata->setParents([$parentFolderId]);
        }

        $mimeType = mime_content_type($filePath);
        if (!$mimeType) $mimeType = 'application/octet-stream';

        try {
            $this->client->setDefer(true);
            $request = $this->service->files->create($fileMetadata, ['fields' => 'id, webViewLink, webContentLink']);
            $this->client->setDefer(false);

            $chunkSizeBytes = 5 * 1024 * 1024; // 5MB chunks
            $media = new \Google_Http_MediaFileUpload(
                $this->client,
                $request,
                $mimeType,
                null,
                true,
                $chunkSizeBytes
            );
            $media->setFileSize(filesize($filePath));

            $status = false;
            $handle = fopen($filePath, "rb");
            while (!$status && !feof($handle)) {
                $chunk = fread($handle, $chunkSizeBytes);
                $status = $media->nextChunk($chunk);
            }
            fclose($handle);

            if ($status && isset($status['id'])) {
                // Dar permisos de lectura general si se necesita (opcional)
                $this->makePublic($status['id']);

                return [
                    'id' => $status['id'],
                    'webViewLink' => $status['webViewLink'],
                    'webContentLink' => $status['webContentLink']
                ];
            }
            return false;
        } catch (Exception $e) {
            if (isset($handle) && is_resource($handle)) fclose($handle);
            error_log("Error subiendo archivo a Drive: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crea una sesión de subida resumible en Google Drive
     * @param string $fileName Nombre del archivo
     * @param string $mimeType Tipo MIME
     * @param string|null $parentFolderId Carpeta destino
     * @return string|false URL de subida o false en error
     */
    public function createResumableUploadSession($fileName, $mimeType, $parentFolderId = null) {
        if (!$this->isConfigured) return false;

        $httpClient = $this->client->authorize();
        $url = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&supportsAllDrives=true';
        
        $metadata = ['name' => $fileName];
        if ($parentFolderId) {
            $metadata['parents'] = [$parentFolderId];
        }

        try {
            $headers = [
                'X-Upload-Content-Type' => $mimeType,
                'Content-Type' => 'application/json'
            ];
            
            // Allow CORS for direct-from-browser uploads
            $origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $headers['Origin'] = $origin;

            $response = $httpClient->request('POST', $url, [
                'headers' => $headers,
                'body' => json_encode($metadata)
            ]);

            if ($response->getStatusCode() == 200) {
                return $response->getHeaderLine('Location');
            }
            return false;
        } catch (Exception $e) {
            error_log("Error creando sesión resumible en Drive: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Hace público un archivo o carpeta
     */
    public function makePublic($fileId) {
        if (!$this->isConfigured) return false;
        try {
            $permission = new \Google_Service_Drive_Permission([
                'type' => 'anyone',
                'role' => 'reader'
            ]);
            $this->service->permissions->create($fileId, $permission, ['supportsAllDrives' => true]);
            return true;
        } catch (Exception $e) {
            error_log("Error cambiando permisos en Drive: " . $e->getMessage());
            return false;
        }
    }
    public function deleteFile($fileId) {
        if (!$this->isConfigured) return false;
        try {
            $this->service->files->delete($fileId);
            return true;
        } catch (Exception $e) {
            error_log("Error eliminando archivo en Drive: " . $e->getMessage());
            return false;
        }
    }
    public function deleteFileByName($name, $parentFolderId) {
        if (!$this->isConfigured) return false;
        try {
            $q = "name='" . str_replace("'", "\\'", $name) . "' and '" . $parentFolderId . "' in parents and trashed=false";
            $optParams = [
                'q' => $q,
                'fields' => 'files(id)'
            ];
            $results = $this->service->files->listFiles($optParams);
            $files = $results->getFiles();
            foreach ($files as $file) {
                $this->service->files->delete($file->getId());
            }
            return true;
        } catch (Exception $e) {
            error_log("Error eliminando archivo por nombre en Drive: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Renombra un archivo o carpeta
     * @param string $fileId ID del archivo/carpeta
     * @param string $newName Nuevo nombre
     * @return array|false Datos actualizados del archivo o false en caso de error
     */
    public function renameFile($fileId, $newName) {
        if (!$this->isConfigured) return false;
        try {
            $fileMetadata = new \Google_Service_Drive_DriveFile([
                'name' => $newName
            ]);
            $updatedFile = $this->service->files->update($fileId, $fileMetadata, [
                'fields' => 'id, name'
            ]);
            return [
                'id' => $updatedFile->id,
                'name' => $updatedFile->name
            ];
        } catch (Exception $e) {
            error_log("Error renombrando archivo en Drive: " . $e->getMessage());
            return false;
        }
    }
}
