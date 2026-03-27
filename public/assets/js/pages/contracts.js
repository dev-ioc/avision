/**
 * JavaScript spécifique à la page de visualisation des contrats
 * Utilise les composants réutilisables (DragDropUploader, Utils, etc.)
 */

'use strict';

(function() {
    'use strict';
    
    // Attendre que le DOM soit chargé
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        console.log('contracts.js: Initialisation...');
        console.log('contracts.js: DragDropUploader disponible?', typeof DragDropUploader !== 'undefined');
        console.log('contracts.js: Utils disponible?', typeof Utils !== 'undefined');
        console.log('contracts.js: ApiClient disponible?', typeof ApiClient !== 'undefined');
        
        // IMPORTANT:
        // Cette page charge `contracts.js` AVANT le footer qui charge les dépendances (DragDropUploader/Utils/ApiClient).
        // Donc on ne doit PAS `return` ici: on attend le moment où la modale s'ouvre pour vérifier/initialiser.
        initAttachmentUploader();
        initPreviewErrorHandling();
    }
    
    /**
     * Initialise l'uploader de pièces jointes
     */
    function initAttachmentUploader() {
        console.log('contracts.js: initAttachmentUploader appelée.');
        const modal = document.getElementById('addAttachmentModal');
        if (!modal) {
            console.log('contracts.js: Modale #addAttachmentModal non trouvée.');
            return;
        }
        console.log('contracts.js: Modale #addAttachmentModal trouvée.');

        let uploader = null;
        
        // Initialiser l'uploader quand la modale s'ouvre
        modal.addEventListener('shown.bs.modal', async function() {
            console.log('contracts.js: Événement shown.bs.modal déclenché.');
            // Vérifier les dépendances au moment utile (après chargement du footer)
            if (typeof DragDropUploader === 'undefined' || typeof Utils === 'undefined' || typeof ApiClient === 'undefined') {
                console.error('contracts.js: Dépendances manquantes au moment d\'ouvrir la modale', {
                    DragDropUploader: typeof DragDropUploader,
                    Utils: typeof Utils,
                    ApiClient: typeof ApiClient
                });
                return;
            }

            if (!uploader || !uploader.initialized) {
                console.log('contracts.js: Création ou réinitialisation de DragDropUploader.');
                // Récupérer l'URL d'upload depuis le formulaire
                const form = document.getElementById('dragDropForm');
                const baseUrl = window.BASE_URL || window.AppConfig?.BASE_URL || '';
                const uploadUrl = form ? form.action.replace(baseUrl, '') : null;
                
                console.log('contracts.js: Form trouvé?', !!form);
                console.log('contracts.js: Upload URL:', uploadUrl);
                
                if (!uploadUrl) {
                    console.error('contracts.js: URL d\'upload non trouvée');
                    return;
                }
                
                // Vérifier que les éléments DOM existent
                const dropZone = document.getElementById('dropZone');
                const fileInput = document.getElementById('fileInput');
                const fileList = document.getElementById('fileList');
                
                console.log('contracts.js: dropZone trouvé?', !!dropZone);
                console.log('contracts.js: fileInput trouvé?', !!fileInput);
                console.log('contracts.js: fileList trouvé?', !!fileList);
                
                if (!dropZone || !fileInput || !fileList) {
                    console.error('contracts.js: Éléments DOM manquants pour l\'uploader');
                    return;
                }
                
                // Créer l'instance de l'uploader
                uploader = new DragDropUploader({
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
                    onSuccess: (result) => {
                        console.log('contracts.js: Upload réussi', result);
                        // Fermer la modale et recharger la page
                        const modalInstance = bootstrap?.Modal?.getInstance?.(modal);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    },
                    onError: (error) => {
                        console.error('contracts.js: Erreur lors de l\'upload:', error);
                        if (error && error.data) {
                            console.error('contracts.js: Détails erreur upload:', error.data);
                        }
                    }
                });
                
                // Initialiser l'uploader - attendre que le DOM soit prêt
                setTimeout(async () => {
                    try {
                        console.log('contracts.js: Appel de uploader.init()...');
                        await uploader.init();
                        console.log('contracts.js: DragDropUploader initialisé avec succès');
                    } catch (error) {
                        console.error('contracts.js: Erreur lors de l\'initialisation de DragDropUploader:', error);
                        console.error('contracts.js: Stack trace:', error.stack);
                    }
                }, 0);
                
                // Stocker l'instance globalement pour les fonctions onclick
                window.dragDropUploaderInstance = uploader;
            } else {
                console.log('contracts.js: DragDropUploader déjà initialisé.');
            }
        });
        
        // Réinitialiser l'uploader quand la modale se ferme
        modal.addEventListener('hidden.bs.modal', function() {
            console.log('contracts.js: Événement hidden.bs.modal déclenché.');
            if (uploader) {
                uploader.clearAllFiles(false); // Silent reset
                uploader.initialized = false; // Permettre la réinitialisation
                uploader = null;
                console.log('contracts.js: Uploader réinitialisé.');
            }
        });
    }
    
    /**
     * Initialise la gestion des erreurs d'aperçu (images, PDFs)
     */
    function initPreviewErrorHandling() {
        // Gérer les erreurs d'images
        document.querySelectorAll('img[src*="contracts/preview"]').forEach(img => {
            img.addEventListener('error', function() {
                const attachmentId = this.dataset.attachmentId || this.getAttribute('data-attachment-id');
                const fileName = this.alt || this.title || 'Fichier';
                Utils.handlePreviewError({
                    target: this
                }, `Impossible d'afficher l'aperçu de l'image: ${fileName}`);
            });
        });
        
        // Gérer les erreurs d'iframe pour les PDFs
        document.querySelectorAll('iframe[src*="contracts/preview"]').forEach(iframe => {
            iframe.addEventListener('error', function() {
                const container = this.parentElement;
                const downloadUrl = this.src.replace('preview', 'download');
                container.innerHTML = `
                    <div class="alert alert-warning text-center">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Impossible d'afficher l'aperçu du PDF</strong><br><br>
                        <a href="${downloadUrl}" 
                           class="btn btn-sm btn-outline-primary" 
                           target="_blank">
                            <i class="bi bi-download me-1"></i> Télécharger le fichier
                        </a>
                    </div>
                `;
            });
        });
    }
    
    /**
     * Confirme la suppression d'un contrat
     * @param {number} contractId - ID du contrat
     * @param {string} contractName - Nom du contrat
     */
    window.confirmDeleteContract = function(contractId, contractName) {
        const message = `Êtes-vous sûr de vouloir supprimer le contrat "${contractName}" ?\n\nCette action est irréversible et supprimera définitivement le contrat.`;
        Utils.confirm(message).then(confirmed => {
            if (confirmed) {
                window.location.href = (window.BASE_URL || '') + 'contracts/delete/' + contractId;
            }
        });
    };
})();
