document.addEventListener("DOMContentLoaded", function () {
  "use strict";

  const table = document.querySelector("#interventionsTable");
  if (!table) return;

  // === NETTOYAGE COMPLET ===
  // 1. Supprimer toutes les configurations localStorage
  Object.keys(localStorage).forEach((key) => {
    if (key.includes("DataTable") || key.includes("interventionsTable")) {
      localStorage.removeItem(key);
      console.log(`🗑️ Supprimé: ${key}`);
    }
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
      const dt = DataTable.isDataTable(table);
      if (dt) {
        // Note: DataTable native ne permet pas toujours de détruire facilement
        console.log("⚠️ Instance DataTable native détectée");
      }
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

  // === INITIALISATION ===
  const dt = new DataTable(table, {
    pageLength: 10,
    lengthMenu: [10, 25, 50, 100],
    order: [[7, "desc"]],
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
    ],
    initComplete: function () {
      console.log("✅ DataTable initialisée avec succès");
    },
  });
});
