<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/FileUploadValidator.php';

// Vérification des permissions pour modifier les interventions
if (!canModifyInterventions()) {
    $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour modifier cette intervention.";
    header('Location: ' . BASE_URL . 'interventions/view/' . ($intervention['id'] ?? ''));
    exit;
}

setPageVariables(
    'Modifier l\'intervention',
    'intervention_edit'
);

// Définir la page courante pour le menu
$currentPage = 'interventions';

// Définir les breadcrumbs personnalisés pour l'édition d'intervention
if (isset($intervention) && !empty($intervention)) {
    $GLOBALS['customBreadcrumbs'] = generateInterventionEditBreadcrumbs($intervention);
}

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">

    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">Modifier l'intervention</h4>
        </div>

        <div class="ms-auto p-2 bd-highlight">
            <a href="<?php echo BASE_URL; ?>interventions/view/<?php echo $intervention['id']; ?>"
                class="btn btn-secondary me-2">
                <i class="bi bi-arrow-left me-1"></i> Retour
            </a>

            <button type="button" id="saveButton" class="btn btn-primary">
                <i class="bi bi-save me-1"></i> Enregistrer les modifications
            </button>
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

    <?php if (isset($_SESSION['info'])): ?>
        <div class="alert alert-info">
            <?php
            echo $_SESSION['info'];
            unset($_SESSION['info']);
            ?>
        </div>
    <?php endif; ?>

    <?php if ($intervention): ?>
        <!-- Alerte pour les interventions flash à compléter -->
        <?php if (isset($intervention['is_flash']) && $intervention['is_flash'] == 1 && $intervention['needs_completion'] == 1): ?>
            <div class="alert alert-warning alert-dismissible fade show mb-4 border-start border-4 border-warning" role="alert">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="bi bi-lightning-charge-fill fs-1 text-warning"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="alert-heading mb-2">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Intervention Flash à compléter !
                        </h5>
                        <p class="mb-2">
                            Cette intervention a été créée rapidement. Veuillez compléter les informations suivantes :
                        </p>
                        <ul class="mt-2 mb-0">
                            <?php if (empty($intervention['site_id'])): ?>
                                <li><i class="bi bi-geo-alt text-warning"></i> <strong>Site</strong> - Sélectionnez un site</li>
                            <?php endif; ?>
                            <?php if (empty($intervention['building_id'])): ?>
                                <li><i class="bi bi-building text-warning"></i> <strong>Bâtiment</strong> - Sélectionnez un bâtiment</li>
                            <?php endif; ?>
                            <?php if (empty($intervention['room_id'])): ?>
                                <li><i class="bi bi-door-closed text-warning"></i> <strong>Salle</strong> - Sélectionnez une salle</li>
                            <?php endif; ?>
                            <?php if (empty($intervention['description'])): ?>
                                <li><i class="bi bi-file-text text-warning"></i> <strong>Description</strong> - Décrivez le problème</li>
                            <?php endif; ?>
                            <?php if (empty($intervention['demande_par'])): ?>
                                <li><i class="bi bi-person text-warning"></i> <strong>Demandeur</strong> - Qui a demandé l'intervention ?</li>
                            <?php endif; ?>
                            <?php if (empty($intervention['title']) || $intervention['title'] == 'Flash Intervention - Assistance téléphonique' || strpos($intervention['title'], 'Flash Intervention') !== false): ?>
                                <li><i class="bi bi-pencil text-warning"></i> <strong>Titre</strong> - Personnalisez le titre</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Formulaire de modification -->
        <div class="card">
            <div class="card-header py-2">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="card-title mb-0">
                            <span class="fw-bold me-3"><?= h($intervention['reference'] ?? '') ?></span>
                            <input type="text" class="form-control d-inline-block bg-body text-body" id="title" name="title"
                                value="<?= h($intervention['title'] ?? '') ?>" required>
                        </h5>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label fw-bold mb-0 text-white">Date de création</label>
                                <input type="date" class="form-control bg-body text-body" id="created_date"
                                    name="created_date" value="<?= date('Y-m-d', strtotime($intervention['created_at'])) ?>"
                                    form="interventionForm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold mb-0 text-white">Heure de création</label>
                                <input type="time" class="form-control bg-body text-body" id="created_time"
                                    name="created_time" value="<?= date('H:i', strtotime($intervention['created_at'])) ?>"
                                    form="interventionForm">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body py-2">
                <form action="<?php echo BASE_URL; ?>interventions/update/<?php echo $intervention['id']; ?>" method="post"
                    id="interventionForm">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <!-- Colonne 1 : Client, Site, Bâtiment, Salle -->
                        <div class="col-md-3">
                            <div class="d-flex flex-column gap-2">
                                <!-- Client -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Client *</label>
                                    <div class="input-group">
                                        <select class="form-select bg-body text-body" id="client_id" name="client_id" required>
                                            <option value="">Sélectionner un client</option>
                                            <?php foreach ($clients as $client): ?>
                                                <option value="<?= $client['id'] ?>"
                                                    <?= $client['id'] == $intervention['client_id'] ? 'selected' : '' ?>>
                                                    <?= h($client['name'] ?? '') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                            id="quickCreateClientBtn" title="Créer un nouveau client">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Site -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Site</label>
                                    <div class="input-group">
                                        <select class="form-select bg-body text-body" id="site_id" name="site_id">
                                            <option value="">Sélectionner un site</option>
                                            <?php foreach ($sites as $site): ?>
                                                <option value="<?= $site['id'] ?>" <?= $site['id'] == $intervention['site_id'] ? 'selected' : '' ?>>
                                                    <?= h($site['name'] ?? '') ?>
                                                </option>
                                            <?php endforeach; ?>
                                            <?php if ($intervention['site_id'] && $intervention['site_id'] !== '0' && !in_array($intervention['site_id'], array_column($sites, 'id'))): ?>
                                                <option value="<?= $intervention['site_id'] ?>" selected style="display: none;">
                                                    <?= h($intervention['site_name'] ?? 'Site inconnu') ?>
                                                </option>
                                            <?php endif; ?>
                                        </select>
                                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                            id="quickCreateSiteBtn" title="Créer un nouveau site">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Bâtiment -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Bâtiment</label>
                                    <div class="input-group">
                                        <select class="form-select bg-body text-body" id="building_id" name="building_id">
                                            <option value="">Sélectionner un bâtiment</option>
                                            <?php if (isset($buildings) && is_array($buildings)): ?>
                                                <?php foreach ($buildings as $building): ?>
                                                    <option value="<?= $building['id'] ?>" <?= (isset($intervention['building_id']) && $building['id'] == $intervention['building_id']) ? 'selected' : '' ?>>
                                                        <?= h($building['name'] ?? '') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                            id="quickCreateBuildingBtn" title="Créer un nouveau bâtiment">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Salle -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Salle</label>
                                    <div class="input-group">
                                        <select class="form-select bg-body text-body" id="room_id" name="room_id">
                                            <option value="">Sélectionner une salle</option>
                                            <?php foreach ($rooms as $room): ?>
                                                <option value="<?= $room['id'] ?>" <?= $room['id'] == $intervention['room_id'] ? 'selected' : '' ?>>
                                                    <?= h($room['name'] ?? '') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                            id="quickCreateRoomBtn" title="Créer une nouvelle salle">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Colonne 2 : Type, Contrat -->
                        <div class="col-md-3">
                            <div class="d-flex flex-column gap-2">
                                <!-- Type d'intervention -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Type d'intervention *</label>
                                    <select class="form-select bg-body text-body" id="type_id" name="type_id" required>
                                        <option value="">Sélectionner un type</option>
                                        <?php foreach ($types as $type): ?>
                                            <option value="<?= $type['id'] ?>" <?= $type['id'] == $intervention['type_id'] ? 'selected' : '' ?>>
                                                <?= h($type['name'] ?? '') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Contrat -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Contrat associé *</label>
                                    <select class="form-select bg-body text-body" id="contract_id" name="contract_id" required>
                                        <option value="">Sélectionner un contrat</option>
                                        <?php foreach ($contracts as $contract): ?>
                                            <option value="<?= $contract['id'] ?>"
                                                <?= $contract['id'] == $intervention['contract_id'] ? 'selected' : '' ?>>
                                                <?= h($contract['name'] ?? '') ?>
                                                (<?= h($contract['contract_type_name'] ?? '') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Colonne 3 : Statut, Priorité, Case à cocher Préventive -->
                        <div class="col-md-3">
                            <div class="d-flex flex-column gap-2">
                                <!-- Statut -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Statut *</label>
                                    <select class="form-select bg-body text-body" id="status_id" name="status_id" required>
                                        <option value="">Sélectionner un statut</option>
                                        <?php foreach ($statuses as $status): ?>
                                            <option value="<?= $status['id'] ?>" <?= $status['id'] == $intervention['status_id'] ? 'selected' : '' ?>>
                                                <?= h($status['name'] ?: '') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Priorité -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Priorité *</label>
                                    <select class="form-select bg-body text-body" id="priority_id" name="priority_id" required>
                                        <?php 
                                        // Si l'intervention est préventive, forcer l'affichage de la priorité préventive
                                        $selectedPriority = isset($intervention['is_preventive']) && $intervention['is_preventive'] == 1 ? 5 : $intervention['priority_id'];
                                        ?>
                                        <?php foreach ($priorities as $priority): ?>
                                            <option value="<?= $priority['id'] ?>" <?= $priority['id'] == $selectedPriority ? 'selected' : '' ?>>
                                                <?= h($priority['name'] ?? '') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Case à cocher Intervention préventive -->
                                <div class="mt-2 pt-1">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="is_preventive" name="is_preventive" value="1"
                                            <?= isset($intervention['is_preventive']) && $intervention['is_preventive'] == 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="is_preventive">
                                            Intervention préventive
                                        </label>
                                        <small class="text-muted d-block mt-1">
                                            Cocher pour une intervention préventive
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="col-12 mt-3">
                            <div class="card">
                                <div class="card-header py-2">
                                    <h6 class="card-title mb-0">Demande/description du problème</h6>
                                </div>
                                <div class="card-body py-2">
                                    <textarea class="form-control bg-body text-body" id="description" name="description"
                                        rows="5"><?php echo h($intervention['description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Informations de contact et demande -->
                        <div class="col-12 mt-3">
                            <div class="card contact-info-card">
                                <div class="card-header py-2 contact-info-header">
                                    <h6 class="card-title mb-0 fw-bold">
                                        <i class="bi bi-person-lines-fill me-2"></i>Informations de contact et demande
                                    </h6>
                                </div>
                                <div class="card-body py-3">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Demande par</label>
                                            <input type="text" class="form-control bg-body text-body" id="demande_par"
                                                name="demande_par"
                                                value="<?php echo h($intervention['demande_par'] ?? ''); ?>"
                                                placeholder="Nom de la personne qui a demandé l'intervention">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Référence client</label>
                                            <input type="text" class="form-control bg-body text-body" id="ref_client"
                                                name="ref_client" value="<?= h($intervention['ref_client'] ?? '') ?>"
                                                placeholder="Référence interne du client">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Contact existant</label>
                                            <div class="input-group">
                                                <select class="form-select bg-body text-body" id="contact_client_select"
                                                    name="contact_client_select">
                                                    <option value="">Sélectionner un contact existant</option>
                                                    <!-- Les contacts seront chargés dynamiquement selon le client -->
                                                </select>
                                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                                    id="quickCreateContactBtn" title="Créer un nouveau contact">
                                                    <i class="bi bi-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Ou saisir un email</label>
                                            <input type="email" class="form-control bg-body text-body" id="contact_client"
                                                name="contact_client"
                                                value="<?php echo h($intervention['contact_client'] ?? ''); ?>"
                                                placeholder="email@exemple.com">
                                            <div class="invalid-feedback" id="email-error"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Le reste du code (commentaires, pièces jointes, historique) reste inchangé -->
        
        <!-- Espace entre le formulaire et les sections -->
        <div class="mb-4"></div>

        <!-- Section Commentaires et Pièces jointes -->
        <div class="row">
            <!-- Section Commentaires -->
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Compte-rendu/observations</h5>
                        <?php if (canModifyInterventions() && $intervention['status_id'] != 6): ?>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                data-bs-target="#addCommentModal">
                                <i class="bi bi-plus me-1"></i> Ajouter un commentaire
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body py-2">
                        <?php if (empty($comments)): ?>
                            <p class="text-muted mb-0">Aucun commentaire pour le moment.</p>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="card mb-2 <?php echo $comment['is_solution'] ? 'bg-success bg-opacity-10' : ''; ?>">
                                    <div class="card-header py-1 d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo h($comment['created_by_name'] ?? 'Utilisateur inconnu'); ?></strong>
                                            <small class="text-muted ms-2">
                                                <?php echo date('d/m/Y H:i', strtotime($comment['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div>
                                            <?php if ($comment['is_solution']): ?>
                                                <span class="badge bg-success">Solution</span>
                                            <?php endif; ?>
                                            <?php if ($comment['visible_by_client']): ?>
                                                <span class="badge bg-info">Visible par le client</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Interne</span>
                                            <?php endif; ?>
                                            <?php if (canDelete()): ?>
                                                <a href="<?php echo BASE_URL; ?>interventions/deleteComment/<?php echo $comment['id']; ?>"
                                                    class="btn btn-sm btn-outline-danger btn-action" title="Supprimer"
                                                    onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?');">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-body py-2">
                                        <p class="card-text mb-0"><?php echo nl2br(h($comment['comment'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Section Pièces jointes -->
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Pièces jointes</h5>
                        <?php if (canModifyInterventions() && $intervention['status_id'] != 6): ?>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openAttachmentModal()">
                                <i class="bi bi-plus me-1"></i> Ajouter une pièce jointe
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body py-2">
                        <?php if (empty($attachments)): ?>
                            <p class="text-muted mb-0">Aucune pièce jointe pour le moment.</p>
                        <?php else: ?>
                            <?php
                            usort($attachments, function ($a, $b) {
                                $aIsBI = $a['type_liaison'] === 'bi';
                                $bIsBI = $b['type_liaison'] === 'bi';
                                if ($aIsBI && !$bIsBI) return -1;
                                if (!$aIsBI && $bIsBI) return 1;
                                return strtotime($b['date_creation']) - strtotime($a['date_creation']);
                            });

                            foreach ($attachments as $attachment):
                                $isBI = $attachment['type_liaison'] === 'bi';
                                $originalFileName = $attachment['nom_personnalise'] ?? $attachment['nom_fichier'];
                                $extension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
                                $isPdf = $extension === 'pdf';
                                $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg']);
                                $isExcel = in_array($extension, ['xls', 'xlsx']);
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
                                            <button type="button" class="btn btn-sm btn-outline-info btn-action"
                                                data-bs-toggle="modal" data-bs-target="#previewModal<?= $attachment['id'] ?>"
                                                title="Aperçu">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <a href="<?php echo BASE_URL; ?>interventions/download/<?php echo $attachment['id']; ?>"
                                                class="btn btn-sm btn-outline-success btn-action" title="Télécharger">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <?php if (canDelete()): ?>
                                                <a href="<?php echo BASE_URL; ?>interventions/deleteAttachment/<?php echo $attachment['id']; ?>"
                                                    class="btn btn-sm btn-outline-danger btn-action" title="Supprimer"
                                                    onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette pièce jointe ?');">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-body py-2">
                                        <div class="d-flex align-items-center">
                                            <?php if ($isBI): ?>
                                                <i class="bi bi-file-pdf text-danger me-2"></i>
                                                <span class="badge bg-info me-2">BI</span>
                                            <?php elseif ($isPdf): ?>
                                                <i class="bi bi-file-pdf text-danger me-2"></i>
                                            <?php elseif ($isImage): ?>
                                                <i class="bi bi-image-fill text-primary me-2"></i>
                                            <?php elseif ($isExcel): ?>
                                                <i class="bi bi-file-spreadsheet text-success me-2"></i>
                                            <?php else: ?>
                                                <i class="bi bi-file-earmark text-secondary me-2"></i>
                                            <?php endif; ?>
                                            <div class="attachment-name flex-grow-1">
                                                <div class="display-name"><?php echo h($attachment['nom_fichier']); ?></div>
                                                <?php if (!empty($attachment['nom_personnalise']) && $attachment['nom_personnalise'] !== $attachment['nom_fichier']): ?>
                                                    <div class="original-name text-muted small">
                                                        <?php echo h($attachment['nom_personnalise']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (canModifyInterventions() && $intervention['status_id'] != 6): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                                    onclick="editAttachmentName(<?= $attachment['id'] ?>, '<?= h($attachment['nom_fichier']) ?>')"
                                                    title="Modifier le nom">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal d'aperçu -->
                                <div class="modal fade" id="previewModal<?= $attachment['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-xl">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    <div class="attachment-name">
                                                        <div class="display-name"><?= h($attachment['nom_fichier']) ?></div>
                                                        <?php if (!empty($attachment['nom_personnalise']) && $attachment['nom_personnalise'] !== $attachment['nom_fichier']): ?>
                                                            <div class="original-name text-muted small">
                                                                <?= h($attachment['nom_personnalise']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                    aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="preview-container">
                                                    <?php
                                                    $extension = strtolower(pathinfo($attachment['nom_fichier'], PATHINFO_EXTENSION));
                                                    if ($extension === 'pdf'):
                                                        ?>
                                                        <iframe src="<?= BASE_URL; ?>interventions/preview/<?= $attachment['id'] ?>"
                                                            width="100%" height="600px" frameborder="0">
                                                        </iframe>
                                                    <?php elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                        <img src="<?= BASE_URL; ?>interventions/preview/<?= $attachment['id'] ?>"
                                                            class="img-fluid" alt="<?= h($attachment['nom_fichier']) ?>">
                                                    <?php else: ?>
                                                        <div class="alert alert-info">
                                                            <i class="bi bi-info-circle me-1"></i>
                                                            Ce type de fichier ne peut pas être prévisualisé.
                                                            <a href="<?= BASE_URL; ?>interventions/download/<?= $attachment['id'] ?>"
                                                                class="alert-link" target="_blank">
                                                                Télécharger le fichier
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <a href="<?= BASE_URL; ?>interventions/download/<?= $attachment['id'] ?>"
                                                    class="btn btn-primary" target="_blank">
                                                    <i class="bi bi-download me-1"></i> Télécharger
                                                </a>
                                                <button type="button" class="btn btn-secondary"
                                                    data-bs-dismiss="modal">Fermer</button>
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

        <!-- Section Historique (Bouton flottant) -->
        <button type="button" class="btn btn-sm btn-outline-secondary position-fixed bottom-0 end-0 m-3"
            data-bs-toggle="modal" data-bs-target="#historyModal" title="Historique des modifications">
            <i class="bi bi-clock-history me-1"></i>
        </button>

        <!-- Modal Historique -->
        <div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-clock-history me-2"></i> Historique des modifications
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
                                            par
                                            <?php echo isset($entry['changed_by_name']) && $entry['changed_by_name'] !== null ? h($entry['changed_by_name']) : 'Utilisateur inconnu'; ?>
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
    <?php else: ?>
        <div class="alert alert-danger">
            Intervention introuvable.
        </div>
    <?php endif; ?>
</div>


<!-- Modal Ajout de commentaire -->
<div class="modal fade" id="addCommentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>interventions/addComment/<?= $intervention['id'] ?>" method="post">
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
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="visible_by_client" name="visible_by_client" value="1" checked>
                            <label class="form-check-label" for="visible_by_client">
                                Visible par le client
                            </label>
                        </div>
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
<div class="modal fade" id="addAttachmentModal" tabindex="-1" aria-labelledby="addAttachmentModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form
                action="<?php echo BASE_URL; ?>interventions/addMultipleAttachments/<?php echo $intervention['id']; ?>"
                method="post" enctype="multipart/form-data" id="dragDropForm">
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
<script>
// Initialisation du Drag & Drop pour l'upload de fichiers dans edit.php
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const fileList = document.getElementById('fileList');
    const stats = document.getElementById('stats');
    const validCountSpan = document.getElementById('validCount');
    const invalidCountSpan = document.getElementById('invalidCount');
    const clearAllBtn = document.getElementById('clearAllBtn');
    const uploadValidBtn = document.getElementById('uploadValidBtn');
    const dragDropForm = document.getElementById('dragDropForm');
    
    let validFiles = [];
    let invalidFiles = [];
    
    if (!dropZone) return;
    
    // Empêcher le comportement par défaut du navigateur pour le drag & drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    // Highlight la zone de drop
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight(e) {
        dropZone.classList.add('dragover');
    }
    
    function unhighlight(e) {
        dropZone.classList.remove('dragover');
    }
    
    // Gérer le drop
    dropZone.addEventListener('drop', handleDrop, false);
    dropZone.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', handleFiles);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles({ target: { files: files } });
    }
    
    function handleFiles(e) {
        const files = Array.from(e.target.files);
        
        const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
        
        files.forEach(file => {
            const extension = file.name.split('.').pop().toLowerCase();
            const isValid = allowedExtensions.includes(extension);
            
            if (isValid) {
                validFiles.push(file);
            } else {
                invalidFiles.push({
                    file: file,
                    error: `Extension .${extension} non autorisée`
                });
            }
        });
        
        updateFileList();
        
        // Réinitialiser l'input file
        fileInput.value = '';
    }
    
    function updateFileList() {
        fileList.innerHTML = '';
        
        // Afficher les fichiers valides
        validFiles.forEach((file, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item valid';
            fileItem.innerHTML = `
                <div class="file-info">
                    <i class="bi bi-file-earmark-check"></i>
                    <span class="file-name">${escapeHtml(file.name)}</span>
                    <span class="file-size">(${formatFileSize(file.size)})</span>
                </div>
                <div class="custom-name-field">
                    <input type="text" class="form-control form-control-sm" placeholder="Nom personnalisé (optionnel)" 
                           data-file-index="${index}" id="custom_name_${index}">
                </div>
                <button type="button" class="remove-file" data-index="${index}" data-type="valid">
                    <i class="bi bi-x-circle"></i>
                </button>
            `;
            fileList.appendChild(fileItem);
        });
        
        // Afficher les fichiers invalides
        invalidFiles.forEach((item, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item invalid';
            fileItem.innerHTML = `
                <div class="file-info">
                    <i class="bi bi-file-earmark-exclamation"></i>
                    <span class="file-name">${escapeHtml(item.file.name)}</span>
                    <span class="file-size">(${formatFileSize(item.file.size)})</span>
                    <span class="error-message">${escapeHtml(item.error)}</span>
                </div>
                <button type="button" class="remove-file" data-index="${index}" data-type="invalid">
                    <i class="bi bi-x-circle"></i>
                </button>
            `;
            fileList.appendChild(fileItem);
        });
        
        // Mettre à jour les compteurs
        validCountSpan.textContent = validFiles.length;
        invalidCountSpan.textContent = invalidFiles.length;
        stats.style.display = validFiles.length > 0 || invalidFiles.length > 0 ? 'block' : 'none';
        clearAllBtn.style.display = validFiles.length > 0 || invalidFiles.length > 0 ? 'inline-block' : 'none';
        uploadValidBtn.style.display = validFiles.length > 0 ? 'inline-block' : 'none';
        
        // Ajouter les événements de suppression
        document.querySelectorAll('.remove-file').forEach(btn => {
            btn.addEventListener('click', function() {
                const index = parseInt(this.dataset.index);
                const type = this.dataset.type;
                if (type === 'valid') {
                    validFiles.splice(index, 1);
                } else {
                    invalidFiles.splice(index, 1);
                }
                updateFileList();
            });
        });
    }
    
    // Effacer tous les fichiers
    clearAllBtn.addEventListener('click', function() {
        validFiles = [];
        invalidFiles = [];
        updateFileList();
    });
    
    // Upload des fichiers valides
    uploadValidBtn.addEventListener('click', function() {
        if (validFiles.length === 0) return;
        
        const formData = new FormData(dragDropForm);
        
        // Ajouter les fichiers avec leurs noms personnalisés
        validFiles.forEach((file, index) => {
            const customNameInput = document.getElementById(`custom_name_${index}`);
            const customName = customNameInput ? customNameInput.value.trim() : '';
            formData.append('attachments[]', file);
            formData.append('custom_names[]', customName);
        });
        
        // Désactiver le bouton pendant l'upload
        uploadValidBtn.disabled = true;
        uploadValidBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Upload en cours...';
        
        fetch('<?php echo BASE_URL; ?>interventions/addMultipleAttachments/<?php echo $intervention['id']; ?>', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Fermer le modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('addAttachmentModal'));
                if (modal) modal.hide();
                
                // Afficher un message de succès
                const successMsg = document.createElement('div');
                successMsg.className = 'alert alert-success alert-dismissible fade show mt-3';
                successMsg.innerHTML = `
                    <i class="bi bi-check-circle me-2"></i>
                    ${data.message || 'Fichiers uploadés avec succès !'}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                const container = document.querySelector('.container-p-y');
                if (container) {
                    container.insertBefore(successMsg, container.firstChild);
                }
                
                // Recharger la page après 2 secondes
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                alert('Erreur: ' + (data.error || 'Erreur lors de l\'upload'));
                uploadValidBtn.disabled = false;
                uploadValidBtn.innerHTML = '<i class="bi bi-upload me-1"></i> Uploader les fichiers valides';
            }
        })
        .catch(error => {
            
            alert('Erreur lors de l\'upload des fichiers');
            uploadValidBtn.disabled = false;
            uploadValidBtn.innerHTML = '<i class="bi bi-upload me-1"></i> Uploader les fichiers valides';
        });
    });
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    function escapeHtml(str) {
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
});
</script>
<script>
function openAttachmentModal() {
    const modalElement = document.getElementById('addAttachmentModal');
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    }
}
</script>
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
<script>
// Script pour gérer la case à cocher préventive et la priorité
document.addEventListener('DOMContentLoaded', function() {
    const isPreventiveCheckbox = document.getElementById('is_preventive');
    const prioritySelect = document.getElementById('priority_id');
    
    if (isPreventiveCheckbox && prioritySelect) {
        // Fonction pour mettre à jour la priorité
        function updatePriority() {
            if (isPreventiveCheckbox.checked) {
                // Si préventive, on peut toujours modifier la priorité
                // On ne bloque plus la sélection
                prioritySelect.disabled = false;
                // Optionnel: ajouter un message d'information
                const helpText = document.querySelector('#priority_id + small');
                if (helpText) {
                    helpText.innerHTML = '<i class="bi bi-info-circle me-1"></i>La priorité peut être modifiée même pour les interventions préventives.';
                }
            } else {
                prioritySelect.disabled = false;
            }
        }
        
        // Écouter le changement de la case à cocher
        isPreventiveCheckbox.addEventListener('change', updatePriority);
        
        // Appliquer au chargement
        updatePriority();
        
        // Ajouter une option pour créer des interventions préventives automatiquement
        // lors de la création de contrats de maintenance
        const autoCreatePreventiveBtn = document.getElementById('autoCreatePreventiveBtn');
        if (autoCreatePreventiveBtn) {
            autoCreatePreventiveBtn.addEventListener('click', function() {
                // Logique pour créer automatiquement des interventions préventives
                isPreventiveCheckbox.checked = true;
                updatePriority();
            });
        }
    }
});
</script>

<!-- Scripts pour le chargement dynamique des sites, bâtiments et salles -->
<script>
    // Initialiser BASE_URL pour JavaScript
    window.BASE_URL = '<?php echo BASE_URL; ?>';
    window.csrfToken = '<?php echo csrf_token(); ?>';

    // Fonction pour charger les sites d'un client
    function loadSites(clientId, siteSelectId, selectedSiteId = null, selectedSiteName = null, callback = null) {
        if (!clientId) {
            const siteSelect = document.getElementById(siteSelectId);
            if (siteSelect) {
                siteSelect.innerHTML = '<option value="">Sélectionner un site</option>';
            }
            if (callback) callback();
            return;
        }

        const siteSelect = document.getElementById(siteSelectId);
        if (!siteSelect) return;

        siteSelect.innerHTML = '<option value="">Chargement des sites...</option>';

        fetch(`${window.BASE_URL}interventions/getSites/${clientId}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then(response => response.json())
            .then(data => {
                siteSelect.innerHTML = '<option value="">Sélectionner un site</option>';

                if (data.sites && data.sites.length > 0) {
                    data.sites.forEach(site => {
                        const option = document.createElement('option');
                        option.value = site.id;
                        option.textContent = site.name;
                        if (selectedSiteId && site.id == selectedSiteId) {
                            option.selected = true;
                        }
                        siteSelect.appendChild(option);
                    });
                }

                if (selectedSiteId && selectedSiteName && !data.sites.find(s => s.id == selectedSiteId)) {
                    const option = document.createElement('option');
                    option.value = selectedSiteId;
                    option.textContent = selectedSiteName + ' (Site inconnu)';
                    option.selected = true;
                    siteSelect.appendChild(option);
                }

                if (callback) callback();
            })
            .catch(error => {
                siteSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                if (callback) callback();
            });
    }

    // Fonction pour charger les bâtiments d'un site
    function loadBuildings(siteId, buildingSelectId, selectedBuildingId = null, callback = null) {
        if (!siteId) {
            const buildingSelect = document.getElementById(buildingSelectId);
            if (buildingSelect) {
                buildingSelect.innerHTML = '<option value="">Sélectionner un bâtiment</option>';
            }
            if (callback) callback();
            return;
        }

        const buildingSelect = document.getElementById(buildingSelectId);
        if (!buildingSelect) return;

        buildingSelect.innerHTML = '<option value="">Chargement des bâtiments...</option>';

        fetch(`${window.BASE_URL}interventions/getBuildings/${siteId}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then(response => response.json())
            .then(data => {
                buildingSelect.innerHTML = '<option value="">Sélectionner un bâtiment</option>';

                if (data && data.length > 0) {
                    data.forEach(building => {
                        const option = document.createElement('option');
                        option.value = building.id;
                        option.textContent = building.name;
                        if (selectedBuildingId && building.id == selectedBuildingId) {
                            option.selected = true;
                        }
                        buildingSelect.appendChild(option);
                    });
                }

                if (callback) callback();
            })
            .catch(error => {
                buildingSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                if (callback) callback();
            });
    }

    // Fonction pour charger les salles d'un bâtiment
    function loadRoomsByBuilding(buildingId, roomSelectId, selectedRoomId = null, callback = null) {
        if (!buildingId) {
            const roomSelect = document.getElementById(roomSelectId);
            if (roomSelect) {
                roomSelect.innerHTML = '<option value="">Sélectionner une salle</option>';
            }
            if (callback) callback();
            return;
        }

        const roomSelect = document.getElementById(roomSelectId);
        if (!roomSelect) return;

        roomSelect.innerHTML = '<option value="">Chargement des salles...</option>';

        fetch(`${window.BASE_URL}interventions/getRoomsByBuilding/${buildingId}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then(response => response.json())
            .then(data => {
                roomSelect.innerHTML = '<option value="">Sélectionner une salle</option>';

                if (data && data.length > 0) {
                    data.forEach(room => {
                        const option = document.createElement('option');
                        option.value = room.id;
                        option.textContent = room.name;
                        if (selectedRoomId && room.id == selectedRoomId) {
                            option.selected = true;
                        }
                        roomSelect.appendChild(option);
                    });
                }

                if (callback) callback();
            })
            .catch(error => {
                roomSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                if (callback) callback();
            });
    }

    // Fonction pour mettre à jour le contrat sélectionné
    function updateSelectedContract(clientIdField, siteIdField, buildingIdField, roomIdField, contractIdField, selectedContractId = null) {
        const clientSelect = document.getElementById(clientIdField);
        const siteSelect = document.getElementById(siteIdField);
        const buildingSelect = document.getElementById(buildingIdField);
        const roomSelect = document.getElementById(roomIdField);
        const contractSelect = document.getElementById(contractIdField);

        if (!clientSelect || !contractSelect) return;

        const clientId = clientSelect.value;
        const siteId = siteSelect ? siteSelect.value : null;
        const buildingId = buildingSelect ? buildingSelect.value : null;
        const roomId = roomSelect ? roomSelect.value : null;

        if (!clientId) {
            contractSelect.innerHTML = '<option value="">Sélectionner un contrat</option>';
            return;
        }

        let url = `${window.BASE_URL}interventions/getContracts/${clientId}`;
        const params = [];
        if (siteId) params.push(`site_id=${siteId}`);
        if (buildingId) params.push(`building_id=${buildingId}`);
        if (roomId) params.push(`room_id=${roomId}`);
        if (params.length) url += '?' + params.join('&');

        contractSelect.innerHTML = '<option value="">Chargement des contrats...</option>';

        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then(response => response.json())
            .then(data => {
                contractSelect.innerHTML = '<option value="">Sélectionner un contrat</option>';

                if (data && data.length > 0) {
                    data.forEach(contract => {
                        const option = document.createElement('option');
                        option.value = contract.id;
                        option.textContent = `${contract.name} (${contract.contract_type_name || 'Sans type'})`;
                        if (selectedContractId && contract.id == selectedContractId) {
                            option.selected = true;
                        }
                        contractSelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                contractSelect.innerHTML = '<option value="">Erreur de chargement</option>';
            });
    }

    // Fonction pour charger les contacts d'un client
    function loadContacts(clientId, contactSelectId = 'contact_client_select', contactInputId = 'contact_client') {
        const contactSelect = document.getElementById(contactSelectId);
        const contactInput = document.getElementById(contactInputId);

        if (!contactSelect) return;

        if (!clientId) {
            contactSelect.innerHTML = '<option value="">Sélectionner un contact existant</option>';
            return;
        }

        contactSelect.innerHTML = '<option value="">Chargement des contacts...</option>';

        fetch(`${window.BASE_URL}interventions/getContacts/${clientId}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then(response => response.json())
            .then(contacts => {
                contactSelect.innerHTML = '<option value="">Sélectionner un contact existant</option>';

                if (contacts && contacts.length > 0) {
                    contacts.forEach(contact => {
                        const option = document.createElement('option');
                        option.value = contact.email;
                        option.textContent = `${contact.first_name} ${contact.last_name} (${contact.email})`;
                        contactSelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                contactSelect.innerHTML = '<option value="">Erreur de chargement</option>';
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const saveButton = document.getElementById('saveButton');
        const form = document.getElementById('interventionForm');

        if (saveButton) {
            saveButton.addEventListener('click', function (e) {
                e.preventDefault();
                form.submit();
            });
        }

        const clientSelect = document.getElementById('client_id');
        const siteSelect = document.getElementById('site_id');
        const buildingSelect = document.getElementById('building_id');
        const roomSelect = document.getElementById('room_id');
        const contractSelect = document.getElementById('contract_id');

        const currentSiteId = '<?php echo $intervention['site_id'] ?? ''; ?>';
        const currentSiteName = '<?php echo addslashes($intervention['site_name'] ?? ''); ?>';
        const currentBuildingId = '<?php echo $intervention['building_id'] ?? ''; ?>';
        const currentRoomId = '<?php echo $intervention['room_id'] ?? ''; ?>';
        const currentContractId = '<?php echo $intervention['contract_id'] ?? ''; ?>';

        if (clientSelect) {
            if (clientSelect.value) {
                loadSites(clientSelect.value, 'site_id', currentSiteId, currentSiteName, function () {
                    if (siteSelect && siteSelect.value) {
                        loadBuildings(siteSelect.value, 'building_id', currentBuildingId, function () {
                            if (buildingSelect && buildingSelect.value) {
                                loadRoomsByBuilding(buildingSelect.value, 'room_id', currentRoomId, function () {
                                    updateSelectedContract('client_id', 'site_id', 'building_id', 'room_id', 'contract_id', currentContractId);
                                });
                            }
                        });
                    }
                });
            }

            clientSelect.addEventListener('change', function () {
                loadSites(this.value, 'site_id', null, null, function () {
                    buildingSelect.innerHTML = '<option value="">Sélectionner un bâtiment</option>';
                    roomSelect.innerHTML = '<option value="">Sélectionner une salle</option>';
                    updateSelectedContract('client_id', 'site_id', 'building_id', 'room_id', 'contract_id');
                });
            });
        }

        if (siteSelect) {
            siteSelect.addEventListener('change', function () {
                loadBuildings(this.value, 'building_id', null, function () {
                    roomSelect.innerHTML = '<option value="">Sélectionner une salle</option>';
                    updateSelectedContract('client_id', 'site_id', 'building_id', 'room_id', 'contract_id');
                });
            });
        }

        if (buildingSelect) {
            buildingSelect.addEventListener('change', function () {
                loadRoomsByBuilding(this.value, 'room_id', null, function () {
                    updateSelectedContract('client_id', 'site_id', 'building_id', 'room_id', 'contract_id');
                });
            });
        }

        if (roomSelect) {
            roomSelect.addEventListener('change', function () {
                updateSelectedContract('client_id', 'site_id', 'building_id', 'room_id', 'contract_id');
            });
        }

        // Charger les contacts
        const contactClientSelect = document.getElementById('contact_client_select');
        const contactClientInput = document.getElementById('contact_client');

        if (clientSelect && contactClientSelect) {
            clientSelect.addEventListener('change', function () {
                loadContacts(this.value);
            });

            if (clientSelect.value && typeof loadContacts === 'function') {
                loadContacts(clientSelect.value);
            }
        }

        if (contactClientSelect && contactClientInput) {
            contactClientSelect.addEventListener('change', function () {
                if (this.value) {
                    contactClientInput.value = this.value;
                }
            });
        }
    });
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?>