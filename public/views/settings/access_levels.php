<?php
require_once __DIR__ . '/../../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user']) || !isAdmin()) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

setPageVariables('Paramétrage des niveaux d\'accès', 'settings');

// Définir la page courante pour le menu
$currentPage = 'settings';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">
                <i class="bi bi-layers me-2 me-1"></i>Paramétrage des niveaux d'accès
            </h4>
        </div>
        <div class="ms-auto p-2 bd-highlight">
            <button type="button" class="btn btn-success me-2" onclick="saveAccessLevelOrder()">
                <i class="bi bi-check-lg me-2 me-1"></i>Sauvegarder l'ordre
            </button>
            <a href="<?= BASE_URL ?>settings" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Retour aux paramètres
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <!-- Liste des niveaux d'accès -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-list me-2 me-1"></i>Niveaux d'accès existants
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="accessLevelsTable">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">Ordre</th>
                                    <th>Nom</th>
                                    <th>Description</th>
                                    <th>Contrats</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="sortableAccessLevels">
                                <?php foreach ($accessLevels as $level): ?>
                                    <tr data-id="<?= $level['id'] ?>" class="sortable-row">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-grip-vertical text-muted me-2" style="cursor: move;"></i>
                                                <span class="badge bg-secondary"><?= $level['ordre_affichage'] ?? 0 ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="view-mode">
                                                <strong><?= h($level['name']) ?></strong>
                                            </div>
                                            <div class="edit-mode" style="display: none;">
                                                <input type="text" class="form-control form-control-sm" value="<?= h($level['name']) ?>" data-original="<?= h($level['name']) ?>">
                                            </div>
                                        </td>
                                        <td>
                                            <div class="view-mode">
                                                <?= h($level['description']) ?>
                                            </div>
                                            <div class="edit-mode" style="display: none;">
                                                <input type="text" class="form-control form-control-sm" value="<?= h($level['description']) ?>" data-original="<?= h($level['description']) ?>">
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                            $contractCount = $accessLevelModel->getContractCountByAccessLevel($level['id']);
                                            if ($contractCount > 0): ?>
                                                <span class="badge bg-success">
                                                    <?= $contractCount ?> contrat(s)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Aucun contrat</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="view-mode d-flex gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-primary btn-action p-1 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" onclick="selectAccessLevel(<?= $level['id'] ?>)" title="Configurer">
                                                    <i class="bi bi-gear me-1"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-warning btn-action p-1 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" onclick="startEditAccessLevel(<?= $level['id'] ?>)" title="Modifier">
                                                    <i class="bi bi-pencil me-1"></i>
                                                </button>
                                                <?php if ($contractCount > 0): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-action p-1 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" disabled title="Impossible de supprimer - <?= $contractCount ?> contrat(s) utilisent ce niveau">
                                                        <i class="bi bi-trash me-1"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-action p-1 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" onclick="deleteAccessLevel(<?= $level['id'] ?>, '<?= addslashes($level['name']) ?>')" title="Supprimer">
                                                        <i class="bi bi-trash me-1"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            <div class="edit-mode d-flex gap-1" style="display: none;">
                                                <button type="button" class="btn btn-sm btn-outline-success btn-action p-1 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" onclick="saveAccessLevel(<?= $level['id'] ?>)" title="Sauvegarder">
                                                    <i class="bi bi-check me-1"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary btn-action p-1 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" onclick="cancelEditAccessLevel(<?= $level['id'] ?>)" title="Annuler">
                                                    <i class="bi bi-x-lg me-1"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Configuration des règles de visibilité -->
            <?php if ($selectedLevel): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-eye me-2 me-1"></i>Configuration des champs visibles pour le niveau 
                        <span class="text-primary">"<?= h($selectedLevel['name']) ?>"</span>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= BASE_URL ?>settings/saveAccessLevelVisibility">
                        <?= csrf_field() ?>
                        <input type="hidden" name="access_level_id" value="<?= $selectedLevel['id'] ?>">
                        <div class="row">
                            <?php foreach ($fields as $field => $info): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="fields[<?= $field ?>]" id="field_<?= $field ?>" value="1"
                                            <?= (isset($rules[$field]) && $rules[$field]) ? 'checked' : '' ?> >
                                        <label class="form-check-label" for="field_<?= $field ?>">
                                            <strong><?= h($info['label']) ?></strong>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="apply_to_existing" id="apply_to_existing" value="1">
                                <label class="form-check-label" for="apply_to_existing">
                                    <strong>Appliquer ces règles aux matériels existants</strong>
                                    <br>
                                    <small class="text-muted">Si coché, ces règles de visibilité seront appliquées à tous les matériels des contrats ayant ce niveau d'accès.</small>
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1 me-1"></i>Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="card mb-4">
                <div class="card-body text-center">
                    <p class="text-muted">Sélectionnez un niveau d'accès dans le tableau ci-dessus pour configurer ses règles de visibilité.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Création d'un nouveau niveau d'accès -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-plus me-2 me-1"></i>Créer un nouveau niveau d'accès
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= BASE_URL ?>settings/createAccessLevel">
                        <?= csrf_field() ?>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="name" class="form-label">Nom du niveau</label>
                                <input type="text" name="name" id="name" class="form-control bg-body text-body" placeholder="Ex: Premium" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="description" class="form-label">Description</label>
                                <input type="text" name="description" id="description" class="form-control bg-body text-body" placeholder="Ex: Accès complet à tous les champs" required>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-plus me-1 me-1"></i>Créer
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>



<!-- Modal de confirmation pour la suppression -->
<div class="modal fade" id="deleteAccessLevelModal" tabindex="-1" aria-labelledby="deleteAccessLevelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteAccessLevelModalLabel">Confirmer la suppression</h5>
                <button type="button" class="btn-close" onclick="closeModal('deleteAccessLevelModal')" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer le niveau d'accès "<span id="delete_access_level_name"></span>" ?</p>
                <p class="text-danger"><small>Cette action est irréversible et supprimera également toutes les règles de visibilité associées.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteAccessLevelModal')">Annuler</button>
                <form method="post" action="<?= BASE_URL ?>settings/deleteAccessLevel" style="display: inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" id="delete_access_level_id">
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?>

<style>
.edit-mode {
    display: none !important;
}
.edit-mode.show {
    display: block !important;
}
.edit-mode.d-flex.show {
    display: flex !important;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser le tri par glisser-déposer
    const tbody = document.getElementById('sortableAccessLevels');
    if (tbody) {
        new Sortable(tbody, {
            animation: 150,
            handle: '.bi-grip-vertical',
            onEnd: function() {
                updateAccessLevelOrderNumbers();
            }
        });
    }
    
    // Test de débogage pour vérifier les boutons d'édition
    console.log('Page chargée, vérification des boutons d\'édition...');
    const editButtons = document.querySelectorAll('[onclick*="editAccessLevel"]');
    console.log('Boutons d\'édition trouvés:', editButtons.length);
    
    // Ajouter un listener de clic pour déboguer
    editButtons.forEach((button, index) => {
        button.addEventListener('click', function(e) {
            console.log('Clic sur bouton d\'édition', index, e);
        });
    });
});

function updateAccessLevelOrderNumbers() {
    const rows = document.querySelectorAll('#sortableAccessLevels .sortable-row');
    rows.forEach((row, index) => {
        const badge = row.querySelector('.badge');
        if (badge) {
            badge.textContent = index + 1;
        }
    });
}

function saveAccessLevelOrder() {
    const rows = document.querySelectorAll('#sortableAccessLevels .sortable-row');
    const orders = {};
    
    rows.forEach((row, index) => {
        const id = row.getAttribute('data-id');
        orders[id] = index + 1;
    });

    // Envoyer les données au serveur
    fetch('<?= BASE_URL ?>settings/updateAccessLevelOrder', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': '<?= csrf_token() ?>',
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'orders=' + encodeURIComponent(JSON.stringify(orders))
    })
    .then(response => response.text())
    .then(() => {
        window.location.reload();
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de la sauvegarde de l\'ordre');
    });
}

function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Insérer l'alerte au début du conteneur principal
    const container = document.querySelector('.container-fluid');
    container.insertAdjacentHTML('afterbegin', alertHtml);
    
    // Auto-supprimer après 5 secondes
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if (alert) alert.remove();
    }, 5000);
}

function selectAccessLevel(id) {
    // Rediriger vers la page avec le niveau d'accès sélectionné
    window.location.href = '<?= BASE_URL ?>settings/accessLevels?access_level_id=' + id;
}

function startEditAccessLevel(id) {
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (!row) return;
    
    // Masquer le mode vue et afficher le mode édition
    row.querySelectorAll('.view-mode').forEach(el => el.style.display = 'none');
    row.querySelectorAll('.edit-mode').forEach(el => {
        el.style.display = 'none';
        el.classList.add('show');
    });
    
    // Focus sur le premier champ
    const firstInput = row.querySelector('.edit-mode input');
    if (firstInput) {
        firstInput.focus();
        firstInput.select();
    }
}

function saveAccessLevel(id) {
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (!row) return;
    
    const nameInput = row.querySelectorAll('.edit-mode input')[0];
    const descriptionInput = row.querySelectorAll('.edit-mode input')[1];
    
    if (!nameInput || !descriptionInput) return;
    
    const name = nameInput.value.trim();
    const description = descriptionInput.value.trim();
    
    if (!name) {
        alert('Le nom ne peut pas être vide');
        nameInput.focus();
        return;
    }
    
    // Envoyer les données au serveur
    const formData = new FormData();
    formData.append('id', id);
    formData.append('name', name);
    formData.append('description', description);
    
    fetch('<?= BASE_URL ?>settings/updateAccessLevel', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': '<?= csrf_token() ?>'
        },
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        // Mettre à jour l'affichage
        row.querySelector('.view-mode strong').textContent = name;
        row.querySelectorAll('.view-mode')[1].textContent = description;
        
        // Retourner au mode vue
        cancelEditAccessLevel(id);
        
        // Afficher un message de succès
        showAlert('success', 'Niveau d\'accès mis à jour avec succès');
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de la mise à jour');
    });
}

function cancelEditAccessLevel(id) {
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (!row) return;
    
    // Restaurer les valeurs originales
    const inputs = row.querySelectorAll('.edit-mode input');
    inputs.forEach(input => {
        input.value = input.getAttribute('data-original');
    });
    
    // Masquer le mode édition et afficher le mode vue
    row.querySelectorAll('.view-mode').forEach(el => el.style.display = 'block');
    row.querySelectorAll('.edit-mode').forEach(el => {
        el.style.display = 'none';
        el.classList.remove('show');
    });
}

function deleteAccessLevel(id, name) {
    console.log('deleteAccessLevel called with:', id, name);
    
    // Vérifier d'abord si le niveau d'accès peut être supprimé
    fetch('<?= BASE_URL ?>settings/checkAccessLevelDeletion?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.can_delete) {
                // Remplir les champs du formulaire
                document.getElementById('delete_access_level_id').value = id;
                document.getElementById('delete_access_level_name').textContent = name;
                
                // Essayer d'ouvrir le modal avec Bootstrap
                const modalElement = document.getElementById('deleteAccessLevelModal');
                if (modalElement) {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        const modal = new bootstrap.Modal(modalElement);
                        modal.show();
                    } else {
                        // Fallback si Bootstrap n'est pas disponible
                        modalElement.style.display = 'block';
                        modalElement.classList.add('show');
                        document.body.classList.add('modal-open');
                        
                        // Ajouter un backdrop
                        const backdrop = document.createElement('div');
                        backdrop.className = 'modal-backdrop fade show';
                        document.body.appendChild(backdrop);
                    }
                } else {
                    console.error('Modal element not found');
                    alert('Erreur: Modal non trouvé');
                }
            } else {
                alert('Impossible de supprimer ce niveau d\'accès car ' + data.contracts_count + ' contrat(s) l\'utilisent.');
            }
        })
        .catch(error => {
            console.error('Erreur lors de la vérification:', error);
            alert('Erreur lors de la vérification de la suppression');
        });
}

function closeModal(modalId) {
    const modalElement = document.getElementById(modalId);
    if (modalElement) {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        } else {
            // Fallback si Bootstrap n'est pas disponible
            modalElement.style.display = 'none';
            modalElement.classList.remove('show');
            document.body.classList.remove('modal-open');
            
            // Supprimer le backdrop
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
        }
    }
}
</script> 