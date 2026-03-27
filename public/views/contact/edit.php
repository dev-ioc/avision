<?php
// Vérification de l'accès
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Récupération des données
$contact = $contact ?? null;
$user = $_SESSION['user'];

setPageVariables('Modifier le contact', 'contact');
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">Modifier le contact</h4>
        </div>
        <div class="ms-auto p-2 bd-highlight">
            <a href="<?php echo BASE_URL; ?>clients/edit/<?php echo $contact['client_id'] ?? ''; ?>#contacts" class="btn btn-secondary me-2">
                <i class="bi bi-arrow-left me-1"></i> Retour
            </a>
            <button type="submit" form="contactForm" class="btn btn-primary me-2">
                <i class="<?php echo getIcon('save', 'bi bi-check-lg'); ?>"></i> Enregistrer
            </button>
            <?php if (isAdmin()): ?>
            <a href="<?php echo BASE_URL; ?>contacts/delete/<?php echo $contact['id']; ?>" 
               class="btn btn-danger" 
               onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce contact ?');">
                <i class="<?php echo getIcon('delete', 'bi bi-trash'); ?>"></i> Supprimer
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($contact): ?>
        <form id="contactForm" action="<?php echo BASE_URL; ?>contacts/edit/<?php echo $contact['id']; ?>" method="POST">
            <?= csrf_field() ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Informations du contact</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">Prénom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo h($contact['first_name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Nom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo h($contact['last_name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="fonction" class="form-label">Fonction</label>
                                <input type="text" class="form-control" id="fonction" name="fonction" 
                                       value="<?php echo htmlspecialchars($contact['fonction'] ?? ''); ?>" 
                                       placeholder="Ex: Directeur commercial, Responsable IT, etc.">
                            </div>
                            <div class="mb-3">
                                <label for="phone1" class="form-label">Téléphone fixe</label>
                                <input type="text" class="form-control" id="phone1" name="phone1" 
                                       value="<?php echo htmlspecialchars($contact['phone1'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="phone2" class="form-label">Mobile</label>
                                <input type="text" class="form-control" id="phone2" name="phone2" 
                                       value="<?php echo htmlspecialchars($contact['phone2'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo h($contact['email']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="comment" class="form-label">Commentaire</label>
                                <textarea class="form-control" id="comment" name="comment" rows="4"><?php echo htmlspecialchars($contact['comment'] ?? ''); ?></textarea>
                            </div>
                            <?php if (isAdmin()): ?>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="has_user_account" name="has_user_account" value="1" 
                                           <?php echo $contact['has_user_account'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="has_user_account">Ce contact a un compte utilisateur</label>
                                </div>
                            </div>
                            
                            <!-- Sous-formulaire pour la création de compte utilisateur -->
                            <div id="userAccountForm" class="card mt-3 mb-3" style="display: <?php echo $contact['has_user_account'] ? 'block' : 'none'; ?>;">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Compte utilisateur</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($contact['user_id']): ?>
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle me-1"></i> Ce contact a déjà un compte utilisateur.
                                        </div>
                                    <?php else: ?>
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
                                    <?php endif; ?>
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
            Contact non trouvé.
        </div>
    <?php endif; ?>
</div>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>

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
                    if (usernameInput) usernameInput.required = true;
                    if (passwordInput) passwordInput.required = true;
                } else {
                    userAccountForm.style.display = 'none';
                    if (usernameInput) usernameInput.required = false;
                    if (passwordInput) passwordInput.required = false;
                }
            });
        }

        // Gestion de l'affichage/masquage du mot de passe
        if (togglePassword) {
            togglePassword.addEventListener('click', function (e) {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('bi-eye');
                this.querySelector('i').classList.toggle('bi-eye-slash');
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
                        element.querySelector('i').classList.remove('bi-x-lg');
                        element.querySelector('i').classList.add('bi-check-lg');
                    } else {
                        element.classList.remove('text-success');
                        element.classList.add('text-danger');
                        element.querySelector('i').classList.remove('bi-check-lg');
                        element.querySelector('i').classList.add('bi-x-lg');
                    }
                }
            });
        }
    });
</script> 