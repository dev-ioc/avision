/**
 * DataTable Persistence Utility
 * Gère la persistance des configurations DataTable (pageLength, etc.)
 *
 * Stratégie à deux niveaux :
 * 1. localStorage : cache instantané, mais peut être vidé par le navigateur
 *    (Chrome "effacer à la fermeture", politique d'entreprise, extension, etc.)
 * 2. Base de données (via window.serverSavedSettings, injecté par PHP au
 *    chargement de la page) : source de vérité durable, liée au compte utilisateur.
 */

window.DataTablePersistence = {
  STORAGE_PREFIX: "datatable_",

  /**
   * Récupère la configuration sauvegardée pour une table spécifique.
   * Priorité : valeur serveur (la plus fiable) > localStorage > valeur par défaut.
   */
  getSetting: function (tableId, setting, defaultValue) {
    const settingKey = tableId + "_" + setting;

    if (
      window.serverSavedSettings &&
      window.serverSavedSettings[settingKey] !== undefined &&
      window.serverSavedSettings[settingKey] !== null
    ) {
      return window.serverSavedSettings[settingKey];
    }

    try {
      const key = this.STORAGE_PREFIX + settingKey;
      const stored = localStorage.getItem(key);
      return stored !== null ? JSON.parse(stored) : defaultValue;
    } catch (e) {
      console.warn("Erreur lors de la récupération du paramètre DataTable:", e);
      return defaultValue;
    }
  },

  /**
   * Sauvegarde une configuration pour une table spécifique.
   * Écrit en localStorage (immédiat) puis synchronise en base (durable).
   */
  setSetting: function (tableId, setting, value) {
    try {
      const key = this.STORAGE_PREFIX + tableId + "_" + setting;
      localStorage.setItem(key, JSON.stringify(value));
    } catch (e) {
      console.warn("Erreur lors de la sauvegarde du paramètre DataTable:", e);
    }

    this.syncToServer(tableId, setting, value);
  },

  /**
   * Envoie la préférence au serveur pour un stockage durable en base.
   * Échec silencieux (log uniquement) : le localStorage reste la valeur
   * courante même si la synchronisation serveur échoue.
   */
  syncToServer: function (tableId, setting, value) {
    if (!window.BASE_URL) return;

    const key = this.STORAGE_PREFIX + tableId + "_" + setting;

    fetch(window.BASE_URL + "preferences/save", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": window.csrfToken || window.CSRF_TOKEN || "",
      },
      body: JSON.stringify({ key: key, value: value }),
    }).catch(function (e) {
      console.warn(
        "Impossible de synchroniser la préférence avec le serveur:",
        e,
      );
    });
  },

  /**
   * Récupère la configuration complète pour une table
   */
  getTableConfig: function (tableId) {
    return {
      pageLength: this.getSetting(tableId, "pageLength", 10),
      order: this.getSetting(tableId, "order", [[0, "asc"]]),
      search: this.getSetting(tableId, "search", ""),
      page: this.getSetting(tableId, "page", 0),
    };
  },

  /**
   * Sauvegarde la configuration complète d'une table
   */
  saveTableConfig: function (tableId, config) {
    if (config.pageLength !== undefined) {
      this.setSetting(tableId, "pageLength", config.pageLength);
    }
    if (config.order !== undefined) {
      this.setSetting(tableId, "order", config.order);
    }
    if (config.search !== undefined) {
      this.setSetting(tableId, "search", config.search);
    }
    if (config.page !== undefined) {
      this.setSetting(tableId, "page", config.page);
    }
  },

  /**
   * Efface toutes les configurations sauvegardées (localStorage uniquement)
   */
  clearAllSettings: function () {
    try {
      const keys = Object.keys(localStorage);
      keys.forEach((key) => {
        if (key.startsWith(this.STORAGE_PREFIX)) {
          localStorage.removeItem(key);
        }
      });
    } catch (e) {
      console.warn(
        "Erreur lors de la suppression des paramètres DataTable:",
        e,
      );
    }
  },

  /**
   * Efface la configuration d'une table spécifique (localStorage uniquement)
   */
  clearTableSettings: function (tableId) {
    try {
      const keys = Object.keys(localStorage);
      keys.forEach((key) => {
        if (key.startsWith(this.STORAGE_PREFIX + tableId + "_")) {
          localStorage.removeItem(key);
        }
      });
    } catch (e) {
      console.warn(
        "Erreur lors de la suppression des paramètres DataTable:",
        e,
      );
    }
  },
};
