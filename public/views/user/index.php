<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de la liste des clients
 * Affiche la liste de tous les clients avec leurs statistiques
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Utilisateurs',
    'users'
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
    <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Gestion des Utilisateurs</h4></div>

    <div class="ms-auto p-2 bd-highlight">
        <a href="<?php echo BASE_URL; ?>user/add" class="btn btn-primary">
            <i class="bi bi-plus me-1"></i> Nouvel utilisateur
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

    <div class="card">
        <div class="card-header py-2">
            <h5 class="card-title mb-0">Liste des utilisateurs</h5>
        </div>
        <div class="card-body py-2">
            <div class="table-responsive">
                <table id="usersTable" class="table table-striped table-hover dt-responsive">
                    <thead>
                        <tr>
                            <th>Nom d'utilisateur</th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th>Date de création</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($users) && !empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td data-label="Nom d'utilisateur">
                                        <a href="<?php echo BASE_URL; ?>user/view/<?php echo $user['id']; ?>" 
                                           class="text-decoration-none fw-bold" 
                                           title="Voir l'utilisateur">
                                            <?php echo htmlspecialchars($user['username'] ?? ''); ?>
                                        </a>
                                    </td>
                                    <td data-label="Nom"><?php echo htmlspecialchars($user['last_name'] ?? ''); ?></td>
                                    <td data-label="Prénom"><?php echo htmlspecialchars($user['first_name'] ?? ''); ?></td>
                                    <td data-label="Email"><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                                    <td data-label="Type" data-order="<?php echo $user['user_type'] ?? ''; ?>">
                                        <?php
                                        $typeClass = '';
                                        $typeLabel = '';
                                        $userType = $user['user_type'] ?? '';
                                        $isAdmin = $user['is_admin'] ?? false;
                                        
                                        // Déterminer le type et la couleur du badge
                                        switch ($userType) {
                                            case 'technicien':
                                                $typeClass = 'warning';
                                                $typeLabel = 'Technicien';
                                                break;
                                            case 'adv':
                                                $typeClass = 'primary';
                                                $typeLabel = 'Commercial (ADV)';
                                                break;
                                            case 'client':
                                                $typeClass = 'info';
                                                $typeLabel = 'Client';
                                                break;
                                            default:
                                                $typeClass = 'secondary';
                                                $typeLabel = 'Inconnu';
                                                break;
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $typeClass; ?>"><?php echo $typeLabel; ?></span>
                                        <?php if ($isAdmin): ?>
                                            <i class="bi bi-shield-fill text-danger ms-1" title="Administrateur"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Statut" data-order="<?php echo $user['status'] ?? 0; ?>">
                                        <?php if (($user['status'] ?? 0) == 1): ?>
                                            <span class="badge bg-success">Actif</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Date de création" data-order="<?php echo isset($user['created_at']) ? strtotime($user['created_at']) : 0; ?>">
                                        <?php echo isset($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : ''; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                                                    <?php else: ?>
                                <?php // Laisser tbody vide. DataTables utilisera language.emptyTable ?>
                            <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<!-- DataTable Persistence -->
<script src="<?php echo BASE_URL; ?>assets/js/datatable-persistence.js"></script>

<!-- Page JS -->
<script src="<?php echo BASE_URL; ?>assets/js/users-datatable.js"></script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?> 