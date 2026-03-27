/**
 * Configuration centralisée de l'application
 * Contient les constantes et configurations globales
 */

'use strict';

// Configuration de l'application
const AppConfig = {
    // URL de base (sera initialisée depuis PHP)
    baseUrl: (typeof window !== 'undefined' && window.AppConfig && window.AppConfig.BASE_URL) 
        ? window.AppConfig.BASE_URL 
        : (typeof window !== 'undefined' && window.BASE_URL) 
            ? window.BASE_URL 
            : '',
    
    // Token CSRF (sera initialisé depuis PHP)
    csrfToken: (typeof window !== 'undefined' && window.AppConfig && window.AppConfig.CSRF_TOKEN) 
        ? window.AppConfig.CSRF_TOKEN 
        : (typeof window !== 'undefined' && window.CSRF_TOKEN) 
            ? window.CSRF_TOKEN 
            : '',
    
    // Configuration DataTable par défaut
    dataTable: {
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        language: {
            url: '', // Sera défini après l'initialisation de baseUrl
            emptyTable: 'Aucune donnée disponible dans le tableau',
            info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
            infoEmpty: 'Affichage de 0 à 0 sur 0 entrées',
            infoFiltered: '(filtré à partir de _MAX_ entrées au total)',
            lengthMenu: 'Afficher _MENU_ entrées',
            loadingRecords: 'Chargement...',
            processing: 'Traitement en cours...',
            search: 'Rechercher:',
            zeroRecords: 'Aucun enregistrement correspondant trouvé',
            paginate: {
                first: 'Premier',
                last: 'Dernier',
                next: 'Suivant',
                previous: 'Précédent'
            }
        },
        responsive: true,
        order: [[0, 'desc']], // Ordre par défaut : première colonne, décroissant
        dom: '<"card-header d-flex flex-wrap py-3 flex-md-row flex-column gap-3"<"me-3"l><"ms-auto"f>>t<"card-footer d-flex flex-wrap py-3 flex-md-row flex-column gap-3"<"me-3"i><"ms-auto"p>>',
        displayLength: 10
    },
    
    // Configuration des requêtes AJAX
    ajax: {
        timeout: 30000, // 30 secondes
        retryAttempts: 3,
        retryDelay: 1000 // 1 seconde
    },
    
    // Configuration des fichiers
    fileUpload: {
        maxSize: null, // Sera défini dynamiquement depuis PHP
        allowedExtensions: [] // Sera défini dynamiquement depuis PHP
    }
};

// Initialiser BASE_URL depuis window si disponible (priorité à window.AppConfig)
if (typeof window !== 'undefined') {
    if (window.AppConfig && window.AppConfig.BASE_URL) {
        AppConfig.baseUrl = window.AppConfig.BASE_URL;
    } else if (window.BASE_URL) {
        AppConfig.baseUrl = window.BASE_URL;
    }
    
    if (window.AppConfig && window.AppConfig.CSRF_TOKEN) {
        AppConfig.csrfToken = window.AppConfig.CSRF_TOKEN;
    } else if (window.CSRF_TOKEN) {
        AppConfig.csrfToken = window.CSRF_TOKEN;
    }
    
    // Initialiser l'URL de langue DataTable après avoir défini baseUrl
    if (AppConfig.baseUrl && AppConfig.dataTable && AppConfig.dataTable.language) {
        AppConfig.dataTable.language.url = AppConfig.baseUrl + 'assets/vendor/libs/datatables-bs5/i18n/fr-FR.json';
    }
    
    // Exporter pour utilisation globale
    window.AppConfig = AppConfig;
    // Debug: Afficher la configuration finale
    console.log('DEBUG: AppConfig final =', AppConfig);
}

// Exporter pour modules ES6 (si utilisé plus tard)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AppConfig;
}
