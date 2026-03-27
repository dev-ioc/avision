/**
 * ModalManager - Gestion centralisée des modales dynamiques
 * Remplace ~450 lignes de code dupliqué pour les modales
 * 
 * Usage:
 * ModalManager.onShow('myModal', async () => {
 *   const data = await ApiClient.get('endpoint');
 *   document.getElementById('modalContent').innerHTML = buildHTML(data);
 * });
 */

'use strict';

class ModalManager {
    constructor() {
        this.modals = new Map(); // Stocke les callbacks par ID de modale
    }
    
    /**
     * Enregistre un callback à exécuter quand une modale s'ouvre
     * @param {string} modalId - ID de la modale
     * @param {Function} callback - Callback à exécuter (peut être async)
     * @param {Object} options - Options supplémentaires
     */
    onShow(modalId, callback, options = {}) {
        const modalElement = document.getElementById(modalId);
        if (!modalElement) {
            console.warn(`ModalManager: Modale #${modalId} non trouvée`);
            return;
        }
        
        // Stocker le callback
        this.modals.set(modalId, { callback, options });
        
        // Écouter l'événement shown.bs.modal
        modalElement.addEventListener('shown.bs.modal', async (e) => {
            const config = this.modals.get(modalId);
            if (!config) return;
            
            try {
                // Afficher un loader si demandé
                if (config.options.showLoader !== false) {
                    const contentElement = document.getElementById(config.options.contentId || `${modalId}Content`);
                    if (contentElement) {
                        contentElement.innerHTML = '<div class="text-center p-4"><div class="spinner-border" role="status"><span class="visually-hidden">Chargement...</span></div></div>';
                    }
                }
                
                // Exécuter le callback
                await config.callback(e);
            } catch (error) {
                console.error(`ModalManager: Erreur lors du chargement de la modale ${modalId}:`, error);
                
                // Afficher un message d'erreur
                const contentElement = document.getElementById(config.options.contentId || `${modalId}Content`);
                if (contentElement) {
                    contentElement.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Erreur lors du chargement des données
                        </div>
                    `;
                }
                
                if (config.options.onError) {
                    config.options.onError(error);
                }
            }
        });
    }
    
    /**
     * Ouvre une modale et charge son contenu
     * @param {string} modalId - ID de la modale
     * @param {Function} loadCallback - Callback pour charger le contenu
     * @param {Object} options - Options supplémentaires
     */
    static open(modalId, loadCallback, options = {}) {
        const modalElement = document.getElementById(modalId);
        if (!modalElement) {
            console.warn(`ModalManager: Modale #${modalId} non trouvée`);
            return;
        }
        
        // Enregistrer le callback
        const manager = ModalManager.getInstance();
        manager.onShow(modalId, loadCallback, options);
        
        // Ouvrir la modale
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modal = new bootstrap.Modal(modalElement, options.modalOptions || {});
            modal.show();
        } else {
            console.error('ModalManager: Bootstrap Modal non disponible');
        }
    }
    
    /**
     * Ferme une modale
     * @param {string} modalId - ID de la modale
     */
    static close(modalId) {
        const modalElement = document.getElementById(modalId);
        if (!modalElement) return;
        
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        }
    }
    
    /**
     * Récupère l'instance singleton
     * @returns {ModalManager} Instance de ModalManager
     */
    static getInstance() {
        if (!window._modalManagerInstance) {
            window._modalManagerInstance = new ModalManager();
        }
        return window._modalManagerInstance;
    }
}

// Exporter pour utilisation globale
if (typeof window !== 'undefined') {
    window.ModalManager = ModalManager;
    // Créer l'instance singleton
    ModalManager.getInstance();
}

// Exporter pour modules ES6 (si utilisé plus tard)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ModalManager;
}
