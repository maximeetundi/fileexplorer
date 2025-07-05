<?php
/**
 * Explorateur de Fichiers - Serveur PHP Amélioré
 * Version corrigée avec gestion d'erreurs et optimisations
 */

// Configuration de sécurité et performance
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
error_reporting(E_ALL);

// Augmenter les limites de temps et mémoire
ini_set('max_execution_time', 300); // 5 minutes
ini_set('memory_limit', '256M');
ini_set('max_input_time', 300);

// Augmenter les limites pour le téléversement de fichiers
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '55M'); // Doit être supérieur à upload_max_filesize

// Inclusion des modules
require_once 'file_explorer_terminal.php';

// Configuration - Utilisation du répertoire du script comme racine
define('BASE_PATH', __DIR__);
define('UPLOAD_MAX_SIZE', 50 * 1024 * 1024); // 50MB
define('MAX_FILE_READ_SIZE', 10 * 1024 * 1024); // 10MB pour lecture
define('ALLOWED_EXTENSIONS', [
    'txt', 'html', 'css', 'js', 'php', 'py', 'rs', 'cpp', 'c', 'java', 
    'json', 'xml', 'md', 'log', 'yml', 'yaml', 'ini', 'conf',
    'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp', 'ico',
    'mp4', 'avi', 'mov', 'mkv', 'webm', 'wmv', 'flv',
    'mp3', 'wav', 'flac', 'ogg', 'aac', 'wma',
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'exe', 'bat'
]);

// Headers pour JSON et CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Gestion des requêtes OPTIONS (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Classe pour gérer les erreurs et logs
 */
class Logger {
    public static function error($message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        error_log("[$timestamp] ERROR: $message $contextStr");
    }
    
    public static function info($message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        error_log("[$timestamp] INFO: $message $contextStr");
    }
}

/**
 * Classe principale de l'explorateur de fichiers
 */
class FileExplorer {
    private $basePath;
    private $protectedFiles = [
        // Fichiers principaux de l'explorateur
        'file_explorer.html',
        'file_explorer_transfer.js',
        'file_explorer_server.php',
        'file_explorer_js.js',
        'file_explorer_terminal.php',
        'file_explorer_terminal.js',
        'terminal.html',
        
        // Fichiers de l'éditeur
        'editor.html',
        'editor.js',
        
        // Configuration et logs
        'requirements.txt',
        'error.log',
        
        // Dossiers importants
        'vendor',
        'assets',
        'css',
        'js',
        'images',
        'uploads',
        
        // Fichiers système et de versioning
        '.git',
        '.gitignore',
        '.htaccess',
        'composer.json',
        'composer.lock',
        'package.json',
        'package-lock.json',
        'README.md',
        'LICENSE',
        
        // Fichiers d'IDE
        '.idea',
        '.vscode'
    ];
    
    public function __construct() {
        $this->basePath = BASE_PATH;
        
        // Vérifier que le répertoire de base existe
        if (!is_dir($this->basePath)) {
            throw new Exception("Le répertoire de base n'existe pas");
        }
    }

    /**
     * Récupérer la configuration du serveur
     */
    public function getConfig() {
        return [
            'success' => true,
            'config' => [
                'upload_max_size' => UPLOAD_MAX_SIZE,
                'allowed_extensions' => ALLOWED_EXTENSIONS
            ]
        ];
    }
    
    /**
     * Sécuriser les chemins avec validation renforcée
     */
    public function sanitizePath($path) {
        try {
            // Nettoyer le chemin
            $path = str_replace(['../', '..\\', '..', '\\'], '', $path);
            $path = trim($path, '/\\');
            
            // Construire le chemin complet
            $fullPath = $this->basePath . DIRECTORY_SEPARATOR . $path;
            
            // Résoudre le chemin réel
            $realPath = realpath($fullPath);
            
            // Si le fichier n'existe pas, vérifier le répertoire parent
            if (!$realPath) {
                $parentDir = dirname($fullPath);
                $realParentPath = realpath($parentDir);
                
                if ($realParentPath && $this->isPathAllowed($realParentPath)) {
                    return $fullPath; // Retourner le chemin pour création
                }
            }
            
            // Vérifier que le chemin est dans le répertoire autorisé
            if (!$realPath || !$this->isPathAllowed($realPath)) {
                return false;
            }
            
            return $realPath;
        } catch (Exception $e) {
            Logger::error("Erreur sanitizePath", ['path' => $path, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Vérifier si un chemin est autorisé
     */
    private function isPathAllowed($path) {
        $basePath = realpath($this->basePath);
        return $basePath && strpos($path, $basePath) === 0;
    }
    
    /**
     * Obtenir les informations d'un fichier avec gestion d'erreurs
     */
    public function getFileInfo($filePath) {
        try {
            if (!file_exists($filePath)) {
                return false;
            }
            
            $stat = stat($filePath);
            if (!$stat) {
                return false;
            }
            
            $name = basename($filePath);
            $relativePath = str_replace($this->basePath, '', $filePath);
            $relativePath = str_replace('\\', '/', $relativePath);
            
            return [
                'name' => $name,
                'path' => $relativePath,
                'type' => is_dir($filePath) ? 'directory' : 'file',
                'size' => $stat['size'],
                'modified' => $stat['mtime'],
                'permissions' => substr(sprintf('%o', fileperms($filePath)), -4),
                'readable' => is_readable($filePath),
                'writable' => is_writable($filePath)
            ];
        } catch (Exception $e) {
            Logger::error("Erreur getFileInfo", ['path' => $filePath, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Lister les fichiers avec pagination et limite
     */
    public function listFiles($path, $sortBy = 'name', $limit = 1000) {
        try {
            $realPath = $this->sanitizePath($path);
            
            if (!$realPath || !is_dir($realPath)) {
                return ['success' => false, 'message' => 'Répertoire non trouvé'];
            }
            
            // Vérifier les permissions
            if (!is_readable($realPath)) {
                return ['success' => false, 'message' => 'Répertoire non accessible'];
            }
            
            $files = [];
            $handle = opendir($realPath);
            
            if (!$handle) {
                return ['success' => false, 'message' => 'Impossible d\'ouvrir le répertoire'];
            }
            
            $count = 0;
            while (($item = readdir($handle)) !== false && $count < $limit) {
                if ($item === '.' || $item === '..') continue;
                
                // Masquer les fichiers de l'application
                if (in_array($item, $this->protectedFiles)) continue;
                
                $itemPath = $realPath . DIRECTORY_SEPARATOR . $item;
                $fileInfo = $this->getFileInfo($itemPath);
                
                if ($fileInfo) {
                    $files[] = $fileInfo;
                    $count++;
                }
            }
            closedir($handle);
            
            // Trier les fichiers
            $this->sortFiles($files, $sortBy);
            
            return [
                'success' => true, 
                'files' => $files,
                'total' => count($files),
                'limited' => $count >= $limit
            ];
            
        } catch (Exception $e) {
            Logger::error("Erreur listFiles", ['path' => $path, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erreur lors de la lecture du répertoire'];
        }
    }
    
    /**
     * Trier les fichiers
     */
    private function sortFiles(&$files, $sortBy) {
        usort($files, function($a, $b) use ($sortBy) {
            // Dossiers en premier
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            
            switch ($sortBy) {
                case 'size':
                    return $a['size'] <=> $b['size'];
                case 'modified':
                    return $b['modified'] <=> $a['modified'];
                case 'type':
                    return $a['type'] <=> $b['type'];
                default:
                    return strcasecmp($a['name'], $b['name']);
            }
        });
    }
    
    /**
     * Lire un fichier avec protection contre les fichiers trop volumineux
     */
    public function readFile($path, $preview = false) {
        try {
            $realPath = $this->sanitizePath($path);
            
            if (!$realPath || !file_exists($realPath) || is_dir($realPath)) {
                return ['success' => false, 'message' => 'Fichier non trouvé'];
            }
            
            if (!is_readable($realPath)) {
                return ['success' => false, 'message' => 'Fichier non accessible'];
            }
            
            $fileSize = filesize($realPath);
            
            // Vérifier la taille du fichier
            if ($fileSize > MAX_FILE_READ_SIZE) {
                return ['success' => false, 'message' => 'Fichier trop volumineux (max ' . (MAX_FILE_READ_SIZE/1024/1024) . ' MB)'];
            }
            
            // Lire le fichier par chunks pour éviter les problèmes de mémoire
            $handle = fopen($realPath, 'rb');
            if (!$handle) {
                return ['success' => false, 'message' => 'Impossible d\'ouvrir le fichier'];
            }
            
            $content = '';
            $maxRead = $preview ? 1000 : $fileSize;
            
            while (!feof($handle) && strlen($content) < $maxRead) {
                $chunk = fread($handle, 8192);
                if ($chunk === false) break;
                $content .= $chunk;
            }
            fclose($handle);
            
            if ($preview && strlen($content) > 1000) {
                $content = substr($content, 0, 1000) . '...';
            }
            
            return ['success' => true, 'content' => $content, 'size' => $fileSize];
            
        } catch (Exception $e) {
            Logger::error("Erreur readFile", ['path' => $path, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erreur lors de la lecture du fichier'];
        }
    }
    
    /**
     * Écrire un fichier avec sauvegarde de sécurité
     */
    public function writeFile($path, $content) {
        try {
            $realPath = $this->sanitizePath($path);
            
            if (!$realPath) {
                return ['success' => false, 'message' => 'Chemin invalide'];
            }
            
            // Vérifier l'extension
            $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_EXTENSIONS)) {
                return ['success' => false, 'message' => 'Type de fichier non autorisé'];
            }
            
            // Créer le répertoire parent si nécessaire
            $parentDir = dirname($realPath);
            if (!is_dir($parentDir)) {
                if (!mkdir($parentDir, 0755, true)) {
                    return ['success' => false, 'message' => 'Impossible de créer le répertoire parent'];
                }
            }
            
            // Sauvegarde du fichier existant
            if (file_exists($realPath)) {
                $backupPath = $realPath . '.bak.' . time();
                if (!copy($realPath, $backupPath)) {
                    Logger::error("Impossible de créer la sauvegarde", ['path' => $realPath]);
                }
            }
            
            // Écrire le fichier avec un fichier temporaire
            $tempPath = $realPath . '.tmp.' . uniqid();
            
            if (file_put_contents($tempPath, $content, LOCK_EX) === false) {
                return ['success' => false, 'message' => 'Erreur lors de l\'écriture du fichier temporaire'];
            }
            
            // Déplacer le fichier temporaire
            if (!rename($tempPath, $realPath)) {
                unlink($tempPath);
                return ['success' => false, 'message' => 'Erreur lors du déplacement du fichier'];
            }
            
            return ['success' => true, 'message' => 'Fichier sauvegardé avec succès'];
            
        } catch (Exception $e) {
            Logger::error("Erreur writeFile", ['path' => $path, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erreur lors de l\'écriture du fichier'];
        }
    }
    
    /**
     * Supprimer un fichier ou dossier avec protection
     */
    public function deleteFile($path) {
        try {
            $realPath = $this->sanitizePath($path);
            
            if (!$realPath || !file_exists($realPath)) {
                return ['success' => false, 'message' => 'Fichier non trouvé'];
            }
            
            if (!is_writable($realPath)) {
                return ['success' => false, 'message' => 'Fichier non modifiable'];
            }
            
            if (is_dir($realPath)) {
                if (!$this->deleteDirectory($realPath)) {
                    return ['success' => false, 'message' => 'Erreur lors de la suppression du répertoire'];
                }
                return ['success' => true, 'message' => 'Répertoire supprimé avec succès'];
            } else {
                if (!unlink($realPath)) {
                    return ['success' => false, 'message' => 'Erreur lors de la suppression du fichier'];
                }
                return ['success' => true, 'message' => 'Fichier supprimé avec succès'];
            }
            
        } catch (Exception $e) {
            Logger::error("Erreur deleteFile", ['path' => $path, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erreur lors de la suppression'];
        }
    }
    
    /**
     * Supprimer un répertoire récursivement
     */
    private function deleteDirectory($dir) {
        try {
            if (!is_dir($dir)) return false;
            
            $files = array_diff(scandir($dir), ['.', '..']);
            
            foreach ($files as $file) {
                $filePath = $dir . DIRECTORY_SEPARATOR . $file;
                if (is_dir($filePath)) {
                    if (!$this->deleteDirectory($filePath)) {
                        return false;
                    }
                } else {
                    if (!unlink($filePath)) {
                        return false;
                    }
                }
            }
            
            return rmdir($dir);
            
        } catch (Exception $e) {
            Logger::error("Erreur deleteDirectory", ['dir' => $dir, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Gérer les téléchargements avec validation améliorée
     */
    public function uploadFiles($targetPath, $files) {
        try {
            $realPath = $this->sanitizePath($targetPath);
            
            if (!$realPath || !is_dir($realPath)) {
                return ['success' => false, 'message' => 'Répertoire cible invalide'];
            }
            
            if (!is_writable($realPath)) {
                return ['success' => false, 'message' => 'Répertoire non modifiable'];
            }
            
            $uploadedFiles = [];
            $errors = [];
            
            $fileCount = is_array($files['tmp_name']) ? count($files['tmp_name']) : 1;
            
            for ($i = 0; $i < $fileCount; $i++) {
                $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                $fileSize = is_array($files['size']) ? $files['size'][$i] : $files['size'];
                $fileError = is_array($files['error']) ? $files['error'][$i] : $files['error'];
                
                // Vérifier les erreurs
                if ($fileError !== UPLOAD_ERR_OK) {
                    $errors[] = "Erreur lors du téléchargement de $fileName (code: $fileError)";
                    continue;
                }
                
                // Vérifier la taille
                if ($fileSize > UPLOAD_MAX_SIZE) {
                    $errors[] = "$fileName est trop volumineux (max " . (UPLOAD_MAX_SIZE/1024/1024) . " MB)";
                    continue;
                }
                
                // Vérifier l'extension
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if (!in_array($ext, ALLOWED_EXTENSIONS)) {
                    $errors[] = "Type de fichier non autorisé pour $fileName";
                    continue;
                }
                
                // Sécuriser le nom de fichier
                $fileName = $this->sanitizeFileName($fileName);
                $targetFile = $realPath . DIRECTORY_SEPARATOR . $fileName;
                
                // Gérer les doublons
                $targetFile = $this->getUniqueFilename($targetFile);
                
                if (move_uploaded_file($tmpName, $targetFile)) {
                    $uploadedFiles[] = basename($targetFile);
                } else {
                    $errors[] = "Erreur lors du déplacement de $fileName";
                }
            }
            
            if (!empty($errors) && empty($uploadedFiles)) {
                return ['success' => false, 'message' => 'Échec du téléversement : ' . implode(', ', $errors)];
            }

            $message = count($uploadedFiles) . ' fichier(s) téléchargé(s) avec succès.';
            if (!empty($errors)) {
                $message .= ' Erreurs : ' . implode(', ', $errors);
            }

            return [
                'success' => true,
                'message' => $message,
                'files' => $uploadedFiles,
                'errors' => $errors
            ];            
        } catch (Exception $e) {
            Logger::error("Erreur uploadFiles", ['path' => $targetPath, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erreur lors du téléchargement'];
        }
    }
    
    /**
     * Sécuriser le nom de fichier
     */
    private function sanitizeFileName($fileName) {
        // Supprimer les caractères dangereux
        $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
        
        // Limiter la longueur
        if (strlen($fileName) > 255) {
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            $name = substr(pathinfo($fileName, PATHINFO_FILENAME), 0, 250 - strlen($ext));
            $fileName = $name . '.' . $ext;
        }
        
        return $fileName;
    }
    
    /**
     * Obtenir un nom de fichier unique
     */
    private function getUniqueFilename($filePath) {
        $counter = 1;
        $originalPath = $filePath;
        $pathInfo = pathinfo($filePath);
        
        while (file_exists($filePath)) {
            $filePath = $pathInfo['dirname'] . DIRECTORY_SEPARATOR . 
                       $pathInfo['filename'] . '_' . $counter . 
                       (isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '');
            $counter++;
            
            // Éviter les boucles infinies
            if ($counter > 9999) {
                break;
            }
        }
        
        return $filePath;
    }
    
    /**
     * Servir les fichiers pour téléchargement/aperçu
     */
    public function serveFile($path, $action = 'download') {
        try {
            $realPath = $this->sanitizePath($path);
            
            if (!$realPath || !file_exists($realPath) || is_dir($realPath)) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Fichier non trouvé']);
                return;
            }
            
            if (!is_readable($realPath)) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Fichier non accessible']);
                return;
            }
            
            $fileName = basename($realPath);
            $fileSize = filesize($realPath);
            
            // Déterminer le type MIME
            $mimeType = 'application/octet-stream';
            if (function_exists('mime_content_type')) {
                $detectedMime = mime_content_type($realPath);
                if ($detectedMime) {
                    $mimeType = $detectedMime;
                }
            }
            
            // Headers pour le téléchargement
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . $fileSize);
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            
            if ($action === 'download') {
                header('Content-Disposition: attachment; filename="' . $fileName . '"');
            } else {
                header('Content-Disposition: inline; filename="' . $fileName . '"');
            }
            
            // Lire le fichier par chunks pour éviter les problèmes de mémoire
            $handle = fopen($realPath, 'rb');
            if ($handle) {
                while (!feof($handle)) {
                    $chunk = fread($handle, 8192);
                    echo $chunk;
                    flush();
                }
                fclose($handle);
            }
            
        } catch (Exception $e) {
            Logger::error("Erreur serveFile", ['path' => $path, 'error' => $e->getMessage()]);
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
        }
    }

    public function executeCommand($command, $currentPath) {
        try {
            // Vérifier si proc_open est disponible
            if (!function_exists('proc_open') || in_array('proc_open', array_map('trim', explode(',', ini_get('disable_functions'))))) {
                return ['success' => false, 'message' => 'Erreur serveur : proc_open() est désactivé pour des raisons de sécurité.'];
            }

            $fullPath = $this->sanitizePath($currentPath);
            if (!$fullPath || !is_dir($fullPath)) {
                return ['success' => false, 'message' => 'Chemin invalide ou non trouvé'];
            }

            // Séparer la commande et ses arguments
            $parts = preg_split('/\s+/', trim($command));
            $cmd = array_shift($parts);
            $args = $parts;

            // ATTENTION: La validation des commandes a été désactivée à la demande de l'utilisateur.
            // Ceci représente un risque de sécurité majeur, autorisant l'exécution de n'importe quelle commande
            // sur le serveur par l'utilisateur de l'application.
            // Il est fortement recommandé de réactiver une liste blanche de commandes.

            // Cas spécial pour 'cd' qui doit être géré par l'application
            if ($cmd === 'cd') {
                $targetDir = $args[0] ?? '.';
                
                $newPath = realpath($fullPath . DIRECTORY_SEPARATOR . $targetDir);

                if ($newPath && $this->isPathAllowed($newPath) && is_dir($newPath)) {
                    $relativePath = str_replace(realpath($this->basePath), '', $newPath);
                    $relativePath = str_replace('\\', '/', $relativePath);
                    return [
                        'success' => true,
                        'output' => "Nouveau répertoire : " . ($relativePath ?: '/'),
                        'path' => $relativePath ?: '/'
                    ];
                } else {
                    return ['success' => false, 'message' => 'Répertoire non trouvé ou accès refusé'];
                }
            }

            // Gérer les alias (ls/dir)
            if ($cmd === 'ls' && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = 'dir';
            } elseif ($cmd === 'dir' && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                $cmd = 'ls -lA'; // -A pour ne pas montrer . et ..
            }

            // Sécuriser les arguments
            $safe_args = array_map('escapeshellarg', $args);
            $fullCommand = $cmd . ' ' . implode(' ', $safe_args);

            // Exécuter la commande dans le répertoire spécifié
            $descriptorspec = [
               0 => ["pipe", "r"],  // stdin
               1 => ["pipe", "w"],  // stdout
               2 => ["pipe", "w"],  // stderr
            ];
            
            $process = proc_open($fullCommand, $descriptorspec, $pipes, $fullPath);
            
            if (is_resource($process)) {
                fclose($pipes[0]);

                $output = stream_get_contents($pipes[1]);
                fclose($pipes[1]);

                $error = stream_get_contents($pipes[2]);
                fclose($pipes[2]);

                proc_close($process);

                if (!empty($error)) {
                    return ['success' => false, 'message' => $error];
                }
                
                // Gérer l'encodage de la sortie, surtout pour Windows
                if (function_exists('mb_convert_encoding')) {
                    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                        // Les consoles Windows utilisent souvent des pages de codes comme CP850.
                        // On tente de convertir depuis CP850 (ou la page de code OEM) vers UTF-8.
                        $output = mb_convert_encoding($output, 'UTF-8', 'CP850');
                    }
                    // Pour les autres systèmes, ou après la conversion, on s'assure que c'est du UTF-8 valide.
                    $output = mb_convert_encoding($output, 'UTF-8', 'UTF-8');
                } else {
                    // Fallback si mbstring n'est pas disponible.
                    // Supprime les caractères de contrôle non imprimables qui peuvent casser le JSON.
                    $output = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $output);
                }

                return ['success' => true, 'output' => $output];
            } else {
                return ['success' => false, 'message' => 'Impossible d\'exécuter la commande.'];
            }

        } catch (Exception $e) {
            Logger::error("Erreur executeCommand", ['command' => $command, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erreur serveur lors de l\'exécution de la commande.'];
        }
    }

    public function createFolder($currentPath, $folderName) {
        $fullPath = $this->sanitizePath($currentPath);
        $newFolderPath = $fullPath . DIRECTORY_SEPARATOR . $folderName;

        if (file_exists($newFolderPath)) {
            return ['success' => false, 'message' => 'Un dossier avec ce nom existe déjà.'];
        }

        if (mkdir($newFolderPath)) {
            return ['success' => true];
        } else {
            return ['success' => false, 'message' => 'Impossible de créer le dossier. Vérifiez les permissions.'];
        }
    }

    public function createFile($currentPath, $fileName) {
        $newFilePath = $this->sanitizePath($currentPath . '/' . $fileName);
        if (!$newFilePath) {
            return ['success' => false, 'message' => 'Chemin invalide'];
        }

        if (file_exists($newFilePath)) {
            return ['success' => false, 'message' => 'Un fichier avec ce nom existe déjà'];
        }

        if (@file_put_contents($newFilePath, '') === false) {
            return ['success' => false, 'message' => 'Impossible de créer le fichier'];
        }

        return ['success' => true];
    }

    public function renameItem($path, $newName) {
        $oldPath = $this->sanitizePath($path);
        if (!$oldPath) {
            return ['success' => false, 'message' => 'Chemin source invalide'];
        }

        $newPath = dirname($oldPath) . DIRECTORY_SEPARATOR . $this->sanitizeFileName($newName);
        if (file_exists($newPath)) {
            return ['success' => false, 'message' => 'Un fichier avec le nouveau nom existe déjà'];
        }

        if (rename($oldPath, $newPath)) {
            return ['success' => true];
        } else {
            return ['success' => false, 'message' => 'Impossible de renommer l\'élément'];
        }
    }

    public function moveItem($source, $destination) {
        try {
            $sourcePath = $this->sanitizePath($source);
            $destDir = $this->sanitizePath($destination);

            if (!$sourcePath || !$destDir || !is_dir($destDir)) {
                return ['success' => false, 'message' => 'Chemin source ou de destination invalide.'];
            }

            $finalDestPath = $destDir . DIRECTORY_SEPARATOR . basename($sourcePath);

            if (file_exists($finalDestPath)) {
                return ['success' => false, 'message' => 'Un élément du même nom existe déjà à la destination.'];
            }

            if (rename($sourcePath, $finalDestPath)) {
                return ['success' => true, 'message' => 'Élément déplacé avec succès.'];
            } else {
                return ['success' => false, 'message' => 'Impossible de déplacer l\'élément.'];
            }
        } catch (Exception $e) {
            Logger::error("Erreur moveItem", ['source' => $source, 'destination' => $destination, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erreur serveur lors du déplacement.'];
        }
    }

    public function copyItem($source, $destination) {
        try {
            $sourcePath = $this->sanitizePath($source);
            $destDir = $this->sanitizePath($destination);

            if (!$sourcePath || !$destDir || !is_dir($destDir)) {
                return ['success' => false, 'message' => 'Chemin source ou de destination invalide.'];
            }

            $finalDestPath = $destDir . DIRECTORY_SEPARATOR . basename($sourcePath);

            if (file_exists($finalDestPath)) {
                return ['success' => false, 'message' => 'Un élément du même nom existe déjà à la destination.'];
            }

            if (is_dir($sourcePath)) {
                return $this->copyDirectory($sourcePath, $finalDestPath);
            } else {
                if (copy($sourcePath, $finalDestPath)) {
                    return ['success' => true, 'message' => 'Élément copié avec succès.'];
                } else {
                    return ['success' => false, 'message' => 'Impossible de copier le fichier.'];
                }
            }
        } catch (Exception $e) {
            Logger::error("Erreur copyItem", ['source' => $source, 'destination' => $destination, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erreur serveur lors de la copie.'];
        }
    }

    private function copyDirectory($source, $dest)
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $destPath = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($item, $destPath);
            }
        }
    }

    public function ensureDirectory($path)
    {
        $fullPath = $this->sanitizePath($path);
        if (!$fullPath) {
            return ['success' => false, 'message' => 'Chemin invalide.'];
        }

        if (!is_dir($fullPath)) {
            if (!mkdir($fullPath, 0755, true)) {
                return ['success' => false, 'message' => 'Impossible de créer le dossier.'];
            }
        }
        return ['success' => true, 'path' => $path];
    }

    public function downloadFromUrl($url, $path)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'URL invalide.'];
        }

        $destinationPath = $this->sanitizePath($path);
        if (!$destinationPath) {
            return ['success' => false, 'message' => 'Chemin de destination invalide.'];
        }

        $filename = basename(parse_url($url, PHP_URL_PATH));
        if (empty($filename)) {
            $filename = 'downloaded_file';
        }

        $fullFilePath = $destinationPath . DIRECTORY_SEPARATOR . $filename;

        $fileContent = @file_get_contents($url);
        if ($fileContent === false) {
            return ['success' => false, 'message' => 'Impossible de télécharger le fichier depuis l\'URL.'];
        }

        if (file_put_contents($fullFilePath, $fileContent) === false) {
            return ['success' => false, 'message' => 'Impossible de sauvegarder le fichier sur le serveur.'];
        }

        return ['success' => true];
    }
}

// Fonction pour nettoyer les sessions/ressources
function cleanup() {
    // Nettoyer les sessions expirées
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    
    // Forcer la libération de la mémoire
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
}

// Gérer l'arrêt du script
register_shutdown_function('cleanup');

// Traitement des requêtes
try {
    $fileExplorer = new FileExplorer();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Gérer les requêtes GET (téléchargement/aperçu)
        if (isset($_GET['action']) && isset($_GET['path'])) {
            $action = $_GET['action'];
            $path = $_GET['path'];
            
            if (in_array($action, ['download', 'preview'])) {
                $fileExplorer->serveFile($path, $action);
                exit;
            }
        }
        
        // Retourner une erreur pour les autres requêtes GET
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Requête invalide']);
        exit;
    }
    
    // Traitement des requêtes POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Fallback pour les données POST classiques
    if (!$data && !empty($_POST)) {
        $data = $_POST;
    }
    
    if (!isset($data['action'])) {
        echo json_encode(['success' => false, 'message' => 'Action non spécifiée']);
        exit;
    }
    
    $action = $data['action'];
    $response = ['success' => false, 'message' => 'Action non reconnue'];
    
    switch ($action) {
        case 'get_config':
            $response = $fileExplorer->getConfig();
            break;

        case 'list_files':
            $path = $data['path'] ?? '/';
            $sort = $data['sort'] ?? 'name';
            $limit = $data['limit'] ?? 1000;
            $response = $fileExplorer->listFiles($path, $sort, $limit);
            break;
            
        case 'get_file_content': // Action pour l'éditeur léger
        case 'read_file':
            $path = $data['path'] ?? '';
            $preview = $data['preview'] ?? false;
            $response = $fileExplorer->readFile($path, $preview);
            break;
            
        case 'save_file': // Action pour l'éditeur léger
        case 'write_file':
            $path = $data['path'] ?? '';
            $content = $data['content'] ?? '';
            $response = $fileExplorer->writeFile($path, $content);
            break;
            
        case 'delete_file':
            $path = $data['path'] ?? '';
            $response = $fileExplorer->deleteFile($path);
            break;
            
        case 'list_directory':
            $path = $data['path'] ?? $fileExplorer->currentPath;
            $files = [];
            
            if (is_dir($path)) {
                $items = scandir($path);
                foreach ($items as $item) {
                    if ($item !== '.' && $item !== '..') {
                        $fullPath = rtrim($path, '/') . '/' . $item;
                        $files[] = [
                            'name' => $item,
                            'is_dir' => is_dir($fullPath)
                        ];
                    }
                }
                $response = [
                    'success' => true,
                    'files' => $files
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Le chemin spécifié n\'est pas un répertoire.'
                ];
            }
            break;

    case 'execute_command':
        try {
            // Lire le corps de la requête JSON
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Erreur de décodage JSON: ' . json_last_error_msg());
            }
            
            $command = $data['command'] ?? '';
            $path = $data['path'] ?? $fileExplorer->currentPath;
            
            // Log de débogage
            error_log("Commande reçue: " . $command . " dans le répertoire: " . $path);
            
            if (empty($command)) {
                throw new Exception('Aucune commande spécifiée');
            }
            
            $terminalHandler = new TerminalHandler($fileExplorer);
            $response = $terminalHandler->executeCommand($command, $path);
            
            // Ajouter des informations de débogage à la réponse
            $response['debug'] = [
                'commande_executee' => $command,
                'repertoire_courant' => $path,
                'timestamp' => date('Y-m-d H:i:s'),
                'system' => php_uname()
            ];
            
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'output' => 'Erreur lors de l\'exécution de la commande: ' . $e->getMessage(),
                'debug' => [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'command' => $command ?? 'Non définie',
                    'path' => $path ?? 'Non défini',
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
        }
        break;
            
        case 'upload_files':
            $path = $_POST['path'] ?? '/';
            if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
                $response = $fileExplorer->uploadFiles($path, $_FILES['files']);
            } else {
                $response = ['success' => false, 'message' => 'Aucun fichier reçu'];
            }
            break;

        case 'create_folder':
            $path = $data['path'] ?? '/';
            $name = $data['name'] ?? '';
            $response = $fileExplorer->createFolder($path, $name);
            break;

        case 'create_file':
            $path = $data['path'] ?? '/';
            $name = $data['name'] ?? '';
            $response = $fileExplorer->createFile($path, $name);
            break;

        case 'rename_item':
            $path = $data['path'] ?? '';
            $newName = $data['newName'] ?? '';
            $response = $fileExplorer->renameItem($path, $newName);
            break;

        case 'copy_item':
            $source = $data['source'] ?? '';
            $destination = $data['destination'] ?? '';
            $response = $fileExplorer->copyItem($source, $destination);
            break;

        case 'move_item':
            $source = $data['source'] ?? '';
            $destination = $data['destination'] ?? '';
            $response = $fileExplorer->moveItem($source, $destination);
            break;

        case 'ensure_directory':
            $path = $data['path'] ?? '';
            $response = $fileExplorer->ensureDirectory($path);
            break;
        case 'download_from_url':
            $url = $data['url'] ?? '';
            $path = $data['path'] ?? '';
            $response = $fileExplorer->downloadFromUrl($url, $path);
            break;
        default:
            $response = ['success' => false, 'message' => 'Action non supportée'];
    }
    
    // Utiliser JSON_INVALID_UTF8_SUBSTITUTE pour éviter que json_encode ne retourne false en cas de caractères invalides.
    // Ceci est disponible à partir de PHP 7.2.
    if (version_compare(PHP_VERSION, '7.2.0', '>=')) {
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    } else {
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    Logger::error("Erreur générale", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur serveur interne',
        'error_code' => 'INTERNAL_ERROR'
    ]);
}
?>