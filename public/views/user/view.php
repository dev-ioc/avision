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
                    onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'] ?? ''); ?>')"
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
                    <small class="text-muted">(<?php echo h($user['username']); ?>)</small>
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
                    <h6 class="mb-3">Informations</h6>
                    <table class="table table-borderless">
                        <tr>
                            <th style="width: 150px;">Nom d'utilisateur :</th>
                            <td><?php echo h($user['username']); ?></td>
                        </tr>
                        <tr>
                            <th>Email (Login):</th>
                            <td><?php echo h($user['email']); ?></td>
                        </tr>
                        <tr>
                            <th>Prénom :</th>
                            <td><?php echo h($user['first_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Nom :</th>
                            <td><?php echo h($user['last_name']); ?></td>
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
                                ?>
                            </td>
                        </tr>
                        <?php if (isset($user['type']) && $user['type'] === 'technicien'): ?>
                            <tr>
                                <th>Coefficient :</th>
                                <td><?php echo number_format($user['coef_utilisateur'], 2); ?></td>
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
                                                <h6 class="mb-0"><?php echo h($client['name']); ?></h6>
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
    function confirmDelete(userId, username) {
        if (confirm('Êtes-vous sûr de vouloir supprimer l\'utilisateur "' + username + '" ?\n\nCette action est irréversible et supprimera définitivement l\'utilisateur et toutes ses données associées.')) {
            window.location.href = '<?php echo BASE_URL; ?>user/delete/' + userId;
        }
    }
</script>
<script>
    function confirmDelete(userId, username) {
        if (confirm('Êtes-vous sûr de vouloir supprimer l\'utilisateur "' + username + '" ?\n\nCette action est irréversible et supprimera définitivement l\'utilisateur et toutes ses données associées.')) {
            window.location.href = '<?php echo BASE_URL; ?>user/delete/' + userId;
        }
    }
</script>

<script>
    function sendResetLink(userId) {
        if (!confirm('Envoyer un lien de réinitialisation de mot de passe à cet utilisateur ?')) {
            return;
        }

        // Afficher le loader
        showLoadingOverlay('Envoi du lien de réinitialisation en cours...');

        // Récupérer le token CSRF
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
            document.querySelector('input[name="csrf_token"]')?.value;

        fetch('<?php echo BASE_URL; ?>user/send-reset-link/' + userId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ csrf_token: csrfToken })
        })
            .then(response => response.json())
            .then(data => {
                // Cacher le loader
                hideLoadingOverlay();

                if (data.success) {
                    alert(data.message);
                    // Recharger l'historique si présent
                    if (typeof loadResetHistory === 'function') {
                        loadResetHistory(userId);
                    }
                } else {
                    alert('Erreur: ' + (data.message || 'Une erreur est survenue'));
                }
            })
            .catch(error => {
                // Cacher le loader en cas d'erreur
                hideLoadingOverlay();
                console.error('Erreur:', error);
                alert('Une erreur est survenue lors de l\'envoi.');
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

        // Ajouter l'overlay au body
        document.body.appendChild(overlay);

        // Empêcher le scroll
        document.body.style.overflow = 'hidden';
    }

    function hideLoadingOverlay() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.opacity = '0';
            setTimeout(() => {
                overlay.remove();
                // Réactiver le scroll
                document.body.style.overflow = '';
            }, 300);
        } else {
            document.body.style.overflow = '';
        }
    }
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>