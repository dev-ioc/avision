'use strict';

(function() {
    'use strict';

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        initAttachmentUploader();
        initPasswordToggles();
        initPreviewErrorHandling();
    }

    function initAttachmentUploader() {
        const modal = document.getElementById('addAttachmentModal');
        if (!modal) return;

        let uploader = null;

        modal.addEventListener('shown.bs.modal', async function() {
            if (!uploader || !uploader.initialized) {
                if (typeof DragDropUploader === 'undefined' || typeof Utils === 'undefined') {
                    console.error('materiel.js: Dépendances DragDropUploader ou Utils manquantes.');
                    return;
                }
                const form = document.getElementById('dragDropForm');
                const baseUrl = window.BASE_URL || window.AppConfig?.BASE_URL || '';
                const uploadUrl = form ? form.action.replace(baseUrl, '') : null;

                if (!uploadUrl) {
                    console.error('URL d\'upload non trouvée');
                    return;
                }

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
                    showDescriptionOption: true,
                    showClientVisibilityOption: true,
                    onSuccess: (result) => {
                        const modalInstance = bootstrap?.Modal?.getInstance?.(modal);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    },
                    onError: (error) => {
                        console.error('Erreur lors de l\'upload:', error);
                        if (error && error.data) {
                            console.error('Erreur lors de l\'upload (details):', error.data);
                        }
                    }
                });

                setTimeout(async () => {
                    try {
                        await uploader.init();
                        console.log('DragDropUploader initialisé avec succès');
                    } catch (error) {
                        console.error('Erreur lors de l\'initialisation de DragDropUploader:', error);
                    }
                }, 0);
                
                window.dragDropUploaderInstance = uploader;
            }
        });

        modal.addEventListener('hidden.bs.modal', function() {
            if (uploader) {
                uploader.clearAllFiles(false); // Silent reset
                uploader.initialized = false;
                uploader = null;
            }
        });
    }

    function initPasswordToggles() {
        // Toggle password principal
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const passwordIcon = document.getElementById('passwordIcon');

        if (togglePassword && passwordInput && passwordIcon) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Changer l'icône
                if (type === 'text') {
                    passwordIcon.classList.remove('bi-eye');
                    passwordIcon.classList.add('bi-eye-slash');
                    togglePassword.title = 'Masquer le mot de passe';
                } else {
                    passwordIcon.classList.remove('bi-eye-slash');
                    passwordIcon.classList.add('bi-eye');
                    togglePassword.title = 'Afficher le mot de passe';
                }
            });
        }

        // Toggle password WiFi
        const togglePasswordWifi = document.getElementById('togglePasswordWifi');
        const passwordWifiInput = document.getElementById('passwordWifi');
        const passwordWifiIcon = document.getElementById('passwordWifiIcon');

        if (togglePasswordWifi && passwordWifiInput && passwordWifiIcon) {
            togglePasswordWifi.addEventListener('click', function() {
                const type = passwordWifiInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordWifiInput.setAttribute('type', type);
                
                // Changer l'icône
                if (type === 'text') {
                    passwordWifiIcon.classList.remove('bi-eye');
                    passwordWifiIcon.classList.add('bi-eye-slash');
                    togglePasswordWifi.title = 'Masquer le mot de passe WiFi';
                } else {
                    passwordWifiIcon.classList.remove('bi-eye-slash');
                    passwordWifiIcon.classList.add('bi-eye');
                    togglePasswordWifi.title = 'Afficher le mot de passe WiFi';
                }
            });
        }
    }

    function initPreviewErrorHandling() {
        document.querySelectorAll('img[data-preview-url]').forEach(img => {
            img.addEventListener('error', function() {
                if (typeof Utils !== 'undefined') Utils.handlePreviewError({ target: this });
            });
        });

        document.querySelectorAll('iframe[src*="materiel/preview"]').forEach(iframe => {
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

    window.confirmDeleteMateriel = function(materielId, materielName) {
        const message = `Êtes-vous sûr de vouloir supprimer le matériel "${materielName}" ?\n\nCette action est irréversible et supprimera définitivement le matériel et toutes ses données associées (pièces jointes, historique, etc.).`;
        if (typeof Utils !== 'undefined') {
            Utils.confirm(message).then(confirmed => {
                if (confirmed) {
                    window.location.href = (window.BASE_URL || '') + 'materiel/delete/' + materielId;
                }
            });
        } else {
            if (confirm(message)) {
                window.location.href = (window.BASE_URL || '') + 'materiel/delete/' + materielId;
            }
        }
    };
})();
