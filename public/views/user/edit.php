<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de modification d'utilisateur
 * Affiche le formulaire de modification d'un utilisateur existant
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

// Récupérer l'ID de l'utilisateur depuis l'URL
$userId = isset($user['id']) ? $user['id'] : null;

setPageVariables(
    'Utilisateur',
    'users' . ($userId ? '_edit_' . $userId : '')
);

// Définir la page courante pour le menu
$currentPage = 'users';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Initialiser BASE_URL pour JavaScript
echo '<script>const baseUrl = "' . BASE_URL . '";</script>';

// Définir les variables PHP en JavaScript
echo '<script>';
echo 'const existingPermissionIds = ' . json_encode($existingPermissionIds) . ';';
echo 'const existingLocations = ' . (isset($existingLocations) ? json_encode($existingLocations) : 'null') . ';';
echo '</script>';
?>

<div class="container-fluid flex-grow-1 container-p-y">

<div class="d-flex bd-highlight mb-3">
    <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Modifier l'utilisateur</h4></div>

    <div class="ms-auto p-2 bd-highlight">
        <a href="<?php echo BASE_URL; ?>user" class="btn btn-secondary me-2">
            <i class="bi bi-arrow-left me-1"></i> Retour
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
            
    <!-- Formulaire de modification -->
    <div class="card">
        <div class="card-body py-2">
            <?php if (isset($errors) && !empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo h($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <div class="row">
                    <!-- Colonne 1 : Informations de base -->
                    <div class="col-md-4">
                        <h6 class="mb-3">Informations de base</h6>
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control bg-body text-body" id="username" name="username" 
                                   value="<?php echo isset($_POST['username']) ? h($_POST['username']) : (isset($user['username']) ? h($user['username']) : ''); ?>" 
                                   required>
                            <div class="invalid-feedback">
                                Veuillez saisir un nom d'utilisateur.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control bg-body text-body" id="email" name="email" 
                                   value="<?php echo isset($_POST['email']) ? h($_POST['email']) : (isset($user['email']) ? h($user['email']) : ''); ?>" 
                                   required>
                            <div class="invalid-feedback">
                                Veuillez saisir une adresse email valide.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="first_name" class="form-label">Prénom</label>
                            <input type="text" class="form-control bg-body text-body" id="first_name" name="first_name" 
                                   value="<?php echo isset($_POST['first_name']) ? h($_POST['first_name']) : (isset($user['first_name']) ? h($user['first_name']) : ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="last_name" class="form-label">Nom</label>
                            <input type="text" class="form-control bg-body text-body" id="last_name" name="last_name" 
                                   value="<?php echo isset($_POST['last_name']) ? h($_POST['last_name']) : (isset($user['last_name']) ? h($user['last_name']) : ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mot de passe actuel</label>
                            <div class="input-group">
                                <input type="password" class="form-control bg-body text-body" id="current_password" 
                                       value="••••••••" readonly style="background-color: #f8f9fa; cursor: not-allowed;">
                                <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword" 
                                        title="Mot de passe actuel (non modifiable)">
                                    <i class="bi bi-eye me-1"></i>
                                </button>
                            </div>
                            <div class="form-text text-muted">
                                <i class="bi bi-info-circle me-1 me-1"></i>Mot de passe actuel (en lecture seule)
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nouveau mot de passe</label>
                            <div class="input-group">
                                <input type="password" class="form-control bg-body text-body" id="new_password" name="password" 
                                       placeholder="Laissez vide pour conserver l'actuel">
                                <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword" 
                                        title="Afficher/Masquer le mot de passe">
                                    <i class="bi bi-eye me-1"></i>
                                </button>
                            </div>
                            <div class="password-rules mt-2" style="display: none;">
                                <small class="d-block text-muted">Le mot de passe doit contenir :</small>
                                <div class="row">
                                    <div class="col-6">
                                        <ul class="list-unstyled mb-0">
                                            <li id="length" class="text-danger"><i class="bi bi-x-lg me-1"></i> Au moins 8 caractères</li>
                                            <li id="uppercase" class="text-danger"><i class="bi bi-x-lg me-1"></i> Une majuscule</li>
                                            <li id="lowercase" class="text-danger"><i class="bi bi-x-lg me-1"></i> Une minuscule</li>
                                        </ul>
                                    </div>
                                    <div class="col-6">
                                        <ul class="list-unstyled mb-0">
                                            <li id="number" class="text-danger"><i class="bi bi-x-lg me-1"></i> Un chiffre</li>
                                            <li id="special" class="text-danger"><i class="bi bi-x-lg me-1"></i> Un caractère spécial</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="form-text text-info">
                                <i class="bi bi-pencil me-1 me-1"></i>Saisissez un nouveau mot de passe ou laissez vide pour conserver l'actuel
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="type" class="form-label">Type d'utilisateur *</label>
                            <select class="form-select bg-body text-body" id="type" name="type" required>
                                <option value="">Sélectionner un type</option>
                                <?php if (!empty($userTypes)): ?>
                                    <?php 
                                    $currentType = isset($_POST['type']) ? $_POST['type'] : (isset($user['user_type']) ? $user['user_type'] : '');
                                    foreach ($userTypes as $type): ?>
                                        <option value="<?php echo h($type['name']); ?>" 
                                                <?php echo $currentType === $type['name'] ? 'selected' : ''; ?>>
                                            <?php echo h($type['description']); ?> (<?php echo h($type['group_name']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner un type d'utilisateur.
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_admin" name="is_admin" value="1" 
                                       <?php echo ((isset($_POST['is_admin']) ? $_POST['is_admin'] : (isset($user['is_admin']) ? $user['is_admin'] : false)) ? 'checked' : ''); ?>>
                                <label class="form-check-label" for="is_admin">
                                    Administrateur
                                </label>
                                <div class="form-text text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Les administrateurs ont accès à toutes les fonctionnalités de gestion.
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="status" name="status" value="1" 
                                       <?php echo ((isset($_POST['status']) ? $_POST['status'] : (isset($user['status']) ? $user['status'] : 0)) ? 'checked' : ''); ?>>
                                <label class="form-check-label" for="status">
                                    Compte actif
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Colonne 2 : Permissions -->
                    <div class="col-md-4">
                        <h6 class="mb-3">Permissions</h6>
                        
                        <div id="permissionsSection" style="display: none;">
                            <p class="text-muted">Sélectionnez un type d'utilisateur pour voir les permissions disponibles.</p>
                        </div>
                    </div>

                    <!-- Colonne 3 : Informations spécifiques -->
                    <div class="col-md-4">
                        <h6 class="mb-3">Informations spécifiques</h6>
                        
                        <div id="coefficientSection" class="mb-3" style="display: none;">
                            <label for="coef_utilisateur" class="form-label">Coefficient</label>
                            <input type="number" class="form-control bg-body text-body" id="coef_utilisateur" name="coef_utilisateur" 
                                   step="0.01" min="0" 
                                   value="<?php echo isset($_POST['coef_utilisateur']) ? h($_POST['coef_utilisateur']) : (isset($user['coef_utilisateur']) ? h($user['coef_utilisateur']) : '1.00'); ?>">
                            <div class="form-text text-muted">
                                <i class="bi bi-info-circle me-1 me-1"></i>
                                <strong>À quoi ça sert :</strong> Le coefficient utilisateur permet d'ajuster le nombre de tickets facturés selon l'expérience et la spécialisation du technicien.<br>
                                <strong>Formule :</strong> Tickets = Durée + Coefficient utilisateur + Coefficient intervention (+ 1 si déplacement)
                            </div>
                        </div>

                        <div id="clientSection" class="mb-3" style="display: none;">
                            <label for="client_id" class="form-label">Client *</label>
                            <select class="form-select bg-body text-body" id="client_id" name="client_id">
                                <option value="">Sélectionner un client</option>
                                <?php if (!empty($clients)): ?>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo htmlspecialchars($client['id'] ?? ''); ?>" 
                                                <?php echo (isset($_POST['client_id']) ? $_POST['client_id'] : (isset($user['client_id']) ? $user['client_id'] : '')) == ($client['id'] ?? '') ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($client['name'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>Aucun client actif disponible</option>
                                <?php endif; ?>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner un client.
                            </div>
                        </div>

                        <div id="locations-container" style="display: none;">
                            <div id="locations-content">
                                <!-- Les localisations seront chargées dynamiquement ici -->
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                        <a href="<?php echo BASE_URL; ?>user" class="btn btn-secondary">Annuler</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Variables JavaScript pour les données existantes -->
<script>
// Les permissions existantes sont déjà définies en haut du fichier
</script>

<!-- Script pour la validation des formulaires Bootstrap et la gestion dynamique des sections -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser BASE_URL pour les fonctions communes
    initBaseUrl(baseUrl);
    
    // Initialiser la validation Bootstrap
    initBootstrapValidation();
    
    // Initialiser la gestion du mot de passe
    initPasswordToggle('new_password', 'toggleNewPassword');
    initPasswordToggle('current_password', 'toggleCurrentPassword');
    
    // Initialiser la validation du mot de passe
    const passwordRules = document.querySelector('.password-rules');
    initPasswordValidation('new_password', passwordRules);

    // Gestion des sections en fonction du type d'utilisateur
    const typeSelect = document.getElementById('type');
    const clientSelect = document.getElementById('client_id');

    typeSelect.addEventListener('change', function() {
        toggleUserSections('type', {
            coefficientSection: 'coefficientSection',
            adminCheckbox: 'is_admin',
            clientSection: 'clientSection',
            permissionsSection: 'permissionsSection',
            locationsContainer: 'locations-container'
        });
        
        // Mettre à jour les attributs required selon le type
        updateRequiredFields();
    });

    clientSelect.addEventListener('change', function() {
        const clientId = this.value;
        const locationsContainer = document.getElementById('locations-container');
        
        if (clientId) {
            // Charger les localisations du client avec l'ID de l'utilisateur pour pré-sélection
            const userId = <?php echo $userId ? $userId : 'null'; ?>;
            loadClientLocationsSimple(clientId, 'locations-content', userId);
            locationsContainer.style.display = 'block';
        } else {
            locationsContainer.style.display = 'none';
        }
    });

    // Appliquer les sections initiales en fonction du type d'utilisateur actuel
    toggleUserSections('type', {
        coefficientSection: 'coefficientSection',
        adminCheckbox: 'is_admin',
        clientSection: 'clientSection',
        permissionsSection: 'permissionsSection',
        locationsContainer: 'locations-container'
    });

    // Forcer le chargement des localisations si un client est déjà sélectionné (édition)
    if (typeSelect.value === 'client' && clientSelect.value) {
        clientSelect.dispatchEvent(new Event('change'));
    }
        
    // Mettre à jour les champs requis initialement
    updateRequiredFields();
    
    // Fonction pour mettre à jour les champs requis
    function updateRequiredFields() {
        const userType = typeSelect.value;
        const coefInput = document.getElementById('coef_utilisateur');
        const clientSelect = document.getElementById('client_id');
        
        // Déterminer le groupe en fonction du type sélectionné
        let userGroup = '';
        if (userType === 'technicien' || userType === 'adv') {
            userGroup = 'Staff';
        } else if (userType === 'client') {
            userGroup = 'Externe';
        }
    
        // Coefficient requis pour les membres du staff
        if (coefInput) {
            if (userGroup === 'Staff') {
                coefInput.setAttribute('required', 'required');
            } else {
                coefInput.removeAttribute('required');
            }
        }

        // Client requis pour les utilisateurs externes
        if (clientSelect) {
            if (userGroup === 'Externe') {
                clientSelect.setAttribute('required', 'required');
            } else {
                clientSelect.removeAttribute('required');
            }
        }
    }
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?> 