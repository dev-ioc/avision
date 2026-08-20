<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de consultation d'utilisateur
 * Affiche les informations détaillées d'un utilisateur
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

// Récupérer l'ID de l'utilisateur depuis l'URL
$userId = isset($user['id']) ? $user['id'] : '';

setPageVariables(
    'Utilisateur',
    'users' . ($userId ? '_view_' . $userId : '')
);

// Définir la page courante pour le menu
$currentPage = 'users';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">

    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">Détails de l'utilisateur</h4>
        </div>

        <div class="ms-auto p-2 bd-highlight">
            <a href="<?php echo BASE_URL; ?>user" class="btn btn-secondary me-2">
                <i class="bi bi-arrow-left me-1"></i> Retour
            </a>
            <a href="<?php echo BASE_URL; ?>user/edit/<?php echo $user['id']; ?>" class="btn btn-warning me-2">
                <i class="bi bi-pencil me-1"></i> Modifier
            </a>
            <button type="button" class="btn btn-info me-2" onclick="sendResetLink(<?php echo $user['id']; ?>)">
                <?= csrf_field() ?>
                <i class="bi bi-envelope me-1"></i> Envoyer lien de réinitialisation
            </button>
            <?php if (isAdmin()): ?>
                <button type="button" class="btn btn-outline-danger btn-sm"
                    onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>')"
                    title="Supprimer l'utilisateur">
                    <i class="bi bi-trash"></i>
                </button>
            <?php endif; ?>
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

    <!-- Carte des informations de l'utilisateur -->
    <div class="card">
        <div class="card-header py-2">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                    <small class="text-muted">(
                        <?php echo h($user['first_name']); ?>)
                    </small>
                </h5>
                <span class="badge bg-<?php echo $user['status'] ? 'success' : 'danger'; ?>">
                    <?php echo $user['status'] ? 'Actif' : 'Inactif'; ?>
                </span>
            </div>
        </div>
        <div class="card-body py-2">
            <div class="row">
                <!-- Informations de base -->
                <div class="col-md-6">
                    <h6 class="mb-3">Informations de base</h6>
                    <table class="table table-borderless">
                        <!-- <tr>
                            <th style="width: 150px;">Nom d'utilisateur :</th>
                            <td><?php echo h($user['first_name']); ?></td>
                        </tr> -->
                        <tr>
                            <th>Email :</th>
                            <td>
                                <?php echo h($user['email']); ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Prénom :</th>
                            <td>
                                <?php echo h($user['first_name']); ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Nom :</th>
                            <td>
                                <?php echo h($user['last_name']); ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Type :</th>
                            <td>
                                <?php
                                $typeLabels = [
                                    'admin' => 'Administrateur',
                                    'technicien' => 'Technicien',
                                    'client' => 'Client'
                                ];
                                echo isset($user['user_type']) ? ($typeLabels[$user['user_type']] ?? $user['user_type']) : 'Non défini';
                                echo isset($user['user_type']) ? ($typeLabels[$user['user_type']] ?? $user['user_type']) : 'Non défini';
                                ?>
                            </td>
                        </tr>
                        <?php if (isset($user['user_type']) && in_array($user['user_type'], ['technicien', 'client']) && !empty($userPermissions)): ?>
                            <tr>
                                <th>Coefficient :</th>
                                <td>
                                    <?php echo number_format($user['coef_utilisateur'], 2); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>

                <!-- Permissions -->
                <?php if (isset($user['user_type']) && in_array($user['user_type'], ['technicien', 'client']) && !empty($userPermissions)): ?>
                    <div class="col-md-6">
                        <h6 class="mb-3">Permissions</h6>
                        <div class="permissions-list">
                            <?php
                            // Grouper les permissions par catégorie
                            $groupedPermissions = [];
                            foreach ($userPermissions as $permission) {
                                $category = $permission['category'] ?? 'general';
                                if (!isset($groupedPermissions[$category])) {
                                    $groupedPermissions[$category] = [];
                                }
                                $groupedPermissions[$category][] = $permission;
                            }

                            // Afficher les permissions groupées
                            foreach ($groupedPermissions as $category => $permissions):
                                ?>
                                <div class="permission-category mb-3">
                                    <h6 class="text-muted mb-2">
                                        <?php echo ucfirst($category); ?>
                                    </h6>
                                    <ul class="list-unstyled ms-3">
                                        <?php foreach ($permissions as $permission): ?>
                                            <li>
                                                <i class="bi bi-check text-success me-2 me-1"></i>
                                                <?php echo h($permission['description']); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Localisations pour les utilisateurs de type client -->
            <?php if (isset($user['user_type']) && $user['user_type'] === 'client'): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <h6 class="mb-3">Localisations</h6>
                        <div class="locations-list">
                            <?php
                            // Récupérer les localisations de l'utilisateur
                            $userLocations = $this->userModel->getUserLocations($user['id']);

                            if (!empty($userLocations)) {
                                // Grouper par client
                                $groupedLocations = [];
                                foreach ($userLocations as $location) {
                                    $clientId = $location['client_id'];
                                    if (!isset($groupedLocations[$clientId])) {
                                        $groupedLocations[$clientId] = [
                                            'client_full' => false,
                                            'sites' => [],
                                            'rooms' => []
                                        ];
                                    }

                                    // Si c'est un accès complet au client
                                    if (!$location['site_id'] && !$location['room_id']) {
                                        $groupedLocations[$clientId]['client_full'] = true;
                                        continue;
                                    }

                                    // Si c'est un site
                                    if ($location['site_id'] && !$location['room_id']) {
                                        $groupedLocations[$clientId]['sites'][] = $location['site_id'];
                                    }

                                    // Si c'est une salle
                                    if ($location['room_id']) {
                                        $groupedLocations[$clientId]['rooms'][] = [
                                            'site_id' => $location['site_id'],
                                            'room_id' => $location['room_id']
                                        ];
                                    }
                                }

                                // Afficher les localisations groupées
                                foreach ($groupedLocations as $clientId => $locations):
                                    $client = $this->userModel->getClientById($clientId);
                                    if ($client):
                                        ?>
                                        <div class="card mb-3">
                                            <div class="card-header py-2">
                                                <h6 class="mb-0">
                                                    <?php echo h($client['name']); ?>
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <?php if ($locations['client_full']): ?>
                                                    <p class="mb-0"><i class="bi bi-check-circle text-success me-2 me-1"></i>Accès complet
                                                    </p>
                                                <?php else: ?>
                                                    <?php if (!empty($locations['sites'])): ?>
                                                        <h6 class="text-muted mb-2">Sites</h6>
                                                        <ul class="list-unstyled ms-3 mb-3">
                                                            <?php
                                                            foreach ($locations['sites'] as $siteId):
                                                                $site = $this->userModel->getSiteById($siteId);
                                                                if ($site):
                                                                    ?>
                                                                    <li>
                                                                        <i class="bi bi-building text-primary me-2 me-1"></i>
                                                                        <?php echo h($site['name']); ?>
                                                                    </li>
                                                                    <?php
                                                                endif;
                                                            endforeach;
                                                            ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                    <?php if (!empty($locations['rooms'])): ?>
                                                        <h6 class="text-muted mb-2">Salles</h6>
                                                        <ul class="list-unstyled ms-3">
                                                            <?php
                                                            foreach ($locations['rooms'] as $roomLocation):
                                                                $site = $this->userModel->getSiteById($roomLocation['site_id']);
                                                                $room = $this->userModel->getRoomById($roomLocation['room_id']);
                                                                if ($site && $room):
                                                                    ?>
                                                                    <li>
                                                                        <i class="bi bi-door-open text-info me-2 me-1"></i>
                                                                        <?php echo htmlspecialchars($site['name'] . ' - ' . $room['name']); ?>
                                                                    </li>
                                                                    <?php
                                                                endif;
                                                            endforeach;
                                                            ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php
                                    endif;
                                endforeach;
                                ?>
                                <?php
                            } else {
                                echo '<div class="alert alert-info"><i class="bi bi-info-circle me-2 me-1"></i>Aucune localisation attribuée.</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <!-- Ajouter une section pour l'historique des réinitialisations -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header py-2">
                        <h5 class="card-title mb-0">Historique des réinitialisations de mot de passe</h5>
                    </div>
                    <div class="card-body py-2" id="resetHistory">
                        <?php
                        $resetHistory = $this->userModel->getPasswordResetHistory($user['id']);
                        if (!empty($resetHistory)):
                            ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Demandé par</th>
                                            <th>Statut</th>
                                            <th>IP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($resetHistory as $history): ?>
                                            <tr>
                                                <td>
                                                    <?php echo formatDateFrench($history['created_at']); ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    if ($history['requested_by'] === null) {
                                                        echo '<span class="badge bg-info">Par l\'utilisateur</span>';
                                                    } else {
                                                        echo htmlspecialchars($history['requested_by_first_name'] . ' ' . $history['requested_by_last_name']);
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($history['used_at']): ?>
                                                        <span class="badge bg-success">Utilisé</span>
                                                    <?php elseif (strtotime($history['expires_at']) < time()): ?>
                                                        <span class="badge bg-danger">Expiré</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">En attente</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($history['request_ip'] ?? '-'); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">Aucune demande de réinitialisation enregistrée.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(userId, first_name) {
        if (confirm('Êtes-vous sûr de vouloir supprimer l\'utilisateur "' + first_name + '" ?\n\nCette action est irréversible et supprimera définitivement l\'utilisateur et toutes ses données associées.')) {
            window.location.href = '<?php echo BASE_URL; ?>user/delete/' + userId;
        }
    }
</script>
<script>
    function confirmDelete(userId, first_name) {
        if (confirm('Êtes-vous sûr de vouloir supprimer l\'utilisateur "' + first_name + '" ?\n\nCette action est irréversible et supprimera définitivement l\'utilisateur et toutes ses données associées.')) {
            window.location.href = '<?php echo BASE_URL; ?>user/delete/' + userId;
        }
    }
</script>

<script>
    // ─── TOAST NOTIFICATION (haut droite) ────────────────────────────────────
    function showToast(message, type) {
        type = type || 'info';

        // Supprimer les toasts existants pour éviter l'empilement
        document.querySelectorAll('.custom-toast').forEach(t => t.remove());

        if (!document.getElementById('toast-styles')) {
            const style = document.createElement('style');
            style.id = 'toast-styles';
            style.textContent = `
                @keyframes toastSlideIn {
                    from { transform: translateX(110%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes toastSlideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(110%); opacity: 0; }
                }
                .custom-toast {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    min-width: 320px;
                    max-width: 420px;
                    z-index: 99999;
                    border-radius: 10px;
                    padding: 14px 16px;
                    overflow: hidden;
                    background: #fff;
                    box-shadow: 0 8px 24px rgba(0,0,0,.18);
                    border-left: 4px solid;
                    animation: toastSlideIn .3s ease-out;
                    cursor: pointer;
                }
                .custom-toast:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 12px 28px rgba(0,0,0,.22);
                }
                .custom-toast .toast-progress {
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    height: 3px;
                    width: 100%;
                    transition: width 4s linear;
                }
            `;
            document.head.appendChild(style);
        }

        const config = {
            success: { icon: 'bi-check-circle-fill', title: 'Succès', color: '#28a745' },
            danger: { icon: 'bi-exclamation-triangle-fill', title: 'Erreur', color: '#dc3545' },
            warning: { icon: 'bi-exclamation-circle-fill', title: 'Attention', color: '#ffc107' },
            info: { icon: 'bi-info-circle-fill', title: 'Information', color: '#17a2b8' }
        }[type] || { icon: 'bi-info-circle-fill', title: 'Information', color: '#17a2b8' };

        const toast = document.createElement('div');
        toast.className = 'custom-toast';
        toast.style.borderLeftColor = config.color;
        toast.innerHTML = `
            <div class="d-flex align-items-start">
                <i class="bi ${config.icon} me-2 fs-5" style="color:${config.color};"></i>
                <div class="flex-grow-1">
                    <strong>${config.title}</strong>
                    <div class="small mt-1 text-secondary">${message}</div>
                </div>
                <button type="button" class="btn-close ms-2" aria-label="Fermer"></button>
            </div>
            <div class="toast-progress" style="background:${config.color};"></div>
        `;

        document.body.appendChild(toast);

        const progress = toast.querySelector('.toast-progress');
        requestAnimationFrame(() => { progress.style.width = '0%'; });

        function dismiss() {
            toast.style.animation = 'toastSlideOut .3s ease-out forwards';
            setTimeout(() => { if (toast.parentNode) toast.remove(); }, 280);
        }

        toast.querySelector('.btn-close').addEventListener('click', (e) => {
            e.stopPropagation();
            dismiss();
        });
        toast.addEventListener('click', dismiss);
        setTimeout(dismiss, 4000);
    }
    function showConfirmModal(options) {
        const {
            title = 'Confirmation',
            message = 'Êtes-vous sûr de vouloir continuer ?',
            confirmText = 'Confirmer',
            cancelText = 'Annuler',
            icon = 'bi-question-circle',
            iconColor = '#0d6efd',
            onConfirm = null
        } = options;

        // Supprimer une éventuelle ancienne modal
        document.getElementById('customConfirmModal')?.remove();

        // Ajouter les styles une seule fois
        if (!document.getElementById('confirm-modal-styles')) {
            const style = document.createElement('style');
            style.id = 'confirm-modal-styles';

            style.textContent = `
            @keyframes confirmModalFadeIn {
                from {
                    opacity: 0;
                }
                to {
                    opacity: 1;
                }
            }

            @keyframes confirmModalScaleIn {
                from {
                    transform: scale(0.9) translateY(10px);
                    opacity: 0;
                }
                to {
                    transform: scale(1) translateY(0);
                    opacity: 1;
                }
            }

            @keyframes confirmModalFadeOut {
                from {
                    opacity: 1;
                }
                to {
                    opacity: 0;
                }
            }

            .custom-confirm-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.55);
                backdrop-filter: blur(4px);
                z-index: 100000;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                animation: confirmModalFadeIn 0.2s ease;
            }

            .custom-confirm-modal {
                width: 100%;
                max-width: 430px;
                background: #fff;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
                overflow: hidden;
                animation: confirmModalScaleIn 0.25s ease;
            }

            .custom-confirm-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 18px 22px;
                border-bottom: 1px solid #eee;
            }

            .custom-confirm-title {
                margin: 0;
                font-size: 18px;
                font-weight: 600;
                color: #212529;
            }

            .custom-confirm-close {
                border: none;
                background: transparent;
                font-size: 20px;
                color: #6c757d;
                cursor: pointer;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                transition: all 0.2s ease;
            }

            .custom-confirm-close:hover {
                background: #f1f3f5;
                color: #212529;
            }

            .custom-confirm-body {
                text-align: center;
                padding: 30px 25px 25px;
            }

            .custom-confirm-icon {
                width: 68px;
                height: 68px;
                margin: 0 auto 18px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(13, 110, 253, 0.1);
            }

            .custom-confirm-icon i {
                font-size: 30px;
            }

            .custom-confirm-message {
                margin: 0;
                color: #6c757d;
                font-size: 14px;
                line-height: 1.6;
            }

            .custom-confirm-footer {
                display: flex;
                justify-content: center;
                gap: 10px;
                padding: 0 25px 25px;
            }

            .custom-confirm-btn {
                border: none;
                border-radius: 8px;
                padding: 10px 20px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .custom-confirm-btn-cancel {
                background: #f1f3f5;
                color: #495057;
            }

            .custom-confirm-btn-cancel:hover {
                background: #e9ecef;
            }

            .custom-confirm-btn-confirm {
                color: white;
                box-shadow: 0 4px 10px rgba(13, 110, 253, 0.2);
            }

            .custom-confirm-btn-confirm:hover {
                filter: brightness(0.92);
                transform: translateY(-1px);
            }

            @media (max-width: 480px) {
                .custom-confirm-modal {
                    max-width: 100%;
                }

                .custom-confirm-footer {
                    flex-direction: column-reverse;
                }

                .custom-confirm-btn {
                    width: 100%;
                }
            }
        `;

            document.head.appendChild(style);
        }

        // Créer la modal
        const overlay = document.createElement('div');
        overlay.id = 'customConfirmModal';
        overlay.className = 'custom-confirm-overlay';

        overlay.innerHTML = `
        <div class="custom-confirm-modal" role="dialog" aria-modal="true">

            <div class="custom-confirm-header">
                <h5 class="custom-confirm-title">
                    ${title}
                </h5>

                <button type="button"
                        class="custom-confirm-close"
                        aria-label="Fermer">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="custom-confirm-body">

                <div class="custom-confirm-icon"
                     style="background: ${iconColor}15;">
                    <i class="bi ${icon}"
                       style="color: ${iconColor};"></i>
                </div>

                <p class="custom-confirm-message">
                    ${message}
                </p>

            </div>

            <div class="custom-confirm-footer">

                <button type="button"
                        class="custom-confirm-btn custom-confirm-btn-cancel">
                    ${cancelText}
                </button>

                <button type="button"
                        class="custom-confirm-btn custom-confirm-btn-confirm"
                        style="background: ${iconColor};">
                    <i class="bi bi-check-lg me-1"></i>
                    ${confirmText}
                </button>

            </div>

        </div>
    `;

        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';

        const closeModal = () => {
            overlay.style.animation = 'confirmModalFadeOut 0.2s ease forwards';

            setTimeout(() => {
                overlay.remove();
                document.body.style.overflow = '';
            }, 180);
        };

        // Bouton fermer
        overlay.querySelector('.custom-confirm-close')
            .addEventListener('click', closeModal);

        // Bouton annuler
        overlay.querySelector('.custom-confirm-btn-cancel')
            .addEventListener('click', closeModal);

        // Bouton confirmer
        overlay.querySelector('.custom-confirm-btn-confirm')
            .addEventListener('click', () => {
                closeModal();

                if (typeof onConfirm === 'function') {
                    onConfirm();
                }
            });

        // Cliquer sur le fond ferme la modal
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) {
                closeModal();
            }
        });

        // Touche Échap
        const handleEscape = (event) => {
            if (event.key === 'Escape') {
                closeModal();
                document.removeEventListener('keydown', handleEscape);
            }
        };

        document.addEventListener('keydown', handleEscape);
    }
    function sendResetLink(userId) {

        showConfirmModal({
            title: 'Réinitialisation du mot de passe',
            message: 'Voulez-vous envoyer un lien de réinitialisation de mot de passe à cet utilisateur ?',
            confirmText: 'Envoyer le lien',
            cancelText: 'Annuler',
            icon: 'bi-envelope-at',
            iconColor: '#0d6efd',

            onConfirm: () => {

                // Afficher le loader
                showLoadingOverlay('Envoi du lien de réinitialisation en cours...');

                // Récupérer le token CSRF
                const csrfToken =
                    document.querySelector('meta[name="csrf-token"]')?.content ||
                    document.querySelector('input[name="csrf_token"]')?.value;

                fetch('<?php echo BASE_URL; ?>user/send-reset-link/' + userId, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken
                    })
                })
                    .then(response => response.json())
                    .then(data => {

                        hideLoadingOverlay();

                        if (data.success) {

                            showToast(data.message, 'success');

                            if (typeof loadResetHistory === 'function') {
                                loadResetHistory(userId);
                            }

                        } else {

                            showToast(
                                data.message || 'Une erreur est survenue',
                                'danger'
                            );
                        }
                    })
                    .catch(error => {

                        hideLoadingOverlay();

                        console.error('Erreur:', error);

                        showToast(
                            'Une erreur est survenue lors de l\'envoi.',
                            'danger'
                        );
                    });
            }
        });
    }
    function loadResetHistory(userId) {
        fetch('<?php echo BASE_URL; ?>user/reset-history/' + userId, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const historyContainer = document.getElementById('resetHistory');
                    if (historyContainer) {
                        historyContainer.innerHTML = data.html;
                    }
                }
            })
            .catch(error => console.error('Erreur:', error));
    }

    // ─── LOADER OVERLAY ──────────────────────────────────────────────────────
    function showLoadingOverlay(message) {
        // Supprimer un overlay existant
        const existingOverlay = document.getElementById('loadingOverlay');
        if (existingOverlay) {
            existingOverlay.remove();
        }

        // Créer l'overlay
        const overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 99999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            transition: opacity 0.3s ease;
            opacity: 1;
        `;

        // Contenu du loader
        overlay.innerHTML = `
            <div style="
                background: white;
                border-radius: 16px;
                padding: 40px 50px;
                max-width: 400px;
                width: 90%;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                animation: scaleIn 0.3s ease;
            ">
                <div style="
                    width: 60px;
                    height: 60px;
                    margin: 0 auto 20px auto;
                    border: 4px solid #e9ecef;
                    border-top: 4px solid #0d6efd;
                    border-radius: 50%;
                    animation: spin 0.8s linear infinite;
                "></div>
                <h5 style="margin-bottom: 8px; color: #212529; font-weight: 600;">
                    <i class="bi bi-envelope me-2"></i>Envoi en cours
                </h5>
                <p style="color: #6c757d; margin-bottom: 0; font-size: 14px;">
                    ${message || 'Veuillez patienter...'}
                </p>
                <div style="
                    margin-top: 16px;
                    height: 3px;
                    background: #e9ecef;
                    border-radius: 2px;
                    overflow: hidden;
                ">
                    <div style="
                        height: 100%;
                        width: 0%;
                        background: linear-gradient(90deg, #0d6efd, #0dcaf0);
                        border-radius: 2px;
                        animation: progressBar 2s ease-in-out infinite;
                    "></div>
                </div>
            </div>
        `;

        // Ajouter les animations
        const style = document.createElement('style');
        style.id = 'loaderStyles';
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            @keyframes scaleIn {
                0% { transform: scale(0.9); opacity: 0; }
                100% { transform: scale(1); opacity: 1; }
            }
            @keyframes progressBar {
                0% { width: 0%; }
                50% { width: 70%; }
                100% { width: 0%; }
            }
            .loader-enter {
                animation: scaleIn 0.3s ease;
            }
        `;

        if (!document.getElementById('loaderStyles')) {
            document.head.appendChild(style);
        }

        document.body.appendChild(overlay);

        document.body.style.overflow = 'hidden';
    }

    function hideLoadingOverlay() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.opacity = '0';
            setTimeout(() => {
                overlay.remove();
                document.body.style.overflow = '';
            }, 300);
        } else {
            document.body.style.overflow = '';
        }
    }
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>