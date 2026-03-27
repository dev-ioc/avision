'use strict';

/**
 * JavaScript pour les formulaires de contrats (add/edit)
 * Gère la logique de formulaire : types de contrats, dates, salles, etc.
 */

(function() {
    'use strict';

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        // Initialiser BASE_URL pour les fonctions communes
        if (typeof baseUrl !== 'undefined') {
            if (typeof initBaseUrl === 'function') {
                initBaseUrl(baseUrl);
            } else if (typeof window !== 'undefined') {
                window.BASE_URL = baseUrl;
            }
        }
        
        // Initialiser la validation Bootstrap
        if (typeof initBootstrapValidation === 'function') {
            initBootstrapValidation();
        }
        
        initContractTypeHandling();
        initDateHandling();
        initRoomsHandling();
    }

    /**
     * Gère la logique des types de contrats
     */
    function initContractTypeHandling() {
        const contractTypeSelect = document.getElementById('contract_type_id');
        const ticketsNumberInput = document.getElementById('tickets_number');
        const isticketcontractCheckbox = document.getElementById('isticketcontract');
        
        if (!contractTypeSelect || !ticketsNumberInput || !isticketcontractCheckbox) {
            return;
        }

        // Récupérer les types de contrats depuis le data-attribute
        let contractTypes = [];
        if (contractTypeSelect.dataset.contractTypes) {
            try {
                contractTypes = JSON.parse(contractTypeSelect.dataset.contractTypes);
            } catch (e) {
                console.error('Erreur lors du parsing des types de contrats:', e);
                return;
            }
        }

        if (contractTypes.length === 0) {
            return;
        }

        // Fonction pour mettre à jour les champs selon le type de contrat sélectionné
        function updateFieldsBasedOnContractType() {
            const selectedType = contractTypes.find(type => type.id == contractTypeSelect.value);
            if (selectedType) {
                // Pour l'édition, ne mettre à jour que si différent (pour éviter d'écraser une valeur personnalisée)
                const isEditMode = document.getElementById('contract_id') !== null;
                if (!isEditMode) {
                    // Mode ajout : toujours mettre à jour
                    ticketsNumberInput.value = selectedType.default_tickets;
                } else {
                    // Mode édition : mettre à jour seulement si différent
                    if (ticketsNumberInput.value !== selectedType.default_tickets.toString()) {
                        ticketsNumberInput.value = selectedType.default_tickets;
                    }
                }
                
                // Cocher automatiquement la case isticketcontract si le type a des tickets par défaut > 0
                if (selectedType.default_tickets > 0) {
                    isticketcontractCheckbox.checked = true;
                } else {
                    isticketcontractCheckbox.checked = false;
                }
            }
        }

        // Mettre à jour les champs quand le type de contrat change
        contractTypeSelect.addEventListener('change', updateFieldsBasedOnContractType);
        
        // Initialiser les champs au chargement de la page si un type est déjà sélectionné
        updateFieldsBasedOnContractType();
    }

    /**
     * Gère la logique des dates (calcul automatique de la date de fin)
     */
    function initDateHandling() {
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        
        if (!startDateInput || !endDateInput) {
            return;
        }

        // Calculer automatiquement la date de fin au 31 décembre de l'année suivante
        startDateInput.addEventListener('change', function() {
            if (this.value) {
                const startDate = new Date(this.value);
                const startYear = startDate.getFullYear();
                const endYear = startYear + 1;
                
                // Créer la date de fin au 31 décembre de l'année suivante
                const endDate = new Date(endYear, 11, 31); // 11 = décembre (0-indexé)
                
                // Formater la date au format YYYY-MM-DD
                const year = endDate.getFullYear();
                const month = String(endDate.getMonth() + 1).padStart(2, '0');
                const day = String(endDate.getDate()).padStart(2, '0');
                const formattedEndDate = `${year}-${month}-${day}`;
                
                endDateInput.value = formattedEndDate;
            }
        });
    }

    /**
     * Gère le chargement des salles selon le client sélectionné
     */
    function initRoomsHandling() {
        const clientSelect = document.getElementById('client_id_select');
        const roomsContainer = document.getElementById('rooms-container');
        
        // Vérifier si on est en mode édition (client fixe)
        const contractIdInput = document.getElementById('contract_id');
        const isEditMode = contractIdInput !== null;
        
        if (isEditMode) {
            // Mode édition : client fixe, charger les salles avec pré-sélection
            const clientId = contractIdInput.dataset.clientId || 
                           (document.getElementById('client_id') ? document.getElementById('client_id').value : null);
            
            if (clientId && roomsContainer && typeof loadContractRoomsSimple === 'function') {
                loadContractRoomsSimple(clientId, 'rooms-container', contractIdInput.value);
            }
        } else {
            // Mode ajout : client sélectionnable
            if (clientSelect && roomsContainer) {
                clientSelect.addEventListener('change', function() {
                    if (this.value) {
                        if (typeof loadContractRoomsSimple === 'function') {
                            loadContractRoomsSimple(this.value, 'rooms-container');
                        } else if (typeof Utils !== 'undefined' && Utils.loadRoomsBySite) {
                            // Fallback : utiliser Utils.loadRoomsBySite si disponible
                            // Note: loadContractRoomsSimple gère plusieurs sites, loadRoomsBySite gère un seul site
                            console.warn('loadContractRoomsSimple non disponible, utilisation de fallback');
                        }
                    } else {
                        roomsContainer.innerHTML = `
                            <div class="text-center text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Sélectionnez d'abord un client
                            </div>
                        `;
                    }
                });
                
                // Si un client est déjà sélectionné (ex: rechargement avec erreur de formulaire), charger ses salles
                if (clientSelect.value) {
                    if (typeof loadContractRoomsSimple === 'function') {
                        loadContractRoomsSimple(clientSelect.value, 'rooms-container');
                    }
                }
            }
            
            // Si le client est fixé (passé par l'URL via data-attribute)
            const clientIdFromData = clientSelect ? clientSelect.dataset.clientId : null;
            if (clientIdFromData && roomsContainer && typeof loadContractRoomsSimple === 'function') {
                loadContractRoomsSimple(clientIdFromData, 'rooms-container');
            }
        }
    }
})();
