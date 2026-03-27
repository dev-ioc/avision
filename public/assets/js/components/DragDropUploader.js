/**
 * DragDropUploader - Composant réutilisable pour l'upload drag & drop
 * Remplace ~1000 lignes de code dupliqué dans les vues
 * 
 * Usage:
 * const uploader = new DragDropUploader({
 *   dropZoneId: 'dropZone',
 *   fileInputId: 'fileInput',
 *   fileListId: 'fileList',
 *   uploadUrl: 'contracts/addAttachment/123',
 *   onSuccess: (result) => { // ... }
 * });
 */

'use strict';

class DragDropUploader {
    /**
     * @param {Object} options - Options de configuration
     * @param {string} options.dropZoneId - ID de la zone de drop
     * @param {string} options.fileInputId - ID de l'input file
     * @param {string} options.fileListId - ID du conteneur de la liste de fichiers
     * @param {string} options.uploadUrl - URL pour l'upload
     * @param {string} [options.statsId] - ID du conteneur des statistiques
     * @param {string} [options.validCountId] - ID de l'élément affichant le nombre de fichiers valides
     * @param {string} [options.invalidCountId] - ID de l'élément affichant le nombre de fichiers invalides
     * @param {string} [options.progressFillId] - ID de la barre de progression
     * @param {string} [options.uploadBtnId] - ID du bouton d'upload
     * @param {string} [options.clearBtnId] - ID du bouton de nettoyage
     * @param {string} [options.filesOptionsId] - ID du conteneur des options de fichiers
     * @param {string} [options.filesOptionsListId] - ID de la liste des options de fichiers
     * @param {string} [options.formId] - ID du formulaire
     * @param {Function} [options.onSuccess] - Callback appelé en cas de succès
     * @param {Function} [options.onError] - Callback appelé en cas d'erreur
     * @param {boolean} [options.showClientVisibilityOption] - Afficher l'option "Masquer aux clients" (défaut: true)
     * @param {boolean} [options.showDescriptionOption] - Afficher l'option description (défaut: true)
     * @param {number} [options.maxSize] - Taille maximale en octets (défaut: depuis PHP)
     * @param {Array} [options.allowedExtensions] - Extensions autorisées (défaut: chargé depuis le serveur)
     * @param {string} [options.fileFieldName] - Nom du champ pour les fichiers (défaut: 'attachments')
     * @param {string} [options.descriptionFieldName] - Nom du champ pour la description (défaut: 'file_description')
     * @param {string} [options.visibilityFieldName] - Nom du champ pour la visibilité (défaut: 'file_masque_client')
     * @param {boolean} [options.visibilityInverted] - Inverser la logique de visibilité (défaut: false, true = visible_by_client)
     */
    constructor(options = {}) {
        // IDs requis
        this.dropZoneId = options.dropZoneId || 'dropZone';
        this.fileInputId = options.fileInputId || 'fileInput';
        this.fileListId = options.fileListId || 'fileList';
        this.uploadUrl = options.uploadUrl;
        
        // IDs optionnels
        this.statsId = options.statsId || 'stats';
        this.validCountId = options.validCountId || 'validCount';
        this.invalidCountId = options.invalidCountId || 'invalidCount';
        this.progressFillId = options.progressFillId || 'progressFill';
        this.uploadBtnId = options.uploadBtnId || 'uploadValidBtn';
        this.clearBtnId = options.clearBtnId || 'clearAllBtn';
        this.filesOptionsId = options.filesOptionsId || 'filesOptions';
        this.filesOptionsListId = options.filesOptionsListId || 'filesOptionsList';
        this.formId = options.formId || 'dragDropForm';
        
        // Callbacks
        this.onSuccess = options.onSuccess || null;
        this.onError = options.onError || null;
        
        // Options d'affichage
        this.showClientVisibilityOption = options.showClientVisibilityOption !== false;
        this.showDescriptionOption = options.showDescriptionOption !== false;
        
        // Configuration
        this.maxSize = options.maxSize || null;
        this.allowedExtensions = options.allowedExtensions || [];
        
        // Noms de champs personnalisables
        this.fileFieldName = options.fileFieldName || 'attachments';
        this.descriptionFieldName = options.descriptionFieldName || 'file_description';
        this.visibilityFieldName = options.visibilityFieldName || 'file_masque_client';
        this.visibilityInverted = options.visibilityInverted || false;
        
        // État
        this.files = [];
        this.initialized = false;
        
        // Ne pas initialiser immédiatement - sera fait manuellement après que le DOM soit prêt
    }
    
    /**
     * Initialise le composant
     * Doit être appelé après que les éléments DOM soient disponibles
     */
    async init() {
        // Récupérer les éléments DOM
        this.dropZone = document.getElementById(this.dropZoneId);
        this.fileInput = document.getElementById(this.fileInputId);
        this.fileList = document.getElementById(this.fileListId);
        this.stats = document.getElementById(this.statsId);
        this.validCount = document.getElementById(this.validCountId);
        this.invalidCount = document.getElementById(this.invalidCountId);
        this.progressFill = document.getElementById(this.progressFillId);
        this.uploadBtn = document.getElementById(this.uploadBtnId);
        this.clearBtn = document.getElementById(this.clearBtnId);
        this.filesOptions = document.getElementById(this.filesOptionsId);
        this.filesOptionsList = document.getElementById(this.filesOptionsListId);
        this.form = document.getElementById(this.formId);
        
        // Vérifier les éléments requis
        if (!this.dropZone || !this.fileInput || !this.fileList) {
            console.error('DragDropUploader: Éléments DOM requis manquants');
            return;
        }
        
        // Charger la configuration
        await this.loadConfiguration();
        
        // Configurer les événements
        this.setupEventListeners();
        
        this.initialized = true;
    }
    
    /**
     * Charge la configuration (extensions autorisées, taille max)
     */
    async loadConfiguration() {
        // Charger les extensions autorisées depuis le serveur
        if (this.allowedExtensions.length === 0) {
            try {
                const result = await ApiClient.get('settings/getAllowedExtensions');
                this.allowedExtensions = result.extensions || [];
            } catch (error) {
                console.error('Erreur lors du chargement des extensions autorisées:', error);
                Utils.showError('Erreur lors du chargement de la configuration');
            }
        }
        
        // Déterminer la taille maximale
        if (!this.maxSize) {
            // Essayer de récupérer depuis PHP si disponible
            const phpMaxSize = window.PHP_MAX_FILE_SIZE;
            if (phpMaxSize) {
                this.maxSize = Utils.parsePhpSize(phpMaxSize);
            } else {
                // Valeur par défaut : 10MB
                this.maxSize = 10 * 1024 * 1024;
            }
        }
    }
    
    /**
     * Configure les écouteurs d'événements
     */
    setupEventListeners() {
        // Drag & Drop events
        this.dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.dropZone.classList.add('dragover');
        });
        
        this.dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.dropZone.classList.remove('dragover');
        });
        
        this.dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.dropZone.classList.remove('dragover');
            const files = Array.from(e.dataTransfer.files);
            this.handleFiles(files);
        });
        
        // Click to select files
        this.dropZone.addEventListener('click', (e) => {
            if (e.target === this.dropZone || 
                e.target.classList.contains('drop-message') || 
                e.target.closest('.drop-message')) {
                this.fileInput.click();
            }
        });
        
        this.fileInput.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            this.handleFiles(files);
        });
        
        // Action buttons
        if (this.uploadBtn) {
            this.uploadBtn.addEventListener('click', () => {
                this.uploadValidFiles();
            });
        }
        
        if (this.clearBtn) {
            this.clearBtn.addEventListener('click', () => {
                this.clearAllFiles();
            });
        }
        
        // Form submission
        if (this.form) {
            this.form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.uploadValidFiles();
            });
        }
    }
    
    /**
     * Traite les fichiers ajoutés
     * @param {File[]} newFiles - Nouveaux fichiers à traiter
     */
    handleFiles(newFiles) {
        const validatedFiles = this.validateFiles(newFiles);
        this.files = [...this.files, ...validatedFiles];
        this.displayFiles();
        this.updateStats();
        this.updateFilesOptions();
    }
    
    /**
     * Valide les fichiers
     * @param {File[]} files - Fichiers à valider
     * @returns {Array} Fichiers validés avec leurs statuts
     */
    validateFiles(files) {
        return files.map(file => {
            const extension = file.name.split('.').pop().toLowerCase();
            const isValidExtension = this.allowedExtensions.length === 0 || this.allowedExtensions.includes(extension);
            const isSizeValid = file.size <= this.maxSize;
            
            let error = null;
            if (!isSizeValid) {
                error = `Le fichier est trop volumineux (${Utils.formatFileSize(file.size)}). Taille maximale autorisée : ${Utils.formatFileSize(this.maxSize)}.`;
            } else if (!isValidExtension) {
                error = 'Ce format n\'est pas accepté, rapprochez-vous de l\'administrateur du site, ou utilisez un format compressé.';
            }
            
            return {
                file,
                isValid: isValidExtension && isSizeValid,
                extension,
                error
            };
        });
    }
    
    /**
     * Affiche la liste des fichiers
     */
    displayFiles() {
        this.fileList.innerHTML = '';
        
        this.files.forEach((fileData, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = `file-item ${fileData.isValid ? 'valid' : 'invalid'}`;
            
            fileItem.innerHTML = `
                <span class="file-name">${Utils.escapeHtml(fileData.file.name)}</span>
                <span class="file-size">${Utils.formatFileSize(fileData.file.size)}</span>
                ${fileData.error ? `<span class="error-message">${Utils.escapeHtml(fileData.error)}</span>` : ''}
                <button type="button" class="remove-file" onclick="window.dragDropUploaderInstance?.removeFile(${index})">×</button>
            `;
            
            this.fileList.appendChild(fileItem);
        });
    }
    
    /**
     * Met à jour les options de fichiers (description, masquer aux clients)
     */
    updateFilesOptions() {
        if (!this.filesOptions || !this.filesOptionsList) return;
        
        const validFiles = this.files.filter(f => f.isValid);
        
        if (validFiles.length > 0) {
            this.filesOptions.style.display = 'block';
            this.filesOptionsList.innerHTML = '';
            
            validFiles.forEach((fileData, index) => {
                const fileOptionsDiv = document.createElement('div');
                fileOptionsDiv.className = 'file-options mb-2';
                
                let optionsHTML = '<div class="row align-items-center">';
                
                // Description option
                if (this.showDescriptionOption) {
                    optionsHTML += `
                        <div class="${this.showClientVisibilityOption ? 'col-md-8' : 'col-12'}">
                            <div class="d-flex align-items-center">
                                <strong class="me-3" style="min-width: 120px;">${Utils.escapeHtml(fileData.file.name)}</strong>
                                <input type="text" class="form-control form-control-sm" name="${this.descriptionFieldName}[${index}]" 
                                       placeholder="Titre ou description (optionnel)" style="max-width: 200px;">
                            </div>
                        </div>
                    `;
                }
                
                // Client visibility option
                if (this.showClientVisibilityOption) {
                    optionsHTML += `
                        <div class="${this.showDescriptionOption ? 'col-md-4' : 'col-12'}">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="${this.visibilityFieldName}[${index}]" value="1" id="masque_${index}">
                                <label class="form-check-label" for="masque_${index}">
                                    <i class="bi bi-eye-slash text-warning me-1"></i>
                                    ${this.visibilityInverted ? 'Visible par le client' : 'Masquer aux clients'}
                                </label>
                            </div>
                        </div>
                    `;
                }
                
                optionsHTML += '</div>';
                fileOptionsDiv.innerHTML = optionsHTML;
                this.filesOptionsList.appendChild(fileOptionsDiv);
            });
        } else {
            this.filesOptions.style.display = 'none';
        }
    }
    
    /**
     * Supprime un fichier de la liste
     * @param {number} index - Index du fichier à supprimer
     */
    removeFile(index) {
        if (index >= 0 && index < this.files.length) {
            this.files.splice(index, 1);
            this.displayFiles();
            this.updateStats();
            this.updateFilesOptions();
        }
    }
    
    /**
     * Met à jour les statistiques
     */
    updateStats() {
        const validFiles = this.files.filter(f => f.isValid);
        const invalidFiles = this.files.filter(f => !f.isValid);
        
        if (this.validCount) this.validCount.textContent = validFiles.length;
        if (this.invalidCount) this.invalidCount.textContent = invalidFiles.length;
        
        if (this.files.length > 0) {
            if (this.stats) this.stats.style.display = 'block';
            if (this.uploadBtn) this.uploadBtn.style.display = 'inline-block';
            if (this.clearBtn) this.clearBtn.style.display = 'inline-block';
            
            if (this.progressFill) {
                const progress = (validFiles.length / this.files.length) * 100;
                this.progressFill.style.width = `${progress}%`;
            }
        } else {
            if (this.stats) this.stats.style.display = 'none';
            if (this.uploadBtn) this.uploadBtn.style.display = 'none';
            if (this.clearBtn) this.clearBtn.style.display = 'none';
        }
    }
    
    /**
     * Vide tous les fichiers
     */
    clearAllFiles(askConfirmation = true) {
        if (askConfirmation && this.files.length > 0 && !confirm('Êtes-vous sûr de vouloir supprimer tous les fichiers ?')) {
            return;
        }
        
        this.files = [];
        this.displayFiles();
        this.updateStats();
        this.updateFilesOptions();
        if (this.fileInput) this.fileInput.value = '';
    }
    
    /**
     * Upload les fichiers valides
     */
    async uploadValidFiles() {
        const validFiles = this.files.filter(f => f.isValid);
        
        if (validFiles.length === 0) {
            Utils.showError('Aucun fichier valide à uploader');
            return;
        }
        
        if (!this.uploadUrl) {
            Utils.showError('URL d\'upload non définie');
            return;
        }
        
        // Préparer les données du formulaire
        const formData = new FormData();
        
        // Ajouter les fichiers
        validFiles.forEach((fileData, index) => {
            formData.append(`${this.fileFieldName}[${index}]`, fileData.file);
        });
        
        // Ajouter les options individuelles
        validFiles.forEach((fileData, index) => {
            const descriptionInput = document.querySelector(`input[name="${this.descriptionFieldName}[${index}]"]`);
            const visibilityInput = document.querySelector(`input[name="${this.visibilityFieldName}[${index}]"]`);
            
            if (descriptionInput && descriptionInput.value) {
                formData.append(`${this.descriptionFieldName}[${index}]`, descriptionInput.value);
            }
            if (visibilityInput) {
                const isChecked = visibilityInput.checked;
                if (this.visibilityInverted) {
                    // visible_by_client: 0 = visible, 1 = masqué
                    formData.append(`${this.visibilityFieldName}[${index}]`, isChecked ? '0' : '1');
                } else {
                    // file_masque_client: 1 = masqué
                    if (isChecked) {
                        formData.append(`${this.visibilityFieldName}[${index}]`, '1');
                    }
                }
            }
        });
        
        // Désactiver le bouton pendant l'upload
        if (this.uploadBtn) {
            this.uploadBtn.disabled = true;
            const originalHTML = this.uploadBtn.innerHTML;
            this.uploadBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-1"></i>Upload en cours...';
        }
        
        try {
            // Ne pas fournir options.headers ici: ApiClient injecte X-Requested-With + X-CSRF-Token.
            // FormData gère automatiquement Content-Type (boundary).
            const result = await ApiClient.post(this.uploadUrl, formData);
            
            if (result.success) {
                Utils.showSuccess(`${validFiles.length} fichier(s) uploadé(s) avec succès !`);
                
                // Appeler le callback de succès
                if (this.onSuccess) {
                    this.onSuccess(result);
                } else {
                    // Comportement par défaut : fermer la modale et recharger
                    const modal = bootstrap?.Modal?.getInstance?.(document.querySelector('.modal.show'));
                    if (modal) {
                        modal.hide();
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                }
            } else {
                throw new Error(result.error || 'Erreur inconnue');
            }
        } catch (error) {
            console.error('Erreur lors de l\'upload:', error);
            // Debug utile quand le serveur renvoie du HTML (login/redirect/erreur PHP) au lieu de JSON
            if (error && error.data) {
                console.error('Erreur lors de l\'upload (details):', error.data);
            }
            const errorMessage = error.message || 'Erreur lors de l\'upload des fichiers';
            Utils.showError(errorMessage);
            
            if (this.onError) {
                this.onError(error);
            }
        } finally {
            if (this.uploadBtn) {
                this.uploadBtn.disabled = false;
                this.uploadBtn.innerHTML = '<i class="bi bi-upload me-1"></i> Uploader les fichiers valides';
            }
        }
    }
    
    /**
     * Récupère la liste des fichiers valides
     * @returns {File[]} Liste des fichiers valides
     */
    getValidFiles() {
        return this.files.filter(f => f.isValid).map(f => f.file);
    }
    
    /**
     * Récupère la liste de tous les fichiers
     * @returns {File[]} Liste de tous les fichiers
     */
    getAllFiles() {
        return this.files.map(f => f.file);
    }
}

// Vérifier que les dépendances sont disponibles
if (typeof window !== 'undefined') {
    // Attendre que ApiClient et Utils soient chargés
    if (typeof ApiClient === 'undefined') {
        console.error('DragDropUploader: ApiClient n\'est pas disponible. Assurez-vous que ApiClient.js est chargé avant DragDropUploader.js');
    }
    if (typeof Utils === 'undefined') {
        console.error('DragDropUploader: Utils n\'est pas disponible. Assurez-vous que utils.js est chargé avant DragDropUploader.js');
    }
    
    // Exporter pour utilisation globale
    window.DragDropUploader = DragDropUploader;
}

// Exporter pour modules ES6 (si utilisé plus tard)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DragDropUploader;
}
