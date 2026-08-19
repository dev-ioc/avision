/**
 * DataTable configuration for Users table
 * Responsive DataTable with modal details
 */

document.addEventListener("DOMContentLoaded", function () {
  "use strict";

  const dt_users_table = document.querySelector("#usersTable");

  if (dt_users_table) {
    // v4 : bump obligatoire à chaque changement de structure de colonnes
    // (ici, suppression de la colonne "Nom d'utilisateur" : 7 → 6 colonnes)
    const tableConfigKey = "usersTable_v4";

    const savedConfig = window.DataTablePersistence
      ? window.DataTablePersistence.getTableConfig(tableConfigKey)
      : {
          pageLength: 10,
          order: [[0, "asc"]],
        };

    // Filet de sécurité : si une config sauvegardée référence une colonne
    // qui n'existe plus (ex: table restructurée sans bump de version),
    // on l'ignore silencieusement plutôt que de laisser DataTables planter.
    const actualColumnCount =
      dt_users_table.querySelectorAll("thead th").length;
    const safeOrder =
      Array.isArray(savedConfig.order) &&
      savedConfig.order.every(
        ([colIndex]) =>
          Number.isInteger(colIndex) && colIndex < actualColumnCount,
      )
        ? savedConfig.order
        : [[0, "asc"]];

    const dt_users = new DataTable(dt_users_table, {
      pageLength: savedConfig.pageLength || 10,

      lengthMenu: [10, 25, 50, 100],

      // Tri par défaut sur la colonne Nom
      order: safeOrder,

      layout: {
        topStart: {
          search: {
            placeholder: "Rechercher...",
          },
        },

        topEnd: {
          rowClass: "row mx-3 my-0 justify-content-between",
          features: [
            {
              pageLength: {
                menu: [10, 25, 50, 100],
                text: "Afficher _MENU_ entrées",
              },
            },
          ],
        },

        bottomStart: {
          rowClass: "row mx-3 justify-content-between",
          features: ["info"],
        },

        bottomEnd: {
          paging: {
            firstLast: false,
          },
        },
      },

      language: {
        url: "assets/json/locales/datatables-fr.json",

        paginate: {
          next: '<i class="icon-base bx bx-chevron-right scaleX-n1-rtl icon-sm"></i>',
          previous:
            '<i class="icon-base bx bx-chevron-left scaleX-n1-rtl icon-sm"></i>',
        },
      },

      responsive: {
        details: {
          display: DataTable.Responsive.display.modal({
            header: function (row) {
              const data = row.data();

              return (
                "Détails de l'utilisateur " +
                (data[0] || "") +
                " " +
                (data[1] || "")
              );
            },
          }),

          type: "column",

          renderer: function (api, rowIdx, columns) {
            const data = columns
              .map(function (col) {
                return col.title !== ""
                  ? `<tr data-dt-row="${col.rowIndex}" data-dt-column="${col.columnIndex}">
                      <td><strong>${col.title}:</strong></td>
                      <td>${col.data}</td>
                    </tr>`
                  : "";
              })
              .join("");

            if (data) {
              const div = document.createElement("div");
              div.classList.add("table-responsive");

              const table = document.createElement("table");
              table.classList.add("table", "table-striped");

              const tbody = document.createElement("tbody");
              tbody.innerHTML = data;

              table.appendChild(tbody);
              div.appendChild(table);

              return div;
            }

            return false;
          },
        },
      },

      // 6 colonnes uniquement
      columnDefs: [
        {
          // Nom
          targets: 0,
          responsivePriority: 1,
        },
        {
          // Prénom
          targets: 1,
          responsivePriority: 2,
        },
        {
          // Email
          targets: 2,
          responsivePriority: 3,
        },
        {
          // Type
          targets: 3,
          responsivePriority: 4,
        },
        {
          // Statut
          targets: 4,
          responsivePriority: 5,
        },
        {
          // Date de création
          targets: 5,
          responsivePriority: 6,
        },
      ],

      initComplete: function () {
        console.log("Users DataTable initialized");
      },

      drawCallback: function (settings) {
        if (window.DataTablePersistence) {
          window.DataTablePersistence.saveTableConfig(tableConfigKey, {
            pageLength: settings._iDisplayLength,
            order: settings.aaSorting,
            page: settings._iDisplayStart / settings._iDisplayLength,
          });
        }
      },
    });
  }
});
