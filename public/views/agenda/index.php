<?php
require_once __DIR__ . '/../../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables('Agenda des Interventions', 'agenda');

// Définir la page courante pour le menu
$currentPage = 'agenda';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<!-- Page CSS -->
            <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/libs/flatpickr/flatpickr.css" />
            <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/libs/select2/select2.css" />
            <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/libs/quill/editor.css" />
            <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/libs/@form-validation/form-validation.css" />
            <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/fr.js"></script>

<!-- Content -->
<div class="container-fluid flex-grow-1 container-p-y">
  <div class="row">
    <div class="col-12">
      <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
          <h4 class="py-4 mb-6">Agenda des Interventions</h4>
        </div>
                            <div class="ms-auto p-2 bd-highlight">
                       <!-- Bouton supprimé - lecture seule -->
                     </div>
      </div>
    </div>
  </div>

  <div class="row">
    <!-- Calendar Sidebar -->
    <div class="col-md-3">
      <div class="card">
        <div class="card-body">
          <div class="px-3 pt-2">
            <!-- inline calendar (flatpicker) -->
            <div class="inline-calendar"></div>
          </div>
          <hr class="mb-4 mt-3" />
          <div class="px-3 pb-2">
            <!-- Filter -->
            <div>
              <h5>Filtres d'Interventions</h5>
            </div>

            <div class="form-check form-check-secondary mb-3">
              <input
                class="form-check-input select-all"
                type="checkbox"
                id="selectAll"
                data-value="all"
                checked />
              <label class="form-check-label" for="selectAll">Voir Tout</label>
            </div>

                                     <div class="app-calendar-events-filter text-heading">
                           <?php foreach ($technicians as $technician): ?>
                             <div class="form-check mb-3">
                               <input
                                 class="form-check-input input-filter"
                                 type="checkbox"
                                 id="select-technician-<?= $technician['id'] ?>"
                                 data-value="technician_<?= $technician['id'] ?>"
                                 data-technician-id="<?= $technician['id'] ?>"
                                 checked />
                               <label class="form-check-label" for="select-technician-<?= $technician['id'] ?>">
                                 <?= htmlspecialchars($technician['last_name'] . ' ' . $technician['first_name']) ?>
                               </label>
                             </div>
                           <?php endforeach; ?>
                           
                           <div class="form-check form-check-warning mb-3">
                             <input
                               class="form-check-input input-filter"
                               type="checkbox"
                               id="select-sans-affectation"
                               data-value="sans_affectation"
                               checked />
                             <label class="form-check-label" for="select-sans-affectation">Sans affectation</label>
                           </div>
                         </div>
          </div>
        </div>
      </div>
    </div>
    <!-- /Calendar Sidebar -->

    <!-- Calendar Content -->
    <div class="col-md-9">
      <div class="card">
        <div class="card-body pb-0">
          <!-- FullCalendar -->
          <div id="calendar"></div>
        </div>
      </div>
    </div>
    <!-- /Calendar Content -->
  </div>
</div>

            <!-- FullCalendar Offcanvas -->
            <div
              class="offcanvas offcanvas-end event-sidebar"
              tabindex="-1"
              id="addEventSidebar"
              aria-labelledby="addEventSidebarLabel">
              <div class="offcanvas-header border-bottom">
                <h5 class="offcanvas-title" id="addEventSidebarLabel">Détails de l'Intervention</h5>
                <button
                  type="button"
                  class="btn-close text-reset"
                  data-bs-dismiss="offcanvas"
                  aria-label="Close"></button>
              </div>
              <div class="offcanvas-body">
                <div class="event-details pt-0">
                        <div class="mb-3">
                    <label class="form-label fw-bold">Référence</label>
                    <div class="form-control-plaintext" id="eventReference">-</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-bold">Titre</label>
                    <div class="form-control-plaintext" id="eventTitle">-</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-bold">Statut</label>
                    <div class="form-control-plaintext" id="eventStatus">-</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-bold">Priorité</label>
                    <div class="form-control-plaintext" id="eventPriority">-</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-bold">Type d'intervention</label>
                    <div class="form-control-plaintext" id="eventType">-</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-bold">Client</label>
                    <div class="form-control-plaintext" id="eventClient">-</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-bold">Site</label>
                    <div class="form-control-plaintext" id="eventSite">-</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-bold">Salle</label>
                    <div class="form-control-plaintext" id="eventRoom">-</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-bold">Technicien</label>
                    <div class="form-control-plaintext" id="eventTechnician">-</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-bold">Date planifiée</label>
                    <div class="form-control-plaintext" id="eventPlannedDate">-</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-bold">Heure planifiée</label>
                    <div class="form-control-plaintext" id="eventPlannedTime">-</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-bold">Durée</label>
                    <div class="form-control-plaintext" id="eventDuration">-</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-bold">Description</label>
                    <div class="form-control-plaintext" id="eventDescription">-</div>
                  </div>
                  <div class="d-flex justify-content-center mt-4">
                    <a href="#" id="viewInterventionLink" class="btn btn-primary">
                      <i class="bx bx-eye me-2"></i>
                      Voir l'intervention
                    </a>
                  </div>
    </form>
  </div>
</div>

<!-- Vendors JS -->
<script src="<?= BASE_URL ?>assets/vendor/libs/@form-validation/popular.js"></script>
<script src="<?= BASE_URL ?>assets/vendor/libs/@form-validation/bootstrap5.js"></script>
<script src="<?= BASE_URL ?>assets/vendor/libs/@form-validation/auto-focus.js"></script>
<script src="<?= BASE_URL ?>assets/vendor/libs/select2/select2.js"></script>
<script src="<?= BASE_URL ?>assets/vendor/libs/moment/moment.js"></script>
<script src="<?= BASE_URL ?>assets/vendor/libs/flatpickr/flatpickr.js"></script>

<!-- Page JS -->
<script>
  // Définir BASE_URL pour JavaScript
  window.BASE_URL = '<?= BASE_URL ?>';
</script>
<script src="<?= BASE_URL ?>assets/js/app-calendar.js"></script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?> 