<?php 
// $pageTitle = "Modifier le Site - " . ($site['name'] ?? 'Site inconnu'); // Défini dans le contrôleur
// $isAdmin = isAdmin(); // Assurez-vous que isAdmin est disponible

setPageVariables('Modifier le Site', 'site');
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">Modifier le Site</h4>
        </div>
        <div class="ms-auto p-2 bd-highlight">
            <a href="<?php echo BASE_URL; ?>clients/edit/<?php echo $site['client_id']; ?>?open_site_id=<?php echo $site['id']; ?>#sites" class="btn btn-secondary me-2">
                <i class="bi bi-arrow-left me-1"></i> Retour
            </a>
            <button type="submit" form="siteForm" class="btn btn-primary me-2">
                <i class="<?php echo getIcon('save', 'bi bi-check-lg'); ?>"></i> Enregistrer
            </button>
            <?php if ($isAdmin ?? false): // Vérifier si $isAdmin est défini et true ?>
            <a href="<?= BASE_URL ?>site/delete/<?= $site['id'] ?>" 
               class="btn btn-danger" 
               onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce site ? Cette action supprimera également toutes les salles associées.');">
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

    <?php if ($site): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Informations du Site</h5>
        </div>
        <div class="card-body">
            <form id="siteForm" action="<?= BASE_URL ?>site/edit/<?= $site['id'] ?>" method="POST">
                <?= csrf_field() ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nom du site <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($site['name'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="main_contact_id" class="form-label">Contact principal</label>
                            <select class="form-select select2" id="main_contact_id" name="main_contact_id">
                                <option value="">Sélectionner un contact</option>
                                <?php if (!empty($contacts)): ?>
                                    <?php foreach ($contacts as $contact): ?>
                                        <option value="<?= $contact['id'] ?>" <?= ($contact['id'] == ($site['main_contact_id'] ?? null)) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="address" class="form-label">Adresse</label>
                            <input type="text" class="form-control" id="address" name="address" value="<?= htmlspecialchars($site['address'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="postal_code" class="form-label">Code postal</label>
                            <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?= htmlspecialchars($site['postal_code'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="city" class="form-label">Ville</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?= htmlspecialchars($site['city'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="phone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($site['phone'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($site['email'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="mb-3">
                            <label for="comment" class="form-label">Commentaire</label>
                            <textarea class="form-control" id="comment" name="comment" rows="3"><?= htmlspecialchars($site['comment'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="status" name="status" <?= ($site['status'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="status">Site actif</label>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-warning">Site non trouvé.</div>
    <?php endif; ?>
</div>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?> 