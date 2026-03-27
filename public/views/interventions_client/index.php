<?php
// Vue interventions client - version sans accent, encodage UTF-8
// Affiche la liste des interventions du client

// Activer l'affichage des erreurs pour debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclure les fichiers de base
require_once __DIR__ . '/../../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables('Interventions', 'interventions_client');

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Debug simple
// echo '<pre>'; var_dump($interventions); echo '</pre>';
?>
<div class="container-fluid flex-grow-1 container-p-y">

<div class="d-flex bd-highlight mb-3">
    <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Mes interventions</h4></div>
    
    <div class="ms-auto p-2 bd-highlight">
        <?php if (hasPermission('client_add_intervention')): ?>
            <a href="<?php echo BASE_URL; ?>interventions_client/add" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Créer une intervention
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filtres rapides par statut -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card status-filter-card">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <!-- Tous les statuts -->
                    <a href="<?php echo BASE_URL; ?>interventions_client" class="btn btn-outline-secondary btn-sm status-filter-btn <?php echo (!isset($_GET['status_id'])) ? 'active' : ''; ?>">
                        <span class="badge bg-secondary me-1"><?php echo isset($statsByStatus) ? array_sum(array_column($statsByStatus, 'count')) : 0; ?></span>
                        Tous
                    </a>
                    <?php if (isset($statsByStatus)): ?>
                        <?php foreach ($statsByStatus as $statusStat): ?>
                            <a href="<?php echo BASE_URL; ?>interventions_client?status_id=<?php echo $statusStat['id']; ?>"
                               class="btn btn-outline-secondary btn-sm status-filter-btn <?php echo (isset($_GET['status_id']) && $_GET['status_id'] == $statusStat['id']) ? 'active' : ''; ?>">
                                <span class="badge me-1" style="background-color: <?php echo $statusStat['color']; ?>">
                                    <?php echo $statusStat['count']; ?>
                                </span>
                                <?php echo h($statusStat['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table id="interventionsTable" class="table table-striped table-hover dt-responsive">
        <thead>
            <tr>
                <th>Reference</th>
                <th>Titre</th>
                <th>Client</th>
                <th>Site</th>
                <th>Salle</th>
                <th>Statut</th>
                <th>Priorite</th>
                <th>Date planifiee</th>
                <th>Technicien</th>
                <th>Date creation</th>
            </tr>
        </thead>
        <tbody>
            <?php if (isset($interventions) && !empty($interventions)): ?>
                <?php foreach ($interventions as $intervention): ?>
                    <tr>
                        <td data-label="Reference">
                            <a href="<?php echo BASE_URL; ?>interventions_client/view/<?php echo $intervention['id']; ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($intervention['reference'] ?? ''); ?>
                            </a>
                        </td>
                        <td data-label="Titre"><?php echo htmlspecialchars($intervention['title'] ?? ''); ?></td>
                        <td data-label="Client"><?php echo htmlspecialchars($intervention['client_name'] ?? ''); ?></td>
                        <td data-label="Site"><?php echo htmlspecialchars($intervention['site_name'] ?? '-'); ?></td>
                        <td data-label="Salle"><?php echo htmlspecialchars($intervention['room_name'] ?? '-'); ?></td>
                        <td data-label="Statut" data-order="<?php echo $intervention['status_id'] ?? 0; ?>">
                            <span class="badge rounded-pill" style="background-color: <?php echo $intervention['status_color'] ?? ''; ?>">
                                <?php echo htmlspecialchars($intervention['status_name'] ?? ''); ?>
                            </span>
                        </td>
                        <td data-label="Priorite" data-order="<?php echo $intervention['priority_id'] ?? 0; ?>">
                            <span class="badge rounded-pill" style="background-color: <?php echo $intervention['priority_color'] ?? ''; ?>">
                                <?php echo htmlspecialchars($intervention['priority_name'] ?? ''); ?>
                            </span>
                        </td>
                        <td data-label="Date planifiee" data-order="<?php echo isset($intervention['date_planif']) ? strtotime($intervention['date_planif']) : 0; ?>">
                            <?php echo !empty($intervention['date_planif']) ? date('d/m/Y', strtotime($intervention['date_planif'])) : '-'; ?>
                        </td>
                        <td data-label="Technicien"><?php echo htmlspecialchars($intervention['technician_first_name'] ?? '') . ' ' . htmlspecialchars($intervention['technician_last_name'] ?? ''); ?></td>
                        <td data-label="Date creation" data-order="<?php echo isset($intervention['created_at']) ? strtotime($intervention['created_at']) : 0; ?>">
                            <?php echo date('d/m/Y H:i', strtotime($intervention['created_at'] ?? '')); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Laisser tbody vide. DataTables utilisera language.emptyTable -->
            <?php endif; ?>
        </tbody>
    </table>
</div>

</div>

<!-- DataTable Persistence -->
<script src="<?php echo BASE_URL; ?>assets/js/datatable-persistence.js"></script>

<!-- Page JS -->
<script src="<?php echo BASE_URL; ?>assets/js/interventions-datatable.js"></script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?> 