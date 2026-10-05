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

// Conserver l'URL de la liste des utilisateurs et ses filtres.
$returnUrl = $returnUrl
    ?? $_GET['return_url']
    ?? $_POST['return_url']
    ?? (BASE_URL . 'user');

$returnUrl = htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8');

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
        <a href="<?php echo $returnUrl; ?>" class="btn btn-secondary me-2">
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

                <input type="hidden" name="return_url" value="<?php echo $returnUrl; ?>">
                <div class="row">
                    <!-- Colonne 1 : Informations de base -->
                    <div class="col-md-4">
                        <h6 class="mb-3">Informations de base</h6>
                        
                        <!-- <div class="mb-3">
                            <label for="username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control bg-body text-body" id="username" name="username" 
                                   value="<?php echo isset($_POST['username']) ? h($_POST['username']) : (isset($user['username']) ? h($user['username']) : ''); ?>" 
                                   required>
                            <div class="invalid-feedback">
                                Veuillez saisir un nom d'utilisateur.
                            </div>
                        </div> -->

                       <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control bg-body text-body" id="email" name="email"
                                value="<?php echo isset($user['email']) ? h($user['email']) : ''; ?>"
                                readonly required>
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
                                        style="background-color: #f8f9fa;">
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
                                <button type="button" class="btn btn-outline-warning"
                                        data-bs-toggle="modal" data-bs-target="#recoverModal">
                                    <i class="bi bi-life-preserver me-1"></i> E-mail inaccessible ?
                                </button>
                        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                        <a href="<?php echo $returnUrl; ?>" class="btn btn-secondary">Annuler</a>
                    </div>
                </div>
            </form>
            <?php if ($userId && empty($user['is_admin']) && (int) $userId !== (int) ($_SESSION['user']['id'] ?? 0)): ?>
                <div class="modal fade" id="recoverModal" tabindex="-1" aria-labelledby="recoverModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="recoverModalLabel">Récupération de compte</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-danger small">
                                    L'e-mail actuel (<strong><?php echo h($user['email']); ?></strong>) sera remplacé,
                                    les sessions ouvertes seront fermées, et un lien de réinitialisation de mot de passe
                                    sera envoyé à la <strong>nouvelle</strong> adresse. L'ancienne adresse sera prévenue.
                                </div>

                                <div id="recoverAlert" class="alert alert-danger d-none"></div>

                                <div class="mb-3">
                                    <label for="recover_new_email" class="form-label">Nouvel e-mail *</label>
                                    <input type="email" class="form-control bg-body text-body" id="recover_new_email" autocomplete="off">
                                </div>
                                <div class="mb-3">
                                    <label for="recover_confirm_email" class="form-label">Confirmer le nouvel e-mail *</label>
                                    <input type="email" class="form-control bg-body text-body" id="recover_confirm_email" autocomplete="off">
                                </div>
                                <div class="mb-3">
                                    <label for="recover_reason" class="form-label">Motif (conservé dans le journal) *</label>
                                    <textarea class="form-control bg-body text-body" id="recover_reason" rows="2"
                                            placeholder="Ex : ancien e-mail supprimé, demande reçue par téléphone le ..."></textarea>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="recover_reset_2fa">
                                    <label class="form-check-label" for="recover_reset_2fa">
                                        Réinitialiser aussi la 2FA et les passkeys (téléphone perdu)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="recover_identity">
                                    <label class="form-check-label" for="recover_identity">
                                        J'ai vérifié l'identité de la personne par un autre moyen *
                                    </label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <button type="button" class="btn btn-warning" id="recoverSubmit">
                                    <span class="spinner-border spinner-border-sm d-none me-1" id="recoverSpinner"></span>
                                    Récupérer le compte
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

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
<?php if ($userId): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('recoverModal');
    if (!modalEl) return;

    const recoverUserId = <?php echo (int) $userId; ?>;
    const submitBtn = document.getElementById('recoverSubmit');
    const spinner   = document.getElementById('recoverSpinner');
    const alertBox  = document.getElementById('recoverAlert');

    function showError(msg) {
        alertBox.textContent = msg;
        alertBox.classList.remove('d-none');
    }

    submitBtn.addEventListener('click', async function () {
        alertBox.classList.add('d-none');

        const newEmail = document.getElementById('recover_new_email').value.trim();
        const confirmEmail = document.getElementById('recover_confirm_email').value.trim();
        const reason = document.getElementById('recover_reason').value.trim();
        const reset2fa = document.getElementById('recover_reset_2fa').checked;

        if (!newEmail || !confirmEmail || !reason) {
            return showError('Tous les champs marqués * sont requis.');
        }
        if (newEmail.toLowerCase() !== confirmEmail.toLowerCase()) {
            return showError('Les deux adresses ne correspondent pas.');
        }
        if (!document.getElementById('recover_identity').checked) {
            return showError("Confirmez avoir vérifié l'identité de la personne.");
        }

        // Récupère le token CSRF du formulaire principal (généré par csrf_field())
        const csrfInput = document.querySelector('form input[name="csrf_token"]');
        const csrfToken = csrfInput ? csrfInput.value : '';

        submitBtn.disabled = true;
        spinner.classList.remove('d-none');

        let success = false;

        try {
            const response = await fetch(baseUrl + 'user/recover-account/' + recoverUserId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    new_email: newEmail,
                    confirm_email: confirmEmail,
                    reason: reason,
                    reset_2fa: reset2fa
                })
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.success) {
                success = true;
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();

                // Toast orange si l'un des e-mails n'a pas pu partir, vert sinon
                const type = data.message.includes('Attention') ? 'warning' : 'success';

                // Rechargement de la page une fois le toast disparu
                showToast(data.message, type, function () {
                    window.location.reload();
                });
            } else {
                showError(data.message || data.error || 'Une erreur est survenue.');
            }
        } catch (e) {
            showError('Erreur réseau, veuillez réessayer.');
        } finally {
            // En cas de succès, le bouton reste bloqué jusqu'au rechargement
            if (!success) {
                submitBtn.disabled = false;
                spinner.classList.add('d-none');
            }
        }
    });
});

function showToast(message, type = 'success', onHidden = null) {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '3000';
        document.body.appendChild(container);
    }

    const toastEl = document.createElement('div');
    toastEl.className = 'toast align-items-center text-bg-' + type + ' border-0';
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');

    const wrapper = document.createElement('div');
    wrapper.className = 'd-flex';

    const body = document.createElement('div');
    body.className = 'toast-body';
    body.textContent = message; 

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'btn-close btn-close-white me-2 m-auto';
    closeBtn.setAttribute('data-bs-dismiss', 'toast');
    closeBtn.setAttribute('aria-label', 'Fermer');

    wrapper.appendChild(body);
    wrapper.appendChild(closeBtn);
    toastEl.appendChild(wrapper);
    container.appendChild(toastEl);

    toastEl.addEventListener('hidden.bs.toast', function () {
        toastEl.remove();
        if (onHidden) onHidden();
    });

    new bootstrap.Toast(toastEl, { delay: 4000 }).show();
}
</script>
<script>
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.modal').forEach(function (modal) {
			modal.addEventListener('hidden.bs.modal', function () {
				const dialog = modal.querySelector('.modal-dialog');
				if (dialog) {
					dialog.style.position = '';
					dialog.style.left = '';
					dialog.style.top = '';
					dialog.style.margin = '';
					dialog.style.width = '';
					dialog.style.maxWidth = '';
				}
			});

			modal.addEventListener('shown.bs.modal', function () {
				const dialog = modal.querySelector('.modal-dialog');
				const header = modal.querySelector('.modal-header');
				if (!dialog || !header) return;
				if (header.dataset.draggable) return;
				header.dataset.draggable = 'true';

				header.style.cursor = 'grab';

				let isDragging = false;
				let startX, startY, startLeft, startTop;

				header.addEventListener('mousedown', function (e) {
					if (e.target.closest('button')) return;

					isDragging = true;
					header.style.cursor = 'grabbing';

					const rect = dialog.getBoundingClientRect();
					startX = e.clientX;
					startY = e.clientY;
					startLeft = rect.left;
					startTop = rect.top;
					dialog.style.width = rect.width + 'px';
					dialog.style.maxWidth = 'none';
					dialog.style.position = 'fixed';
					dialog.style.left = startLeft + 'px';
					dialog.style.top = startTop + 'px';
					dialog.style.margin = '0';
				});

				document.addEventListener('mousemove', function (e) {
					if (!isDragging) return;
					const dx = e.clientX - startX;
					const dy = e.clientY - startY;
					dialog.style.left = (startLeft + dx) + 'px';
					dialog.style.top = (startTop + dy) + 'px';
				});

				document.addEventListener('mouseup', function () {
					if (isDragging) {
						isDragging = false;
						header.style.cursor = 'grab';
					}
				});
			});

		});
	});
</script>

<?php endif; ?>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?> 