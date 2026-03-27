<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/FileUploadValidator.php';
/**
 * Vue de détail du contrat
 * Affiche les informations complètes d'un contrat
 */

// Vérification de l'accès - seuls les utilisateurs connectés peuvent voir les contrats
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;
$isAdmin = isAdmin();

// Récupérer l'ID du contrat depuis l'URL
$contractId = isset($contract['id']) ? $contract['id'] : '';

setPageVariables(
    'Contrat',
    'contracts' . ($contractId ? '_view_' . $contractId : '')
);

// Définir la page courante pour le menu
$currentPage = 'contracts';

// Définir les breadcrumbs personnalisés pour la vue contrat
if (isset($contract) && !empty($contract)) {
    $GLOBALS['customBreadcrumbs'] = generateContractViewBreadcrumbs($contract);
}

// Déterminer l'URL de retour dynamiquement
$returnTo = $_GET['return_to'] ?? null;
$clientId = $_GET['client_id'] ?? null;
$activeTab = $_GET['active_tab'] ?? null;

if ($returnTo === 'client' && $clientId) {
    // Si on vient de la vue du client, retourner à cette vue avec l'onglet actif
    $returnUrl = BASE_URL . 'clients/view/' . $clientId;
    if ($activeTab) {
        $returnUrl .= '?active_tab=' . $activeTab;
    }
} else {
    // Par défaut, retourner à la liste des contrats
    $returnUrl = BASE_URL . 'contracts';
}

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">

<div class="d-flex bd-highlight mb-3">
    <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Détails du contrat</h4></div>

    <div class="ms-auto p-2 bd-highlight">
        <a href="<?php echo $returnUrl; ?>" class="btn btn-secondary me-2">
            <i class="bi bi-arrow-left me-1"></i> Retour
        </a>

        <?php if (canManageContracts()): ?>
            <a href="<?php echo BASE_URL; ?>contracts/edit/<?php echo $contract['id']; ?>?return_to=<?php echo $returnTo; ?><?php echo $clientId ? '&client_id=' . $clientId : ''; ?><?php echo $activeTab ? '&active_tab=' . $activeTab : ''; ?>" class="btn btn-warning me-2">
                <i class="bi bi-pencil me-1 me-1"></i>Modifier
            </a>
            
            <?php 
            // Vérifier si le contrat peut être renouvelé (30 jours avant la fin OU après la fin)
            $endDate = new DateTime($contract['end_date']);
            $today = new DateTime();
            $daysUntilEnd = $today->diff($endDate)->days;
            $isExpired = $today > $endDate;
            $canRenew = ($daysUntilEnd <= 30 && $daysUntilEnd >= 0) || $isExpired;
            
            // Afficher le bouton de renouvellement sans contrainte de renouvellement tacite
            if ($canRenew): 
            ?>
                <button type="button" class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#renewalModal">
                    <i class="bi bi-arrow-repeat me-1"></i>Renouveler le contrat
                    <?php if ($isExpired): ?>
                        <span class="badge bg-warning text-dark ms-1">Expiré</span>
                    <?php endif; ?>
                </button>
            <?php else: ?>
                <span class="badge bg-secondary me-2" title="Renouvellement disponible dans <?= $daysUntilEnd - 30 ?> jours">
                    <i class="bi bi-clock me-1"></i>Renouvellement indisponible
                </span>
            <?php 
            endif; 
            ?>
        <?php endif; ?>
        
        <?php if ($isAdmin && !empty($contract['contract_type_id'])): ?>
            <!-- DEBUG: isAdmin = <?php echo var_export($isAdmin, true); ?> -->
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDeleteContract(<?php echo $contract['id']; ?>, '<?php echo htmlspecialchars($contract['name'] ?? ''); ?>')" title="Supprimer le contrat">
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
    
    <div class="card mb-4">
        <div class="card-header py-2">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-info-circle me-1 me-1"></i>
                    Informations du contrat
                </h5>
                <span class="badge bg-<?php echo $contract['status'] === 'actif' ? 'success' : ($contract['status'] === 'inactif' ? 'danger' : 'warning'); ?>">
                    <?php echo ucfirst($contract['status']); ?>
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
                            <td><?= htmlspecialchars($contract['client_name'] ?? '') ?></td>
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
                                                        <?= h($room['room_name']) ?>
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
                            <td><?= htmlspecialchars($contract['contract_type_name'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th>Niveau d'accès:</th>
                            <td>
                                <?php if (!empty($contract['access_level_name'])): ?>
                                    <span class="badge bg-primary">
                                        <i class="fas fa-level-up-alt me-1"></i>
                                        <?= h($contract['access_level_name']) ?>
                                    </span>
                                    <br><small class="text-muted"><?= h($contract['access_level_description']) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">Non défini</span>
                                <?php endif; ?>
                            </td>
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
                            <th>Tickets initiaux:</th>
                            <td>
                                <?= $contract['tickets_number'] ?>
                                <?php if ($isAdmin): ?>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-primary ms-2" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#addTicketsModal"
                                            title="Ajouter des tickets">
                                        <i class="bi bi-plus-circle me-1 me-1"></i> Ajouter des tickets
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th>Tickets restants:</th>
                            <td>
                                <?php if (isContractTicketById($contract['id'])): ?>
                                    <?= $contract['tickets_remaining'] ?>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-info ms-2" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#ticketsHistoryModal"
                                            title="Voir l'historique des tickets">
                                        <i class="bi bi-clock-history me-1 me-1"></i> Historique
                                    </button>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Sans tickets</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (canManageContracts() && !isContractTicketById($contract['id'])): ?>
                        <tr>
                            <th>Interventions préventives:</th>
                            <td>
                                <a href="<?php echo BASE_URL; ?>contracts/generatePreventiveInterventions/<?php echo $contract['id']; ?>" 
                                   class="btn btn-sm btn-outline-warning"
                                   title="Générer des interventions préventives">
                                    <i class="bi bi-calendar-check me-1"></i> Générer des interventions préventives
                                </a>
                                <br><small class="text-muted">Disponible uniquement pour les contrats sans tickets</small>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th>Rappel de fin:</th>
                            <td>
                                <?php if ($contract['reminder_enabled']): ?>
                                    <span class="badge bg-info">Activé (<?= $contract['reminder_days'] ?> jours)</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Désactivé</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Renouvellement tacite:</th>
                            <td>
                                <?php if ($contract['renouvellement_tacite']): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-arrow-repeat me-1"></i>Activé
                                    </span>
                                    <br><small class="text-muted">Le contrat se renouvelle automatiquement</small>
                                <?php else: ?>
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-x-circle me-1"></i>Désactivé
                                    </span>
                                    <br><small class="text-muted">Renouvellement manuel requis</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Numéro de facture:</th>
                            <td>
                                <?php if (!empty($contract['num_facture'])): ?>
                                    <span class="badge bg-primary">
                                        <i class="bi bi-receipt me-1"></i><?= h($contract['num_facture']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Non défini</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Tarif:</th>
                            <td>
                                <?php if (!empty($contract['tarif'])): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-currency-euro me-1"></i><?= number_format($contract['tarif'], 2, ',', ' ') ?> €
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Non défini</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Indice de révision:</th>
                            <td>
                                <?php if (!empty($contract['indice'])): ?>
                                    <span class="badge bg-info">
                                        <i class="bi bi-graph-up me-1"></i><?= h($contract['indice']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Non défini</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <?php if (!empty($contract['comment'])): ?>
            <div class="row mt-3">
                <div class="col-md-12">
                    <h5>Commentaires</h5>
                    <div class="alert alert-info">
                        <?= nl2br(h($contract['comment'])) ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>



    <!-- Section Pièces jointes -->
    <div class="card mb-4">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                                                <i class="<?php echo getIcon('attachment', 'bi bi-paperclip'); ?> me-1"></i>
                Pièces jointes
            </h5>
            <?php if (canManageContracts()): ?>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAttachmentModal">
                    <i class="bi bi-plus me-1"></i> Ajouter une pièce jointe
                </button>
            <?php endif; ?>
        </div>
        <div class="card-body py-2">
            <?php if (empty($attachments)): ?>
                <p class="text-muted mb-0">Aucune pièce jointe pour le moment.</p>
            <?php else: ?>
                <?php 
                // Trier les pièces jointes par date de création (plus récent en premier)
                usort($attachments, function($a, $b) {
                    return strtotime($b['date_creation']) - strtotime($a['date_creation']);
                });
                
                foreach ($attachments as $attachment): 
                    $isPdf = strtolower(pathinfo($attachment['nom_fichier'], PATHINFO_EXTENSION)) === 'pdf';
                ?>
                    <div class="card mb-2 <?php echo isset($attachment['masque_client']) && $attachment['masque_client'] == 1 ? 'bg-light-warning' : ''; ?>">
                        <div class="card-header py-1 d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo h($attachment['created_by_name'] ?? 'Utilisateur inconnu'); ?></strong>
                                <small class="text-muted ms-2">
                                    <?php echo date('d/m/Y H:i', strtotime($attachment['date_creation'])); ?>
                                </small>
                            </div>
                            <div>
                                <button type="button" 
                                        class="btn btn-sm btn-outline-info btn-action" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#previewModal<?= $attachment['id'] ?>"
                                        title="Aperçu">
                                        <i class="<?php echo getIcon('preview', 'bi bi-eye'); ?>"></i>
                                </button>
                                <a href="<?php echo BASE_URL; ?>contracts/download?attachment_id=<?php echo $attachment['id']; ?>" 
                                   class="btn btn-sm btn-outline-success btn-action" 
                                   title="Télécharger">
                                    <i class="<?php echo getIcon('download', 'bi bi-download'); ?>"></i>
                                </a>
                                <?php if (canManageContracts()): ?>
                                    <a href="<?php echo BASE_URL; ?>contracts/toggleAttachmentVisibility/<?php echo $contract['id']; ?>?attachment_id=<?php echo $attachment['id']; ?>" 
                                       class="btn btn-sm btn-outline-warning btn-action" 
                                       title="<?php echo isset($attachment['masque_client']) && $attachment['masque_client'] == 1 ? 'Rendre visible aux clients' : 'Masquer aux clients'; ?>"
                                       onclick="return confirm('<?php echo isset($attachment['masque_client']) && $attachment['masque_client'] == 1 ? 'Rendre cette pièce jointe visible aux clients ?' : 'Masquer cette pièce jointe aux clients ?'; ?>');">
                                        <i class="<?php echo isset($attachment['masque_client']) && $attachment['masque_client'] == 1 ? 'bi bi-eye' : 'bi bi-eye-slash'; ?>"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($isAdmin): ?>
                                    <a href="<?php echo BASE_URL; ?>contracts/deleteAttachment/<?php echo $contract['id']; ?>?attachment_id=<?php echo $attachment['id']; ?>" 
                                       class="btn btn-sm btn-outline-danger btn-action" 
                                       title="Supprimer"
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette pièce jointe ?');">
                                        <i class="<?php echo getIcon('delete', 'bi bi-trash'); ?>"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body py-2">
                            <div class="d-flex align-items-center">
                                <?php if (isset($attachment['masque_client']) && $attachment['masque_client'] == 1): ?>
                                    <i class="bi bi-eye-slash text-warning me-2" title="Masqué aux clients"></i>
                                <?php endif; ?>
                                <?php echo h($attachment['nom_fichier']); ?>
                                <?php if ($attachment['commentaire']): ?>
                                    <small class="text-muted ms-2">(<?php echo h($attachment['commentaire']); ?>)</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Modal d'aperçu -->
                    <div class="modal fade" id="previewModal<?= $attachment['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><?= h($attachment['nom_fichier']) ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="preview-container">
                                        <?php 
                                        $extension = strtolower(pathinfo($attachment['nom_fichier'], PATHINFO_EXTENSION));
                                        if ($extension === 'pdf'): 
                                        ?>
                                            <iframe src="<?= BASE_URL ?>contracts/preview?attachment_id=<?= $attachment['id'] ?>" 
                                                    width="100%" 
                                                    height="600px" 
                                                    frameborder="0">
                                            </iframe>
                                        <?php elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                            <img src="<?= BASE_URL ?>contracts/preview?attachment_id=<?= $attachment['id'] ?>" 
                                                 class="img-fluid" 
                                                 alt="<?= h($attachment['nom_fichier']) ?>"
                                                 data-attachment-id="<?= $attachment['id'] ?>">
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                <i class="bi bi-info-circle me-1"></i> 
                                                Ce type de fichier ne peut pas être prévisualisé. 
                                                <a href="<?= BASE_URL; ?>contracts/download?attachment_id=<?= $attachment['id'] ?>" 
                                                   class="alert-link" 
                                                   target="_blank">
                                                    Télécharger le fichier
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <a href="<?= BASE_URL; ?>contracts/download?attachment_id=<?= $attachment['id'] ?>" 
                                       class="btn btn-primary" 
                                       target="_blank">
                                        <i class="bi bi-download me-1"></i> Télécharger
                                    </a>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
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
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th>Titre</th>
                            <th>Date</th>
                            <th>Technicien</th>
                            <th>Durée</th>
                            <th>Tickets utilisés</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($interventions)): ?>
                            <?php foreach ($interventions as $intervention): ?>
                            <tr>
                                <td>
                                    <a href="<?= BASE_URL ?>interventions/view/<?= $intervention['id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($intervention['reference'] ?? '') ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($intervention['title'] ?? '') ?></td>
                                <td><?= !empty($intervention['date_planif']) ? date('d/m/Y', strtotime($intervention['date_planif'])) . (!empty($intervention['heure_planif']) ? ' ' . $intervention['heure_planif'] : '') : date('d/m/Y H:i', strtotime($intervention['created_at'])) ?></td>
                                <td><?= htmlspecialchars($intervention['technician_name'] ?? '') ?></td>
                                <td><?= $intervention['duration'] ?? 0 ?>h</td>
                                <td>
                                    <?php if (isContractTicketById($contract['id'])): ?>
                                        <?= $intervention['tickets_used'] ?? 0 ?>
                                    <?php else: ?>
                                        <span class="no-tickets-indicator" title="Sans tickets">
                                            <i class="no-tickets-icon"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge" style="background-color: <?= $intervention['status_color'] ?? '#6c757d' ?>">
                                        <?= htmlspecialchars($intervention['status_name'] ?? 'Non défini') ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">Aucune intervention associée</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ajout de pièces jointes avec Drag & Drop -->
<div class="modal fade" id="addAttachmentModal" tabindex="-1" aria-labelledby="addAttachmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="<?php echo BASE_URL; ?>contracts/addMultipleAttachments/<?php echo $contract['id']; ?>" method="post" enctype="multipart/form-data" id="dragDropForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAttachmentModalLabel">
                        <i class="bi bi-cloud-upload me-2 me-1"></i>
                        Ajouter des pièces jointes
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Zone de Drag & Drop -->
                    <div class="drop-zone" id="dropZone">
                        <div class="drop-message">
                            <i class="bi bi-cloud-upload me-1"></i>
                            Glissez-déposez vos fichiers ici<br>
                            <small class="text-muted">ou cliquez pour sélectionner</small>
                        </div>
                        
                        <input type="file" id="fileInput" multiple style="display: none;" 
                               accept="<?= FileUploadValidator::getAcceptAttribute($GLOBALS['db']) ?>">
                        
                        <div class="file-list" id="fileList"></div>
                        
                        <div class="stats" id="stats" style="display: none;">
                            <div class="row">
                                <div class="col-6">
                                    <strong>Fichiers valides:</strong> <span id="validCount">0</span>
                                </div>
                                <div class="col-6">
                                    <strong>Fichiers rejetés:</strong> <span id="invalidCount">0</span>
                                </div>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" id="progressFill"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Liste des fichiers avec options individuelles -->
                    <div id="filesOptions" style="display: none;">
                        <h6 class="mt-3 mb-2">Options par fichier :</h6>
                        <div id="filesOptionsList"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-warning" id="clearAllBtn" style="display: none;">
                        <i class="bi bi-trash me-1 me-1"></i> Tout effacer
                    </button>
                    <button type="submit" class="btn btn-primary" id="uploadValidBtn" style="display: none;">
                        <i class="bi bi-upload me-1 me-1"></i> Uploader les fichiers valides
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.drop-zone {
    border: 2px dashed var(--bs-border-color);
    border-radius: 8px;
    padding: 30px;
    text-align: center;
    background-color: var(--bs-body-bg);
    transition: all 0.3s ease;
    min-height: 150px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.drop-zone.dragover {
    border-color: var(--bs-primary);
    background-color: var(--bs-primary-bg-subtle);
}

.drop-zone.dragover .drop-message {
    color: var(--bs-primary);
}

.drop-message {
    font-size: 1.1em;
    color: var(--bs-secondary-color);
    margin-bottom: 15px;
}

.drop-message i {
    font-size: 2.5em;
    margin-bottom: 10px;
    display: block;
}

.file-list {
    margin-top: 15px;
    max-height: 200px;
    overflow-y: auto;
}

.file-item {
    display: flex;
    align-items: center;
    padding: 8px;
    margin: 3px 0;
    border-radius: 5px;
    border: 1px solid var(--bs-border-color);
    background-color: var(--bs-body-bg);
}

.file-item.valid {
    background-color: var(--bs-success-bg-subtle);
    border-color: var(--bs-success-border-subtle);
}

.file-item.invalid {
    background-color: var(--bs-danger-bg-subtle);
    border-color: var(--bs-danger-border-subtle);
}

.file-name {
    flex: 1;
    font-weight: 500;
    font-size: 0.9em;
    color: var(--bs-body-color);
}

.file-size {
    color: var(--bs-secondary-color);
    font-size: 0.8em;
    margin: 0 8px;
}

.error-message {
    color: var(--bs-danger);
    font-size: 0.8em;
    margin-left: 8px;
}

.remove-file {
    background: none;
    border: none;
    color: var(--bs-danger);
    font-size: 1.1em;
    cursor: pointer;
    padding: 0 4px;
}

.remove-file:hover {
    color: var(--bs-danger-hover);
}

.stats {
    margin-top: 10px;
    padding: 8px;
    background-color: var(--bs-secondary-bg);
    border-radius: 5px;
    font-size: 0.9em;
    color: var(--bs-body-color);
}

.progress-bar {
    height: 3px;
    background-color: var(--bs-secondary-bg);
    border-radius: 2px;
    overflow: hidden;
    margin-top: 8px;
}

.progress-fill {
    height: 100%;
    background-color: var(--bs-primary);
    width: 0%;
    transition: width 0.3s ease;
}

.file-options {
    margin-top: 8px;
    padding: 8px 12px;
    background-color: var(--bs-secondary-bg);
    border-radius: 5px;
    border: 1px solid var(--bs-border-color);
}

.file-options .form-control {
    font-size: 0.85em;
    background-color: var(--bs-body-bg);
    border-color: var(--bs-border-color);
    color: var(--bs-body-color);
    height: 32px;
}

.file-options .form-control:focus {
    background-color: var(--bs-body-bg);
    border-color: var(--bs-primary);
    color: var(--bs-body-color);
}

.file-options .form-check {
    margin: 0;
}

.file-options strong {
    font-size: 0.9em;
    color: var(--bs-body-color);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Dark mode specific adjustments */
[data-bs-theme="dark"] .drop-zone {
    border-color: var(--bs-border-color);
    background-color: var(--bs-body-bg);
}

[data-bs-theme="dark"] .file-item {
    background-color: var(--bs-body-bg);
    border-color: var(--bs-border-color);
}

[data-bs-theme="dark"] .file-item.valid {
    background-color: rgba(25, 135, 84, 0.1);
    border-color: rgba(25, 135, 84, 0.3);
}

[data-bs-theme="dark"] .file-item.invalid {
    background-color: rgba(220, 53, 69, 0.1);
    border-color: rgba(220, 53, 69, 0.3);
}

[data-bs-theme="dark"] .stats {
    background-color: var(--bs-secondary-bg);
}

[data-bs-theme="dark"] .file-options {
    background-color: var(--bs-secondary-bg);
}
</style>

<!-- JavaScript extrait vers public/assets/js/pages/contracts.js -->
<!-- La classe DragDropUploader est maintenant dans public/assets/js/components/DragDropUploader.js -->
<!-- Le code d'initialisation est dans public/assets/js/pages/contracts.js -->
<!-- Code JavaScript supprimé - Utilise maintenant DragDropUploader.js et contracts.js -->

<!-- JavaScript spécifique à la page contracts -->
<script src="<?php echo BASE_URL; ?>assets/js/pages/contracts.js" onerror="console.error('ERREUR: contracts.js n\'a pas pu être chargé. Vérifiez que le fichier existe et est accessible.');"></script>

    <!-- Modal Ajout de tickets -->
    <div class="modal fade" id="addTicketsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="<?php echo BASE_URL; ?>contracts/addTickets/<?php echo $contract['id']; ?>" method="post" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-plus-circle me-2 me-1"></i> Ajouter des tickets
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="tickets_to_add" class="form-label">Nombre de tickets à ajouter *</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="tickets_to_add" 
                                   name="tickets_to_add" 
                                   min="1" 
                                   required 
                                   placeholder="Ex: 10">
                            <div class="form-text">Tickets actuels: <?= $contract['tickets_number'] ?>, Restants: <?= $contract['tickets_remaining'] ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_tickets_date" class="form-label">Date d'ajout</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="add_tickets_date" 
                                   name="add_tickets_date" 
                                   value="<?= date('Y-m-d') ?>" 
                                   required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_num_facture" class="form-label">
                                <i class="bi bi-receipt me-1"></i>Nouveau numéro de facture
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="new_num_facture" 
                                   name="new_num_facture" 
                                   placeholder="Ex: FACT-2025-002">
                            <div class="form-text">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Remplace le numéro de facture actuel. Laissez vide pour conserver l'actuel.
                                </small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="avenant_file" class="form-label">
                                <i class="bi bi-file-earmark-text me-1"></i>Avenant contractuel
                            </label>
                            <input type="file" 
                                   class="form-control" 
                                   id="avenant_file" 
                                   name="avenant_file" 
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <div class="form-text">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Document justifiant l'ajout de tickets (PDF, Word, images). Optionnel.
                                </small>
                            </div>
                        </div>
                        
                        <?php 
                        // Vérifier si le contrat se termine dans l'année courante
                        $currentYear = date('Y');
                        $contractEndYear = date('Y', strtotime($contract['end_date']));
                        $shouldShowExtension = ($contractEndYear == $currentYear);
                        ?>
                        
                        <?php if ($shouldShowExtension): ?>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="extend_contract" 
                                       name="extend_contract" 
                                       value="1">
                                <label class="form-check-label" for="extend_contract">
                                    <i class="bi bi-calendar-plus me-1"></i>Prolonger le contrat jusqu'au 31 décembre de l'année suivante
                                </label>
                            </div>
                            <div class="form-text">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Si le contrat se termine dans l'année courante, il sera prolongé jusqu'au 31 décembre de l'année suivante.
                                </small>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="add_tickets_comment" class="form-label">Commentaire</label>
                            <textarea class="form-control" 
                                      id="add_tickets_comment" 
                                      name="add_tickets_comment" 
                                      rows="3" 
                                      placeholder="Raison de l'ajout de tickets..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1 me-1"></i> Ajouter les tickets
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Historique des tickets -->
    <div class="modal fade" id="ticketsHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-ticket-detailed me-2 me-1"></i> Historique des tickets
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php 
                    // Récupérer l'historique des tickets (field_name = 'Tickets restants')
                    $ticketsHistory = [];
                    if (!empty($history)) {
                        foreach ($history as $entry) {
                            if (isset($entry['field_name']) && $entry['field_name'] === 'Tickets restants') {
                                $ticketsHistory[] = $entry;
                            }
                        }
                    }
                    ?>
                    
                    <?php if (empty($ticketsHistory)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-info-circle text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3">Aucun historique de tickets disponible pour le moment.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Ancienne valeur</th>
                                        <th>Nouvelle valeur</th>
                                        <th>Commentaire</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ticketsHistory as $entry): ?>
                                        <tr>
                                            <td>
                                                <i class="bi bi-calendar3 text-primary me-1"></i>
                                                <?php echo date('d/m/Y H:i', strtotime($entry['created_at'])); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo htmlspecialchars($entry['old_value'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    $oldValue = intval($entry['old_value'] ?? 0);
                                                    $newValue = intval($entry['new_value'] ?? 0);
                                                    echo ($newValue > $oldValue) ? 'success' : 'danger';
                                                ?>">
                                                    <?php echo htmlspecialchars($entry['new_value'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($entry['description'] ?? 'Aucun commentaire'); ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Total : <?php echo count($ticketsHistory); ?> modification(s) de tickets
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Section Historique (Bouton flottant) -->
    <button type="button" 
            class="btn btn-sm btn-outline-secondary position-fixed bottom-0 end-0 m-3" 
            data-bs-toggle="modal" 
            data-bs-target="#historyModal"
            title="Historique des modifications">
        <i class="bi bi-clock-history me-1"></i>
    </button>

    <!-- Modal Historique -->
    <div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-clock-history me-2 me-1"></i> Historique des modifications
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($history)): ?>
                        <p class="text-muted">Aucun historique disponible.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($history as $entry): ?>
                                <div class="list-group-item px-0">
                                    <small class="text-muted d-block ps-3">
                                        <?php echo date('d/m/Y H:i', strtotime($entry['created_at'])); ?>
                                        par <?php echo isset($entry['changed_by_name']) && $entry['changed_by_name'] !== null ? h($entry['changed_by_name']) : 'Utilisateur inconnu'; ?>
                                    </small>
                                    <div class="mt-1 ps-3">
                                        <?php echo isset($entry['description']) && $entry['description'] !== null ? h($entry['description']) : 'Aucune description disponible.'; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Renouvellement de contrat -->
    <div class="modal fade" id="renewalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="<?php echo BASE_URL; ?>contracts/renew/<?php echo $contract['id']; ?>" method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-arrow-repeat me-2"></i> Renouveler le contrat
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Action :</strong> Désactiver le contrat actuel et créer un nouveau contrat
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Contrat actuel</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Nom :</strong> <?= h($contract['name']) ?></li>
                                    <li><strong>Date de fin :</strong> <?= formatDateFrench($contract['end_date']) ?></li>
                                    <li><strong>Statut :</strong> <span class="badge bg-success">Actif</span></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Nouveau contrat</h6>
                                <ul class="list-unstyled">
                                    <?php if ($isExpired): ?>
                                        <li><strong>Date de début :</strong> <?= formatDateFrench(date('Y-m-d')) ?> <span class="badge bg-info">Aujourd'hui</span></li>
                                        <li><strong>Date de fin :</strong> <?= formatDateFrench(date('Y-m-d', strtotime('+364 days'))) ?></li>
                                    <?php else: ?>
                                        <li><strong>Date de début :</strong> <?= formatDateFrench(date('Y-m-d', strtotime($contract['end_date'] . ' +1 day'))) ?></li>
                                        <li><strong>Date de fin :</strong> <?= formatDateFrench(date('Y-m-d', strtotime($contract['end_date'] . ' +365 days'))) ?></li>
                                    <?php endif; ?>
                                    <li><strong>Durée :</strong> 364 jours</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_contract_name" class="form-label">Nom du nouveau contrat *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="new_contract_name" 
                                   name="new_contract_name" 
                                   value="<?= h($contract['name']) ?>" 
                                   required>
                        </div>
                        
                        <?php if (isContractTicketById($contract['id'])): ?>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="reset_tickets" name="reset_tickets" value="1" checked>
                                <label class="form-check-label" for="reset_tickets">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Réinitialiser les tickets restants
                                </label>
                                <small class="form-text text-muted d-block">
                                    Les tickets restants seront remis à <?= $contract['tickets_number'] ?> (tickets initiaux)
                                </small>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="mb-3">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-1"></i>
                                Ce contrat n'a pas de tickets associés.
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="renewal_comment" class="form-label">Commentaire</label>
                            <textarea class="form-control" 
                                      id="renewal_comment" 
                                      name="renewal_comment" 
                                      rows="3" 
                                      placeholder="Commentaire sur le renouvellement..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-info">
                            <i class="bi bi-arrow-repeat me-1"></i> Renouveler le contrat
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Le JavaScript est maintenant dans public/assets/js/pages/contracts.js -->

<?php include_once __DIR__ . '/../../includes/footer.php'; ?> 