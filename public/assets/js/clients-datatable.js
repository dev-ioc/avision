/**
 * DataTable configuration for Clients table
 * Responsive DataTable with modal details
 */

document.addEventListener('DOMContentLoaded', function() {
  'use strict';

  // Get the clients table
  const dt_clients_table = document.querySelector('#clientsTable');
  
  if (dt_clients_table) {
    // Récupérer la configuration sauvegardée
    const savedConfig = window.DataTablePersistence ? 
      window.DataTablePersistence.getTableConfig('clientsTable') : 
      { pageLength: 10, order: [[0, 'asc']] };

    let dt_clients = new DataTable(dt_clients_table, {
      // Configuration options avec persistance
      pageLength: savedConfig.pageLength,
      lengthMenu: [10, 25, 50, 100],
      order: savedConfig.order, // Sort by client name ascending by default
      
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
        url: 'assets/json/locales/datatables-fr.json',
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
              return 'Détails du client ' + (data[0] || '');
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
          // Client name column
          targets: 0,
          responsivePriority: 1
        },
        {
          // City column
          targets: 1,
          responsivePriority: 2
        },
        {
          // Email column
          targets: 2,
          responsivePriority: 3
        },
        {
          // Phone column
          targets: 3,
          responsivePriority: 4
        },
        {
          // Status column
          targets: 4,
          responsivePriority: 5
        },
        {
          // Sites column
          targets: 5,
          responsivePriority: 6
        },
        {
          // Rooms column
          targets: 6,
          responsivePriority: 7
        },
        {
          // Contracts column
          targets: 7,
          responsivePriority: 8
        },
        {
          // Tickets remaining column
          targets: 8,
          responsivePriority: 9,
          type: 'num'
        }
      ],

      // Initialization complete callback
      initComplete: function() {
        // Add any additional initialization here
        console.log('Clients DataTable initialized');
      },

      // Callbacks pour la persistance
      drawCallback: function(settings) {
        // Sauvegarder la configuration actuelle
        if (window.DataTablePersistence) {
          window.DataTablePersistence.saveTableConfig('clientsTable', {
            pageLength: settings._iDisplayLength,
            order: settings.aaSorting,
            page: settings._iDisplayStart / settings._iDisplayLength
          });
        }
      }
    });
  }
}); 