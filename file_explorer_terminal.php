<?php
// file_explorer_terminal.php - Version Terminal Réel - CORRIGÉ
class TerminalHandler {
    private $explorer;
    private $currentPath;
    private $isWindows;
    
    public function __construct(FileExplorer $explorer) {
        $this->explorer = $explorer;
        $this->isWindows = (PHP_OS_FAMILY === 'Windows');
        $this->currentPath = $this->initializeCurrentPath();
    }
    
    /**
     * Initialise le répertoire courant
     */
    private function initializeCurrentPath() {
        // Définir le répertoire de base
        $basePath = defined('BASE_PATH') ? realpath(BASE_PATH) : __DIR__;
        
        // Vérifier que le répertoire de base existe et est accessible
        if (!is_dir($basePath) || !is_readable($basePath)) {
            $basePath = __DIR__; // Fallback vers le répertoire du script
        }
        
        $this->currentPath = $basePath;
    }
    
    /**
     * Obtient le répertoire courant
     */
    public function getCurrentPath() {
        return $this->currentPath;
    }
    
    /**
     * Définit le répertoire courant (avec validation)
     */
    public function setCurrentPath($path) {
        $sanitizedPath = $this->explorer->sanitizePath($path);
        if ($sanitizedPath && is_dir($sanitizedPath)) {
            $this->currentPath = $sanitizedPath;
            return true;
        }
        return false;
    }
    
    public function executeCommand($command, $currentPath = null) {
        // CORRECTION 2: Utiliser le currentPath fourni seulement s'il est valide
        if ($currentPath !== null) {
            $sanitizedPath = $this->explorer->sanitizePath($currentPath);
            if ($sanitizedPath && is_dir($sanitizedPath)) {
                $this->currentPath = $sanitizedPath;
            }
        }
        
        // CORRECTION 3: Vérifier que nous avons un répertoire courant valide
        if (!$this->currentPath || !is_dir($this->currentPath)) {
            $this->initializeCurrentPath(); // Ré-initialiser si nécessaire
        }
        
        // Vérification finale
        if (!is_dir($this->currentPath)) {
            return ['success' => false, 'output' => 'Impossible d\'initialiser le répertoire de travail.'];
        }
        
        // Gestion robuste de l'input
        if (is_array($command)) {
            $command = implode(' ', $command);
        }
        
        $command = trim((string)$command);
        
        // Debug détaillé
        if (empty($command)) {
            return [
                'success' => false, 
                'output' => "Commande vide détectée.\nRépertoire courant: {$this->currentPath}\nDébug info:\n" . 
                           "- Input original: " . var_export(func_get_arg(0), true) . "\n" .
                           "- Après traitement: '$command'\n" .
                           "- Longueur: " . strlen($command) . "\n" .
                           "- Type: " . gettype($command)
            ];
        }
        
        // Commande pour afficher le répertoire courant
        if ($command === 'pwd') {
            return ['success' => true, 'output' => $this->currentPath];
        }
        
        // Commandes spéciales intégrées uniquement
        if ($command === 'clear') {
            return ['success' => true, 'output' => '', 'clear' => true];
        }
        
        if ($command === 'help') {
            return ['success' => true, 'output' => implode("\n", $this->getHelpMessage())];
        }
        
        // Gestion spéciale pour CD car on doit changer le répertoire courant
        if (preg_match('/^cd\s+(.+)$/i', $command, $matches)) {
            return $this->changeDirectory($matches[1]);
        }
        
        if (strtolower($command) === 'cd') {
            return $this->changeDirectory('');
        }
        
        // Toutes les autres commandes sont exécutées directement par le système
        return $this->executeSystemCommand($command);
    }
    
    private function executeSystemCommand($command) {
        $output = null;
        $method = '';
        $returnCode = 0;
        
        // CORRECTION 4: Afficher le répertoire courant dans le debug
        $debugInfo = "Répertoire courant: {$this->currentPath}\n";
        
        // Construire la commande complète avec changement de répertoire
        $fullCommand = $this->buildFullCommand($command);
        
        // Méthode 1: proc_open (recommandée)
        if (function_exists('proc_open') && !$this->isFunctionDisabled('proc_open')) {
            $descriptorspec = [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout
                2 => ['pipe', 'w']   // stderr
            ];
            
            // IMPORTANT: Ne pas utiliser $fullCommand mais la commande originale
            // et utiliser le paramètre $cwd pour définir le répertoire de travail
            $process = @proc_open($command, $descriptorspec, $pipes, $this->currentPath);
            
            if (is_resource($process)) {
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                
                $returnCode = proc_close($process);
                
                $output = $stdout;
                if (!empty($stderr)) {
                    $output .= "\n" . $stderr;
                }
                
                $method = 'proc_open';
            }
        }
        
        // Méthode 2: shell_exec avec changement de répertoire
        if ($output === null && function_exists('shell_exec') && !$this->isFunctionDisabled('shell_exec')) {
            // Changer temporairement le répertoire de travail
            $oldCwd = getcwd();
            chdir($this->currentPath);
            
            $output = @shell_exec($command);
            
            // Restaurer le répertoire de travail
            chdir($oldCwd);
            
            $method = 'shell_exec';
        }
        
        // Méthode 3: exec avec changement de répertoire
        if ($output === null && function_exists('exec') && !$this->isFunctionDisabled('exec')) {
            // Changer temporairement le répertoire de travail
            $oldCwd = getcwd();
            chdir($this->currentPath);
            
            $execOutput = [];
            $returnCode = 0;
            @exec($command, $execOutput, $returnCode);
            
            // Restaurer le répertoire de travail
            chdir($oldCwd);
            
            if (!empty($execOutput)) {
                $output = implode("\n", $execOutput);
                $method = 'exec';
            }
        }
        
        // Méthode 4: system avec changement de répertoire
        if ($output === null && function_exists('system') && !$this->isFunctionDisabled('system')) {
            // Changer temporairement le répertoire de travail
            $oldCwd = getcwd();
            chdir($this->currentPath);
            
            ob_start();
            $returnCode = @system($command);
            $output = ob_get_clean();
            
            // Restaurer le répertoire de travail
            chdir($oldCwd);
            
            if ($returnCode !== false) {
                $method = 'system';
            }
        }
        
        // Méthode 5: passthru avec changement de répertoire
        if ($output === null && function_exists('passthru') && !$this->isFunctionDisabled('passthru')) {
            // Changer temporairement le répertoire de travail
            $oldCwd = getcwd();
            chdir($this->currentPath);
            
            ob_start();
            $returnCode = null;
            @passthru($command, $returnCode);
            $output = ob_get_clean();
            
            // Restaurer le répertoire de travail
            chdir($oldCwd);
            
            if ($returnCode === 0 || !empty($output)) {
                $method = 'passthru';
            }
        }
        
        // Si aucune méthode n'a fonctionné, essayer avec $fullCommand en dernier recours
        if ($output === null) {
            // Essayer avec la commande complète construite
            if (function_exists('shell_exec') && !$this->isFunctionDisabled('shell_exec')) {
                $output = @shell_exec($fullCommand);
                $method = 'shell_exec_full';
            }
        }
        
        // Si toujours rien, message d'erreur
        if ($output === null) {
            return [
                'success' => false, 
                'output' => $debugInfo . "Erreur: Impossible d'exécuter la commande '$command'.\n\nToutes les fonctions d'exécution système sont désactivées ou ont échoué.\n\nFonctions testées: proc_open, shell_exec, exec, system, passthru\n\nVérifiez la configuration PHP (disable_functions dans php.ini)."
            ];
        }
        
        // Nettoyer la sortie
        $output = rtrim($output);
        
        // Si la sortie est vide mais la commande a réussi
        if (empty($output) && $returnCode === 0) {
            $output = "Commande exécutée avec succès.";
        }
        
        return [
            'success' => true, 
            'output' => $output, 
            'method' => $method,
            'return_code' => $returnCode,
            'current_path' => $this->currentPath
        ];
    }
    
    private function buildFullCommand($command) {
        $isWindows = (PHP_OS_FAMILY === 'Windows');
        
        if ($isWindows) {
            // Sur Windows, utiliser cmd /c pour exécuter dans le bon répertoire
            $escapedPath = escapeshellarg($this->currentPath);
            return "cmd /c \"cd /d $escapedPath && $command\"";
        } else {
            // Sur Unix/Linux, utiliser cd && command
            $escapedPath = escapeshellarg($this->currentPath);
            return "cd $escapedPath && $command 2>&1";
        }
    }
    
    private function changeDirectory($path) {
        try {
            $basePath = defined('BASE_PATH') ? realpath(BASE_PATH) : __DIR__;
            
            if (empty($path)) {
                // cd sans argument va au répertoire racine (dossier du script)
                $newPath = $basePath;
            } else {
                $newPath = $this->resolvePath($path);
            }
            
            // Vérifier que le chemin est valide
            if (!is_dir($newPath)) {
                throw new Exception("Le répertoire '$newPath' n'existe pas.");
            }
            
            // Vérifier que le chemin est lisible
            if (!is_readable($newPath)) {
                throw new Exception("Accès refusé au répertoire '$newPath'.");
            }
            
            // Vérifier que le nouveau chemin est bien dans le dossier du script
            if (strpos(realpath($newPath), $basePath) !== 0) {
                throw new Exception("Accès non autorisé en dehors du dossier de l'application.");
            }
            
            // Mettre à jour le chemin courant
            $this->currentPath = $newPath;
            
            return [
                'success' => true, 
                'output' => "Répertoire changé vers: " . $newPath, 
                'newPath' => $newPath
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false, 
                'output' => 'Erreur: ' . $e->getMessage()
            ];
        }
    }
    
    private function resolvePath($path) {
        if (empty($path)) {
            return $this->currentPath;
        }
        
        // Utiliser le dossier du script comme base
        $basePath = defined('BASE_PATH') ? realpath(BASE_PATH) : __DIR__;
        $separator = DIRECTORY_SEPARATOR;
        
        // Gérer les chemins spéciaux
        if ($path === '~') {
            return $basePath; // Répertoire "home" = répertoire de base
        }
        
        // Si c'est un chemin absolu, vérifier qu'il est dans BASE_PATH
        if (substr($path, 0, 1) === $separator || (PHP_OS_FAMILY === 'Windows' && preg_match('/^[A-Za-z]:/', $path))) {
            $path = str_replace(['/', '\\'], $separator, $path);
            
            // Si le chemin commence par BASE_PATH, le garder tel quel
            if (strpos($path, $basePath) === 0) {
                return $path;
            }
            
            // Sinon, essayer de le rendre relatif à BASE_PATH
            $relativePath = ltrim(str_replace($basePath, '', $path), $separator);
            $newPath = $basePath . $separator . $relativePath;
            
            // Nettoyer le chemin (supprimer les références . et ..)
            $newPath = realpath($newPath);
            
            // Vérifier qu'on est toujours dans BASE_PATH
            if ($newPath === false || strpos($newPath, $basePath) !== 0) {
                return $this->currentPath; // Revenir au chemin actuel si tentative de sortie
            }
            
            return $newPath;
        }
        
        // Pour les chemins relatifs
        $currentPath = $this->currentPath;
        
        // Construire le chemin complet
        $fullPath = $currentPath . $separator . $path;
        $fullPath = str_replace(['/', '\\'], $separator, $fullPath);
        
        // Nettoyer le chemin (supprimer les références . et ..)
        $parts = [];
        foreach (explode($separator, $fullPath) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '' && $part !== '.') {
                $parts[] = $part;
            }
        }
        
        $newPath = implode($separator, $parts);
        
        // S'assurer qu'on ne sort pas de BASE_PATH
        if (strpos($newPath, $basePath) !== 0) {
            return $currentPath; // Revenir au chemin actuel si tentative de sortie
        }
        
        return $newPath;
    }
    
    /**
     * Teste le répertoire courant et affiche des informations de debug
     */
    public function testCurrentDirectory() {
        $result = [
            'php_cwd' => getcwd(),
            'current_path' => $this->currentPath,
            'is_dir' => is_dir($this->currentPath),
            'is_readable' => is_readable($this->currentPath),
            'realpath' => realpath($this->currentPath),
            'base_path' => defined('BASE_PATH') ? BASE_PATH : 'non défini',
            'dir_content' => []
        ];
        
        if (is_dir($this->currentPath) && is_readable($this->currentPath)) {
            $result['dir_content'] = array_slice(scandir($this->currentPath), 0, 10); // Limiter à 10 éléments
        }
        
        return $result;
    }
    
    private function isFunctionDisabled($function) {
        $disabled = explode(',', ini_get('disable_functions'));
        return in_array($function, array_map('trim', $disabled));
    }
    
    private function getHelpMessage() {
        $help = [
            "=== TERMINAL RÉEL ===",
            "",
            "Ce terminal exécute les vraies commandes système.",
            "Répertoire courant: " . $this->currentPath,
            "",
            "Commandes intégrées:",
            "  help    - Afficher cette aide",
            "  clear   - Effacer l'écran",
            "  pwd     - Afficher le répertoire courant",
            "  cd      - Changer de répertoire (géré spécialement)",
            "",
            "Toutes les autres commandes sont exécutées directement par le système.",
            ""
        ];
        
        if ($this->isWindows) {
            $help = array_merge($help, [
                "Exemples Windows:",
                "  dir                 - Lister le contenu",
                "  dir /a              - Lister avec fichiers cachés",
                "  type fichier.txt    - Afficher un fichier",
                "  echo Hello World    - Afficher du texte",
                "  mkdir dossier       - Créer un dossier",
                "  del fichier.txt     - Supprimer un fichier",
                "  copy src dest       - Copier un fichier",
                "  move src dest       - Déplacer un fichier",
                "  ipconfig            - Configuration réseau",
                "  tasklist            - Liste des processus",
                "  systeminfo          - Informations système"
            ]);
        } else {
            $help = array_merge($help, [
                "Exemples Unix/Linux:",
                "  ls                  - Lister le contenu",
                "  ls -la              - Lister avec détails",
                "  cat fichier.txt     - Afficher un fichier",
                "  echo Hello World    - Afficher du texte",
                "  mkdir dossier       - Créer un dossier",
                "  rm fichier.txt      - Supprimer un fichier",
                "  cp src dest         - Copier un fichier",
                "  mv src dest         - Déplacer un fichier",
                "  ifconfig            - Configuration réseau",
                "  ps aux              - Liste des processus",
                "  uname -a            - Informations système"
            ]);
        }
        
        return $help;
    }
    }