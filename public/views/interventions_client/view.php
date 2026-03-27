<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/FileUploadValidator.php';

// Vérification de l'accès - seuls les utilisateurs connectés peuvent voir les interventions
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

// Récupérer l'ID de l'intervention depuis l'URL
$interventionId = isset($intervention['id']) ? $intervention['id'] : '';

setPageVariables(
    'Intervention',
    'interventions_client' . ($interventionId ? '_view_' . $interventionId : '')
);

// Définir la page courante pour le menu
$currentPage = 'interventions_client';

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">

<div class="d-flex bd-highlight mb-3">
    <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Details de l'intervention</h4></div>

    <div class="ms-auto p-2 bd-highlight">
        <?php if (hasPermission('client_add_intervention')): ?>
            <a href="<?php echo BASE_URL; ?>interventions_client/add" class="btn btn-primary me-2">
                <i class="bi bi-plus-circle me-1"></i> Nouvelle intervention
            </a>
        <?php endif; ?>
        <a href="<?php echo BASE_URL; ?>interventions_client" class="btn btn-secondary me-2">
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

    <?php if ($intervention): ?>
        <!-- Details de l'intervention -->
        <div class="card">
            <div class="card-header py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <span class="fw-bold me-3"><?= h($intervention['reference'] ?? '') ?></span>
                        <?= h($intervention['title'] ?? '') ?>
                    </h5>
                    <div class="d-flex align-items-center gap-2">
                        <div class="text-muted me-2">
                            <i class="fas fa-clock me-1"></i>
                            <?= h($intervention['duration'] ?? '0') ?>h
                        </div>
                        <?php if (isInterventionLinkedToTicketContract($intervention['id'])): ?>
                        <div class="text-muted me-2">
                            <i class="fas fa-ticket-alt me-1"></i>
                            <?= h($intervention['tickets_used'] ?? '0') ?>
                        </div>
                        <?php endif; ?>
                        <span class="badge rounded-pill" style="background-color: <?= h($intervention['status_color'] ?? '') ?>">
                            <?= h($intervention['status_name'] ?? '') ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body py-2">
                <div class="row g-3">
                    <!-- Colonne 1 : Client, Site, Salle -->
                    <div class="col-md-3">
                        <div class="d-flex flex-column gap-2">
                            <!-- Client -->
                            <div>
                                <label class="form-label fw-bold mb-0">Client</label>
                                <p class="form-control-static mb-0"><?= h($intervention['client_name'] ?? '') ?></p>
                            </div>

                            <!-- Site -->
                            <?php if (!empty($intervention['site_name'])): ?>
                            <div>
                                <label class="form-label fw-bold mb-0">Site</label>
                                <p class="form-control-static mb-0"><?= h($intervention['site_name']) ?></p>
                            </div>
                            <?php endif; ?>

                            <!-- Salle -->
                            <?php if (!empty($intervention['room_name'])): ?>
                            <div>
                                <label class="form-label fw-bold mb-0">Salle</label>
                                <p class="form-control-static mb-0"><?= h($intervention['room_name']) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Colonne 2 : Type, Deplacement, Contrat -->
                    <div class="col-md-3">
                        <div class="d-flex flex-column gap-2">
                            <!-- Type d'intervention -->
                            <div>
                                <label class="form-label fw-bold mb-0">Type d'intervention</label>
                                <p class="form-control-static mb-0"><?= h($intervention['type_name'] ?? '') ?></p>
                            </div>

                            <!-- Deplacement -->
                            <div>
                                <label class="form-label fw-bold mb-0">Deplacement</label>
                                <p class="form-control-static mb-0">
                                    <?= isset($intervention['type_requires_travel']) && (int)$intervention['type_requires_travel'] === 1 ? 'Oui' : 'Non' ?>
                                </p>
                            </div>

                            <!-- Contrat -->
                            <?php if (!empty($intervention['contract_name'])): ?>
                            <div>
                                <label class="form-label fw-bold mb-0">Contrat</label>
                                <p class="form-control-static mb-0"><?= h($intervention['contract_name']) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Colonne 3 : Priorite, Date de creation, Technicien -->
                    <div class="col-md-3">
                        <div class="d-flex flex-column gap-2">
                            <!-- Priorite -->
                            <div>
                                <label class="form-label fw-bold mb-0">Priorite</label>
                                <p class="form-control-static mb-0">
                                    <span class="badge" style="background-color: <?= h($intervention['priority_color'] ?? '') ?>">
                                        <?= h($intervention['priority_name'] ?? '') ?>
                                    </span>
                                </p>
                            </div>

                            <!-- Date de creation -->
                            <div>
                                <label class="form-label fw-bold mb-0">Date de creation</label>
                                <p class="form-control-static mb-0"><?= date('d/m/Y H:i', strtotime($intervention['created_at'])) ?></p>
                            </div>

                            <!-- Technicien -->
                            <div>
                                <label class="form-label fw-bold mb-0">Technicien</label>
                                <p class="form-control-static mb-0">
                                    <?php if (!empty($intervention['technician_first_name']) || !empty($intervention['technician_last_name'])): ?>
                                        <?= h($intervention['technician_first_name'] ?? '') ?> <?= h($intervention['technician_last_name'] ?? '') ?>
                                    <?php else: ?>
                                        Non attribue
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Colonne 4 : Date planifiee, Heure planifiee -->
                    <div class="col-md-3">
                        <div class="d-flex flex-column gap-2">
                            <!-- Date planifiee -->
                            <div>
                                <label class="form-label fw-bold mb-0">Date planifiee</label>
                                <p class="form-control-static mb-0">
                                    <?= !empty($intervention['date_planif']) ? date('d/m/Y', strtotime($intervention['date_planif'])) : 'Non definie' ?>
                                </p>
                            </div>

                            <!-- Heure planifiee -->
                            <div>
                                <label class="form-label fw-bold mb-0">Heure planifiee</label>
                                <p class="form-control-static mb-0">
                                    <?= !empty($intervention['heure_planif']) ? h($intervention['heure_planif']) : 'Non definie' ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <?php if (!empty($intervention['description'])): ?>
                    <div class="col-12 mt-3">
                        <div class="card">
                            <div class="card-header py-2">
                                <h6 class="card-title mb-0">Description</h6>
                            </div>
                            <div class="card-body py-2">
                                <p class="mb-0"><?= nl2br(h($intervention['description'])) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section Commentaires et Pieces jointes -->
        <div class="row mt-4">
            <!-- Section Commentaires -->
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Commentaires</h5>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCommentModal">
                            <i class="bi bi-plus me-1"></i> Ajouter un commentaire
                        </button>
                    </div>
                    <div class="card-body py-2">
                        <?php if (!empty($comments)): ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment mb-3 p-3 border rounded">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <strong><?= h($comment['user_name'] ?? 'Utilisateur inconnu') ?></strong>
                                            <small class="text-muted"><?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?></small>
                                        </div>
                                        <div>
                                            <?php if ($comment['is_solution']): ?>
                                                <span class="badge bg-success">Solution</span>
                                            <?php endif; ?>
                                            <?php if ($comment['is_observation']): ?>
                                                <span class="badge bg-warning">Observation</span>
                                            <?php endif; ?>
                                            <?php 
                                            // Permettre la modification/suppression si c'est le commentaire du client connecté
                                            $currentUserId = $_SESSION['user']['id'] ?? 0;
                                            $isOwnComment = ($comment['created_by'] ?? 0) == $currentUserId;
                                            if ($isOwnComment): 
                                            ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-warning btn-action" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editCommentModal<?= $comment['id'] ?>"
                                                        title="Modifier">
                                                        <i class="bi bi-pencil me-1"></i>
                                                </button>
                                                <a href="<?= BASE_URL ?>interventions_client/deleteComment/<?= $comment['id'] ?>" 
                                                   class="btn btn-sm btn-outline-danger btn-action" 
                                                   onclick="return confirm('Etes-vous sur de vouloir supprimer ce commentaire ?')"
                                                   title="Supprimer">
                                                    <i class="bi bi-trash me-1"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <p class="mb-0"><?= nl2br(h($comment['comment'])) ?></p>
                                </div>

                                <!-- Modal Edition de commentaire -->
                                <?php if ($isOwnComment): ?>
                                <div class="modal fade" id="editCommentModal<?= $comment['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="<?= BASE_URL ?>interventions_client/editComment/<?= $comment['id'] ?>" method="post">
                                                <?= csrf_field() ?>
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Modifier le commentaire</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label for="comment<?= $comment['id'] ?>" class="form-label">Commentaire</label>
                                                        <textarea class="form-control" id="comment<?= $comment['id'] ?>" name="comment" rows="4" required><?= h($comment['comment']) ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted mb-0">Aucun commentaire pour le moment.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Section Pieces jointes -->
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Pieces jointes</h5>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAttachmentModal">
                            <i class="bi bi-plus me-1"></i> Ajouter une piece jointe
                        </button>
                    </div>
                    <div class="card-body py-2">
                        <?php if (empty($attachments)): ?>
                            <p class="text-muted mb-0">Aucune piece jointe pour le moment.</p>
                        <?php else: ?>
                            <?php 
                            // Trier les pieces jointes pour mettre le bon d'intervention en premier
                            usort($attachments, function($a, $b) {
                                $aIsReport = isset($a['nom_fichier']) && strpos($a['nom_fichier'], 'bon_intervention_') === 0;
                                $bIsReport = isset($b['nom_fichier']) && strpos($b['nom_fichier'], 'bon_intervention_') === 0;
                                if ($aIsReport && !$bIsReport) return -1;
                                if (!$aIsReport && $bIsReport) return 1;
                                return strtotime($b['date_creation']) - strtotime($a['date_creation']);
                            });
                            
                            foreach ($attachments as $attachment): 
                                $isReport = isset($attachment['nom_fichier']) && strpos($attachment['nom_fichier'], 'bon_intervention_') === 0;
                                $originalFileName = $attachment['nom_personnalise'] ?? $attachment['nom_fichier'];
                                $extension = isset($originalFileName) ? strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION)) : '';
                                $isPdf = $extension === 'pdf';
                                $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg']);
                                $isExcel = in_array($extension, ['xls', 'xlsx']);
                                $filename = $attachment['nom_fichier'];
                                $currentUserId = $_SESSION['user']['id'] ?? 0;
                                $isOwnAttachment = ($attachment['created_by'] ?? 0) == $currentUserId;
                            ?>
                                <div class="card mb-2">
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
                                                    title="Apercu">
                                                                                                    <i class="<?php echo getIcon('preview', 'bi bi-eye'); ?>"></i>
                                            </button>
                                            <a href="<?= BASE_URL ?>interventions_client/download/<?= $attachment['id'] ?>" 
                                               class="btn btn-sm btn-outline-success btn-action" 
                                               title="Telecharger" target="_blank">
                                                <i class="<?php echo getIcon('download', 'bi bi-download'); ?>"></i>
                                            </a>
                                            <?php if ($isOwnAttachment): ?>
                                                <a href="<?= BASE_URL ?>interventions_client/deleteAttachment/<?= $attachment['id'] ?>" 
                                                   class="btn btn-sm btn-outline-danger btn-action" 
                                                   title="Supprimer"
                                                   onclick="return confirm('Etes-vous sur de vouloir supprimer cette piece jointe ?');">
                                                    <i class="bi bi-trash me-1"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-body py-2">
                                        <div class="d-flex align-items-center">
                                            <?php if ($isReport): ?>
                                                <i class="bi bi-file-pdf text-danger me-2 me-1"></i>
                                            <?php elseif ($isPdf): ?>
                                                <i class="bi bi-file-pdf text-danger me-2 me-1"></i>
                                            <?php elseif ($isImage): ?>
                                                <i class="bi bi-image-fill text-primary me-2 me-1"></i>
                                            <?php elseif ($isExcel): ?>
                                                <i class="bi bi-file-spreadsheet text-success me-2 me-1"></i>
                                            <?php else: ?>
                                                <i class="bi bi-file-earmark text-secondary me-2 me-1"></i>
                                            <?php endif; ?>
                                            <div class="attachment-name">
                                                <div class="display-name"><?php echo h($filename); ?></div>
                                                <?php if (!empty($attachment['nom_personnalise']) && $attachment['nom_personnalise'] !== $filename): ?>
                                                    <div class="original-name text-muted small"><?php echo h($attachment['nom_personnalise']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal d'apercu -->
                                <div class="modal fade" id="previewModal<?= $attachment['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-xl">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    <div class="attachment-name">
                                                        <div class="display-name"><?= h($filename) ?></div>
                                                        <?php if (!empty($attachment['nom_personnalise']) && $attachment['nom_personnalise'] !== $filename): ?>
                                                            <div class="original-name text-muted small"><?= h($attachment['nom_personnalise']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="preview-container">
                                                    <?php 
                                                    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                                    if ($extension === 'pdf'): 
                                                    ?>
                                                        <iframe src="<?= BASE_URL ?>interventions_client/preview/<?= $attachment['id'] ?>" 
                                                                width="100%" 
                                                                height="600px" 
                                                                frameborder="0">
                                                        </iframe>
                                                    <?php elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                        <img src="<?= BASE_URL ?>interventions_client/preview/<?= $attachment['id'] ?>" 
                                                             class="img-fluid" 
                                                             alt="<?= h($filename) ?>">
                                                    <?php else: ?>
                                                        <div class="alert alert-info">
                                                            <i class="bi bi-info-circle me-1"></i> 
                                                            Ce type de fichier ne peut pas etre previsualise. 
                                                            <a href="<?= BASE_URL ?>interventions_client/download/<?= $attachment['id'] ?>" 
                                                               class="alert-link" 
                                                               target="_blank">
                                                                Telecharger le fichier
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <a href="<?= BASE_URL ?>interventions_client/download/<?= $attachment['id'] ?>" 
                                                   class="btn btn-primary" 
                                                   target="_blank">
                                                    <i class="bi bi-download me-1"></i> Telecharger
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
            </div>
        </div>

        <!-- Modal Ajout de commentaire -->
        <div class="modal fade" id="addCommentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_URL ?>interventions_client/addComment/<?= $intervention['id'] ?>" method="post">
                        <?= csrf_field() ?>
                        <div class="modal-header">
                            <h5 class="modal-title">Ajouter un commentaire</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="comment" class="form-label">Commentaire</label>
                                <textarea class="form-control" id="comment" name="comment" rows="4" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Ajouter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Ajout de pièces jointes avec Drag & Drop -->
        <div class="modal fade" id="addAttachmentModal" tabindex="-1" aria-labelledby="addAttachmentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form action="<?= BASE_URL ?>interventions_client/addMultipleAttachments/<?= $intervention['id']; ?>" method="post" enctype="multipart/form-data" id="dragDropForm">
                        <?= csrf_field() ?>
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

    <?php else: ?>
        <div class="alert alert-warning">
            <h6>Intervention non trouvee</h6>
            <p>L'intervention demandee n'existe pas ou vous n'avez pas les droits pour y acceder.</p>
        </div>
    <?php endif; ?>

</div>

<!-- JavaScript extrait vers public/assets/js/pages/interventions_client.js -->
<!-- La classe DragDropUploader est maintenant dans public/assets/js/components/DragDropUploader.js -->
<!-- Le code d'initialisation est dans public/assets/js/pages/interventions_client.js -->
<!-- Code JavaScript supprimé - Utilise maintenant DragDropUploader.js et interventions_client.js -->

<!-- JavaScript spécifique à la page interventions_client -->
<script src="<?php echo BASE_URL; ?>assets/js/pages/interventions_client.js" onerror="console.error('ERREUR: interventions_client.js n\'a pas pu être chargé. Vérifiez que le fichier existe et est accessible.');"></script>

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
    gap: 10px;
}

.file-item.valid {
    background-color: var(--bs-success-bg-subtle);
    border-color: var(--bs-success-border-subtle);
}

.file-item.invalid {
    background-color: var(--bs-danger-bg-subtle);
    border-color: var(--bs-danger-border-subtle);
}

.file-info {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
}

.file-name {
    font-weight: 500;
    font-size: 0.9em;
    color: var(--bs-body-color);
}

.file-size {
    color: var(--bs-secondary-color);
    font-size: 0.8em;
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

/* Styles pour le champ de nom personnalisé intégré */
.custom-name-field {
    flex: 0 0 200px;
}

.custom-name-field input {
    width: 100%;
    padding: 4px 8px;
    border: 1px solid var(--bs-border-color);
    border-radius: 3px;
    font-size: 0.8em;
    background-color: var(--bs-body-bg);
    color: var(--bs-body-color);
}

.custom-name-field input:focus {
    outline: none;
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.25);
}

[data-bs-theme="dark"] .custom-name-field input {
    background-color: var(--bs-body-bg);
    border-color: var(--bs-border-color);
    color: var(--bs-body-color);
}

/* Styles pour l'affichage des noms de fichiers */
.attachment-name {
    display: flex;
    flex-direction: column;
}

.attachment-name .display-name {
    font-weight: 500;
    color: var(--bs-body-color);
}

.attachment-name .original-name {
    font-size: 0.75em;
    margin-top: 2px;
    opacity: 0.7;
    font-style: italic;
}
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?> 