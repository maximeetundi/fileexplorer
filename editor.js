document.addEventListener('DOMContentLoaded', () => {
    const filePathElement = document.getElementById('filePath');
    const saveBtn = document.getElementById('saveBtn');
    const editorContainer = document.getElementById('editor-container');

    let editor;
    let currentFile = '';

    // --- Initialisation de Monaco Editor ---
    require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.34.0/min/vs' }});
    require(['vs/editor/editor.main'], () => {
        editor = monaco.editor.create(editorContainer, {
            theme: 'vs-dark',
            automaticLayout: true, // S'adapte à la taille du conteneur
            fontSize: 14,
            minimap: {
                enabled: true
            }
        });

        // Charger le fichier au démarrage
        loadFileContent();

        // Ajouter le raccourci de sauvegarde (Ctrl+S)
        editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, () => {
            saveFileContent();
        });
    });

    // --- Chargement du contenu du fichier ---
    async function loadFileContent() {
        const urlParams = new URLSearchParams(window.location.search);
        currentFile = urlParams.get('file');

        if (!currentFile) {
            filePathElement.textContent = 'Aucun fichier sélectionné';
            editor.setValue('Veuillez sélectionner un fichier à éditer depuis l\`explorateur.');
            return;
        }

        filePathElement.textContent = currentFile;

        try {
            const response = await fetch('file_explorer_server.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'read_file', path: currentFile })
            });

            const data = await response.json();

            if (data.success) {
                const extension = currentFile.split('.').pop();
                const language = getLanguageFromExtension(extension);
                
                // Définir le contenu et le langage de l'éditeur
                editor.setValue(data.content);
                monaco.editor.setModelLanguage(editor.getModel(), language);
                
            } else {
                editor.setValue(`Erreur: ${data.message}`);
            }
        } catch (error) {
            console.error('Erreur de chargement:', error);
            editor.setValue(`Erreur de communication avec le serveur: ${error.message}`);
        }
    }

    // --- Sauvegarde du contenu du fichier ---
    async function saveFileContent() {
        if (!currentFile || !editor) return;

        const content = editor.getValue();
        saveBtn.disabled = true;
        saveBtn.textContent = 'Sauvegarde...';

        try {
            const response = await fetch('file_explorer_server.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'write_file', path: currentFile, content: content })
            });

            const data = await response.json();

            if (data.success) {
                // Peut-être afficher une notification de succès
                console.log('Fichier sauvegardé avec succès');
            } else {
                alert(`Erreur de sauvegarde: ${data.message}`);
            }
        } catch (error) {
            console.error('Erreur de sauvegarde:', error);
            alert(`Erreur de communication avec le serveur: ${error.message}`);
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg><span>Enregistrer (Ctrl+S)</span>';
        }
    }

    saveBtn.addEventListener('click', saveFileContent);

    // --- Utilitaire pour mapper l'extension au langage Monaco ---
    function getLanguageFromExtension(ext) {
        const mapping = {
            'js': 'javascript',
            'ts': 'typescript',
            'html': 'html',
            'css': 'css',
            'json': 'json',
            'md': 'markdown',
            'php': 'php',
            'py': 'python',
            'rs': 'rust',
            'java': 'java',
            'c': 'c',
            'cpp': 'cpp',
            'sql': 'sql',
            'xml': 'xml',
            'yaml': 'yaml',
            'yml': 'yaml',
            'sh': 'shell',
            'bat': 'bat',
            'ini': 'ini',
            'log': 'log'
        };
        return mapping[ext] || 'plaintext';
    }
});
