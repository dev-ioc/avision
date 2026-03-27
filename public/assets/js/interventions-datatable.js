/**
 * DataTable configuration for Interventions table
 * Responsive DataTable with modal details
 */

document.addEventListener('DOMContentLoaded', function() {
  'use strict';

  // Get the interventions table
  const dt_interventions_table = document.querySelector('#interventionsTable');
  
  if (dt_interventions_table) {
    // Récupérer la configuration sauvegardée
    const savedConfig = window.DataTablePersistence ? 
      window.DataTablePersistence.getTableConfig('interventionsTable') : 
      { pageLength: 10, order: [[9, 'desc']] };

    let dt_interventions = new DataTable(dt_interventions_table, {
      // Configuration options avec persistance
      pageLength: savedConfig.pageLength,
      lengthMenu: [10, 25, 50, 100],
      order: savedConfig.order, // Sort by creation date descending by default
      
      // Layout configuration
      layout: {
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
      language: {
        url: (window.BASE_URL || '') + 'assets/json/locales/datatables-fr.json',
        paginate: {
          next: '<i class="icon-base bx bx-chevron-right scaleX-n1-rtl icon-sm"></i>',
          previous: '<i class="icon-base bx bx-chevron-left scaleX-n1-rtl icon-sm"></i>'
        }
      },

      // Responsive configuration
      responsive: {
        details: {
          display: DataTable.Responsive.display.modal({
            header: function (row) {
              var data = row.data();
              return 'Détails de l\'intervention ' + (data[0] || '');
            }
          }),
          type: 'column',
          renderer: function (api, rowIdx, columns) {
            const data = columns
              .map(function (col) {
                return col.title !== '' // Do not show row in modal popup if title is blank
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
              table.classList.add('table');
              table.classList.add('table-striped');
              const tbody = document.createElement('tbody');
              tbody.innerHTML = data;
              table.appendChild(tbody);
              return div;
            }
            return false;
          }
        }
      },

      // Column definitions
      columnDefs: [
        {
          // Reference column
          targets: 0,
          responsivePriority: 1
        },
        {
          // Title column
          targets: 1,
          responsivePriority: 2
        },
        {
          // Client column
          targets: 2,
          responsivePriority: 3
        },
        {
          // Site column - hide on small screens
          targets: 3,
          responsivePriority: 4
        },
        {
          // Room column - hide on small screens
          targets: 4,
          responsivePriority: 5
        },
        {
          // Status column
          targets: 5,
          responsivePriority: 6
        },
        {
          // Priority column
          targets: 6,
          responsivePriority: 7
        },
        {
          // Planned date column
          targets: 7,
          responsivePriority: 8
        },
        {
          // Technician column
          targets: 8,
          responsivePriority: 9
        },
        {
          // Creation date column
          targets: 9,
          responsivePriority: 10
        },
      ],

      // Initialization complete callback
      initComplete: function() {
        // Add any additional initialization here
        console.log('Interventions DataTable initialized');
      },

      // Callbacks pour la persistance
      drawCallback: function(settings) {
        // Sauvegarder la configuration actuelle
        if (window.DataTablePersistence) {
          window.DataTablePersistence.saveTableConfig('interventionsTable', {
            pageLength: settings._iDisplayLength,
            order: settings.aaSorting,
            page: settings._iDisplayStart / settings._iDisplayLength
          });
        }
      }
    });
  }
}); 