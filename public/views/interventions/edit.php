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
    'Intervention',
    'intervention'
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
    <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Gestion des Interventions</h4></div>

    <div class="ms-auto p-2 bd-highlight">
        

   
        <a href="<?php echo BASE_URL; ?>interventions/view/<?php echo $intervention['id']; ?>" class="btn btn-secondary me-2">
            <i class="bi bi-arrow-left me-1"></i> Retour
        </a>

        
        <button type="button" id="saveButton" class="btn btn-primary">Enregistrer les modifications</button>

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
        <!-- Formulaire de modification -->
        <div class="card">
            <div class="card-header py-2">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="card-title mb-0">
                            <span class="fw-bold me-3"><?= h($intervention['reference'] ?? '') ?></span>
                            <input type="text" class="form-control d-inline-block bg-body text-body" id="title" name="title" value="<?= h($intervention['title'] ?? '') ?>" required>
                        </h5>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label fw-bold mb-0 text-white">Date de création</label>
                                <input type="date" class="form-control bg-body text-body" id="created_date" name="created_date" value="<?= date('Y-m-d', strtotime($intervention['created_at'])) ?>" form="interventionForm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold mb-0 text-white">Heure de création</label>
                                <input type="time" class="form-control bg-body text-body" id="created_time" name="created_time" value="<?= date('H:i', strtotime($intervention['created_at'])) ?>" form="interventionForm">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body py-2">
                <form action="<?php echo BASE_URL; ?>interventions/update/<?php echo $intervention['id']; ?>" method="post" id="interventionForm">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <!-- Colonne 1 : Client, Site, Salle -->
                        <div class="col-md-3">
                            <div class="d-flex flex-column gap-2">
                                <!-- Client -->
                            <div>
                                <label class="form-label fw-bold mb-0">Client *</label>
                                <div class="input-group">
                                    <select class="form-select bg-body text-body" id="client_id" name="client_id" required>
                                        <option value="">Sélectionner un client</option>
                                        <?php foreach ($clients as $client): ?>
                                            <option value="<?= $client['id'] ?>" <?= $client['id'] == $intervention['client_id'] ? 'selected' : '' ?>>
                                                <?= h($client['name'] ?? '') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="quickCreateClientBtn" title="Créer un nouveau client">
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
                                                    <?= h($site['name'] ?? '') ?><?= $site['status'] == 0 ? ' (Site désactivé)' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                            <?php if ($intervention['site_id'] && $intervention['site_id'] !== '0' && !in_array($intervention['site_id'], array_column($sites, 'id'))): ?>
                                                <option value="<?= $intervention['site_id'] ?>" selected style="display: none;">
                                                    <?= h($intervention['site_name'] ?? 'Site inconnu') ?>
                                                </option>
                                            <?php endif; ?>
                                        </select>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="quickCreateSiteBtn" title="Créer un nouveau site">
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
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="quickCreateRoomBtn" title="Créer une nouvelle salle">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Colonne 2 : Type, Déplacement, Contrat -->
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

                                <!-- Déplacement -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Déplacement</label>
                                    <select class="form-select bg-body text-body" id="type_requires_travel" name="type_requires_travel">
                                        <option value="0" <?php echo (!isset($intervention['type_requires_travel']) || $intervention['type_requires_travel'] == 0) ? 'selected' : ''; ?>>Non</option>
                                        <option value="1" <?php echo (isset($intervention['type_requires_travel']) && $intervention['type_requires_travel'] == 1) ? 'selected' : ''; ?>>Oui</option>
                                    </select>
                                </div>

                                <!-- Contrat -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Contrat associé *</label>
                                    <select class="form-select bg-body text-body" id="contract_id" name="contract_id" required>
                                        <option value="">Sélectionner un contrat</option>
                                        <?php foreach ($contracts as $contract): ?>
                                            <option value="<?= $contract['id'] ?>" <?= $contract['id'] == $intervention['contract_id'] ? 'selected' : '' ?>>
                                                <?= h($contract['name'] ?? '') ?> (<?= h($contract['contract_type_name'] ?? '') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Colonne 3 : Statut, Priorité, Technicien -->
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
                                        <?php foreach ($priorities as $priority): ?>
                                            <option value="<?= $priority['id'] ?>" <?= $priority['id'] == $intervention['priority_id'] ? 'selected' : '' ?>>
                                                <?= h($priority['name'] ?? '') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Technicien -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Technicien</label>
                                    <select class="form-select bg-body text-body" id="technician_id" name="technician_id">
                                        <option value="">Sélectionner un technicien</option>
                                        <?php foreach ($technicians as $technician): ?>
                                            <option value="<?= $technician['id'] ?>" <?= $technician['id'] == $intervention['technician_id'] ? 'selected' : '' ?>>
                                                <?= h($technician['first_name'] ?? '') ?> <?= h($technician['last_name'] ?? '') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Colonne 4 : Date planifiée, Heure planifiée, Durée -->
                        <div class="col-md-3">
                            <div class="d-flex flex-column gap-2">
                                <!-- Date planifiée -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Date planifiée</label>
                                    <input type="date" class="form-control bg-body text-body" id="date_planif" name="date_planif" 
                                           value="<?= !empty($intervention['date_planif']) ? date('Y-m-d', strtotime($intervention['date_planif'])) : '' ?>">
                                </div>

                                <!-- Heure planifiée -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Heure planifiée</label>
                                    <input type="time" class="form-control bg-body text-body" id="heure_planif" name="heure_planif" 
                                           value="<?= h($intervention['heure_planif'] ?? '') ?>">
                                </div>
                                
                                <!-- Durée -->
                                <div>
                                    <label class="form-label fw-bold mb-0">Durée</label>
                                    <select class="form-select bg-body text-body" id="duration" name="duration">
                                        <option value="">Sélectionner une durée</option>
                                        <?php foreach ($durations as $duration): ?>
                                            <option value="<?= $duration['duration'] ?>" <?= $duration['duration'] == $intervention['duration'] ? 'selected' : '' ?>>
                                                <?= number_format($duration['duration'], 2) ?> heure(s)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Description sur une ligne complète -->
                        <div class="col-12 mt-3">
                            <div class="card">
                            <div class="card-header py-2">
                                <h6 class="card-title mb-0">Demande/description du problème</h6>
                            </div>
                                <div class="card-body py-2">
                                    <textarea class="form-control bg-body text-body" id="description" name="description" rows="5"><?php echo h($intervention['description'] ?? ''); ?></textarea>
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
                                            <input type="text" class="form-control bg-body text-body" id="demande_par" name="demande_par" value="<?php echo h($intervention['demande_par'] ?? ''); ?>" placeholder="Nom de la personne qui a demandé l'intervention">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Référence client</label>
                                            <input type="text" class="form-control bg-body text-body" id="ref_client" name="ref_client" value="<?= h($intervention['ref_client'] ?? '') ?>" placeholder="Référence interne du client">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Contact existant</label>
                                            <div class="input-group">
                                                <select class="form-select bg-body text-body" id="contact_client_select" name="contact_client_select">
                                                    <option value="">Sélectionner un contact existant</option>
                                                    <!-- Les contacts seront chargés dynamiquement selon le client -->
                                                </select>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" id="quickCreateContactBtn" title="Créer un nouveau contact">
                                                    <i class="bi bi-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Ou saisir un email</label>
                                            <input type="email" class="form-control bg-body text-body" id="contact_client" name="contact_client" value="<?php echo h($intervention['contact_client'] ?? ''); ?>" placeholder="email@exemple.com">
                                            <div class="invalid-feedback" id="email-error"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                        <!-- Le bouton submit est maintenant en haut de la page -->
                    </div>
                </form>
            </div>
        </div>

        <!-- Espace entre le formulaire et les sections -->
        <div class="mb-4"></div>

<!-- Modale de création rapide de client -->
<div class="modal fade" id="quickCreateClientModal" tabindex="-1" aria-labelledby="quickCreateClientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickCreateClientModalLabel">
                    <i class="bi bi-person-plus me-2"></i>Créer un nouveau client
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="quickCreateClientForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Nom du client *</label>
                            <input type="text" class="form-control" id="client_name" name="name" required placeholder="Nom de l'entreprise">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" class="form-control" id="client_email" name="email" placeholder="contact@entreprise.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Téléphone</label>
                            <input type="tel" class="form-control" id="client_phone" name="phone" placeholder="01 23 45 67 89">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Site web</label>
                            <input type="url" class="form-control" id="client_website" name="website" placeholder="https://www.entreprise.com">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Adresse</label>
                            <input type="text" class="form-control" id="client_address" name="address" placeholder="123 Rue de la Paix">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Code postal</label>
                            <input type="text" class="form-control" id="client_postal_code" name="postal_code" placeholder="75001">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Ville</label>
                            <input type="text" class="form-control" id="client_city" name="city" placeholder="Paris">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Commentaire</label>
                            <textarea class="form-control" id="client_comment" name="comment" rows="3" placeholder="Commentaires ou notes sur ce client..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="saveQuickClientBtn">
                    <span class="spinner-border spinner-border-sm d-none" id="clientSpinner"></span>
                    <i class="bi bi-check-lg me-1" id="clientIcon"></i>
                    Créer le client
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modale de création rapide de site -->
<div class="modal fade" id="quickCreateSiteModal" tabindex="-1" aria-labelledby="quickCreateSiteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickCreateSiteModalLabel">
                    <i class="bi bi-building me-2"></i>Créer un nouveau site
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="quickCreateSiteForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Nom du site *</label>
                            <input type="text" class="form-control" id="site_name" name="name" required placeholder="Nom du site">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Adresse</label>
                            <input type="text" class="form-control" id="site_address" name="address" placeholder="123 Rue de la Paix">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Code postal</label>
                            <input type="text" class="form-control" id="site_postal_code" name="postal_code" placeholder="75001">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Ville</label>
                            <input type="text" class="form-control" id="site_city" name="city" placeholder="Paris">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Téléphone</label>
                            <input type="tel" class="form-control" id="site_phone" name="phone" placeholder="01 23 45 67 89">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" class="form-control" id="site_email" name="email" placeholder="contact@site.com">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Commentaire</label>
                            <textarea class="form-control" id="site_comment" name="comment" rows="2" placeholder="Commentaires sur ce site..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="saveQuickSiteBtn">
                    <span class="spinner-border spinner-border-sm d-none" id="siteSpinner"></span>
                    <i class="bi bi-check-lg me-1" id="siteIcon"></i>
                    Créer le site
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modale de création rapide de salle -->
<div class="modal fade" id="quickCreateRoomModal" tabindex="-1" aria-labelledby="quickCreateRoomModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickCreateRoomModalLabel">
                    <i class="bi bi-door-open me-2"></i>Créer une nouvelle salle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="quickCreateRoomForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Nom de la salle *</label>
                            <input type="text" class="form-control" id="room_name" name="name" required placeholder="Nom de la salle">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Commentaire</label>
                            <textarea class="form-control" id="room_comment" name="comment" rows="3" placeholder="Commentaires sur cette salle..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="saveQuickRoomBtn">
                    <span class="spinner-border spinner-border-sm d-none" id="roomSpinner"></span>
                    <i class="bi bi-check-lg me-1" id="roomIcon"></i>
                    Créer la salle
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modale de création rapide de contact -->
<div class="modal fade" id="quickCreateContactModal" tabindex="-1" aria-labelledby="quickCreateContactModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickCreateContactModalLabel">
                    <i class="bi bi-person-plus me-2"></i>Créer un nouveau contact
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="quickCreateContactForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Prénom *</label>
                            <input type="text" class="form-control" id="contact_first_name" name="first_name" required placeholder="Prénom">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nom *</label>
                            <input type="text" class="form-control" id="contact_last_name" name="last_name" required placeholder="Nom">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" class="form-control" id="contact_email" name="email" placeholder="contact@exemple.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Téléphone 1</label>
                            <input type="tel" class="form-control" id="contact_phone1" name="phone1" placeholder="01 23 45 67 89">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Téléphone 2</label>
                            <input type="tel" class="form-control" id="contact_phone2" name="phone2" placeholder="01 23 45 67 89">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Fonction</label>
                            <input type="text" class="form-control" id="contact_fonction" name="fonction" placeholder="Directeur, Responsable IT, etc.">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Commentaire</label>
                            <textarea class="form-control" id="contact_comment" name="comment" rows="2" placeholder="Commentaires sur ce contact..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="saveQuickContactBtn">
                    <span class="spinner-border spinner-border-sm d-none" id="contactSpinner"></span>
                    <i class="bi bi-check-lg me-1" id="contactIcon"></i>
                    Créer le contact
                </button>
            </div>
        </div>
    </div>
</div>

        <!-- Section Commentaires et Pièces jointes -->
        <div class="row">
            <!-- Section Commentaires -->
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Compte-rendu/observations</h5>
                        <?php if (canModifyInterventions() && $intervention['status_id'] != 6): ?>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCommentModal">
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
                                                   class="btn btn-sm btn-outline-danger btn-action" 
                                                   title="Supprimer"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?');">
                                                    <i class="<?php echo getIcon('delete', 'bi bi-trash'); ?>"></i>
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
                            // Trier les pièces jointes pour mettre les bons d'intervention en premier
                            usort($attachments, function($a, $b) {
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
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-info btn-action" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#previewModal<?= $attachment['id'] ?>"
                                                    title="Aperçu">
                                                    <i class="<?php echo getIcon('visibility', 'bi bi-eye'); ?>"></i>
                                            </button>
                                            <a href="<?php echo BASE_URL; ?>interventions/download/<?php echo $attachment['id']; ?>" 
                                               class="btn btn-sm btn-outline-success btn-action" 
                                               title="Télécharger">
                                                <i class="<?php echo getIcon('download', 'bi bi-download'); ?>"></i>
                                            </a>
                                            <?php if (canDelete()): ?>
                                                <a href="<?php echo BASE_URL; ?>interventions/deleteAttachment/<?php echo $attachment['id']; ?>" 
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
                                            <?php if ($isBI): ?>
                                                <i class="bi bi-file-pdf text-danger me-2 me-1"></i>
                                                <span class="badge bg-info me-2">BI</span>
                                            <?php elseif ($isPdf): ?>
                                                <i class="bi bi-file-pdf text-danger me-2 me-1"></i>
                                            <?php elseif ($isImage): ?>
                                                <i class="bi bi-image-fill text-primary me-2 me-1"></i>
                                            <?php elseif ($isExcel): ?>
                                                <i class="bi bi-file-spreadsheet text-success me-2 me-1"></i>
                                            <?php else: ?>
                                                <i class="bi bi-file-earmark text-secondary me-2 me-1"></i>
                                            <?php endif; ?>
                                            <div class="attachment-name flex-grow-1">
                                                <div class="display-name"><?php echo h($attachment['nom_fichier']); ?></div>
                                                <?php if (!empty($attachment['nom_personnalise']) && $attachment['nom_personnalise'] !== $attachment['nom_fichier']): ?>
                                                    <div class="original-name text-muted small"><?php echo h($attachment['nom_personnalise']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (canModifyInterventions() && $intervention['status_id'] != 6): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary me-2" 
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
                                                            <div class="original-name text-muted small"><?= h($attachment['nom_personnalise']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="preview-container">
                                                    <?php 
                                                    $extension = strtolower(pathinfo($attachment['nom_fichier'], PATHINFO_EXTENSION));
                                                    if ($extension === 'pdf'): 
                                                    ?>
                                                        <iframe src="<?= BASE_URL; ?>interventions/preview/<?= $attachment['id'] ?>" 
                                                                width="100%" 
                                                                height="600px" 
                                                                frameborder="0">
                                                        </iframe>
                                                    <?php elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                        <img src="<?= BASE_URL; ?>interventions/preview/<?= $attachment['id'] ?>" 
                                                             class="img-fluid" 
                                                             alt="<?= h($attachment['nom_fichier']) ?>">
                                                    <?php else: ?>
                                                        <div class="alert alert-info">
                                                            <i class="bi bi-info-circle me-1"></i> 
                                                            Ce type de fichier ne peut pas être prévisualisé. 
                                                            <a href="<?= BASE_URL; ?>interventions/download/<?= $attachment['id'] ?>" 
                                                               class="alert-link" 
                                                               target="_blank">
                                                                Télécharger le fichier
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <a href="<?= BASE_URL; ?>interventions/download/<?= $attachment['id'] ?>" 
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
    <?php else: ?>
        <div class="alert alert-danger">
            Intervention introuvable.
        </div>
    <?php endif; ?>
</div>

<!-- Modal Ajout de commentaire -->
<div class="modal fade" id="addCommentModal" tabindex="-1" aria-labelledby="addCommentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?php echo BASE_URL; ?>interventions/addComment/<?php echo $intervention['id']; ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="addCommentModalLabel">Ajouter un commentaire</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="comment" class="form-label">Commentaire</label>
                        <textarea class="form-control" id="comment" name="comment" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="visible_by_client" name="visible_by_client">
                            <label class="form-check-label" for="visible_by_client">
                                Visible par le client
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_solution" name="is_solution">
                            <label class="form-check-label" for="is_solution">
                                Marquer comme solution
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

<!-- Modal Ajout de pièce jointe -->
<div class="modal fade" id="addAttachmentModal" tabindex="-1" aria-labelledby="addAttachmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?php echo BASE_URL; ?>interventions/addAttachment/<?php echo $intervention['id']; ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="addAttachmentModalLabel">Ajouter une pièce jointe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="attachment" class="form-label">Fichier</label>
                        <input type="file" class="form-control" id="attachment" name="attachment" accept="<?= FileUploadValidator::getAcceptAttribute($GLOBALS['db']) ?>" required>
                        <div class="form-text">
                            Formats acceptés : Images, documents, archives et fichiers texte<br>
                            Taille maximale : <?php echo ini_get('upload_max_filesize'); ?>
                        </div>
                        <div id="attachment-error" class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="custom_name" class="form-label">Nom du fichier (optionnel)</label>
                        <input type="text" class="form-control" id="custom_name" name="custom_name" placeholder="Nom personnalisé pour ce fichier" maxlength="255">
                        <div class="form-text">
                            Si laissé vide, le nom original du fichier sera utilisé
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="addAttachmentModal-submit">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal d'édition du nom de pièce jointe -->
<div class="modal fade" id="editAttachmentNameModal" tabindex="-1" aria-labelledby="editAttachmentNameModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editAttachmentNameForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAttachmentNameModalLabel">
                        <i class="bi bi-pencil-square me-2"></i>
                        Modifier le nom du fichier
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editAttachmentName" class="form-label">Nom du fichier</label>
                        <input type="text" class="form-control" id="editAttachmentName" name="nom_fichier" 
                               placeholder="Nom personnalisé pour ce fichier" maxlength="255" required>
                        <div class="form-text">
                            Le nom original du fichier sera conservé pour référence.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nom original</label>
                        <div class="form-control-plaintext text-muted small" id="editOriginalName">
                            <!-- Sera rempli par JavaScript -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>
                        Sauvegarder
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal d'information sur la gestion des tickets -->
<div class="modal fade" id="ticketManagementInfoModal" tabindex="-1" aria-labelledby="ticketManagementInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ticketManagementInfoModalLabel">
                    <i class="bi bi-info-circle me-2"></i>
                    Gestion automatique des tickets
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <h6><i class="bi bi-lightbulb me-2"></i>Information importante</h6>
                    <p class="mb-0">
                        Cette intervention est fermée et des tickets ont déjà été déduits. 
                        Si vous changez le contrat associé, la gestion des tickets se fera automatiquement :
                    </p>
                </div>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <i class="bi bi-arrow-right-circle text-primary me-2"></i>
                        <strong>Contrat à tickets → Contrat à tickets :</strong> Les tickets seront transférés automatiquement
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-arrow-right-circle text-success me-2"></i>
                        <strong>Contrat à tickets → Contrat sans tickets :</strong> Les tickets seront recrédités à l'ancien contrat
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-arrow-right-circle text-warning me-2"></i>
                        <strong>Contrat sans tickets → Contrat à tickets :</strong> Les tickets seront déduits du nouveau contrat
                    </li>
                </ul>
                <div class="alert alert-warning">
                    <small>
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Toutes les modifications de tickets sont enregistrées dans l'historique des contrats.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Compris</button>
            </div>
        </div>
    </div>
</div>

<!-- Script pour mettre à jour les sites et les salles en fonction du client sélectionné -->
<script>
    // Initialiser BASE_URL pour JavaScript
    initBaseUrl('<?php echo BASE_URL; ?>');
    
    document.addEventListener('DOMContentLoaded', function() {
        // Vérifier les permissions pour la création rapide
        const canModifyClients = <?php echo canModifyClients() ? 'true' : 'false'; ?>;
        const clientSelect = document.getElementById('client_id');
        const siteSelect = document.getElementById('site_id');
        const roomSelect = document.getElementById('room_id');
        const typeSelect = document.getElementById('type_id');
        const typeRequiresTravelSelect = document.getElementById('type_requires_travel');
        const contractSelect = document.getElementById('contract_id');

        // Helpers utilisés par les modales (déclarés tôt pour être disponibles partout)
        const isValidEmailFormat = (email) => {
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            return emailRegex.test(email);
        };

        const isValidWebsiteUrl = (website) => {
            try {
                const url = new URL(website);
                return url.protocol === 'http:' || url.protocol === 'https:';
            } catch {
                return false;
            }
        };

        const showSuccessMessage = (message) => {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed';
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                <i class="bi bi-check-circle me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            setTimeout(() => { if (alertDiv.parentNode) { alertDiv.remove(); } }, 3000);
        };

        // Gestion de la création rapide de client
        const quickCreateClientBtn = document.getElementById('quickCreateClientBtn');
        const quickCreateClientModal = new bootstrap.Modal(document.getElementById('quickCreateClientModal'));
        const saveQuickClientBtn = document.getElementById('saveQuickClientBtn');
        const quickCreateClientForm = document.getElementById('quickCreateClientForm');
        const clientSpinner = document.getElementById('clientSpinner');
        const clientIcon = document.getElementById('clientIcon');

        // Debug: vérifier si les éléments existent
        /* debug removed: elements presence */ /*
            quickCreateClientBtn: !!quickCreateClientBtn,
            quickCreateClientModal: !!quickCreateClientModal,
            saveQuickClientBtn: !!saveQuickClientBtn,
            quickCreateClientForm: !!quickCreateClientForm
        });*/

        // (Supprimé) Attache anticipée: provoquait une erreur d'ordre d'initialisation

        

        // Gestion de la création rapide de site
        
        const quickCreateSiteBtn = document.getElementById('quickCreateSiteBtn');
        const quickCreateSiteModal = new bootstrap.Modal(document.getElementById('quickCreateSiteModal'));
        const saveQuickSiteBtn = document.getElementById('saveQuickSiteBtn');
        const quickCreateSiteForm = document.getElementById('quickCreateSiteForm');
        const siteSpinner = document.getElementById('siteSpinner');
        const siteIcon = document.getElementById('siteIcon');

        // Gestion de la création rapide de salle
        
        const quickCreateRoomBtn = document.getElementById('quickCreateRoomBtn');
        const quickCreateRoomModal = new bootstrap.Modal(document.getElementById('quickCreateRoomModal'));
        const saveQuickRoomBtn = document.getElementById('saveQuickRoomBtn');
        const quickCreateRoomForm = document.getElementById('quickCreateRoomForm');
        const roomSpinner = document.getElementById('roomSpinner');
        const roomIcon = document.getElementById('roomIcon');

        // Gestion de la création rapide de contact
        
        const quickCreateContactBtn = document.getElementById('quickCreateContactBtn');
        const quickCreateContactModal = new bootstrap.Modal(document.getElementById('quickCreateContactModal'));
        const saveQuickContactBtn = document.getElementById('saveQuickContactBtn');
        const quickCreateContactForm = document.getElementById('quickCreateContactForm');
        const contactSpinner = document.getElementById('contactSpinner');
        const contactIcon = document.getElementById('contactIcon');
        
        
        
        // Attacher les gestionnaires de clic des 4 boutons + (modales)
        if (quickCreateClientBtn) {
            quickCreateClientBtn.addEventListener('click', function() {
                if (!canModifyClients) {
                    alert('Vous n\'avez pas les permissions nécessaires pour créer un client.');
                    return;
                }
                quickCreateClientForm.reset();
                quickCreateClientModal.show();
            });
        }

        if (quickCreateSiteBtn) {
            quickCreateSiteBtn.addEventListener('click', function() {
                if (!canModifyClients) {
                    alert('Vous n\'avez pas les permissions nécessaires pour créer un site.');
                    return;
                }
                const selectedClientId = clientSelect.value;
                if (!selectedClientId) {
                    alert('Veuillez d\'abord sélectionner un client avant de créer un site.');
                    clientSelect.focus();
                    return;
                }
                quickCreateSiteForm.reset();
                quickCreateSiteModal.show();
            });
        }

        if (quickCreateRoomBtn) {
            quickCreateRoomBtn.addEventListener('click', function() {
                if (!canModifyClients) {
                    alert('Vous n\'avez pas les permissions nécessaires pour créer une salle.');
                    return;
                }
                const selectedSiteId = siteSelect.value;
                if (!selectedSiteId) {
                    alert('Veuillez d\'abord sélectionner un site avant de créer une salle.');
                    siteSelect.focus();
                    return;
                }
                quickCreateRoomForm.reset();
                quickCreateRoomModal.show();
            });
        }

        if (quickCreateContactBtn) {
            quickCreateContactBtn.addEventListener('click', function() {
                if (!canModifyClients) {
                    alert('Vous n\'avez pas les permissions nécessaires pour créer un contact.');
                    return;
                }
                const selectedClientId = clientSelect.value;
                if (!selectedClientId) {
                    alert('Veuillez d\'abord sélectionner un client avant de créer un contact.');
                    clientSelect.focus();
                    return;
                }
                quickCreateContactForm.reset();
                quickCreateContactModal.show();
            });
        }

        // Attacher au plus tôt les gestionnaires de clic des boutons "Enregistrer" des modales
        if (saveQuickClientBtn && !saveQuickClientBtn.dataset.bound) {
            saveQuickClientBtn.dataset.bound = '1';
            saveQuickClientBtn.addEventListener('click', function() {
                
                const formData = new FormData(quickCreateClientForm);
                const clientName = formData.get('name').trim();
                const clientEmail = formData.get('email').trim();
                const clientWebsite = formData.get('website').trim();
                if (!clientName) { alert('Le nom du client est obligatoire'); return; }
                if (clientEmail && !isValidEmailFormat(clientEmail)) { alert('Format d\'email invalide'); return; }
                if (clientWebsite && !isValidWebsiteUrl(clientWebsite)) { alert('Format d\'URL invalide (ex: https://www.exemple.com)'); return; }
                clientSpinner.classList.remove('d-none');
                clientIcon.classList.add('d-none');
                saveQuickClientBtn.disabled = true;
                fetch(`${BASE_URL}interventions/quickCreateClient`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': '<?= csrf_token() ?>'
                    },
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const newOption = document.createElement('option');
                            newOption.value = data.client.id;
                            newOption.textContent = data.client.name;
                            newOption.selected = true;
                            clientSelect.appendChild(newOption);
                            quickCreateClientModal.hide();
                            clientSelect.dispatchEvent(new Event('change'));
                            showSuccessMessage(data.message);
                        } else {
                            alert('Erreur : ' + (data.error || 'Une erreur est survenue'));
                        }
                    })
                    .catch(() => { alert('Une erreur est survenue lors de la création du client'); })
                    .finally(() => { clientSpinner.classList.add('d-none'); clientIcon.classList.remove('d-none'); saveQuickClientBtn.disabled = false; });
            });
        }

        if (saveQuickSiteBtn && !saveQuickSiteBtn.dataset.bound) {
            saveQuickSiteBtn.dataset.bound = '1';
            saveQuickSiteBtn.addEventListener('click', function() {
                
                const formData = new FormData(quickCreateSiteForm);
                const selectedClientId = clientSelect.value;
                formData.append('client_id', selectedClientId);
                const siteName = formData.get('name').trim();
                const siteEmail = formData.get('email').trim();
                if (!siteName) { alert('Le nom du site est obligatoire'); return; }
                if (!selectedClientId) { alert('Aucun client sélectionné'); return; }
                if (siteEmail && !isValidEmailFormat(siteEmail)) { alert('Format d\'email invalide'); return; }
                siteSpinner.classList.remove('d-none');
                siteIcon.classList.add('d-none');
                saveQuickSiteBtn.disabled = true;
                fetch(`${BASE_URL}interventions/quickCreateSite`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': '<?= csrf_token() ?>'
                    },
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const newOption = document.createElement('option');
                            newOption.value = data.site.id;
                            newOption.textContent = data.site.name;
                            newOption.selected = true;
                            siteSelect.appendChild(newOption);
                            quickCreateSiteModal.hide();
                            siteSelect.dispatchEvent(new Event('change'));
                            showSuccessMessage(data.message);
                        } else {
                            alert('Erreur : ' + (data.error || 'Une erreur est survenue'));
                        }
                    })
                    .catch(() => { alert('Une erreur est survenue lors de la création du site'); })
                    .finally(() => { siteSpinner.classList.add('d-none'); siteIcon.classList.remove('d-none'); saveQuickSiteBtn.disabled = false; });
            });
        }

        if (saveQuickRoomBtn && !saveQuickRoomBtn.dataset.bound) {
            saveQuickRoomBtn.dataset.bound = '1';
            saveQuickRoomBtn.addEventListener('click', function() {
                
                const formData = new FormData(quickCreateRoomForm);
                const selectedSiteId = siteSelect.value;
                const selectedClientId = clientSelect.value;
                formData.append('site_id', selectedSiteId);
                formData.append('client_id', selectedClientId);
                const roomName = formData.get('name').trim();
                if (!roomName) { alert('Le nom de la salle est obligatoire'); return; }
                if (!selectedSiteId) { alert('Aucun site sélectionné'); return; }
                if (!selectedClientId) { alert('Aucun client sélectionné'); return; }
                roomSpinner.classList.remove('d-none');
                roomIcon.classList.add('d-none');
                saveQuickRoomBtn.disabled = true;
                fetch(`${BASE_URL}interventions/quickCreateRoom`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': '<?= csrf_token() ?>'
                    },
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const newOption = document.createElement('option');
                            newOption.value = data.room.id;
                            newOption.textContent = data.room.name;
                            newOption.selected = true;
                            roomSelect.appendChild(newOption);
                            quickCreateRoomModal.hide();
                            roomSelect.dispatchEvent(new Event('change'));
                            showSuccessMessage(data.message);
                        } else {
                            alert('Erreur : ' + (data.error || 'Une erreur est survenue'));
                        }
                    })
                    .catch(() => { alert('Une erreur est survenue lors de la création de la salle'); })
                    .finally(() => { roomSpinner.classList.add('d-none'); roomIcon.classList.remove('d-none'); saveQuickRoomBtn.disabled = false; });
            });
        }

        if (saveQuickContactBtn && !saveQuickContactBtn.dataset.bound) {
            saveQuickContactBtn.dataset.bound = '1';
            saveQuickContactBtn.addEventListener('click', function() {
                
                const formData = new FormData(quickCreateContactForm);
                const selectedClientId = clientSelect.value;
                formData.append('client_id', selectedClientId);
                const firstName = formData.get('first_name').trim();
                const lastName = formData.get('last_name').trim();
                const email = formData.get('email').trim();
                if (!firstName) { alert('Le prénom est obligatoire'); return; }
                if (!lastName) { alert('Le nom est obligatoire'); return; }
                if (!selectedClientId) { alert('Aucun client sélectionné'); return; }
                if (email && !isValidEmailFormat(email)) { alert('Format d\'email invalide'); return; }
                contactSpinner.classList.remove('d-none');
                contactIcon.classList.add('d-none');
                saveQuickContactBtn.disabled = true;
                fetch(`${BASE_URL}interventions/quickCreateContact`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': '<?= csrf_token() ?>'
                    },
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const newOption = document.createElement('option');
                            newOption.value = data.contact.email;
                            newOption.textContent = `${data.contact.first_name} ${data.contact.last_name} (${data.contact.email})`;
                            newOption.selected = true;
                            document.getElementById('contact_client_select').appendChild(newOption);
                            quickCreateContactModal.hide();
                            showSuccessMessage(data.message);
                        } else {
                            alert('Erreur : ' + (data.error || 'Une erreur est survenue'));
                        }
                    })
                    .catch(() => { alert('Une erreur est survenue lors de la création du contact'); })
                    .finally(() => { contactSpinner.classList.add('d-none'); contactIcon.classList.remove('d-none'); saveQuickContactBtn.disabled = false; });
            });
        }
        
        // Utiliser les fonctions centralisées pour charger les sites et salles dynamiquement
        
        try {
            clientSelect.addEventListener('change', function() {
                loadSites(this.value, 'site_id', null, null, function() {
                    updateSelectedContract('client_id', 'site_id', 'room_id', 'contract_id', '<?php echo $intervention['contract_id'] ?? ''; ?>');
                });
            });
        } catch (error) {
            /* noop */
        }
        
        siteSelect.addEventListener('change', function() {
            loadRooms(this.value, 'room_id', null, function() {
                updateSelectedContract('client_id', 'site_id', 'room_id', 'contract_id', '<?php echo $intervention['contract_id'] ?? ''; ?>');
            });
        });
        
        roomSelect.addEventListener('change', function() {
            updateSelectedContract('client_id', 'site_id', 'room_id', 'contract_id', '<?php echo $intervention['contract_id'] ?? ''; ?>');
            // Pré-sélectionner le contrat associé à la salle sélectionnée
            const roomId = this.value;
            if (roomId) {
                fetch(`${BASE_URL}interventions/getContractByRoom/${roomId}`)
                    .then(response => response.json())
                    .then(contract => {
                        if (contract && contract.id) {
                            setTimeout(() => {
                                const option = contractSelect.querySelector(`option[value="${contract.id}"]`);
                                if (option) {
                                    option.selected = true;
                                    // Vérifier si on doit afficher la modal d'information sur les tickets
                                    if (typeof checkAndShowTicketManagementModal === 'function') {
                                        checkAndShowTicketManagementModal();
                                    }
                                }
                            }, 100);
                        }
                    })
                    .catch(() => {/* noop */});
            }
        });
        
        typeSelect.addEventListener('change', function() {
            updateTypeRequiresTravel('type_id', 'type_requires_travel', 'type_requires_travel');
        });

        // Gestion du changement de contrat pour les interventions fermées
        contractSelect.addEventListener('change', function() {
            checkAndShowTicketManagementModal();
        });

        // Gestion des contacts clients
        const contactClientSelect = document.getElementById('contact_client_select');
        const contactClientInput = document.getElementById('contact_client');
        
        // Charger les contacts quand le client change
        clientSelect.addEventListener('change', function() {
            loadContacts(this.value);
        });
        
        // Quand on sélectionne un contact existant, remplir le champ email
        contactClientSelect.addEventListener('change', function() {
            if (this.value) {
                contactClientInput.value = this.value;
            }
        });
        
        // Fonction pour charger les contacts d'un client
        let contactsLoading = false;
        let currentContactsRequest = null;
        
        function loadContacts(clientId) {
            if (!clientId) {
                contactClientSelect.innerHTML = '<option value="">Sélectionner un contact existant</option>';
                return;
            }
            
            // Annuler la requête précédente si elle est en cours
            if (currentContactsRequest) {
                // Note: fetch n'a pas de méthode abort() native, mais on peut ignorer la réponse
                contactsLoading = false;
            }
            
            // Éviter les requêtes multiples simultanées
            if (contactsLoading) {
                return;
            }
            
            contactsLoading = true;
            contactClientSelect.disabled = true;
            contactClientSelect.innerHTML = '<option value="">Chargement...</option>';
            
            // Créer un AbortController pour gérer le timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000); // Timeout de 10 secondes
            
            currentContactsRequest = fetch(`${BASE_URL}interventions/getContacts/${clientId}`, {
                signal: controller.signal
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(contacts => {
                    clearTimeout(timeoutId);
                    contactClientSelect.innerHTML = '<option value="">Sélectionner un contact existant</option>';
                    if (contacts && Array.isArray(contacts)) {
                        contacts.forEach(contact => {
                            const option = document.createElement('option');
                            option.value = contact.email;
                            option.textContent = `${contact.first_name} ${contact.last_name} (${contact.email})`;
                            contactClientSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    clearTimeout(timeoutId);
                    if (error.name !== 'AbortError') {
                        console.error('Erreur lors du chargement des contacts:', error);
                        contactClientSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                    } else {
                        contactClientSelect.innerHTML = '<option value="">Timeout - Veuillez réessayer</option>';
                    }
                })
                .finally(() => {
                    contactsLoading = false;
                    contactClientSelect.disabled = false;
                    currentContactsRequest = null;
                });
        }
        
        // Charger les contacts au chargement de la page si un client est déjà sélectionné
        if (clientSelect.value) {
            loadContacts(clientSelect.value);
        }
        
        // Validation de l'email
        const emailError = document.getElementById('email-error');
        
        contactClientInput.addEventListener('input', function() {
            validateEmail(this.value);
        });
        
        contactClientInput.addEventListener('blur', function() {
            validateEmail(this.value);
        });
        
        function validateEmail(email) {
            // Réinitialiser les erreurs
            contactClientInput.classList.remove('is-invalid', 'is-valid');
            emailError.textContent = '';
            
            // Si le champ est vide, pas de validation
            if (!email.trim()) {
                return true;
            }
            
            // Regex pour valider l'email
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            
            if (!emailRegex.test(email)) {
                contactClientInput.classList.add('is-invalid');
                emailError.textContent = 'Format d\'email invalide. Exemple : nom@domaine.com';
                return false;
            } else {
                contactClientInput.classList.add('is-valid');
                return true;
            }
        }
        
        // Validation du formulaire avant soumission
        document.getElementById('interventionForm').addEventListener('submit', function(e) {
            const email = contactClientInput.value.trim();
            if (email && !validateEmail(email)) {
                e.preventDefault();
                contactClientInput.focus();
                return false;
            }
            
            // Vérifier si on passe le statut à "Fermé" (statut 6)
            const statusSelect = document.getElementById('status_id');
            const currentStatus = <?php echo $intervention['status_id']; ?>;
            const newStatus = parseInt(statusSelect.value);
            
            if (newStatus === 6 && currentStatus !== 6) {
                // Vérifier si l'intervention est liée à un contrat à tickets
                const isTicketContract = <?php echo isInterventionLinkedToTicketContract($intervention['id']) ? 'true' : 'false'; ?>;
                
                if (isTicketContract) {
                    // Empêcher la soumission normale
                    e.preventDefault();
                    
                    // Sauvegarder d'abord les modifications (sans le statut)
                    saveInterventionDataBeforeClose();
                }
                // Si ce n'est pas un contrat à tickets, laisser la soumission normale se faire
            }
        });

        // Initialiser la validation de fichiers pour le modal d'ajout de pièce jointe
            // Validation côté client avec récupération des extensions autorisées
    const fileInput = document.getElementById('attachment');
    const fileError = document.getElementById('attachment-error');
    const submitButton = document.getElementById('addAttachmentModal-submit');
    const maxSize = parsePhpSize('<?php echo ini_get("upload_max_filesize"); ?>');
    
    // Récupérer les extensions autorisées
    fetch('<?php echo BASE_URL; ?>settings/getAllowedExtensions')
        .then(response => response.json())
        .then(data => {
            const allowedExtensions = data.extensions || [];
            
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (!file) return;
                
                // Réinitialiser les messages d'erreur
                fileError.textContent = '';
                fileInput.classList.remove('is-invalid');
                submitButton.disabled = false;
                
                // Vérifier la taille du fichier
                if (file.size > maxSize) {
                    fileError.textContent = `Le fichier est trop volumineux (${formatFileSize(file.size)}). Taille maximale autorisée : ${formatFileSize(maxSize)}.`;
                    fileError.style.display = 'block';
                    fileInput.classList.add('is-invalid');
                    submitButton.disabled = true;
                    return;
                }
                
                // Vérifier l'extension du fichier
                const fileName = file.name;
                const fileExtension = fileName.split('.').pop().toLowerCase();
                
                if (!allowedExtensions.includes(fileExtension)) {
                    fileError.textContent = 'Ce format n\'est pas accepté, rapprochez-vous de l\'administrateur du site, ou utilisez un format compressé.';
                    fileError.style.display = 'block';
                    fileInput.classList.add('is-invalid');
                    submitButton.disabled = true;
                    return;
                }
                
                // Fichier valide
                fileError.style.display = 'none';
                fileInput.classList.remove('is-invalid');
                submitButton.disabled = false;
            });
        })
        .catch(() => { /* ignore */ });

        // Initialiser les champs au chargement de la page
        const currentSiteId = '<?php echo $intervention['site_id'] ?? ''; ?>';
        const currentSiteName = '<?php echo h($intervention['site_name'] ?? ''); ?>';
        const currentRoomId = '<?php echo $intervention['room_id'] ?? ''; ?>';
        const clientId = clientSelect.value;
        const siteId = siteSelect.value;
        const typeId = typeSelect.value;

        if (clientId) {
            loadSites(clientId, 'site_id', currentSiteId, currentSiteName, function() {
                updateSelectedContract('client_id', 'site_id', 'room_id', 'contract_id', '<?php echo $intervention['contract_id'] ?? ''; ?>');
                if (siteId) {
                    loadRooms(siteId, 'room_id', currentRoomId);
                }
            });
        }
        if (typeId) {
            updateTypeRequiresTravel('type_id', 'type_requires_travel', 'type_requires_travel');
        }
        // Initialiser la pré-sélection du contrat si une salle est déjà sélectionnée au chargement
        if (roomSelect.value) {
            const roomId = roomSelect.value;
            fetch(`${BASE_URL}interventions/getContractByRoom/${roomId}`)
                .then(response => response.json())
                .then(contract => {
                    if (contract && contract.id) {
                        setTimeout(() => {
                            const option = contractSelect.querySelector(`option[value="${contract.id}"]`);
                            if (option) {
                                option.selected = true;
                            }
                        }, 100);
                    }
                })
                .catch(() => {/* noop */});
        }
    });

    // --- Garder les fonctions spécifiques à la page ---

// Fonction pour éditer le nom d'une pièce jointe
function editAttachmentName(attachmentId, currentName) {
    // Récupérer les informations de la pièce jointe via AJAX
    fetch(`<?php echo BASE_URL; ?>interventions/getAttachmentInfo/${attachmentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remplir la modale
                document.getElementById('editAttachmentName').value = data.attachment.nom_fichier;
                document.getElementById('editOriginalName').textContent = data.attachment.nom_personnalise || data.attachment.nom_fichier;
                
                // Stocker l'ID de la pièce jointe pour la sauvegarde
                document.getElementById('editAttachmentNameForm').setAttribute('data-attachment-id', attachmentId);
                
                // Ouvrir la modale
                const modal = new bootstrap.Modal(document.getElementById('editAttachmentNameModal'));
                modal.show();
            } else {
                alert('Erreur lors du chargement des informations du fichier : ' + (data.error || 'Erreur inconnue'));
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors du chargement des informations du fichier');
        });
}

// Gérer la soumission du formulaire d'édition
document.getElementById('editAttachmentNameForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const attachmentId = this.getAttribute('data-attachment-id');
    const newName = document.getElementById('editAttachmentName').value.trim();
    
    if (!newName) {
        alert('Le nom du fichier ne peut pas être vide');
        return;
    }
    
    // Désactiver le bouton de soumission
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-1"></i>Sauvegarde...';
    
    // Envoyer la requête
    fetch(`<?php echo BASE_URL; ?>interventions/updateAttachmentName/${attachmentId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            nom_fichier: newName
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Fermer la modale
            const modal = bootstrap.Modal.getInstance(document.getElementById('editAttachmentNameModal'));
            modal.hide();
            
            // Recharger la page pour afficher les changements
            window.location.reload();
        } else {
            alert('Erreur lors de la sauvegarde : ' + (data.error || 'Erreur inconnue'));
        }
    })
    .catch(() => { alert('Erreur lors de la sauvegarde du nom'); })
    .finally(() => {
        // Réactiver le bouton
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        });
    });

    // Fonction pour vérifier et afficher la modal de gestion des tickets
    function checkAndShowTicketManagementModal() {
        // Vérifier si l'intervention est fermée (status_id = 6)
        const statusId = <?php echo $intervention['status_id'] ?? 0; ?>;
        const ticketsUsed = <?php echo $intervention['tickets_used'] ?? 0; ?>;
        const originalContractId = '<?php echo $intervention['contract_id'] ?? ''; ?>';
        const currentContractId = document.getElementById('contract_id').value;
        
        // Afficher la modal seulement si :
        // 1. L'intervention est fermée (status_id = 6)
        // 2. Des tickets ont été utilisés
        // 3. Le contrat a changé
        if (statusId == 6 && ticketsUsed > 0 && originalContractId !== currentContractId && currentContractId !== '') {
            // Attendre un peu pour que l'utilisateur voie le changement
            setTimeout(() => {
                const modal = new bootstrap.Modal(document.getElementById('ticketManagementInfoModal'));
                modal.show();
            }, 500);
        }

        // Ouvrir la modale de création de client
        console.log('Début attachement gestionnaire client...');
        try {
            if (quickCreateClientBtn) {
                console.log('Bouton client trouvé, attachement du gestionnaire...');
                quickCreateClientBtn.addEventListener('click', function() {
                    console.log('Bouton client cliqué !');
                    if (!canModifyClients) {
                        alert('Vous n\'avez pas les permissions nécessaires pour créer un client.');
                        return;
                    }
                    quickCreateClientForm.reset();
                    quickCreateClientModal.show();
                });
                console.log('Gestionnaire d\'événement client attaché');
            } else {
                console.error('Bouton quickCreateClientBtn non trouvé !');
            }
        } catch (error) {
            console.error('Erreur lors de l\'attachement du gestionnaire client:', error);
        }

        // Créer le client via AJAX
        saveQuickClientBtn.addEventListener('click', function() {
            const formData = new FormData(quickCreateClientForm);
            
            // Validation côté client
            const clientName = formData.get('name').trim();
            const clientEmail = formData.get('email').trim();
            const clientWebsite = formData.get('website').trim();
            
            if (!clientName) {
                alert('Le nom du client est obligatoire');
                return;
            }
            
            // Validation optionnelle de l'email
            if (clientEmail && !validateEmailFormat(clientEmail)) {
                alert('Format d\'email invalide');
                return;
            }
            
            // Validation optionnelle du website
            if (clientWebsite && !validateWebsiteFormat(clientWebsite)) {
                alert('Format d\'URL invalide (ex: https://www.exemple.com)');
                return;
            }

            // Afficher le spinner
            clientSpinner.classList.remove('d-none');
            clientIcon.classList.add('d-none');
            saveQuickClientBtn.disabled = true;

            // Envoyer la requête AJAX
            fetch(`${BASE_URL}interventions/quickCreateClient`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': '<?= csrf_token() ?>'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Ajouter le nouveau client au select
                    const newOption = document.createElement('option');
                    newOption.value = data.client.id;
                    newOption.textContent = data.client.name;
                    newOption.selected = true;
                    clientSelect.appendChild(newOption);

                    // Fermer la modale
                    quickCreateClientModal.hide();

                    // Déclencher le changement pour charger les sites
                    clientSelect.dispatchEvent(new Event('change'));

                    // Afficher un message de succès
                    showSuccessMessage(data.message);
                } else {
                    alert('Erreur : ' + (data.error || 'Une erreur est survenue'));
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue lors de la création du client');
            })
            .finally(() => {
                // Masquer le spinner
                clientSpinner.classList.add('d-none');
                clientIcon.classList.remove('d-none');
                saveQuickClientBtn.disabled = false;
            });
        });

        // Ouvrir la modale de création de site (avec validation client)
        quickCreateSiteBtn.addEventListener('click', function() {
            if (!canModifyClients) {
                alert('Vous n\'avez pas les permissions nécessaires pour créer un site.');
                return;
            }
            
            const selectedClientId = clientSelect.value;
            
            if (!selectedClientId) {
                // Aucun client sélectionné - afficher un message
                alert('Veuillez d\'abord sélectionner un client avant de créer un site.');
                clientSelect.focus();
                return;
            }
            
            // Client sélectionné - ouvrir la modale
            quickCreateSiteForm.reset();
            quickCreateSiteModal.show();
        });

        // Créer le site via AJAX
        saveQuickSiteBtn.addEventListener('click', function() {
            const formData = new FormData(quickCreateSiteForm);
            const selectedClientId = clientSelect.value;
            
            // Ajouter le client_id aux données
            formData.append('client_id', selectedClientId);
            
            // Validation côté client
            const siteName = formData.get('name').trim();
            const siteEmail = formData.get('email').trim();
            
            if (!siteName) {
                alert('Le nom du site est obligatoire');
                return;
            }
            
            if (!selectedClientId) {
                alert('Aucun client sélectionné');
                return;
            }
            
            // Validation optionnelle de l'email
            if (siteEmail && !validateEmailFormat(siteEmail)) {
                alert('Format d\'email invalide');
                return;
            }

            // Afficher le spinner
            siteSpinner.classList.remove('d-none');
            siteIcon.classList.add('d-none');
            saveQuickSiteBtn.disabled = true;

            // Envoyer la requête AJAX
            fetch(`${BASE_URL}interventions/quickCreateSite`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': '<?= csrf_token() ?>'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Ajouter le nouveau site au select
                    const newOption = document.createElement('option');
                    newOption.value = data.site.id;
                    newOption.textContent = data.site.name;
                    newOption.selected = true;
                    siteSelect.appendChild(newOption);

                    // Fermer la modale
                    quickCreateSiteModal.hide();

                    // Déclencher le changement pour charger les salles
                    siteSelect.dispatchEvent(new Event('change'));

                    // Afficher un message de succès
                    showSuccessMessage(data.message);
                } else {
                    alert('Erreur : ' + (data.error || 'Une erreur est survenue'));
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue lors de la création du site');
            })
            .finally(() => {
                // Masquer le spinner
                siteSpinner.classList.add('d-none');
                siteIcon.classList.remove('d-none');
                saveQuickSiteBtn.disabled = false;
            });
        });

        // Ouvrir la modale de création de salle (avec validation site)
        quickCreateRoomBtn.addEventListener('click', function() {
            if (!canModifyClients) {
                alert('Vous n\'avez pas les permissions nécessaires pour créer une salle.');
                return;
            }
            
            const selectedSiteId = siteSelect.value;
            
            if (!selectedSiteId) {
                // Aucun site sélectionné - afficher un message
                alert('Veuillez d\'abord sélectionner un site avant de créer une salle.');
                siteSelect.focus();
                return;
            }
            
            // Site sélectionné - ouvrir la modale
            quickCreateRoomForm.reset();
            quickCreateRoomModal.show();
        });

        // Créer la salle via AJAX
        saveQuickRoomBtn.addEventListener('click', function() {
            const formData = new FormData(quickCreateRoomForm);
            const selectedSiteId = siteSelect.value;
            const selectedClientId = clientSelect.value;
            
            // Ajouter le site_id et client_id aux données
            formData.append('site_id', selectedSiteId);
            formData.append('client_id', selectedClientId);
            
            // Validation côté client
            const roomName = formData.get('name').trim();
            
            if (!roomName) {
                alert('Le nom de la salle est obligatoire');
                return;
            }
            
            if (!selectedSiteId) {
                alert('Aucun site sélectionné');
                return;
            }
            
            if (!selectedClientId) {
                alert('Aucun client sélectionné');
                return;
            }

            // Afficher le spinner
            roomSpinner.classList.remove('d-none');
            roomIcon.classList.add('d-none');
            saveQuickRoomBtn.disabled = true;

            // Envoyer la requête AJAX
            fetch(`${BASE_URL}interventions/quickCreateRoom`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': '<?= csrf_token() ?>'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Ajouter la nouvelle salle au select
                    const newOption = document.createElement('option');
                    newOption.value = data.room.id;
                    newOption.textContent = data.room.name;
                    newOption.selected = true;
                    roomSelect.appendChild(newOption);

                    // Fermer la modale
                    quickCreateRoomModal.hide();

                    // Déclencher le changement pour charger les contrats
                    roomSelect.dispatchEvent(new Event('change'));

                    // Afficher un message de succès
                    showSuccessMessage(data.message);
                } else {
                    alert('Erreur : ' + (data.error || 'Une erreur est survenue'));
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue lors de la création de la salle');
            })
            .finally(() => {
                // Masquer le spinner
                roomSpinner.classList.add('d-none');
                roomIcon.classList.remove('d-none');
                saveQuickRoomBtn.disabled = false;
            });
        });

        // Ouvrir la modale de création de contact (avec validation client)
        quickCreateContactBtn.addEventListener('click', function() {
            if (!canModifyClients) {
                alert('Vous n\'avez pas les permissions nécessaires pour créer un contact.');
                return;
            }
            
            const selectedClientId = clientSelect.value;
            
            if (!selectedClientId) {
                // Aucun client sélectionné - afficher un message
                alert('Veuillez d\'abord sélectionner un client avant de créer un contact.');
                clientSelect.focus();
                return;
            }
            
            // Client sélectionné - ouvrir la modale
            quickCreateContactForm.reset();
            quickCreateContactModal.show();
        });

        // Créer le contact via AJAX
        saveQuickContactBtn.addEventListener('click', function() {
            const formData = new FormData(quickCreateContactForm);
            const selectedClientId = clientSelect.value;
            
            // Ajouter le client_id aux données
            formData.append('client_id', selectedClientId);
            
            // Validation côté client
            const firstName = formData.get('first_name').trim();
            const lastName = formData.get('last_name').trim();
            const email = formData.get('email').trim();
            
            if (!firstName) {
                alert('Le prénom est obligatoire');
                return;
            }
            
            if (!lastName) {
                alert('Le nom est obligatoire');
                return;
            }
            
            if (!selectedClientId) {
                alert('Aucun client sélectionné');
                return;
            }
            
            // Validation optionnelle de l'email
            if (email && !validateEmailFormat(email)) {
                alert('Format d\'email invalide');
                return;
            }

            // Afficher le spinner
            contactSpinner.classList.remove('d-none');
            contactIcon.classList.add('d-none');
            saveQuickContactBtn.disabled = true;

            // Envoyer la requête AJAX
            fetch(`${BASE_URL}interventions/quickCreateContact`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': '<?= csrf_token() ?>'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Ajouter le nouveau contact au select
                    const newOption = document.createElement('option');
                    newOption.value = data.contact.email;
                    newOption.textContent = `${data.contact.first_name} ${data.contact.last_name} (${data.contact.email})`;
                    newOption.selected = true;
                    contactClientSelect.appendChild(newOption);

                    // Fermer la modale
                    quickCreateContactModal.hide();

                    // Afficher un message de succès
                    showSuccessMessage(data.message);
                } else {
                    alert('Erreur : ' + (data.error || 'Une erreur est survenue'));
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue lors de la création du contact');
            })
            .finally(() => {
                // Masquer le spinner
                contactSpinner.classList.add('d-none');
                contactIcon.classList.remove('d-none');
                saveQuickContactBtn.disabled = false;
            });
        });

        // Fonction pour valider le format d'email
        function validateEmailFormat(email) {
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            return emailRegex.test(email);
        }
        
        // Fonction pour valider le format d'URL
        function validateWebsiteFormat(website) {
            try {
                const url = new URL(website);
                return url.protocol === 'http:' || url.protocol === 'https:';
            } catch {
                return false;
            }
        }

        // Fonction pour afficher un message de succès
        function showSuccessMessage(message) {
            // Créer une alerte temporaire
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed';
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                <i class="bi bi-check-circle me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Supprimer automatiquement après 3 secondes
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 3000);
        }
    }
</script>

<style>
/* Styles pour la carte des informations de contact */
.contact-info-card {
    border-width: 2px !important;
    border-style: solid !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
}

.contact-info-header {
    border-bottom: 2px solid !important;
}

/* Mode clair */
[data-bs-theme="light"] .contact-info-card {
    background-color: #f8f9fa !important;
    border-color: #dee2e6 !important;
}

[data-bs-theme="light"] .contact-info-header {
    background-color: #e9ecef !important;
    border-bottom-color: #dee2e6 !important;
    color: #495057 !important;
}

/* Mode sombre */
[data-bs-theme="dark"] .contact-info-card {
    background-color: var(--bs-body-bg) !important;
    border-color: var(--bs-border-color) !important;
}

[data-bs-theme="dark"] .contact-info-header {
    background-color: var(--bs-secondary-bg) !important;
    border-bottom-color: var(--bs-border-color) !important;
    color: var(--bs-body-color) !important;
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

/* Styles pour la modale de fermeture d'intervention */
#closeInterventionModal .modal-dialog {
    max-width: 800px;
}

#closeInterventionModal .card {
    border: 1px solid var(--bs-border-color);
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

#closeInterventionModal .card-header {
    background-color: var(--bs-light);
    border-bottom: 1px solid var(--bs-border-color);
}

[data-bs-theme="dark"] #closeInterventionModal .card-header {
    background-color: var(--bs-secondary-bg);
}

#closeInterventionModal .form-control:focus {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.25);
}

#closeInterventionModal .input-group-text {
    background-color: var(--bs-secondary-bg);
    border-color: var(--bs-border-color);
    color: var(--bs-body-color);
}

#closeInterventionModal code {
    background-color: var(--bs-secondary-bg);
    color: var(--bs-body-color);
    padding: 0.2rem 0.4rem;
    border-radius: 0.25rem;
    font-size: 0.9em;
}

#closeInterventionModal .spinner-border {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<!-- Modal de confirmation de fermeture d'intervention -->
<?php if (isInterventionLinkedToTicketContract($intervention['id'])): ?>
<div class="modal fade" id="closeInterventionModal" tabindex="-1" aria-labelledby="closeInterventionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="closeInterventionModalLabel">
                    <i class="bi bi-x-lg-circle me-2"></i>Confirmation de fermeture d'intervention
                </h5>
            </div>
            <div class="modal-body">
                <div id="closeInterventionContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p class="mt-2">Chargement des détails de fermeture...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelCloseBtn" disabled>
                    <i class="bi bi-x-lg me-1"></i>Annuler
                </button>
                <button type="button" class="btn btn-danger" id="confirmCloseBtn" disabled>
                    <i class="bi bi-check-lg me-1"></i>Fermer l'intervention
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (isInterventionLinkedToTicketContract($intervention['id'])): ?>
<script>
// Test simple pour voir si le JavaScript s'exécute
console.log('DEBUG: Script de fermeture d\'intervention chargé');

// Fonction pour afficher la modale de confirmation de fermeture
function showCloseConfirmationModal() {
    const modal = new bootstrap.Modal(document.getElementById('closeInterventionModal'));
    modal.show();
}

// Fonction pour sauvegarder les données avant d'ouvrir la modale de fermeture
function saveInterventionDataBeforeClose() {
    console.log('DEBUG: Sauvegarde des données avant fermeture');
    
    // Récupérer toutes les données du formulaire sauf le statut
    const form = document.getElementById('interventionForm');
    const formData = new FormData(form);
    
    // Retirer le statut des données à sauvegarder
    formData.delete('status_id');
    
    // Ajouter un flag pour indiquer que c'est une sauvegarde avant fermeture
    formData.append('save_before_close', '1');
    
    // Afficher un indicateur de chargement
    const submitButton = document.querySelector('button[form="interventionForm"]');
    const originalText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="bi bi-arrow-clockwise spin me-1"></i>Sauvegarde...';
    
    // Envoyer les données
    fetch('<?php echo BASE_URL; ?>interventions/update/<?php echo $intervention['id']; ?>', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': '<?= csrf_token() ?>'
        },
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('DEBUG: Données sauvegardées avec succès');
            // Maintenant ouvrir la modale de confirmation
            showCloseConfirmationModal();
        } else {
            console.error('DEBUG: Erreur lors de la sauvegarde:', data.error);
            alert('Erreur lors de la sauvegarde des modifications : ' + (data.error || 'Erreur inconnue'));
        }
    })
    .catch(error => {
        console.error('DEBUG: Erreur lors de la sauvegarde:', error);
        alert('Erreur lors de la sauvegarde des modifications');
    })
    .finally(() => {
        // Réactiver le bouton
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    });
}

// Gestion de la modale de fermeture d'intervention
document.addEventListener('DOMContentLoaded', function() {
    console.log('DEBUG: DOMContentLoaded - Initialisation de la modale de fermeture');
    
    const closeModal = document.getElementById('closeInterventionModal');
    const contentDiv = document.getElementById('closeInterventionContent');
    const confirmBtn = document.getElementById('confirmCloseBtn');
    const cancelBtn = document.getElementById('cancelCloseBtn');
    
    console.log('DEBUG: Éléments trouvés:', {
        closeModal: !!closeModal,
        contentDiv: !!contentDiv,
        confirmBtn: !!confirmBtn,
        cancelBtn: !!cancelBtn
    });
    
    if (!closeModal || !contentDiv || !confirmBtn || !cancelBtn) {
        console.error('DEBUG: Un ou plusieurs éléments de la modale sont manquants');
        return;
    }
    
    // Activer le bouton d'annulation
    cancelBtn.style.display = 'inline-block';
    cancelBtn.disabled = false;
    cancelBtn.innerHTML = '<i class="bi bi-x-lg me-1"></i>Annuler la fermeture';
    
    // Quand la modale s'ouvre, charger les détails
    closeModal.addEventListener('shown.bs.modal', function() {
        console.log('DEBUG: Événement shown.bs.modal déclenché');
        loadCloseDetails();
    });
    
    // Gérer le bouton d'annulation
    cancelBtn.addEventListener('click', function() {
        console.log('DEBUG: Annulation de la fermeture');
        // Fermer la modale sans fermer l'intervention
        const modal = bootstrap.Modal.getInstance(closeModal);
        if (modal) {
            modal.hide();
        }
    });
    
    // Fonction pour charger les détails de fermeture
    function loadCloseDetails() {
        console.log('DEBUG: loadCloseDetails() appelée');
        const interventionId = <?php echo isset($intervention['id']) ? $intervention['id'] : 'null'; ?>;
        console.log('DEBUG: interventionId:', interventionId);
        
        if (!interventionId) {
            console.error('DEBUG: interventionId est null ou undefined');
            contentDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Erreur: ID d'intervention manquant.
                </div>
            `;
            return;
        }
        
        // Afficher le spinner
        contentDiv.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <p class="mt-2">Chargement des détails de fermeture...</p>
            </div>
        `;
        
        // Faire la requête AJAX
        const url = '<?php echo BASE_URL; ?>interventions/getCloseDetails/' + interventionId;
        console.log('DEBUG: URL appelée:', url);
        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                console.log('DEBUG: Réponse reçue:', response.status, response.statusText);
                return response.json();
            })
            .then(data => {
                console.log('DEBUG: Données reçues:', data);
                if (data.error) {
                    contentDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            ${data.error}
                        </div>
                    `;
                    confirmBtn.disabled = true;
                } else {
                    displayCloseDetails(data);
                    confirmBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des détails:', error);
                console.error('Type d\'erreur:', typeof error);
                console.error('Message d\'erreur:', error.message);
                console.error('Stack trace:', error.stack);
                contentDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Erreur lors du chargement des détails de fermeture: ${error.message}
                    </div>
                `;
                confirmBtn.disabled = true;
            });
    }
    
    // Fonction pour afficher les détails de fermeture
    function displayCloseDetails(data) {
        console.log('DEBUG: displayCloseDetails - Données reçues:', data);
        
        const intervention = data.intervention;
        const calculation = data.calculation;
        const contract = data.contract;
        
        // Validation des données
        if (!intervention || !calculation) {
            console.error('DEBUG: Données manquantes:', {intervention, calculation});
            contentDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Erreur: Données incomplètes reçues du serveur.
                </div>
            `;
            return;
        }
        
        // S'assurer que tickets_used est un nombre valide
        const ticketsUsed = parseInt(calculation.tickets_used) || 0;
        console.log('DEBUG: tickets_used validé:', ticketsUsed);
        console.log('DEBUG: calculation.tickets_used original:', calculation.tickets_used);
        console.log('DEBUG: typeof calculation.tickets_used:', typeof calculation.tickets_used);
        
        // S'assurer que tous les champs nécessaires sont définis
        const safeCalculation = {
            duration: calculation.duration || 0,
            coef_utilisateur: calculation.coef_utilisateur || 0,
            coef_intervention: calculation.coef_intervention || 0,
            requires_travel: calculation.requires_travel || false,
            travel_bonus: calculation.travel_bonus || 0,
            formula: calculation.formula || 'Calcul non disponible',
            tickets_calculated: calculation.tickets_calculated || 0,
            tickets_used: ticketsUsed
        };
        
        let contractSection = '';
        if (contract) {
            const isTicketContract = contract.isticketcontract == 1;
            const ticketsAfter = contract.tickets_remaining - ticketsUsed;
            const ticketsColor = ticketsAfter > 3 ? 'success' : ticketsAfter > 0 ? 'warning' : 'danger';
            
            contractSection = `
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-file-earmark-text me-2"></i>Impact sur le contrat
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Contrat:</strong> ${contract.name || 'Non défini'}<br>
                                <strong>Type:</strong> ${contract.type_name || 'Non défini'}<br>
                                <strong>Nature:</strong> ${isTicketContract ? 
                                    '<span class="badge bg-info"><i class="bi bi-ticket-perforated me-1"></i>Contrat à tickets</span>' : 
                                    '<span class="badge bg-secondary"><i class="bi bi-file-text me-1"></i>Contrat sans tickets</span>'
                                }
                            </div>
                            <div class="col-md-6">
                                ${isTicketContract ? `
                                    <strong>Tickets actuels:</strong> <span class="badge bg-primary">${contract.tickets_remaining || 0}</span><br>
                                    <strong>Tickets après fermeture:</strong> <span class="badge bg-${ticketsColor}">${ticketsAfter}</span><br>
                                    <strong>Variation:</strong> <span class="text-danger">-${ticketsUsed}</span>
                                ` : '<em class="text-muted">Aucun impact sur les tickets</em>'}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Construire le HTML étape par étape
        let html = `
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Attention !</strong> Vous êtes sur le point de fermer définitivement cette intervention.
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-calculator me-2"></i>Calcul des tickets utilisés
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Formule de calcul:</strong><br>
                            <code>${safeCalculation.formula}</code><br><br>
                            <strong>Détail:</strong><br>
                            • Durée: ${safeCalculation.duration}h<br>
                            • Coefficient technicien: ${safeCalculation.coef_utilisateur}<br>
                            • Coefficient intervention: ${safeCalculation.coef_intervention}<br>
                            ${safeCalculation.requires_travel ? '• Bonus déplacement: +1' : ''}
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="customTicketsUsed" class="form-label">
                                    <strong>Tickets à utiliser:</strong>
                                </label>
                                <div class="input-group">
                                    <input type="number" 
                                           class="form-control" 
                                           id="customTicketsUsed" 
                                           min="0" 
                                           max="999"
                                           step="1">
                                    <span class="input-group-text">tickets</span>
                                </div>
                                <div class="form-text">
                                    Valeur calculée automatiquement. Vous pouvez la modifier si nécessaire.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            ${contractSection}
            
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-envelope me-2"></i>Notification par email
                    </h6>
                </div>
                <div class="card-body">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="sendEmailOnClose" name="send_email" value="1" checked>
                        <label class="form-check-label" for="sendEmailOnClose">
                            <strong>Envoyer un email de notification au client</strong>
                        </label>
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>
                            Un email sera envoyé au contact client pour l'informer de la fermeture de l'intervention.
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        contentDiv.innerHTML = html;
        
        // Définir la valeur de l'input après avoir créé le HTML
        const ticketsInput = document.getElementById('customTicketsUsed');
        if (ticketsInput) {
            ticketsInput.value = safeCalculation.tickets_used;
            console.log('DEBUG: Valeur définie dans l\'input:', safeCalculation.tickets_used);
            
            // Ajouter un gestionnaire d'événement pour mettre à jour l'impact en temps réel
            ticketsInput.addEventListener('input', function() {
                updateContractImpact();
            });
        } else {
            console.error('DEBUG: Élément customTicketsUsed non trouvé');
        }
        
        // Fonction pour mettre à jour l'impact sur le contrat
        function updateContractImpact() {
            const ticketsInput = document.getElementById('customTicketsUsed');
            
            if (ticketsInput && contract) {
                const newTickets = parseInt(ticketsInput.value) || 0;
                const currentRemaining = contract.tickets_remaining || 0;
                const ticketsAfter = currentRemaining - newTickets;
                
                // Trouver l'élément "Tickets après fermeture" plus spécifiquement
                const contractSection = document.querySelector('#closeInterventionContent .card:last-child');
                if (contractSection) {
                    // Alternative: chercher par le texte du label précédent
                    const allStrongs = contractSection.querySelectorAll('strong');
                    let ticketsAfterElement = null;
                    for (let strong of allStrongs) {
                        if (strong.textContent.includes('Tickets après fermeture:')) {
                            ticketsAfterElement = strong.nextElementSibling;
                            break;
                        }
                    }
                    
                    if (ticketsAfterElement) {
                        // Mettre à jour le nombre de tickets après fermeture
                        ticketsAfterElement.textContent = ticketsAfter;
                        
                        // Changer la couleur selon l'impact
                        ticketsAfterElement.className = 'badge';
                        if (ticketsAfter < 0) {
                            ticketsAfterElement.classList.add('bg-danger');
                        } else if (ticketsAfter < 5) {
                            ticketsAfterElement.classList.add('bg-warning');
                        } else {
                            ticketsAfterElement.classList.add('bg-success');
                        }
                    }
                }
                
                // Mettre à jour la variation
                const variationElement = document.querySelector('.text-danger');
                if (variationElement) {
                    variationElement.textContent = '-' + newTickets;
                }
            }
        }
    }
    
    // Gérer la confirmation de fermeture
    confirmBtn.addEventListener('click', function() {
        const interventionId = <?php echo $intervention['id']; ?>;
        const ticketsUsed = document.getElementById('customTicketsUsed').value;
        
        if (!ticketsUsed || ticketsUsed < 0) {
            alert('Veuillez saisir un nombre de tickets valide.');
            return;
        }
        
        // Désactiver le bouton pendant le traitement
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-1"></i>Fermeture en cours...';
        
        // Créer un formulaire pour envoyer les données
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo BASE_URL; ?>interventions/close/' + interventionId;
        
        const ticketsInput = document.createElement('input');
        ticketsInput.type = 'hidden';
        ticketsInput.name = 'tickets_used';
        ticketsInput.value = ticketsUsed;
        form.appendChild(ticketsInput);
        
        // Ajouter la case à cocher pour l'envoi d'email
        const sendEmailCheckbox = document.getElementById('sendEmailOnClose');
        if (sendEmailCheckbox && sendEmailCheckbox.checked) {
            const emailInput = document.createElement('input');
            emailInput.type = 'hidden';
            emailInput.name = 'send_email';
            emailInput.value = '1';
            form.appendChild(emailInput);
        }
        
        document.body.appendChild(form);
        form.submit();
    });
});
</script>
<?php endif; ?>

<!-- Modal de notification technicien -->
<div class="modal fade" id="notifyTechnicianModal" tabindex="-1" aria-labelledby="notifyTechnicianModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="notifyTechnicianModalLabel">
                    <i class="bi bi-envelope me-2"></i>Notifier le technicien
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Le technicien a été modifié. Souhaitez-vous lui envoyer un email de notification ?</p>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="notifyTechnicianCheckbox" checked>
                    <label class="form-check-label" for="notifyTechnicianCheckbox">
                        Envoyer un email au technicien
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="confirmNotifyBtn">
                    <i class="bi bi-check-lg me-1"></i>Enregistrer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const saveButton = document.getElementById('saveButton');
    const form = document.getElementById('interventionForm');
    const originalTechnicianId = <?php echo $intervention['technician_id'] ?? 'null'; ?>;
    const technicianSelect = document.getElementById('technician_id');
    let shouldShowModal = false;
    
    // Intercepter le clic sur le bouton Enregistrer
    saveButton.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Vérifier si le technicien a changé
        const currentTechnicianId = technicianSelect.value ? parseInt(technicianSelect.value) : null;
        
        // Si le technicien a changé et qu'un technicien est sélectionné, afficher la modale
        if (currentTechnicianId && currentTechnicianId !== originalTechnicianId) {
            shouldShowModal = true;
            const modal = new bootstrap.Modal(document.getElementById('notifyTechnicianModal'));
            modal.show();
        } else {
            // Pas de changement de technicien, soumettre directement
            form.submit();
        }
    });
    
    // Gérer la confirmation dans la modale
    document.getElementById('confirmNotifyBtn').addEventListener('click', function() {
        const notifyCheckbox = document.getElementById('notifyTechnicianCheckbox');
        
        // Ajouter un champ caché pour indiquer si on doit envoyer l'email
        if (notifyCheckbox.checked) {
            // Supprimer l'ancien champ s'il existe
            const existingInput = form.querySelector('input[name="notify_technician"]');
            if (existingInput) {
                existingInput.remove();
            }
            
            const notifyInput = document.createElement('input');
            notifyInput.type = 'hidden';
            notifyInput.name = 'notify_technician';
            notifyInput.value = '1';
            form.appendChild(notifyInput);
        }
        
        // Fermer la modale
        const modal = bootstrap.Modal.getInstance(document.getElementById('notifyTechnicianModal'));
        modal.hide();
        
        // Soumettre le formulaire
        form.submit();
    });
});
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 