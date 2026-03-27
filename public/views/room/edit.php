<?php 
// $pageTitle = "Modifier la Salle - " . ($room['name'] ?? 'Salle inconnue'); // Défini dans le contrôleur
// $isAdmin = isAdmin(); // Assurez-vous que isAdmin est disponible
// Assurez-vous que $room, $site['client_id'] et $room['site_id'] (pour open_site_id) sont disponibles

setPageVariables('Modifier la Salle', 'room');
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">Modifier la Salle</h4>
        </div>
        <div class="ms-auto p-2 bd-highlight">
            <a href="<?php echo BASE_URL; ?>clients/edit/<?php echo $site['client_id']; ?>?open_site_id=<?php echo $room['site_id']; ?>#sites" class="btn btn-secondary me-2">
                <i class="bi bi-arrow-left me-1"></i> Retour
            </a>
            <button type="submit" form="roomForm" class="btn btn-primary me-2">
                <i class="<?php echo getIcon('save', 'bi bi-check-lg'); ?>"></i> Enregistrer
            </button>
            <?php if ($isAdmin ?? false): ?>
            <a href="<?= BASE_URL ?>room/delete/<?= $room['id'] ?>" 
               class="btn btn-danger" 
               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette salle ?');">
                <i class="<?php echo getIcon('delete', 'bi bi-trash'); ?>"></i> Supprimer
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])) : ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])) : ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($room): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Informations de la Salle</h5>
        </div>
        <div class="card-body">
            <form id="roomForm" action="<?= BASE_URL ?>room/edit/<?= $room['id'] ?>" method="POST">
                <?= csrf_field() ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nom de la salle <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($room['name'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="main_contact_id" class="form-label">Contact principal</label>
                            <select class="form-select select2" id="main_contact_id" name="main_contact_id">
                                <option value="">Sélectionner un contact</option>
                                <?php if (!empty($contacts)): ?>
                                    <?php foreach ($contacts as $contact): ?>
                                        <option value="<?= $contact['id'] ?>" <?= ($contact['id'] == ($room['main_contact_id'] ?? null)) ? 'selected' : '' ?>>
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
                            <textarea class="form-control" id="comment" name="comment" rows="3"><?= htmlspecialchars($room['comment'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="status" name="status" <?= ($room['status'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="status">Salle active</label>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-warning">Salle non trouvée.</div>
    <?php endif; ?>
</div>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?> 