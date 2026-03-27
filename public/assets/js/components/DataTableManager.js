/**
 * DataTableManager - Configuration unifiée pour DataTables
 * Remplace ~400 lignes de code répétées dans 4 fichiers
 * 
 * Usage:
 * DataTableManager.init('clientsTable', {
 *   order: [[0, 'asc']],
 *   responsiveDetails: { header: (row) => 'Détails du client ' + row.data()[0] }
 * });
 */

'use strict';

class DataTableManager {
    /**
     * Initialise une DataTable avec la configuration standard
     * @param {string} tableId - ID de la table
     * @param {Object} options - Options personnalisées
     * @returns {DataTable|null} Instance DataTable ou null si la table n'existe pas
     */
    static init(tableId, options = {}) {
        const table = document.querySelector(`#${tableId}`);
        if (!table) {
            console.warn(`DataTableManager: Table #${tableId} non trouvée`);
            return null;
        }
        
        const config = window.AppConfig || {};
        const defaultConfig = config.dataTable || {};
        
        // Récupérer la configuration sauvegardée
        const savedConfig = window.DataTablePersistence ? 
            window.DataTablePersistence.getTableConfig(tableId) : 
            null;
        
        // Configuration de base
        const dataTableConfig = {
            pageLength: savedConfig?.pageLength || options.pageLength || defaultConfig.pageLength || 10,
            lengthMenu: options.lengthMenu || defaultConfig.lengthMenu || [10, 25, 50, 100],
            order: savedConfig?.order || options.order || defaultConfig.order || [[0, 'desc']],
            
            // Layout configuration
            layout: options.layout || {
                topStart: {
                    search: {
                        placeholder: 'Rechercher...'
                    }
                },
                topEnd: {
                    rowClass: 'row mx-3 my-0 justify-content-between',
                    features: [
                        {
                            pageLength: {
                                menu: [10, 25, 50, 100],
                                text: 'Afficher _MENU_ entrées'
                            }
                        }
                    ]
                },
                bottomStart: {
                    rowClass: 'row mx-3 justify-content-between',
                    features: ['info']
                },
                bottomEnd: {
                    paging: {
                        firstLast: false
                    }
                }
            },
            
            // Language configuration
            language: options.language || {
                url: (config.baseUrl || window.BASE_URL || '') + 'assets/json/locales/datatables-fr.json',
                paginate: {
                    next: '<i class="icon-base bx bx-chevron-right scaleX-n1-rtl icon-sm"></i>',
                    previous: '<i class="icon-base bx bx-chevron-left scaleX-n1-rtl icon-sm"></i>'
                }
            },
            
            // Responsive configuration
            responsive: options.responsive !== false ? (options.responsive || {
                details: {
                    display: DataTable.Responsive.display.modal({
                        header: options.responsiveDetails?.header || function (row) {
                            return 'Détails';
                        }
                    }),
                    type: 'column',
                    renderer: options.responsiveDetails?.renderer || function (api, rowIdx, columns) {
                        const data = columns
                            .map(function (col) {
                                return col.title !== ''
                                    ? `<tr data-dt-row="${col.rowIndex}" data-dt-column="${col.columnIndex}">
                                        <td><strong>${col.title}:</strong></td>
                                        <td>${col.data}</td>
                                      </tr>`
                                    : '';
                            })
                            .join('');
                        
                        if (data) {
                            const div = document.createElement('div');
                            div.classList.add('table-responsive');
                            const table = document.createElement('table');
                            div.appendChild(table);
                            table.classList.add('table', 'table-striped');
                            const tbody = document.createElement('tbody');
                            tbody.innerHTML = data;
                            table.appendChild(tbody);
                            return div;
                        }
                        return false;
                    }
                }
            }) : false,
            
            // Column definitions
            columnDefs: options.columnDefs || [],
            
            // Callbacks
            initComplete: options.initComplete || function() {
                console.log(`DataTable ${tableId} initialized`);
            },
            
            drawCallback: function(settings) {
                // Sauvegarder la configuration actuelle
                if (window.DataTablePersistence) {
                    window.DataTablePersistence.saveTableConfig(tableId, {
                        pageLength: settings._iDisplayLength,
                        order: settings.aaSorting,
                        page: settings._iDisplayStart / settings._iDisplayLength
                    });
                }
                
                // Appeler le callback personnalisé si fourni
                if (options.drawCallback) {
                    options.drawCallback(settings);
                }
            },
            
            // Autres options personnalisées
            ...options.extra
        };
        
        // Initialiser la DataTable
        const dataTable = new DataTable(table, dataTableConfig);
        
        // Stocker l'instance pour référence future
        if (!window.DataTableManager) {
            window.DataTableManager = {};
        }
        window.DataTableManager[tableId] = dataTable;
        
        return dataTable;
    }
    
    /**
     * Récupère une instance DataTable existante
     * @param {string} tableId - ID de la table
     * @returns {DataTable|null} Instance DataTable ou null
     */
    static getInstance(tableId) {
        if (window.DataTableManager && window.DataTableManager[tableId]) {
            return window.DataTableManager[tableId];
        }
        return null;
    }
    
    /**
     * Détruit une DataTable
     * @param {string} tableId - ID de la table
     */
    static destroy(tableId) {
        const instance = this.getInstance(tableId);
        if (instance) {
            instance.destroy();
            delete window.DataTableManager[tableId];
        }
    }
}

// Exporter pour utilisation globale
if (typeof window !== 'undefined') {
    window.DataTableManager = DataTableManager;
}

// Exporter pour modules ES6 (si utilisé plus tard)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DataTableManager;
}
