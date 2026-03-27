<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de détail du contrat client
 * Affiche les informations complètes d'un contrat accessible selon les localisations autorisées
 */

// Vérification de l'accès
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

// Récupérer l'ID du contrat depuis l'URL
$contractId = isset($contract['id']) ? $contract['id'] : '';

setPageVariables(
    'Détail du Contrat',
    'contracts_client' . ($contractId ? '_view_' . $contractId : '')
);

// Définir la page courante pour le menu
$currentPage = 'contracts_client';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">Détails du contrat</h4>
        </div>
        <div class="ms-auto p-2 bd-highlight">
            <a href="<?= BASE_URL ?>contracts_client" class="btn btn-secondary me-2">
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
    
    <?php if ($contract): ?>
        <div class="card mb-4">
            <div class="card-header py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-1 me-1"></i>
                        Informations du contrat
                    </h5>
                    <span class="badge bg-<?= $contract['status'] === 'actif' ? 'success' : 'danger'; ?>">
                        <?= ucfirst($contract['status']); ?>
                    </span>
                </div>
            </div>
            <div class="card-body py-2">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Informations générales</h5>
                        <table class="table table-sm">
                            <tr>
                                <th>Client:</th>
                                <td><?= h($contract['client_name'] ?? '') ?></td>
                            </tr>
                            <tr>
                                <th>Salles associées:</th>
                                <td>
                                    <?php if (!empty($contract['rooms'])): ?>
                                        <?php
                                        // Grouper les salles par site
                                        $sites = [];
                                        foreach ($contract['rooms'] as $room) {
                                            $siteName = $room['site_name'] ?? 'Site inconnu';
                                            if (!isset($sites[$siteName])) {
                                                $sites[$siteName] = [];
                                            }
                                            $sites[$siteName][] = $room;
                                        }
                                        ?>
                                        <?php foreach ($sites as $siteName => $siteRooms): ?>
                                            <div class="mb-2">
                                                <strong class="text-primary">
                                                    <i class="bi bi-building me-1 me-1"></i><?= h($siteName) ?>
                                                </strong>
                                                <ul class="list-unstyled ms-3 mb-2">
                                                    <?php foreach ($siteRooms as $room): ?>
                                                        <li>
                                                            <i class="bi bi-door-open text-muted me-1 me-1"></i>
                                                            <?= h($room['name']) ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Aucune salle associée</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Type de contrat:</th>
                                <td><?= h($contract['contract_type_name'] ?? '') ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>Détails du contrat</h5>
                        <table class="table table-sm">
                            <tr>
                                <th>Date de début:</th>
                                <td><?= formatDateFrench($contract['start_date']) ?></td>
                            </tr>
                            <tr>
                                <th>Date de fin:</th>
                                <td><?= formatDateFrench($contract['end_date']) ?></td>
                            </tr>
                            <?php if (isContractTicketById($contract['id'])): ?>
                            <tr>
                                <th>Tickets totaux:</th>
                                <td><?= $contract['tickets_number'] ?></td>
                            </tr>
                            <tr>
                                <th>Tickets restants:</th>
                                <td>
                                    <span class="badge bg-<?= $contract['tickets_remaining'] > 3 ? 'success' : 'danger'; ?>">
                                        <?= $contract['tickets_remaining'] ?>
                                    </span>
                                </td>
                            </tr>
                            <?php else: ?>
                            <tr>
                                <th>Tickets:</th>
                                <td><span class="text-muted">Pas de tickets</span></td>
                            </tr>
                            <?php endif; ?>

                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Pièces jointes -->
        <div class="card mb-4">
            <div class="card-header py-2">
                <h5 class="card-title mb-0">
                    <i class="bi bi-paperclip me-1"></i>
                    Pièces jointes
                </h5>
            </div>
            <div class="card-body py-2">
                <?php if (!empty($attachments)): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($attachments as $att): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-1">
                                            <i class="<?php echo getIcon('attachment', 'bi bi-paperclip'); ?> me-2 text-muted"></i>
                                            <span class="fw-medium"><?= h($att['nom_fichier']) ?></span>
                                        </div>
                                        <?php if (!empty($att['commentaire'])): ?>
                                            <small class="text-muted d-block"><?= h($att['commentaire']) ?></small>
                                        <?php endif; ?>
                                        <small class="text-muted">
                                            <?= number_format(($att['taille_fichier'] ?? 0) / 1024, 1) ?> KB • 
                                            <?= date('d/m/Y H:i', strtotime($att['date_creation'])) ?>
                                        </small>
                                    </div>
                                    <div class="ms-3">
                                        <a href="<?= BASE_URL; ?>contracts_client/download?attachment_id=<?= $att['id'] ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-outline-primary" 
                                           title="Télécharger">
                                            <i class="bi bi-download me-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="<?php echo getIcon('attachment', 'bi bi-paperclip'); ?> fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">Aucune pièce jointe disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header py-2">
                <h5 class="card-title mb-0">
                    <i class="bi bi-tools me-1 me-1"></i>
                    Interventions associées
                </h5>
            </div>
            <div class="card-body py-2">
                <?php if (!empty($interventions)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Référence</th>
                                    <th>Titre</th>
                                    <th>Date</th>
                                    <th>Technicien</th>
                                    <th>Durée</th>
                                    <?php if (isContractTicketById($contract['id'])): ?>
                                    <th>Tickets utilisés</th>
                                    <?php endif; ?>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($interventions as $intervention): ?>
                                    <tr>
                                        <td><?= h($intervention['reference'] ?? '') ?></td>
                                        <td><?= h($intervention['title'] ?? '') ?></td>
                                        <td><?= !empty($intervention['date_planif']) ? date('d/m/Y', strtotime($intervention['date_planif'])) : date('d/m/Y', strtotime($intervention['created_at'])) ?></td>
                                        <td>
                                            <?php if (!empty($intervention['technician_first_name']) || !empty($intervention['technician_last_name'])): ?>
                                                <?= h($intervention['technician_first_name'] ?? '') ?> <?= h($intervention['technician_last_name'] ?? '') ?>
                                            <?php else: ?>
                                                <span class="text-muted">Non attribué</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= h($intervention['duration'] ?? '0') ?>h</td>
                                        <?php if (isContractTicketById($contract['id'])): ?>
                                        <td><?= h($intervention['tickets_used'] ?? '0') ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="badge" style="background-color: <?= h($intervention['status_color'] ?? '') ?>">
                                                <?= h($intervention['status_name'] ?? '') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?= BASE_URL ?>interventions_client/view/<?= $intervention['id']; ?>" 
                                               class="btn btn-sm btn-outline-info" title="Voir">
                                                <i class="bi bi-info-circle me-1"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2 me-1"></i>
                        Aucune intervention associée à ce contrat.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <h6>Contrat non trouvé</h6>
            <p>Le contrat demandé n'existe pas ou vous n'avez pas les droits pour y accéder.</p>
        </div>
    <?php endif; ?>
</div>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 