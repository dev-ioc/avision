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
            <a href="<?php echo BASE_URL; ?>clients/edit/<?php echo $contact['client_id'] ?? ''; ?>#contacts"
                class="btn btn-secondary me-2">
                <i class="bi bi-arrow-left me-1"></i> Retour
            </a>
            <button type="submit" form="contactForm" class="btn btn-primary me-2">
                <i class="<?php echo getIcon('save', 'bi bi-check-lg'); ?>"></i> Enregistrer
            </button>
            <?php if (isAdmin()): ?>
                <a href="<?php echo BASE_URL; ?>contacts/delete/<?php echo $contact['id']; ?>" class="btn btn-danger"
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
                                <textarea class="form-control" id="comment" name="comment"
                                    rows="4"><?php echo htmlspecialchars($contact['comment'] ?? ''); ?></textarea>
                            </div>
                            <?php if (isAdmin()): ?>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="has_user_account"
                                            name="has_user_account" value="1" <?php echo $contact['has_user_account'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="has_user_account">Ce contact a un compte
                                            utilisateur</label>
                                    </div>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_vip" name="is_vip" value="1" <?php echo (!empty($contact['is_vip'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_vip">
                                        <i class="bi bi-star-fill text-warning me-1"></i> Contact VIP
                                    </label>
                                </div>

                                                                <?php if (!empty($contact['is_vip']) && !empty($contactQR)): ?>
                                    <div class="card mb-3">
                                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                            <h6 class="card-title mb-0">QR Code VIP</h6>
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">
                                                <i class="bi bi-printer"></i>
                                            </button>
                                        </div>
                                        <div class="card-body py-2 text-center">
                                            <img src="<?php echo $contactQR; ?>" alt="QR Code VIP"
                                                style="width:130px;height:130px;">
                                            <small class="text-muted d-block mt-2">Accès direct au tableau de bord client</small>
                                        </div>
                                    </div>
                                                                <?php endif; ?>
                                <!-- Sous-formulaire pour la création de compte utilisateur -->
                                <div id="userAccountForm" class="card mt-3 mb-3"
                                    style="display: <?php echo $contact['has_user_account'] ? 'block' : 'none'; ?>;">
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
                                                <label for="username" class="form-label">Nom d'utilisateur <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="username" name="username" value="">
                                            </div>
                                          <div class="mb-3">
                                            <label for="password" class="form-label">Mot de passe <span class="text-muted">(optionnel)</span></label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="password" name="password">
                                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                    <i class="bi bi-eye me-1"></i>
                                                </button>
                                            </div>
                                            <div class="password-rules mt-2" id="passwordRules" style="display: none;">
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const hasUserAccountCheckbox = document.getElementById('has_user_account');
    const userAccountForm = document.getElementById('userAccountForm');
    const usernameInput = document.getElementById('username');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');
    const passwordRulesBlock = document.getElementById('passwordRules');
        if (hasUserAccountCheckbox && userAccountForm) {
                hasUserAccountCheckbox.addEventListener('change', function() {
                userAccountForm.style.display = this.checked ? 'block' : 'none';
                if (this.checked && usernameInput && emailInput) {
                    usernameInput.value = emailInput.value;
                }
            });
        }

        // Le username suit l'email en temps réel
        if (emailInput && usernameInput) {
            emailInput.addEventListener('input', function() {
                usernameInput.value = this.value;
            });
        }

    const passwordRules = {
        length: /.{8,}/,
        uppercase: /[A-Z]/,
        lowercase: /[a-z]/,
        number: /[0-9]/,
        special: /[!@#$%^&*(),.?":{}|<>]/
    };

    /*
     * Affiche ou masque le formulaire de compte utilisateur
     */
    function updateUserAccountForm() {
        if (!hasUserAccountCheckbox || !userAccountForm) {
            return;
        }

        const accountEnabled = hasUserAccountCheckbox.checked;

        userAccountForm.style.display = accountEnabled ? 'block' : 'none';

        /*
         * Le nom d'utilisateur est obligatoire uniquement
         * lorsque la création du compte est activée.
         */
        if (usernameInput) {
            usernameInput.required = accountEnabled;
        }

        /*
         * Le mot de passe reste toujours facultatif.
         */
        if (passwordInput) {
            passwordInput.required = false;
        }
    }

    /*
     * Met à jour visuellement une règle de mot de passe
     */
    function updatePasswordRule(ruleId, isValid) {
        const ruleElement = document.getElementById(ruleId);

        if (!ruleElement) {
            return;
        }

        const icon = ruleElement.querySelector('i');

        ruleElement.classList.toggle('text-success', isValid);
        ruleElement.classList.toggle('text-danger', !isValid);

        if (icon) {
            icon.classList.toggle('bi-check-lg', isValid);
            icon.classList.toggle('bi-x-lg', !isValid);
        }
    }

    /*
     * Valide le mot de passe et affiche les conditions
     */
    function validatePassword() {
        if (!passwordInput) {
            return;
        }

        const value = passwordInput.value;

        /*
         * Le champ est vide :
         * le mot de passe étant facultatif, on masque les règles.
         */
        if (value.length === 0) {
            if (passwordRulesBlock) {
                passwordRulesBlock.style.display = 'none';
            }

            return;
        }

        /*
         * Le champ contient une valeur :
         * on affiche les conditions nécessaires.
         */
        if (passwordRulesBlock) {
            passwordRulesBlock.style.display = 'block';
        }

        Object.entries(passwordRules).forEach(function ([ruleId, regex]) {
            updatePasswordRule(ruleId, regex.test(value));
        });
    }

    /*
     * Initialisation de l'affichage au chargement de la page
     */
    updateUserAccountForm();
    validatePassword();

    /*
     * Gestion de l'affichage du compte utilisateur
     */
    if (hasUserAccountCheckbox) {
        hasUserAccountCheckbox.addEventListener('change', function () {
            updateUserAccountForm();
        });
    }

    /*
     * Affichage ou masquage du mot de passe
     */
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function () {
            const passwordIsHidden = passwordInput.type === 'password';

            passwordInput.type = passwordIsHidden ? 'text' : 'password';

            const icon = togglePassword.querySelector('i');

            if (icon) {
                icon.classList.toggle('bi-eye', !passwordIsHidden);
                icon.classList.toggle('bi-eye-slash', passwordIsHidden);
            }
        });
    }

    /*
     * Validation en temps réel pendant la saisie
     */
    if (passwordInput) {
        passwordInput.addEventListener('input', function () {
            validatePassword();
        });
    }
});
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
