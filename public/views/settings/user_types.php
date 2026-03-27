<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/UserTypeModel.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user']) || !isAdmin()) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Initialiser le modèle
$userTypeModel = new UserTypeModel($db);

// Traitement des actions POST (AVANT d'inclure les headers)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $group_id = (int)($_POST['group_id'] ?? 0);

                if (empty($name)) {
                    $_SESSION['error'] = 'Le nom du type d\'utilisateur est requis.';
                } elseif (strlen($name) > 50) {
                    $_SESSION['error'] = 'Le nom du type d\'utilisateur ne peut pas dépasser 50 caractères.';
                } elseif (strlen($description) > 255) {
                    $_SESSION['error'] = 'La description ne peut pas dépasser 255 caractères.';
                } elseif ($group_id <= 0) {
                    $_SESSION['error'] = 'Le groupe est obligatoire.';
                } elseif ($userTypeModel->nameExists($name)) {
                    $_SESSION['error'] = 'Un type d\'utilisateur avec ce nom existe déjà.';
                } else {
                    $data = ['name' => $name, 'description' => $description, 'group_id' => $group_id];
                    if ($userTypeModel->create($data)) {
                        $_SESSION['success'] = 'Type d\'utilisateur créé avec succès.';
                    } else {
                        $_SESSION['error'] = 'Erreur lors de la création du type d\'utilisateur.';
                    }
                }
                break;

            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $group_id = (int)($_POST['group_id'] ?? 0);

                if (empty($name)) {
                    $_SESSION['error'] = 'Le nom du type d\'utilisateur est requis.';
                } elseif (strlen($name) > 50) {
                    $_SESSION['error'] = 'Le nom du type d\'utilisateur ne peut pas dépasser 50 caractères.';
                } elseif (strlen($description) > 255) {
                    $_SESSION['error'] = 'La description ne peut pas dépasser 255 caractères.';
                } elseif ($group_id <= 0) {
                    $_SESSION['error'] = 'Le groupe est obligatoire.';
                } elseif ($userTypeModel->nameExists($name, $id)) {
                    $_SESSION['error'] = 'Un type d\'utilisateur avec ce nom existe déjà.';
                } else {
                    $data = ['name' => $name, 'description' => $description, 'group_id' => $group_id];
                    if ($userTypeModel->update($id, $data)) {
                        $_SESSION['success'] = 'Type d\'utilisateur mis à jour avec succès.';
                    } else {
                        $_SESSION['error'] = 'Erreur lors de la mise à jour du type d\'utilisateur.';
                    }
                }
                break;

            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                $userType = $userTypeModel->getById($id);
                
                if (!$userType) {
                    $_SESSION['error'] = 'Type d\'utilisateur non trouvé.';
                } else {
                    $userCount = $userTypeModel->getUserCount($id);
                    if ($userCount > 0) {
                        $_SESSION['error'] = 'Impossible de supprimer ce type d\'utilisateur car ' . $userCount . ' utilisateur(s) l\'utilise(nt).';
                    } else {
                        if ($userTypeModel->delete($id)) {
                            $_SESSION['success'] = 'Type d\'utilisateur supprimé avec succès.';
                        } else {
                            $_SESSION['error'] = 'Erreur lors de la suppression du type d\'utilisateur.';
                        }
                    }
                }
                break;
        }
        
        // Rediriger pour éviter la soumission multiple du formulaire
        header('Location: ' . BASE_URL . 'settings/userTypes');
        exit;
    }
}

// Configuration de la page (APRÈS le traitement POST)
setPageVariables('Types d\'utilisateur', 'settings');

// Définir la page courante pour le menu
$currentPage = 'settings';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Récupérer tous les types d'utilisateur
$userTypes = $userTypeModel->getAll();

// Ajouter le nombre d'utilisateurs pour chaque type
foreach ($userTypes as $key => $type) {
    $userTypes[$key]['user_count'] = $userTypeModel->getUserCount($type['id']);
}

// Récupérer tous les groupes pour le formulaire
$userGroups = $userTypeModel->getAllGroups();
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">
                <i class="bi bi-people me-2 me-1"></i>Types d'utilisateur
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
                        Configuration des types d'utilisateur
                    </h5>
                    <small class="text-muted">Gérer les types d'utilisateur et leurs descriptions</small>
                </div>
                <div class="card-body">
                    <!-- Types d'utilisateur existants -->
                    <div class="mb-4">
                        <h6>Types d'utilisateur configurés :</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Description</th>
                                        <th>Groupe</th>
                                        <th>Utilisateurs</th>
                                        <th>Date de création</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($userTypes)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">
                                                <i class="bi bi-info-circle me-2 me-1"></i>Aucun type d'utilisateur trouvé
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($userTypes as $type): ?>
                                        <tr>
                                            <td>
                                                <strong><?= h($type['name']) ?></strong>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($type['description'] ?? 'Aucune description') ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $groupName = '';
                                                $groupId = $type['group_id'] ?? null;
                                                if ($groupId) {
                                                    foreach ($userGroups as $group) {
                                                        if ($group['id'] == $groupId) {
                                                            $groupName = $group['name'];
                                                            break;
                                                        }
                                                    }
                                                }
                                                ?>
                                                <?php if ($groupName): ?>
                                                    <span class="badge bg-<?= $groupName === 'Staff' ? 'primary' : 'success' ?>">
                                                        <?= h($groupName) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Non défini</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($type['user_count'] > 0): ?>
                                                    <span class="badge bg-info">
                                                        <?= $type['user_count'] ?> utilisateur(s)
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Aucun utilisateur</span>
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
                                                            onclick="editType(<?= $type['id'] ?>, '<?= h($type['name']) ?>', '<?= htmlspecialchars($type['description'] ?? '') ?>', <?= $type['group_id'] ?? 0 ?>)" 
                                                            title="Modifier">
                                                        <i class="bi bi-pencil me-1"></i>
                                                    </button>
                                                    <?php if ($type['user_count'] == 0): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="deleteType(<?= $type['id'] ?>, '<?= h($type['name']) ?>')" 
                                                                title="Supprimer">
                                                            <i class="bi bi-trash me-1"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                                title="Impossible de supprimer - utilisé par des utilisateurs"
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
                    
                    <!-- Ajouter un type d'utilisateur -->
                    <div class="mb-4">
                        <h6>Ajouter un type d'utilisateur :</h6>
                        <form method="POST">
                     <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <div class="row">
                                <div class="col-md-3">
                                    <label for="name" class="form-label">Nom du type</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           placeholder="ex: Administrateur" maxlength="50" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="description" class="form-label">Description</label>
                                    <input type="text" class="form-control" id="description" name="description" 
                                           placeholder="ex: Accès complet à toutes les fonctionnalités" maxlength="255">
                                </div>
                                <div class="col-md-3">
                                    <label for="group_id" class="form-label">Groupe *</label>
                                    <select class="form-select" id="group_id" name="group_id" required>
                                        <option value="">Sélectionner un groupe</option>
                                        <?php foreach ($userGroups as $group): ?>
                                            <option value="<?= $group['id'] ?>"><?= h($group['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
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

<!-- Modal pour modifier un type d'utilisateur -->
<div class="modal fade" id="editTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le type d'utilisateur</h5>
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
                               placeholder="ex: Administrateur" maxlength="50" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <input type="text" class="form-control" id="edit_description" name="description" 
                               placeholder="ex: Accès complet à toutes les fonctionnalités" maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label for="edit_group_id" class="form-label">Groupe *</label>
                        <select class="form-select" id="edit_group_id" name="group_id" required>
                            <option value="">Sélectionner un groupe</option>
                            <?php foreach ($userGroups as $group): ?>
                                <option value="<?= $group['id'] ?>"><?= h($group['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
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
function editType(id, name, description, groupId) {
    document.getElementById('edit_type_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_group_id').value = groupId;
    
    const modal = new bootstrap.Modal(document.getElementById('editTypeModal'));
    modal.show();
}

function deleteType(id, name) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer définitivement le type d\'utilisateur "' + name + '" ?\n\nCette action ne peut pas être annulée.')) {
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