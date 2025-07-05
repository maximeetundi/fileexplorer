// file_explorer_terminal.js

class TerminalManager {
    constructor(fileExplorer) {
        this.fileExplorer = fileExplorer;
        this.commandHistory = [];
        this.historyIndex = -1;
        this.currentInput = '';
        
        // Références aux éléments du DOM
        this.terminalOutput = document.getElementById('terminalContent');
        this.terminalInput = document.getElementById('terminalInput');
        this.commandLine = document.getElementById('commandLine');
        
        // Initialisation
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            setTimeout(() => this.init(), 0);
        }
    }
    
    // Fait défiler la fenêtre vers le bas
    scrollToBottom() {
        this.terminalOutput.scrollTop = this.terminalOutput.scrollHeight;
    }
    
    // Met à jour l'affichage du chemin actuel dans le prompt
    updatePrompt() {
        const pathElement = document.getElementById('current-path');
        if (pathElement && this.fileExplorer) {
            let displayPath = this.fileExplorer.currentPath || '/';
            
            // Nettoyer le chemin pour l'affichage
            displayPath = displayPath.replace(/^\/+|\/+$/g, '');
            
            // Mettre à jour le chemin affiché
            pathElement.textContent = displayPath ? `/${displayPath}` : '/';
        }
    }
    
    // Ajoute du contenu à la sortie du terminal (méthode de compatibilité)
    addOutput(html, isCommand = false) {
        const outputElement = document.createElement('div');
        outputElement.className = isCommand ? 'command' : 'output';
        
        if (typeof html === 'string') {
            outputElement.textContent = html;
        } else {
            outputElement.appendChild(html);
        }
        
        if (this.terminalOutput) {
            this.terminalOutput.appendChild(outputElement);
            this.scrollToBottom();
        }
        
        return outputElement;
    }
    
    // Affiche une commande avant son exécution
    addCommand(command) {
        const commandElement = document.createElement('div');
        commandElement.className = 'command';
        
        // Créer le prompt
        const prompt = document.createElement('span');
        prompt.className = 'command-prompt';
        prompt.textContent = 'user@terminal:';
        
        // Ajouter le chemin actuel
        const path = document.createElement('span');
        path.className = 'command-path';
        const currentPath = this.fileExplorer.currentPath || '/';
        path.textContent = currentPath === '/' ? '/' : `/${currentPath}`;
        
        // Ajouter le prompt de commande
        commandElement.appendChild(prompt);
        commandElement.appendChild(path);
        commandElement.appendChild(document.createTextNode('$ ' + command));
        
        this.terminalOutput.appendChild(commandElement);
        this.scrollToBottom();
    }
    
    // Navigue dans l'historique des commandes
    navigateHistory(direction) {
        if (direction < 0 && this.historyIndex > 0) {
            // Flèche haut
            this.historyIndex--;
            this.terminalInput.value = this.commandHistory[this.historyIndex] || '';
        } else if (direction > 0 && this.historyIndex < this.commandHistory.length - 1) {
            // Flèche bas
            this.historyIndex++;
            this.terminalInput.value = this.commandHistory[this.historyIndex] || '';
        } else if (direction > 0) {
            // Flèche bas sur la dernière commande
            this.historyIndex = this.commandHistory.length;
            this.terminalInput.value = '';
        }
        this.terminalInput.selectionStart = this.terminalInput.selectionEnd = this.terminalInput.value.length;
    }

    // Initialise le terminal
    init() {
        try {
            // Références aux éléments du DOM
            this.terminalOutput = document.getElementById('terminalContent');
            this.terminalInput = document.getElementById('terminalInput');
            this.commandLine = document.getElementById('commandLine');
            
            if (!this.terminalOutput || !this.terminalInput) {
                console.error('TerminalManager: Éléments du terminal manquants dans le DOM.');
                return;
            }
            
            // Initialiser le chemin courant
            if (!this.fileExplorer.currentPath) {
                // Utiliser le chemin de la page actuelle comme point de départ
                const currentPath = window.location.pathname;
                this.fileExplorer.currentPath = currentPath;
                this.updatePrompt();
            }
            
            // Mettre à jour le prompt initial
            this.updatePrompt();
            
            // Gestionnaire d'événements pour la touche Entrée
            this.terminalInput.addEventListener('keydown', async (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const command = this.terminalInput.value.trim();
                    
                    if (command) {
                        // Afficher la commande
                        this.addCommand(command);
                        
                        // Ajouter à l'historique
                        this.commandHistory.push(command);
                        this.historyIndex = this.commandHistory.length;
                        
                        // Exécuter la commande
                        await this.executeCommand(command);
                        
                        // Vider le champ de saisie
                        this.terminalInput.value = '';
                        
                        // Remettre le focus sur le champ de saisie
                        this.terminalInput.focus();
                    }
                } else if (e.key === 'Tab') {
                    e.preventDefault();
                    await this.handleTabCompletion();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.navigateHistory(-1);
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.navigateHistory(1);
                }
            });
            
            // Mettre le focus sur le champ de saisie
            this.terminalInput.focus();
            
        } catch (error) {
            console.error('Erreur lors de l\'initialisation du terminal:', error);
            // Gestion du clic pour le focus
            if (this.terminalOutput) {
                this.terminalOutput.addEventListener('click', () => {
                    if (this.terminalInput) {
                        this.terminalInput.focus();
                    }
                });
            }
            return false;
        }
    }

    show() {
        // Le terminal est toujours visible, donc on se contente de mettre le focus
        if (this.terminalInput) {
            this.terminalInput.focus();
        }
    }

    hide() {
        // Le terminal est toujours visible, donc cette méthode ne fait rien
        // Elle est conservée pour la compatibilité avec le code existant
    }

    // Ajoute du contenu à la sortie du terminal
    addOutput(html, isCommand = false) {
        if (this.terminalOutput) {
            const div = document.createElement('div');
            div.className = isCommand ? 'command-output' : 'command-result';
            div.innerHTML = html;
            
            // Insérer avant la ligne de commande
            const terminalContent = document.getElementById('terminalContent');
            const commandLine = document.querySelector('.command-line');
            terminalContent.insertBefore(div, commandLine);
            
            this.scrollToBottom();
        }
    }
    
    // Gère la complétion par tabulation
    async handleTabCompletion() {
        const input = this.terminalInput;
        const cursorPos = input.selectionStart;
        const textBeforeCursor = input.value.substring(0, cursorPos);
        
        // Récupérer le mot actuel (tout ce qui est après le dernier espace ou le début de la ligne)
        const words = textBeforeCursor.split(/\s+/);
        const currentWord = words[words.length - 1] || '';
        
        if (currentWord) {
            try {
                const completion = await this.getPathCompletion(currentWord);
                if (completion) {
                    // Calculer la position de début du mot actuel
                    const lastSpaceIndex = textBeforeCursor.lastIndexOf(' ');
                    const startPos = lastSpaceIndex === -1 ? 0 : lastSpaceIndex + 1;
                    
                    // Remplacer uniquement la partie du mot après le dernier /
                    const lastSlashIndex = currentWord.lastIndexOf('/');
                    const prefix = lastSlashIndex === -1 ? '' : currentWord.substring(0, lastSlashIndex + 1);
                    
                    // Construire le nouveau texte
                    const newText = textBeforeCursor.substring(0, startPos) + 
                                  prefix + completion + 
                                  input.value.substring(cursorPos);
                    
                    // Mettre à jour l'input
                    input.value = newText;
                    input.selectionStart = input.selectionEnd = startPos + prefix.length + completion.length;
                }
            } catch (error) {
                console.error('Erreur lors de la complétion:', error);
            }
        }
    }

    // Obtient la complétion de chemin pour la tabulation
    async getPathCompletion(partialPath) {
        try {
            // Gestion des chemins absolus
            const isAbsolute = partialPath.startsWith('/');
            
            // Séparer le chemin en segments
            const segments = partialPath.split('/');
            const searchTerm = segments.pop() || ''; // Le dernier segment est le terme de recherche
            
            // Déterminer le chemin de base pour la recherche
            let searchPath;
            if (isAbsolute) {
                // Pour les chemins absolus, on prend tout sauf le dernier segment
                searchPath = segments.join('/') || '/';
            } else {
                // Pour les chemins relatifs, on combine avec le chemin courant
                const currentPath = this.fileExplorer.currentPath;
                const relativePath = segments.join('/');
                searchPath = this.normalizePath(relativePath, currentPath);
            }
            
            // Récupérer la liste des fichiers/dossiers
            const response = await this.fileExplorer.fetchAPI('list_directory', {
                path: searchPath
            });
            
            if (response.success && response.files) {
                // Filtrer les correspondances
                const matches = response.files.filter(file => 
                    file.name.toLowerCase().startsWith(searchTerm.toLowerCase())
                );
                
                if (matches.length === 1) {
                    // Une seule correspondance, on la retourne
                    const match = matches[0];
                    let result = match.name;
                    
                    // Ajouter un / pour les dossiers
                    if (match.is_dir) {
                        result += '/';
                    }
                    
                    return result;
                } else if (matches.length > 1) {
                    // Afficher les correspondances possibles
                    let output = '<div class="tab-completion">';
                    matches.forEach(match => {
                        const className = match.is_dir ? 'folder' : '';
                        output += `<span class="${className}">${match.name}${match.is_dir ? '/' : ''}</span>`;
                    });
                    output += '</div>';
                    
                    // Créer un élément temporaire pour afficher les suggestions
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = output;
                    this.terminalOutput.appendChild(tempDiv);
                    
                    // Faire défiler jusqu'aux suggestions
                    tempDiv.scrollIntoView({ behavior: 'smooth' });
                    
                    // Retourner la partie commune
                    const commonPrefix = this.findCommonPrefix(matches.map(m => m.name));
                    if (commonPrefix && commonPrefix.length > searchTerm.length) {
                        return commonPrefix;
                    }
                }
            }
        } catch (error) {
            console.error('Erreur lors de la complétion:', error);
        }
        return null;
    }
    
    // Trouve le préfixe commun entre plusieurs chaînes
    findCommonPrefix(strings) {
        if (!strings || strings.length === 0) return '';
        
        const first = strings[0];
        let prefix = '';
        
        for (let i = 0; i < first.length; i++) {
            const char = first[i];
            if (strings.every(s => s[i] === char)) {
                prefix += char;
            } else {
                break;
            }
        }
        
        return prefix;
    }
    
    // Normalise un chemin (supprime les doublons de /, gère les .., etc.)
    normalizePath(path, basePath = null) {
        if (!path || path === '.') {
            return basePath || this.fileExplorer.currentPath || this.fileExplorer.basePath || '/';
        }
        
        // Si c'est un chemin absolu, on le nettoie
        if (path.startsWith('/')) {
            // Nettoyer les segments du chemin
            const segments = [];
            path.split('/').forEach(segment => {
                if (segment === '..') {
                    if (segments.length > 0) segments.pop();
                } else if (segment && segment !== '.') {
                    segments.push(segment);
                }
            });
            
            // Reconstruire le chemin
            let normalized = '/' + segments.join('/');
            
            // Si le chemin normalisé est vide, retourner la racine
            if (normalized === '') return '/';
            
            // S'assurer que le chemin ne se termine pas par / sauf s'il s'agit de la racine
            if (normalized !== '/' && normalized.endsWith('/')) {
                normalized = normalized.slice(0, -1);
            }
            
            return normalized;
        }
        
        // Pour les chemins relatifs, on utilise le chemin de base fourni ou le chemin courant
        const base = basePath || this.fileExplorer.currentPath || this.fileExplorer.basePath || '/';
        let segments = base.split('/').filter(Boolean);
        
        // Gestion des segments du chemin relatif
        path.split('/').forEach(segment => {
            if (segment === '..') {
                if (segments.length > 0) segments.pop();
            } else if (segment && segment !== '.') {
                segments.push(segment);
            }
        });
        
        // Reconstruire le chemin
        let normalized = '/' + segments.join('/');
        
        // Si le chemin normalisé est vide, retourner la racine
        if (normalized === '') return '/';
        
        // S'assurer que le chemin ne se termine pas par / sauf s'il s'agit de la racine
        if (normalized !== '/' && normalized.endsWith('/')) {
            normalized = normalized.slice(0, -1);
        }
        
        return normalized;
    }

    // Exécute une commande
    async executeCommand(command) {
        if (!command) return;
        
        // Traiter les commandes spéciales
        const args = command.trim().split(/\s+/);
        const cmd = args[0].toLowerCase();
        
        try {
            switch (cmd) {
                case 'cd':
                    await this.handleCdCommand(args[1] || '');
                    break;
                    
                case 'ls':
                    await this.handleLsCommand(args.slice(1));
                    break;
                    
                case 'clear':
                case 'cls':
                    if (this.terminalOutput) {
                        this.terminalOutput.innerHTML = '';
                    }
                    break;
                    
                case 'pwd':
                    this.addOutput(this.fileExplorer.currentPath || '/');
                    break;
                    
                case 'help':
                    this.showHelp();
                    break;
                    
                default:
                    // Si ce n'est pas une commande interne, l'envoyer au serveur
                    await this.sendToServer(command);
            }
        } catch (error) {
            this.addOutput(`Erreur: ${error.message}`, 'error');
            console.error('Erreur lors de l\'exécution de la commande:', error);
        }
        
        // Mettre à jour le prompt avec le nouveau chemin
        this.updatePrompt();
        
        // Faire défiler vers le bas
        this.scrollToBottom();
    }
    
    // Gère la commande cd
    async handleCdCommand(targetDir) {
        targetDir = targetDir || '/';
        
        if (targetDir === '' || targetDir === '.') {
            // Ne rien faire, on reste dans le répertoire courant
            return;
        }
        
        let newPath;
        if (targetDir.startsWith('/')) {
            // Chemin absolu
            newPath = targetDir;
        } else {
            // Chemin relatif
            let currentSegments = this.fileExplorer.currentPath.split('/').filter(Boolean);
            
            targetDir.split('/').forEach(segment => {
                if (segment === '..') {
                    currentSegments.pop();
                } else if (segment && segment !== '.') {
                    currentSegments.push(segment);
                }
            });
            
            newPath = '/' + currentSegments.join('/');
        }
        
        // Vérifier si le chemin existe
        try {
            const response = await this.fileExplorer.fetchAPI('list_directory', {
                path: newPath
            });
            
            if (response.success) {
                this.fileExplorer.currentPath = newPath;
                this.updatePrompt();
            } else {
                this.addOutput(`cd: ${response.message || 'Aucun fichier ou dossier de ce type'}`, 'error');
            }
        } catch (error) {
            this.addOutput(`Erreur: ${error.message}`, 'error');
        }
    }
    
    // Gère la commande ls
    async handleLsCommand(args = []) {
        try {
            // Déterminer le chemin à lister
            let targetPath = args[0] || '';
            let fullPath;
            
            if (targetPath.startsWith('/')) {
                // Chemin absolu - s'assurer qu'il est sous le dossier racine
                fullPath = this.normalizePath(targetPath);
                if (!fullPath.startsWith(this.fileExplorer.basePath) && fullPath !== '/') {
                    this.addOutput(`ls: accès refusé: ${targetPath}`, 'error');
                    return;
                }
            } else {
                // Chemin relatif - le combiner avec le chemin courant
                fullPath = this.normalizePath(
                    targetPath, 
                    this.fileExplorer.currentPath || this.fileExplorer.basePath
                );
                
                // S'assurer que le chemin résultant est sous le dossier racine
                if (!fullPath.startsWith(this.fileExplorer.basePath) && fullPath !== '/') {
                    this.addOutput(`ls: accès refusé: ${targetPath}`, 'error');
                    return;
                }
            }
            
            // Utiliser le chemin racine si le chemin est vide
            if (!fullPath || fullPath === '/') {
                fullPath = this.fileExplorer.basePath;
            }
            
            // Appeler l'API pour lister le contenu du dossier
            const response = await this.fileExplorer.fetchAPI('list_directory', { 
                path: fullPath 
            });
            
            if (response.success) {
                if (response.files && response.files.length > 0) {
                    let output = '';
                    response.files.forEach(file => {
                        const icon = file.is_dir ? '📁' : '📄';
                        const nameClass = file.is_dir ? 'directory' : 'file';
                        output += `${icon} <span class="${nameClass}">${file.name}</span>\n`;
                    });
                    // Utiliser une balise pre pour conserver le formatage
                    const pre = document.createElement('pre');
                    pre.className = 'output';
                    pre.innerHTML = output;
                    this.terminalOutput.appendChild(pre);
                } else {
                    this.addOutput('(répertoire vide)');
                }
            } else {
                this.addOutput(`ls: ${response.message || 'Erreur inconnue'}`, 'error');
            }
        } catch (error) {
            this.addOutput(`Erreur: ${error.message}`, 'error');
        }
    }
    
    // Affiche l'aide
    showHelp() {
        const helpText = `
Commandes disponibles:
  cd [dossier]    Changer de répertoire
  ls [dossier]    Lister les fichiers
  pwd             Afficher le répertoire courant
  clear / cls     Effacer l'écran
  help            Afficher cette aide

Appuyez sur Tab pour la complétion automatique des noms de fichiers`;
        
        this.addOutput(helpText);
    }
    
    // Échappe le HTML pour éviter les injections
    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    
    // Envoie une commande au serveur
    async sendToServer(command) {
        try {
            const response = await this.fileExplorer.fetchAPI('execute_command', {
                command: command,
                path: this.fileExplorer.currentPath
            });
            
            if (response.success) {
                if (response.output) {
                    // Échapper le HTML pour la sécurité
                    const safeOutput = this.escapeHtml(response.output);
                    this.addOutput(safeOutput);
                }
            } else {
                this.addOutput(`Erreur: ${this.escapeHtml(response.message || 'Commande inconnue ou erreur d\'exécution')}`, 'error');
            }
        } catch (error) {
            this.addOutput(`Erreur de connexion: ${this.escapeHtml(error.message)}`, 'error');
        }
    }
}
