<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclure les fichiers de base
require_once __DIR__ . '/../../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables('Interventions', 'interventions_client');

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Préférence de visibilité des colonnes du tableau (point 2 du retour client)
$savedColumnVisibilityRaw = getUserPreference('datatable_interventionsTable_columnVisibility', '{}');
$savedColumnVisibility = json_decode($savedColumnVisibilityRaw, true);
if (!is_array($savedColumnVisibility)) {
    $savedColumnVisibility = [];
}

?>
<div class="container-fluid flex-grow-1 container-p-y">

    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">Mes interventions</h4>
        </div>

        <div class="ms-auto p-2 bd-highlight">
            <?php if (hasPermission('client_add_intervention')): ?>
                <a href="<?php echo BASE_URL; ?>interventions_client/add" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i> Créer une intervention
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtres rapides par statut -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card status-filter-card">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Tous les statuts -->
                        <a href="<?php echo BASE_URL; ?>interventions_client"
                            class="btn btn-outline-secondary btn-sm status-filter-btn <?php echo (!isset($_GET['status_id'])) ? 'active' : ''; ?>">
                            <span
                                class="badge bg-secondary me-1"><?php echo isset($statsByStatus) ? array_sum(array_column($statsByStatus, 'count')) : 0; ?></span>
                            Tous
                        </a>
                        <?php if (isset($statsByStatus)): ?>
                            <?php foreach ($statsByStatus as $statusStat): ?>
                                <a href="<?php echo BASE_URL; ?>interventions_client?status_id=<?php echo $statusStat['id']; ?>"
                                    class="btn btn-outline-secondary btn-sm status-filter-btn <?php echo (isset($_GET['status_id']) && $_GET['status_id'] == $statusStat['id']) ? 'active' : ''; ?>">
                                    <span class="badge me-1" style="background-color: <?php echo $statusStat['color']; ?>">
                                        <?php echo $statusStat['count']; ?>
                                    </span>
                                    <?php echo h($statusStat['name']); ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<!-- Export CSV -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header py-2">
                <h6 class="card-title mb-0">
                    <i class="bi bi-download me-2"></i>Exporter les interventions
                </h6>
            </div>
            <div class="card-body">
                <form action="<?php echo BASE_URL; ?>interventions_client/export" method="get" id="exportForm">
                    <input type="hidden" name="status_id" value="<?php echo htmlspecialchars($_GET['status_id'] ?? ''); ?>">
                    <input type="hidden" name="site_id" value="<?php echo htmlspecialchars($_GET['site_id'] ?? ''); ?>">
                    <input type="hidden" name="building_id" value="<?php echo htmlspecialchars($_GET['building_id'] ?? ''); ?>">
                    <input type="hidden" name="room_id" value="<?php echo htmlspecialchars($_GET['room_id'] ?? ''); ?>">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-bold mb-0">Début</label>
                            <input type="date" class="form-control bg-body text-body" name="date_start">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold mb-0">Fin</label>
                            <input type="date" class="form-control bg-body text-body" name="date_end">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold mb-0">Type d'intervention</label>
                            <select class="form-select bg-body text-body" name="type">
                                <option value="all">Toutes</option>
                                <option value="curative">Curatives</option>
                                <option value="preventive">Préventives</option>
                            </select>
                        </div>
                        <div class="col-md-3 ">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-download me-2"></i> Exporter en CSV
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Colonnes affichées dans le tableau (NOUVEAU - point 2 du retour client) -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header py-2">
                <h6 class="card-title mb-0">
                    <i class="bi bi-layout-three-columns me-2"></i>Colonnes affichées
                </h6>
            </div>
            <div class="card-body" id="columnVisibilityPanel">
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button"
                        id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-layout-three-columns me-2"></i>
                        Sélectionner les colonnes
                    </button>

                    <div class="dropdown-menu p-3" aria-labelledby="columnVisibilityDropdown"
                        style="min-width: 280px; max-height: 400px; overflow-y: auto;">
                        <?php
                        // Clé = doit correspondre à l'ordre exact des colonnes du <thead> ci-dessous
                        $visibleColumnsConfig = [
                            'reference' => 'Référence',
                            'title' => 'Titre',
                            'client' => 'Client',
                            'site' => 'Site',
                            'building' => 'Bâtiment',
                            'room' => 'Salle',
                            'status' => 'Statut',
                            'priority' => 'Priorité',
                            'date_planif' => 'Date planifiée',
                            'technician' => 'Technicien',
                            'ref_client' => 'Référence client',
                            'created_at' => 'Date de création',
                            'closed_at' => 'Date de clôture',
                        ];
                        $lockedColumnKeys = ['reference'];
                        $columnIndex = 0;
                        foreach ($visibleColumnsConfig as $key => $label):
                            $isLocked = in_array($key, $lockedColumnKeys, true);
                            $isChecked = $isLocked || ($savedColumnVisibility[$key] ?? true);
                        ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox"
                                    data-column-key="<?= h($key) ?>"
                                    data-column-index="<?= $columnIndex ?>"
                                    id="colvis_<?= h($key) ?>"
                                    <?= $isChecked ? 'checked' : '' ?>
                                    <?= $isLocked ? 'disabled' : '' ?>>
                                <label class="form-check-label" for="colvis_<?= h($key) ?>">
                                    <?= h($label) ?>
                                </label>
                            </div>
                        <?php $columnIndex++; endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <div class="table-responsive">
        <table id="interventionsTable" class="table table-striped table-hover dt-responsive">
            <thead>
                <tr>
                    <th>Date creation</th>
                    <th>Reference</th>
                    <th>Titre</th>
                    <th>Client</th>
                    <th>Site</th>
                    <th>Bâtiment</th>
                    <th>Salle</th>
                    <th>Statut</th>
                    <th>Priorite</th>
                    <th>Date planifiee</th>
                    <th>Technicien</th>
                    <th>Reference client</th>
                    <th>Date cloture</th>
                </tr>
            </thead>
            <tbody>
                <?php if (isset($interventions) && !empty($interventions)): ?>
                    <?php foreach ($interventions as $intervention): ?>
                        <tr>
                            
                            <td data-label="Date creation"
                                data-order="<?php echo isset($intervention['created_at']) ? strtotime($intervention['created_at']) : 0; ?>">
                                <?php echo date('d/m/Y H:i', strtotime($intervention['created_at'] ?? '')); ?>
                            </td>
                            <td data-label="Reference">
                                <a href="<?php echo BASE_URL; ?>interventions_client/view/<?php echo $intervention['id']; ?>"
                                    class="text-decoration-none">
                                    <?php echo htmlspecialchars($intervention['reference'] ?? ''); ?>
                                </a>
                            </td>
                            <td data-label="Titre"><?php echo htmlspecialchars($intervention['title'] ?? ''); ?></td>
                            <td data-label="Client"><?php echo htmlspecialchars($intervention['client_name'] ?? ''); ?></td>
                            <td data-label="Site"><?php echo htmlspecialchars($intervention['site_name'] ?? '-'); ?></td>
                            <td data-label="Bâtiment">
                                <?php echo htmlspecialchars($intervention['building_name'] ?? '-'); ?>
                            </td>
                            <td data-label="Salle"><?php echo htmlspecialchars($intervention['room_name'] ?? '-'); ?></td>
                            <td data-label="Statut" data-order="<?php echo $intervention['status_id'] ?? 0; ?>">
                                <span class="badge rounded-pill"
                                    style="background-color: <?php echo $intervention['status_color'] ?? ''; ?>">
                                    <?php echo htmlspecialchars($intervention['status_name'] ?? ''); ?>
                                </span>
                            </td>
                            <td data-label="Priorite" data-order="<?php echo $intervention['priority_id'] ?? 0; ?>">
                                <span class="badge rounded-pill"
                                    style="background-color: <?php echo $intervention['priority_color'] ?? ''; ?>">
                                    <?php echo htmlspecialchars($intervention['priority_name'] ?? ''); ?>
                                </span>
                            </td>
                            <td data-label="Date planifiee"
                                data-order="<?php echo isset($intervention['date_planif']) ? strtotime($intervention['date_planif']) : 0; ?>">
                                <?php echo !empty($intervention['date_planif']) ? date('d/m/Y', strtotime($intervention['date_planif'])) : '-'; ?>
                            </td>
                            <td data-label="Technicien">
                                <?php echo htmlspecialchars($intervention['technicians_names'] ?? '') . ' ' . htmlspecialchars($intervention['technician_last_name'] ?? ''); ?>
                            </td>
                            <td data-label="Reference client">
                                <?php echo htmlspecialchars($intervention['ref_client'] ?? '-'); ?>
                            </td>
                            <td data-label="Date cloture"
                                data-order="<?php echo isset($intervention['closed_at']) ? strtotime($intervention['closed_at']) : 0; ?>">
                                <?php echo !empty($intervention['closed_at']) ? date('d/m/Y H:i', strtotime($intervention['closed_at'])) : '-'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Laisser tbody vide. DataTables utilisera language.emptyTable -->
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
<script>
    window.serverSavedSettings = {
        interventionsTable_pageLength:
            <?= json_encode((int) getUserPreference('datatable_interventionsTable_pageLength', 10)) ?>,
        interventionsTable_columnVisibility:
            <?= json_encode($savedColumnVisibility) ?>
    };
    window.interventionsClientBaseUrl = '<?php echo BASE_URL; ?>';
</script>
<script src="<?php echo BASE_URL; ?>assets/js/datatable-persistence.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/interventions-datatable.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const exportForm = document.getElementById('exportForm');
        if (!exportForm) return;

        const baseUrl = window.interventionsClientBaseUrl;
        const exportBtn = exportForm.querySelector('button[type="submit"]');
        const dateStartInput = exportForm.querySelector('[name="date_start"]');
        const dateEndInput = exportForm.querySelector('[name="date_end"]');
        const typeSelect = exportForm.querySelector('[name="type"]');
        const columnVisibilityPanel = document.getElementById('columnVisibilityPanel');
        const exportColumnMap = {
            reference: 'reference',
            title: 'title',
            client: 'client_name',
            site: 'site_name',
            building: 'building_name',
            room: 'room_name',
            status: 'status_name',
            priority: 'priority_name',
            date_planif: 'date_planif',
            technician: 'technicians_names',
            ref_client: 'ref_client',
            created_at: 'created_at',
            closed_at: 'closed_at'
        };

        /**
         * Ajoute au formulaire uniquement les colonnes sélectionnées dans
         * « Colonnes affichées », afin que l'endpoint export reçoive columns[].
         */
        function syncExportColumns() {
            exportForm.querySelectorAll('input[data-export-column="1"]').forEach(function (input) {
                input.remove();
            });

            if (!columnVisibilityPanel) return;

            columnVisibilityPanel
                .querySelectorAll('input[type="checkbox"][data-column-key]:checked')
                .forEach(function (checkbox) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'columns[]';
                    const displayKey = checkbox.dataset.columnKey;
                    const exportKey = exportColumnMap[displayKey];
                    if (!exportKey) return;
                    input.value = exportKey;
                    input.dataset.exportColumn = '1';
                    exportForm.appendChild(input);
                });
        }

        let previewInFlight = null;

        /**
         * Charge l'aperçu filtré et met à jour le tableau, sans déclencher de téléchargement
         */
        function loadPreview() {
            const params = new URLSearchParams(new FormData(exportForm));

            if (previewInFlight) {
                previewInFlight.abort();
            }
            const controller = new AbortController();
            previewInFlight = controller;

            showTableLoading();

            fetch(baseUrl + 'interventions_client/previewExport?' + params.toString(), {
                credentials: 'include',
                signal: controller.signal
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        console.error("Erreur lors du chargement de l'aperçu:", data.error);
                        return;
                    }
                    renderPreviewTable(data.interventions, baseUrl);
                })
                .catch(error => {
                    if (error.name === 'AbortError') return;
                    console.error("Erreur lors de l'aperçu de l'export:", error);
                })
                .finally(() => {
                    if (previewInFlight === controller) {
                        previewInFlight = null;
                    }
                });
        }

        // Aperçu automatique dès qu'un filtre change
        [dateStartInput, dateEndInput, typeSelect].forEach(function (input) {
            if (input) {
                input.addEventListener('change', loadPreview);
            }
        });


        exportForm.addEventListener('submit', function (e) {
            e.preventDefault();
            syncExportColumns();

            const params = new URLSearchParams(new FormData(exportForm));

            const originalBtnHtml = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Export...';

            window.location.href = baseUrl + 'interventions_client/export?' + params.toString();

            setTimeout(function () {
                exportBtn.disabled = false;
                exportBtn.innerHTML = originalBtnHtml;
            }, 1500);
        });
    });

    function showTableLoading() {
        const table = document.querySelector('#interventionsTable');
        if (!table || typeof DataTable === 'undefined' || !DataTable.isDataTable(table)) return;
        const dt = new DataTable(table);
        dt.processing(true);
    }

    function renderPreviewTable(interventions, baseUrl) {
        const table = document.querySelector('#interventionsTable');

        if (!table || typeof DataTable === 'undefined' || !DataTable.isDataTable(table)) {
            console.error("Instance DataTable introuvable pour l'aperçu de l'export");
            return;
        }

        const dt = new DataTable(table);
        dt.clear();

        interventions.forEach(function (i) {
            const rowHtml = `<tr>
                <td data-label="Reference"><a href="${baseUrl}interventions_client/view/${i.id}" class="text-decoration-none">${escapeHtml(i.reference || '')}</a></td>
                <td data-label="Titre">${escapeHtml(i.title || '')}</td>
                <td data-label="Client">${escapeHtml(i.client_name || '')}</td>
                <td data-label="Site">${escapeHtml(i.site_name || '-')}</td>
                <td data-label="Bâtiment">${escapeHtml(i.building_name || '-')}</td>
                <td data-label="Salle">${escapeHtml(i.room_name || '-')}</td>
                <td data-label="Statut" data-order="${i.status_id || 0}">
                    <span class="badge rounded-pill" style="background-color: ${escapeHtml(i.status_color || '')}">${escapeHtml(i.status_name || '')}</span>
                </td>
                <td data-label="Priorite" data-order="${i.priority_id || 0}">
                    <span class="badge rounded-pill" style="background-color: ${escapeHtml(i.priority_color || '')}">${escapeHtml(i.priority_name || '')}</span>
                </td>
                <td data-label="Date planifiee">${escapeHtml(i.date_planif_formatted || '-')}</td>
                <td data-label="Technicien">${escapeHtml(i.technicians_names || '')}</td>
                <td data-label="Reference client">${escapeHtml(i.ref_client || '-')}</td>
                <td data-label="Date creation">${escapeHtml(i.created_at_formatted || '')}</td>
                <td data-label="Date cloture">${escapeHtml(i.closed_at_formatted || '-')}</td>
            </tr>`;

            const template = document.createElement('template');
            template.innerHTML = rowHtml.trim();
            dt.row.add(template.content.firstElementChild);
        });

        dt.processing(false);
        dt.draw();

        if (dt.responsive && typeof dt.responsive.recalc === 'function') {
            dt.responsive.recalc();
        }
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const panel = document.getElementById('columnVisibilityPanel');
        const table = document.getElementById('interventionsTable');
        if (!panel || !table || typeof DataTable === 'undefined') return;

        if (!DataTable.isDataTable(table)) {
            console.error('Le tableau interventionsTable n’est pas initialisé par DataTables.');
            return;
        }

        // Récupère l'instance DataTables déjà créée.
        const dt = new DataTable(table);
        const savedVisibility = {
            ...((window.serverSavedSettings || {}).interventionsTable_columnVisibility || {})
        };

        panel.querySelectorAll('input[type="checkbox"][data-column-index]').forEach(function (checkbox) {
            const key = checkbox.dataset.columnKey;
            const index = Number(checkbox.dataset.columnIndex);

            // Applique la préférence sauvegardée au chargement.
            if (!checkbox.disabled && Object.prototype.hasOwnProperty.call(savedVisibility, key)) {
                checkbox.checked = Boolean(savedVisibility[key]);
            }

            dt.column(index).visible(checkbox.checked, false);

            checkbox.addEventListener('change', function () {
                if (checkbox.disabled) return;

                savedVisibility[key] = checkbox.checked;
                dt.column(index).visible(checkbox.checked, false);
                dt.columns.adjust();

                if (dt.responsive && typeof dt.responsive.recalc === 'function') {
                    dt.responsive.recalc();
                }

                dt.draw(false);

                if (window.DataTablePersistence &&
                    typeof window.DataTablePersistence.setSetting === 'function') {
                    window.DataTablePersistence.setSetting(
                        'interventionsTable',
                        'columnVisibility',
                        JSON.stringify(savedVisibility)
                    );
                }
            });
        });

        dt.columns.adjust();
        if (dt.responsive && typeof dt.responsive.recalc === 'function') {
            dt.responsive.recalc();
        }
    });
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>