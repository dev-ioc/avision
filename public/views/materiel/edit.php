<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue d'édition de matériel
 * Formulaire de modification avec gestion de la visibilité des champs
 */

// Vérifier si l'utilisateur est connecté et a les permissions
if (!isset($_SESSION['user']) || !canModifyMateriel()) {
    $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour modifier du matériel.";
    header('Location: ' . BASE_URL . 'dashboard');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Modifier le Matériel',
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
                        <i class="bi bi-pencil me-2 me-1"></i>Modifier le Matériel
                    </h4>
                    <p class="text-muted mb-0">Modification des informations du matériel</p>
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

    <!-- Formulaire d'édition -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-hdd-network me-2 me-1"></i>Informations du Matériel
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= BASE_URL ?>materiel/update/<?= $materiel['id'] ?>">
                <?= csrf_field() ?>
                <!-- Champs cachés pour conserver les filtres -->
                <?php if (isset($_GET['client_id']) && !empty($_GET['client_id'])): ?>
                    <input type="hidden" name="return_client_id" value="<?= h($_GET['client_id']) ?>">
                <?php endif; ?>
                <?php if (isset($_GET['site_id']) && !empty($_GET['site_id'])): ?>
                    <input type="hidden" name="return_site_id" value="<?= h($_GET['site_id']) ?>">
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
                                            <i class="bi bi-building me-2"></i>Client
                                        </label>
                                        <select class="form-select bg-body text-body" id="client_id" name="client_id" required>
                                            <option value="">Sélectionner un client</option>
                                            <?php foreach ($clients as $clientItem): ?>
                                                <option value="<?= $clientItem['id'] ?>" <?= $clientItem['id'] == $client['id'] ? 'selected' : '' ?>>
                                                    <?= h($clientItem['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="site_id" class="form-label fw-bold">
                                            <i class="bi bi-geo-alt me-2"></i>Site
                                        </label>
                                        <select class="form-select bg-body text-body" id="site_id" name="site_id" required>
                                            <option value="">Sélectionner un site</option>
                                            <?php if (!empty($sites)): ?>
                                                <?php foreach ($sites as $siteItem): ?>
                                                    <option value="<?= $siteItem['id'] ?>" <?= $siteItem['id'] == $site['id'] ? 'selected' : '' ?>>
                                                        <?= h($siteItem['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="salle_id" class="form-label fw-bold">
                                            <i class="bi bi-door-open me-2"></i>Salle *
                                        </label>
                                        <select class="form-select bg-body text-body" id="salle_id" name="salle_id" required>
                                            <option value="">Sélectionner une salle</option>
                                            <?php if (!empty($salles)): ?>
                                                <?php foreach ($salles as $salleItem): ?>
                                                    <option value="<?= $salleItem['id'] ?>" <?= $salleItem['id'] == $room['id'] ? 'selected' : '' ?>>
                                                        <?= h($salleItem['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Type, Marque, Référence, Usage, Numéro de série -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="type_materiel" class="form-label fw-bold">
                                            <i class="fas fa-tag me-2"></i>Type de matériel
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="type_materiel" name="type_materiel" 
                                               value="<?= htmlspecialchars($materiel['type_materiel'] ?? '') ?>" 
                                               placeholder="Ex: Amplificateur, Mixeur, etc.">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="marque" class="form-label fw-bold">
                                            <i class="fas fa-tag me-2"></i>Marque *
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="marque" name="marque" value="<?= h($materiel['marque']) ?>" required>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="reference" class="form-label fw-bold">
                                            <i class="fas fa-barcode me-2"></i>Référence
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="reference" name="reference" 
                                               value="<?= htmlspecialchars($materiel['reference'] ?? '') ?>" 
                                               placeholder="Référence du matériel">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="usage_materiel" class="form-label fw-bold">
                                            <i class="fas fa-tasks me-2"></i>Usage
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="usage_materiel" name="usage_materiel" 
                                               value="<?= htmlspecialchars($materiel['usage_materiel'] ?? '') ?>" 
                                               placeholder="Usage du matériel">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="numero_serie" class="form-label fw-bold">
                                            <i class="fas fa-barcode me-2"></i>Numéro de série
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="numero_serie" name="numero_serie" value="<?= htmlspecialchars($materiel['numero_serie'] ?? '') ?>">
                                    </div>
                                </div>

                                <!-- MAC, IP, Masque, Passerelle, ID, Login, Password -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="adresse_mac" class="form-label fw-bold">
                                            <i class="fas fa-wifi me-2"></i>MAC
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="adresse_mac" name="adresse_mac" 
                                               value="<?= htmlspecialchars($materiel['adresse_mac'] ?? '') ?>" 
                                               placeholder="00:11:22:33:44:55">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="adresse_ip" class="form-label fw-bold">
                                            <i class="fas fa-globe me-2"></i>IP
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="adresse_ip" name="adresse_ip" 
                                               value="<?= htmlspecialchars($materiel['adresse_ip'] ?? '') ?>" 
                                               placeholder="192.168.1.100">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="masque" class="form-label fw-bold">
                                            <i class="fas fa-mask me-2"></i>Masque
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="masque" name="masque" 
                                               value="<?= htmlspecialchars($materiel['masque'] ?? '') ?>" 
                                               placeholder="255.255.255.0">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="passerelle" class="form-label fw-bold">
                                            <i class="fas fa-route me-2"></i>Passerelle
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="passerelle" name="passerelle" 
                                               value="<?= htmlspecialchars($materiel['passerelle'] ?? '') ?>" 
                                               placeholder="192.168.1.1">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="id_materiel" class="form-label fw-bold">
                                            <i class="fas fa-id-card me-2"></i>ID
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="id_materiel" name="id_materiel" 
                                               value="<?= htmlspecialchars($materiel['id_materiel'] ?? '') ?>" 
                                               placeholder="Identifiant unique">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="login" class="form-label fw-bold">
                                            <i class="fas fa-user me-2"></i>Login
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="login" name="login" value="<?= htmlspecialchars($materiel['login'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="password" class="form-label fw-bold">
                                            <i class="fas fa-lock me-2"></i>Password
                                        </label>
                                        <div class="input-group">
                                            <input type="password" class="form-control bg-body text-body" id="password" name="password" value="<?= htmlspecialchars($materiel['password'] ?? '') ?>">
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword" title="Afficher/Masquer le mot de passe">
                                                <i class="<?php echo getIcon('visibility', 'bi bi-eye'); ?>" id="passwordIcon"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modèle (obligatoire) -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="modele" class="form-label fw-bold">
                                            <i class="fas fa-cube me-2"></i>Modèle *
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="modele" name="modele" value="<?= h($materiel['modele']) ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bloc 2: Firmware -->
                        <div class="card mb-4">
                            <div class="card-header bg-body-secondary border-bottom">
                                <h6 class="mb-0 text-body">
                                    <i class="fas fa-microchip me-2"></i>Firmware
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="version_firmware" class="form-label fw-bold">
                                            <i class="fas fa-microchip me-2"></i>Version firmware
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="version_firmware" name="version_firmware" value="<?= htmlspecialchars($materiel['version_firmware'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="ancien_firmware" class="form-label fw-bold">
                                            <i class="fas fa-history me-2"></i>Ancien firmware
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="ancien_firmware" name="ancien_firmware" 
                                               value="<?= htmlspecialchars($materiel['ancien_firmware'] ?? '') ?>" 
                                               placeholder="Version précédente">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-12">
                                        <label for="url_github" class="form-label fw-bold">
                                            <i class="fab fa-github me-2"></i>URL GitHub
                                        </label>
                                        <input type="url" class="form-control bg-body text-body" id="url_github" name="url_github" 
                                               value="<?= htmlspecialchars($materiel['url_github'] ?? '') ?>" 
                                               placeholder="https://github.com/user/repo">
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
                                            <i class="fas fa-server me-2"></i>IP Primaire
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="ip_primaire" name="ip_primaire" 
                                               value="<?= htmlspecialchars($materiel['ip_primaire'] ?? '') ?>" 
                                               placeholder="192.168.1.100">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="mac_primaire" class="form-label fw-bold">
                                            <i class="fas fa-ethernet me-2"></i>MAC Primaire
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="mac_primaire" name="mac_primaire" 
                                               value="<?= htmlspecialchars($materiel['mac_primaire'] ?? '') ?>" 
                                               placeholder="00:11:22:33:44:55">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="ip_secondaire" class="form-label fw-bold">
                                            <i class="fas fa-server me-2"></i>IP Secondaire
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="ip_secondaire" name="ip_secondaire" 
                                               value="<?= htmlspecialchars($materiel['ip_secondaire'] ?? '') ?>" 
                                               placeholder="192.168.2.100">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="mac_secondaire" class="form-label fw-bold">
                                            <i class="fas fa-ethernet me-2"></i>MAC Secondaire
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="mac_secondaire" name="mac_secondaire" 
                                               value="<?= htmlspecialchars($materiel['mac_secondaire'] ?? '') ?>" 
                                               placeholder="00:11:22:33:44:66">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="stream_aes67_recu" class="form-label fw-bold">
                                            <i class="fas fa-download me-2"></i>Stream AES67 Reçu
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="stream_aes67_recu" name="stream_aes67_recu" 
                                               value="<?= htmlspecialchars($materiel['stream_aes67_recu'] ?? '') ?>" 
                                               placeholder="Stream reçu">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="stream_aes67_transmis" class="form-label fw-bold">
                                            <i class="fas fa-upload me-2"></i>Stream AES67 Transmis
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="stream_aes67_transmis" name="stream_aes67_transmis" 
                                               value="<?= htmlspecialchars($materiel['stream_aes67_transmis'] ?? '') ?>" 
                                               placeholder="Stream transmis">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bloc 4: WiFi -->
                        <div class="card mb-4">
                            <div class="card-header bg-body-secondary border-bottom">
                                <h6 class="mb-0 text-body">
                                    <i class="fas fa-wifi me-2"></i>WiFi
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="ssid" class="form-label fw-bold">
                                            <i class="fas fa-wifi me-2"></i>SSID
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="ssid" name="ssid" 
                                               value="<?= htmlspecialchars($materiel['ssid'] ?? '') ?>" 
                                               placeholder="Nom du réseau WiFi">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="type_cryptage" class="form-label fw-bold">
                                            <i class="fas fa-shield-alt me-2"></i>Type de cryptage
                                        </label>
                                        <select class="form-select bg-body text-body" id="type_cryptage" name="type_cryptage">
                                            <option value="">Sélectionner</option>
                                            <option value="WEP" <?= ($materiel['type_cryptage'] ?? '') === 'WEP' ? 'selected' : '' ?>>WEP</option>
                                            <option value="WPA" <?= ($materiel['type_cryptage'] ?? '') === 'WPA' ? 'selected' : '' ?>>WPA</option>
                                            <option value="WPA2" <?= ($materiel['type_cryptage'] ?? '') === 'WPA2' ? 'selected' : '' ?>>WPA2</option>
                                            <option value="WPA3" <?= ($materiel['type_cryptage'] ?? '') === 'WPA3' ? 'selected' : '' ?>>WPA3</option>
                                            <option value="Aucun" <?= ($materiel['type_cryptage'] ?? '') === 'Aucun' ? 'selected' : '' ?>>Aucun</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="password_wifi" class="form-label fw-bold">
                                            <i class="fas fa-key me-2"></i>Password
                                        </label>
                                        <div class="input-group">
                                            <input type="password" class="form-control bg-body text-body" id="password_wifi" name="password_wifi" 
                                                   value="<?= htmlspecialchars($materiel['password_wifi'] ?? '') ?>" 
                                                   placeholder="Mot de passe WiFi">
                                            <button class="btn btn-outline-secondary" type="button" id="togglePasswordWifi" title="Afficher/Masquer le mot de passe WiFi">
                                                <i class="bi bi-eye" id="passwordWifiIcon"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bloc 5: Infrastructure -->
                        <div class="card mb-4">
                            <div class="card-header bg-body-secondary border-bottom">
                                <h6 class="mb-0 text-body">
                                    <i class="fas fa-building me-2"></i>Infrastructure
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="libelle_pa_salle" class="form-label fw-bold">
                                            <i class="fas fa-volume-up me-2"></i>Libellé de PA salle
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="libelle_pa_salle" name="libelle_pa_salle" 
                                               value="<?= htmlspecialchars($materiel['libelle_pa_salle'] ?? '') ?>" 
                                               placeholder="Libellé de la sonorisation">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="numero_port_switch" class="form-label fw-bold">
                                            <i class="fas fa-plug me-2"></i>N° Port switch
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="numero_port_switch" name="numero_port_switch" 
                                               value="<?= htmlspecialchars($materiel['numero_port_switch'] ?? '') ?>" 
                                               placeholder="Numéro du port">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="vlan" class="form-label fw-bold">
                                            <i class="fas fa-sitemap me-2"></i>VLAN
                                        </label>
                                        <input type="text" class="form-control bg-body text-body" id="vlan" name="vlan" 
                                               value="<?= htmlspecialchars($materiel['vlan'] ?? '') ?>" 
                                               placeholder="Numéro de VLAN">
                                    </div>
                                </div>

                                <!-- Dates importantes -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="date_fin_maintenance" class="form-label fw-bold">
                                            <i class="bi bi-tools me-2"></i>Date fin maintenance
                                        </label>
                                        <input type="date" class="form-control bg-body text-body" id="date_fin_maintenance" name="date_fin_maintenance" value="<?= $materiel['date_fin_maintenance'] ?? '' ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="date_fin_garantie" class="form-label fw-bold">
                                            <i class="fas fa-certificate me-2"></i>Date fin garantie
                                        </label>
                                        <input type="date" class="form-control bg-body text-body" id="date_fin_garantie" name="date_fin_garantie" value="<?= $materiel['date_fin_garantie'] ?? '' ?>">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="date_derniere_inter" class="form-label fw-bold">
                                            <i class="fas fa-calendar-check me-2"></i>Date dernière intervention
                                        </label>
                                        <input type="date" class="form-control bg-body text-body" id="date_derniere_inter" name="date_derniere_inter" value="<?= $materiel['date_derniere_inter'] ?? '' ?>">
                                    </div>
                                </div>

                                <!-- Commentaire -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <label for="commentaire" class="form-label fw-bold">
                                            <i class="fas fa-comment me-2"></i>Remarques
                                        </label>
                                        <textarea class="form-control bg-body text-body" id="commentaire" name="commentaire" rows="3" placeholder="Commentaires additionnels..."><?= htmlspecialchars($materiel['commentaire'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Colonne droite : Visibilité des champs -->
                    <div class="col-md-4">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-eye me-2 me-1"></i>Visibilité Client
                                </h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-3">
                                    Cochez les champs que le client peut voir dans son interface.
                                </p>
                                
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
                                <i class="bi bi-check-lg me-2 me-1"></i>Enregistrer les Modifications
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
    const roomSelect = document.getElementById('salle_id');
    
    // Utiliser les fonctions centralisées pour charger les sites et salles dynamiquement
    // Mais en surchargeant les URLs pour utiliser les endpoints materiel
    clientSelect.addEventListener('change', function() {
        loadSitesForMateriel(this.value, 'site_id');
    });
    
    siteSelect.addEventListener('change', function() {
        loadRoomsForMateriel(this.value, 'salle_id');
    });

    // Gestion de l'affichage du mot de passe
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const passwordIcon = document.getElementById('passwordIcon');

    if (togglePassword && passwordInput && passwordIcon) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Changer l'icône
            if (type === 'text') {
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
                togglePassword.title = 'Masquer le mot de passe';
            } else {
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
                togglePassword.title = 'Afficher le mot de passe';
            }
        });
    }

    // Gestion de l'affichage du mot de passe WiFi
    const togglePasswordWifi = document.getElementById('togglePasswordWifi');
    const passwordWifiInput = document.getElementById('password_wifi');
    const passwordWifiIcon = document.getElementById('passwordWifiIcon');

    if (togglePasswordWifi && passwordWifiInput && passwordWifiIcon) {
        togglePasswordWifi.addEventListener('click', function() {
            const type = passwordWifiInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordWifiInput.setAttribute('type', type);
            
            // Changer l'icône
            if (type === 'text') {
                passwordWifiIcon.classList.remove('bi-eye');
                passwordWifiIcon.classList.add('bi-eye-slash');
                togglePasswordWifi.title = 'Masquer le mot de passe WiFi';
            } else {
                passwordWifiIcon.classList.remove('bi-eye-slash');
                passwordWifiIcon.classList.add('bi-eye');
                togglePasswordWifi.title = 'Afficher le mot de passe WiFi';
            }
        });
    }
});

// Fonctions spécifiques pour materiel qui utilisent les bons endpoints
function loadSitesForMateriel(clientId, siteSelectId) {
    const siteSelect = document.getElementById(siteSelectId);
    if (!siteSelect) return;
    
    // Vider le select sauf l'option par défaut
    while (siteSelect.options.length > 1) {
        siteSelect.remove(1);
    }
    
    if (!clientId) return;
    
    fetch(`${BASE_URL}materiel/get_sites?client_id=${clientId}`, {
        credentials: 'include'
    })
        .then(response => response.json())
        .then(data => {
            if (data && Array.isArray(data)) {
                data.forEach(site => {
                    const option = document.createElement('option');
                    option.value = site.id;
                    option.textContent = site.name;
                    siteSelect.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Erreur lors du chargement des sites:', error));
}

function loadRoomsForMateriel(siteId, roomSelectId) {
    const roomSelect = document.getElementById(roomSelectId);
    if (!roomSelect) return;
    
    roomSelect.innerHTML = '<option value="">Sélectionner une salle</option>';
    
    if (!siteId) return;
    
    fetch(`${BASE_URL}materiel/get_rooms?site_id=${siteId}`, {
        credentials: 'include'
    })
        .then(response => response.json())
        .then(data => {
            if (data && Array.isArray(data)) {
                data.forEach(room => {
                    const option = document.createElement('option');
                    option.value = room.id;
                    option.textContent = room.name;
                    roomSelect.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Erreur lors du chargement des salles:', error));
}

// Fonction pour ajouter un autre matériel avec les valeurs actuelles du formulaire
function addAnotherMateriel() {
    const clientId = document.getElementById('client_id').value;
    const siteId = document.getElementById('site_id').value;
    const salleId = document.getElementById('salle_id').value;
    
    // Construire l'URL avec les valeurs actuelles
    const params = new URLSearchParams();
    if (clientId) {
        params.set('client_id', clientId);
    }
    if (siteId) {
        params.set('site_id', siteId);
    }
    if (salleId) {
        params.set('salle_id', salleId);
    }
    
    const url = `${BASE_URL}materiel/add${params.toString() ? '?' + params.toString() : ''}`;
    window.location.href = url;
}

// Fonction pour cocher/décocher toutes les cases
function toggleAll(checked) {
    const checkboxes = document.querySelectorAll('input[name^="visibilite_"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = checked;
    });
}
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 