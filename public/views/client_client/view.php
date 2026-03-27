<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue détaillée d'un site client
 * Affiche les détails complets d'un site avec ses salles
 */

// Vérification de l'accès
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Récupération des données
$site = $site ?? null;
$rooms = $rooms ?? [];

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Détails du site',
    'sites_client'
);

// Définir la page courante pour le menu
$currentPage = 'sites_client';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">Détails du site</h4>
        </div>
        <div class="ms-auto p-2 bd-highlight">
            <a href="<?php echo BASE_URL; ?>sites_client" class="btn btn-secondary">
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

    <?php if ($site): ?>
        <!-- Informations du site -->
        <div class="card mb-4">
            <div class="card-header py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><?php echo h($site['name']); ?></h5>
                    <span class="badge bg-primary">
                        <?php echo count($rooms); ?> salles
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr>
                                <th>Client</th>
                                <td><?php echo htmlspecialchars($site['client_name'] ?? ''); ?></td>
                            </tr>
                            <tr>
                                <th>Adresse</th>
                                <td><?php echo htmlspecialchars($site['address'] ?? ''); ?></td>
                            </tr>
                            <tr>
                                <th>Code Postal</th>
                                <td><?php echo htmlspecialchars($site['postal_code'] ?? ''); ?></td>
                            </tr>
                            <tr>
                                <th>Ville</th>
                                <td><?php echo htmlspecialchars($site['city'] ?? ''); ?></td>
                            </tr>
                            <tr>
                                <th>Téléphone</th>
                                <td><?php echo htmlspecialchars($site['phone'] ?? ''); ?></td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td><?php echo htmlspecialchars($site['email'] ?? ''); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($site['comment'])): ?>
                            <div class="card">
                                <div class="card-header py-2">
                                    <h6 class="card-title mb-0">Commentaire</h6>
                                </div>
                                <div class="card-body py-2">
                                    <p class="card-text"><?php echo nl2br(h($site['comment'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Contact principal du site -->
                        <div class="card mt-3">
                            <div class="card-header py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="card-title mb-0">Contact principal</h6>
                                    <?php if (canManageOwnContacts()): ?>
                                        <button type="button" class="btn btn-outline-primary btn-sm" 
                                                onclick="toggleContactEdit('site', <?php echo $site['id']; ?>)" 
                                                title="Modifier le contact principal">
                                            <i class="bi bi-pencil"></i> Modifier
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body py-2">
                                <div id="site-contact-display-<?php echo $site['id']; ?>">
                                    <?php if (!empty($site['primary_contact'])): ?>
                                        <div class="d-flex">
                                            <div class="avatar avatar-sm me-2">
                                                <div class="avatar-initial rounded-circle bg-label-primary">
                                                    <?php 
                                                    $initials = substr($site['primary_contact']['first_name'], 0, 1) . substr($site['primary_contact']['last_name'], 0, 1);
                                                    echo strtoupper($initials);
                                                    ?>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($site['primary_contact']['first_name'] . ' ' . $site['primary_contact']['last_name']); ?></h6>
                                                <?php if (!empty($site['primary_contact']['phone1'])) : ?>
                                                    <p class="mb-1 small">
                                                        <i class="bi bi-telephone me-1"></i> <?php echo htmlspecialchars($site['primary_contact']['phone1']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <?php if (!empty($site['primary_contact']['email'])) : ?>
                                                    <p class="mb-0 small">
                                                        <i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($site['primary_contact']['email']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center text-muted py-3">
                                            <i class="bi bi-person-x fs-1"></i>
                                            <p class="mt-2 mb-0">Aucun contact principal défini</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div id="site-contact-edit-<?php echo $site['id']; ?>" style="display: none;">
                                    <div class="mb-3">
                                        <label class="form-label">Sélectionner le contact principal</label>
                                        <select class="form-select" id="site-contact-select-<?php echo $site['id']; ?>">
                                            <option value="">-- Aucun contact --</option>
                                        </select>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-primary btn-sm" 
                                                onclick="saveContactSelection('site', <?php echo $site['id']; ?>)">
                                            <i class="bi bi-check"></i> Enregistrer
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm" 
                                                onclick="cancelContactEdit('site', <?php echo $site['id']; ?>)">
                                            <i class="bi bi-x"></i> Annuler
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des salles -->
        <div class="card">
            <div class="card-header py-2">
                <h5 class="card-title mb-0">Salles du site</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($rooms)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Contact principal</th>
                                    <th>Statut</th>
                                    <th>Commentaire</th>
                                    <?php if (canManageOwnContacts()): ?>
                                        <th>Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rooms as $room): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo h($room['name']); ?></strong>
                                        </td>
                                        <td>
                                            <div id="room-contact-display-<?php echo $room['id']; ?>">
                                                <?php 
                                                if (!empty($room['first_name']) && !empty($room['last_name'])) {
                                                    echo htmlspecialchars($room['first_name'] . ' ' . $room['last_name']);
                                                    if (!empty($room['phone1'])) {
                                                        echo '<br><small><i class="bi bi-telephone me-1"></i>' . h($room['phone1']) . '</small>';
                                                    }
                                                    if (!empty($room['email'])) {
                                                        echo '<br><small><i class="bi bi-envelope me-1"></i>' . h($room['email']) . '</small>';
                                                    }
                                                } else {
                                                    echo '<span class="text-muted">Aucun contact</span>';
                                                }
                                                ?>
                                            </div>
                                            <div id="room-contact-edit-<?php echo $room['id']; ?>" style="display: none;">
                                                <select class="form-select form-select-sm" id="room-contact-select-<?php echo $room['id']; ?>">
                                                    <option value="">-- Aucun contact --</option>
                                                </select>
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-primary btn-sm" 
                                                            onclick="saveContactSelection('room', <?php echo $room['id']; ?>)">
                                                        <i class="bi bi-check"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-secondary btn-sm" 
                                                            onclick="cancelContactEdit('room', <?php echo $room['id']; ?>)">
                                                        <i class="bi bi-x"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo ($room['status'] ?? 0) == 1 ? 'success' : 'danger'; ?>">
                                                <?php echo ($room['status'] ?? 0) == 1 ? 'Actif' : 'Inactif'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if (!empty($room['comment'])) {
                                                echo h($room['comment']);
                                            } else {
                                                echo '<span class="text-muted">Aucun commentaire</span>';
                                            }
                                            ?>
                                        </td>
                                        <?php if (canManageOwnContacts()): ?>
                                            <td>
                                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                                        onclick="toggleContactEdit('room', <?php echo $room['id']; ?>)" 
                                                        title="Modifier le contact principal">
                                                    <i class="bi bi-person"></i>
                                                </button>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Aucune salle enregistrée pour ce site.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Site non trouvé ou vous n'avez pas accès à ce site.
        </div>
    <?php endif; ?>
</div>

<script>
// Cache pour les contacts chargés
let contactsCache = null;

// Fonction pour basculer entre l'affichage et l'édition
function toggleContactEdit(type, id) {
    const displayElement = document.getElementById(`${type}-contact-display-${id}`);
    const editElement = document.getElementById(`${type}-contact-edit-${id}`);
    
    if (displayElement && editElement) {
        displayElement.style.display = 'none';
        editElement.style.display = 'block';
        
        // Charger les contacts si pas encore fait
        if (!contactsCache) {
            loadContactsForSelect(type, id);
        } else {
            populateSelect(type, id);
        }
    }
}

// Fonction pour annuler l'édition
function cancelContactEdit(type, id) {
    const displayElement = document.getElementById(`${type}-contact-display-${id}`);
    const editElement = document.getElementById(`${type}-contact-edit-${id}`);
    
    if (displayElement && editElement) {
        displayElement.style.display = 'block';
        editElement.style.display = 'none';
    }
}

// Fonction pour charger les contacts
function loadContactsForSelect(type, id) {
    const selectElement = document.getElementById(`${type}-contact-select-${id}`);
    if (selectElement) {
        selectElement.innerHTML = '<option value="">Chargement...</option>';
    }
    
    fetch('<?php echo BASE_URL; ?>contactClient/getContacts')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                contactsCache = data.contacts;
                populateSelect(type, id);
            } else {
                if (selectElement) {
                    selectElement.innerHTML = '<option value="">Erreur de chargement</option>';
                }
            }
        })
        .catch(error => {
            if (selectElement) {
                selectElement.innerHTML = '<option value="">Erreur de chargement</option>';
            }
        });
}

// Fonction pour remplir le select
function populateSelect(type, id) {
    const selectElement = document.getElementById(`${type}-contact-select-${id}`);
    if (!selectElement || !contactsCache) return;
    
    let html = '<option value="">-- Aucun contact --</option>';
    
    contactsCache.forEach(contact => {
        const contactText = `${contact.first_name} ${contact.last_name}`;
        const contactDetails = [];
        
        if (contact.fonction) contactDetails.push(contact.fonction);
        if (contact.email) contactDetails.push(contact.email);
        if (contact.phone1) contactDetails.push(contact.phone1);
        
        const fullText = contactDetails.length > 0 
            ? `${contactText} (${contactDetails.join(', ')})`
            : contactText;
            
        html += `<option value="${contact.id}">${fullText}</option>`;
    });
    
    selectElement.innerHTML = html;
}

// Fonction pour sauvegarder la sélection
function saveContactSelection(type, id) {
    const selectElement = document.getElementById(`${type}-contact-select-${id}`);
    if (!selectElement) return;
    
    const contactId = selectElement.value;
    
    fetch('<?php echo BASE_URL; ?>contactClient/setPrimaryContact', {
        method: 'POST',
            headers: {
                'X-CSRF-Token': '<?= csrf_token() ?>',
                'Content-Type': 'application/json',
            },
        body: JSON.stringify({
            type: type,
            id: id,
            contact_id: contactId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recharger la page pour afficher les changements
            location.reload();
        } else {
            alert('Erreur : ' + data.message);
        }
    })
    .catch(error => {
        alert('Erreur lors de la sauvegarde');
    });
}
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?>
