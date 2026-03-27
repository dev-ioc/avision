<?php
require_once __DIR__ . '/../../includes/functions.php';

// Vérification de l'accès - Utiliser le nouveau système de permissions
if (!isset($_SESSION['user']) || !canModifyInterventions()) {
    $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour créer une intervention.";
    header('Location: ' . BASE_URL . 'dashboard');
    exit;
}

// Définir le titre de la page pour le header
$pageTitle = "Nouvelle intervention";

// Inclure le header
include_once __DIR__ . '/../../includes/header.php';

require_once __DIR__ . '/../../includes/functions.php';

// Vérification des permissions pour modifier les interventions
if (!canModifyInterventions()) {
    $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour créer une intervention.";
    header('Location: ' . BASE_URL . 'interventions');
    exit;
}

setPageVariables(
    'Nouvelle Intervention',
    'interventions'
);

// Définir la page courante pour le menu
$currentPage = 'interventions';

// Récupérer le client_id depuis l'URL si présent
$selectedClientId = $_GET['client_id'] ?? null;

// Récupérer les informations du client si un client_id est fourni
$selectedClient = null;
if ($selectedClientId) {
    // Chercher d'abord dans le tableau $clients si disponible
    if (isset($clients) && is_array($clients)) {
        foreach ($clients as $c) {
            if (isset($c['id']) && $c['id'] == $selectedClientId) {
                $selectedClient = $c;
                break;
            }
        }
    }
    
    // Si le client n'a pas été trouvé dans $clients, le charger depuis le modèle
    if (!$selectedClient) {
        require_once __DIR__ . '/../../models/ClientModel.php';
        global $db;
        $clientModel = new ClientModel($db);
        $selectedClient = $clientModel->getClientById($selectedClientId);
    }
}

// Définir les breadcrumbs personnalisés pour l'ajout d'intervention
$GLOBALS['customBreadcrumbs'] = generateInterventionAddBreadcrumbs($selectedClient);

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">

<div class="d-flex bd-highlight mb-3">
    <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Nouvelle Intervention</h4></div>

    <div class="ms-auto p-2 bd-highlight">
        <?php 
        $returnTo = $_GET['return_to'] ?? 'index';
        $clientId = $_GET['client_id'] ?? null;
        $returnUrl = ($returnTo === 'view' && $clientId) ? 
            BASE_URL . 'clients/view/' . $clientId . '?active_tab=interventions-tab' : 
            BASE_URL . 'interventions/curatives'; // Par défaut, retourner vers les curatives
        ?>
        <a href="<?php echo $returnUrl; ?>" class="btn btn-secondary me-2">
            <i class="bi bi-arrow-left me-1"></i> Retour
        </a>
        
        <button type="button" id="createButton" class="btn btn-primary">Créer l'intervention</button>
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
                            <small id="titleError" class="text-danger d-none">Le titre est obligatoire.</small>
                        </h5>
                      <script>
                        document.addEventListener('DOMContentLoaded', function () {

                            const titleInput = document.getElementById('title');
                            const titleError = document.getElementById('titleError');
                            const form = document.getElementById('interventionForm');

                            function validation (){
                              if (titleInput.value.trim() === '') {
                                titleError.classList.remove('d-none');
                                titleInput.classList.add('is-invalid');
                                return false;
                              } else {
                                titleError.classList.add('d-none');
                                titleInput.classList.remove('is-invalid');
                                return true;
                              }
                            }
                            validation();

                            titleInput.addEventListener('input', function () {
                                if (titleInput.value.trim() === '') {
                                    titleError.classList.remove('d-none');
                                    titleInput.classList.add('is-invalid');
                                } else {
                                    titleError.classList.add('d-none');
                                    titleInput.classList.remove('is-invalid');
                                }
                            });

                            form.addEventListener('submit', function (e) {
                                if (titleInput.value.trim() === '') {
                                    e.preventDefault();
                                    titleError.classList.remove('d-none');
                                    titleInput.classList.add('is-invalid');
                                }
                            });
                         });
                      </script>
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
            <form action="<?php echo BASE_URL; ?>interventions/store<?php echo isset($_GET['return_to']) ? '?return_to=' . $_GET['return_to'] : ''; ?>" method="post" id="interventionForm">
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
                                            <option value="<?= $client['id'] ?>" <?= ($selectedClientId && $client['id'] == $selectedClientId) ? 'selected' : '' ?>>
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
                                <select class="form-select bg-body text-body" id="type_id" name="type_id" required form="interventionForm" >
                                    <option value="">Sélectionner un type</option>
                                    <?php foreach ($types as $type): ?>
                                        <option value="<?= $type['id'] ?>">
                                            <?= h($type['name'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small id="typeError" class="text-danger d-none">Le type d'intervention est obligatoire.</small>
                                <script>
                                  document.addEventListener('DOMContentLoaded', function () {

                                      const typeInput = document.getElementById('type_id');
                                      const typeError = document.getElementById('typeError');
                                      const form = document.getElementById('interventionForm');

                                      function validateType() {
                                          if (typeInput.value === '') {
                                              typeError.classList.remove('d-none');
                                              typeInput.classList.add('is-invalid');
                                              return false;
                                          } else {
                                              typeError.classList.add('d-none');
                                              typeInput.classList.remove('is-invalid');
                                              return true;
                                          }
                                      }

                                      validateType();
                                      typeInput.addEventListener('change', validateType);
                                      form.addEventListener('submit', function (e) {
                                          if (!validateType()) {
                                              e.preventDefault();
                                          }
                                      });

                                  });
                                </script>
                            </div>

                            <!-- Déplacement -->
                            <div>
                                <label class="form-label fw-bold mb-0">Déplacement</label>
                                <select class="form-select bg-body text-body" id="type_requires_travel" name="type_requires_travel">
                                    <option value="0" selected>Non</option>
                                    <option value="1">Oui</option>
                                </select>
                            </div>

                            <!-- Contrat -->
                            <div>
                                <label class="form-label fw-bold mb-0">Contrat associé *</label>
                                <select class="form-select bg-body text-body" id="contract_id" name="contract_id" required form="interventionForm">
                                    <option value="">Sélectionner un contrat</option>
                                </select>
                                <small id="contractError" class="text-danger d-none">Le contrat est obligatoire.</small>
                                 <script>
                                    document.addEventListener('DOMContentLoaded', function () {

                                        const contractInput = document.getElementById('contract_id');
                                        const contractError = document.getElementById('contractError');
                                        const form = document.getElementById('interventionForm');

                                        function validateContract() {
                                            if (contractInput.value === '') {
                                                contractError.classList.remove('d-none');
                                                contractInput.classList.add('is-invalid');
                                                return false;
                                            } else {
                                                contractError.classList.add('d-none');
                                                contractInput.classList.remove('is-invalid');
                                                return true;
                                            }
                                        }

                                        validateContract();
                                        contractInput.addEventListener('change', validateContract);
                                        form.addEventListener('submit', function (e) {
                                            if (!validateContract()) {
                                                e.preventDefault();
                                            }
                                        });

                                    });
                                </script>
                            </div>
                        </div>
                    </div>

                    <!-- Colonne 3 : Statut, Priorité, Technicien -->
                    <div class="col-md-3">
                        <div class="d-flex flex-column gap-2">
                            <!-- Statut -->
                            <div>
                                <label class="form-label fw-bold mb-0">Statut *</label>
                                <select class="form-select bg-body text-body" id="status_id" name="status_id" required form="interventionForm">
                                    <option value="">Sélectionner un statut</option>
                                    <?php foreach ($statuses as $status): ?>
                                        <?php 
                                        // Présélectionner le statut "Nouveau" (généralement ID 1)
                                        $isSelected = ($status['name'] == 'Nouveau' || $status['id'] == 1) ? 'selected' : '';
                                        ?>
                                        <option value="<?= $status['id'] ?>" <?= $isSelected ?>>
                                            <?= h($status['name'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small id="statutError" class="text-danger d-none">Le statut est obligatoire.</small>
                                <script>
                                  document.addEventListener('DOMContentLoaded', function () {

                                      const statutInput = document.getElementById('status_id');
                                      const statutError = document.getElementById('statutError');
                                      const form = document.getElementById('interventionForm');

                                      function validation (){
                                        if (statutInput.value.trim() === '') {
                                            statutError.classList.remove('d-none');
                                            statutInput.classList.add('is-invalid');
                                            return false;
                                        } else {
                                            statutError.classList.add('d-none');
                                            statutInput.classList.remove('is-invalid');
                                            return true;
                                        }
                                      }
                                      validation();

                                      statutInput.addEventListener('input', function () {
                                          if (statutInput.value.trim() === '') {
                                              statutError.classList.remove('d-none');
                                              statutInput.classList.add('is-invalid');
                                          } else {
                                              statutError.classList.add('d-none');
                                              statutInput.classList.remove('is-invalid');
                                          }
                                      });

                                      form.addEventListener('submit', function (e) {
                                          if (!validation()) {
                                              e.preventDefault();
                                          }
                                      });

                                  }); 
                                </script>
                            </div>

                            <!-- Priorité -->
                            <div>
                                <label class="form-label fw-bold mb-0">Priorité *</label>
                                <select class="form-select bg-body text-body" id="priority_id" name="priority_id" required form="interventionForm">
                                    <option value="">Sélectionner une priorité</option>
                                    <?php foreach ($priorities as $priority): ?>
                                        <?php 
                                        // Présélectionner la priorité "Moyenne" (généralement ID 2)
                                        $isSelected = ($priority['name'] == 'Moyenne' || $priority['id'] == 2) ? 'selected' : '';
                                        ?>
                                        <option value="<?= $priority['id'] ?>" <?= $isSelected ?>>
                                            <?= h($priority['name'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small id="prioriError" class="text-danger d-none">La priorité est obligatoire.</small>
                                <script>
                                  document.addEventListener('DOMContentLoaded', function () {

                                      const prioriInput = document.getElementById('priority_id');
                                      const prioriError = document.getElementById('prioriError');
                                      const form = document.getElementById('interventionForm');

                                      function validation (){
                                        if (prioriInput.value.trim() === '') {
                                            prioriError.classList.remove('d-none');
                                            prioriInput.classList.add('is-invalid');
                                            return false;
                                        } else {
                                            prioriError.classList.add('d-none');
                                            prioriInput.classList.remove('is-invalid');
                                            return true;
                                        }
                                      }
                                      validation();

                                      prioriInput.addEventListener('input', function () {
                                          if (prioriInput.value.trim() === '') {
                                              prioriError.classList.remove('d-none');
                                              prioriInput.classList.add('is-invalid');
                                          } else {
                                              prioriError.classList.add('d-none');
                                              prioriInput.classList.remove('is-invalid');
                                          }
                                      });

                                      form.addEventListener('submit', function (e) {
                                          if (!validation()) {
                                              e.preventDefault();
                                          }
                                      });

                                  }); 
                                </script>
                            </div>

                            <!-- Technicien -->
                            <div>
                                <label class="form-label fw-bold mb-0">Technicien</label>
                                <select class="form-select bg-body text-body" id="technician_id" name="technician_id">
                                    <option value="">Sélectionner un technicien</option>
                                    <?php foreach ($technicians as $technician): ?>
                                        <option value="<?= $technician['id'] ?>">
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
                                <input type="date" class="form-control bg-body text-body" id="date_planif" name="date_planif">
                            </div>

                            <!-- Heure planifiée -->
                            <div>
                                <label class="form-label fw-bold mb-0">Heure planifiée</label>
                                <input type="time" class="form-control bg-body text-body" id="heure_planif" name="heure_planif">
                            </div>
                            
                            <!-- Durée -->
                            <div>
                                <label class="form-label fw-bold mb-0">Durée</label>
                                <select class="form-select bg-body text-body" id="duration" name="duration">
                                    <option value="">Sélectionner une durée</option>
                                    <?php foreach ($durations as $duration): ?>
                                        <option value="<?= $duration['duration'] ?>">
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
                                <textarea class="form-control bg-body text-body" id="description" name="description" rows="5"></textarea>
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
                                        <input type="text" class="form-control bg-body text-body" id="demande_par" name="demande_par" placeholder="Nom de la personne qui a demandé l'intervention">
                                        <small id="titleError" class="text-danger d-none">Le nom de demandeur est obligatoire.</small>
                  
                                      </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Référence client</label>
                                        <input type="text" class="form-control bg-body text-body" id="ref_client" name="ref_client" placeholder="Référence interne du client">
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
                                        <input type="email" class="form-control bg-body text-body" id="contact_client" name="contact_client" placeholder="email@exemple.com">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser BASE_URL pour JavaScript
    initBaseUrl('<?php echo BASE_URL; ?>');
    
    // Vérifier les permissions pour la création rapide
    const canModifyClients = <?php echo canModifyClients() ? 'true' : 'false'; ?>;
    
    const clientSelect = document.getElementById('client_id');
    const siteSelect = document.getElementById('site_id');
    const roomSelect = document.getElementById('room_id');
    const typeSelect = document.getElementById('type_id');
    const typeRequiresTravelSelect = document.getElementById('type_requires_travel');
    const contractSelect = document.getElementById('contract_id');
    
    // Charger automatiquement les sites et salles si un client est présélectionné
    <?php if ($selectedClientId): ?>
    if (clientSelect.value) {
        loadSites(clientSelect.value, 'site_id', null, null, function() {
            updateSelectedContract('client_id', 'site_id', 'room_id', 'contract_id');
        });
    }
    <?php endif; ?>
    
    // Utiliser les fonctions centralisées pour charger les sites et salles dynamiquement
    clientSelect.addEventListener('change', function() {
        loadSites(this.value, 'site_id', null, null, function() {
            updateSelectedContract('client_id', 'site_id', 'room_id', 'contract_id');
        });
    });
    
    siteSelect.addEventListener('change', function() {
        loadRooms(this.value, 'room_id', null, function() {
            updateSelectedContract('client_id', 'site_id', 'room_id', 'contract_id');
        });
    });
    
    roomSelect.addEventListener('change', function() {
        updateSelectedContract('client_id', 'site_id', 'room_id', 'contract_id');
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
                            }
                        }, 100);
                    }
                })
                .catch(error => console.error('Erreur lors de la récupération du contrat de la salle:', error));
        }
    });
    
    typeSelect.addEventListener('change', function() {
        updateTypeRequiresTravel('type_id', 'type_requires_travel', 'type_requires_travel');
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

    // Initialiser le champ de déplacement si un type est déjà sélectionné
    if (typeSelect && typeSelect.value) {
        updateTypeRequiresTravel('type_id', 'type_requires_travel', 'type_requires_travel');
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
    });

    // Gestion de la création rapide de client
    const quickCreateClientBtn = document.getElementById('quickCreateClientBtn');
    const quickCreateClientModal = new bootstrap.Modal(document.getElementById('quickCreateClientModal'));
    const saveQuickClientBtn = document.getElementById('saveQuickClientBtn');
    const quickCreateClientForm = document.getElementById('quickCreateClientForm');
    const clientSpinner = document.getElementById('clientSpinner');
    const clientIcon = document.getElementById('clientIcon');

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

    // Ouvrir la modale de création de client
    quickCreateClientBtn.addEventListener('click', function() {
        if (!canModifyClients) {
            alert('Vous n\'avez pas les permissions nécessaires pour créer un client.');
            return;
        }
        quickCreateClientForm.reset();
        quickCreateClientModal.show();
    });

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
});
</script>

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
                <p>Un technicien a été affecté à cette intervention. Souhaitez-vous lui envoyer un email de notification ?</p>
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
                    <i class="bi bi-check-lg me-1"></i>Créer l'intervention
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const createButton = document.getElementById('createButton');
    const form = document.getElementById('interventionForm');
    const technicianSelect = document.getElementById('technician_id');
    
    // Intercepter le clic sur le bouton Créer
    createButton.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Vérifier si un technicien est sélectionné
        const technicianId = technicianSelect.value ? parseInt(technicianSelect.value) : null;
        
        // Si un technicien est sélectionné, afficher la modale
        if (technicianId) {
            const modal = new bootstrap.Modal(document.getElementById('notifyTechnicianModal'));
            modal.show();
        } else {
            // Pas de technicien, soumettre directement
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