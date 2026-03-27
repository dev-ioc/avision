/**
 * Utils - Fonctions utilitaires communes
 * Centralise les fonctions répétées dans les vues
 */

'use strict';

const Utils = {
    /**
     * Échappe le HTML pour éviter les injections XSS
     * @param {string} text - Texte à échapper
     * @returns {string} Texte échappé
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    /**
     * Gère les erreurs d'aperçu d'image
     * @param {Event} event - Événement d'erreur
     * @param {string} fallbackText - Texte à afficher en cas d'erreur (optionnel)
     */
    handlePreviewError(event, fallbackText = 'Impossible de charger l\'aperçu') {
        const img = event.target;
        if (img && img.tagName === 'IMG') {
            // Remplacer l'image par un message d'erreur
            const container = img.parentElement;
            if (container) {
                container.innerHTML = `
                    <div class="alert alert-warning d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <span>${this.escapeHtml(fallbackText)}</span>
                    </div>
                `;
            }
        }
    },
    
    /**
     * Gère les erreurs d'aperçu de fichier (iframe, embed, etc.)
     * @param {Event} event - Événement d'erreur
     * @param {string} fallbackText - Texte à afficher en cas d'erreur (optionnel)
     */
    handleFilePreviewError(event, fallbackText = 'Impossible de prévisualiser ce fichier') {
        const element = event.target;
        if (element) {
            // Remplacer le contenu par un message d'erreur
            const container = element.parentElement || element;
            container.innerHTML = `
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <span>${this.escapeHtml(fallbackText)}</span>
                </div>
            `;
        }
    },
    
    /**
     * Formate la taille d'un fichier en MB
     * @param {number} bytes - Taille en octets
     * @returns {string} Taille formatée
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 MB';
        const mb = bytes / (1024 * 1024);
        return mb.toFixed(2) + ' MB';
    },
    
    /**
     * Convertit la taille maximale de PHP en octets
     * @param {string} size - La taille au format PHP (ex: "8M", "2G")
     * @returns {number} La taille en octets
     */
    parsePhpSize(size) {
        const units = {
            'K': 1024,
            'M': 1024 * 1024,
            'G': 1024 * 1024 * 1024
        };
        const match = size.match(/^(\d+)([KMG])$/i);
        if (match) {
            return parseInt(match[1]) * units[match[2].toUpperCase()];
        }
        return parseInt(size);
    },
    
    /**
     * Affiche un message de succès
     * @param {string} message - Message à afficher
     * @param {number} duration - Durée d'affichage en ms (défaut: 3000)
     */
    showSuccess(message, duration = 3000) {
        // Utiliser Bootstrap toast si disponible, sinon alert
        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            // Créer un toast Bootstrap
            const toastContainer = document.getElementById('toast-container') || this._createToastContainer();
            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-success border-0';
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${this.escapeHtml(message)}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            toastContainer.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast, { delay: duration });
            bsToast.show();
            toast.addEventListener('hidden.bs.toast', () => toast.remove());
        } else {
            alert(message);
        }
    },
    
    /**
     * Affiche un message d'erreur
     * @param {string} message - Message à afficher
     * @param {number} duration - Durée d'affichage en ms (défaut: 5000)
     */
    showError(message, duration = 5000) {
        // Utiliser Bootstrap toast si disponible, sinon alert
        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            const toastContainer = document.getElementById('toast-container') || this._createToastContainer();
            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-danger border-0';
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${this.escapeHtml(message)}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            toastContainer.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast, { delay: duration });
            bsToast.show();
            toast.addEventListener('hidden.bs.toast', () => toast.remove());
        } else {
            alert('Erreur: ' + message);
        }
    },
    
    /**
     * Crée un conteneur pour les toasts si il n'existe pas
     * @private
     */
    _createToastContainer() {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }
        return container;
    },
    
    /**
     * Débounce une fonction
     * @param {Function} func - Fonction à débouncer
     * @param {number} wait - Délai en ms
     * @returns {Function} Fonction débouncée
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    /**
     * Throttle une fonction
     * @param {Function} func - Fonction à throttler
     * @param {number} limit - Limite en ms
     * @returns {Function} Fonction throttlée
     */
    throttle(func, limit) {
        let inThrottle;
        return function executedFunction(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },
    
    /**
     * Confirme une action avec l'utilisateur
     * @param {string} message - Message de confirmation
     * @param {string} title - Titre de la confirmation (optionnel)
     * @returns {Promise<boolean>} Promise résolue avec true si confirmé
     */
    async confirm(message, title = 'Confirmation') {
        return new Promise((resolve) => {
            if (confirm(message)) {
                resolve(true);
            } else {
                resolve(false);
            }
        });
    },
    
    /**
     * Charge les salles d'un site dans un select
     * Fonction centralisée qui gère automatiquement staff vs client
     * @param {string|number} siteId - ID du site
     * @param {string} roomSelectId - ID du select des salles
     * @param {string|number|null} currentRoomId - ID de la salle à présélectionner (optionnel)
     * @param {Function|null} callback - Callback à appeler après chargement (optionnel)
     * @param {string} [context] - Contexte : 'interventions', 'materiel', 'documentation' (défaut: 'interventions')
     * @param {boolean} [isClient] - Si true, utilise les endpoints client (défaut: détection automatique)
     * @returns {Promise} Promise résolue avec les salles
     */
    async loadRoomsBySite(siteId, roomSelectId, currentRoomId = null, callback = null, context = 'interventions', isClient = null) {
        const roomSelect = document.getElementById(roomSelectId);
        if (!roomSelect) {
            console.error('Utils.loadRoomsBySite: Select non trouvé:', roomSelectId);
            return;
        }
        
        // Vider le select
        roomSelect.innerHTML = '<option value="">Chargement...</option>';
        
        if (!siteId) {
            roomSelect.innerHTML = '<option value="">Sélectionner une salle</option>';
            return;
        }
        
        // Détecter automatiquement si on est en contexte client
        if (isClient === null) {
            // Vérifier l'URL ou le contexte de la page
            const path = window.location.pathname;
            isClient = path.includes('_client') || path.includes('client');
        }
        
        // Construire l'URL selon le contexte
        let url;
        const config = window.AppConfig || {};
        const baseUrl = config.baseUrl || window.BASE_URL || '';
        
        if (isClient) {
            // Endpoints client
            switch (context) {
                case 'interventions':
                    url = `${baseUrl}interventions_client/get_rooms?site_id=${siteId}`;
                    break;
                case 'documentation':
                    url = `${baseUrl}documentation_client/get_rooms?site_id=${siteId}`;
                    break;
                default:
                    url = `${baseUrl}interventions_client/get_rooms?site_id=${siteId}`;
            }
        } else {
            // Endpoints staff
            switch (context) {
                case 'interventions':
                    url = `${baseUrl}interventions/getRooms/${siteId}`;
                    break;
                case 'materiel':
                    url = `${baseUrl}materiel/get_rooms?site_id=${siteId}`;
                    break;
                case 'documentation':
                    url = `${baseUrl}documentation/get_rooms?site_id=${siteId}`;
                    break;
                default:
                    url = `${baseUrl}interventions/getRooms/${siteId}`;
            }
        }
        
        try {
            const data = await ApiClient.get(url);
            
            // Gérer les différents formats de réponse
            let rooms = [];
            if (Array.isArray(data)) {
                // Format direct : [{id, name}, ...]
                rooms = data;
            } else if (data.rooms && Array.isArray(data.rooms)) {
                // Format : {rooms: [{id, name}, ...]}
                rooms = data.rooms;
            } else if (data.data && Array.isArray(data.data)) {
                // Format : {data: [{id, name}, ...]}
                rooms = data.data;
            }
            
            // Vider et remplir le select
            roomSelect.innerHTML = '<option value="">Sélectionner une salle</option>';
            
            if (rooms.length > 0) {
                rooms.forEach(room => {
                    const option = document.createElement('option');
                    option.value = room.id;
                    option.textContent = room.name || room.nom || `Salle ${room.id}`;
                    if (currentRoomId && room.id == currentRoomId) {
                        option.selected = true;
                    }
                    roomSelect.appendChild(option);
                });
            } else {
                roomSelect.innerHTML = '<option value="">Aucune salle disponible</option>';
            }
            
            if (typeof callback === 'function') {
                callback(rooms);
            }
            
            return rooms;
        } catch (error) {
            console.error('Erreur lors du chargement des salles:', error);
            const errorMessage = error.message || 'Erreur lors du chargement';
            roomSelect.innerHTML = `<option value="">Erreur: ${Utils.escapeHtml(errorMessage)}</option>`;
            
            if (typeof callback === 'function') {
                callback([]);
            }
            
            throw error;
        }
    }
};

// Exporter pour utilisation globale
if (typeof window !== 'undefined') {
    window.Utils = Utils;
}

// Exporter pour modules ES6 (si utilisé plus tard)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Utils;
}
