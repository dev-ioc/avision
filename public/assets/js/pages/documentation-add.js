'use strict';

/**
 * JavaScript pour le formulaire d'ajout de documentation
 * Gère la sélection client/site/salle et l'upload de fichiers avec DragDropUploader
 */

(function() {
    'use strict';

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        initSiteRoomHandlers();
        initDragDropUploader();
    }

    function initDragDropUploader() {
        if (typeof DragDropUploader === 'undefined' || typeof Utils === 'undefined' || typeof ApiClient === 'undefined') {
            console.error('documentation-add.js: Dépendances manquantes');
            return;
        }

        const form = document.getElementById('dragDropForm');
        if (!form) return;

        const baseUrl = window.BASE_URL || window.AppConfig?.BASE_URL || '';
        const uploadUrl = form.action.replace(baseUrl, '');

        const uploader = new DragDropUploader({
            dropZoneId: 'dropZone',
            fileInputId: 'fileInput',
            fileListId: 'fileList',
            uploadUrl: uploadUrl,
            statsId: 'stats',
            validCountId: 'validCount',
            invalidCountId: 'invalidCount',
            progressFillId: 'progressFill',
            uploadBtnId: 'uploadValidBtn',
            clearBtnId: 'clearAllBtn',
            filesOptionsId: 'filesOptions',
            filesOptionsListId: 'filesOptionsList',
            formId: 'dragDropForm',
            // Configuration spécifique pour documentation/store
            fileFieldName: 'files',
            descriptionFieldName: 'custom_names',
            visibilityFieldName: 'visible_by_client',
            visibilityInverted: true, // visible_by_client: 0 = visible, 1 = masqué
            onSuccess: (result) => {
                // Rediriger vers la liste avec les filtres
                const clientId = document.getElementById('client_id').value;
                const siteId = document.getElementById('site_id').value;
                const roomId = document.getElementById('room_id').value;
                
                const returnParams = new URLSearchParams();
                if (clientId) returnParams.set('client_id', clientId);
                if (siteId) returnParams.set('site_id', siteId);
                if (roomId) returnParams.set('salle_id', roomId);
                
                window.location.href = `${baseUrl}documentation?${returnParams.toString()}`;
            },
            onError: (error) => {
                console.error('Erreur lors de l\'upload:', error);
            }
        });

        // Surcharger uploadValidFiles pour inclure client/site/room
        const originalUploadValidFiles = uploader.uploadValidFiles.bind(uploader);
        uploader.uploadValidFiles = async function() {
            const validFiles = this.files.filter(f => f.isValid);
            
            if (validFiles.length === 0) {
                Utils.showError('Aucun fichier valide à uploader');
                return;
            }

            const clientId = document.getElementById('client_id').value;
            if (!clientId) {
                Utils.showError('Veuillez sélectionner un client');
                return;
            }

            if (!this.uploadUrl) {
                Utils.showError('URL d\'upload non définie');
                return;
            }

            // Préparer les données du formulaire
            const formData = new FormData();
            
            // Ajouter client/site/room
            formData.append('client_id', clientId);
            const siteId = document.getElementById('site_id').value;
            if (siteId) formData.append('site_id', siteId);
            const roomId = document.getElementById('room_id').value;
            if (roomId) formData.append('room_id', roomId);
            
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
                } else {
                    // Si pas de nom personnalisé, utiliser le nom du fichier
                    formData.append(`${this.descriptionFieldName}[${index}]`, fileData.file.name);
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
                } else {
                    // Par défaut, visible (0)
                    if (this.visibilityInverted) {
                        formData.append(`${this.visibilityFieldName}[${index}]`, '0');
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
                const result = await ApiClient.post(this.uploadUrl, formData);
                
                if (result.success) {
                    Utils.showSuccess(`${validFiles.length} fichier(s) uploadé(s) avec succès !`);
                    
                    // Appeler le callback de succès
                    if (this.onSuccess) {
                        this.onSuccess(result);
                    }
                } else {
                    Utils.showError(result.error || 'Erreur lors de l\'upload');
                    if (this.onError) {
                        this.onError(result);
                    }
                }
            } catch (error) {
                console.error('Erreur lors de l\'upload:', error);
                Utils.showError('Erreur lors de l\'upload: ' + (error.message || 'Erreur inconnue'));
                if (this.onError) {
                    this.onError(error);
                }
            } finally {
                if (this.uploadBtn) {
                    this.uploadBtn.disabled = false;
                    this.uploadBtn.innerHTML = '<i class="bi bi-upload me-1"></i>Uploader les fichiers';
                }
            }
        };

        // Initialiser le composant
        setTimeout(async () => {
            try {
                await uploader.init();
                console.log('DragDropUploader initialisé avec succès pour documentation/add');
            } catch (error) {
                console.error('Erreur lors de l\'initialisation de DragDropUploader:', error);
            }
        }, 0);
    }

    function initSiteRoomHandlers() {
        const clientSelect = document.getElementById('client_id');
        const siteSelect = document.getElementById('site_id');
        const roomSelect = document.getElementById('room_id');

        if (!clientSelect || !siteSelect || !roomSelect) return;

        // Mettre à jour les sites quand le client change
        clientSelect.addEventListener('change', function() {
            const clientId = this.value;
            if (clientId) {
                updateSites(clientId);
            } else {
                siteSelect.innerHTML = '<option value="">Sélectionner un site (optionnel)</option>';
                roomSelect.innerHTML = '<option value="">Sélectionner une salle (optionnel)</option>';
            }
        });

        // Mettre à jour les salles quand le site change
        siteSelect.addEventListener('change', function() {
            const siteId = this.value;
            if (siteId) {
                updateRooms(siteId);
            } else {
                roomSelect.innerHTML = '<option value="">Sélectionner une salle (optionnel)</option>';
            }
        });
    }

    function updateSites(clientId) {
        const siteSelect = document.getElementById('site_id');
        const roomSelect = document.getElementById('room_id');
        
        if (!siteSelect || !roomSelect) return;

        // Réinitialiser les salles
        roomSelect.innerHTML = '<option value="">Sélectionner une salle (optionnel)</option>';

        const baseUrl = window.BASE_URL || window.AppConfig?.BASE_URL || '';
        
        if (typeof ApiClient !== 'undefined') {
            ApiClient.get(`clients/getSites/${clientId}`)
                .then(data => {
                    siteSelect.innerHTML = '<option value="">Sélectionner un site (optionnel)</option>';
                    if (data.sites && Array.isArray(data.sites)) {
                        data.sites.forEach(site => {
                            const option = document.createElement('option');
                            option.value = site.id;
                            option.textContent = site.name;
                            siteSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la mise à jour des sites:', error);
                });
        } else {
            // Fallback avec fetch
            fetch(`${baseUrl}documentation/get_sites?client_id=${clientId}`)
                .then(response => response.json())
                .then(data => {
                    siteSelect.innerHTML = '<option value="">Sélectionner un site (optionnel)</option>';
                    if (Array.isArray(data)) {
                        data.forEach(site => {
                            const option = document.createElement('option');
                            option.value = site.id;
                            option.textContent = site.name;
                            siteSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la mise à jour des sites:', error);
                });
        }
    }

    function updateRooms(siteId) {
        const roomSelect = document.getElementById('room_id');
        if (!roomSelect) return;

        const baseUrl = window.BASE_URL || window.AppConfig?.BASE_URL || '';

        if (typeof Utils !== 'undefined' && Utils.loadRoomsBySite) {
            Utils.loadRoomsBySite(siteId, 'room_id', null, null, 'documentation', false);
        } else if (typeof ApiClient !== 'undefined') {
            ApiClient.get(`documentation/getRooms/${siteId}`)
                .then(data => {
                    roomSelect.innerHTML = '<option value="">Sélectionner une salle (optionnel)</option>';
                    if (data.rooms && Array.isArray(data.rooms)) {
                        data.rooms.forEach(room => {
                            const option = document.createElement('option');
                            option.value = room.id;
                            option.textContent = room.name;
                            roomSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la mise à jour des salles:', error);
                });
        } else {
            // Fallback avec fetch
            fetch(`${baseUrl}documentation/get_rooms?site_id=${siteId}`)
                .then(response => response.json())
                .then(data => {
                    roomSelect.innerHTML = '<option value="">Sélectionner une salle (optionnel)</option>';
                    if (Array.isArray(data)) {
                        data.forEach(room => {
                            const option = document.createElement('option');
                            option.value = room.id;
                            option.textContent = room.name;
                            roomSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la mise à jour des salles:', error);
                });
        }
    }
})();
