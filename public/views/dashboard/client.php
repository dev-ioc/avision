<?php
/**
 * Vue du tableau de bord client
 * Affiche les localisations autorisées du client connecté
 */

// Inclure les fonctions utilitaires
require_once __DIR__ . '/../../includes/functions.php';

setPageVariables(
    'Tableau de bord',
    'dashboard'
);

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Vérifier que l'utilisateur est client (sécurité)
if (!isClient()) {
    $_SESSION['error'] = 'Accès non autorisé. Cette page est réservée aux clients.';
    header('Location: ' . BASE_URL . 'dashboard');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

// Définir la page courante pour le menu
$currentPage = 'dashboard';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Tableau de bord</h1>
                <div class="d-flex align-items-center gap-3">
                    <?php if (hasPermission('client_add_intervention')): ?>
                        <a href="<?php echo BASE_URL; ?>interventions_client/add" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i> Créer une intervention
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section des contrats ticket -->
            <?php if (!empty($ticketContracts)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-ticket-perforated me-2"></i>Contrats Ticket
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($ticketContracts as $contract): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card border-primary">
                                                <div class="card-body">
                                                    <h6 class="card-title text-primary">
                                                        <?php echo h($contract['name']); ?>
                                                    </h6>
                                                    <p class="card-text small text-muted mb-2">
                                                        <?php echo htmlspecialchars($contract['contract_type_name'] ?? 'Type non défini'); ?>
                                                    </p>
                                                    
                                                    <div class="row text-center">
                                                        <div class="col-6">
                                                            <div class="border-end">
                                                                <div class="h4 text-success mb-0">
                                                                    <?php echo $contract['tickets_remaining']; ?>
                                                                </div>
                                                                <small class="text-muted">Tickets restants</small>
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="h6 text-info mb-0">
                                                                <?php echo date('d/m/Y', strtotime($contract['end_date'])); ?>
                                                            </div>
                                                            <small class="text-muted">Fin de contrat</small>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if (!empty($contract['last_purchase_date'])): ?>
                                                        <div class="mt-2 pt-2 border-top">
                                                            <small class="text-muted">
                                                                <i class="bi bi-calendar-event me-1"></i>
                                                                Dernier achat : <?php echo date('d/m/Y', strtotime($contract['last_purchase_date'])); ?>
                                                            </small>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="mt-2 pt-2 border-top">
                                                            <small class="text-muted">
                                                                <i class="bi bi-info-circle me-1"></i>
                                                                Aucun achat récent trouvé
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Section des interventions ouvertes -->
            <?php if (hasPermission('client_view_interventions')): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-tools me-2"></i>Interventions en cours
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($openInterventions)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Référence</th>
                                                    <th>Titre</th>
                                                    <th>Site/Salle</th>
                                                    <th>Statut</th>
                                                    <th>Priorité</th>
                                                    <th>Technicien</th>
                                                    <th>Date création</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($openInterventions as $intervention): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="<?php echo BASE_URL; ?>interventions_client/view/<?php echo $intervention['id']; ?>" 
                                                               class="badge bg-light text-dark text-decoration-none intervention-link">
                                                                <?php echo safeHtml($intervention['reference'], 'N/A'); ?>
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <a href="<?php echo BASE_URL; ?>interventions_client/view/<?php echo $intervention['id']; ?>" 
                                                               class="text-decoration-none intervention-title-link">
                                                                <strong><?php echo safeHtml($intervention['title'], 'Titre non défini'); ?></strong>
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <small>
                                                                <?php echo safeHtml($intervention['site_name'], 'Site non défini'); ?>
                                                                <?php if (!empty($intervention['room_name'])): ?>
                                                                    <br><span class="text-muted"><?php echo safeHtml($intervention['room_name']); ?></span>
                                                                <?php endif; ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <span class="badge" style="background-color: <?php echo $intervention['status_color'] ?? '#6c757d'; ?>">
                                                                <?php echo safeHtml($intervention['status_name'], 'Statut inconnu'); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($intervention['priority_name'])): ?>
                                                                <span class="badge" style="background-color: <?php echo $intervention['priority_color'] ?? '#6c757d'; ?>">
                                                                    <?php echo safeHtml($intervention['priority_name']); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($intervention['technician_name'])): ?>
                                                                <small><?php echo safeHtml($intervention['technician_name']); ?></small>
                                                            <?php else: ?>
                                                                <span class="text-muted">Non assigné</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <small><?php echo !empty($intervention['created_at']) ? date('d/m/Y H:i', strtotime($intervention['created_at'])) : 'Date inconnue'; ?></small>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bi bi-check-circle fs-1 mb-3"></i>
                                            <p>Aucune intervention en cours</p>
                                            <small>Toutes vos interventions sont terminées ou fermées</small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Section des localisations autorisées -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-building me-2"></i>Localisations autorisées
                            </h5>
                        </div>
                        <div class="card-body">

                            <?php if (empty($sitesWithAccess)): ?>
                                <div class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-building fs-1 mb-3"></i>
                                        <p>Aucune localisation disponible</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($sitesWithAccess as $site): ?>
                                        <div class="list-group-item p-0 border-0 mb-1">
                                            <div class="d-flex align-items-center p-2 <?php echo isset($site['authorized']) && $site['authorized'] ? 'bg-success bg-opacity-10 border-start border-success border-4' : 'bg-light border-start border-secondary border-4'; ?>">
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-building me-2 <?php echo isset($site['authorized']) && $site['authorized'] ? 'text-success' : 'text-secondary'; ?>"></i>
                                                        <span class="<?php echo isset($site['authorized']) && $site['authorized'] ? 'text-success' : 'text-secondary'; ?>">
                                                            <?php echo h($site['name']); ?>
                                                        </span>
                                                        <?php if (isset($site['authorized']) && $site['authorized']): ?>
                                                            <span class="badge bg-success ms-2">Autorisé</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary ms-2">Non autorisé</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($site['rooms'])): ?>
                                                <div class="ms-4">
                                                    <?php foreach ($site['rooms'] as $room): ?>
                                                        <div class="d-flex align-items-center py-1 <?php echo isset($room['authorized']) && $room['authorized'] ? 'text-success' : 'text-muted'; ?>">
                                                            <i class="bi bi-door-open me-2"></i>
                                                            <span class="small"><?php echo h($room['name']); ?></span>
                                                            <?php if (isset($room['authorized']) && $room['authorized']): ?>
                                                                <span class="badge bg-success ms-auto">Accès</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Styles pour les liens cliquables dans le tableau des interventions */
.intervention-link {
    transition: all 0.2s ease-in-out;
}

.intervention-link:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.intervention-link:hover .badge {
    background-color: #e9ecef !important;
    color: #495057 !important;
}

.intervention-title-link {
    color: inherit;
    transition: color 0.2s ease-in-out;
}

.intervention-title-link:hover {
    color: #0d6efd;
}
</style>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 