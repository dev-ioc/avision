<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de confirmation pour l'import de matériel
 * Affiche les modifications qui vont être apportées et demande confirmation
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Confirmation Import - Matériel',
    'materiel'
);

// Définir la page courante pour le menu
$currentPage = 'materiel';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Récupérer les données depuis le contrôleur
$clients = $clients ?? [];
$sites = $sites ?? [];
$salles = $salles ?? [];

// Récupérer les paramètres de filtres pour pré-sélectionner
$selectedClientId = $_GET['client_id'] ?? '';
$selectedSiteId = $_GET['site_id'] ?? '';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête de page -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bi bi-exclamation-triangle me-2"></i>Confirmation Import - Matériel
            </h4>
            <p class="text-muted mb-0">
                Vérifiez les modifications qui vont être apportées avant de confirmer l'import
            </p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>materiel_bulk" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Retour
            </a>
        </div>
    </div>

    <!-- Messages d'alerte -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Résumé de l'import -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">
                        <i class="bi bi-info-circle me-2"></i>Résumé de l'import
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h5 class="text-primary"><?= $totalRows ?></h5>
                                <small class="text-muted">Lignes traitées</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h5 class="text-success"><?= count($validRows) ?></h5>
                                <small class="text-muted">Lignes valides</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h5 class="text-warning"><?= count($errors) ?></h5>
                                <small class="text-muted">Erreurs</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h5 class="text-info"><?= count($warnings) ?></h5>
                                <small class="text-muted">Avertissements</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Erreurs -->
    <?php if (!empty($errors)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h6 class="mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>Erreurs détectées
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li class="text-danger"><?= h($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Avertissements -->
    <?php if (!empty($warnings)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>Avertissements
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <?php foreach ($warnings as $warning): ?>
                                <li class="text-warning"><?= h($warning) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Nouvelles lignes (ajoutées automatiquement) -->
    <?php 
    $newRows = array_filter($validRows, function($row) {
        return ($row['comparison']['is_new'] ?? false);
    });
    ?>
    
    <?php if (!empty($newRows)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-success">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0">
                            <i class="bi bi-plus-circle me-2"></i>Nouvelles lignes (ajoutées automatiquement)
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Les lignes suivantes seront automatiquement ajoutées sans confirmation :
                        </p>
                        
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Marque</th>
                                        <th>Modèle</th>
                                        <th>Type</th>
                                        <th>Référence</th>
                                        <th>Salle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($newRows as $row): ?>
                                        <tr>
                                            <td><?= h($row['marque'] ?? '') ?></td>
                                            <td><?= h($row['modele'] ?? '') ?></td>
                                            <td><?= h($row['type_materiel'] ?? '') ?></td>
                                            <td><?= h($row['reference'] ?? '') ?></td>
                                            <td><?= h($row['salle_id'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Lignes à traiter (seulement les modifications, pas les nouvelles) -->
    <?php 
    $rowsToProcess = array_filter($validRows, function($row) {
        return ($row['comparison']['has_changes'] ?? false) && !($row['comparison']['is_new'] ?? false);
    });
    ?>
    
    <?php if (!empty($rowsToProcess)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0">
                            <i class="bi bi-pencil-square me-2"></i>Lignes à traiter
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Sélectionnez les modifications que vous souhaitez appliquer. Les nouvelles lignes seront automatiquement ajoutées.
                        </p>
                        
                        <form action="<?= BASE_URL ?>materiel_bulk/process_bulk_import" method="POST" id="confirmForm">
                            <?= csrf_field() ?>
                            <?php foreach ($rowsToProcess as $index => $row): ?>
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0">
                                                <span class="badge bg-info me-2">MODIFICATION</span>
                                                Matériel ID: <?= h($row['id_materiel']) ?>
                                                <?php if (!empty($row['marque']) || !empty($row['modele'])): ?>
                                                    - <?= h($row['marque'] . ' ' . $row['modele']) ?>
                                                <?php endif; ?>
                                            </h6>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" 
                                                       id="selectAll_<?= $index ?>" 
                                                       onchange="toggleAllFields(<?= $index ?>)"
                                                       checked>
                                                <label class="form-check-label fw-bold" for="selectAll_<?= $index ?>">
                                                    Tout sélectionner
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($row['comparison']['changes'])): ?>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th width="50">✓</th>
                                                            <th>Champ</th>
                                                            <th>Valeur actuelle</th>
                                                            <th>Nouvelle valeur</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($row['comparison']['changes'] as $field => $change): ?>
                                                            <tr>
                                                                <td>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input field-checkbox" 
                                                                               type="checkbox" 
                                                                               name="confirm_fields[<?= $row['id_materiel'] ?>][<?= $field ?>]"
                                                                               value="1"
                                                                               id="field_<?= $index ?>_<?= $field ?>"
                                                                               data-row="<?= $index ?>"
                                                                               checked>
                                                                    </div>
                                                                </td>
                                                                <td class="fw-bold"><?= h($field) ?></td>
                                                                <td>
                                                                    <span class="badge bg-secondary">
                                                                        <?= h($change['current'] ?: 'Vide') ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-primary">
                                                                        <?= h($change['new'] ?: 'Vide') ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <?php if ($change['action'] === 'null'): ?>
                                                                        <span class="badge bg-warning">Vider</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-info">Modifier</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Stocker les données de la ligne -->
                                    <input type="hidden" name="row_data[<?= $index ?>]" value="<?= h(json_encode($row)) ?>">
                                </div>
                            <?php endforeach; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Prêt à importer</h6>
                            <p class="text-muted mb-0">
                                <?php if (empty($errors)): ?>
                                    Cliquez sur "Confirmer l'import" pour procéder à l'importation.
                                <?php else: ?>
                                    Corrigez les erreurs avant de pouvoir importer.
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <?php if (empty($errors)): ?>
                                <button type="submit" form="confirmForm" class="btn btn-success">
                                    <i class="bi bi-check-circle me-2"></i>Confirmer les modifications sélectionnées
                                </button>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>materiel_bulk" class="btn btn-secondary">
                                <i class="bi bi-x-circle me-2"></i>Annuler
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Fonction pour sélectionner/désélectionner tous les champs d'un matériel
function toggleAllFields(rowIndex) {
    const selectAllCheckbox = document.getElementById('selectAll_' + rowIndex);
    const fieldCheckboxes = document.querySelectorAll('input[data-row="' + rowIndex + '"]');
    
    fieldCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    updateSelectedCount();
}

// Fonction pour mettre à jour l'état du "Tout sélectionner" quand un champ individuel change
function updateSelectAllState(rowIndex) {
    const selectAllCheckbox = document.getElementById('selectAll_' + rowIndex);
    const rowFieldCheckboxes = document.querySelectorAll('input[data-row="' + rowIndex + '"]');
    
    // Vérifier si tous les champs de cette ligne sont cochés
    const allChecked = Array.from(rowFieldCheckboxes).every(cb => cb.checked);
    const someChecked = Array.from(rowFieldCheckboxes).some(cb => cb.checked);
    
    selectAllCheckbox.checked = allChecked;
    selectAllCheckbox.indeterminate = someChecked && !allChecked;
}

// Compter les champs sélectionnés
function updateSelectedCount() {
    const fieldCheckboxes = document.querySelectorAll('.field-checkbox');
    const checked = document.querySelectorAll('.field-checkbox:checked');
    
    const countElement = document.getElementById('selectedCount');
    if (countElement) {
        countElement.textContent = checked.length + ' / ' + fieldCheckboxes.length;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Mettre à jour le compteur quand une checkbox change
    const fieldCheckboxes = document.querySelectorAll('.field-checkbox');
    fieldCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const rowIndex = this.getAttribute('data-row');
            updateSelectAllState(rowIndex);
            updateSelectedCount();
        });
    });
    
    // Initialiser l'état des "Tout sélectionner" au chargement
    const selectAllCheckboxes = document.querySelectorAll('input[id^="selectAll_"]');
    selectAllCheckboxes.forEach(checkbox => {
        const rowIndex = checkbox.id.replace('selectAll_', '');
        updateSelectAllState(rowIndex);
    });
    
    // Initialiser le compteur
    updateSelectedCount();
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>