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
    'Clients',
    'clients'
);

// Définir la page courante pour le menu
$currentPage = 'clients';

// Inclure le header qui contient le menu latéral

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">

<div class="d-flex bd-highlight mb-3">
    <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Gestion des Clients</h4></div>

    <div class="ms-auto p-2 bd-highlight">
        <?php if (canModifyClients()): ?>
            <a href="<?php echo BASE_URL; ?>clients/add" class="btn btn-primary">
                <i class="bi bi-plus me-2 me-1"></i>Ajouter un client
            </a>
        <?php endif; ?>
    </div>
</div>

            
            
            <!-- Liste des clients -->

                    <div class="table-responsive">
                        <table id="clientsTable" class="table table-striped table-hover dt-responsive">
                            <thead>
                                <tr>
                                    <th>Nom du Client</th>
                                    <th>Ville</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Statut</th>
                                    <th>Sites</th>
                                    <th>Salles</th>
                                    <th>Contrats</th>
                                    <th>Tickets Restants</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($clients) && !empty($clients)): ?>
                                    <?php foreach ($clients as $client): ?>
                                        <tr>
                                            <td data-label="Nom du Client">
                                                <a href="<?php echo BASE_URL; ?>clients/view/<?php echo $client['id']; ?>" 
                                                   class="text-decoration-none fw-bold" 
                                                   title="Voir le client">
                                                    <?php echo htmlspecialchars($client['name'] ?? ''); ?>
                                                </a>
                                            </td>
                                            <td data-label="Ville"><?php echo htmlspecialchars($client['city'] ?? ''); ?></td>
                                            <td data-label="Email"><?php echo htmlspecialchars($client['email'] ?? ''); ?></td>
                                            <td data-label="Téléphone"><?php echo htmlspecialchars($client['phone'] ?? ''); ?></td>
                                            <td data-label="Statut" data-order="<?php echo $client['status'] ?? 0; ?>">
                                                <?php if (isset($client['status']) && $client['status'] == 1): ?>
                                                    <span class="badge bg-success">Actif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Sites" data-order="<?php echo $client['site_count'] ?? 0; ?>">
                                                <span class="badge bg-primary">
                                                    <?php echo $client['site_count']; ?>
                                                </span>
                                            </td>
                                            <td data-label="Salles" data-order="<?php echo $client['room_count'] ?? 0; ?>">
                                                <span class="badge bg-info">
                                                    <?php echo $client['room_count']; ?>
                                                </span>
                                            </td>
                                            <td data-label="Contrats" data-order="<?php echo $client['contract_count'] ?? 0; ?>">
                                                <span class="badge bg-success">
                                                    <?php echo $client['contract_count']; ?>
                                                </span>
                                            </td>
                                            <td data-label="Tickets Restants" data-order="<?php echo $client['total_tickets_remaining'] ?? 0; ?>">
                                                <?php if (($client['total_tickets_remaining'] ?? 0) > 0): ?>
                                                    <span class="badge bg-<?php echo ($client['total_tickets_remaining'] ?? 0) > 10 ? 'success' : 'warning'; ?>">
                                                        <?php echo $client['total_tickets_remaining']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        Sans tickets
                                                    </span>
                                                <?php endif; ?>
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



<!-- DataTable Persistence -->
<script src="<?php echo BASE_URL; ?>assets/js/datatable-persistence.js"></script>

<!-- Page JS -->
<script src="<?php echo BASE_URL; ?>assets/js/clients-datatable.js"></script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 