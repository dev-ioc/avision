<?php
// Les données sont récupérées par le contrôleur SettingsController
// Variables disponibles : $allowedExtensions, $blacklistedExtensions
// Les variables de page sont déjà définies par le contrôleur

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">
                <i class="bi bi-file-earmark-arrow-up me-2 me-1"></i>Extensions de fichiers autorisées
            </h4>
        </div>
        <div class="ms-auto p-2 bd-highlight">
            <a href="<?= BASE_URL ?>settings" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2 me-1"></i>Retour aux paramètres
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

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-gear text-primary me-2 me-1"></i>
                        Configuration des extensions autorisées
                    </h5>
                    <small class="text-muted">Configuration globale pour tout le site</small>
                </div>
                <div class="card-body">
                    <!-- Extensions autorisées -->
                    <div class="mb-4">
                        <h6>Extensions configurées :</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Extension</th>
                                        <th>Type MIME</th>
                                        <th>Description</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allowedExtensions as $ext): ?>
                                    <tr>
                                        <td><code><?= h($ext['extension']) ?></code></td>
                                        <td><?= htmlspecialchars($ext['mime_type'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($ext['description'] ?? '') ?></td>
                                        <td>
                                            <?php if ($ext['is_active']): ?>
                                                <span class="badge bg-success">Actif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <?php if ($ext['is_active']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                            onclick="toggleExtension(<?= $ext['id'] ?>, 0)" 
                                                            title="Désactiver">
                                                        <i class="bi bi-pause me-1"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success" 
                                                            onclick="toggleExtension(<?= $ext['id'] ?>, 1)" 
                                                            title="Activer">
                                                        <i class="bi bi-play me-1"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteExtension(<?= $ext['id'] ?>, '<?= h($ext['extension']) ?>')" 
                                                        title="Supprimer">
                                                    <i class="bi bi-trash me-1"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Ajouter une extension -->
                    <div class="mb-4">
                        <h6>Ajouter une extension :</h6>
                        <form method="POST">
                       <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <div class="row">
                                <div class="col-md-3">
                                    <label for="extension" class="form-label">Extension</label>
                                    <input type="text" class="form-control" id="extension" name="extension" 
                                           placeholder="ex: odt" maxlength="10" required>
                                    <div class="form-text">Lettres et chiffres uniquement</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="mime_type" class="form-label">Type MIME (optionnel)</label>
                                    <input type="text" class="form-control" id="mime_type" name="mime_type" 
                                           placeholder="ex: text/plain">
                                </div>
                                <div class="col-md-3">
                                    <label for="description" class="form-label">Description (optionnel)</label>
                                    <input type="text" class="form-control" id="description" name="description" 
                                           placeholder="ex: Fichier de configuration">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-plus me-2 me-1"></i>Ajouter
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Extensions interdites -->
                    <div class="alert alert-warning">
                        <h6><i class="bi bi-exclamation-triangle me-2 me-1"></i>Extensions interdites pour des raisons de sécurité :</h6>
                        <small class="text-muted">
                            <?= implode(', ', $blacklistedExtensions) ?>
                        </small>
                        <br><small class="text-muted mt-2">
                            Ces extensions ne peuvent pas être ajoutées car elles représentent un risque de sécurité.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleExtension(extensionId, isActive) {
    if (!confirm('Êtes-vous sûr de vouloir modifier le statut de cette extension ?')) {
        return;
    }
    
    fetch('<?= BASE_URL ?>settings/fileExtensions', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': '<?= csrf_token() ?>',
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=toggle&extension_id=' + extensionId + '&is_active=' + isActive
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erreur lors de la modification');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de la modification');
    });
}

function deleteExtension(extensionId, extensionName) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer définitivement l\'extension "' + extensionName + '" ?\n\nCette action ne peut pas être annulée.')) {
        return;
    }
    
    fetch('<?= BASE_URL ?>settings/fileExtensions', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': '<?= csrf_token() ?>',
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=delete&extension_id=' + extensionId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erreur lors de la suppression : ' + (data.message || 'Erreur inconnue'));
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de la suppression');
    });
}

// Validation côté client pour l'ajout d'extension
document.getElementById('extension').addEventListener('input', function() {
    const extension = this.value.toLowerCase();
    
    // Nettoyer l'extension (lettres et chiffres uniquement)
    const cleanExtension = extension.replace(/[^a-z0-9]/g, '');
    if (cleanExtension !== extension) {
        this.value = cleanExtension;
    }
});
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 