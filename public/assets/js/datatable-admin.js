/**
 * Script d'administration pour les configurations DataTable
 * Permet de gérer et visualiser les configurations sauvegardées
 */

window.DataTableAdmin = {
  /**
   * Affiche un modal avec toutes les configurations sauvegardées
   */
  showSettingsModal: function() {
    if (!window.DataTablePersistence) {
      alert('DataTablePersistence n\'est pas disponible');
      return;
    }

    const keys = Object.keys(localStorage);
    const datatableKeys = keys.filter(key => key.startsWith(window.DataTablePersistence.STORAGE_PREFIX));
    
    let modalContent = `
      <div class="modal fade" id="datatableSettingsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Configurations DataTable sauvegardées</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
    `;

    if (datatableKeys.length === 0) {
      modalContent += '<p class="text-muted">Aucune configuration sauvegardée</p>';
    } else {
      modalContent += '<div class="table-responsive"><table class="table table-sm">';
      modalContent += '<thead><tr><th>Clé</th><th>Valeur</th><th>Actions</th></tr></thead><tbody>';
      
      datatableKeys.forEach(key => {
        try {
          const value = JSON.parse(localStorage.getItem(key));
          const displayValue = typeof value === 'object' ? JSON.stringify(value, null, 2) : value;
          modalContent += `
            <tr>
              <td><code>${key}</code></td>
              <td><pre class="mb-0">${displayValue}</pre></td>
              <td>
                <button class="btn btn-sm btn-outline-danger" onclick="DataTableAdmin.deleteSetting('${key}')">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
          `;
        } catch (e) {
          modalContent += `
            <tr>
              <td><code>${key}</code></td>
              <td><span class="text-danger">Erreur de parsing</span></td>
              <td>
                <button class="btn btn-sm btn-outline-danger" onclick="DataTableAdmin.deleteSetting('${key}')">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
          `;
        }
      });
      
      modalContent += '</tbody></table></div>';
    }

    modalContent += `
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
              <button type="button" class="btn btn-danger" onclick="DataTableAdmin.clearAllSettings()">
                <i class="bi bi-trash"></i> Tout effacer
              </button>
            </div>
          </div>
        </div>
      </div>
    `;

    // Supprimer l'ancien modal s'il existe
    const existingModal = document.getElementById('datatableSettingsModal');
    if (existingModal) {
      existingModal.remove();
    }

    // Ajouter le modal au DOM
    document.body.insertAdjacentHTML('beforeend', modalContent);

    // Afficher le modal
    const modal = new bootstrap.Modal(document.getElementById('datatableSettingsModal'));
    modal.show();
  },

  /**
   * Supprime un paramètre spécifique
   */
  deleteSetting: function(key) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer "${key}" ?`)) {
      localStorage.removeItem(key);
      this.showSettingsModal(); // Recharger le modal
    }
  },

  /**
   * Efface toutes les configurations
   */
  clearAllSettings: function() {
    if (confirm('Êtes-vous sûr de vouloir supprimer toutes les configurations DataTable ?')) {
      if (window.DataTablePersistence) {
        window.DataTablePersistence.clearAllSettings();
      }
      this.showSettingsModal(); // Recharger le modal
    }
  },

  /**
   * Ajoute un bouton d'administration dans la navbar (optionnel)
   */
  addAdminButton: function() {
    // Chercher la navbar
    const navbar = document.querySelector('.navbar-nav');
    if (!navbar) return;

    // Vérifier si le bouton existe déjà
    if (document.getElementById('datatableAdminBtn')) return;

    const adminButton = document.createElement('li');
    adminButton.className = 'nav-item';
    adminButton.innerHTML = `
      <button class="btn btn-outline-secondary btn-sm" 
              onclick="DataTableAdmin.showSettingsModal()" 
              title="Gérer les configurations DataTable">
        <i class="bi bi-gear"></i>
      </button>
    `;

    navbar.appendChild(adminButton);
  }
};

// Ajouter le bouton d'administration si l'utilisateur est admin
document.addEventListener('DOMContentLoaded', function() {
  // Vérifier si l'utilisateur est admin (à adapter selon votre logique)
  const isAdmin = document.body.hasAttribute('data-user-type') && 
                  document.body.getAttribute('data-user-type') === 'admin';
  
  if (isAdmin) {
    DataTableAdmin.addAdminButton();
  }
});

console.log('📊 Script d\'administration DataTable chargé');
console.log('- DataTableAdmin.showSettingsModal() : Afficher le modal de gestion');
console.log('- DataTableAdmin.clearAllSettings() : Effacer toutes les configurations'); 