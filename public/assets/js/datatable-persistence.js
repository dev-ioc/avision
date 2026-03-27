/**
 * DataTable Persistence Utility
 * Gère la persistance des configurations DataTable (pageLength, etc.)
 */

window.DataTablePersistence = {
  /**
   * Clé de base pour localStorage
   */
  STORAGE_PREFIX: 'datatable_',

  /**
   * Récupère la configuration sauvegardée pour une table spécifique
   * @param {string} tableId - ID de la table
   * @param {string} setting - Nom du paramètre (pageLength, order, etc.)
   * @param {*} defaultValue - Valeur par défaut si aucune sauvegarde
   * @returns {*} Valeur sauvegardée ou valeur par défaut
   */
  getSetting: function(tableId, setting, defaultValue) {
    try {
      const key = this.STORAGE_PREFIX + tableId + '_' + setting;
      const stored = localStorage.getItem(key);
      return stored !== null ? JSON.parse(stored) : defaultValue;
    } catch (e) {
      console.warn('Erreur lors de la récupération du paramètre DataTable:', e);
      return defaultValue;
    }
  },

  /**
   * Sauvegarde une configuration pour une table spécifique
   * @param {string} tableId - ID de la table
   * @param {string} setting - Nom du paramètre
   * @param {*} value - Valeur à sauvegarder
   */
  setSetting: function(tableId, setting, value) {
    try {
      const key = this.STORAGE_PREFIX + tableId + '_' + setting;
      localStorage.setItem(key, JSON.stringify(value));
    } catch (e) {
      console.warn('Erreur lors de la sauvegarde du paramètre DataTable:', e);
    }
  },

  /**
   * Récupère la configuration complète pour une table
   * @param {string} tableId - ID de la table
   * @returns {object} Configuration complète
   */
  getTableConfig: function(tableId) {
    return {
      pageLength: this.getSetting(tableId, 'pageLength', 10),
      order: this.getSetting(tableId, 'order', [[0, 'asc']]),
      search: this.getSetting(tableId, 'search', ''),
      page: this.getSetting(tableId, 'page', 0)
    };
  },

  /**
   * Sauvegarde la configuration complète d'une table
   * @param {string} tableId - ID de la table
   * @param {object} config - Configuration à sauvegarder
   */
  saveTableConfig: function(tableId, config) {
    if (config.pageLength !== undefined) {
      this.setSetting(tableId, 'pageLength', config.pageLength);
    }
    if (config.order !== undefined) {
      this.setSetting(tableId, 'order', config.order);
    }
    if (config.search !== undefined) {
      this.setSetting(tableId, 'search', config.search);
    }
    if (config.page !== undefined) {
      this.setSetting(tableId, 'page', config.page);
    }
  },

  /**
   * Efface toutes les configurations sauvegardées
   */
  clearAllSettings: function() {
    try {
      const keys = Object.keys(localStorage);
      keys.forEach(key => {
        if (key.startsWith(this.STORAGE_PREFIX)) {
          localStorage.removeItem(key);
        }
      });
    } catch (e) {
      console.warn('Erreur lors de la suppression des paramètres DataTable:', e);
    }
  },

  /**
   * Efface la configuration d'une table spécifique
   * @param {string} tableId - ID de la table
   */
  clearTableSettings: function(tableId) {
    try {
      const keys = Object.keys(localStorage);
      keys.forEach(key => {
        if (key.startsWith(this.STORAGE_PREFIX + tableId + '_')) {
          localStorage.removeItem(key);
        }
      });
    } catch (e) {
      console.warn('Erreur lors de la suppression des paramètres DataTable:', e);
    }
  }
}; 