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
    <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Détails de l'utilisateur</h4></div>

    <div class="ms-auto p-2 bd-highlight">
        <a href="<?php echo BASE_URL; ?>user" class="btn btn-secondary me-2">
            <i class="bi bi-arrow-left me-1"></i> Retour
        </a>
        <a href="<?php echo BASE_URL; ?>user/edit/<?php echo $user['id']; ?>" class="btn btn-warning me-2">
            <i class="bi bi-pencil me-1"></i> Modifier
        </a>
        <?php if (isAdmin()): ?>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'] ?? ''); ?>')" title="Supprimer l'utilisateur">
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
                    <h6 class="mb-3">Informations de base</h6>
                    <table class="table table-borderless">
                        <tr>
                            <th style="width: 150px;">Nom d'utilisateur :</th>
                            <td><?php echo h($user['username']); ?></td>
                        </tr>
                        <tr>
                            <th>Email :</th>
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
                                echo isset($user['type']) ? ($typeLabels[$user['type']] ?? $user['type']) : 'Non défini';
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
                <?php if (isset($user['type']) && in_array($user['type'], ['technicien', 'client']) && !empty($userPermissions)): ?>
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
            <?php if (isset($user['type']) && $user['type'] === 'client'): ?>
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
                                            <p class="mb-0"><i class="bi bi-check-circle text-success me-2 me-1"></i>Accès complet</p>
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
    </div>
</div>

<script>
function confirmDelete(userId, username) {
    if (confirm('Êtes-vous sûr de vouloir supprimer l\'utilisateur "' + username + '" ?\n\nCette action est irréversible et supprimera définitivement l\'utilisateur et toutes ses données associées.')) {
        window.location.href = '<?php echo BASE_URL; ?>user/delete/' + userId;
    }
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?> 