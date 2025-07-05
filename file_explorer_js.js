// Explorateur de fichiers - JavaScript
const doc = document;

document.addEventListener('DOMContentLoaded', () => {
    const app = new FileExplorer();
    app.init();
});

class FileExplorer {
    constructor(options) {
        this.currentPath = '/';
        this.history = ['/'];
        this.historyIndex = 0;
        this.config = {}; // Sera chargé depuis le serveur
        this.options = Object.assign({}, options);
        this.selectedFile = null;
        this.viewMode = 'grid';
        this.sortBy = 'name';
        this.searchQuery = '';
        this.allFiles = []; // Initialisation pour éviter les erreurs
        this.transferManager = new TransferManager(this);
    }

    async init() {
        await this.loadConfig();
        await this.loadFiles(); // Attendre que les fichiers soient chargés
        this.initEventListeners(); // Initialiser les écouteurs APRÈS le chargement des données
        this.initSidebar();
    }

    initEventListeners() {
        // Navigation
        document.getElementById('backBtn').addEventListener('click', () => this.navigateBack());
        document.getElementById('forwardBtn').addEventListener('click', () => this.navigateForward());
        document.getElementById('refreshBtn').addEventListener('click', () => this.loadFiles());
        
        // View modes
        document.getElementById('viewGrid').addEventListener('click', () => this.setViewMode('grid'));
        document.getElementById('viewList').addEventListener('click', () => this.setViewMode('list'));
        
        // Sorting
        document.getElementById('sortBy').addEventListener('change', (e) => {
            this.sortBy = e.target.value;
            this.loadFiles();
        });
        
        // Search
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.searchQuery = e.target.value;
                this.filterFiles();
            });
        }
        
        // Upload
        document.getElementById('uploadBtn').addEventListener('click', () => document.getElementById('fileInput').click());
        document.getElementById('fileInput').addEventListener('change', (e) => this.transferManager.uploadFiles(e.target.files));

        // Gérer le clic droit sur le conteneur de fichiers (pour coller à la racine)
        document.getElementById('fileListContainer').addEventListener('contextmenu', (e) => {
            // Si le clic est sur le conteneur ou la liste elle-même (et pas sur un item)
            if (e.target.id === 'fileListContainer' || e.target.id === 'fileList') {
                this.showContextMenu(e, null);
            }
        });

        // Terminal


        
        // Editor
        document.getElementById('closeLightEditor').addEventListener('click', () => this.hideLightEditor());
        document.getElementById('saveLightEditor').addEventListener('click', () => this.saveLightEditorContent());
        
        // Preview
        document.getElementById('closePreview').addEventListener('click', () => this.hidePreview());
        
        // Context menu
        document.addEventListener('click', () => this.hideContextMenu());
        document.addEventListener('contextmenu', (e) => e.preventDefault());

        // Shortcuts
        document.getElementById('shortcuts').addEventListener('click', (e) => this.handleShortcutClick(e));

        // Root context menu button (mobile)
        document.getElementById('rootContextMenuBtn').addEventListener('click', (e) => {
            e.stopPropagation();
            this.showContextMenu(e, null);
        });
    }

    async fetchAPI(action, params = {}) {
        try {
            const response = await fetch('file_explorer_server.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, ...params })
            });
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error(`Erreur API pour l'action "${action}":`, error);
            this.showError(`Erreur de communication avec le serveur.`);
            return { success: false, message: error.message };
        }
    }

    async loadConfig() {
        const response = await this.fetchAPI('get_config');
        if (response.success) {
            this.config = response.config;
        } else {
            this.showError('Impossible de charger la configuration du serveur. Utilisation des valeurs par défaut.');
            // Fallback sur des valeurs par défaut si le chargement échoue
            this.config = {
                upload_max_size: 50 * 1024 * 1024,
                allowed_extensions: []
            };
        }
    }

    async loadFiles() {
        try {
            const response = await fetch('file_explorer_server.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'list_files',
                    path: this.currentPath,
                    sort: this.sortBy
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.allFiles = data.files; // Sauvegarder la liste !
                this.renderFiles(data.files);
                this.updateBreadcrumb();
                this.updateNavigationButtons();
            } else {
                this.showError(data.message);
            }
        } catch (error) {
            this.showError('Erreur de connexion au serveur');
        }
    }

    renderFiles(files) {
        const fileList = document.getElementById('fileList');
        fileList.innerHTML = '';
        
        files.forEach(file => {
            const fileElement = this.createFileElement(file);
            fileList.appendChild(fileElement);
        });
    }

    createFileElement(file) {
        const div = document.createElement('div');
        div.className = `file-item bg-gray-700 rounded-lg p-4 cursor-pointer hover:bg-gray-600 transition-colors`;
        div.dataset.filename = file.name;
        div.dataset.filepath = file.path;
        div.dataset.type = file.type;
        
        const icon = this.getFileIcon(file);
        const size = this.formatFileSize(file.size);
        const date = new Date(file.modified * 1000).toLocaleDateString();
        
        div.innerHTML = `
            <div class="flex items-center space-x-3 w-full">
                <div class="text-2xl ${this.getFileIconColor(file)}">
                    <i class="${icon}"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-medium truncate">${file.name}</div>
                    <div class="text-sm text-gray-400">${file.type === 'directory' ? 'Dossier' : size} • ${date}</div>
                </div>
                <div class="flex-shrink-0">
                    <button class="p-2 rounded-full hover:bg-gray-500 text-gray-400 hover:text-white options-button">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
            </div>
        `;
        
        // Event listeners
        div.addEventListener('dblclick', () => this.openFile(file));
        div.addEventListener('contextmenu', (e) => this.showContextMenu(e, file));

        // Clic sur l'icône 'trois points'
        const optionsButton = div.querySelector('.options-button');
        optionsButton.addEventListener('click', (e) => {
            e.stopPropagation(); // Empêche le dblclick sur le parent
            this.showContextMenu(e, file);
        });

        // Clic sur l'élément lui-même (pour la sélection)
        div.addEventListener('click', (e) => {
            // Ne pas sélectionner si on clique sur le bouton d'options
            if (!optionsButton.contains(e.target)) {
                this.selectFile(file);
            }
        });
        
        return div;
    }

    getFileIcon(file) {
        if (file.type === 'directory') return 'fas fa-folder';
        
        const ext = file.name.split('.').pop().toLowerCase();
        const iconMap = {
            // Images
            'jpg': 'fas fa-file-image', 'jpeg': 'fas fa-file-image', 'png': 'fas fa-file-image',
            'gif': 'fas fa-file-image', 'svg': 'fas fa-file-image', 'webp': 'fas fa-file-image',
            
            // Videos
            'mp4': 'fas fa-file-video', 'avi': 'fas fa-file-video', 'mov': 'fas fa-file-video',
            'mkv': 'fas fa-file-video', 'webm': 'fas fa-file-video',
            
            // Audio
            'mp3': 'fas fa-file-audio', 'wav': 'fas fa-file-audio', 'flac': 'fas fa-file-audio',
            'ogg': 'fas fa-file-audio',
            
            // Documents
            'pdf': 'fas fa-file-pdf', 'doc': 'fas fa-file-word', 'docx': 'fas fa-file-word',
            'xls': 'fas fa-file-excel', 'xlsx': 'fas fa-file-excel',
            'ppt': 'fas fa-file-powerpoint', 'pptx': 'fas fa-file-powerpoint',
            
            // Code
            'html': 'fas fa-file-code', 'css': 'fas fa-file-code', 'js': 'fas fa-file-code',
            'php': 'fas fa-file-code', 'py': 'fas fa-file-code', 'rs': 'fas fa-file-code',
            'cpp': 'fas fa-file-code', 'c': 'fas fa-file-code', 'java': 'fas fa-file-code',
            'json': 'fas fa-file-code', 'xml': 'fas fa-file-code',
            
            // Archives
            'zip': 'fas fa-file-archive', 'rar': 'fas fa-file-archive', '7z': 'fas fa-file-archive',
            'tar': 'fas fa-file-archive', 'gz': 'fas fa-file-archive',
            
            // Text
            'txt': 'fas fa-file-alt', 'md': 'fas fa-file-alt', 'log': 'fas fa-file-alt'
        };
        
        return iconMap[ext] || 'fas fa-file';
    }

    getFileIconColor(file) {
        if (file.type === 'directory') return 'text-blue-400';
        
        const ext = file.name.split('.').pop().toLowerCase();
        const colorMap = {
            'jpg': 'text-green-400', 'jpeg': 'text-green-400', 'png': 'text-green-400',
            'gif': 'text-green-400', 'svg': 'text-green-400', 'webp': 'text-green-400',
            'mp4': 'text-red-400', 'avi': 'text-red-400', 'mov': 'text-red-400',
            'mkv': 'text-red-400', 'webm': 'text-red-400',
            'mp3': 'text-purple-400', 'wav': 'text-purple-400', 'flac': 'text-purple-400',
            'ogg': 'text-purple-400',
            'pdf': 'text-red-500', 'doc': 'text-blue-500', 'docx': 'text-blue-500',
            'xls': 'text-green-500', 'xlsx': 'text-green-500',
            'ppt': 'text-orange-500', 'pptx': 'text-orange-500',
            'html': 'text-orange-400', 'css': 'text-blue-400', 'js': 'text-yellow-400',
            'php': 'text-purple-500', 'py': 'text-yellow-500', 'rs': 'text-orange-600',
            'cpp': 'text-blue-600', 'c': 'text-blue-600', 'java': 'text-red-600',
            'json': 'text-yellow-400', 'xml': 'text-orange-400',
            'zip': 'text-yellow-600', 'rar': 'text-yellow-600', '7z': 'text-yellow-600',
            'tar': 'text-yellow-600', 'gz': 'text-yellow-600'
        };
        
        return colorMap[ext] || 'text-gray-400';
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    selectFile(file) {
        // Remove previous selection
        document.querySelectorAll('.file-item').forEach(item => {
            item.classList.remove('ring-2', 'ring-blue-500');
        });

        // Add selection to current file
        const fileElement = document.querySelector(`[data-filename="${file.name}"]`);
        if (fileElement) {
            fileElement.classList.add('ring-2', 'ring-blue-500');
        }
        
        this.selectedFile = file;
        this.showPreview(file);
    }

    openFile(file) {
        if (file.type === 'dir' || file.type === 'directory') {
            this.navigateToPath(file.path);
        } else {
            // Ouvre directement l'éditeur dans un nouvel onglet
            window.open(`editor.html?file=${encodeURIComponent(file.path)}`, '_blank');
        }
    }

    async showLightEditor(file) {
        try {
            const data = await this.fetchAPI('get_file_content', { path: file.path });
            if (data.success) {
                document.getElementById('lightEditorTitle').textContent = file.name;
                document.getElementById('lightEditorTextarea').value = data.content;
                document.getElementById('lightEditorModal').classList.remove('hidden');
                this.selectedFile = file; // Garder en mémoire le fichier en cours d'édition
            } else {
                this.showError(data.message || 'Impossible de charger le fichier.');
            }
        } catch (error) {
            this.showError('Erreur de communication avec le serveur.');
        }
    }

    hideLightEditor() {
        document.getElementById('lightEditorModal').classList.add('hidden');
        this.selectedFile = null;
    }

    async saveLightEditorContent() {
        if (!this.selectedFile) return;
        const content = document.getElementById('lightEditorTextarea').value;
        const data = await this.fetchAPI('save_file', {
            path: this.selectedFile.path,
            content: content
        });

        if (data.success) {
            this.showSuccess('Fichier sauvegardé avec succès.');
            this.hideLightEditor();
            this.loadFiles();
        } else {
            this.showError(data.message || 'Impossible de sauvegarder le fichier.');
        }
    }

    showPreview(file) {
        const previewPanel = document.getElementById('previewPanel');
        const previewContent = document.getElementById('previewContent');
        previewPanel.classList.remove('hidden');
        previewContent.innerHTML = `
            <h5>${this.escapeHtml(file.name)}</h5>
            <p><strong>Type:</strong> ${this.escapeHtml(file.type)}</p>
            <p><strong>Taille:</strong> ${this.formatFileSize(file.size)}</p>
            <p><strong>Modifié le:</strong> ${new Date(file.modified * 1000).toLocaleString()}</p>
        `;
    }

    hidePreview() {
        document.getElementById('previewPanel').classList.add('hidden');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    navigateToPath(path) {
        this.currentPath = path;
        this.addToHistory(path);
        this.loadFiles();
    }

    addToHistory(path) {
        if (this.historyIndex < this.history.length - 1) {
            this.history = this.history.slice(0, this.historyIndex + 1);
        }
        this.history.push(path);
        this.historyIndex = this.history.length - 1;
        this.updateNavigationButtons();
    }

    navigateBack() {
        if (this.historyIndex > 0) {
            this.historyIndex--;
            this.currentPath = this.history[this.historyIndex];
            this.loadFiles();
            this.updateNavigationButtons();
        }
    }

    navigateForward() {
        if (this.historyIndex < this.history.length - 1) {
            this.historyIndex++;
            this.currentPath = this.history[this.historyIndex];
            this.loadFiles();
            this.updateNavigationButtons();
        }
    }

    updateNavigationButtons() {
        document.getElementById('backBtn').disabled = this.historyIndex <= 0;
        document.getElementById('forwardBtn').disabled = this.historyIndex >= this.history.length - 1;
    }

    updateBreadcrumb() {
        const breadcrumb = document.getElementById('breadcrumb');
        breadcrumb.innerHTML = '';
        const parts = this.currentPath.split('/').filter(p => p);
        let path = '';
        const root = document.createElement('button');
        root.textContent = 'Racine';
        root.onclick = () => this.navigateToPath('/');
        breadcrumb.appendChild(root);

        for (const part of parts) {
            path += '/' + part;
            breadcrumb.append(' / ');
            const btn = document.createElement('button');
            btn.textContent = part;
            const currentPath = path;
            btn.onclick = () => this.navigateToPath(currentPath);
            breadcrumb.appendChild(btn);
        }
    }

    setViewMode(mode) {
        this.viewMode = mode;
        const fileList = document.getElementById('fileList');
        fileList.className = mode === 'list' ? 'list-view' : 'grid-view';
        this.renderFiles(this.allFiles);
    }

    filterFiles() {
        const query = this.searchQuery.toLowerCase().trim();

        // Si la recherche est vide, afficher tous les fichiers
        if (!query) {
            this.renderFiles(this.allFiles || []);
            return;
        }

        // S'assurer que this.allFiles existe avant de filtrer
        const filesToFilter = this.allFiles || [];

        const filteredFiles = filesToFilter.filter(file =>
            file.name.toLowerCase().includes(query)
        );

        this.renderFiles(filteredFiles);
    }



    showContextMenu(event, file = null) {
        event.preventDefault();
        this.hideContextMenu();
        const menu = document.getElementById('contextMenu');
        menu.innerHTML = ''; // Clear previous items
        this.selectedFile = file;

        const actions = [];
        if (file) {
            if (file.type === 'directory') {
                actions.push({ label: 'Ouvrir', icon: 'fa-folder-open', action: () => this.navigateToPath(file.path) });
            } else {
                actions.push({ label: 'Aperçu', icon: 'fa-eye', action: () => this.showPreview(file) });
                actions.push({ label: 'Éditer', icon: 'fa-edit', action: () => this.showLightEditor(file) });
            }
            actions.push({ label: 'Renommer', icon: 'fa-i-cursor', action: () => this.renameItem(file) });
            actions.push({ label: 'Copier', icon: 'fa-copy', action: () => this.copyItem(file) });
            actions.push({ label: 'Couper', icon: 'fa-cut', action: () => this.cutItem(file) });
            actions.push({ label: 'Télécharger', icon: 'fa-download', action: () => this.transferManager.downloadFile(file), separator: true });
            actions.push({ label: 'Supprimer', icon: 'fa-trash', action: () => this.deleteFile(file), destructive: true });
        } else {
            actions.push({ label: 'Nouveau dossier', icon: 'fa-folder-plus', action: () => this.createNewFolder() });
            actions.push({ label: 'Nouveau fichier', icon: 'fa-file-plus', action: () => this.createNewFile() });
            actions.push({ label: 'Téléverser depuis URL', icon: 'fa-link', action: () => this.transferManager.downloadFromUrl() });
        }

        if (this.clipboard) {
            actions.push({ label: 'Coller', icon: 'fa-paste', action: () => this.pasteItem(), separator: true });
        }

        // Apply new TailwindCSS classes for a modern look
        menu.className = 'absolute bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg py-1 z-50';

        actions.forEach(act => {
            if (act.separator) {
                const hr = document.createElement('hr');
                hr.className = 'my-1 border-gray-200 dark:border-gray-700';
                menu.appendChild(hr);
            }
            const btn = document.createElement('button');
            btn.className = 'flex items-center w-full px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700';
            if (act.destructive) {
                btn.classList.add('text-red-600', 'dark:text-red-500');
            }
            btn.innerHTML = `<i class="fas ${act.icon} w-6"></i> <span class="ml-2">${act.label}</span>`;
            btn.onclick = (e) => {
                e.stopPropagation();
                act.action();
                this.hideContextMenu();
            };
            menu.appendChild(btn);
        });

        menu.style.left = `${event.pageX}px`;
        menu.style.top = `${event.pageY}px`;
        menu.classList.remove('hidden');
    }

    hideContextMenu() {
        document.getElementById('contextMenu').classList.add('hidden');
    }

    copyItem(item) {
        this.clipboard = { item: item, mode: 'copy' };
        this.showSuccess(`"${item.name}" copié.`);
    }

    cutItem(item) {
        this.clipboard = { item: item, mode: 'cut' };
        this.showSuccess(`"${item.name}" coupé.`);
    }

    async pasteItem() {
        if (!this.clipboard) return;
        const data = await this.fetchAPI(this.clipboard.mode === 'cut' ? 'move_item' : 'copy_item', {
            source: this.clipboard.item.path,
            destination: this.currentPath
        });

        if (data.success) {
            if (this.clipboard.mode === 'cut') this.clipboard = null;
            this.loadFiles();
        } else {
            this.showError(data.message || 'Impossible de coller l\'élément.');
        }
    }

    async renameItem(item) {
        const newName = prompt(`Entrez le nouveau nom pour "${item.name}":`, item.name);
        if (!newName || newName === item.name) return;

        const data = await this.fetchAPI('rename_item', { path: item.path, newName: newName });
        if (data.success) {
            this.loadFiles();
        } else {
            this.showError(data.message || 'Impossible de renommer.');
        }
    }

    async deleteFile(file) {
        if (!confirm(`Êtes-vous sûr de vouloir supprimer "${file.name}"?`)) return;
        const data = await this.fetchAPI('delete_file', { path: file.path });
        if (data.success) {
            this.loadFiles();
        } else {
            this.showError(data.message || 'Impossible de supprimer.');
        }
    }

    initSidebar() { /* Placeholder */ }





    async handleShortcutClick(e) {
        e.preventDefault();
        const target = e.target.closest('[data-shortcut]');
        if (!target) return;
        const data = await this.fetchAPI('ensure_directory', { path: target.dataset.shortcut });
        if (data.success) this.navigateToPath(data.path);
        else this.showError(`Impossible d'accéder au raccourci : ${data.message}`);
    }

    createNewFolder() {
        const name = prompt('Nom du nouveau dossier:');
        if (name) this.createItem('create_folder', name);
    }

    createNewFile() {
        const name = prompt('Nom du nouveau fichier:');
        if (name) this.createItem('create_file', name);
    }

    async createItem(action, name) {
        const data = await this.fetchAPI(action, { path: this.currentPath, name: name });
        if (data.success) {
            this.loadFiles();
        } else {
            this.showError(data.message || 'Impossible de créer l\'élément.');
        }
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showNotification(message, type) {
        const container = document.getElementById('notificationContainer');
        const notif = document.createElement('div');
        notif.className = `notification ${type}`;
        notif.textContent = message;
        container.appendChild(notif);
        setTimeout(() => notif.remove(), 3000);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
}

}

// Initialize the file explorer once the DOM is fully loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('File Explorer Script v1.0.6 loaded successfully.');
    const fileExplorer = new FileExplorer();
    fileExplorer.init();

    // Initialize the new Terminal Manager
    const terminalManager = new TerminalManager(fileExplorer);
    terminalManager.init();
});