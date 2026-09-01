document.addEventListener("DOMContentLoaded", function () {
  "use strict";

  const table = document.querySelector("#interventionsTable");
  if (!table) return;

  table.querySelectorAll("tbody tr td[colspan]").forEach((td) => {
    td.closest("tr").remove();
  });

  // 2. Détruire toute instance DataTable existante
  try {
    // Méthode jQuery si disponible
    if (
      typeof $ !== "undefined" &&
      $.fn.DataTable &&
      $.fn.DataTable.isDataTable(table)
    ) {
      $(table).DataTable().destroy();
      console.log("✅ Instance DataTable détruite (jQuery)");
    }

    // Méthode DataTables native
    else if (typeof DataTable !== "undefined" && DataTable.isDataTable(table)) {
      // CORRECTIF : DataTable.isDataTable() renvoie un booléen, ce n'est pas
      // une instance sur laquelle appeler .destroy(). Sans ce correctif, une
      // ré-initialisation ne détruisait jamais l'ancienne instance native.
      new DataTable(table).destroy();
      console.log("✅ Instance DataTable détruite (native)");
    }
  } catch (e) {
    console.log("Aucune instance à détruire");
  }

  // 3. Nettoyer les classes et attributs DataTables
  table.classList.remove("dataTable");
  table.removeAttribute("data-dt-instance");

  // 4. Supprimer les wrappers DataTables si présents
  const wrapper = table.closest(".dataTables_wrapper");

  if (wrapper && wrapper.parentNode) {
    const parent = wrapper.parentNode;
    parent.insertBefore(table, wrapper);
    parent.removeChild(wrapper);
    console.log("✅ Wrapper DataTables supprimé");
  }

  // Récupération de la configuration sauvegardée
  const savedPageLength = window.DataTablePersistence
    ? DataTablePersistence.getSetting("interventionsTable", "pageLength", 10)
    : 10;

  // ============================================================
  // Visibilité des colonnes (retour client - point 2)
  // IMPORTANT : on calcule la visibilité initiale ICI, avant la création
  // du DataTable, et on l'injecte directement dans columnDefs. Basculer
  // .column().visible() APRÈS l'init (en boucle sur plusieurs colonnes)
  // entre en conflit avec le calcul interne de l'extension Responsive et
  // provoque le warning "Requested unknown parameter for row X, column Y".
  // ============================================================
  const columnKeys = [
    "reference",
    "title",
    "client",
    "site",
    "building",
    "room",
    "status",
    "priority",
    "date_planif",
    "technician",
    "ref_client",
    "created_at",
    "closed_at",
  ];
  const lockedColumns = ["reference"];

  const savedColumnVisibility =
    (window.serverSavedSettings &&
      window.serverSavedSettings.interventionsTable_columnVisibility) ||
    {};

  function isColumnVisible(key) {
    if (lockedColumns.includes(key)) return true;
    return savedColumnVisibility[key] !== false; // visible par défaut
  }

  const visibilityColumnDefs = columnKeys.map(function (key, idx) {
    return { targets: idx, visible: isColumnVisible(key) };
  });

  const dt = new DataTable(table, {
    pageLength: savedPageLength,

    lengthMenu: [10, 25, 50, 100],

    order: [],

    layout: {
      topStart: {
        search: {
          placeholder: "Rechercher...",
        },
      },

      topEnd: {
        features: [
          {
            pageLength: {
              menu: [10, 25, 50, 100],
            },
          },
        ],
      },

      bottomStart: ["info"],

      bottomEnd: ["paging"],
    },

    language: {
      url: (window.BASE_URL || "") + "assets/json/locales/datatables-fr.json",
    },

    responsive: {
      details: {
        display: DataTable.Responsive.display.modal({
          header: function (row) {
            const data = row.data();

            return "Détails : " + (data[0] || "");
          },
        }),

        type: "column",
      },
    },

    columnDefs: [
      { targets: 0, responsivePriority: 1 },
      { targets: 1, responsivePriority: 2 },
      { targets: 2, responsivePriority: 3 },
      { targets: 3, responsivePriority: 4 },
      { targets: 4, responsivePriority: 5 },
      { targets: 5, responsivePriority: 6 },
      { targets: 6, responsivePriority: 7 },
      { targets: 7, responsivePriority: 8 },
    ].concat(visibilityColumnDefs),

    initComplete: function () {
      console.log("✅ DataTable initialisée avec succès");
    },
  });

  dt.on("length.dt", function (e, settings, len) {
    if (window.DataTablePersistence) {
      DataTablePersistence.setSetting("interventionsTable", "pageLength", len);

      console.log("✅ Nombre d'entrées sauvegardé :", len);
    }
  });

  // ============================================================
  // Expose une API simple pour basculer UNE colonne à la fois depuis
  // le panneau "Colonnes affichées" (index.php), sans jamais boucler
  // sur plusieurs colonnes d'un coup après l'init.
  // ============================================================
  window.interventionsTableSetColumnVisible = function (key, visible) {
    const idx = columnKeys.indexOf(key);
    if (idx === -1 || lockedColumns.includes(key)) return;

    dt.column(idx).visible(visible);

    if (dt.responsive && typeof dt.responsive.recalc === "function") {
      dt.responsive.recalc();
    }
  };
});
