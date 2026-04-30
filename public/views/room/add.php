<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue d'ajout d'une salle
 * Permet d'ajouter une nouvelle salle à un bâtiment
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Ajouter une Salle',
    'clients'
);

// Définir la page courante pour le menu
$currentPage = 'clients';

// Initialiser les variables pour éviter les erreurs
$clientId = $clientId ?? $_GET['client_id'] ?? null;
$building_id = $building_id ?? $_GET['building_id'] ?? null;
$siteId = $siteId ?? $_GET['site_id'] ?? null;
$sites = $sites ?? [];
$buildings = $buildings ?? [];
$contacts = $contacts ?? [];
$client = $client ?? null;

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">Ajouter une Salle</h4>
        </div>

        <div class="ms-auto p-2 bd-highlight">
            <?php
            $returnTo = $_GET['return_to'] ?? 'edit';
            if ($returnTo === 'view') {
                $backUrl = BASE_URL . 'clients/view/' . $clientId . '?active_tab=sites-tab';
            } else {
                $backUrl = BASE_URL . 'clients/edit/' . $clientId . ($siteId ? '?open_site_id=' . $siteId . '#sites' : '#sites');
            }
            ?>
            <a href="<?php echo $backUrl; ?>" class="btn btn-secondary me-2">
                <i class="bi bi-arrow-left me-1"></i> Retour
            </a>
            <button type="submit" form="roomForm" class="btn btn-primary">
                Enregistrer
            </button>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error'];
            unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success'];
            unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header py-2">
            <h5 class="card-title mb-0">Informations de la Salle</h5>
        </div>
        <div class="card-body py-2">
            <?php
            // Construire l'URL du formulaire
            $formAction = BASE_URL . 'room/add/';
            if ($building_id) {
                $formAction .= $building_id;
            } else {
                $formAction .= '0';
            }
            $queryParams = [];
            if (isset($clientId) && !$building_id) {
                $queryParams[] = 'client_id=' . $clientId;
            }
            if (isset($_GET['return_to'])) {
                $queryParams[] = 'return_to=' . $_GET['return_to'];
            }
            if (!empty($queryParams)) {
                $formAction .= '?' . implode('&', $queryParams);
            }
            ?>
            <form id="roomForm" action="<?= $formAction ?>" method="POST">
                <?= csrf_field() ?>

                <!-- Sélection du bâtiment (nouvelle hiérarchie) -->
                <?php if (!empty($buildings)): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="building_id" class="form-label">Bâtiment <span
                                        class="text-danger">*</span></label>
                                <select class="form-select" id="building_id" name="building_id" required>
                                    <option value="">Sélectionner un bâtiment</option>
                                    <?php foreach ($buildings as $building): ?>
                                        <option value="<?= $building['id'] ?>" <?= ($building_id && $building['id'] == $building_id) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($building['name']) ?> (Site:
                                            <?= htmlspecialchars($building['site_name'] ?? '') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                <?php elseif ($building_id): ?>
                    <!-- Bâtiment pré-défini : champ caché -->
                    <input type="hidden" name="building_id" value="<?= $building_id ?>">
                <?php endif; ?>

                <!-- Sélection du site (fallback pour compatibilité) -->
                <?php if (!empty($sites) && empty($buildings)): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="site_id" class="form-label">Site <span class="text-danger">*</span></label>
                                <select class="form-select" id="site_id" name="site_id" required>
                                    <option value="">Sélectionner un site</option>
                                    <?php foreach ($sites as $siteOption): ?>
                                        <option value="<?= $siteOption['id'] ?>" <?= ($siteId && $siteOption['id'] == $siteId) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($siteOption['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                <?php elseif ($siteId): ?>
                    <!-- Site pré-défini : champ caché -->
                    <input type="hidden" name="site_id" value="<?= $siteId ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nom de la salle <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="main_contact_id" class="form-label">Contact principal</label>
                            <select class="form-select select2" id="main_contact_id" name="main_contact_id">
                                <option value="">Sélectionner un contact</option>
                                <?php if (!empty($contacts)): ?>
                                    <?php foreach ($contacts as $contact): ?>
                                        <option value="<?= $contact['id'] ?>">
                                            <?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="mb-3">
                            <label for="comment" class="form-label">Commentaire</label>
                            <textarea class="form-control" id="comment" name="comment" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Initialiser Select2 pour le select des contacts
        if (typeof $.fn.select2 !== 'undefined' && $('#main_contact_id').length) {
            $('#main_contact_id').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Sélectionner un contact'
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>