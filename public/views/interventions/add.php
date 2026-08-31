<?php
require_once __DIR__ . '/../../includes/functions.php';

// Vérification de l'accès
if (!isset($_SESSION['user']) || !canModifyInterventions()) {
    $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour créer une intervention.";
    header('Location: ' . BASE_URL . 'dashboard');
    exit;
}

$pageTitle = "Nouvelle intervention";
include_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!canModifyInterventions()) {
    $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour créer une intervention.";
    header('Location: ' . BASE_URL . 'interventions');
    exit;
}

setPageVariables('Nouvelle Intervention', 'interventions');
$currentPage = 'interventions';

$selectedClientId = $_GET['client_id'] ?? null;
$selectedClient = null;
if ($selectedClientId) {
    if (isset($clients) && is_array($clients)) {
        foreach ($clients as $c) {
            if (isset($c['id']) && $c['id'] == $selectedClientId) {
                $selectedClient = $c;
                break;
            }
        }
    }
    if (!$selectedClient) {
        require_once __DIR__ . '/../../models/ClientModel.php';
        global $db;
        $clientModel = new ClientModel($db);
        $selectedClient = $clientModel->getClientById($selectedClientId);
    }
}

$GLOBALS['customBreadcrumbs'] = generateInterventionAddBreadcrumbs($selectedClient);

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>
<header>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
</header>
<div class="container-fluid flex-grow-1 container-p-y">

    <div class="d-flex flex-row align-items-center justify-content-between">
        <div class="p-2">
            <h4 class="py-4 mb-6">Nouvelle Intervention</h4>
        </div>
        <div class="">
            <?php
            $returnTo = $_GET['return_to'] ?? 'index';
            $clientId = $_GET['client_id'] ?? null;
            $returnUrl = ($returnTo === 'view' && $clientId) ?
                BASE_URL . 'clients/view/' . $clientId . '?active_tab=interventions-tab' :
                BASE_URL . 'interventions/curatives';
            ?>
            <a href="<?php echo $returnUrl; ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Retour
            </a>

            <button type="submit" id="createButton" class="btn btn-primary" form="interventionForm">
                <i class="bi bi-plus-lg me-1"></i>
                Créer l'intervention
            </button>

        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo $_SESSION['error'];
            unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['success'];
            unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header py-2">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="card-title mb-0">
                        <span class="fw-bold me-3">Nouvelle référence</span>
                        <input type="text" class="form-control d-inline-block bg-body text-body" id="title" name="title"
                            form="interventionForm" placeholder="Titre de l'intervention" required>
                        <small id="titleError" class="text-danger d-none">Le titre est obligatoire.</small>
                    </h5>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label fw-bold mb-0 text-white">Date de création</label>
                            <input type="date" class="form-control bg-body text-body" id="created_date"
                                name="created_date" value="<?= date('Y-m-d') ?>" form="interventionForm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold mb-0 text-white">Heure de création</label>
                            <input type="time" class="form-control bg-body text-body" id="created_time"
                                name="created_time" value="<?= date('H:i') ?>" form="interventionForm">

                        </div>

                    </div>
                </div>
            </div>
        </div>
        <div class="card-body py-2">
            <form
                action="<?php echo BASE_URL; ?>interventions/store<?php echo isset($_GET['return_to']) ? '?return_to=' . $_GET['return_to'] : ''; ?>"
                method="post" id="interventionForm">
                <?= csrf_field() ?>
                <div class="row g-3">

                    <!-- Colonne 1 : Client, Site, Bâtiment, Salle -->
                    <div class="col-md-3">
                        <div class="d-flex flex-column gap-2">

                            <!-- Client -->
                            <div>
                                <label class="form-label fw-bold mb-0">Client *</label>
                                <div class="input-group">
                                    <select class="form-select bg-body text-body" id="client_id" name="client_id"
                                        required>
                                        <option value="">Sélectionner un client</option>
                                        <?php foreach ($clients as $client): ?>
                                            <option value="<?= $client['id'] ?>" <?= ($selectedClientId && $client['id'] == $selectedClientId) ? 'selected' : '' ?>>
                                                <?= h($client['name'] ?? '') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                        id="quickCreateClientBtn" title="Créer un nouveau client">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                                <small id="clientError" class="text-danger d-none">Le client est obligatoire.</small>
                            </div>

                            <!-- Site -->
                            <div>
                                <label class="form-label fw-bold mb-0">Site</label>
                                <div class="input-group">
                                    <select class="form-select bg-body text-body" id="site_id" name="site_id">
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
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                        id="quickCreateRoomBtn" title="Créer un nouveau bâtiment">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="d-flex flex-column gap-2">

                            <!-- Type d'intervention -->
                            <div>
                                <label class="form-label fw-bold mb-0">Type d'intervention *</label>
                                <select class="form-select bg-body text-body" id="type_id" name="type_id" required>
                                    <option value="">Sélectionner un type</option>
                                    <?php foreach ($types as $type): ?>
                                        <option value="<?= $type['id'] ?>">
                                            <?= h($type['name'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small id="typeError" class="text-danger d-none">Le type d'intervention est
                                    obligatoire.</small>
                            </div>

                            <!-- Contrat -->
                            <div>
                                <label class="form-label fw-bold mb-0">Contrat associé</label>
                                <select class="form-select bg-body text-body" id="contract_id" name="contract_id">
                                    <option value="">Sélectionner un contrat</option>
                                </select>
                                <small id="contractError" class="text-danger d-none">Le contrat est obligatoire.</small>
                                <small id="contractWarning" class="text-warning d-none">Aucun contrat associé à cette
                                    salle.</small>
                            </div>
                            <div>
                                <label class="form-label fw-bold mb-0">Technicien(s) à affecter</label>
                                <select class="form-select bg-body text-body" id="technicien_ids"
                                    name="technicien_ids[]" multiple size="4">
                                    <?php foreach ($technicians as $technician): ?>
                                        <option value="<?= $technician['id'] ?>">
                                            <?= h(($technician['first_name'] ?? '') . ' ' . ($technician['last_name'] ?? '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted d-block mt-1">
                                    Ctrl + clic pour sélectionner plusieurs techniciens.
                                </small>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="notify_technician"
                                        name="notify_technician" value="1" checked>
                                    <label class="form-check-label" for="notify_technician">
                                        Notifier le(s) technicien(s) par email
                                    </label>
                                </div>
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
                                        <?php $isSelected = ($status['name'] == 'Nouveau' || $status['id'] == 1) ? 'selected' : ''; ?>
                                        <option value="<?= $status['id'] ?>" <?= $isSelected ?>>
                                            <?= h($status['name'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small id="statutError" class="text-danger d-none">Le statut est obligatoire.</small>
                            </div>

                            <!-- Priorité -->
                            <div>
                                <label class="form-label fw-bold mb-0">Priorité *</label>
                                <select class="form-select bg-body text-body" id="priority_id" name="priority_id"
                                    required>
                                    <option value="">Sélectionner une priorité</option>
                                    <?php foreach ($priorities as $priority): ?>
                                        <?php $isSelected = ($priority['name'] == 'Moyenne' || $priority['id'] == 2) ? 'selected' : ''; ?>
                                        <option value="<?= $priority['id'] ?>" <?= $isSelected ?>>
                                            <?= h($priority['name'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small id="prioriError" class="text-danger d-none">La priorité est obligatoire.</small>
                            </div>
                            <div class="mt-2 pt-1">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_preventive"
                                        name="is_preventive" value="1">
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
                                    rows="5"></textarea>
                            </div>
                        </div>
                    </div>

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
                                            placeholder="Nom de la personne qui a demandé l'intervention">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Référence client</label>
                                        <input type="text" class="form-control bg-body text-body" id="ref_client"
                                            name="ref_client" placeholder="Référence interne du client">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Contact existant</label>
                                        <div class="input-group">
                                            <select class="form-select bg-body text-body" id="contact_client_select"
                                                name="contact_client_select">
                                                <option value="">Sélectionner un contact existant</option>
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
                                            name="contact_client" placeholder="email@exemple.com">
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
</div>

<div class="modal fade" id="quickCreateClientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Créer un nouveau client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="quickCreateClientForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Nom du client *</label>
                            <input type="text" class="form-control" name="name" required
                                placeholder="Nom de l'entreprise">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" class="form-control" id="client_email" name="email"
                                placeholder="contact@entreprise.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Téléphone</label>
                            <input type="tel" class="form-control" name="phone" placeholder="01 23 45 67 89">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Site web</label>
                            <input type="url" class="form-control" id="client_website" name="website"
                                placeholder="https://www.entreprise.com">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Adresse</label>
                            <input type="text" class="form-control" name="address" placeholder="123 Rue de la Paix">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Code postal</label>
                            <input type="text" class="form-control" name="postal_code" placeholder="75001">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Ville</label>
                            <input type="text" class="form-control" name="city" placeholder="Paris">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Commentaire</label>
                            <textarea class="form-control" name="comment" rows="3"
                                placeholder="Commentaires ou notes sur ce client..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="saveQuickClientBtn">
                    <span class="spinner-border spinner-border-sm d-none" id="clientSpinner"></span>
                    <i class="bi bi-check-lg me-1" id="clientIcon"></i>Créer le client
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="quickCreateSiteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-building me-2"></i>Créer un nouveau site</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="quickCreateSiteForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Nom du site *</label>
                            <input type="text" class="form-control" name="name" required placeholder="Nom du site">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Adresse</label>
                            <input type="text" class="form-control" name="address" placeholder="123 Rue de la Paix">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Code postal</label>
                            <input type="text" class="form-control" name="postal_code" placeholder="75001">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Ville</label>
                            <input type="text" class="form-control" name="city" placeholder="Paris">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Téléphone</label>
                            <input type="tel" class="form-control" name="phone" placeholder="01 23 45 67 89">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" class="form-control" id="site_email" name="email"
                                placeholder="contact@site.com">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Commentaire</label>
                            <textarea class="form-control" name="comment" rows="2"
                                placeholder="Commentaires sur ce site..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="saveQuickSiteBtn">
                    <span class="spinner-border spinner-border-sm d-none" id="siteSpinner"></span>
                    <i class="bi bi-check-lg me-1" id="siteIcon"></i>Créer le site
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="quickCreateBuildingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-building me-2"></i>Créer un nouveau bâtiment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="quickCreateBuildingForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Nom du bâtiment *</label>
                            <input type="text" class="form-control" name="name" required placeholder="Nom du bâtiment">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Commentaire</label>
                            <textarea class="form-control" name="comment" rows="3"
                                placeholder="Commentaires sur ce bâtiment..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="saveQuickBuildingBtn">
                    <span class="spinner-border spinner-border-sm d-none" id="buildingSpinner"></span>
                    <i class="bi bi-check-lg me-1" id="buildingIcon"></i>Créer le bâtiment
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="quickCreateRoomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-door-open me-2"></i>Créer une nouvelle salle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="quickCreateRoomForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Nom de la salle *</label>
                            <input type="text" class="form-control" name="name" required placeholder="Nom de la salle">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Commentaire</label>
                            <textarea class="form-control" name="comment" rows="3"
                                placeholder="Commentaires sur cette salle..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="saveQuickRoomBtn">
                    <span class="spinner-border spinner-border-sm d-none" id="roomSpinner"></span>
                    <i class="bi bi-check-lg me-1" id="roomIcon"></i>Créer la salle
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="quickCreateContactModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Créer un nouveau contact</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="quickCreateContactForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Prénom *</label>
                            <input type="text" class="form-control" id="contact_first_name" name="first_name" required
                                placeholder="Prénom">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nom *</label>
                            <input type="text" class="form-control" id="contact_last_name" name="last_name" required
                                placeholder="Nom">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" class="form-control" id="contact_email" name="email"
                                placeholder="contact@exemple.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Téléphone 1</label>
                            <input type="tel" class="form-control" id="contact_phone1" name="phone1"
                                placeholder="01 23 45 67 89">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Téléphone 2</label>
                            <input type="tel" class="form-control" id="contact_phone2" name="phone2"
                                placeholder="01 23 45 67 89">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Fonction</label>
                            <input type="text" class="form-control" id="contact_fonction" name="fonction"
                                placeholder="Directeur, Responsable IT, etc.">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Commentaire</label>
                            <textarea class="form-control" id="contact_comment" name="comment" rows="2"
                                placeholder="Commentaires sur ce contact..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="saveQuickContactBtn">
                    <span class="spinner-border spinner-border-sm d-none" id="contactSpinner"></span>
                    <i class="bi bi-check-lg me-1" id="contactIcon"></i>Créer le contact
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="notifyTechnicianModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-envelope me-2"></i>Notifier le technicien</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Un technicien a été affecté à cette intervention. Souhaitez-vous lui envoyer un email de notification
                    ?</p>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="notifyTechnicianCheckbox" checked>
                    <label class="form-check-label" for="notifyTechnicianCheckbox">Envoyer un email au
                        technicien</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="confirmNotifyBtn">
                    <i class="bi bi-check-lg me-1"></i>Créer l'intervention
                </button>
            </div>
        </div>
    </div>
</div>
<script>
    window.BASE_URL = '<?= BASE_URL ?>';
    window.csrfToken = '<?= csrf_token() ?>';
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        initBaseUrl('<?php echo BASE_URL; ?>');

        const canModifyClients = <?php echo canModifyClients() ? 'true' : 'false'; ?>;

        const clientSelect = document.getElementById('client_id');
        const siteSelect = document.getElementById('site_id');
        const buildingSelect = document.getElementById('building_id');
        const roomSelect = document.getElementById('room_id');
        const contractSelect = document.getElementById('contract_id');
        const contractError = document.getElementById('contractError');
        const contractWarning = document.getElementById('contractWarning');
        const tomSelectConfig = {
            plugins: ['dropdown_input'],
            placeholder: 'Rechercher...',
            allowEmptyOption: true,
            maxOptions: null,
            render: {
                option: function (data, escape) {
                    return `<div>${escape(data.text)}</div>`;
                },
                item: function (data, escape) {
                    return `<div>${escape(data.text)}</div>`;
                }
            },
            onDropdownOpen: function (dropdown) {
                if (!dropdown) return;

                let resizer = dropdown.querySelector('.filter-dropdown-resizer');
                if (!resizer) {
                    resizer = document.createElement('div');
                    resizer.className = 'filter-dropdown-resizer';
                    dropdown.appendChild(resizer);
                }

                // On ne rebranche pas les listeners si déjà fait sur cette poignée
                if (resizer.dataset.bound) return;
                resizer.dataset.bound = 'true';

                resizer.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const startX = e.clientX;
                    const startY = e.clientY;
                    const startWidth = dropdown.offsetWidth;
                    const startHeight = dropdown.offsetHeight;

                    function onMouseMove(e) {
                        const newWidth = Math.max(150, startWidth + (e.clientX - startX));
                        const newHeight = Math.max(80, startHeight + (e.clientY - startY));
                        dropdown.style.setProperty('width', newWidth + 'px', 'important');
                        dropdown.style.setProperty('height', newHeight + 'px', 'important');
                    }

                    function onMouseUp() {
                        document.removeEventListener('mousemove', onMouseMove);
                        document.removeEventListener('mouseup', onMouseUp);
                    }

                    document.addEventListener('mousemove', onMouseMove);
                    document.addEventListener('mouseup', onMouseUp);
                });
            }
        };

        function initTomSelect(selectId, placeholder, searchFields = ['text']) {
            const select = document.getElementById(selectId);
            if (!select) return null;

            if (select.tomselect) {
                select.tomselect.destroy();
            }

            return new TomSelect('#' + selectId, {
                ...tomSelectConfig,
                placeholder: placeholder || 'Rechercher...',
                searchField: searchFields,
                valueField: 'value',
                labelField: 'text'
            });
        }
        const tomClients = initTomSelect('client_id', 'Rechercher un client...');
        const tomSites = initTomSelect('site_id', 'Rechercher un site...');
        const tomBuildings = initTomSelect('building_id', 'Rechercher un bâtiment...');
        const tomRooms = initTomSelect('room_id', 'Rechercher une salle...');
        const tomTypes = initTomSelect('type_id', 'Rechercher un type...');
        const tomContracts = initTomSelect('contract_id', 'Rechercher un contrat...');
        const tomStatuses = initTomSelect('status_id', 'Rechercher un statut...');
        const tomPriorities = initTomSelect('priority_id', 'Rechercher une priorité...');
        const tomContacts = initTomSelect('contact_client_select', 'Rechercher un contact...');

        window.tomSelectInstances = {
            client_id: tomClients,
            site_id: tomSites,
            building_id: tomBuildings,
            room_id: tomRooms,
            type_id: tomTypes,
            contract_id: tomContracts,
            status_id: tomStatuses,
            priority_id: tomPriorities,
            contact_client_select: tomContacts
        };
        function getFilterValues() {
            return {
                client_id: clientSelect?.tomselect
                    ? clientSelect.tomselect.getValue()
                    : (clientSelect?.value || ''),

                site_id: siteSelect?.tomselect
                    ? siteSelect.tomselect.getValue()
                    : (siteSelect?.value || ''),

                building_id: buildingSelect?.tomselect
                    ? buildingSelect.tomselect.getValue()
                    : (buildingSelect?.value || ''),

                room_id: roomSelect?.tomselect
                    ? roomSelect.tomselect.getValue()
                    : (roomSelect?.value || '')
            };
        }

        /**
         * Appel AJAX JSON pour les routes de filtrage.
         */
        async function fetchFilterData(route, params = {}) {

            const url = new URL(
                `${BASE_URL}interventions/${route}`,
                window.location.origin
            );

            Object.entries(params).forEach(([key, value]) => {

                if (
                    value !== null &&
                    value !== undefined &&
                    value !== ''
                ) {
                    url.searchParams.set(key, value);
                }

            });

            console.log(
                `GET ${url.pathname}${url.search}`
            );

            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });


            if (!response.ok) {

                throw new Error(
                    `HTTP ${response.status} - ${url}`
                );

            }


            const contentType =
                response.headers.get('content-type') || '';


            if (!contentType.includes('application/json')) {

                const text = await response.text();

                console.error(
                    'Réponse non JSON :',
                    text
                );

                throw new Error(
                    'La réponse du serveur n’est pas au format JSON.'
                );

            }


            return await response.json();
        }

        function fillFilterSelect(select, items) {
            if (!select) {
                return;
            }

            select.innerHTML = '';

            if (!Array.isArray(items)) {
                items = [];
            }

            items.forEach(item => {
                const option = document.createElement('option');
                option.value = String(item.id);
                option.textContent = item.name ?? '';
                select.appendChild(option);
            });

            if (select.tomselect) {
                const options = items.map(item => ({
                    value: String(item.id),
                    text: item.name || ''
                }));
                select.tomselect.clearOptions();
                options.forEach(opt => select.tomselect.addOption(opt));
                select.tomselect.refreshOptions(false);
                select.tomselect.setValue('', true);
            }
        }

        function selectFilterValue(select, value) {
            if (!select) {
                return false;
            }

            if (select.tomselect) {

                if (!value) {
                    select.tomselect.setValue('', true);

                    if (
                        select.id === 'client_id' &&
                        typeof window.validateClient === 'function'
                    ) {
                        window.validateClient();
                    }

                    return true;
                }

                const strValue = String(value);

                if (!select.tomselect.options[strValue]) {
                    const nativeOption = Array.from(select.options).find(
                        option => String(option.value) === strValue
                    );

                    if (nativeOption) {
                        select.tomselect.addOption({
                            value: strValue,
                            text: nativeOption.textContent
                        });

                        select.tomselect.refreshOptions(false);
                    }
                }

                if (select.tomselect.options[strValue]) {
                    select.tomselect.setValue(strValue, true);

                    // Validation du client après sélection automatique
                    if (
                        select.id === 'client_id' &&
                        typeof window.validateClient === 'function'
                    ) {
                        window.validateClient();
                    }

                    return true;
                }

                console.warn(
                    'selectFilterValue: option introuvable pour Tom Select',
                    select.id,
                    strValue
                );

                return false;
            }

            if (!value) {
                select.value = '';

                if (
                    select.id === 'client_id' &&
                    typeof window.validateClient === 'function'
                ) {
                    window.validateClient();
                }

                return true;
            }

            const option = Array.from(select.options).find(
                option => String(option.value) === String(value)
            );

            if (!option) {
                return false;
            }

            select.value = String(value);

            if (
                select.id === 'client_id' &&
                typeof window.validateClient === 'function'
            ) {
                window.validateClient();
            }

            return true;
        }

        /**
         * Active / désactive les filtres pendant le chargement.
         */
        function setFiltersLoading(loading) {

            if (clientSelect) {
                clientSelect.disabled = loading;
            }

            if (siteSelect) {
                siteSelect.disabled = loading;
            }

            if (buildingSelect) {
                buildingSelect.disabled = loading;
            }

            if (roomSelect) {
                roomSelect.disabled = loading;
            }

        }

        function selectContractWhenAvailable(
            contractId,
            attempt = 0
        ) {

            if (!contractSelect || !contractId) {
                return;
            }


            const option =
                Array.from(contractSelect.options)
                    .find(
                        option =>
                            String(option.value) ===
                            String(contractId)
                    );


            if (option) {

                const selected = selectFilterValue(contractSelect, contractId);

                if (!selected) {
                    if (attempt < 30) {
                        setTimeout(() => selectContractWhenAvailable(contractId, attempt + 1), 100);
                    } else {
                        console.warn('Impossible de sélectionner le contrat dans Tom Select :', contractId);
                    }
                    return;
                }
                contractSelect.dispatchEvent(
                    new Event('change', {
                        bubbles: true
                    })
                );

                if (contractWarning) {
                    contractWarning.classList.add('d-none');
                }
                if (contractError) {
                    contractError.classList.add('d-none');
                }
                return;
            }

            if (attempt < 30) {

                setTimeout(
                    () => {
                        selectContractWhenAvailable(
                            contractId,
                            attempt + 1
                        );
                    },
                    100
                );

            } else {

                console.warn(
                    'Le contrat n’a pas été trouvé dans le select :',
                    contractId
                );

            }

        }

        /**
         * Récupère le contrat associé directement à une salle.
         */
        async function loadContractByRoom(roomId) {

            if (!roomId || !contractSelect) {
                return;
            }

            try {

                const response = await fetch(
                    `${BASE_URL}interventions/getContractByRoom/${roomId}`,
                    {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    }
                );

                if (!response.ok) {
                    throw new Error(
                        `HTTP ${response.status}`
                    );
                }

                const contract = await response.json();

                if (contract && contract.id) {
                    selectContractWhenAvailable(contract.id);
                } else {
                    selectFilterValue(contractSelect, '');

                    if (contractError) {
                        contractError.classList.add('d-none');
                    }

                    if (contractWarning) {
                        contractWarning.classList.remove('d-none');
                    }
                    contractSelect.classList.remove('is-invalid');

                    contractSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }

            } catch (error) {

                console.error(
                    'Erreur lors de la récupération du contrat de la salle :',
                    error
                );

                // En cas d'erreur, réinitialiser SANS erreur
                selectFilterValue(contractSelect, '');
                if (contractError) {
                    contractError.classList.add('d-none');
                }
                if (contractWarning) {
                    contractWarning.classList.remove('d-none');
                }
                contractSelect.classList.remove('is-invalid');

            }

        }

        async function reloadLocationFilters() {

            const values = getFilterValues();

            console.log('Reload des filtres avec :', values);

            const params = {};

            // On transmet uniquement les filtres réellement sélectionnés
            if (values.client_id) {
                params.client_id = values.client_id;
            }

            if (values.site_id) {
                params.site_id = values.site_id;
            }

            if (values.building_id) {
                params.building_id = values.building_id;
            }

            if (values.room_id) {
                params.room_id = values.room_id;
            }

            setFiltersLoading(true);

            try {

                const [
                    clients,
                    sites,
                    buildings,
                    rooms
                ] = await Promise.all([

                    fetchFilterData(
                        'get_all_clients',
                        params
                    ),

                    fetchFilterData(
                        'get_all_sites',
                        params
                    ),

                    fetchFilterData(
                        'get_all_buildings',
                        params
                    ),

                    fetchFilterData(
                        'get_all_rooms',
                        params
                    )
                ]);

                console.log('Résultats des filtres :', {
                    clients,
                    sites,
                    buildings,
                    rooms
                });

                /*
                 * IMPORTANT :
                 * On mémorise les valeurs actuelles avant de
                 * reconstruire les options.
                 */
                const selectedClient = values.client_id;
                const selectedSite = values.site_id;
                const selectedBuilding = values.building_id;
                const selectedRoom = values.room_id;

                /*
                 * Recharge les listes.
                 */
                fillFilterSelect(clientSelect, clients);
                fillFilterSelect(siteSelect, sites);
                fillFilterSelect(buildingSelect, buildings);
                fillFilterSelect(roomSelect, rooms);

                /*
                 * Restaure les sélections existantes
                 * uniquement si elles existent encore dans
                 * les résultats filtrés.
                 */
                if (
                    selectedClient &&
                    clients.some(
                        item => String(item.id) === String(selectedClient)
                    )
                ) {
                    selectFilterValue(clientSelect, selectedClient);
                }

                if (
                    selectedSite &&
                    sites.some(
                        item => String(item.id) === String(selectedSite)
                    )
                ) {
                    selectFilterValue(siteSelect, selectedSite);
                }

                if (
                    selectedBuilding &&
                    buildings.some(
                        item => String(item.id) === String(selectedBuilding)
                    )
                ) {
                    selectFilterValue(buildingSelect, selectedBuilding);
                }

                if (
                    selectedRoom &&
                    rooms.some(
                        item => String(item.id) === String(selectedRoom)
                    )
                ) {
                    selectFilterValue(roomSelect, selectedRoom);
                }

            } catch (error) {

                console.error(
                    'Erreur lors du rechargement des filtres :',
                    error
                );

            } finally {

                setFiltersLoading(false);
            }
        }
        clientSelect.addEventListener('change', async function () {

            const clientId = this.value;

            console.log('Client sélectionné :', clientId);

            // Quand le client change, les filtres enfants
            // doivent être recalculés à partir de ce client.
            selectFilterValue(siteSelect, '');
            selectFilterValue(buildingSelect, '');
            selectFilterValue(roomSelect, '');

            if (clientId) {
                loadContacts(clientId);
            } else {
                loadContacts('');
            }

            await reloadLocationFilters();

            updateSelectedContract(
                'client_id',
                'site_id',
                'room_id',
                'contract_id'
            );
        });

        siteSelect.addEventListener('change', async function () {

            const siteId = this.value;

            console.log('Site sélectionné :', siteId);

            // Un changement de site invalide
            // le bâtiment et la salle précédemment sélectionnés.
            selectFilterValue(buildingSelect, '');
            selectFilterValue(roomSelect, '');

            await reloadLocationFilters();

            updateSelectedContract(
                'client_id',
                'site_id',
                'room_id',
                'contract_id'
            );
        });
        buildingSelect.addEventListener('change', async function () {

            const buildingId = this.value;

            console.log('Bâtiment sélectionné :', buildingId);
            selectFilterValue(roomSelect, '');

            await reloadLocationFilters();

            updateSelectedContract(
                'client_id',
                'site_id',
                'room_id',
                'contract_id'
            );
        });
        roomSelect.addEventListener(
            'change',
            async function () {

                const roomId =
                    this.value;

                if (!roomId) {

                    updateSelectedContract(
                        'client_id',
                        'site_id',
                        'room_id',
                        'contract_id'
                    );

                    return;
                }


                try {

                    const [
                        clients,
                        sites,
                        buildings
                    ] = await Promise.all([

                        fetchFilterData(
                            'get_all_clients',
                            {
                                room_id: roomId
                            }
                        ),

                        fetchFilterData(
                            'get_all_sites',
                            {
                                room_id: roomId
                            }
                        ),

                        fetchFilterData(
                            'get_all_buildings',
                            {
                                room_id: roomId
                            }
                        )

                    ]);


                    console.log(
                        'Parents de la salle :',
                        {
                            clients,
                            sites,
                            buildings
                        }
                    );

                    fillFilterSelect(
                        clientSelect,
                        clients
                    );

                    if (clients.length > 0) {
                        selectFilterValue(clientSelect, String(clients[0].id));
                    } else {
                        selectFilterValue(clientSelect, '');
                    }
                    fillFilterSelect(
                        siteSelect,
                        sites
                    );

                    if (sites.length > 0) {
                        selectFilterValue(siteSelect, String(sites[0].id));
                    } else {
                        selectFilterValue(siteSelect, '');
                    }

                    fillFilterSelect(
                        buildingSelect,
                        buildings
                    );

                    if (buildings.length > 0) {
                        selectFilterValue(buildingSelect, String(buildings[0].id));
                    } else {
                        selectFilterValue(buildingSelect, '');
                    }

                    const roomStillExists =
                        Array.from(
                            roomSelect.options
                        ).some(
                            option =>
                                String(option.value) ===
                                String(roomId)
                        );


                    if (!roomStillExists) {

                        const option =
                            document.createElement('option');

                        option.value =
                            String(roomId);

                        option.textContent =
                            'Salle sélectionnée';

                        roomSelect.appendChild(option);

                        if (roomSelect.tomselect) {
                            roomSelect.tomselect.addOption({
                                value: String(roomId),
                                text: 'Salle sélectionnée'
                            });
                        }
                    }
                    selectFilterValue(roomSelect, String(roomId));


                    console.log(
                        'Filtres après sélection salle :',
                        getFilterValues()
                    );

                    if (clientSelect.value) {

                        loadContacts(
                            clientSelect.value
                        );

                    }

                    updateSelectedContract(
                        'client_id',
                        'site_id',
                        'room_id',
                        'contract_id'
                    );

                    await loadContractByRoom(
                        roomId
                    );


                } catch (error) {

                    console.error(
                        'Erreur lors de la récupération des parents de la salle :',
                        error
                    );

                }

            }
        );


        (async function initializeLocationFilters() {

            try {

                const initialValues =
                    getFilterValues();


                console.log(
                    'Initialisation des filtres :',
                    initialValues
                );

                if (initialValues.room_id) {

                    roomSelect.dispatchEvent(
                        new Event('change', {
                            bubbles: true
                        })
                    );

                    return;
                }


                if (initialValues.client_id) {

                    await reloadLocationFilters();


                    loadContacts(
                        initialValues.client_id
                    );


                    updateSelectedContract(
                        'client_id',
                        'site_id',
                        'room_id',
                        'contract_id'
                    );

                    return;
                }


                /*
                 * Aucun filtre :
                 * charger toutes les possibilités.
                 */

                await reloadLocationFilters();


            } catch (error) {

                console.error(
                    'Erreur lors de l\'initialisation des filtres : ',
                    error
                );

            }

        })();
        <?php if ($selectedClientId): ?>
            if (clientSelect.value) {
                loadSites(clientSelect.value, 'site_id', null, null, function () {
                    updateSelectedContract('client_id', 'site_id', 'room_id', 'contract_id');
                });
            }
        <?php endif; ?>

        const contactClientSelect = document.getElementById('contact_client_select');
        const contactClientInput = document.getElementById('contact_client');

        contactClientSelect.addEventListener('change', function () {
            if (this.value) contactClientInput.value = this.value;
        });

        let contactsLoading = false;
        let currentContactsRequest = null;

        function loadContacts(clientId) {
            if (!clientId) {
                contactClientSelect.innerHTML = '<option value="">Sélectionner un contact existant</option>';
                if (contactClientSelect.tomselect) {
                    contactClientSelect.tomselect.clearOptions();
                    contactClientSelect.tomselect.addOption({ value: '', text: 'Sélectionner un contact existant' });
                    contactClientSelect.tomselect.refreshOptions(false);
                }
                return;
            }
            if (currentContactsRequest) contactsLoading = false;
            if (contactsLoading) return;
            contactsLoading = true;
            contactClientSelect.disabled = true;
            contactClientSelect.innerHTML = '<option value="">Chargement...</option>';
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000);
            currentContactsRequest = fetch(`${BASE_URL}interventions/getContacts/${clientId}`, {
                signal: controller.signal
            })
                .then(r => {
                    if (!r.ok) throw new Error(`HTTP ${r.status}`);
                    return r.json();
                })
                .then(contacts => {
                    clearTimeout(timeoutId);
                    contactClientSelect.innerHTML = '<option value="">Sélectionner un contact existant</option>';
                    if (contacts && Array.isArray(contacts)) {
                        contacts.forEach(c => {
                            const opt = document.createElement('option');
                            opt.value = c.email;
                            opt.textContent = `${c.first_name} ${c.last_name} (${c.email})`;
                            contactClientSelect.appendChild(opt);
                        });
                    }
                    if (contactClientSelect.tomselect) {
                        const options = Array.from(contactClientSelect.options).map(opt => ({
                            value: opt.value,
                            text: opt.textContent
                        }));
                        contactClientSelect.tomselect.clearOptions();
                        options.forEach(opt => contactClientSelect.tomselect.addOption(opt));
                        contactClientSelect.tomselect.refreshOptions(false);
                    }
                })
                .catch(err => {
                    clearTimeout(timeoutId);
                    contactClientSelect.innerHTML = err.name === 'AbortError' ?
                        '<option value="">Timeout - Veuillez réessayer</option>' :
                        '<option value="">Erreur de chargement</option>';
                })
                .finally(() => {
                    contactsLoading = false;
                    contactClientSelect.disabled = false;
                    currentContactsRequest = null;
                });
        }

        function loadBuildingsLocal(siteId, targetSelectId, selectedId = null, callback = null) {
            const targetSelect = document.getElementById(targetSelectId);
            if (!siteId) {
                targetSelect.innerHTML = '';
                if (callback) callback();
                return;
            }
            targetSelect.disabled = true;
            targetSelect.innerHTML = '<option value="">Chargement...</option>';
            fetch(`${BASE_URL}interventions/getBuildings/${siteId}`)
                .then(r => r.json())
                .then(buildings => {
                    targetSelect.innerHTML = '';
                    if (buildings && Array.isArray(buildings)) {
                        buildings.forEach(b => {
                            const opt = document.createElement('option');
                            opt.value = b.id;
                            opt.textContent = b.name;
                            if (selectedId && selectedId == b.id) opt.selected = true;
                            targetSelect.appendChild(opt);
                        });
                    }
                    if (targetSelect.tomselect) {
                        const options = Array.from(targetSelect.options).map(opt => ({
                            value: opt.value,
                            text: opt.textContent
                        }));
                        targetSelect.tomselect.clearOptions();
                        options.forEach(opt => targetSelect.tomselect.addOption(opt));
                        targetSelect.tomselect.refreshOptions(false);
                        if (selectedId) {
                            targetSelect.tomselect.setValue(String(selectedId), true);
                        }
                    }
                    targetSelect.disabled = false;
                    if (callback) callback();
                })
                .catch(err => {
                    targetSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                    targetSelect.disabled = false;
                    if (callback) callback();
                });
        }

        function loadRoomsByBuildingLocal(buildingId, targetSelectId, selectedId = null, callback = null) {
            const targetSelect = document.getElementById(targetSelectId);
            if (!buildingId) {
                targetSelect.innerHTML = '';
                if (callback) callback();
                return;
            }
            targetSelect.disabled = true;
            targetSelect.innerHTML = '<option value="">Chargement...</option>';
            fetch(`${BASE_URL}interventions/getRoomsByBuilding/${buildingId}`)
                .then(r => r.json())
                .then(rooms => {
                    targetSelect.innerHTML = '';
                    if (rooms && Array.isArray(rooms)) {
                        rooms.forEach(r => {
                            const opt = document.createElement('option');
                            opt.value = r.id;
                            opt.textContent = r.name;
                            if (selectedId && selectedId == r.id) opt.selected = true;
                            targetSelect.appendChild(opt);
                        });
                    }
                    if (targetSelect.tomselect) {
                        const options = Array.from(targetSelect.options).map(opt => ({
                            value: opt.value,
                            text: opt.textContent
                        }));
                        targetSelect.tomselect.clearOptions();
                        options.forEach(opt => targetSelect.tomselect.addOption(opt));
                        targetSelect.tomselect.refreshOptions(false);
                        if (selectedId) {
                            targetSelect.tomselect.setValue(String(selectedId), true);
                        }
                    }
                    targetSelect.disabled = false;
                    if (callback) callback();
                })
                .catch(err => {
                    targetSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                    targetSelect.disabled = false;
                    if (callback) callback();
                });
        }


        const emailError = document.getElementById('email-error');
        contactClientInput.addEventListener('input', function () {
            validateEmail(this.value);
        });
        contactClientInput.addEventListener('blur', function () {
            validateEmail(this.value);
        });

        function validateEmail(email) {
            contactClientInput.classList.remove('is-invalid', 'is-valid');
            emailError.textContent = '';
            if (!email.trim()) return true;
            const ok = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(email);
            if (!ok) {
                contactClientInput.classList.add('is-invalid');
                emailError.textContent = "Format d'email invalide. Exemple : nom@domaine.com";
            } else {
                contactClientInput.classList.add('is-valid');
            }
            return ok;
        }

        document.getElementById('interventionForm').addEventListener('submit', function (e) {
            const email = contactClientInput.value.trim();
            if (email && !validateEmail(email)) {
                e.preventDefault();
                contactClientInput.focus();
            }
        });

        function validateEmailFormat(email) {
            return /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(email);
        }

        function validateWebsiteFormat(website) {
            try {
                const u = new URL(website);
                return u.protocol === 'http:' || u.protocol === 'https:';
            } catch {
                return false;
            }
        }

        function showSuccessMessage(message) {
            const div = document.createElement('div');
            div.className = 'alert alert-success alert-dismissible fade show position-fixed';
            div.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            div.innerHTML = `<i class="bi bi-check-circle me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
            document.body.appendChild(div);
            setTimeout(() => {
                if (div.parentNode) div.remove();
            }, 3000);
        }

        const quickCreateClientModal = new bootstrap.Modal(document.getElementById('quickCreateClientModal'));
        const quickCreateSiteModal = new bootstrap.Modal(document.getElementById('quickCreateSiteModal'));
        const quickCreateBuildingModal = new bootstrap.Modal(document.getElementById('quickCreateBuildingModal'));
        const quickCreateRoomModal = new bootstrap.Modal(document.getElementById('quickCreateRoomModal'));
        const quickCreateContactModal = new bootstrap.Modal(document.getElementById('quickCreateContactModal'));


        document.getElementById('quickCreateClientBtn').addEventListener('click', function () {
            if (!canModifyClients) {
                alert("Vous n'avez pas les permissions nécessaires pour créer un client.");
                return;
            }
            document.getElementById('quickCreateClientForm').reset();
            quickCreateClientModal.show();
        });

        document.getElementById('saveQuickClientBtn').addEventListener('click', function () {
            const formData = new FormData(document.getElementById('quickCreateClientForm'));
            const name = formData.get('name').trim();
            const email = (document.getElementById('client_email')?.value || '').trim();
            const website = (document.getElementById('client_website')?.value || '').trim();
            if (!name) {
                alert('Le nom du client est obligatoire');
                return;
            }
            if (email && !validateEmailFormat(email)) {
                alert("Format d'email invalide");
                return;
            }
            if (website && !validateWebsiteFormat(website)) {
                alert("Format d'URL invalide");
                return;
            }

            const spinner = document.getElementById('clientSpinner');
            const icon = document.getElementById('clientIcon');
            const btn = this;
            spinner.classList.remove('d-none');
            icon.classList.add('d-none');
            btn.disabled = true;

            fetch(`${BASE_URL}interventions/quickCreateClient`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': window.csrfToken
                },
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const opt = document.createElement('option');
                        opt.value = data.client.id;
                        opt.textContent = data.client.name;
                        opt.selected = true;
                        clientSelect.appendChild(opt);
                        if (clientSelect.tomselect) {
                            clientSelect.tomselect.addOption({ value: String(data.client.id), text: data.client.name });
                            clientSelect.tomselect.setValue(String(data.client.id), true);
                            clientSelect.tomselect.refreshOptions(false);
                        }
                        quickCreateClientModal.hide();
                        clientSelect.dispatchEvent(new Event('change'));
                        showSuccessMessage(data.message);
                    } else {
                        alert('Erreur : ' + (data.error || 'Une erreur est survenue'));
                    }
                })
                .catch(() => alert('Une erreur est survenue lors de la création du client'))
                .finally(() => {
                    spinner.classList.add('d-none');
                    icon.classList.remove('d-none');
                    btn.disabled = false;
                });
        });
        document.getElementById('quickCreateSiteBtn').addEventListener('click', function () {
            if (!canModifyClients) {
                alert("Vous n'avez pas les permissions nécessaires pour créer un site.");
                return;
            }
            if (!clientSelect.value) {
                alert("Veuillez d'abord sélectionner un client avant de créer un site.");
                clientSelect.focus();
                return;
            }
            document.getElementById('quickCreateSiteForm').reset();
            quickCreateSiteModal.show();
        });

        document.getElementById('saveQuickSiteBtn').addEventListener('click', function () {
            const formData = new FormData(document.getElementById('quickCreateSiteForm'));
            formData.append('client_id', clientSelect.value);
            const name = formData.get('name').trim();
            const email = (document.getElementById('site_email')?.value || '').trim();
            if (!name) {
                alert('Le nom du site est obligatoire');
                return;
            }
            if (!clientSelect.value) {
                alert('Aucun client sélectionné');
                return;
            }
            if (email && !validateEmailFormat(email)) {
                alert("Format d'email invalide");
                return;
            }

            const spinner = document.getElementById('siteSpinner');
            const icon = document.getElementById('siteIcon');
            const btn = this;
            spinner.classList.remove('d-none');
            icon.classList.add('d-none');
            btn.disabled = true;

            fetch(`${BASE_URL}interventions/quickCreateSite`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': window.csrfToken
                },
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const opt = document.createElement('option');
                        opt.value = data.site.id;
                        opt.textContent = data.site.name;
                        opt.selected = true;
                        siteSelect.appendChild(opt);
                        if (siteSelect.tomselect) {
                            siteSelect.tomselect.addOption({ value: String(data.site.id), text: data.site.name });
                            siteSelect.tomselect.setValue(String(data.site.id), true);
                            siteSelect.tomselect.refreshOptions(false);
                        }
                        quickCreateSiteModal.hide();
                        siteSelect.dispatchEvent(new Event('change'));
                        showSuccessMessage(data.message);
                    } else {
                        alert('Erreur : ' + (data.error || 'Une erreur est survenue'));
                    }
                })
                .catch(() => alert('Une erreur est survenue lors de la création du site'))
                .finally(() => {
                    spinner.classList.add('d-none');
                    icon.classList.remove('d-none');
                    btn.disabled = false;
                });
        });


        document.getElementById('quickCreateBuildingBtn').addEventListener('click', function () {
            if (!canModifyClients) {
                alert("Vous n'avez pas les permissions nécessaires pour créer un bâtiment.");
                return;
            }
            if (!siteSelect.value) {
                alert("Veuillez d'abord sélectionner un site avant de créer un bâtiment.");
                siteSelect.focus();
                return;
            }
            document.getElementById('quickCreateBuildingForm').reset();
            quickCreateBuildingModal.show();
        });

        document.getElementById('saveQuickBuildingBtn').addEventListener('click', function () {
            const formData = new FormData(document.getElementById('quickCreateBuildingForm'));
            formData.append('site_id', siteSelect.value);
            formData.append('client_id', clientSelect.value);
            const name = formData.get('name').trim();
            if (!name) {
                alert('Le nom du bâtiment est obligatoire');
                return;
            }
            if (!siteSelect.value) {
                alert('Aucun site sélectionné');
                return;
            }
            if (!clientSelect.value) {
                alert('Aucun client sélectionné');
                return;
            }

            const spinner = document.getElementById('buildingSpinner');
            const icon = document.getElementById('buildingIcon');
            const btn = this;
            spinner.classList.remove('d-none');
            icon.classList.add('d-none');
            btn.disabled = true;

            fetch(`${BASE_URL}interventions/quickCreateBuilding`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': window.csrfToken
                },
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const opt = document.createElement('option');
                        opt.value = data.building.id;
                        opt.textContent = data.building.name;
                        opt.selected = true;
                        buildingSelect.appendChild(opt);
                        if (buildingSelect.tomselect) {
                            buildingSelect.tomselect.addOption({ value: String(data.building.id), text: data.building.name });
                            buildingSelect.tomselect.setValue(String(data.building.id), true);
                            buildingSelect.tomselect.refreshOptions(false);
                        }
                        quickCreateBuildingModal.hide();
                        buildingSelect.dispatchEvent(new Event('change'));
                        showSuccessMessage(data.message);
                    } else {
                        alert('Erreur : ' + (data.error || 'Une erreur est survenue'));
                    }
                })
                .catch(() => alert('Une erreur est survenue lors de la création du bâtiment'))
                .finally(() => {
                    spinner.classList.add('d-none');
                    icon.classList.remove('d-none');
                    btn.disabled = false;
                });
        });

        document.getElementById('quickCreateRoomBtn').addEventListener('click', function () {
            if (!canModifyClients) {
                alert("Vous n'avez pas les permissions nécessaires pour créer une salle.");
                return;
            }
            if (!buildingSelect.value) {
                alert("Veuillez d'abord sélectionner un bâtiment avant de créer une salle.");
                buildingSelect.focus();
                return;
            }
            document.getElementById('quickCreateRoomForm').reset();
            quickCreateRoomModal.show();
        });

        document.getElementById('saveQuickRoomBtn').addEventListener('click', function () {
            const formData = new FormData(document.getElementById('quickCreateRoomForm'));
            formData.append('building_id', buildingSelect.value);
            formData.append('site_id', siteSelect.value);
            formData.append('client_id', clientSelect.value);
            const name = formData.get('name').trim();
            if (!name) {
                alert('Le nom de la salle est obligatoire');
                return;
            }
            if (!buildingSelect.value) {
                alert('Aucun bâtiment sélectionné');
                return;
            }
            if (!siteSelect.value) {
                alert('Aucun site sélectionné');
                return;
            }
            if (!clientSelect.value) {
                alert('Aucun client sélectionné');
                return;
            }

            const spinner = document.getElementById('roomSpinner');
            const icon = document.getElementById('roomIcon');
            const btn = this;
            spinner.classList.remove('d-none');
            icon.classList.add('d-none');
            btn.disabled = true;

            fetch(`${BASE_URL}interventions/quickCreateRoom`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': window.csrfToken
                },
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const opt = document.createElement('option');
                        opt.value = data.room.id;
                        opt.textContent = data.room.name;
                        opt.selected = true;
                        roomSelect.appendChild(opt);
                        if (roomSelect.tomselect) {
                            roomSelect.tomselect.addOption({ value: String(data.room.id), text: data.room.name });
                            roomSelect.tomselect.setValue(String(data.room.id), true);
                            roomSelect.tomselect.refreshOptions(false);
                        }
                        quickCreateRoomModal.hide();
                        roomSelect.dispatchEvent(new Event('change'));
                        showSuccessMessage(data.message);
                    } else {
                        alert('Erreur : ' + (data.error || 'Une erreur est survenue'));
                    }
                })
                .catch(() => alert('Une erreur est survenue lors de la création de la salle'))
                .finally(() => {
                    spinner.classList.add('d-none');
                    icon.classList.remove('d-none');
                    btn.disabled = false;
                });
        });


        document.getElementById('quickCreateContactBtn').addEventListener('click', function () {
            if (!canModifyClients) {
                alert("Vous n'avez pas les permissions nécessaires pour créer un contact.");
                return;
            }
            if (!clientSelect.value) {
                alert("Veuillez d'abord sélectionner un client avant de créer un contact.");
                clientSelect.focus();
                return;
            }
            document.getElementById('quickCreateContactForm').reset();
            quickCreateContactModal.show();
        });

        document.getElementById('saveQuickContactBtn').addEventListener('click', function () {
            const firstName = document.getElementById('contact_first_name').value.trim();
            const lastName = document.getElementById('contact_last_name').value.trim();
            const email = document.getElementById('contact_email').value.trim();
            const phone1 = document.getElementById('contact_phone1').value.trim();
            const phone2 = document.getElementById('contact_phone2').value.trim();
            const fonction = document.getElementById('contact_fonction').value.trim();
            const comment = document.getElementById('contact_comment').value.trim();

            if (!firstName) {
                alert('Le prénom est obligatoire');
                return;
            }
            if (!lastName) {
                alert('Le nom est obligatoire');
                return;
            }
            if (!clientSelect.value) {
                alert('Aucun client sélectionné');
                return;
            }
            if (email && !validateEmailFormat(email)) {
                alert("Format d'email invalide");
                return;
            }

            const spinner = document.getElementById('contactSpinner');
            const icon = document.getElementById('contactIcon');
            const btn = this;
            spinner.classList.remove('d-none');
            icon.classList.add('d-none');
            btn.disabled = true;

            const formData = new FormData();
            formData.append('client_id', clientSelect.value);
            formData.append('first_name', firstName);
            formData.append('last_name', lastName);
            formData.append('email', email);
            formData.append('phone1', phone1);
            formData.append('phone2', phone2);
            formData.append('fonction', fonction);
            formData.append('comment', comment);
            formData.append('csrf_token', window.csrfToken);

            fetch(`${BASE_URL}interventions/quickCreateContact`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const opt = document.createElement('option');
                        opt.value = data.contact.email;
                        opt.textContent = `${data.contact.first_name} ${data.contact.last_name} (${data.contact.email})`;
                        opt.selected = true;
                        contactClientSelect.appendChild(opt);
                        if (contactClientSelect.tomselect) {
                            contactClientSelect.tomselect.addOption({ value: data.contact.email, text: opt.textContent });
                            contactClientSelect.tomselect.setValue(data.contact.email, true);
                            contactClientSelect.tomselect.refreshOptions(false);
                        }
                        quickCreateContactModal.hide();
                        showSuccessMessage(data.message);
                    } else {
                        alert('Erreur : ' + (data.error || 'Une erreur est survenue'));
                    }
                })
                .catch(err => alert('Une erreur est survenue lors de la création du contact: ' + err.message))
                .finally(() => {
                    spinner.classList.add('d-none');
                    icon.classList.remove('d-none');
                    btn.disabled = false;
                });
        });
        document.getElementById('confirmNotifyBtn').addEventListener('click', function () {
            const form = document.getElementById('interventionForm');
            if (form) {
                form.requestSubmit();
            }
        });
        const flashBtn = document.getElementById('confirmFlashBtn');
        const flashClient = document.getElementById('flash_client_id');
        const flashSpinner = document.getElementById('flashSpinner');

        if (flashBtn) {
            flashBtn.addEventListener('click', function () {
                const clientId = flashClient.value;
                if (!clientId) {
                    flashClient.classList.add('is-invalid');
                    flashClient.focus();
                    return;
                }
                flashClient.classList.remove('is-invalid');
                flashSpinner.classList.remove('d-none');
                flashBtn.disabled = true;

                const formData = new URLSearchParams();
                formData.append('client_id', clientId);
                formData.append('title', document.getElementById('flash_title').value);
                formData.append('csrf_token', window.csrfToken);

                fetch(`${window.BASE_URL}interventions/flash`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': window.csrfToken
                    },
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = `${window.BASE_URL}interventions/edit/${data.intervention_id}`;
                        } else {
                            alert('Erreur : ' + (data.error || 'Une erreur est survenue'));
                            flashSpinner.classList.add('d-none');
                            flashBtn.disabled = false;
                        }
                    })
                    .catch(err => {
                        alert('Une erreur est survenue lors de la création flash');
                        flashSpinner.classList.add('d-none');
                        flashBtn.disabled = false;
                    });
            });
        }

    }); 
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const titleInput = document.getElementById('title');
        const titleError = document.getElementById('titleError');
        const clientInput = document.getElementById('client_id');
        const clientError = document.getElementById('clientError');
        const typeInput = document.getElementById('type_id');
        const typeError = document.getElementById('typeError');
        const contractInput = document.getElementById('contract_id');
        const contractError = document.getElementById('contractError');
        const contractWarning = document.getElementById('contractWarning');
        const statutInput = document.getElementById('status_id');
        const statutError = document.getElementById('statutError');
        const prioriInput = document.getElementById('priority_id');
        const prioriError = document.getElementById('prioriError');
        const form = document.getElementById('interventionForm');

        let isValidating = false;

        function validateType() {
            const isValid = typeInput.value !== '';
            if (!isValid) {
                typeError.classList.remove('d-none');
                typeInput.classList.add('is-invalid');
            } else {
                typeError.classList.add('d-none');
                typeInput.classList.remove('is-invalid');
            }
            return isValid;
        }



        function validateContract() {
            const isValid = contractInput.value !== '';
            if (!isValid) {
                contractInput.classList.add('is-invalid');
                if (contractWarning) {
                    contractWarning.classList.remove('d-none');
                }
            } else {
                contractError.classList.add('d-none');
                contractInput.classList.remove('is-invalid');
                if (contractWarning) {
                    contractWarning.classList.add('d-none');
                }
            }
            return isValid; // Retourner la vraie valeur
        }

        function validateStatus() {
            const isValid = statutInput.value !== '';
            if (!isValid) {
                statutError.classList.remove('d-none');
                statutInput.classList.add('is-invalid');
            } else {
                statutError.classList.add('d-none');
                statutInput.classList.remove('is-invalid');
            }
            return isValid;
        }

        function validatePriority() {
            const isValid = prioriInput.value !== '';
            if (!isValid) {
                prioriError.classList.remove('d-none');
                prioriInput.classList.add('is-invalid');
            } else {
                prioriError.classList.add('d-none');
                prioriInput.classList.remove('is-invalid');
            }
            return isValid;
        }

        function validateTitle() {
            const isValid = titleInput.value.trim() !== '';
            if (!isValid) {
                titleError.classList.remove('d-none');
                titleInput.classList.add('is-invalid');
            } else {
                titleError.classList.add('d-none');
                titleInput.classList.remove('is-invalid');
            }
            return isValid;
        }

        function validateClient() {
            const isValid = clientInput.value !== '';
            if (!isValid) {
                clientError.classList.remove('d-none');
                clientInput.classList.add('is-invalid');
            } else {
                clientError.classList.add('d-none');
                clientInput.classList.remove('is-invalid');
            }
            return isValid;
        }
        window.validateClient = validateClient;
        window.validateContract = validateContract;

        validateTitle();
        validateType();
        validateStatus();
        validatePriority();
        validateContract();
        validateClient();

        typeInput.addEventListener('change', validateType);
        contractInput.addEventListener('change', validateContract);
        statutInput.addEventListener('change', validateStatus);
        prioriInput.addEventListener('change', validatePriority);
        titleInput.addEventListener('input', validateTitle);
        clientInput.addEventListener('change', validateClient);



        form.addEventListener('submit', function (e) {
            const isValid = validateType() &&
                validateStatus() &&
                validatePriority() &&
                validateTitle() &&
                validateClient();

            validateContract();

            if (!isValid) {
                e.preventDefault();
                const firstInvalid = document.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                }
            }
        });


        const contractObserver = new MutationObserver(function (mutations) {
            let shouldValidate = false;
            for (const mutation of mutations) {
                if (mutation.type === 'childList' && mutation.target === contractInput) {
                    shouldValidate = true;
                    break;
                }
                if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                    shouldValidate = true;
                    break;
                }
            }
            if (shouldValidate && !isValidating) {
                validateContract();
            }
        });

        contractObserver.observe(contractInput, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['value']
        });

        clientInput.addEventListener('change', validateClient);


        window.resetContractWithError = function () {
            if (!contractInput || isValidating) return;

            contractInput.value = '';

            if (typeof selectFilterValue === 'function') {
                selectFilterValue(contractInput, '');
            }

            validateContract();

            contractInput.dispatchEvent(new Event('change', { bubbles: true }));
        };

        window.resetContractWithoutError = function () {
            if (!contractInput || isValidating) return;

            contractInput.value = '';

            if (typeof selectFilterValue === 'function') {
                selectFilterValue(contractInput, '');
            }
            contractError.classList.add('d-none');
            contractInput.classList.remove('is-invalid');
            if (contractWarning) {
                contractWarning.classList.remove('d-none');
            }

            contractInput.dispatchEvent(new Event('change', { bubbles: true }));
        };

    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.modal').forEach(function (modal) {

            modal.addEventListener('hidden.bs.modal', function () {
                const dialog = modal.querySelector('.modal-dialog');
                if (dialog) {
                    dialog.style.position = '';
                    dialog.style.left = '';
                    dialog.style.top = '';
                    dialog.style.margin = '';
                    dialog.style.width = '';
                    dialog.style.maxWidth = '';
                }
            });

            modal.addEventListener('shown.bs.modal', function () {
                const dialog = modal.querySelector('.modal-dialog');
                const header = modal.querySelector('.modal-header');
                if (!dialog || !header) return;

                if (header.dataset.draggable) return;
                header.dataset.draggable = 'true';

                header.style.cursor = 'grab';

                let isDragging = false;
                let startX, startY, startLeft, startTop;

                header.addEventListener('mousedown', function (e) {
                    if (e.target.closest('button')) return;

                    isDragging = true;
                    header.style.cursor = 'grabbing';

                    const rect = dialog.getBoundingClientRect();
                    startX = e.clientX;
                    startY = e.clientY;
                    startLeft = rect.left;
                    startTop = rect.top;

                    dialog.style.width = rect.width + 'px';
                    dialog.style.maxWidth = 'none';
                    dialog.style.position = 'fixed';
                    dialog.style.left = startLeft + 'px';
                    dialog.style.top = startTop + 'px';
                    dialog.style.margin = '0';
                });

                document.addEventListener('mousemove', function (e) {
                    if (!isDragging) return;
                    const dx = e.clientX - startX;
                    const dy = e.clientY - startY;
                    dialog.style.left = (startLeft + dx) + 'px';
                    dialog.style.top = (startTop + dy) + 'px';
                });

                document.addEventListener('mouseup', function () {
                    if (isDragging) {
                        isDragging = false;
                        header.style.cursor = 'grab';
                    }
                });
            });

        });
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {

        const preventiveCheckbox =
            document.getElementById('is_preventive');

        const plannedDateContainer =
            document.getElementById('plannedDateContainer');

        const plannedDateInput =
            document.getElementById('planned_date');
        if (!preventiveCheckbox) {
            return;
        }

        if (!plannedDateContainer) {
            return;
        }


        function togglePlannedDate() {

            if (preventiveCheckbox.checked) {

                plannedDateContainer.style.display = 'block';

            } else {

                plannedDateContainer.style.display = 'none';

                if (plannedDateInput) {
                    plannedDateInput.value = '';
                }

            }

        }


        togglePlannedDate();


        preventiveCheckbox.addEventListener(
            'change',
            togglePlannedDate
        );

    });
</script>
<style>
    .contact-info-card {
        border-width: 2px !important;
        border-style: solid !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
    }

    .contact-info-header {
        border-bottom: 2px solid !important;
    }

    [data-bs-theme="light"] .contact-info-card {
        background-color: #f8f9fa !important;
        border-color: #dee2e6 !important;
    }

    [data-bs-theme="light"] .contact-info-header {
        background-color: #e9ecef !important;
        border-bottom-color: #dee2e6 !important;
        color: #495057 !important;
    }

    [data-bs-theme="dark"] .contact-info-card {
        background-color: var(--bs-body-bg) !important;
        border-color: var(--bs-border-color) !important;
    }

    [data-bs-theme="dark"] .contact-info-header {
        background-color: var(--bs-secondary-bg) !important;
        border-bottom-color: var(--bs-border-color) !important;
        color: var(--bs-body-color) !important;
    }

    @media (max-width: 768px) {
        .d-flex.gap-2 {
            flex-wrap: wrap;
            gap: 0.5rem !important;
        }

        .d-flex.gap-2 .btn {
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
        }
    }

    /* =========================================================
           TOM SELECT
           ========================================================= */
    .ts-wrapper {
        width: 100%;
    }

    .ts-dropdown {
        z-index: 99999 !important;
        box-sizing: border-box !important;
        width: 350px !important;
        height: 300px !important;
        min-width: 100px !important;
        min-height: 50px !important;
        max-width: none !important;
        max-height: none !important;
        overflow: hidden !important;
    }

    .ts-dropdown .ts-dropdown-content {
        width: 100% !important;
        height: 100% !important;
        max-height: none !important;
        overflow-x: auto !important;
        overflow-y: auto !important;
        box-sizing: border-box !important;
    }

    .ts-dropdown .option {
        white-space: normal !important;
        word-break: break-word;
    }

    /* Poignée de redimensionnement */
    .filter-dropdown-resizer {
        position: absolute;
        right: 0;
        bottom: 0;
        width: 18px;
        height: 18px;
        cursor: nwse-resize;
        z-index: 100000;
        background:
            linear-gradient(135deg,
                transparent 0%,
                transparent 45%,
                #999 46%,
                #999 52%,
                transparent 53%),
            linear-gradient(135deg,
                transparent 0%,
                transparent 62%,
                #999 63%,
                #999 69%,
                transparent 70%);
        opacity: 0.7;
    }

    .filter-dropdown-resizer:hover {
        opacity: 1;
    }

    /* Ne pas couper le dropdown */
    #filterForm,
    #filterForm .row,
    #filterForm .col-md-2,
    #filterForm .ts-wrapper {
        overflow: visible !important;
    }

    /* Style pour le champ contrat en warning */
    .is-warning {
        border-color: #ffc107 !important;
        border-width: 2px !important;
    }

    .is-warning:focus {
        border-color: #ffc107 !important;
        box-shadow: 0 0 0 0.25rem rgba(255, 193, 7, 0.25) !important;
    }
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>