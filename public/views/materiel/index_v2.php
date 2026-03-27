<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de la liste du matériel V2
 * Affiche la liste du matériel regroupé par site/salle avec filtres
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Matériel V2',
    'materiel_v2'
);

// Définir la page courante pour le menu
$currentPage = 'materiel_v2';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Récupérer les données depuis le contrôleur
$materiel_list = $materiel_list ?? [];
$clients = $clients ?? [];
$sites = $sites ?? [];
$salles = $salles ?? [];
$visibilites_champs = $visibilites_champs ?? [];
$pieces_jointes_count = $pieces_jointes_count ?? [];
$filters = $filters ?? [];

// Initialiser les données Tabulator (sera rempli si on a des données)
$tabulator_data = [];

// Récupérer les statistiques
$stats = [];
if (isset($materielModel)) {
    $stats = $materielModel->getStats();
}

// Organiser le matériel par client/site/salle
$materiel_organise = [];
foreach ($materiel_list as $materiel) {
    $client_id = $materiel['client_nom'] ?? 'Sans client';
    $site_id = $materiel['site_nom'] ?? 'Sans site';
    $salle_id = $materiel['salle_nom'] ?? 'Sans salle';
    
    if (!isset($materiel_organise[$client_id])) {
        $materiel_organise[$client_id] = [];
    }
    if (!isset($materiel_organise[$client_id][$site_id])) {
        $materiel_organise[$client_id][$site_id] = [];
    }
    if (!isset($materiel_organise[$client_id][$site_id][$salle_id])) {
        $materiel_organise[$client_id][$site_id][$salle_id] = [];
    }
    
    $materiel_organise[$client_id][$site_id][$salle_id][] = $materiel;
}
?>

<!-- Tabulator CSS -->
<link href="https://unpkg.com/tabulator-tables@6.2.5/dist/css/tabulator_bootstrap5.min.css" rel="stylesheet">

<style>
/* Styles généraux pour Tabulator */
#materiel-tabulator-table {
    width: 100%;
    border-radius: 0.375rem;
    overflow: hidden;
}

/* Adaptation au thème sombre */
[data-bs-theme="dark"] #materiel-tabulator-table,
[data-bs-theme="dark"] .tabulator {
    background-color: var(--bs-body-bg) !important;
    color: var(--bs-body-color) !important;
}

/* En-tête du tableau */
.tabulator .tabulator-header {
    background-color: var(--bs-secondary-bg) !important;
    border-bottom: 2px solid var(--bs-border-color) !important;
    font-weight: 600;
    font-size: 0.875rem !important;
}

.tabulator .tabulator-col {
    padding: 0.5rem 0.75rem !important;
}

[data-bs-theme="dark"] .tabulator .tabulator-header {
    background-color: rgba(var(--bs-secondary-bg-rgb), 0.5) !important;
    border-bottom-color: var(--bs-border-color) !important;
}

.tabulator .tabulator-col {
    background-color: transparent !important;
    border-right: 1px solid var(--bs-border-color) !important;
    color: var(--bs-body-color) !important;
    padding: 0.5rem 0.75rem !important;
}

[data-bs-theme="dark"] .tabulator .tabulator-col {
    border-right-color: rgba(var(--bs-border-color-rgb), 0.3) !important;
}

.tabulator .tabulator-col:hover {
    background-color: var(--bs-tertiary-bg) !important;
}

[data-bs-theme="dark"] .tabulator .tabulator-col:hover {
    background-color: rgba(var(--bs-tertiary-bg-rgb), 0.3) !important;
}

/* Corps du tableau */
.tabulator .tabulator-tableHolder {
    background-color: var(--bs-body-bg) !important;
}

.tabulator .tabulator-row {
    cursor: pointer;
    border-bottom: 1px solid var(--bs-border-color) !important;
    background-color: var(--bs-body-bg) !important;
    color: var(--bs-body-color) !important;
    transition: background-color 0.15s ease-in-out;
}

[data-bs-theme="dark"] .tabulator .tabulator-row {
    border-bottom-color: rgba(var(--bs-border-color-rgb), 0.2) !important;
    background-color: var(--bs-body-bg) !important;
    color: var(--bs-body-color) !important;
}

.tabulator .tabulator-row:nth-child(even) {
    background-color: var(--bs-secondary-bg) !important;
}

/* Pas d'alternance en mode sombre pour meilleure lisibilité */
[data-bs-theme="dark"] .tabulator .tabulator-row:nth-child(even) {
    background-color: var(--bs-body-bg) !important;
}

.tabulator .tabulator-row:hover {
    background-color: var(--bs-primary-bg-subtle) !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

[data-bs-theme="dark"] .tabulator .tabulator-row:hover {
    background-color: #2a2d3a !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.tabulator .tabulator-row.tabulator-selected {
    background-color: var(--bs-primary-bg-subtle) !important;
}

[data-bs-theme="dark"] .tabulator .tabulator-row.tabulator-selected {
    background-color: rgba(var(--bs-primary-rgb), 0.3) !important;
}

/* Cellules */
.tabulator .tabulator-cell {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    border-right: 1px solid var(--bs-border-color) !important;
    padding: 0.4rem 0.75rem !important;
    color: var(--bs-body-color) !important;
    font-size: 0.875rem !important;
    line-height: 1.4 !important;
}

[data-bs-theme="dark"] .tabulator .tabulator-cell {
    border-right-color: rgba(var(--bs-border-color-rgb), 0.2) !important;
}

/* Avertissement de visibilité */
.visibility-warning {
    background-color: var(--bs-warning-bg-subtle) !important;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    display: inline-block;
}

[data-bs-theme="dark"] .visibility-warning {
    background-color: rgba(var(--bs-warning-rgb), 0.2) !important;
    color: var(--bs-warning-text-emphasis) !important;
}

/* Style pour les cellules avec données masquées au client */
.tabulator .tabulator-cell.hidden-to-client {
    background-color: rgba(var(--bs-warning-rgb), 0.2) !important;
    position: relative;
}

[data-bs-theme="dark"] .tabulator .tabulator-cell.hidden-to-client {
    background-color: rgba(var(--bs-warning-rgb), 0.3) !important;
}

/* S'assurer que le texte dans les cellules masquées garde sa couleur normale */
.tabulator .tabulator-cell.hidden-to-client,
.tabulator .tabulator-cell.hidden-to-client * {
    color: var(--bs-body-color) !important;
}

.tabulator .tabulator-cell.hidden-to-client::after {
    content: '👁️';
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
    opacity: 0.5;
    font-size: 0.85em;
    pointer-events: none;
}

/* Scrollbar personnalisée */
.tabulator .tabulator-tableHolder::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.tabulator .tabulator-tableHolder::-webkit-scrollbar-track {
    background: var(--bs-secondary-bg);
    border-radius: 4px;
}

[data-bs-theme="dark"] .tabulator .tabulator-tableHolder::-webkit-scrollbar-track {
    background: rgba(var(--bs-secondary-bg-rgb), 0.3);
}

.tabulator .tabulator-tableHolder::-webkit-scrollbar-thumb {
    background: var(--bs-border-color);
    border-radius: 4px;
}

.tabulator .tabulator-tableHolder::-webkit-scrollbar-thumb:hover {
    background: var(--bs-secondary-color);
}

[data-bs-theme="dark"] .tabulator .tabulator-tableHolder::-webkit-scrollbar-thumb {
    background: rgba(var(--bs-border-color-rgb), 0.5);
}

[data-bs-theme="dark"] .tabulator .tabulator-tableHolder::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--bs-border-color-rgb), 0.7);
}

/* Styles pour les icônes dans les cellules */
.tabulator .tabulator-cell i {
    margin-right: 0.5rem;
    opacity: 0.8;
}

[data-bs-theme="dark"] .tabulator .tabulator-cell i {
    opacity: 0.9;
}

/* Amélioration de la carte contenant le tableau */
#tabulator-card {
    border: 1px solid var(--bs-border-color) !important;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

[data-bs-theme="dark"] #tabulator-card {
    border-color: rgba(var(--bs-border-color-rgb), 0.3) !important;
    box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.3);
    background-color: var(--bs-body-bg) !important;
}

#tabulator-card .card-header {
    background-color: var(--bs-secondary-bg) !important;
    border-bottom: 1px solid var(--bs-border-color) !important;
    padding: 1rem 1.5rem;
}

[data-bs-theme="dark"] #tabulator-card .card-header {
    background-color: rgba(var(--bs-secondary-bg-rgb), 0.3) !important;
    border-bottom-color: rgba(var(--bs-border-color-rgb), 0.2) !important;
}

/* Bouton de colonnes */
#toggleColumnMenu {
    transition: all 0.2s ease-in-out;
}

#toggleColumnMenu:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

[data-bs-theme="dark"] #toggleColumnMenu:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

/* Animation pour les lignes */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.tabulator .tabulator-row {
    animation: fadeIn 0.3s ease-in-out;
}

/* Amélioration du texte dans les cellules */
.tabulator .tabulator-cell .fw-bold {
    font-weight: 600 !important;
}

.tabulator .tabulator-cell .text-muted {
    opacity: 0.7;
}

[data-bs-theme="dark"] .tabulator .tabulator-cell .text-muted {
    opacity: 0.6;
}

/* Styles pour les couleurs de statut (maintenance, garantie) */
.tabulator .tabulator-cell .text-danger {
    color: var(--bs-danger) !important;
    font-weight: 500;
}

.tabulator .tabulator-cell .text-warning {
    color: var(--bs-warning-text-emphasis) !important;
    font-weight: 500;
}

.tabulator .tabulator-cell .text-success {
    color: var(--bs-success) !important;
    font-weight: 500;
}

[data-bs-theme="dark"] .tabulator .tabulator-cell .text-danger {
    color: var(--bs-danger-text-emphasis) !important;
}

[data-bs-theme="dark"] .tabulator .tabulator-cell .text-warning {
    color: var(--bs-warning-text-emphasis) !important;
}

[data-bs-theme="dark"] .tabulator .tabulator-cell .text-success {
    color: var(--bs-success-text-emphasis) !important;
}

/* Styles pour le modal de sélection des colonnes */
#columnMenuModal .modal-content {
    border: 1px solid var(--bs-border-color);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

[data-bs-theme="dark"] #columnMenuModal .modal-content {
    border-color: rgba(var(--bs-border-color-rgb), 0.3);
    box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.5);
    background-color: var(--bs-body-bg);
}

#columnMenuModal .modal-header {
    border-bottom: 1px solid var(--bs-border-color);
    background-color: var(--bs-secondary-bg);
}

[data-bs-theme="dark"] #columnMenuModal .modal-header {
    border-bottom-color: rgba(var(--bs-border-color-rgb), 0.2);
    background-color: rgba(var(--bs-secondary-bg-rgb), 0.3);
}

#columnMenuModal .modal-body {
    max-height: 60vh;
    overflow-y: auto;
}

#columnMenuModal .form-check {
    padding: 0.75rem 1rem;
    border-radius: 0.375rem;
    transition: background-color 0.15s ease-in-out;
    margin-bottom: 0.25rem;
}

#columnMenuModal .form-check:hover {
    background-color: var(--bs-secondary-bg);
}

[data-bs-theme="dark"] #columnMenuModal .form-check:hover {
    background-color: rgba(var(--bs-secondary-bg-rgb), 0.2);
}

#columnMenuModal .form-check-label {
    cursor: pointer;
    user-select: none;
    font-weight: 500;
}

#columnMenuModal .form-check-input {
    cursor: pointer;
    margin-top: 0.375rem;
}

/* Amélioration des filtres */
.card.mb-4 {
    border: 1px solid var(--bs-border-color);
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

[data-bs-theme="dark"] .card.mb-4 {
    border-color: rgba(var(--bs-border-color-rgb), 0.3);
    box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.3);
    background-color: var(--bs-body-bg);
}

.card-header {
    background-color: var(--bs-secondary-bg);
    border-bottom: 1px solid var(--bs-border-color);
}

[data-bs-theme="dark"] .card-header {
    background-color: rgba(var(--bs-secondary-bg-rgb), 0.3);
    border-bottom-color: rgba(var(--bs-border-color-rgb), 0.2);
}

/* Amélioration des messages d'instruction */
.alert {
    border: 1px solid transparent;
    border-radius: 0.5rem;
}

[data-bs-theme="dark"] .alert-info {
    background-color: rgba(var(--bs-info-rgb), 0.15);
    border-color: rgba(var(--bs-info-rgb), 0.3);
    color: var(--bs-info-text-emphasis);
}

[data-bs-theme="dark"] .alert-warning {
    background-color: rgba(var(--bs-warning-rgb), 0.15);
    border-color: rgba(var(--bs-warning-rgb), 0.3);
    color: var(--bs-warning-text-emphasis);
}

/* Styles pour les cellules éditables */
.tabulator .tabulator-cell[tabulator-field]:not([tabulator-field="equipement"]):not([tabulator-field="maintenance_info"]):not([tabulator-field="client_nom"]):not([tabulator-field="site_nom"]):not([tabulator-field="salle_nom"]):not([tabulator-field="id"]):not([tabulator-field="view_url"]):not([tabulator-field="has_visibility_issue"]) {
    cursor: cell;
    position: relative;
}

.tabulator .tabulator-cell[tabulator-field]:not([tabulator-field="equipement"]):not([tabulator-field="maintenance_info"]):not([tabulator-field="client_nom"]):not([tabulator-field="site_nom"]):not([tabulator-field="salle_nom"]):not([tabulator-field="id"]):not([tabulator-field="view_url"]):not([tabulator-field="has_visibility_issue"]):hover {
    background-color: rgba(var(--bs-primary-rgb), 0.05) !important;
}

[data-bs-theme="dark"] .tabulator .tabulator-cell[tabulator-field]:not([tabulator-field="equipement"]):not([tabulator-field="maintenance_info"]):not([tabulator-field="client_nom"]):not([tabulator-field="site_nom"]):not([tabulator-field="salle_nom"]):not([tabulator-field="id"]):not([tabulator-field="view_url"]):not([tabulator-field="has_visibility_issue"]):hover {
    background-color: rgba(255, 255, 255, 0.03) !important;
}

/* Indicateur visuel pour les cellules éditables */
.tabulator .tabulator-cell.editable::after {
    content: '✎';
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
    opacity: 0;
    transition: opacity 0.2s;
    font-size: 0.8em;
    color: var(--bs-secondary-color);
}

.tabulator .tabulator-cell.editable:hover::after {
    opacity: 0.5;
}
</style>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec titre et bouton d'ajout -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="bi bi-hdd-network me-2 me-1"></i>Liste du Matériel V2
                    </h4>
                    <p class="text-muted mb-0">Gestion et suivi du matériel par site et salle (Version 2)</p>
                </div>
                <div class="d-flex gap-2">
                    <?php
                    // Bouton retour vers le client si on vient d'un client
                    if (!empty($filters['client_id'])) {
                        $clientId = $filters['client_id'];
                        echo '<a href="' . BASE_URL . 'clients/view/' . $clientId . '" class="btn btn-secondary me-2">';
                        echo '<i class="bi bi-arrow-left me-1"></i> Retour au client';
                        echo '</a>';
                    }
                    
                    // Construire l'URL d'ajout avec les paramètres de filtres
                    $addParams = [];
                    if (!empty($filters['client_id'])) {
                        $addParams['client_id'] = $filters['client_id'];
                    }
                    if (!empty($filters['site_id'])) {
                        $addParams['site_id'] = $filters['site_id'];
                    }
                    if (!empty($filters['salle_id'])) {
                        $addParams['salle_id'] = $filters['salle_id'];
                    }
                    
                    $addUrl = BASE_URL . 'materiel/add';
                    if (!empty($addParams)) {
                        $addUrl .= '?' . http_build_query($addParams);
                    }
                    ?>
                    <a href="<?= $addUrl ?>" class="btn btn-primary">
                        <i class="bi bi-plus me-2 me-1"></i>Ajouter du Matériel
                    </a>
                    
                    <?php if (canImportMateriel()): ?>
                        <!-- Bouton pour l'import/export en masse -->
                        <?php
                        $bulkParams = [];
                        if (!empty($filters['client_id'])) {
                            $bulkParams['client_id'] = $filters['client_id'];
                        }
                        if (!empty($filters['site_id'])) {
                            $bulkParams['site_id'] = $filters['site_id'];
                        }
                        
                        $bulkUrl = BASE_URL . 'materiel_bulk';
                        if (!empty($bulkParams)) {
                            $bulkUrl .= '?' . http_build_query($bulkParams);
                        }
                        ?>
                        <a href="<?= $bulkUrl ?>" class="btn btn-info">
                            <i class="bi bi-arrow-left-right me-2 me-1"></i>Import/Export en Masse
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <?php if (!empty($stats)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="card border-0 bg-primary bg-opacity-10">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-hdd-network fa-2x text-primary me-1"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1 text-primary fw-bold"><?= $stats['total'] ?? 0 ?></h6>
                                    <small class="text-muted">Total Matériel</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-success bg-opacity-10">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-wifi fa-2x text-success"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1 text-success fw-bold"><?= $stats['online'] ?? 0 ?></h6>
                                    <small class="text-muted">En Ligne</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-warning bg-opacity-10">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-tools fa-2x text-warning me-1"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1 text-warning fw-bold"><?= $stats['maintenance_expired'] ?? 0 ?></h6>
                                    <small class="text-muted">Maintenance Expirée</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-danger bg-opacity-10">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-certificate fa-2x text-danger"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1 text-danger fw-bold"><?= $stats['garantie_expired'] ?? 0 ?></h6>
                                    <small class="text-muted">Garantie Expirée</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-header py-2">
            <h6 class="card-title mb-0">Filtres</h6>
        </div>
        <div class="card-body py-2">
            <form method="get" action="<?= BASE_URL ?>materiel_v2" class="row g-3 align-items-end" id="filterForm">
                <div class="col-md-3">
                    <label for="client_id" class="form-label fw-bold mb-0">Client</label>
                    <select class="form-select bg-body text-body" id="client_id" name="client_id" onchange="updateSitesAndSubmit()">
                        <option value="">Tous les clients</option>
                        <?php if (isset($clients) && is_array($clients)): ?>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>" <?= ($filters['client_id'] ?? '') == $client['id'] ? 'selected' : '' ?>>
                                    <?= h($client['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="site_id" class="form-label fw-bold mb-0">Site <span class="text-danger">*</span></label>
                    <select class="form-select bg-body text-body" id="site_id" name="site_id" required onchange="updateRoomsAndSubmit()">
                        <option value="">Sélectionnez un site</option>
                        <?php if (isset($sites) && is_array($sites)): ?>
                            <?php foreach ($sites as $site): ?>
                                <option value="<?= $site['id'] ?>" <?= ($filters['site_id'] ?? '') == $site['id'] ? 'selected' : '' ?>>
                                    <?= h($site['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="salle_id" class="form-label fw-bold mb-0">Salle <span class="text-danger">*</span></label>
                    <select class="form-select bg-body text-body" id="salle_id" name="salle_id" required onchange="document.getElementById('filterForm').submit();">
                        <option value="">Sélectionnez une salle</option>
                        <?php foreach ($salles as $salle): ?>
                            <option value="<?= $salle['id'] ?>" <?= ($filters['salle_id'] ?? '') == $salle['id'] ? 'selected' : '' ?>>
                                <?= h($salle['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <a href="<?= BASE_URL ?>materiel_v2" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg me-2 me-1"></i>Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Table Tabulator (toujours présent) -->
    <div class="card" id="tabulator-card" style="<?= (empty($filters['client_id']) || empty($filters['site_id']) || empty($filters['salle_id']) || empty($materiel_list)) ? 'display: none;' : '' ?>">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="card-title mb-0">Liste du Matériel</h6>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" id="toggleColumnMenu" onclick="openColumnMenu()">
                    <i class="bi bi-list-check me-1"></i>Colonnes
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <!-- Barre de recherche -->
            <div class="p-3 border-bottom">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="tabulator-search" placeholder="Rechercher dans toutes les colonnes...">
                    <button class="btn btn-outline-secondary" type="button" id="clear-search" style="display: none;">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            <div id="materiel-tabulator-table" style="height: calc(100vh - 400px); min-height: 500px;"></div>
        </div>
    </div>

    <!-- Liste du matériel organisée -->
    <?php if (empty($filters['client_id'])): ?>
        <!-- Message d'instruction quand aucun client n'est sélectionné -->
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-filter fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Sélectionnez un client pour voir le matériel</h5>
                <p class="text-muted mb-3">Choisissez un client dans le filtre ci-dessus pour afficher le matériel associé.</p>
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2 me-1"></i>
                            <strong>Astuce :</strong> Commencez par sélectionner un client, puis un site et enfin une salle pour affiner votre recherche.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif (empty($filters['site_id'])): ?>
        <!-- Message d'instruction quand aucun site n'est sélectionné -->
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-geo-alt fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Sélectionnez un site</h5>
                <p class="text-muted mb-3">Vous devez sélectionner un site et une salle pour afficher le matériel.</p>
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2 me-1"></i>
                            <strong>Attention :</strong> La sélection d'un site et d'une salle est obligatoire pour afficher le tableau.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif (empty($filters['salle_id'])): ?>
        <!-- Message d'instruction quand aucune salle n'est sélectionnée -->
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-door-open fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Sélectionnez une salle</h5>
                <p class="text-muted mb-3">Vous devez sélectionner une salle pour afficher le matériel.</p>
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2 me-1"></i>
                            <strong>Attention :</strong> La sélection d'une salle est obligatoire pour afficher le tableau.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif (empty($materiel_list)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-hdd-network fa-3x text-muted mb-3 me-1"></i>
                <h5 class="text-muted">Aucun matériel trouvé</h5>
                <p class="text-muted mb-3">Aucun matériel ne correspond aux critères sélectionnés.</p>
                <?php
                // Construire l'URL d'ajout avec les paramètres de filtres
                $addParams = [];
                if (!empty($filters['client_id'])) {
                    $addParams['client_id'] = $filters['client_id'];
                }
                if (!empty($filters['site_id'])) {
                    $addParams['site_id'] = $filters['site_id'];
                }
                if (!empty($filters['salle_id'])) {
                    $addParams['salle_id'] = $filters['salle_id'];
                }
                
                $addUrl = BASE_URL . 'materiel/add';
                if (!empty($addParams)) {
                    $addUrl .= '?' . http_build_query($addParams);
                }
                ?>
                <a href="<?= $addUrl ?>" class="btn btn-primary">
                    <i class="bi bi-plus me-2 me-1"></i>Ajouter du Matériel
                </a>
            </div>
        </div>
    <?php else: 
        // Préparer les données pour Tabulator
        foreach ($materiel_list as $materiel) {
            $today = new DateTime();
            $maintenance_info = '';
            $garantie_info = '';
            
            if (!empty($materiel['date_fin_maintenance'])) {
                $maintenance_date = new DateTime($materiel['date_fin_maintenance']);
                $maintenance_class = $maintenance_date < $today ? 'danger' : ($maintenance_date->diff($today)->days < 30 ? 'warning' : 'success');
                $maintenance_info = formatDateFrench($materiel['date_fin_maintenance']);
            }
            
            if (!empty($materiel['date_fin_garantie'])) {
                $garantie_date = new DateTime($materiel['date_fin_garantie']);
                $garantie_class = $garantie_date < $today ? 'danger' : ($garantie_date->diff($today)->days < 30 ? 'warning' : 'success');
                $garantie_info = formatDateFrench($materiel['date_fin_garantie']);
            }
            
            // Construire l'URL de vue
            $filterParams = [];
            if (!empty($filters['client_id'])) {
                $filterParams['client_id'] = $filters['client_id'];
            }
            if (!empty($filters['site_id'])) {
                $filterParams['site_id'] = $filters['site_id'];
            }
            if (!empty($filters['salle_id'])) {
                $filterParams['salle_id'] = $filters['salle_id'];
            }
            
            $viewUrl = BASE_URL . 'materiel/view/' . $materiel['id'];
            if (!empty($filterParams)) {
                $viewUrl .= '?' . http_build_query($filterParams);
            }
            
            // Préparer les informations de visibilité par champ
            $visibility_data = [];
            if (isset($visibilites_champs[$materiel['id']])) {
                $visibility_data = $visibilites_champs[$materiel['id']];
            }
            
            $tabulator_data[] = [
                'id' => $materiel['id'],
                'client_nom' => $materiel['client_nom'] ?? 'Sans client',
                'site_nom' => $materiel['site_nom'] ?? 'Sans site',
                'salle_nom' => $materiel['salle_nom'] ?? 'Sans salle',
                'marque' => $materiel['marque'] ?? '',
                'modele' => $materiel['modele'] ?? '',
                'equipement' => ($materiel['marque'] ?? '') . ' ' . ($materiel['modele'] ?? ''),
                'type_materiel' => $materiel['type_nom'] ?? $materiel['type_materiel'] ?? '',
                'reference' => $materiel['reference'] ?? '',
                'usage_materiel' => $materiel['usage_materiel'] ?? '',
                'numero_serie' => $materiel['numero_serie'] ?? '',
                'version_firmware' => $materiel['version_firmware'] ?? '',
                'ancien_firmware' => $materiel['ancien_firmware'] ?? '',
                'adresse_ip' => $materiel['adresse_ip'] ?? '',
                'adresse_mac' => $materiel['adresse_mac'] ?? '',
                'masque' => $materiel['masque'] ?? '',
                'passerelle' => $materiel['passerelle'] ?? '',
                'login' => $materiel['login'] ?? '',
                'password' => $materiel['password'] ?? '',
                'id_materiel' => $materiel['id_materiel'] ?? '',
                'ip_primaire' => $materiel['ip_primaire'] ?? '',
                'mac_primaire' => $materiel['mac_primaire'] ?? '',
                'ip_secondaire' => $materiel['ip_secondaire'] ?? '',
                'mac_secondaire' => $materiel['mac_secondaire'] ?? '',
                'stream_aes67_recu' => $materiel['stream_aes67_recu'] ?? '',
                'stream_aes67_transmis' => $materiel['stream_aes67_transmis'] ?? '',
                'ssid' => $materiel['ssid'] ?? '',
                'type_cryptage' => $materiel['type_cryptage'] ?? '',
                'password_wifi' => $materiel['password_wifi'] ?? '',
                'libelle_pa_salle' => $materiel['libelle_pa_salle'] ?? '',
                'numero_port_switch' => $materiel['numero_port_switch'] ?? '',
                'vlan' => $materiel['vlan'] ?? '',
                'date_fin_maintenance' => $materiel['date_fin_maintenance'] ?? '',
                'date_fin_garantie' => $materiel['date_fin_garantie'] ?? '',
                'date_derniere_inter' => $materiel['date_derniere_inter'] ?? '',
                'url_github' => $materiel['url_github'] ?? '',
                'commentaire' => $materiel['commentaire'] ?? '',
                'maintenance_info' => $maintenance_info,
                'garantie_info' => $garantie_info,
                'pieces_jointes_count' => $pieces_jointes_count[$materiel['id']] ?? 0,
                'view_url' => $viewUrl,
                'has_visibility_issue' => (isset($visibilites_champs[$materiel['id']]) && (
                    (isset($visibilites_champs[$materiel['id']]['marque']) && !$visibilites_champs[$materiel['id']]['marque']) ||
                    (isset($visibilites_champs[$materiel['id']]['modele']) && !$visibilites_champs[$materiel['id']]['modele']) ||
                    (isset($visibilites_champs[$materiel['id']]['numero_serie']) && !$visibilites_champs[$materiel['id']]['numero_serie']) ||
                    (isset($visibilites_champs[$materiel['id']]['version_firmware']) && !$visibilites_champs[$materiel['id']]['version_firmware']) ||
                    (isset($visibilites_champs[$materiel['id']]['adresse_ip']) && !$visibilites_champs[$materiel['id']]['adresse_ip']) ||
                    (isset($visibilites_champs[$materiel['id']]['adresse_mac']) && !$visibilites_champs[$materiel['id']]['adresse_mac']) ||
                    (isset($visibilites_champs[$materiel['id']]['date_fin_maintenance']) && !$visibilites_champs[$materiel['id']]['date_fin_maintenance']) ||
                    (isset($visibilites_champs[$materiel['id']]['date_fin_garantie']) && !$visibilites_champs[$materiel['id']]['date_fin_garantie'])
                )),
                // Informations de visibilité par champ (true = visible, false = masqué)
                'visibility' => [
                    'marque' => $visibility_data['marque'] ?? true,
                    'modele' => $visibility_data['modele'] ?? true,
                    'numero_serie' => $visibility_data['numero_serie'] ?? true,
                    'version_firmware' => $visibility_data['version_firmware'] ?? true,
                    'adresse_ip' => $visibility_data['adresse_ip'] ?? true,
                    'adresse_mac' => $visibility_data['adresse_mac'] ?? true,
                    'date_fin_maintenance' => $visibility_data['date_fin_maintenance'] ?? true,
                    'date_fin_garantie' => $visibility_data['date_fin_garantie'] ?? true,
                    'type_materiel' => $visibility_data['type_materiel'] ?? true,
                    'reference' => $visibility_data['reference'] ?? true,
                    'usage_materiel' => $visibility_data['usage_materiel'] ?? true,
                    'ancien_firmware' => $visibility_data['ancien_firmware'] ?? true,
                    'masque' => $visibility_data['masque'] ?? true,
                    'passerelle' => $visibility_data['passerelle'] ?? true,
                    'login' => $visibility_data['login'] ?? true,
                    'password' => $visibility_data['password'] ?? true,
                    'id_materiel' => $visibility_data['id_materiel_tech'] ?? true,
                    'ip_primaire' => $visibility_data['ip_primaire'] ?? true,
                    'mac_primaire' => $visibility_data['mac_primaire'] ?? true,
                    'ip_secondaire' => $visibility_data['ip_secondaire'] ?? true,
                    'mac_secondaire' => $visibility_data['mac_secondaire'] ?? true,
                    'stream_aes67_recu' => $visibility_data['stream_aes67_recu'] ?? true,
                    'stream_aes67_transmis' => $visibility_data['stream_aes67_transmis'] ?? true,
                    'ssid' => $visibility_data['ssid'] ?? true,
                    'type_cryptage' => $visibility_data['type_cryptage'] ?? true,
                    'password_wifi' => $visibility_data['password_wifi'] ?? true,
                    'libelle_pa_salle' => $visibility_data['libelle_pa_salle'] ?? true,
                    'numero_port_switch' => $visibility_data['numero_port_switch'] ?? true,
                    'vlan' => $visibility_data['vlan'] ?? true,
                    'date_derniere_inter' => $visibility_data['date_derniere_inter'] ?? true,
                    'commentaire' => $visibility_data['commentaire'] ?? true,
                    'url_github' => $visibility_data['url_github'] ?? true
                ]
            ];
        }
    ?>
    <?php endif; ?>
    
    <!-- Modal de confirmation d'édition -->
    <div class="modal fade" id="editConfirmModal" tabindex="-1" aria-labelledby="editConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editConfirmModalLabel">Confirmer la modification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Voulez-vous vraiment enregistrer cette modification ?</p>
                    <div class="mb-2">
                        <strong>Champ :</strong> <span id="editFieldName"></span>
                    </div>
                    <div class="mb-2">
                        <strong>Ancienne valeur :</strong> <span id="editOldValue" class="text-muted"></span>
                    </div>
                    <div class="mb-2">
                        <strong>Nouvelle valeur :</strong> <span id="editNewValue"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelEditBtn">Annuler</button>
                    <button type="button" class="btn btn-primary" id="confirmEditBtn">Enregistrer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour afficher les pièces jointes -->
    <div class="modal fade" id="attachmentsModal" tabindex="-1" aria-labelledby="attachmentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="attachmentsModalLabel">Pièces jointes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="attachmentsModalContent">
                        <div class="text-center py-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Chargement...</span>
                            </div>
                            <p class="mt-2 text-muted">Chargement des pièces jointes...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Menu de sélection des colonnes (toujours présent) -->
    <div class="modal fade" id="columnMenuModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sélectionner les colonnes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="columnCheckboxes" class="list-group">
                        <!-- Les checkboxes seront générées par JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="button" class="btn btn-primary" onclick="applyColumnVisibility()">Appliquer</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabulator JS -->
<script type="text/javascript" src="https://unpkg.com/tabulator-tables@6.2.5/dist/js/tabulator.min.js"></script>

<script>
// Attendre que Tabulator soit chargé
if (typeof Tabulator === 'undefined') {
    console.error('Tabulator n\'est pas chargé !');
}
// Variable globale pour l'URL de base
const baseUrl = '<?= BASE_URL ?>';

// Données pour Tabulator
<?php
// S'assurer que $tabulator_data est toujours un tableau
if (!isset($tabulator_data) || !is_array($tabulator_data)) {
    $tabulator_data = [];
}
?>
const materielData = <?= json_encode($tabulator_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

// Debug
console.log('Materiel Data (raw):', materielData);
console.log('Type:', typeof materielData);
console.log('Est un tableau:', Array.isArray(materielData));
console.log('Nombre d\'éléments:', materielData ? materielData.length : 0);
if (materielData && materielData.length > 0) {
    console.log('Premier élément:', materielData[0]);
}

// Configuration des colonnes Tabulator
const allColumns = [
    {field: "equipement", title: "Équipement", headerSort: true, width: 200, visible: true, frozen: true, editable: false,
     formatter: function(cell, formatterParams) {
         const row = cell.getRow().getData();
         const warningClass = row.has_visibility_issue ? 'visibility-warning' : '';
         return `<div class="${warningClass}">
                    <div class="fw-bold">${cell.getValue() || '-'}</div>
                    <small class="text-muted">${row.marque || ''} ${row.modele || ''}</small>
                 </div>`;
     },
     cellClick: function(e, cell) {
         const row = cell.getRow().getData();
         window.location.href = row.view_url;
     }},
    {field: "type_materiel", title: "Type", headerSort: true, width: 150, visible: true, editor: "input"},
    {field: "reference", title: "Référence", headerSort: true, width: 120, visible: false, editor: "input"},
    {field: "usage_materiel", title: "Usage", headerSort: true, width: 200, visible: false, editor: "input"},
    {field: "numero_serie", title: "N° Série", headerSort: true, width: 150, visible: true, editor: "input",
     formatter: function(cell, formatterParams) {
         const row = cell.getRow().getData();
         const warningClass = row.has_visibility_issue ? 'visibility-warning' : '';
         return `<span class="${warningClass}">${cell.getValue() || '-'}</span>`;
     }},
    {field: "version_firmware", title: "Firmware", headerSort: true, width: 120, visible: true, editor: "input",
     formatter: function(cell, formatterParams) {
         const row = cell.getRow().getData();
         const warningClass = row.has_visibility_issue ? 'visibility-warning' : '';
         return `<span class="${warningClass}">${cell.getValue() || '-'}</span>`;
     }},
    {field: "ancien_firmware", title: "Ancien Firmware", headerSort: true, width: 150, visible: false, editor: "input"},
    {field: "adresse_ip", title: "IP", headerSort: true, width: 120, visible: true, editor: "input",
     formatter: function(cell, formatterParams) {
         const row = cell.getRow().getData();
         const warningClass = row.has_visibility_issue ? 'visibility-warning' : '';
         return `<span class="${warningClass}">${cell.getValue() || '-'}</span>`;
     }},
    {field: "adresse_mac", title: "MAC", headerSort: true, width: 140, visible: true, editor: "input",
     formatter: function(cell, formatterParams) {
         const row = cell.getRow().getData();
         const warningClass = row.has_visibility_issue ? 'visibility-warning' : '';
         return `<span class="${warningClass}">${cell.getValue() || '-'}</span>`;
     }},
    {field: "masque", title: "Masque", headerSort: true, width: 120, visible: false, editor: "input"},
    {field: "passerelle", title: "Passerelle", headerSort: true, width: 120, visible: false, editor: "input"},
    {field: "login", title: "Login", headerSort: true, width: 120, visible: false, editor: "input"},
    {field: "password", title: "Password", headerSort: true, width: 120, visible: false, editor: "input"},
    {field: "id_materiel", title: "ID Matériel", headerSort: true, width: 120, visible: false, editor: "input"},
    {field: "ip_primaire", title: "IP Primaire", headerSort: true, width: 120, visible: false, editor: "input"},
    {field: "mac_primaire", title: "MAC Primaire", headerSort: true, width: 140, visible: false, editor: "input"},
    {field: "ip_secondaire", title: "IP Secondaire", headerSort: true, width: 130, visible: false, editor: "input"},
    {field: "mac_secondaire", title: "MAC Secondaire", headerSort: true, width: 150, visible: false, editor: "input"},
    {field: "stream_aes67_recu", title: "Stream AES67 Reçu", headerSort: true, width: 180, visible: false, editor: "input"},
    {field: "stream_aes67_transmis", title: "Stream AES67 Transmis", headerSort: true, width: 200, visible: false, editor: "input"},
    {field: "ssid", title: "SSID", headerSort: true, width: 150, visible: false, editor: "input"},
    {field: "type_cryptage", title: "Type Cryptage", headerSort: true, width: 150, visible: false, editor: "input"},
    {field: "password_wifi", title: "Password WiFi", headerSort: true, width: 150, visible: false, editor: "input"},
    {field: "libelle_pa_salle", title: "Libellé PA Salle", headerSort: true, width: 150, visible: false, editor: "input"},
    {field: "numero_port_switch", title: "N° Port Switch", headerSort: true, width: 150, visible: false, editor: "input"},
    {field: "vlan", title: "VLAN", headerSort: true, width: 100, visible: false, editor: "input"},
    {field: "date_fin_maintenance", title: "Fin Maintenance", headerSort: true, width: 150, visible: false, editor: "date",
     formatter: function(cell, formatterParams) {
         const value = cell.getValue();
         if (!value) return '-';
         const date = new Date(value);
         return date.toLocaleDateString('fr-FR');
     }},
    {field: "date_fin_garantie", title: "Fin Garantie", headerSort: true, width: 150, visible: false, editor: "date",
     formatter: function(cell, formatterParams) {
         const value = cell.getValue();
         if (!value) return '-';
         const date = new Date(value);
         return date.toLocaleDateString('fr-FR');
     }},
    {field: "date_derniere_inter", title: "Dernière Intervention", headerSort: true, width: 180, visible: false, editor: "date",
     formatter: function(cell, formatterParams) {
         const value = cell.getValue();
         if (!value) return '-';
         const date = new Date(value);
         return date.toLocaleDateString('fr-FR');
     }},
    {field: "url_github", title: "GitHub", headerSort: true, width: 200, visible: false, editor: "input",
     formatter: "link", formatterParams: {target: "_blank"}},
    {field: "commentaire", title: "Commentaire", headerSort: true, width: 250, visible: false, editor: "textarea"},
    {field: "pieces_jointes", title: "PJ", headerSort: true, width: 60, visible: true, editable: false,
     formatter: function(cell, formatterParams) {
         const row = cell.getRow().getData();
         const count = row.pieces_jointes_count || 0;
         if (count > 0) {
             return `<span class="badge bg-primary" style="cursor: pointer;" title="Cliquer pour voir les pièces jointes">
                        <i class="bi bi-paperclip me-1"></i>${count}
                     </span>`;
         }
         return '<span class="text-muted">-</span>';
     },
     cellClick: function(e, cell) {
         const row = cell.getRow().getData();
         const count = row.pieces_jointes_count || 0;
         if (count > 0) {
             openAttachmentsModal(row.id, row.equipement || 'Matériel');
         }
     }},
    {field: "maintenance_info", title: "Maintenance", headerSort: false, width: 150, visible: true, editable: false,
     formatter: function(cell, formatterParams) {
         const row = cell.getRow().getData();
         const warningClass = row.has_visibility_issue ? 'visibility-warning' : '';
         let html = '';
         if (row.maintenance_info) {
             const today = new Date();
             const maintDate = new Date(row.date_fin_maintenance);
             const daysDiff = Math.floor((maintDate - today) / (1000 * 60 * 60 * 24));
             const colorClass = daysDiff < 0 ? 'text-danger' : (daysDiff < 30 ? 'text-warning' : 'text-success');
             html += `<div class="${warningClass}"><small class="text-muted">Maint:</small> <span class="${colorClass}">${row.maintenance_info}</span></div>`;
         }
         if (row.garantie_info) {
             const today = new Date();
             const garantieDate = new Date(row.date_fin_garantie);
             const daysDiff = Math.floor((garantieDate - today) / (1000 * 60 * 60 * 24));
             const colorClass = daysDiff < 0 ? 'text-danger' : (daysDiff < 30 ? 'text-warning' : 'text-success');
             html += `<div class="${warningClass}"><small class="text-muted">Garantie:</small> <span class="${colorClass}">${row.garantie_info}</span></div>`;
         }
         return html || '<span class="text-muted">-</span>';
     }}
];

// Initialiser Tabulator
let materielTable;

// Fonction pour ouvrir la modal des pièces jointes
function openAttachmentsModal(materielId, materielName) {
    const modal = new bootstrap.Modal(document.getElementById('attachmentsModal'));
    const modalTitle = document.getElementById('attachmentsModalLabel');
    const modalContent = document.getElementById('attachmentsModalContent');
    
    modalTitle.textContent = `Pièces jointes - ${materielName}`;
    modalContent.innerHTML = `
        <div class="text-center py-3">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2 text-muted">Chargement des pièces jointes...</p>
        </div>
    `;
    
    modal.show();
    
    // Charger les pièces jointes
    fetch(baseUrl + 'materiel_v2/getAttachments?id=' + materielId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.attachments) {
                renderAttachments(data.attachments, modalContent);
            } else {
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        ${data.message || 'Erreur lors du chargement des pièces jointes'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            modalContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Erreur lors du chargement des pièces jointes
                </div>
            `;
        });
}

// Fonction pour afficher les pièces jointes dans la modal
function renderAttachments(attachments, container) {
    if (attachments.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <p class="text-muted mt-3">Aucune pièce jointe disponible</p>
            </div>
        `;
        return;
    }
    
    let html = '<div class="list-group">';
    
    attachments.forEach((attachment) => {
        const fileIcon = getFileIcon(attachment.type_fichier || '');
        const fileSize = formatFileSize(attachment.taille_fichier || 0);
        const downloadUrl = baseUrl + 'materiel/download/' + attachment.id;
        const previewUrl = baseUrl + 'materiel/preview/' + attachment.id;
        const dateCreation = attachment.date_creation ? new Date(attachment.date_creation).toLocaleDateString('fr-FR') : '-';
        const isImage = attachment.type_fichier && ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(attachment.type_fichier.toLowerCase());
        const isPdf = attachment.type_fichier && attachment.type_fichier.toLowerCase() === 'pdf';
        
        html += `
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="d-flex align-items-center flex-grow-1">
                        <i class="${fileIcon} fs-4 me-3 text-primary"></i>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">${attachment.nom_fichier || 'Sans nom'}</h6>
                            <small class="text-muted d-block">
                                ${fileSize} • ${dateCreation}
                                ${attachment.type_nom ? ' • ' + attachment.type_nom : ''}
                                ${attachment.created_by_name ? ' • Par ' + attachment.created_by_name : ''}
                            </small>
                            ${attachment.commentaire ? `<small class="text-muted d-block mt-1">${attachment.commentaire}</small>` : ''}
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        ${(isImage || isPdf) ? `
                            <a href="${previewUrl}" target="_blank" class="btn btn-sm btn-outline-info" title="Aperçu">
                                <i class="bi bi-eye"></i>
                            </a>
                        ` : ''}
                        <a href="${downloadUrl}" class="btn btn-sm btn-outline-primary" download title="Télécharger">
                            <i class="bi bi-download"></i>
                        </a>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// Fonction pour obtenir l'icône selon le type de fichier
function getFileIcon(fileType) {
    const type = (fileType || '').toLowerCase();
    if (type.includes('pdf')) return 'bi bi-file-pdf';
    if (type.includes('word') || type.includes('doc')) return 'bi bi-file-word';
    if (type.includes('excel') || type.includes('xls')) return 'bi bi-file-excel';
    if (type.includes('image') || ['jpg', 'jpeg', 'png', 'gif'].includes(type)) return 'bi bi-file-image';
    if (type.includes('zip') || type.includes('rar')) return 'bi bi-file-zip';
    return 'bi bi-file-earmark';
}

// Fonction pour formater la taille du fichier
function formatFileSize(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// Fonction pour afficher des notifications
function showNotification(message, type = 'info') {
    // Créer un élément de notification
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Supprimer automatiquement après 3 secondes
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 3000);
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM chargé, initialisation de Tabulator...');
    console.log('Tabulator disponible:', typeof Tabulator !== 'undefined');
    console.log('Bootstrap disponible:', typeof bootstrap !== 'undefined');
    console.log('Modal editConfirmModal:', document.getElementById('editConfirmModal') ? 'trouvée' : 'non trouvée');
    
    // Vérifier que Tabulator est chargé
    if (typeof Tabulator === 'undefined') {
        console.error('Tabulator n\'est pas chargé !');
        const tableContainer = document.getElementById('materiel-tabulator-table');
        if (tableContainer) {
            tableContainer.innerHTML = '<div class="alert alert-danger">Erreur: Tabulator n\'est pas chargé. Veuillez recharger la page.</div>';
        }
        return;
    }
    
    // Récupérer la configuration sauvegardée des colonnes
    const savedColumnVisibility = localStorage.getItem('materiel_v2_column_visibility');
    let columnVisibility = {};
    
    if (savedColumnVisibility) {
        try {
            columnVisibility = JSON.parse(savedColumnVisibility);
        } catch (e) {
            console.error('Erreur lors du chargement de la configuration des colonnes:', e);
        }
    }
    
    // Récupérer les largeurs sauvegardées
    const savedColumnWidths = localStorage.getItem('materiel_v2_column_widths');
    let columnWidths = {};
    
    if (savedColumnWidths) {
        try {
            columnWidths = JSON.parse(savedColumnWidths);
        } catch (e) {
            console.error('Erreur lors du chargement des largeurs de colonnes:', e);
        }
    }
    
    // Appliquer la visibilité et les largeurs sauvegardées
    allColumns.forEach(col => {
        if (columnVisibility.hasOwnProperty(col.field)) {
            col.visible = columnVisibility[col.field];
        }
        if (columnWidths.hasOwnProperty(col.field)) {
            col.width = columnWidths[col.field];
        }
    });
    
    // Initialiser le menu de colonnes avec la configuration sauvegardée
    initColumnMenu();
    
    console.log('Initialisation Tabulator - materielData:', materielData);
    console.log('Type:', typeof materielData);
    console.log('Est un tableau:', Array.isArray(materielData));
    console.log('Longueur:', materielData ? materielData.length : 'undefined');
    
    const tableContainer = document.getElementById('materiel-tabulator-table');
    if (!tableContainer) {
        console.error('Le conteneur #materiel-tabulator-table n\'existe pas');
        return;
    }
    
    // Vérifier que site et salle sont sélectionnés
    const siteId = document.getElementById('site_id')?.value;
    const salleId = document.getElementById('salle_id')?.value;
    
    if (!siteId || !salleId) {
        console.log('Site ou salle non sélectionné - masquage de la carte');
        const tabulatorCard = document.getElementById('tabulator-card');
        if (tabulatorCard) {
            tabulatorCard.style.display = 'none';
        }
        return;
    }
    
    if (materielData && Array.isArray(materielData) && materielData.length > 0) {
        console.log('Création de la table Tabulator avec', materielData.length, 'éléments');
        
        // Afficher la carte Tabulator
        const tabulatorCard = document.getElementById('tabulator-card');
        if (tabulatorCard) {
            tabulatorCard.style.display = 'block';
        }
        
        // Créer la table Tabulator
        try {
            // Variables pour stocker les informations d'édition en attente
            let pendingEdit = null;
            let isRestoringValue = false; // Flag pour éviter les événements lors de la restauration
            
            materielTable = new Tabulator("#materiel-tabulator-table", {
                data: materielData,
                layout: "fitDataStretch", // Utiliser fitDataStretch pour respecter les largeurs
                height: "100%",
                columns: allColumns,
                pagination: true,
                paginationSize: 50,
                paginationSizeSelector: [10, 25, 50, 100, 200, 500, true], // true pour permettre "Tout"
                paginationCounter: "rows",
                resizableColumns: true, // Permettre le redimensionnement des colonnes
                initialSort: [
                    {column: "equipement", dir: "asc"}
                ]
            });
            
            // Gérer la recherche globale
            const searchInput = document.getElementById('tabulator-search');
            const clearSearchBtn = document.getElementById('clear-search');
            
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', function(e) {
                    clearTimeout(searchTimeout);
                    const searchValue = e.target.value;
                    
                    if (searchValue.length > 0) {
                        clearSearchBtn.style.display = 'block';
                    } else {
                        clearSearchBtn.style.display = 'none';
                    }
                    
                    // Délai pour éviter trop de recherches pendant la frappe
                    searchTimeout = setTimeout(function() {
                        if (searchValue.length > 0) {
                            // Utiliser une fonction de filtre personnalisée pour rechercher dans toutes les colonnes
                            materielTable.setFilter(function(data) {
                                const searchLower = searchValue.toLowerCase();
                                const fieldsToSearch = [
                                    "equipement", "type_materiel", "reference", "usage_materiel",
                                    "numero_serie", "version_firmware", "ancien_firmware",
                                    "adresse_ip", "adresse_mac", "masque", "passerelle",
                                    "login", "password", "id_materiel", "ip_primaire",
                                    "mac_primaire", "ip_secondaire", "mac_secondaire",
                                    "stream_aes67_recu", "stream_aes67_transmis", "ssid",
                                    "type_cryptage", "password_wifi", "libelle_pa_salle",
                                    "numero_port_switch", "vlan", "commentaire", "url_github",
                                    "client_nom", "site_nom", "salle_nom", "pieces_jointes_count"
                                ];
                                
                                // Chercher dans tous les champs (OR)
                                for (let i = 0; i < fieldsToSearch.length; i++) {
                                    const field = fieldsToSearch[i];
                                    const fieldValue = data[field];
                                    if (fieldValue && String(fieldValue).toLowerCase().includes(searchLower)) {
                                        return true;
                                    }
                                }
                                return false;
                            });
                        } else {
                            materielTable.clearFilter();
                        }
                    }, 300);
                });
            }
            
            // Bouton pour effacer la recherche
            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', function() {
                    searchInput.value = '';
                    clearSearchBtn.style.display = 'none';
                    materielTable.clearFilter();
                });
            }
            
            // Synchroniser le sélecteur de pagination de Tabulator avec notre configuration
            // Le sélecteur natif de Tabulator sera utilisé (en bas de la table)
            
            // Appliquer les styles aux cellules avec données masquées après le rendu
            materielTable.on("cellRendered", function(cell) {
                const row = cell.getRow().getData();
                const field = cell.getField();
                
                if (row.visibility && row.visibility.hasOwnProperty(field) && !row.visibility[field]) {
                    cell.getElement().classList.add('hidden-to-client');
                } else {
                    cell.getElement().classList.remove('hidden-to-client');
                }
            });
            
            // Écouter les modifications de cellules avec l'événement
            materielTable.on("cellEdited", function(cell) {
                // Ignorer si on est en train de restaurer une valeur
                if (isRestoringValue) {
                    console.log('Restauration en cours, événement ignoré');
                    return;
                }
                
                console.log('cellEdited déclenché', cell);
                
                const field = cell.getField();
                const row = cell.getRow().getData();
                const oldValue = cell.getOldValue();
                const newValue = cell.getValue();
                
                console.log('Field:', field, 'Old:', oldValue, 'New:', newValue);
                
                // Ignorer si la valeur n'a pas changé
                if (oldValue === newValue) {
                    console.log('Valeur inchangée, ignoré');
                    return;
                }
                
                // Vérifier que la colonne est éditable
                const columnDef = cell.getColumn().getDefinition();
                if (columnDef.editable === false) {
                    console.log('Colonne non éditable, ignoré');
                    return;
                }
                
                // Ne pas annuler immédiatement - on laissera la modification visible
                // et on la restaurera seulement si l'utilisateur annule
                
                // Stocker les informations de modification
                pendingEdit = {
                    id: row.id,
                    field: field,
                    oldValue: oldValue || '',
                    newValue: newValue || '',
                    cell: cell,
                    columnTitle: columnDef.title || field
                };
                
                console.log('Pending edit:', pendingEdit);
                
                // Afficher la modal de confirmation
                const fieldNameEl = document.getElementById('editFieldName');
                const oldValueEl = document.getElementById('editOldValue');
                const newValueEl = document.getElementById('editNewValue');
                
                if (fieldNameEl && oldValueEl && newValueEl) {
                    fieldNameEl.textContent = pendingEdit.columnTitle;
                    oldValueEl.textContent = pendingEdit.oldValue || '(vide)';
                    newValueEl.textContent = pendingEdit.newValue || '(vide)';
                    
                    const modalEl = document.getElementById('editConfirmModal');
                    if (modalEl) {
                        console.log('Affichage de la modal');
                        // Vérifier que Bootstrap est disponible
                        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            const modal = new bootstrap.Modal(modalEl, {
                                backdrop: true,
                                keyboard: true
                            });
                            modal.show();
                        } else {
                            console.error('Bootstrap Modal non disponible');
                            // Fallback : utiliser jQuery si disponible
                            if (typeof $ !== 'undefined' && $.fn.modal) {
                                $(modalEl).modal('show');
                            } else {
                                // Fallback simple : afficher la modal directement
                                modalEl.style.display = 'block';
                                modalEl.classList.add('show');
                                document.body.classList.add('modal-open');
                                const backdrop = document.createElement('div');
                                backdrop.className = 'modal-backdrop fade show';
                                backdrop.id = 'editConfirmModalBackdrop';
                                document.body.appendChild(backdrop);
                            }
                        }
                    } else {
                        console.error('Modal editConfirmModal non trouvée dans le DOM');
                    }
                } else {
                    console.error('Éléments de la modal non trouvés:', {
                        fieldNameEl: !!fieldNameEl,
                        oldValueEl: !!oldValueEl,
                        newValueEl: !!newValueEl
                    });
                }
            });
            
            // Gérer la confirmation d'édition
            document.getElementById('confirmEditBtn').addEventListener('click', function() {
                if (!pendingEdit) return;
                
                const { id, field, newValue, cell } = pendingEdit;
                
                // Afficher un indicateur de chargement
                const confirmBtn = document.getElementById('confirmEditBtn');
                const cancelBtn = document.getElementById('cancelEditBtn');
                const originalText = confirmBtn.innerHTML;
                confirmBtn.disabled = true;
                cancelBtn.disabled = true; // Désactiver aussi le bouton annuler pendant le chargement
                confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...';
                
                // Créer un AbortController pour gérer le timeout
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 10000); // Timeout de 10 secondes
                
                // Faire l'appel AJAX
                fetch(baseUrl + 'materiel_v2/updateField', {
                    method: 'POST',
            headers: {
                'X-CSRF-Token': '<?= csrf_token() ?>',
                'Content-Type': 'application/json',
            },
                    body: JSON.stringify({
                        id: id,
                        field: field,
                        value: newValue
                    }),
                    signal: controller.signal
                })
                .then(response => {
                    clearTimeout(timeoutId);
                    
                    if (!response.ok) {
                        throw new Error('Erreur HTTP: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    clearTimeout(timeoutId);
                    
                    // Réactiver les boutons
                    confirmBtn.disabled = false;
                    cancelBtn.disabled = false;
                    confirmBtn.innerHTML = originalText;
                    
                    if (data.success) {
                        // La modification est déjà dans la table, pas besoin de la réappliquer
                        // Fermer la modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('editConfirmModal'));
                        if (modal) {
                            modal.hide();
                        }
                        
                        // Afficher un message de succès
                        showNotification('Modification enregistrée avec succès', 'success');
                        
                        // Réinitialiser pendingEdit
                        pendingEdit = null;
                    } else {
                        // Restaurer l'ancienne valeur en cas d'erreur
                        if (cell) {
                            isRestoringValue = true;
                            cell.setValue(pendingEdit.oldValue);
                            setTimeout(() => {
                                isRestoringValue = false;
                            }, 100);
                        }
                        
                        // Afficher un message d'erreur
                        showNotification('Erreur : ' + (data.message || 'Erreur lors de l\'enregistrement'), 'error');
                    }
                })
                .catch(error => {
                    clearTimeout(timeoutId);
                    
                    console.error('Erreur:', error);
                    
                    // Réactiver les boutons
                    confirmBtn.disabled = false;
                    cancelBtn.disabled = false;
                    confirmBtn.innerHTML = originalText;
                    
                    // Restaurer l'ancienne valeur en cas d'erreur
                    if (pendingEdit && pendingEdit.cell) {
                        pendingEdit.cell.setValue(pendingEdit.oldValue);
                    }
                    
                    let errorMessage = 'Erreur lors de l\'enregistrement';
                    if (error.name === 'AbortError') {
                        errorMessage = 'Timeout : La requête a pris trop de temps. Veuillez réessayer.';
                    } else if (error.message) {
                        errorMessage = 'Erreur : ' + error.message;
                    }
                    
                    showNotification(errorMessage, 'error');
                });
            });
            
            // Gérer l'annulation d'édition
            document.getElementById('cancelEditBtn').addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Activer le flag de restauration pour éviter que l'événement cellEdited se déclenche
                isRestoringValue = true;
                
                // Restaurer l'ancienne valeur
                if (pendingEdit && pendingEdit.cell) {
                    pendingEdit.cell.setValue(pendingEdit.oldValue);
                }
                
                // Réactiver les boutons au cas où ils seraient désactivés
                const confirmBtn = document.getElementById('confirmEditBtn');
                const cancelBtn = document.getElementById('cancelEditBtn');
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = 'Enregistrer';
                }
                if (cancelBtn) {
                    cancelBtn.disabled = false;
                }
                
                // Réinitialiser pendingEdit
                pendingEdit = null;
                
                // Fermer la modal
                const modalEl = document.getElementById('editConfirmModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) {
                        modal.hide();
                    } else {
                        // Si pas d'instance, créer une nouvelle et la fermer
                        const newModal = new bootstrap.Modal(modalEl);
                        newModal.hide();
                    }
                }
                
                // Réinitialiser le flag après un court délai pour laisser le temps à la restauration
                setTimeout(() => {
                    isRestoringValue = false;
                }, 100);
            });
            
            // Réinitialiser pendingEdit quand la modal est fermée
            document.getElementById('editConfirmModal').addEventListener('hidden.bs.modal', function() {
                // Réactiver les boutons au cas où ils seraient désactivés
                const confirmBtn = document.getElementById('confirmEditBtn');
                const cancelBtn = document.getElementById('cancelEditBtn');
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = 'Enregistrer';
                }
                if (cancelBtn) {
                    cancelBtn.disabled = false;
                }
                
                // Si la modal est fermée sans confirmation (via backdrop ou ESC), restaurer l'ancienne valeur
                // Note: Si c'est via le bouton annuler, la valeur est déjà restaurée, donc on vérifie
                if (pendingEdit && pendingEdit.cell) {
                    // Activer le flag de restauration
                    isRestoringValue = true;
                    
                    // Vérifier si la valeur actuelle est différente de l'ancienne (donc pas encore restaurée)
                    const currentValue = pendingEdit.cell.getValue();
                    if (currentValue !== pendingEdit.oldValue) {
                        pendingEdit.cell.setValue(pendingEdit.oldValue);
                    }
                    
                    // Réinitialiser le flag après un court délai
                    setTimeout(() => {
                        isRestoringValue = false;
                    }, 100);
                }
                pendingEdit = null;
            });
            
            // Écouter les changements de largeur de colonnes
            materielTable.on("columnResized", function(column) {
                // Sauvegarder toutes les largeurs de colonnes
                const widths = {};
                materielTable.getColumns().forEach(col => {
                    if (col.getField()) {
                        widths[col.getField()] = col.getWidth();
                    }
                });
                localStorage.setItem('materiel_v2_column_widths', JSON.stringify(widths));
                console.log('Largeurs de colonnes sauvegardées:', widths);
            });
        
            console.log('Table Tabulator créée avec succès');
            console.log('Nombre de lignes:', materielTable.getRows().length);
        } catch (error) {
            console.error('Erreur lors de la création de la table Tabulator:', error);
            const tableContainer = document.getElementById('materiel-tabulator-table');
            if (tableContainer) {
                tableContainer.innerHTML = '<div class="alert alert-danger">Erreur lors de l\'initialisation de la table: ' + error.message + '</div>';
            }
        }
    } else {
        console.log('Aucune donnée à afficher');
        // Masquer la carte Tabulator
        const tabulatorCard = document.getElementById('tabulator-card');
        if (tabulatorCard) {
            tabulatorCard.style.display = 'none';
        }
        
        // Afficher un message si pas de données
        const tableContainer = document.getElementById('materiel-tabulator-table');
        if (tableContainer) {
            tableContainer.innerHTML = 
                '<div class="text-center py-5"><p class="text-muted">Aucun matériel à afficher</p></div>';
        } else {
            console.error('Le conteneur #materiel-tabulator-table n\'existe pas pour afficher le message');
        }
    }
});

// Initialiser le menu de sélection des colonnes
function initColumnMenu() {
    const columnCheckboxes = document.getElementById('columnCheckboxes');
    columnCheckboxes.innerHTML = '';
    
    allColumns.forEach(col => {
        const listItem = document.createElement('div');
        listItem.className = 'form-check mb-2';
        listItem.innerHTML = `
            <input class="form-check-input column-checkbox" 
                   type="checkbox" 
                   id="col_${col.field}" 
                   data-field="${col.field}"
                   ${col.visible ? 'checked' : ''}>
            <label class="form-check-label" for="col_${col.field}">
                ${col.title}
            </label>
        `;
        columnCheckboxes.appendChild(listItem);
    });
}

// Ouvrir le menu de colonnes
function openColumnMenu() {
    const modal = new bootstrap.Modal(document.getElementById('columnMenuModal'));
    modal.show();
}

// Appliquer la visibilité des colonnes
function applyColumnVisibility() {
    const checkboxes = document.querySelectorAll('.column-checkbox');
    const columnVisibility = {};
    
    checkboxes.forEach(checkbox => {
        const field = checkbox.getAttribute('data-field');
        columnVisibility[field] = checkbox.checked;
        
        // Mettre à jour la colonne dans Tabulator
        if (materielTable) {
            materielTable.showColumn(field);
            if (!checkbox.checked) {
                materielTable.hideColumn(field);
            }
        }
    });
    
    // Sauvegarder dans localStorage
    localStorage.setItem('materiel_v2_column_visibility', JSON.stringify(columnVisibility));
    
    // Fermer le modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('columnMenuModal'));
    modal.hide();
}

// Fonction pour mettre à jour les sites selon le client sélectionné ET soumettre le formulaire
function updateSitesAndSubmit() {
    const clientId = document.getElementById('client_id').value;
    console.log('updateSitesAndSubmit appelé avec clientId:', clientId);
    
    if (clientId) {
        const url = '<?= BASE_URL ?>materiel/get_sites?client_id=' + clientId;
        console.log('URL de la requête:', url);
        
        fetch(url)
            .then(response => {
                console.log('Réponse reçue:', response);
                if (!response.ok) {
                    throw new Error('Erreur HTTP: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Données reçues:', data);
                const siteSelect = document.getElementById('site_id');
                siteSelect.innerHTML = '<option value="">Tous les sites</option>';
                
                if (Array.isArray(data)) {
                    data.forEach(site => {
                        const option = document.createElement('option');
                        option.value = site.id;
                        option.textContent = site.name;
                        siteSelect.appendChild(option);
                    });
                }
                
                // Réinitialiser les salles
                document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
                
                // Soumettre le formulaire après la mise à jour
                document.getElementById('filterForm').submit();
            })
            .catch(error => {
                console.error('Erreur lors de la mise à jour des sites:', error);
                alert('Erreur lors de la mise à jour des sites: ' + error.message);
            });
    } else {
        document.getElementById('site_id').innerHTML = '<option value="">Tous les sites</option>';
        document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
        
        // Soumettre le formulaire même si aucun client n'est sélectionné
        document.getElementById('filterForm').submit();
    }
}

// Fonction pour mettre à jour les salles selon le site sélectionné ET soumettre le formulaire
function updateRoomsAndSubmit() {
    const siteId = document.getElementById('site_id').value;
    console.log('updateRoomsAndSubmit appelé avec siteId:', siteId);
    
    if (siteId) {
        const url = '<?= BASE_URL ?>materiel/get_rooms?site_id=' + siteId;
        console.log('URL de la requête:', url);
        
        fetch(url)
            .then(response => {
                console.log('Réponse reçue:', response);
                if (!response.ok) {
                    throw new Error('Erreur HTTP: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Données reçues:', data);
                const roomSelect = document.getElementById('salle_id');
                roomSelect.innerHTML = '<option value="">Toutes les salles</option>';
                
                if (Array.isArray(data)) {
                    data.forEach(room => {
                        const option = document.createElement('option');
                        option.value = room.id;
                        option.textContent = room.name;
                        roomSelect.appendChild(option);
                    });
                }
                
                // Soumettre le formulaire après la mise à jour
                document.getElementById('filterForm').submit();
            })
            .catch(error => {
                console.error('Erreur lors de la mise à jour des salles:', error);
                alert('Erreur lors de la mise à jour des salles: ' + error.message);
            });
    } else {
        document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
        
        // Soumettre le formulaire même si aucun site n'est sélectionné
        document.getElementById('filterForm').submit();
    }
}


</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?>
