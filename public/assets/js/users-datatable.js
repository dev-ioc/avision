/**
 * DataTable configuration for Users table
 * Responsive DataTable with modal details
 */

document.addEventListener('DOMContentLoaded', function() {
  'use strict';

  // Get the users table
  const dt_users_table = document.querySelector('#usersTable');
  
  if (dt_users_table) {
    // Récupérer la configuration sauvegardée
    const savedConfig = window.DataTablePersistence ? 
      window.DataTablePersistence.getTableConfig('usersTable_v2') : 
      { pageLength: 10, order: [[0, 'asc']] };

    let dt_users = new DataTable(dt_users_table, {
      // Configuration options avec persistance
      pageLength: savedConfig.pageLength,
      lengthMenu: [10, 25, 50, 100],
      order: savedConfig.order, // Sort by username ascending by default
      
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
              return 'Détails de l\'utilisateur ' + (data[1] || '') + ' ' + (data[2] || '');
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
          // Username column
          targets: 0,
          responsivePriority: 1
        },
        {
          // Last name column
          targets: 1,
          responsivePriority: 2
        },
        {
          // First name column
          targets: 2,
          responsivePriority: 3
        },
        {
          // Email column
          targets: 3,
          responsivePriority: 4
        },
        {
          // Type column
          targets: 4,
          responsivePriority: 5
        },
        {
          // Status column
          targets: 5,
          responsivePriority: 6
        },
        {
          // Created date column
          targets: 6,
          responsivePriority: 7
        }
      ],

      // Initialization complete callback
      initComplete: function() {
        // Add any additional initialization here
        console.log('Users DataTable initialized');
      },

      // Callbacks pour la persistance
      drawCallback: function(settings) {
        // Sauvegarder la configuration actuelle
        if (window.DataTablePersistence) {
          window.DataTablePersistence.saveTableConfig('usersTable_v2', {
            pageLength: settings._iDisplayLength,
            order: settings.aaSorting,
            page: settings._iDisplayStart / settings._iDisplayLength
          });
        }
      }
    });
  }
}); 