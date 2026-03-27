<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de suppression en masse de matériel
 * Champs de sélection (client, site, salle) + liste avec cases à cocher
 */

if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Suppression en masse - Matériel',
    'materiel'
);

$currentPage = 'materiel';

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

$clients = $clients ?? [];
$sites = $sites ?? [];
$salles = $salles ?? [];
$materiel_list = $materiel_list ?? [];
$filters = $filters ?? [];

$selectedClientId = $filters['client_id'] ?? '';
$selectedSiteId = $filters['site_id'] ?? '';
$selectedSalleId = $filters['salle_id'] ?? '';

// Organiser le matériel par site / salle (comme index, sans accordéon)
$materiel_organise = [];
foreach ($materiel_list as $m) {
    $site_nom = $m['site_nom'] ?? 'Sans site';
    $salle_nom = $m['salle_nom'] ?? 'Sans salle';
    if (!isset($materiel_organise[$site_nom])) {
        $materiel_organise[$site_nom] = [];
    }
    if (!isset($materiel_organise[$site_nom][$salle_nom])) {
        $materiel_organise[$site_nom][$salle_nom] = [];
    }
    $materiel_organise[$site_nom][$salle_nom][] = $m;
}
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bi bi-trash me-2"></i>Suppression en masse - Matériel
            </h4>
            <p class="text-muted mb-0">
                Sélectionnez un client, un site et une salle puis cochez les matériels à supprimer
            </p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>materiel" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Retour
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['warning'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= $_SESSION['warning'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['warning']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Filtres (même logique que vue matériel index) -->
    <div class="card mb-4">
        <div class="card-header bg-body-secondary border-bottom">
            <h6 class="mb-0 text-body">
                <i class="bi bi-geo-alt me-2"></i>Filtres
            </h6>
        </div>
        <div class="card-body">
            <form method="get" action="<?= BASE_URL ?>materiel_bulk/bulk_delete" id="filterFormBulkDelete">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="client_id" class="form-label fw-bold mb-0">Client</label>
                        <select class="form-select bg-body text-body" id="client_id" name="client_id" onchange="this.form.submit()">
                            <option value="">Tous les clients</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>" <?= $selectedClientId == $client['id'] ? 'selected' : '' ?>>
                                    <?= h($client['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="site_id" class="form-label fw-bold mb-0">Site</label>
                        <select class="form-select bg-body text-body" id="site_id" name="site_id" onchange="this.form.submit()">
                            <option value="">Tous les sites</option>
                            <?php foreach ($sites as $site): ?>
                                <option value="<?= $site['id'] ?>" <?= $selectedSiteId == $site['id'] ? 'selected' : '' ?>>
                                    <?= h($site['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="salle_id" class="form-label fw-bold mb-0">Salle</label>
                        <select class="form-select bg-body text-body" id="salle_id" name="salle_id" onchange="this.form.submit()">
                            <option value="">Toutes les salles</option>
                            <?php foreach ($salles as $salle): ?>
                                <option value="<?= $salle['id'] ?>" <?= $selectedSalleId == $salle['id'] ? 'selected' : '' ?>>
                                    <?= h($salle['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <a href="<?= BASE_URL ?>materiel_bulk/bulk_delete" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg me-1"></i>Réinitialiser
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($selectedClientId)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-funnel fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Sélectionnez un client pour afficher le matériel</h5>
                <p class="text-muted mb-0">Choisissez un client dans les filtres ci-dessus.</p>
            </div>
        </div>
    <?php elseif (empty($materiel_list)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-hdd-network fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun matériel trouvé</h5>
                <p class="text-muted mb-0">Aucun matériel ne correspond aux critères sélectionnés.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header bg-body-secondary border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-body">
                    <i class="bi bi-list-check me-2"></i>Matériel (<?= count($materiel_list) ?>)
                </h6>
                <button type="button" class="btn btn-danger" id="btnSubmitBulkDelete" disabled>
                    <i class="bi bi-trash me-2"></i>Supprimer la sélection
                </button>
            </div>
            <div class="card-body p-0">
                <form id="formBulkDelete" method="post" action="<?= BASE_URL ?>materiel_bulk/bulk_delete_execute">
                    <?= csrf_field() ?>
                    <input type="hidden" name="client_id" value="<?= (int)($filters['client_id'] ?? 0) ?>">
                    <input type="hidden" name="site_id" value="<?= (int)($filters['site_id'] ?? 0) ?>">
                    <input type="hidden" name="salle_id" value="<?= (int)($filters['salle_id'] ?? 0) ?>">

                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="align-middle" style="width: 2.5rem;">
                                        <label class="form-check mb-0 d-flex align-items-center justify-content-center">
                                            <input type="checkbox" class="form-check-input" id="selectAllBulk" title="Tout sélectionner">
                                        </label>
                                    </th>
                                    <th>Équipement</th>
                                    <th>Type</th>
                                    <th>S/N</th>
                                    <th>Firmware</th>
                                    <th>IP</th>
                                    <th class="text-end">Fiche</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materiel_organise as $site_nom => $salles): ?>
                                    <?php foreach ($salles as $salle_nom => $items): ?>
                                        <tr class="table-secondary border-top border-2">
                                            <td colspan="7" class="py-2 fw-bold">
                                                <i class="bi bi-geo-alt me-2"></i><?= h($site_nom) ?>
                                                <span class="text-muted fw-normal ms-2">/</span>
                                                <span class="ms-2"><?= h($salle_nom) ?></span>
                                                <span class="badge bg-secondary ms-2"><?= count($items) ?></span>
                                            </td>
                                        </tr>
                                        <?php foreach ($items as $m): ?>
                                            <?php
                                            $equipement = trim(($m['marque'] ?? '') . ' ' . ($m['modele'] ?? ''));
                                            if ($equipement === '') $equipement = '—';
                                            ?>
                                            <tr>
                                                <td class="align-middle text-center">
                                                    <input type="checkbox" class="form-check-input materiel-row-cb" name="ids[]" value="<?= (int)$m['id'] ?>">
                                                </td>
                                                <td class="align-middle"><?= h($equipement) ?></td>
                                                <td class="align-middle"><?= h($m['type_materiel'] ?? '—') ?></td>
                                                <td class="align-middle"><?= h($m['numero_serie'] ?? '—') ?></td>
                                                <td class="align-middle"><?= h($m['version_firmware'] ?? '—') ?></td>
                                                <td class="align-middle"><?= h($m['adresse_ip'] ?? '—') ?></td>
                                                <td class="align-middle text-end">
                                                    <a href="<?= BASE_URL ?>materiel/view/<?= (int)$m['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                        <i class="bi bi-box-arrow-up-right"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('formBulkDelete');
    if (!form) return;

    var selectAll = document.getElementById('selectAllBulk');
    var rowCbs = form.querySelectorAll('.materiel-row-cb');
    var btnSubmit = document.getElementById('btnSubmitBulkDelete');

    function updateSubmitState() {
        var checked = form.querySelectorAll('.materiel-row-cb:checked').length;
        if (btnSubmit) btnSubmit.disabled = checked === 0;
        if (selectAll) selectAll.checked = checked > 0 && checked === rowCbs.length;
        if (selectAll) selectAll.indeterminate = checked > 0 && checked < rowCbs.length;
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            rowCbs.forEach(function(cb) { cb.checked = selectAll.checked; });
            updateSubmitState();
        });
    }

    rowCbs.forEach(function(cb) {
        cb.addEventListener('change', updateSubmitState);
    });

    if (btnSubmit) {
        btnSubmit.addEventListener('click', function() {
            var checked = form.querySelectorAll('.materiel-row-cb:checked').length;
            if (checked === 0) return;
            if (!confirm('Êtes-vous sûr de vouloir supprimer les ' + checked + ' matériel(s) sélectionné(s) ? Cette action est irréversible.')) return;
            form.submit();
        });
    }

    updateSubmitState();
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
