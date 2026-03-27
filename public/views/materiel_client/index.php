<?php
require_once __DIR__ . '/../../includes/functions.php';


/**
 * Vue de la liste du matériel client
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
    'Mon Matériel',
    'materiel_client'
);

// Définir la page courante pour le menu
$currentPage = 'materiel_client';

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

<style>
.attachments-row {
    background-color: var(--bs-body-bg);
}

.attachments-row .card {
    border: 1px solid var(--bs-border-color);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.attachments-list .list-group-item {
    border: 1px solid var(--bs-border-color);
    border-radius: 0.375rem;
    transition: all 0.2s ease-in-out;
    background-color: var(--bs-body-bg);
    color: var(--bs-body-color);
    padding: 0.5rem;
    margin-bottom: 0.5rem;
}

.attachments-list .list-group-item:hover {
    background-color: var(--bs-secondary-bg);
    border-color: var(--bs-primary);
    box-shadow: 0 2px 4px rgba(var(--bs-primary-rgb), 0.15);
}

.attachments-list .col-md-6:nth-child(odd) .list-group-item {
    margin-right: 0.25rem;
}

.attachments-list .col-md-6:nth-child(even) .list-group-item {
    margin-left: 0.25rem;
}

.btn-action {
    transition: all 0.2s ease-in-out;
}

.btn-action:hover {
    transform: scale(1.05);
}

.attachments-row td {
    border-top: none;
    border-bottom: 1px solid var(--bs-border-color);
}

.min-w-0 {
    min-width: 0;
}

.attachments-list .btn-group {
    flex-shrink: 0;
}

/* Styles pour la zone d'upload */
.upload-zone {
    transition: all 0.3s ease;
}

.upload-zone .card {
    border-style: dashed !important;
    transition: all 0.3s ease;
}

.upload-zone .card:hover {
    border-color: var(--bs-primary) !important;
    background-color: var(--bs-primary-bg-subtle) !important;
}

.upload-zone.dragover .card {
    border-color: var(--bs-success) !important;
    background-color: var(--bs-success-bg-subtle) !important;
    transform: scale(1.02);
}

.upload-zone.dragover .card-body {
    background-color: var(--bs-success-bg-subtle) !important;
}

.border-dashed {
    border-style: dashed !important;
}
</style>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec titre -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="bi bi-hdd-network me-2 me-1"></i>Mon Matériel
                    </h4>
                    <p class="text-muted mb-0">Consultation du matériel de vos sites autorisés</p>
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
            <form method="get" action="" class="row g-3 align-items-end" id="filterForm">
                <div class="col-md-4">
                    <label for="site_id" class="form-label fw-bold mb-0">Site</label>
                    <select class="form-select bg-body text-body" id="site_id" name="site_id" onchange="updateRoomsAndSubmit()">
                        <option value="">Tous les sites</option>
                        <?php if (isset($sites) && is_array($sites)): ?>
                            <?php foreach ($sites as $site): ?>
                                <option value="<?= $site['id'] ?>" <?= ($filters['site_id'] ?? '') == $site['id'] ? 'selected' : '' ?>>
                                    <?= h($site['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="salle_id" class="form-label fw-bold mb-0">Salle</label>
                    <select class="form-select bg-body text-body" id="salle_id" name="salle_id" onchange="document.getElementById('filterForm').submit();">
                        <option value="">Toutes les salles</option>
                        <?php foreach ($salles as $salle): ?>
                            <option value="<?= $salle['id'] ?>" <?= ($filters['salle_id'] ?? '') == $salle['id'] ? 'selected' : '' ?>>
                                <?= h($salle['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4 d-flex align-items-end">
                    <a href="<?= BASE_URL ?>materiel_client" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg me-2 me-1"></i>Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste du matériel organisée -->
    <?php if (empty($materiel_organise)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-hdd-network fa-3x text-muted mb-3 me-1"></i>
                <h5 class="text-muted">Aucun matériel trouvé</h5>
                <p class="text-muted mb-3">Aucun matériel ne correspond aux critères sélectionnés.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($materiel_organise as $client_nom => $sites): ?>
            <div class="card mb-4">
                <div class="card-header bg-body-secondary">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-building me-2 text-primary me-1"></i>
                        <?= h($client_nom) ?>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($sites as $site_nom => $salles): ?>
                        <div class="border-bottom">
                            <div class="p-3 bg-body-secondary bg-opacity-10">
                                <h6 class="mb-0">
                                    <i class="bi bi-geo-alt me-2 text-success me-1"></i>
                                    <?= h($site_nom) ?>
                                </h6>
                            </div>
                            <?php foreach ($salles as $salle_nom => $materiels): ?>
                                <div class="border-bottom">
                                    <div class="p-3">
                                        <h6 class="mb-3">
                                            <i class="bi bi-door-open me-2 text-info me-1"></i>
                                            <?= h($salle_nom) ?>
                                            <span class="badge bg-secondary ms-2"><?= count($materiels) ?> équipement(s)</span>
                                        </h6>
                                        
                                        <div class="table-responsive">
                                            <table class="table table-hover table-sm mb-0">
                                                <thead class="bg-body-secondary">
                                                    <tr>
                                                        <th>Équipement</th>
                                                        <th>Type</th>
                                                        <th>S/N</th>
                                                        <th>Firmware</th>
                                                        <th>IP</th>
                                                        <th>MAC</th>
                                                        <th>Expiration</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($materiels as $materiel): ?>
                                                        <tr>
                                                            <td class="<?= (isset($visibilites_champs[$materiel['id']]['marque']) && !$visibilites_champs[$materiel['id']]['marque']) || (isset($visibilites_champs[$materiel['id']]['modele']) && !$visibilites_champs[$materiel['id']]['modele']) ? 'bg-warning bg-opacity-25' : '' ?>">
                                                                <?php
                                                                // Construire les paramètres de filtres pour les liens
                                                                $filterParams = [];
                                                                if (!empty($filters['site_id'])) {
                                                                    $filterParams['site_id'] = $filters['site_id'];
                                                                }
                                                                if (!empty($filters['salle_id'])) {
                                                                    $filterParams['salle_id'] = $filters['salle_id'];
                                                                }
                                                                
                                                                $viewUrl = BASE_URL . 'materiel_client/view/' . $materiel['id'];
                                                                if (!empty($filterParams)) {
                                                                    $viewUrl .= '?' . http_build_query($filterParams);
                                                                }
                                                                ?>
                                                                <a href="<?= $viewUrl ?>" class="text-decoration-none">
                                                                    <?php if ((isset($visibilites_champs[$materiel['id']]['marque']) && $visibilites_champs[$materiel['id']]['marque']) && (isset($visibilites_champs[$materiel['id']]['modele']) && $visibilites_champs[$materiel['id']]['modele'])): ?>
                                                                        <div class="fw-bold"><?= htmlspecialchars($materiel['marque'] ?? 'Marque non définie') ?></div>
                                                                        <small class="text-muted"><?= htmlspecialchars($materiel['modele'] ?? 'Modèle non défini') ?></small>
                                                                    <?php else: ?>
                                                                        <div class="fw-bold text-muted">---</div>
                                                                        <small class="text-muted">---</small>
                                                                    <?php endif; ?>
                                                                </a>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($materiel['type_nom'])): ?>
                                                                    <?= htmlspecialchars($materiel['type_nom']) ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">---</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="<?= (isset($visibilites_champs[$materiel['id']]['numero_serie']) && !$visibilites_champs[$materiel['id']]['numero_serie']) ? 'bg-warning bg-opacity-25' : '' ?>">
                                                                <?php if (isset($visibilites_champs[$materiel['id']]['numero_serie']) && $visibilites_champs[$materiel['id']]['numero_serie'] && !empty($materiel['numero_serie'])): ?>
                                                                    <?= h($materiel['numero_serie']) ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">---</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="<?= (isset($visibilites_champs[$materiel['id']]['version_firmware']) && !$visibilites_champs[$materiel['id']]['version_firmware']) ? 'bg-warning bg-opacity-25' : '' ?>">
                                                                <?php if (isset($visibilites_champs[$materiel['id']]['version_firmware']) && $visibilites_champs[$materiel['id']]['version_firmware'] && !empty($materiel['version_firmware'])): ?>
                                                                    <?= h($materiel['version_firmware']) ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">---</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="<?= (isset($visibilites_champs[$materiel['id']]['adresse_ip']) && !$visibilites_champs[$materiel['id']]['adresse_ip']) ? 'bg-warning bg-opacity-25' : '' ?>">
                                                                <?php if (isset($visibilites_champs[$materiel['id']]['adresse_ip']) && $visibilites_champs[$materiel['id']]['adresse_ip'] && !empty($materiel['adresse_ip'])): ?>
                                                                    <?= h($materiel['adresse_ip']) ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">---</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="<?= (isset($visibilites_champs[$materiel['id']]['adresse_mac']) && !$visibilites_champs[$materiel['id']]['adresse_mac']) ? 'bg-warning bg-opacity-25' : '' ?>">
                                                                <?php if (isset($visibilites_champs[$materiel['id']]['adresse_mac']) && $visibilites_champs[$materiel['id']]['adresse_mac'] && !empty($materiel['adresse_mac'])): ?>
                                                                    <?= h($materiel['adresse_mac']) ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">---</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="<?= (isset($visibilites_champs[$materiel['id']]['date_fin_maintenance']) && !$visibilites_champs[$materiel['id']]['date_fin_maintenance']) || (isset($visibilites_champs[$materiel['id']]['date_fin_garantie']) && !$visibilites_champs[$materiel['id']]['date_fin_garantie']) ? 'bg-warning bg-opacity-25' : '' ?>">
                                                                <?php 
                                                                $today = new DateTime();
                                                                $maintenance_info = [];
                                                                $has_visible_dates = false;
                                                                
                                                                // Vérifier si la maintenance est visible
                                                                if (isset($visibilites_champs[$materiel['id']]['date_fin_maintenance']) && $visibilites_champs[$materiel['id']]['date_fin_maintenance'] && !empty($materiel['date_fin_maintenance'])) {
                                                                    $maintenance_date = new DateTime($materiel['date_fin_maintenance']);
                                                                    $maintenance_class = $maintenance_date < $today ? 'danger' : ($maintenance_date->diff($today)->days < 30 ? 'warning' : 'success');
                                                                    $maintenance_info[] = '<div class="d-flex justify-content-between align-items-center"><small class="text-muted">Maintenance</small> <span class="text-' . $maintenance_class . '">' . formatDateFrench($materiel['date_fin_maintenance']) . '</span></div>';
                                                                    $has_visible_dates = true;
                                                                }
                                                                
                                                                // Vérifier si la garantie est visible
                                                                if (isset($visibilites_champs[$materiel['id']]['date_fin_garantie']) && $visibilites_champs[$materiel['id']]['date_fin_garantie'] && !empty($materiel['date_fin_garantie'])) {
                                                                    $garantie_date = new DateTime($materiel['date_fin_garantie']);
                                                                    $garantie_class = $garantie_date < $today ? 'danger' : ($garantie_date->diff($today)->days < 30 ? 'warning' : 'success');
                                                                    $maintenance_info[] = '<div class="d-flex justify-content-between align-items-center"><small class="text-muted">Garantie</small> <span class="text-' . $garantie_class . '">' . formatDateFrench($materiel['date_fin_garantie']) . '</span></div>';
                                                                    $has_visible_dates = true;
                                                                }
                                                                
                                                                if ($has_visible_dates) {
                                                                    echo implode('', $maintenance_info);
                                                                } else {
                                                                    echo '<span class="text-muted">---</span>';
                                                                }
                                                                ?>
                                                            </td>

                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
// Variable globale pour l'URL de base
const baseUrl = '<?= BASE_URL ?>';

// Fonction pour mettre à jour les salles selon le site sélectionné ET soumettre le formulaire
function updateRoomsAndSubmit() {
    const siteId = document.getElementById('site_id').value;
    console.log('updateRoomsAndSubmit appelé avec siteId:', siteId);
    
    if (siteId) {
        const url = '<?= BASE_URL ?>materiel_client/get_rooms?site_id=' + siteId;
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