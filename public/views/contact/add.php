<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue d'ajout d'un contact
 * Permet d'ajouter un nouveau contact à un client
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

// Récupération des données
$client = $client ?? null;

setPageVariables(
    'Ajouter un contact',
    'clients'
);

// Définir la page courante pour le menu
$currentPage = 'clients';

// Définir les breadcrumbs personnalisés pour l'ajout de contact
if (isset($client) && !empty($client)) {
    $GLOBALS['customBreadcrumbs'] = generateContactAddBreadcrumbs($client);
}

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">Ajouter un contact</h4>
            <?php if (isset($client) && !empty($client['name'])): ?>
                <p class="text-muted mb-0">
                    <strong>Client :</strong> <?php echo htmlspecialchars($client['name']); ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="ms-auto p-2 bd-highlight">
            <?php 
            $returnTo = $_GET['return_to'] ?? 'edit';
            $returnUrl = ($returnTo === 'view') ? 
                BASE_URL . 'clients/view/' . ($client['id'] ?? '') . '?active_tab=contacts-tab' : 
                BASE_URL . 'clients/edit/' . ($client['id'] ?? '');
            ?>
            <a href="<?php echo $returnUrl; ?>" class="btn btn-secondary me-2">
                <i class="bi bi-arrow-left me-1"></i> Retour
            </a>
            <button type="submit" form="contactForm" class="btn btn-primary">
                Enregistrer
            </button>
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

    <?php if ($client): ?>
        <form id="contactForm" action="<?php echo BASE_URL; ?>contacts/add/<?php echo $client['id']; ?><?php echo isset($_GET['return_to']) ? '?return_to=' . $_GET['return_to'] : ''; ?>" method="POST">
            <?= csrf_field() ?>
            <div class="card">
                <div class="card-header py-2">
                    <h5 class="card-title mb-0">Informations du contact</h5>
                </div>
                <div class="card-body py-2">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">Prénom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Nom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="fonction" class="form-label">Fonction</label>
                                <input type="text" class="form-control" id="fonction" name="fonction" placeholder="Ex: Directeur commercial, Responsable IT, etc.">
                            </div>
                            <div class="mb-3">
                                <label for="phone1" class="form-label">Téléphone fixe</label>
                                <input type="text" class="form-control" id="phone1" name="phone1">
                            </div>
                            <div class="mb-3">
                                <label for="phone2" class="form-label">Mobile</label>
                                <input type="text" class="form-control" id="phone2" name="phone2">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="comment" class="form-label">Commentaire</label>
                                <textarea class="form-control" id="comment" name="comment" rows="4"></textarea>
                            </div>
                            <?php if (isAdmin()): ?>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="has_user_account" name="has_user_account" value="1">
                                    <label class="form-check-label" for="has_user_account">Ce contact a un compte utilisateur</label>
                                </div>
                            </div>
                            
                            <!-- Sous-formulaire pour la création de compte utilisateur -->
                            <div id="userAccountForm" class="card mt-3 mb-3" style="display: none;">
                                <div class="card-header py-2">
                                    <h5 class="card-title mb-0">Création du compte utilisateur</h5>
                                </div>
                                <div class="card-body py-2">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Nom d'utilisateur <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="username" name="username">
                                    </div>
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password" name="password">
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                <i class="bi bi-eye me-1"></i>
                                            </button>
                                        </div>
                                        <div class="password-rules mt-2">
                                            <small class="d-block text-muted">Le mot de passe doit contenir :</small>
                                            <ul class="list-unstyled mb-0">
                                                <li id="length" class="text-danger"><i class="bi bi-x-lg me-1"></i> Au moins 8 caractères</li>
                                                <li id="uppercase" class="text-danger"><i class="bi bi-x-lg me-1"></i> Une majuscule</li>
                                                <li id="lowercase" class="text-danger"><i class="bi bi-x-lg me-1"></i> Une minuscule</li>
                                                <li id="number" class="text-danger"><i class="bi bi-x-lg me-1"></i> Un chiffre</li>
                                                <li id="special" class="text-danger"><i class="bi bi-x-lg me-1"></i> Un caractère spécial</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    <?php else: ?>
        <div class="alert alert-warning">
            Client non trouvé.
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const hasUserAccountCheckbox = document.getElementById('has_user_account');
        const userAccountForm = document.getElementById('userAccountForm');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');
        
        if (hasUserAccountCheckbox && userAccountForm) {
            hasUserAccountCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    userAccountForm.style.display = 'block';
                    usernameInput.required = true;
                    passwordInput.required = true;
                } else {
                    userAccountForm.style.display = 'none';
                    usernameInput.required = false;
                    passwordInput.required = false;
                }
            });
        }

        // Gestion de l'affichage/masquage du mot de passe
        if (togglePassword) {
            togglePassword.addEventListener('click', function (e) {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        }

        // Validation en temps réel du mot de passe
        const passwordRules = {
            length: /.{8,}/,
            uppercase: /[A-Z]/,
            lowercase: /[a-z]/,
            number: /[0-9]/,
            special: /[!@#$%^&*(),.?":{}|<>]/
        };

        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const value = this.value;
                
                // Vérifier chaque règle
                for (const [rule, regex] of Object.entries(passwordRules)) {
                    const element = document.getElementById(rule);
                    const isValid = regex.test(value);
                    
                    if (isValid) {
                        element.classList.remove('text-danger');
                        element.classList.add('text-success');
                        element.querySelector('i').classList.remove('fa-times');
                        element.querySelector('i').classList.add('fa-check');
                    } else {
                        element.classList.remove('text-success');
                        element.classList.add('text-danger');
                        element.querySelector('i').classList.remove('fa-check');
                        element.querySelector('i').classList.add('fa-times');
                    }
                }
            });
        }
    });
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 