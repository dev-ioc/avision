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
  // IMPORTANT : cette table est réutilisée par plusieurs vues
  // (interventions_client : 13 colonnes / interventions
  // curatives-préventives : 9 colonnes). On ne peut donc pas se fier
  // à une liste de clés fixe : on dérive le nombre de colonnes RÉEL
  // depuis le <thead> de la table présente sur la page, puis on
  // tronque la config de visibilité à ce nombre. Sans ça, DataTables
  // reçoit des columnDefs ciblant des index inexistants (ex: target 9
  // sur une table à 9 colonnes) et lève
  // "Requested unknown parameter 'X' for row 0, column X".
  // ============================================================
  const fullColumnKeys = [
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

  const actualColumnCount = table.querySelectorAll("thead th").length;

  // Seule la page interventions_client expose le panneau de
  // visibilité des colonnes avec les clés ci-dessus ; sur les autres
  // pages (curatives/préventives), la table a une structure de
  // colonnes différente et aucune préférence de visibilité ne
  // s'applique : on n'utilise donc les clés que si le nombre de
  // colonnes correspond réellement.
  const columnKeys =
    actualColumnCount === fullColumnKeys.length ? fullColumnKeys : [];

  const savedColumnVisibility =
    (window.serverSavedSettings &&
      window.serverSavedSettings.interventionsTable_columnVisibility) ||
    {};

  function isColumnVisible(key) {
    if (lockedColumns.includes(key)) return true;
    return savedColumnVisibility[key] !== false; // visible par défaut
  }

  const visibilityColumnDefs = columnKeys
    .map(function (key, idx) {
      return { targets: idx, visible: isColumnVisible(key) };
    })
    // filet de sécurité supplémentaire : n'inclure que les index qui
    // existent réellement dans la table courante.
    .filter(function (def) {
      return def.targets < actualColumnCount;
    });

  // Les priorités responsive ne doivent cibler que des colonnes qui
  // existent réellement dans la table courante.
  const responsivePriorityDefs = [
    { targets: 0, responsivePriority: 1 },
    { targets: 1, responsivePriority: 2 },
    { targets: 2, responsivePriority: 3 },
    { targets: 3, responsivePriority: 4 },
    { targets: 4, responsivePriority: 5 },
    { targets: 5, responsivePriority: 6 },
    { targets: 6, responsivePriority: 7 },
    { targets: 7, responsivePriority: 8 },
  ].filter(function (def) {
    return def.targets < actualColumnCount;
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

    columnDefs: responsivePriorityDefs.concat(visibilityColumnDefs),

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
