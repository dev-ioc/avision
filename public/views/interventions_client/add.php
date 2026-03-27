<?php
// Cette vue est appelée par le contrôleur InterventionsClientController->add()
// Les variables $sites, $contracts, $contacts, $statuses, $priorities, $defaultStatusId, $defaultPriorityId
// sont définies par le contrôleur

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Nouvelle Intervention',
    'interventions_client'
);

// Définir la page courante pour le menu
$currentPage = 'interventions_client';

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">

<div class="d-flex bd-highlight mb-3">
    <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Nouvelle Intervention</h4></div>

    <div class="ms-auto p-2 bd-highlight">
        <a href="<?php echo BASE_URL; ?>interventions_client" class="btn btn-secondary me-2">
            <i class="bi bi-arrow-left me-1"></i> Retour
        </a>
        
        <button type="submit" form="interventionForm" class="btn btn-primary">Créer l'intervention</button>
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

    <!-- Formulaire de création -->
    <div class="card">
        <div class="card-header py-2">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="card-title mb-0">
                        <span class="fw-bold me-3">Nouvelle référence</span>
                        <input type="text" class="form-control d-inline-block bg-body text-body" id="title" name="title" form="interventionForm" placeholder="Titre de l'intervention" required>
                    </h5>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label fw-bold mb-0 text-white">Date de création</label>
                            <input type="date" class="form-control bg-body text-body" id="created_date" name="created_date" value="<?= date('Y-m-d') ?>" form="interventionForm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold mb-0 text-white">Heure de création</label>
                            <input type="time" class="form-control bg-body text-body" id="created_time" name="created_time" value="<?= date('H:i') ?>" form="interventionForm">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body py-2">
            <form action="<?php echo BASE_URL; ?>interventions_client/store" method="post" id="interventionForm">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <!-- Colonne 1 : Site, Salle -->
                    <div class="col-md-4">
                        <div class="d-flex flex-column gap-2">
                             <!-- Site -->
                             <div>
                                 <label class="form-label fw-bold mb-0">Site</label>
                                 <select class="form-select bg-body text-body" id="site_id" name="site_id">
                                     <option value="">Sélectionner un site</option>
                                     <?php if (isset($sites) && is_array($sites)): ?>
                                         <?php foreach ($sites as $site): ?>
                                             <option value="<?= $site['id'] ?>">
                                                 <?= h($site['name'] ?? '') ?>
                                             </option>
                                         <?php endforeach; ?>
                                     <?php endif; ?>
                                 </select>
                             </div>

                            <!-- Salle -->
                            <div>
                                <label class="form-label fw-bold mb-0">Salle</label>
                                <select class="form-select bg-body text-body" id="room_id" name="room_id">
                                    <option value="">Sélectionner une salle</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Colonne 2 : Contrat, Priorité -->
                    <div class="col-md-4">
                        <div class="d-flex flex-column gap-2">
                             <!-- Contrat -->
                             <div>
                                 <label class="form-label fw-bold mb-0">Contrat associé</label>
                                 <select class="form-select bg-body text-body" id="contract_id" name="contract_id">
                                     <option value="">Sélectionner un contrat</option>
                                     <?php if (isset($contracts) && is_array($contracts)): ?>
                                         <?php foreach ($contracts as $contract): ?>
                                             <option value="<?= $contract['id'] ?>">
                                                 <?= h($contract['name'] ?? '') ?>
                                                 <?php if (!empty($contract['contract_type_id'])): ?>
                                                     (<?= h($contract['contract_type_name'] ?? '') ?>)
                                                 <?php endif; ?>
                                             </option>
                                         <?php endforeach; ?>
                                     <?php endif; ?>
                                 </select>
                             </div>

                            <!-- Priorité -->
                            <div>
                                <label class="form-label fw-bold mb-0">Priorité *</label>
                                <select class="form-select bg-body text-body" id="priority_id" name="priority_id" required>
                                    <option value="">Sélectionner une priorité</option>
                                    <?php if (isset($priorities) && is_array($priorities)): ?>
                                        <?php foreach ($priorities as $priority): ?>
                                            <?php 
                                            // Présélectionner la priorité par défaut (Normale)
                                            $isSelected = ($defaultPriorityId && $priority['id'] == $defaultPriorityId) ? 'selected' : '';
                                            ?>
                                            <option value="<?= $priority['id'] ?>" <?= $isSelected ?>>
                                                <?= h($priority['name'] ?? '') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Colonne 3 : Informations de contact -->
                    <div class="col-md-4">
                        <div class="d-flex flex-column gap-2">
                            <!-- Référence client -->
                            <div>
                                <label class="form-label fw-bold mb-0">Référence client</label>
                                <input type="text" class="form-control bg-body text-body" id="ref_client" name="ref_client" placeholder="Référence interne">
                            </div>
                        </div>
                    </div>

                    <!-- Description sur une ligne complète -->
                    <div class="col-12 mt-3">
                        <div class="card">
                            <div class="card-header py-2">
                                <h6 class="card-title mb-0">Description *</h6>
                            </div>
                            <div class="card-body py-2">
                                <textarea class="form-control bg-body text-body" id="description" name="description" rows="5" placeholder="Décrivez le problème ou la demande d'intervention..." required></textarea>
                            </div>
                        </div>
                    </div>

                     <!-- Informations de contact -->
                     <div class="col-12 mt-3">
                         <div class="card contact-info-card">
                             <div class="card-header py-2 contact-info-header">
                                 <h6 class="card-title mb-0 fw-bold">
                                     <i class="bi bi-person-lines-fill me-2"></i>Informations de contact
                                 </h6>
                             </div>
                             <div class="card-body py-3">
                                 <div class="row g-3">
                                     <div class="col-md-6">
                                         <label class="form-label fw-bold">Email de contact *</label>
                                         <input type="email" class="form-control bg-body text-body" id="contact_client" name="contact_client" 
                                                placeholder="email@exemple.com" 
                                                value="<?= h($_SESSION['user']['email'] ?? '') ?>" 
                                                required>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser BASE_URL pour JavaScript
    initBaseUrl('<?php echo BASE_URL; ?>');
    
    const siteSelect = document.getElementById('site_id');
    const roomSelect = document.getElementById('room_id');
    const contractSelect = document.getElementById('contract_id');
    
    // Charger les salles quand le site change
    siteSelect.addEventListener('change', function() {
        const siteId = this.value;
        const roomSelect = document.getElementById('room_id');
        
        // Réinitialiser la liste des salles
        roomSelect.innerHTML = '<option value="">Sélectionner une salle</option>';
        
        if (!siteId) {
            return;
        }
        
        // Utiliser l'endpoint client qui respecte les localisations
        fetch(BASE_URL + 'interventions_client/get_rooms?site_id=' + siteId, {
            credentials: 'include'
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur HTTP: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                // Vérifier si la réponse contient une erreur
                if (data.error) {
                    console.error('Erreur serveur:', data.error);
                    roomSelect.innerHTML = '<option value="">' + data.error + '</option>';
                    return;
                }
                
                // L'endpoint client retourne directement un tableau
                if (Array.isArray(data)) {
                    if (data.length === 0) {
                        roomSelect.innerHTML = '<option value="">Aucune salle disponible</option>';
                    } else {
                        data.forEach(room => {
                            const option = document.createElement('option');
                            option.value = room.id;
                            option.textContent = room.name;
                            roomSelect.appendChild(option);
                        });
                    }
                } else {
                    console.error('Format de réponse inattendu:', data);
                    roomSelect.innerHTML = '<option value="">Format de réponse invalide</option>';
                }
                
                // Appeler le callback pour mettre à jour le contrat
                if (typeof updateSelectedContract === 'function') {
                    updateSelectedContract('client_id', 'site_id', 'room_id', 'contract_id');
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des salles:', error);
                roomSelect.innerHTML = '<option value="">Erreur lors du chargement</option>';
            });
    });
    
    // Charger le contrat quand la salle change
    roomSelect.addEventListener('change', function() {
        updateSelectedContract('client_id', 'site_id', 'room_id', 'contract_id');
        // Pré-sélectionner le contrat associé à la salle sélectionnée
        const roomId = this.value;
        if (roomId) {
            fetch(`${BASE_URL}interventions_client/getContractByRoom/${roomId}`)
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
                .catch(error => console.error('Erreur lors de la récupération du contrat de la salle:', error));
        }
    });

     // Gestion du champ email de contact
     const contactClientInput = document.getElementById('contact_client');

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
    });
    
});
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
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
