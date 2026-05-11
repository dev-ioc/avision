<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue d'ajout de matériel
 * Formulaire de création avec gestion de la visibilité des champs
 */

// Vérifier si l'utilisateur est connecté et a les permissions
if (!isset($_SESSION['user']) || !canModifyMateriel()) {
    $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour ajouter du matériel.";
    header('Location: ' . BASE_URL . 'dashboard');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Ajouter du Matériel',
    'materiel'
);

// Définir la page courante pour le menu
$currentPage = 'materiel';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec titre et bouton de retour -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="bi bi-plus-circle me-2 me-1"></i>Ajouter du Matériel
                    </h4>
                    <p class="text-muted mb-0">Création d'un nouvel équipement</p>
                </div>
                <div>
                    <?php
                    // Construire l'URL de retour avec les paramètres de filtres
                    $returnParams = [];
                    if (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
                        $returnParams['client_id'] = $_GET['client_id'];
                    }
                    if (isset($_GET['site_id']) && !empty($_GET['site_id'])) {
                        $returnParams['site_id'] = $_GET['site_id'];
                    }
                    if (isset($_GET['building_id']) && !empty($_GET['building_id'])) {
                        $returnParams['building_id'] = $_GET['building_id'];
                    }
                    if (isset($_GET['salle_id']) && !empty($_GET['salle_id'])) {
                        $returnParams['salle_id'] = $_GET['salle_id'];
                    }
                    
                    $returnUrl = BASE_URL . 'materiel';
                    if (!empty($returnParams)) {
                        $returnUrl .= '?' . http_build_query($returnParams);
                    }
                    
                    // Construire l'URL pour ajouter un autre matériel avec les valeurs actuelles du formulaire
                    $addAnotherUrl = BASE_URL . 'materiel/add';
                    $formParams = [];
                    if (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
                        $formParams['client_id'] = $_GET['client_id'];
                    }
                    if (isset($_GET['site_id']) && !empty($_GET['site_id'])) {
                        $formParams['site_id'] = $_GET['site_id'];
                    }
                    if (isset($_GET['building_id']) && !empty($_GET['building_id'])) {
                        $formParams['building_id'] = $_GET['building_id'];
                    }
                    if (isset($_GET['salle_id']) && !empty($_GET['salle_id'])) {
                        $formParams['salle_id'] = $_GET['salle_id'];
                    }
                    
                    if (!empty($formParams)) {
                        $addAnotherUrl .= '?' . http_build_query($formParams);
                    }
                    ?>
                    <button type="button" class="btn btn-primary me-2" onclick="addAnotherMateriel()">
                        <i class="bi bi-plus me-2 me-1"></i>Ajouter un autre matériel
                    </button>
                    <a href="<?= $returnUrl ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-2 me-1"></i>Retour à la liste
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulaire d'ajout -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-hdd-network me-2 me-1"></i>Informations du Matériel
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= BASE_URL ?>materiel/store" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <!-- Champs cachés pour conserver les filtres -->
                <?php if (isset($_GET['client_id']) && !empty($_GET['client_id'])): ?>
                    <input type="hidden" name="return_client_id" value="<?= h($_GET['client_id']) ?>">
                <?php endif; ?>
                <?php if (isset($_GET['site_id']) && !empty($_GET['site_id'])): ?>
                    <input type="hidden" name="return_site_id" value="<?= h($_GET['site_id']) ?>">
                <?php endif; ?>
                <?php if (isset($_GET['building_id']) && !empty($_GET['building_id'])): ?>
                    <input type="hidden" name="return_building_id" value="<?= h($_GET['building_id']) ?>">
                <?php endif; ?>
                <?php if (isset($_GET['salle_id']) && !empty($_GET['salle_id'])): ?>
                    <input type="hidden" name="return_salle_id" value="<?= h($_GET['salle_id']) ?>">
                <?php endif; ?>
                
                <div class="row">
                    <!-- Colonne gauche : Formulaire principal -->
                    <div class="col-md-8">
                        <!-- Bloc 1: Informations Générales -->
                        <div class="card mb-4">
                            <div class="card-header bg-body-secondary border-bottom">
                                <h6 class="mb-0 text-body">
                                    <i class="bi bi-info-circle me-2"></i>Informations Générales
                                </h6>
                            </div>
                            <div class="card-body">
                                <!-- Localisation -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="client_id" class="form-label fw-bold">
                                            <i class="bi bi-building me-2"></i>Client *
                                        </label>
                                        <select class="form-select bg-body text-body" id="client_id" name="client_id" required>
                                            <option value="">Sélectionner un client</option>
                                            <?php foreach ($clients as $client): ?>
                                                <option value="<?= $client['id'] ?>" <?= (isset($_GET['client_id']) && $_GET['client_id'] == $client['id']) ? 'selected' : '' ?>>
                                                    <?= h($client['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="site_id" class="form-label fw-bold">
                                            <i class="bi bi-geo-alt me-2"></i>Site *
                                        </label>
                                        <select class="form-select bg-body text-body" id="site_id" name="site_id" required>
                                            <option value="">Sélectionner un site</option>
                                            <?php if (!empty($sites)): ?>
                                                <?php foreach ($sites as $site): ?>
                                                    <option value="<?= $site['id'] ?>" <?= (isset($_GET['site_id']) && $_GET['site_id'] == $site['id']) ? 'selected' : '' ?>>
                                                        <?= h($site['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="building_id" class="form-label fw-bold">
                                            <i class="bi bi-building me-2"></i>Bâtiment *
                                        </label>
                                        <select class="form-select bg-body text-body" id="building_id" name="building_id">
                                            <option value="">Sélectionner un bâtiment</option>
                                            <?php if (!empty($buildings)): ?>
                                                <?php foreach ($buildings as $building): ?>
                                                    <option value="<?= $building['id'] ?>" <?= (isset($_GET['building_id']) && $_GET['building_id'] == $building['id']) ? 'selected' : '' ?>>
                                                        <?= h($building['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label for="salle_id" class="form-label fw-bold">
                                            <i class="bi bi-door-open me-2"></i>Salle *
                                        </label>
                                        <select class="form-select bg-body text-body" id="salle_id" name="salle_id" required>
                                            <option value="">Sélectionner une salle</option>
                                            <?php if (!empty($salles)): ?>
                                                <?php foreach ($salles as $salle): ?>
                                                    <option value="<?= $salle['id'] ?>" <?= (isset($_GET['salle_id']) && $_GET['salle_id'] == $salle['id']) ? 'selected' : '' ?>>
                                                        <?= h($salle['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Informations matériel -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="marque" class="form-label fw-bold">
                                            <i class="fas fa-tag me-2"></i>Marque *
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="marque" name="marque" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="modele" class="form-label fw-bold">
                                            <i class="fas fa-cube me-2"></i>Modèle *
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="modele" name="modele" required>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="numero_serie" class="form-label fw-bold">
                                            <i class="fas fa-barcode me-2"></i>Numéro de série
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="numero_serie" name="numero_serie">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="version_firmware" class="form-label fw-bold">
                                            <i class="fas fa-microchip me-2"></i>Version firmware
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="version_firmware" name="version_firmware">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="ancien_firmware" class="form-label fw-bold">
                                            <i class="fas fa-history me-2"></i>Ancien firmware
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="ancien_firmware" name="ancien_firmware">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="url_github" class="form-label fw-bold">
                                            <i class="fab fa-github me-2"></i>URL GitHub
                                        </label>
                                        <input type="url" class="form-control bg-body text-body" id="url_github" name="url_github" 
                                               placeholder="https://github.com/user/repo">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bloc 2: Configuration Réseau -->
                        <div class="card mb-4">
                            <div class="card-header bg-body-secondary border-bottom">
                                <h6 class="mb-0 text-body">
                                    <i class="fas fa-network-wired me-2"></i>Configuration Réseau
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="adresse_mac" class="form-label fw-bold">
                                            <i class="fas fa-wifi me-2"></i>Adresse MAC
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="adresse_mac" name="adresse_mac" 
                                               placeholder="00:11:22:33:44:55">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="adresse_ip" class="form-label fw-bold">
                                            <i class="fas fa-globe me-2"></i>Adresse IP
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="adresse_ip" name="adresse_ip" 
                                               placeholder="192.168.1.100">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="masque" class="form-label fw-bold">
                                            <i class="fas fa-mask me-2"></i>Masque réseau
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="masque" name="masque" 
                                               placeholder="255.255.255.0">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="passerelle" class="form-label fw-bold">
                                            <i class="fas fa-route me-2"></i>Passerelle
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="passerelle" name="passerelle" 
                                               placeholder="192.168.1.1">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="login" class="form-label fw-bold">
                                            <i class="fas fa-user me-2"></i>Login
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="login" name="login">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="password" class="form-label fw-bold">
                                            <i class="fas fa-lock me-2"></i>Mot de passe
                                        </label>
                                        <div class="input-group">
                                            <input type="password" class="form-control bg-body text-body" id="password" name="password">
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword" title="Afficher/Masquer le mot de passe">
                                                <i class="bi bi-eye" id="passwordIcon"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bloc 3: Audio IP -->
                        <div class="card mb-4">
                            <div class="card-header bg-body-secondary border-bottom">
                                <h6 class="mb-0 text-body">
                                    <i class="fas fa-broadcast-tower me-2"></i>Audio IP
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="ip_primaire" class="form-label fw-bold">
                                            <i class="fas fa-network-wired me-2"></i>IP Primaire
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="ip_primaire" name="ip_primaire">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="mac_primaire" class="form-label fw-bold">
                                            <i class="fas fa-wifi me-2"></i>MAC Primaire
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="mac_primaire" name="mac_primaire">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="ip_secondaire" class="form-label fw-bold">
                                            <i class="fas fa-network-wired me-2"></i>IP Secondaire
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="ip_secondaire" name="ip_secondaire">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="mac_secondaire" class="form-label fw-bold">
                                            <i class="fas fa-wifi me-2"></i>MAC Secondaire
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="mac_secondaire" name="mac_secondaire">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="stream_aes67_recu" class="form-label fw-bold">
                                            <i class="fas fa-broadcast-tower me-2"></i>Stream AES67 Reçu
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="stream_aes67_recu" name="stream_aes67_recu">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="stream_aes67_transmis" class="form-label fw-bold">
                                            <i class="fas fa-broadcast-tower me-2"></i>Stream AES67 Transmis
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="stream_aes67_transmis" name="stream_aes67_transmis">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bloc 4: Wi-Fi -->
                        <div class="card mb-4">
                            <div class="card-header bg-body-secondary border-bottom">
                                <h6 class="mb-0 text-body">
                                    <i class="fas fa-wifi me-2"></i>Wi-Fi
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="ssid" class="form-label fw-bold">
                                            <i class="fas fa-wifi me-2"></i>SSID
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="ssid" name="ssid">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="type_cryptage" class="form-label fw-bold">
                                            <i class="fas fa-shield-alt me-2"></i>Type de cryptage
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="type_cryptage" name="type_cryptage">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label for="password_wifi" class="form-label fw-bold">
                                            <i class="fas fa-key me-2"></i>Password WiFi
                                        </label>
                                        <div class="input-group">
                                            <input type="password" class="form-control bg-body text-body" id="password_wifi" name="password_wifi">
                                            <button class="btn btn-outline-secondary" type="button" id="toggleWifiPassword" title="Afficher/Masquer le mot de passe WiFi">
                                                <i class="bi bi-eye" id="wifiPasswordIcon"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bloc 5: Dates et commentaire -->
                        <div class="card mb-4">
                            <div class="card-header bg-body-secondary border-bottom">
                                <h6 class="mb-0 text-body">
                                    <i class="fas fa-calendar me-2"></i>Dates et Commentaire
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="date_fin_maintenance" class="form-label fw-bold">
                                            <i class="bi bi-tools me-2"></i>Date fin maintenance
                                        </label>
                                        <input type="date" class="form-control bg-body text-body" id="date_fin_maintenance" name="date_fin_maintenance">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="date_fin_garantie" class="form-label fw-bold">
                                            <i class="fas fa-certificate me-2"></i>Date fin garantie
                                        </label>
                                        <input type="date" class="form-control bg-body text-body" id="date_fin_garantie" name="date_fin_garantie">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="date_derniere_inter" class="form-label fw-bold">
                                            <i class="fas fa-calendar-check me-2"></i>Date dernière intervention
                                        </label>
                                        <input type="date" class="form-control bg-body text-body" id="date_derniere_inter" name="date_derniere_inter">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="libelle_pa_salle" class="form-label fw-bold">
                                            <i class="fas fa-tag me-2"></i>Libellé PA Salle
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="libelle_pa_salle" name="libelle_pa_salle">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="numero_port_switch" class="form-label fw-bold">
                                            <i class="fas fa-plug me-2"></i>Numéro Port Switch
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="numero_port_switch" name="numero_port_switch">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="vlan" class="form-label fw-bold">
                                            <i class="fas fa-network-wired me-2"></i>VLAN
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="vlan" name="vlan">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-12">
                                        <label for="commentaire" class="form-label fw-bold">
                                            <i class="fas fa-comment me-2"></i>Commentaire
                                        </label>
                                        <textarea class="form-control bg-body text-body" id="commentaire" name="commentaire" rows="3" placeholder="Commentaires additionnels..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Colonne droite : Visibilité des champs -->
                    <div class="col-md-4">
                        <div class="card border-primary sticky-top" style="top: 20px;">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-eye me-2 me-1"></i>Visibilité Client
                                    <?php if (isset($contractAccessLevel)): ?>
                                        <span class="badge bg-light text-dark ms-2">
                                            Niveau: <?= h($contractAccessLevel['name']) ?>
                                        </span>
                                    <?php endif; ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (isset($contractAccessLevel)): ?>
                                    <div class="alert alert-info mb-3">
                                        <small>
                                            <i class="bi bi-info-circle me-1 me-1"></i>
                                            Les champs sont pré-sélectionnés selon le niveau d'accès du contrat.
                                            Vous pouvez modifier individuellement chaque champ.
                                        </small>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted small mb-3">
                                        Cochez les champs que le client peut voir dans son interface.
                                    </p>
                                <?php endif; ?>
                                
                                <?php foreach ($champs_visibilite as $nom_champ => $info): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" 
                                               id="visibilite_<?= $nom_champ ?>" 
                                               name="visibilite_<?= $nom_champ ?>" 
                                               <?= $info['visible_client'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="visibilite_<?= $nom_champ ?>">
                                            <?= h($info['label']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                                
                                <hr>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleAll(true)">
                                        <i class="bi bi-check-square me-1 me-1"></i>Tout cocher
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false)">
                                        <i class="fas fa-square me-1"></i>Tout décocher
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Boutons d'action -->
                <div class="row mt-4">
                    <div class="col-12">
                        <hr>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?= BASE_URL ?>materiel" class="btn btn-secondary">
                                <i class="bi bi-x-lg me-2 me-1"></i>Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2 me-1"></i>Créer le Matériel
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser BASE_URL pour JavaScript
    initBaseUrl('<?php echo BASE_URL; ?>');
    
    const clientSelect = document.getElementById('client_id');
    const siteSelect = document.getElementById('site_id');
    const buildingSelect = document.getElementById('building_id');
    const roomSelect = document.getElementById('salle_id');
    
    // Chargement en cascade
    if (clientSelect) {
        clientSelect.addEventListener('change', function() {
            loadSitesForMateriel(this.value, 'site_id');
            // Réinitialiser les selects dépendants
            if (buildingSelect) buildingSelect.innerHTML = '<option value="">Sélectionner un bâtiment</option>';
            if (roomSelect) roomSelect.innerHTML = '<option value="">Sélectionner une salle</option>';
        });
    }
    
    if (siteSelect) {
        siteSelect.addEventListener('change', function() {
            loadBuildingsForMateriel(this.value, 'building_id');
            if (roomSelect) roomSelect.innerHTML = '<option value="">Sélectionner une salle</option>';
        });
    }
    
    if (buildingSelect) {
        buildingSelect.addEventListener('change', function() {
            loadRoomsForMaterielByBuilding(this.value, 'salle_id');
        });
    }

    // Gestion de l'affichage des mots de passe
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const passwordIcon = document.getElementById('passwordIcon');

    if (togglePassword && passwordInput && passwordIcon) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            passwordIcon.classList.toggle('bi-eye');
            passwordIcon.classList.toggle('bi-eye-slash');
        });
    }

    const toggleWifiPassword = document.getElementById('toggleWifiPassword');
    const wifiPasswordInput = document.getElementById('password_wifi');
    const wifiPasswordIcon = document.getElementById('wifiPasswordIcon');

    if (toggleWifiPassword && wifiPasswordInput && wifiPasswordIcon) {
        toggleWifiPassword.addEventListener('click', function() {
            const type = wifiPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            wifiPasswordInput.setAttribute('type', type);
            wifiPasswordIcon.classList.toggle('bi-eye');
            wifiPasswordIcon.classList.toggle('bi-eye-slash');
        });
    }
});

// Fonctions spécifiques pour materiel
function loadSitesForMateriel(clientId, siteSelectId) {
    const siteSelect = document.getElementById(siteSelectId);
    if (!siteSelect) return;
    
    siteSelect.innerHTML = '<option value="">Chargement...</option>';
    
    if (!clientId) {
        siteSelect.innerHTML = '<option value="">Sélectionner un site</option>';
        return;
    }
    
    fetch(`${BASE_URL}materiel/get_sites?client_id=${clientId}`, {
        credentials: 'include'
    })
        .then(response => response.json())
        .then(data => {
            siteSelect.innerHTML = '<option value="">Sélectionner un site</option>';
            if (data && Array.isArray(data)) {
                data.forEach(site => {
                    const option = document.createElement('option');
                    option.value = site.id;
                    option.textContent = site.name;
                    siteSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des sites:', error);
            siteSelect.innerHTML = '<option value="">Erreur de chargement</option>';
        });
}

function loadBuildingsForMateriel(siteId, buildingSelectId) {
    const buildingSelect = document.getElementById(buildingSelectId);
    if (!buildingSelect) return;
    
    buildingSelect.innerHTML = '<option value="">Chargement...</option>';
    
    if (!siteId) {
        buildingSelect.innerHTML = '<option value="">Sélectionner un bâtiment</option>';
        return;
    }
    
    fetch(`${BASE_URL}materiel/get_buildings?site_id=${siteId}`, {
        credentials: 'include'
    })
        .then(response => response.json())
        .then(data => {
            buildingSelect.innerHTML = '<option value="">Sélectionner un bâtiment</option>';
            if (data && Array.isArray(data)) {
                data.forEach(building => {
                    const option = document.createElement('option');
                    option.value = building.id;
                    option.textContent = building.name;
                    buildingSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des bâtiments:', error);
            buildingSelect.innerHTML = '<option value="">Erreur de chargement</option>';
        });
}

function loadRoomsForMaterielByBuilding(buildingId, roomSelectId) {
    const roomSelect = document.getElementById(roomSelectId);
    if (!roomSelect) return;
    
    roomSelect.innerHTML = '<option value="">Chargement...</option>';
    
    if (!buildingId) {
        roomSelect.innerHTML = '<option value="">Sélectionner une salle</option>';
        return;
    }
    
    fetch(`${BASE_URL}materiel/get_rooms_by_building?building_id=${buildingId}`, {
        credentials: 'include'
    })
        .then(response => response.json())
        .then(data => {
            roomSelect.innerHTML = '<option value="">Sélectionner une salle</option>';
            if (data && Array.isArray(data)) {
                data.forEach(room => {
                    const option = document.createElement('option');
                    option.value = room.id;
                    option.textContent = room.name;
                    roomSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des salles:', error);
            roomSelect.innerHTML = '<option value="">Erreur de chargement</option>';
        });
}

// Fonction pour cocher/décocher toutes les cases
function toggleAll(checked) {
    const checkboxes = document.querySelectorAll('input[name^="visibilite_"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = checked;
    });
}

// Fonction pour ajouter un autre matériel avec les valeurs actuelles du formulaire
function addAnotherMateriel() {
    const clientId = document.getElementById('client_id').value;
    const siteId = document.getElementById('site_id').value;
    const buildingId = document.getElementById('building_id').value;
    const salleId = document.getElementById('salle_id').value;
    
    const params = new URLSearchParams();
    if (clientId) params.set('client_id', clientId);
    if (siteId) params.set('site_id', siteId);
    if (buildingId) params.set('building_id', buildingId);
    if (salleId) params.set('salle_id', salleId);
    
    const url = `${BASE_URL}materiel/add${params.toString() ? '?' + params.toString() : ''}`;
    window.location.href = url;
}
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?>