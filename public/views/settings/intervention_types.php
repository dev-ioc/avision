<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/InterventionTypeModel.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user']) || !isAdmin()) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Initialiser le modèle
$interventionTypeModel = new InterventionTypeModel($db);

// Traitement des actions POST (AVANT d'inclure les headers)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = trim($_POST['name'] ?? '');
                $requiresTravel = isset($_POST['requires_travel']) ? 1 : 0;

                if (empty($name)) {
                    $_SESSION['error'] = 'Le nom du type d\'intervention est requis.';
                } elseif (strlen($name) > 50) {
                    $_SESSION['error'] = 'Le nom du type d\'intervention ne peut pas dépasser 50 caractères.';
                } elseif ($interventionTypeModel->nameExists($name)) {
                    $_SESSION['error'] = 'Un type d\'intervention avec ce nom existe déjà.';
                } else {
                    $data = ['name' => $name, 'requires_travel' => $requiresTravel];
                    if ($interventionTypeModel->create($data)) {
                        $_SESSION['success'] = 'Type d\'intervention créé avec succès.';
                    } else {
                        $_SESSION['error'] = 'Erreur lors de la création du type d\'intervention.';
                    }
                }
                break;

            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $requiresTravel = isset($_POST['requires_travel']) ? 1 : 0;

                if (empty($name)) {
                    $_SESSION['error'] = 'Le nom du type d\'intervention est requis.';
                } elseif (strlen($name) > 50) {
                    $_SESSION['error'] = 'Le nom du type d\'intervention ne peut pas dépasser 50 caractères.';
                } elseif ($interventionTypeModel->nameExists($name, $id)) {
                    $_SESSION['error'] = 'Un type d\'intervention avec ce nom existe déjà.';
                } else {
                    $data = ['name' => $name, 'requires_travel' => $requiresTravel];
                    if ($interventionTypeModel->update($id, $data)) {
                        $_SESSION['success'] = 'Type d\'intervention mis à jour avec succès.';
                    } else {
                        $_SESSION['error'] = 'Erreur lors de la mise à jour du type d\'intervention.';
                    }
                }
                break;

            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                $interventionType = $interventionTypeModel->getById($id);
                
                if (!$interventionType) {
                    $_SESSION['error'] = 'Type d\'intervention non trouvé.';
                } else {
                    $interventionCount = $interventionTypeModel->getInterventionCount($id);
                    if ($interventionCount > 0) {
                        $_SESSION['error'] = 'Impossible de supprimer ce type d\'intervention car ' . $interventionCount . ' intervention(s) l\'utilise(nt).';
                    } else {
                        if ($interventionTypeModel->delete($id)) {
                            $_SESSION['success'] = 'Type d\'intervention supprimé avec succès.';
                        } else {
                            $_SESSION['error'] = 'Erreur lors de la suppression du type d\'intervention.';
                        }
                    }
                }
                break;
        }
        
        // Rediriger pour éviter la soumission multiple du formulaire
        header('Location: ' . BASE_URL . 'settings/interventionTypes');
        exit;
    }
}

// Configuration de la page (APRÈS le traitement POST)
setPageVariables('Types d\'intervention', 'settings');

// Définir la page courante pour le menu
$currentPage = 'settings';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Récupérer tous les types d'intervention avec le nombre d'interventions
$interventionTypes = $interventionTypeModel->getAll();
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">
                <i class="bi bi-tools me-2 me-1"></i>Types d'intervention
            </h4>
        </div>
        <div class="ms-auto p-2 bd-highlight">
            <a href="<?= BASE_URL ?>settings" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2 me-1"></i>Retour aux paramètres
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-gear text-primary me-2 me-1"></i>
                        Configuration des types d'intervention
                    </h5>
                    <small class="text-muted">Gérer les types d'intervention et leurs paramètres de transport</small>
                </div>
                <div class="card-body">
                    <!-- Types d'intervention existants -->
                    <div class="mb-4">
                        <h6>Types d'intervention configurés :</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Transport</th>
                                        <th>Interventions utilisant ce type</th>
                                        <th>Date de création</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($interventionTypes)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">
                                                <i class="bi bi-info-circle me-2 me-1"></i>Aucun type d'intervention trouvé
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($interventionTypes as $type): ?>
                                        <tr>
                                            <td>
                                                <strong><?= h($type['name']) ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($type['requires_travel']): ?>
                                                    <span class="badge bg-warning">
                                                        <i class="bi bi-car-front me-1 me-1"></i>Transport requis
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-house me-1 me-1"></i>Sans transport
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($type['intervention_count'] > 0): ?>
                                                    <span class="badge bg-info">
                                                        <?= $type['intervention_count'] ?> intervention(s)
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Aucune intervention</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= date('d/m/Y H:i', strtotime($type['created_at'])) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-warning" 
                                                            onclick="editType(<?= $type['id'] ?>, '<?= h($type['name']) ?>', <?= $type['requires_travel'] ? 'true' : 'false' ?>)" 
                                                            title="Modifier">
                                                        <i class="bi bi-pencil me-1"></i>
                                                    </button>
                                                    <?php if ($type['intervention_count'] == 0): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="deleteType(<?= $type['id'] ?>, '<?= h($type['name']) ?>')" 
                                                                title="Supprimer">
                                                            <i class="bi bi-trash me-1"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                                title="Impossible de supprimer - utilisé par des interventions"
                                                                disabled>
                                                            <i class="bi bi-lock me-1"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Ajouter un type d'intervention -->
                    <div class="mb-4">
                        <h6>Ajouter un type d'intervention :</h6>
                        <form method="POST">
                       <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Nom du type</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           placeholder="ex: Maintenance préventive" maxlength="50" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Transport</label>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="requires_travel" name="requires_travel">
                                        <label class="form-check-label" for="requires_travel">
                                            <i class="bi bi-car-front me-1 me-1"></i>Transport requis
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-plus me-2 me-1"></i>Ajouter
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un type d'intervention -->
<div class="modal fade" id="editTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le type d'intervention</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_type_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Nom du type</label>
                        <input type="text" class="form-control" id="edit_name" name="name" 
                               placeholder="ex: Maintenance préventive" maxlength="50" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_requires_travel" name="requires_travel">
                            <label class="form-check-label" for="edit_requires_travel">
                                <i class="bi bi-car-front me-1 me-1"></i>Transport requis
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editType(id, name, requiresTravel) {
    document.getElementById('edit_type_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_requires_travel').checked = requiresTravel;
    
    const modal = new bootstrap.Modal(document.getElementById('editTypeModal'));
    modal.show();
}

function deleteType(id, name) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer définitivement le type d\'intervention "' + name + '" ?\n\nCette action ne peut pas être annulée.')) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 