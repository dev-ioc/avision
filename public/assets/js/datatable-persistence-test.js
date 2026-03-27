/**
 * Script de test pour la persistance DataTable
 * À utiliser dans la console du navigateur pour tester la fonctionnalité
 */

// Fonction pour tester la persistance
function testDataTablePersistence() {
  console.log('=== Test de la persistance DataTable ===');
  
  if (!window.DataTablePersistence) {
    console.error('❌ DataTablePersistence n\'est pas disponible');
    return;
  }
  
  console.log('✅ DataTablePersistence est disponible');
  
  // Test de sauvegarde
  console.log('📝 Test de sauvegarde...');
  window.DataTablePersistence.setSetting('testTable', 'pageLength', 25);
  window.DataTablePersistence.setSetting('testTable', 'order', [[1, 'desc']]);
  
  // Test de récupération
  console.log('📖 Test de récupération...');
  const pageLength = window.DataTablePersistence.getSetting('testTable', 'pageLength', 10);
  const order = window.DataTablePersistence.getSetting('testTable', 'order', [[0, 'asc']]);
  
  console.log('pageLength récupéré:', pageLength);
  console.log('order récupéré:', order);
  
  // Test de configuration complète
  console.log('🔧 Test de configuration complète...');
  const config = window.DataTablePersistence.getTableConfig('testTable');
  console.log('Configuration complète:', config);
  
  // Test de sauvegarde de configuration
  window.DataTablePersistence.saveTableConfig('testTable', {
    pageLength: 50,
    order: [[2, 'asc']],
    search: 'test',
    page: 2
  });
  
  const newConfig = window.DataTablePersistence.getTableConfig('testTable');
  console.log('Nouvelle configuration:', newConfig);
  
  // Nettoyage
  window.DataTablePersistence.clearTableSettings('testTable');
  console.log('🧹 Configuration de test supprimée');
  
  console.log('✅ Tests terminés');
}

// Fonction pour afficher toutes les configurations sauvegardées
function showAllDataTableSettings() {
  console.log('=== Configurations DataTable sauvegardées ===');
  
  if (!window.DataTablePersistence) {
    console.error('❌ DataTablePersistence n\'est pas disponible');
    return;
  }
  
  const keys = Object.keys(localStorage);
  const datatableKeys = keys.filter(key => key.startsWith(window.DataTablePersistence.STORAGE_PREFIX));
  
  if (datatableKeys.length === 0) {
    console.log('📭 Aucune configuration sauvegardée');
    return;
  }
  
  datatableKeys.forEach(key => {
    try {
      const value = JSON.parse(localStorage.getItem(key));
      console.log(`${key}:`, value);
    } catch (e) {
      console.log(`${key}:`, localStorage.getItem(key));
    }
  });
}

// Fonction pour effacer toutes les configurations
function clearAllDataTableSettings() {
  if (!window.DataTablePersistence) {
    console.error('❌ DataTablePersistence n\'est pas disponible');
    return;
  }
  
  window.DataTablePersistence.clearAllSettings();
  console.log('🧹 Toutes les configurations DataTable ont été supprimées');
}

// Exposer les fonctions globalement
window.testDataTablePersistence = testDataTablePersistence;
window.showAllDataTableSettings = showAllDataTableSettings;
window.clearAllDataTableSettings = clearAllDataTableSettings;

console.log('📋 Scripts de test DataTable chargés:');
console.log('- testDataTablePersistence() : Test complet de la persistance');
console.log('- showAllDataTableSettings() : Afficher toutes les configurations');
console.log('- clearAllDataTableSettings() : Effacer toutes les configurations'); 