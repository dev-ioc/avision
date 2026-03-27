<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue d'import/export en masse de matériel
 * Permet d'importer/exporter plusieurs matériels en une fois avec localisation pré-sélectionnée
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Import/Export en Masse - Matériel',
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
                <i class="bi bi-upload me-2"></i>Import/Export en Masse - Matériel
            </h4>
            <p class="text-muted mb-0">
                Importez ou exportez plusieurs matériels en une seule opération
            </p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>materiel" class="btn btn-secondary">
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

    <div class="row">
        <!-- Colonne gauche : Sélection et opérations -->
        <div class="col-lg-8">
            <!-- Sélection de la localisation -->
            <div class="card mb-4">
                <div class="card-header bg-body-secondary border-bottom">
                    <h6 class="mb-0 text-body">
                        <i class="bi bi-geo-alt me-2"></i>Sélection de la localisation
                    </h6>
                </div>
                <div class="card-body">
                    <form id="locationForm" method="GET" action="<?= BASE_URL ?>materiel_bulk">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="client_id" class="form-label fw-bold">
                                    <i class="bi bi-building me-2"></i>Client *
                                </label>
                                <select class="form-select bg-body text-body" id="client_id" name="client_id" required onchange="this.form.submit()">
                                    <option value="">Sélectionner un client</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?= $client['id'] ?>" <?= $selectedClientId == $client['id'] ? 'selected' : '' ?>>
                                            <?= h($client['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="site_id" class="form-label fw-bold">
                                    <i class="bi bi-geo-alt me-2"></i>Site (optionnel)
                                </label>
                                <select class="form-select bg-body text-body" id="site_id" name="site_id" onchange="this.form.submit()">
                                    <option value="">Tous les sites</option>
                                    <?php if (!empty($sites)): ?>
                                        <?php foreach ($sites as $site): ?>
                                            <option value="<?= $site['id'] ?>" <?= $selectedSiteId == $site['id'] ? 'selected' : '' ?>>
                                                <?= h($site['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Opérations d'import/export -->
            <div class="card mb-4">
                <div class="card-header bg-body-secondary border-bottom">
                    <h6 class="mb-0 text-body">
                        <i class="bi bi-arrow-left-right me-2"></i>Opérations
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Import -->
                        <div class="col-md-6">
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">
                                        <i class="bi bi-upload me-2"></i>Import en masse
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">
                                        Importez plusieurs matériels depuis un fichier Excel. 
                                        Les lignes avec un ID matériel vide créeront de nouveaux enregistrements.
                                    </p>
                                    
                                    <?php if ($selectedClientId): ?>
                                        <form action="<?= BASE_URL ?>materiel_bulk/validate_import" method="POST" enctype="multipart/form-data">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="client_id" value="<?= $selectedClientId ?>">
                                            <?php if ($selectedSiteId): ?>
                                                <input type="hidden" name="site_id" value="<?= $selectedSiteId ?>">
                                            <?php endif; ?>
                                            
                                            <div class="mb-3">
                                                <label for="excel_file" class="form-label fw-bold">
                                                    <i class="bi bi-file-earmark-excel me-2"></i>Fichier Excel
                                                </label>
                                                <input type="file" class="form-control" id="excel_file" name="excel_file" 
                                                       accept=".xlsx,.xls" required>
                                                <div class="form-text">Formats acceptés : .xlsx, .xls</div>
                                            </div>
                                            
                                            <div class="d-grid gap-2">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-upload me-2"></i>Importer
                                                </button>
                                                <!-- Template téléchargement masqué temporairement -->
                                                <!-- <a href="<?= BASE_URL ?>materiel_bulk/download_template" class="btn btn-outline-primary">
                                                    <i class="bi bi-download me-2"></i>Télécharger le template
                                                </a> -->
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <div class="alert alert-warning mb-0">
                                            <i class="bi bi-exclamation-triangle me-2"></i>
                                            Veuillez sélectionner un client pour importer du matériel.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Export -->
                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <i class="bi bi-download me-2"></i>Export en masse
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">
                                        Exportez tous les matériels du client/site sélectionné vers un fichier Excel.
                                        Le fichier contiendra les IDs pour permettre les mises à jour.
                                    </p>
                                    
                                    <?php if ($selectedClientId): ?>
                                        <div class="d-grid gap-2">
                                            <a href="<?= BASE_URL ?>materiel_bulk/export?client_id=<?= $selectedClientId ?><?= $selectedSiteId ? '&site_id=' . $selectedSiteId : '' ?>" 
                                               class="btn btn-success">
                                                <i class="bi bi-download me-2"></i>Exporter
                                            </a>
                                            <small class="text-muted">
                                                <i class="bi bi-info-circle me-1"></i>
                                                Le fichier contiendra tous les matériels du client sélectionné.
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning mb-0">
                                            <i class="bi bi-exclamation-triangle me-2"></i>
                                            Veuillez sélectionner un client pour exporter du matériel.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Colonne droite : Tableau des salles -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-body-secondary border-bottom">
                    <h6 class="mb-0 text-body">
                        <i class="bi bi-list-ul me-2"></i>Salles disponibles
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($selectedClientId): ?>
                        <?php if (!empty($salles)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom</th>
                                            <th>Site</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($salles as $salle): ?>
                                            <tr>
                                                <td class="fw-bold text-primary"><?= $salle['id'] ?></td>
                                                <td><?= h($salle['name']) ?></td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($salle['site_name'] ?? 'N/A') ?>
                                                    </small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="alert alert-info mt-3 mb-0">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Utilisez ces IDs de salle</strong> dans votre fichier Excel pour affecter correctement le matériel.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Aucune salle trouvée pour ce client/site. 
                                Veuillez d'abord créer des salles avant d'importer du matériel.
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            Sélectionnez un client pour voir la liste des salles disponibles.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Instructions détaillées -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-body-secondary border-bottom">
                    <h6 class="mb-0 text-body">
                        <i class="bi bi-question-circle me-2"></i>Instructions d'utilisation
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary">
                                <i class="bi bi-upload me-2"></i>Pour l'import :
                            </h6>
                            <ul class="small">
                                <li>Téléchargez d'abord le template Excel</li>
                                <li>Remplissez les colonnes avec vos données</li>
                                <li><strong>ID Salle</strong> : Utilisez les IDs affichés dans le tableau de droite</li>
                                <li><strong>ID Matériel</strong> : Laissez vide pour créer un nouveau matériel</li>
                                                                 <li><strong>ID Matériel</strong> : Remplissez pour mettre à jour un matériel existant</li>
                                 <li>Seul l'ID de salle est obligatoire</li>
                                 <li>Uploadez le fichier et cliquez sur "Importer"</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-success">
                                <i class="bi bi-download me-2"></i>Pour l'export :
                            </h6>
                            <ul class="small">
                                <li>Sélectionnez un client (et optionnellement un site)</li>
                                <li>Cliquez sur "Exporter" pour télécharger le fichier Excel</li>
                                <li>Le fichier contiendra tous les matériels avec leurs IDs</li>
                                <li>Vous pouvez modifier le fichier et le réimporter</li>
                                <li>Les lignes avec ID Matériel seront mises à jour</li>
                                <li>Les nouvelles lignes (sans ID) créeront de nouveaux matériels</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit du formulaire de sélection de client
    document.getElementById('client_id').addEventListener('change', function() {
        document.getElementById('locationForm').submit();
    });
    
    // Auto-submit du formulaire de sélection de site
    document.getElementById('site_id').addEventListener('change', function() {
        document.getElementById('locationForm').submit();
    });
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
