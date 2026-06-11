<?php
require_once __DIR__ . '/../../includes/functions.php';

if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

setPageVariables('Utilisateurs', 'users');
$currentPage = 'users';

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="py-4 mb-0">Gestion des utilisateurs</h4>
        <a href="<?= BASE_URL ?>user/add" class="btn btn-primary">
            <i class="bi bi-plus me-1"></i> Nouvel utilisateur
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible">
            <?= $_SESSION['error'];
            unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible">
            <?= $_SESSION['success'];
            unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" id="userTypeTabs">
        <li class="nav-item">
            <a class="nav-link active" data-filter="all" href="#">
                <i class="bi bi-people me-1"></i> Tous
                <span class="badge bg-secondary ms-1" id="count-all">0</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-filter="videosonic" href="#">
                <i class="bi bi-building me-1"></i> VIDEOSONIC
                <span class="badge bg-primary ms-1" id="count-videosonic">0</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-filter="client" href="#">
                <i class="bi bi-person me-1"></i> Clients
                <span class="badge bg-info ms-1" id="count-client">0</span>
            </a>
        </li>
    </ul>

    <!-- Barre de recherche -->
    <div class="mb-4">
        <div class="input-group" style="max-width: 400px;">
            <span class="input-group-text bg-body border-end-0">
                <i class="bi bi-search text-muted"></i>
            </span>
            <input type="text" class="form-control border-start-0 bg-body" id="userSearch"
                placeholder="Rechercher un utilisateur…">
        </div>
    </div>

    <!-- Grille de cartes -->
    <div class="row g-3" id="usersGrid">
        <?php if (isset($users) && !empty($users)): ?>
            <?php foreach ($users as $user): ?>
                <?php
                $userType = $user['user_type'] ?? '';
                $isAdmin = $user['is_admin'] ?? false;
                $filterGroup = ($userType === 'client') ? 'client' : 'videosonic';

                // Initiales avatar
                $initials = strtoupper(
                    substr($user['first_name'] ?? '', 0, 1) .
                    substr($user['last_name'] ?? '', 0, 1)
                );
                if (empty(trim($initials))) {
                    $initials = strtoupper(substr($user['username'] ?? '?', 0, 2));
                }

                // Badge rôle
                switch ($userType) {
                    case 'technicien':
                        $pillClass = 'pill-tech';
                        $pillIcon = 'bi-tools';
                        $pillLabel = 'Technicien';
                        break;
                    case 'adv':
                        $pillClass = 'pill-adv';
                        $pillIcon = 'bi-briefcase';
                        $pillLabel = 'Commercial';
                        break;
                    case 'client':
                        $pillClass = 'pill-client';
                        $pillIcon = 'bi-building';
                        $pillLabel = 'Client';
                        break;
                    default:
                        $pillClass = 'pill-default';
                        $pillIcon = 'bi-person';
                        $pillLabel = 'Inconnu';
                        break;
                }

                $isActive = ($user['status'] ?? 0) == 1;
                $createdAt = isset($user['created_at'])
                    ? date('d/m/Y', strtotime($user['created_at']))
                    : '—';
                $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                $username = $user['username'] ?? '';
                ?>

                <div class="col-sm-6 col-md-4 col-xl-3 user-card-col" data-user-type="<?= $filterGroup ?>"
                    data-search="<?= htmlspecialchars(strtolower($fullName . ' ' . $username . ' ' . $pillLabel)) ?>">
                    <div class="card h-100 user-card">
                        <div class="card-body d-flex flex-column gap-2 p-3">

                            <!-- En-tête : avatar + nom -->
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-avatar avatar-<?= $filterGroup ?>">
                                    <?= htmlspecialchars($initials) ?>
                                </div>
                                <div class="overflow-hidden">
                                    <div class="fw-500 text-truncate user-fullname">
                                        <?= htmlspecialchars($fullName ?: $username) ?>
                                    </div>
                                    <div class="text-muted small">@
                                        <?= htmlspecialchars($username) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Badge rôle + admin -->
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="user-pill <?= $pillClass ?>">
                                    <i class="bi <?= $pillIcon ?>"></i>
                                    <?= $pillLabel ?>
                                </span>
                                <?php if ($isAdmin): ?>
                                    <span class="badge-admin">
                                        <i class="bi bi-shield-fill"></i> Admin
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Date + statut -->
                            <div class="d-flex align-items-center justify-content-between">
                                <small class="text-muted">Créé
                                    <?= $createdAt ?>
                                </small>
                                <span class="d-flex align-items-center gap-1">
                                    <span class="status-dot <?= $isActive ? 'dot-active' : 'dot-inactive' ?>"></span>
                                    <small class="text-muted">
                                        <?= $isActive ? 'Actif' : 'Inactif' ?>
                                    </small>
                                </span>
                            </div>

                        </div>

                        <!-- Actions -->
                        <div class="card-footer bg-transparent d-flex gap-2 p-2">
                            <a href="<?= BASE_URL ?>user/view/<?= $user['id'] ?>"
                                class="btn btn-sm btn-outline-secondary flex-fill" title="Voir">
                                <i class="bi bi-eye me-1"></i> Voir
                            </a>
                            <a href="<?= BASE_URL ?>user/edit/<?= $user['id'] ?>"
                                class="btn btn-sm btn-outline-primary flex-fill" title="Modifier">
                                <i class="bi bi-pencil me-1"></i> Modifier
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-info flex-fill btn-reset-pwd"
                                data-user-id="<?= $user['id'] ?>"
                                data-user-email="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                data-user-name="<?= htmlspecialchars($fullName ?: $username) ?>"
                                title="Envoyer un lien de réinitialisation">
                                <i class="bi bi-envelope me-1"></i> Reset MDP
                            </button>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Message aucun résultat -->
    <div id="noResults" class="text-center text-muted py-5" style="display:none;">
        <i class="bi bi-person-x fs-1 d-block mb-2"></i>
        Aucun utilisateur trouvé.
    </div>

</div>


<!-- Modal confirmation reset MDP -->
<div class="modal fade" id="resetPwdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-envelope me-2"></i> Réinitialisation du mot de passe
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Envoyer un lien de réinitialisation à :</p>
                <p class="fw-bold mb-0" id="resetPwdName"></p>
                <p class="text-muted small" id="resetPwdEmail"></p>
            </div>
            <div class="modal-footer">
                <?= csrf_field() ?>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="confirmResetPwd">
                    <i class="bi bi-send me-1"></i> Envoyer le lien
                </button>
            </div>
        </div>
    </div>
</div>


<style>
    .user-card {
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .user-card:hover {
        border-color: var(--bs-primary);
        box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: 500;
        flex-shrink: 0;
    }

    .avatar-videosonic {
        background-color: #dbeafe;
        color: #1e40af;
    }

    .avatar-client {
        background-color: #d1fae5;
        color: #065f46;
    }

    .user-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 500;
    }

    .pill-tech {
        background: #fef3c7;
        color: #92400e;
    }

    .pill-adv {
        background: #dbeafe;
        color: #1e40af;
    }

    .pill-client {
        background: #d1fae5;
        color: #065f46;
    }

    .pill-default {
        background: var(--bs-secondary-bg);
        color: var(--bs-secondary-color);
    }

    .badge-admin {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: 10px;
        color: var(--bs-danger);
    }

    .status-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .dot-active {
        background: #10b981;
    }

    .dot-inactive {
        background: #ef4444;
    }

    .fw-500 {
        font-weight: 500;
    }

    .user-fullname {
        font-size: 14px;
    }
</style>


<script>
    document.addEventListener('DOMContentLoaded', function () {

        const cards = document.querySelectorAll('.user-card-col');
        const tabs = document.querySelectorAll('#userTypeTabs .nav-link');
        const search = document.getElementById('userSearch');
        const noResult = document.getElementById('noResults');

        let currentFilter = 'all';

        // Compteurs
        let counts = { all: 0, videosonic: 0, client: 0 };
        cards.forEach(card => {
            const type = card.dataset.userType;
            counts.all++;
            if (counts[type] !== undefined) counts[type]++;
        });
        document.getElementById('count-all').textContent = counts.all;
        document.getElementById('count-videosonic').textContent = counts.videosonic;
        document.getElementById('count-client').textContent = counts.client;

        // Filtrage
        function applyFilters() {
            const query = search.value.toLowerCase().trim();
            let visible = 0;

            cards.forEach(card => {
                const matchTab = currentFilter === 'all' || card.dataset.userType === currentFilter;
                const matchSearch = !query || card.dataset.search.includes(query);
                const show = matchTab && matchSearch;
                card.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            noResult.style.display = visible === 0 ? 'block' : 'none';
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', function (e) {
                e.preventDefault();
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                applyFilters();
            });
        });

        search.addEventListener('input', applyFilters);

        // Reset mot de passe
        let resetUserId = null;

        document.querySelectorAll('.btn-reset-pwd').forEach(btn => {
            btn.addEventListener('click', function () {
                resetUserId = this.dataset.userId;
                document.getElementById('resetPwdName').textContent = this.dataset.userName;
                document.getElementById('resetPwdEmail').textContent = this.dataset.userEmail;
                new bootstrap.Modal(document.getElementById('resetPwdModal')).show();
            });
        });

        document.getElementById('confirmResetPwd').addEventListener('click', function () {
            if (!resetUserId) return;

            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Envoi…';

            fetch('<?= BASE_URL ?>user/sendResetLink/' + resetUserId, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': '<?= csrf_token() ?>',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ csrf_token: '<?= csrf_token() ?>' })
            })
                .then(r => r.json())
                .then(data => {
                    bootstrap.Modal.getInstance(document.getElementById('resetPwdModal')).hide();

                    const alertClass = data.success ? 'alert-success' : 'alert-danger';
                    const msg = document.createElement('div');
                    msg.className = `alert ${alertClass} alert-dismissible fade show`;
                    msg.innerHTML = `${data.message || (data.success ? 'Lien envoyé.' : 'Erreur.')}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
                    document.querySelector('.container-p-y').prepend(msg);
                })
                .catch(() => {
                    alert('Erreur lors de l\'envoi.');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-send me-1"></i> Envoyer le lien';
                    resetUserId = null;
                });
        });

    });
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>