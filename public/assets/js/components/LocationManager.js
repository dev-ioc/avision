/**
 * LocationManager - Gestion centralisée des localisations
 * Centralise loadClientLocations, buildLocationsInterface, etc.
 */

'use strict';

class LocationManager {
    /**
     * @param {Object} options - Options de configuration
     * @param {string} [options.baseUrl] - URL de base (défaut: depuis AppConfig)
     * @param {string} [options.containerSelector] - Sélecteur CSS du conteneur (défaut: '.locations-grid')
     */
    constructor(options = {}) {
        const config = window.AppConfig || {};
        this.baseUrl = options.baseUrl || config.baseUrl || window.BASE_URL || '';
        this.containerSelector = options.containerSelector || '.locations-grid';
    }
    
    /**
     * Charge les localisations d'un client via AJAX
     * @param {string|number} clientId - L'ID du client
     * @param {Function} [callback] - Callback à appeler après chargement
     * @returns {Promise} Promise résolue avec les données
     */
    async loadClientLocations(clientId, callback = null) {
        const container = document.querySelector(this.containerSelector);
        if (!container) {
            console.error('LocationManager: Conteneur de localisations non trouvé');
            return null;
        }
        
        // Vérifier que ApiClient est disponible
        if (typeof ApiClient === 'undefined') {
            console.error('LocationManager: ApiClient n\'est pas disponible');
            container.innerHTML = '<div class="alert alert-danger">Erreur: ApiClient non disponible</div>';
            return null;
        }
        
        // Afficher un loader
        container.innerHTML = '<div class="text-center p-4"><div class="spinner-border" role="status"><span class="visually-hidden">Chargement...</span></div></div>';
        
        try {
            const data = await ApiClient.get(`user/get_client_locations?client_id=${clientId}`);
            const locationsData = data.locations || data;
            
            this.buildLocationsInterface(locationsData, container);
            
            if (typeof callback === 'function') {
                callback(locationsData);
            }
            
            return locationsData;
        } catch (error) {
            console.error('Erreur lors du chargement des localisations:', error);
            container.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des localisations.</div>';
            throw error;
        }
    }
    
    /**
     * Construit l'interface des localisations avec accordéon
     * @param {Object} data - Les données des localisations
     * @param {HTMLElement} container - Le conteneur où afficher l'interface
     * @param {Object|null} existingLocations - Les localisations existantes à pré-sélectionner
     */
    buildLocationsInterface(data, container, existingLocations = null) {
        container.innerHTML = '';
        
        // Créer l'option d'accès complet au client
        const clientFullAccessDiv = document.createElement('div');
        clientFullAccessDiv.className = 'form-check mb-3';
        clientFullAccessDiv.innerHTML = `
            <input class="form-check-input" type="checkbox" id="client_full_access" name="locations[client_full]" value="1">
            <label class="form-check-label" for="client_full_access">
                <strong>Accès complet au client</strong> (toutes les localisations)
            </label>
        `;
        container.appendChild(clientFullAccessDiv);
        
        container.appendChild(document.createElement('hr'));
        
        // Si aucun site n'est disponible
        if (!data.sites || data.sites.length === 0) {
            const noSitesDiv = document.createElement('div');
            noSitesDiv.className = 'alert alert-info';
            noSitesDiv.textContent = 'Aucun site disponible pour ce client.';
            container.appendChild(noSitesDiv);
            return;
        }
        
        // Créer un accordéon pour les sites
        const accordionDiv = document.createElement('div');
        accordionDiv.className = 'accordion';
        accordionDiv.id = 'locationsAccordion';
        
        data.sites.forEach((site, siteIndex) => {
            const siteDiv = document.createElement('div');
            siteDiv.className = 'accordion-item';
            
            const siteHeader = document.createElement('h2');
            siteHeader.className = 'accordion-header';
            siteHeader.id = `heading${siteIndex}`;
            
            const siteButton = document.createElement('button');
            siteButton.className = 'accordion-button collapsed';
            siteButton.type = 'button';
            siteButton.setAttribute('data-bs-toggle', 'collapse');
            siteButton.setAttribute('data-bs-target', `#collapse${siteIndex}`);
            siteButton.setAttribute('aria-expanded', 'false');
            siteButton.setAttribute('aria-controls', `collapse${siteIndex}`);
            
            // Utiliser escapeHtml si disponible, sinon utiliser une fonction simple
            const escapeHtml = (typeof Utils !== 'undefined' && Utils.escapeHtml) 
                ? Utils.escapeHtml 
                : (text) => {
                    if (!text) return '';
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                };
            
            siteButton.innerHTML = `
                <div class="form-check me-3">
                    <input class="form-check-input site-checkbox" type="checkbox" id="site_${site.id}" name="locations[sites][]" value="${site.id}" data-site-id="${site.id}">
                    <label class="form-check-label" for="site_${site.id}"></label>
                </div>
                ${escapeHtml(site.name)}
            `;
            
            siteHeader.appendChild(siteButton);
            siteDiv.appendChild(siteHeader);
            
            const siteContent = document.createElement('div');
            siteContent.id = `collapse${siteIndex}`;
            siteContent.className = 'accordion-collapse collapse';
            siteContent.setAttribute('aria-labelledby', `heading${siteIndex}`);
            siteContent.setAttribute('data-bs-parent', '#locationsAccordion');
            
            const siteBody = document.createElement('div');
            siteBody.className = 'accordion-body';
            
            if (site.rooms && site.rooms.length > 0) {
                const roomsList = document.createElement('div');
                roomsList.className = 'rooms-list ms-4 mt-2';
                
                site.rooms.forEach(room => {
                    const roomDiv = document.createElement('div');
                    roomDiv.className = 'form-check mb-2';
                    
                    // Utiliser escapeHtml si disponible
                    const escapeHtml = (typeof Utils !== 'undefined' && Utils.escapeHtml) 
                        ? Utils.escapeHtml 
                        : (text) => {
                            if (!text) return '';
                            const div = document.createElement('div');
                            div.textContent = text;
                            return div.innerHTML;
                        };
                    
                    roomDiv.innerHTML = `
                        <input class="form-check-input room-checkbox" type="checkbox" id="room_${room.id}" name="locations[rooms][]" value="${room.id}" data-site-id="${site.id}">
                        <label class="form-check-label" for="room_${room.id}">
                            ${escapeHtml(room.name)}
                        </label>
                    `;
                    roomsList.appendChild(roomDiv);
                });
                
                siteBody.appendChild(roomsList);
            } else {
                siteBody.innerHTML = '<p class="text-muted ms-4">Aucune salle disponible pour ce site.</p>';
            }
            
            siteContent.appendChild(siteBody);
            siteDiv.appendChild(siteContent);
            accordionDiv.appendChild(siteDiv);
        });
        
        container.appendChild(accordionDiv);
        
        // Configurer les écouteurs d'événements
        this.setupCheckboxListeners();
        
        // Pré-sélectionner les localisations existantes
        if (existingLocations) {
            this.preselectLocations(existingLocations);
        }
    }
    
    /**
     * Configure les écouteurs d'événements des cases à cocher
     */
    setupCheckboxListeners() {
        const clientFullAccess = document.getElementById('client_full_access');
        if (clientFullAccess) {
            clientFullAccess.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.site-checkbox, .room-checkbox');
                allCheckboxes.forEach(checkbox => {
                    checkbox.disabled = this.checked;
                    if (this.checked) {
                        checkbox.checked = false;
                    }
                });
            });
        }
        
        // Gestion des cases à cocher de sites
        document.querySelectorAll('.site-checkbox').forEach(siteCheckbox => {
            siteCheckbox.addEventListener('change', function() {
                const siteId = this.dataset.siteId;
                const roomCheckboxes = document.querySelectorAll(`.room-checkbox[data-site-id="${siteId}"]`);
                roomCheckboxes.forEach(roomCheckbox => {
                    roomCheckbox.disabled = this.checked;
                    if (this.checked) {
                        roomCheckbox.checked = false;
                    }
                });
            });
        });
    }
    
    /**
     * Pré-sélectionne les localisations existantes
     * @param {Object} existingLocations - Les localisations existantes
     */
    preselectLocations(existingLocations) {
        // Accès complet au client
        if (existingLocations.client_full) {
            const clientFullAccess = document.getElementById('client_full_access');
            if (clientFullAccess) {
                clientFullAccess.checked = true;
                clientFullAccess.dispatchEvent(new Event('change'));
            }
            return;
        }
        
        // Sites
        if (existingLocations.sites && Array.isArray(existingLocations.sites)) {
            existingLocations.sites.forEach(siteId => {
                const siteCheckbox = document.getElementById(`site_${siteId}`);
                if (siteCheckbox) {
                    siteCheckbox.checked = true;
                    siteCheckbox.dispatchEvent(new Event('change'));
                }
            });
        }
        
        // Salles
        if (existingLocations.rooms && Array.isArray(existingLocations.rooms)) {
            existingLocations.rooms.forEach(roomId => {
                const roomCheckbox = document.getElementById(`room_${roomId}`);
                if (roomCheckbox) {
                    roomCheckbox.checked = true;
                }
            });
        }
    }
}

// Exporter pour utilisation globale
if (typeof window !== 'undefined') {
    window.LocationManager = LocationManager;
}

// Exporter pour modules ES6 (si utilisé plus tard)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = LocationManager;
}
