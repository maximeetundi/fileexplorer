class TransferManager {
    constructor(fileExplorer) {
        this.fe = fileExplorer; // Référence à l'instance principale de FileExplorer
    }

    uploadFiles(files) {
        if (!files || files.length === 0) return;

        const errors = [];
        for (const file of files) {
            // Validation de la taille
            if (this.fe.config.upload_max_size && file.size > this.fe.config.upload_max_size) {
                const maxSizeMB = this.fe.config.upload_max_size / 1024 / 1024;
                errors.push(`Le fichier "${file.name}" dépasse la taille maximale de ${maxSizeMB.toFixed(1)} Mo.`);
            }

            // Validation de l'extension
            if (this.fe.config.allowed_extensions && !this.fe.config.allowed_extensions.includes(file.name.split('.').pop().toLowerCase())) {
                errors.push(`Le type de fichier de "${file.name}" n'est pas autorisé.`);
            }
        }

        if (errors.length > 0) {
            this.fe.showError(`Échec de la validation :<br>${errors.join('<br>')}`);
            return;
        }

        const formData = new FormData();
        formData.append('action', 'upload_files');
        formData.append('path', this.fe.currentPath);

        for (const file of files) {
            formData.append('files[]', file, file.name);
        }

        const xhr = new XMLHttpRequest();
        const progressContainer = document.getElementById('uploadProgressContainer');
        const progressBar = document.getElementById('uploadProgressBar');
        const progressText = document.getElementById('uploadProgressText');

        progressContainer.classList.remove('hidden');
        progressBar.style.width = '0%';
        progressText.textContent = '0%';

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percentComplete + '%';
                progressText.textContent = percentComplete + '%';
            }
        });

        xhr.addEventListener('load', () => {
            setTimeout(() => progressContainer.classList.add('hidden'), 2000);
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const result = JSON.parse(xhr.responseText);
                    if (result.success) {
                        this.fe.showSuccess(result.message || 'Téléversement réussi.');
                        this.fe.loadFiles();
                    } else {
                        this.fe.showError(result.message || 'Échec du téléversement.');
                    }
                } catch (error) {
                    this.fe.showError('Réponse invalide du serveur.');
                }
            } else {
                this.fe.showError(`Erreur serveur: ${xhr.status}`);
            }
        });

        xhr.addEventListener('error', () => {
            setTimeout(() => progressContainer.classList.add('hidden'), 2000);
            this.fe.showError('Erreur réseau lors du téléversement.');
        });
        
        xhr.addEventListener('abort', () => {
            setTimeout(() => progressContainer.classList.add('hidden'), 2000);
            this.fe.showError('Le téléversement a été annulé.');
        });

        xhr.open('POST', 'file_explorer_server.php', true);
        xhr.send(formData);
    }

    downloadFile(file) {
        window.open(`file_explorer_server.php?action=download_file&path=${encodeURIComponent(file.path)}`);
    }

    downloadFromUrl() {
        const url = prompt('Entrez l\'URL du fichier à télécharger:');
        if (url) this.fetchFromUrl(url);
    }

    async fetchFromUrl(url) {
        const data = await this.fe.fetchAPI('download_from_url', { url: url, path: this.fe.currentPath });
        if (data.success) {
            this.fe.showSuccess('Fichier téléchargé avec succès.');
            this.fe.loadFiles();
        } else {
            this.fe.showError(`Échec du téléchargement : ${data.message}`);
        }
    }
}
