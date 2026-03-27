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

                        <!-- Bloc 2: Firmware -->
                        <div class="card mb-4">
                            <div class="card-header bg-body-secondary border-bottom">
                                <h6 class="mb-0 text-body">
                                    <i class="fas fa-microchip me-2"></i>Firmware
                                </h6>
                            </div>
                            <div class="card-body">
                                <!-- Configuration réseau -->
                                <h6 class="mb-3 mt-4">
                                    <i class="fas fa-network-wired me-2"></i>Configuration Réseau
                                </h6>
                                
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
                                    <div class="col-md-4">
                                        <label for="type_id" class="form-label fw-bold">
                                            <i class="fas fa-tag me-2"></i>Type d'équipement
                                        </label>
                                        <select class="form-select bg-body text-body" id="type_id" name="type_id">
                                            <option value="">Sélectionner un type</option>
                                            <?php foreach ($types_materiel as $type): ?>
                                                <option value="<?= $type['id'] ?>">
                                                    <?= h($type['nom']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
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
                                <!-- Accès -->
                                <h6 class="mb-3 mt-4">
                                    <i class="fas fa-key me-2"></i>Accès
                                </h6>
                                
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
                                                <i class="<?php echo getIcon('visibility', 'bi bi-eye'); ?>" id="passwordIcon"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bloc 4: Dates et commentaire -->
                        <div class="card mb-4">
                            <div class="card-header bg-body-secondary border-bottom">
                                <h6 class="mb-0 text-body">
                                    <i class="fas fa-calendar me-2"></i>Dates et Commentaire
                                </h6>
                            </div>
                            <div class="card-body">
                                <!-- Dates importantes -->
                                <h6 class="mb-3 mt-4">
                                    <i class="fas fa-calendar me-2"></i>Dates Importantes
                                </h6>
                                
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
                                </div>

                                <!-- Commentaire -->
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
    const roomSelect = document.getElementById('salle_id');
    
    // Utiliser les fonctions centralisées pour charger les sites et salles dynamiquement
    // Mais en surchargeant les URLs pour utiliser les endpoints materiel
    clientSelect.addEventListener('change', function() {
        loadSitesForMateriel(this.value, 'site_id');
    });
    
    siteSelect.addEventListener('change', function() {
        loadRoomsForMateriel(this.value, 'salle_id');
    });

    // Ajouter un listener pour la sélection de salle
    roomSelect.addEventListener('change', function() {
        loadAccessLevelForRoom(this.value);
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

// Fonction pour charger le niveau d'accès d'une salle
function loadAccessLevelForRoom(roomId) {
    
    if (!roomId) {
        // Réinitialiser les checkboxes si aucune salle n'est sélectionnée
        const checkboxes = document.querySelectorAll('input[name^="visibilite_"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        
        // Masquer le badge du niveau d'accès
        const badge = document.querySelector('.badge');
        if (badge) {
            badge.style.display = 'none';
        }
        
        // Masquer l'alerte d'info
        const alert = document.querySelector('.alert-info');
        if (alert) {
            alert.style.display = 'none';
        }
        
        return;
    }
    
    fetch(`${BASE_URL}materiel/get_room_access_level?room_id=${roomId}`, {
        credentials: 'include'
    })
        .then(response => {
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Réponse non-JSON reçue du serveur');
                }
            });
        })
        .then(data => {
            if (data.error) {
                return;
            }
            
            // Mettre à jour le badge du niveau d'accès
            const badge = document.querySelector('.badge');
            if (badge) {
                badge.textContent = `Niveau: ${data.access_level_name}`;
                badge.style.display = 'inline';
            }
            
            // Afficher l'alerte d'info
            const alert = document.querySelector('.alert-info');
            if (alert) {
                alert.style.display = 'block';
            }
            
            // Pré-sélectionner les checkboxes selon les règles de visibilité
            const checkboxes = document.querySelectorAll('input[name^="visibilite_"]');
            checkboxes.forEach(checkbox => {
                const fieldName = checkbox.name.replace('visibilite_', '');
                const isChecked = data.visibility_rules[fieldName] || false;
                checkbox.checked = isChecked;
            });
        })
        .catch(error => {
            // Gestion silencieuse des erreurs pour la version en ligne
        });
}
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 