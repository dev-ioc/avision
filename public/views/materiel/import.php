<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue d'import de matériel par Excel
 * Permet d'importer plusieurs matériels en une fois avec localisation pré-sélectionnée
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Import Matériel',
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
$selectedSalleId = $_GET['salle_id'] ?? '';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec titre et bouton de retour -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="bi bi-file-earmark-excel me-2 me-1"></i>Import de Matériel
                    </h4>
                    <p class="text-muted mb-0">Importez plusieurs matériels en une fois via un fichier Excel</p>
                </div>
                <div>
                    <?php
                    // Construire l'URL de retour avec les paramètres de filtres
                    $returnParams = [];
                    if (!empty($selectedClientId)) {
                        $returnParams['client_id'] = $selectedClientId;
                    }
                    if (!empty($selectedSiteId)) {
                        $returnParams['site_id'] = $selectedSiteId;
                    }
                    if (!empty($selectedSalleId)) {
                        $returnParams['salle_id'] = $selectedSalleId;
                    }
                    
                    $returnUrl = BASE_URL . 'materiel';
                    if (!empty($returnParams)) {
                        $returnUrl .= '?' . http_build_query($returnParams);
                    }
                    ?>
                    <a href="<?= $returnUrl ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2 me-1"></i>Retour à la liste
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-upload me-2 me-1"></i>Import de Matériel
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Instructions -->
                    <div class="alert alert-info mb-4">
                        <h6 class="alert-heading">
                            <i class="bi bi-info-circle me-2 me-1"></i>Instructions d'import
                        </h6>
                        <ul class="mb-0">
                            <li>Sélectionnez d'abord la localisation où importer le matériel (Client > Site > Salle)</li>
                            <li>Téléchargez le template Excel pour voir le format attendu</li>
                            <li>Remplissez le fichier Excel avec vos données</li>
                            <li>Uploadez le fichier rempli pour importer le matériel</li>
                            <li><strong>Important :</strong> Tous les matériels importés seront assignés à la localisation sélectionnée</li>
                        </ul>
                    </div>

                    <!-- Sélecteurs de localisation -->
                    <form method="post" action="<?= BASE_URL ?>
                        <?= csrf_field() ?>materiel/process_import" enctype="multipart/form-data" id="importForm">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label for="client_id" class="form-label fw-bold">Client <span class="text-danger">*</span></label>
                                <select class="form-select" id="client_id" name="client_id" required onchange="updateSites()">
                                    <option value="">Sélectionnez un client</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?= $client['id'] ?>" <?= $selectedClientId == $client['id'] ? 'selected' : '' ?>>
                                            <?= h($client['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="site_id" class="form-label fw-bold">Site <span class="text-danger">*</span></label>
                                <select class="form-select" id="site_id" name="site_id" required onchange="updateRooms()">
                                    <option value="">Sélectionnez un site</option>
                                    <?php foreach ($sites as $site): ?>
                                        <option value="<?= $site['id'] ?>" <?= $selectedSiteId == $site['id'] ? 'selected' : '' ?>>
                                            <?= h($site['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="salle_id" class="form-label fw-bold">Salle <span class="text-danger">*</span></label>
                                <select class="form-select" id="salle_id" name="salle_id" required>
                                    <option value="">Sélectionnez une salle</option>
                                    <?php foreach ($salles as $salle): ?>
                                        <option value="<?= $salle['id'] ?>" <?= $selectedSalleId == $salle['id'] ? 'selected' : '' ?>>
                                            <?= h($salle['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Téléchargement du template -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="card-title mb-0">
                                            <i class="bi bi-download me-2 me-1"></i>Template Excel
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-3">Téléchargez le template Excel pour voir le format attendu et les colonnes disponibles :</p>
                                        <a href="<?= BASE_URL ?>materiel/download_template" class="btn btn-primary">
                                            <i class="bi bi-file-earmark-excel me-2 me-1"></i>Télécharger le template
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Upload du fichier -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="card-title mb-0">
                                            <i class="bi bi-upload me-2 me-1"></i>Fichier Excel à importer
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="excel_file" class="form-label fw-bold">Fichier Excel <span class="text-danger">*</span></label>
                                            <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".xlsx,.xls" required>
                                            <div class="form-text">Formats acceptés : .xlsx, .xls</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Boutons d'action -->
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-success" id="submitBtn">
                                        <i class="bi bi-upload me-2 me-1"></i>Importer le matériel
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                        <i class="fas fa-undo me-2"></i>Réinitialiser
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Fonction pour mettre à jour les sites selon le client sélectionné
function updateSites() {
    const clientId = document.getElementById('client_id').value;
    const siteSelect = document.getElementById('site_id');
    const roomSelect = document.getElementById('salle_id');
    
    // Réinitialiser les salles
    roomSelect.innerHTML = '<option value="">Sélectionnez une salle</option>';
    
    if (clientId) {
        const url = '<?= BASE_URL ?>materiel/get_sites?client_id=' + clientId;
        
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur HTTP: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                siteSelect.innerHTML = '<option value="">Sélectionnez un site</option>';
                
                if (Array.isArray(data)) {
                    data.forEach(site => {
                        const option = document.createElement('option');
                        option.value = site.id;
                        option.textContent = site.name;
                        siteSelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Erreur lors de la mise à jour des sites:', error);
                alert('Erreur lors de la mise à jour des sites: ' + error.message);
            });
    } else {
        siteSelect.innerHTML = '<option value="">Sélectionnez un site</option>';
    }
}

// Fonction pour mettre à jour les salles selon le site sélectionné
function updateRooms() {
    const siteId = document.getElementById('site_id').value;
    const roomSelect = document.getElementById('salle_id');
    
    if (siteId) {
        const url = '<?= BASE_URL ?>materiel/get_rooms?site_id=' + siteId;
        
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur HTTP: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                roomSelect.innerHTML = '<option value="">Sélectionnez une salle</option>';
                
                if (Array.isArray(data)) {
                    data.forEach(room => {
                        const option = document.createElement('option');
                        option.value = room.id;
                        option.textContent = room.name;
                        roomSelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Erreur lors de la mise à jour des salles:', error);
                alert('Erreur lors de la mise à jour des salles: ' + error.message);
            });
    } else {
        roomSelect.innerHTML = '<option value="">Sélectionnez une salle</option>';
    }
}

// Fonction pour réinitialiser le formulaire
function resetForm() {
    document.getElementById('importForm').reset();
    document.getElementById('site_id').innerHTML = '<option value="">Sélectionnez un site</option>';
    document.getElementById('salle_id').innerHTML = '<option value="">Sélectionnez une salle</option>';
}

// Validation du formulaire avant soumission
document.getElementById('importForm').addEventListener('submit', function(e) {
    const clientId = document.getElementById('client_id').value;
    const siteId = document.getElementById('site_id').value;
    const salleId = document.getElementById('salle_id').value;
    const fileInput = document.getElementById('excel_file');
    
    if (!clientId || !siteId || !salleId) {
        e.preventDefault();
        alert('Veuillez sélectionner une localisation complète (Client, Site, Salle).');
        return;
    }
    
    if (!fileInput.files[0]) {
        e.preventDefault();
        alert('Veuillez sélectionner un fichier Excel à importer.');
        return;
    }
    
    // Désactiver le bouton pour éviter les soumissions multiples
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('submitBtn').innerHTML = '<i class="bi bi-arrow-clockwise spin me-2 me-1"></i>Import en cours...';
});
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 