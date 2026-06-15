<?php
/**
 * Vue des statistiques d'interventions
 */
require_once __DIR__ . '/../../includes/functions.php';

if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

setPageVariables('Statistiques', 'stats');
$priorities = $priorities ?? [];
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Valeurs par défaut
$statsByTab = $statsByTab ?? ['curatives' => ['total' => 0], 'preventives' => ['total' => 0], 'all' => ['total' => 0]];
$interventionsStats = $interventionsStats ?? [];
$technicians = $technicians ?? [];
$statuses = $statuses ?? [];
$filters = $filters ?? ['type' => 'curatives'];
$totalOnSiteMinutes = $totalOnSiteMinutes ?? 0;
$totalRemoteMinutes = $totalRemoteMinutes ?? 0;
$curativesOnSite = $curativesOnSite ?? 0;
$curativesRemote = $curativesRemote ?? 0;
$preventivesOnSite = $preventivesOnSite ?? 0;
$preventivesRemote = $preventivesRemote ?? 0;

$activeType = $filters['type'] ?? 'curatives';

$totalMinutes = $totalOnSiteMinutes + $totalRemoteMinutes;
$totalInterventions = count($interventionsStats);

$onSitePercent = $totalMinutes > 0 ? round(($totalOnSiteMinutes / $totalMinutes) * 100) : 0;
$remotePercent = 100 - $onSitePercent;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : ($_SESSION['stats_limit'] ?? 25);
$validLimits = [10, 25, 50, 100, 500];
if (!in_array($limit, $validLimits)) {
    $limit = 25;
}
$_SESSION['stats_limit'] = $limit;

function minutesToHuman(int $minutes): string
{
    if ($minutes <= 0)
        return '0h';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $m > 0 ? "{$h}h{$m}m" : "{$h}h";
}
?>

<div class="container-fluid flex-grow-1 container-p-y bg-light min-vh-100">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Gestion des Interventions</h4>
        <a href="<?= BASE_URL ?>interventions/create" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Ajouter une intervention
        </a>
    </div>

    <div class="d-flex justify-content-between bg-white rounded align-items-center flex-wrap gap-2 mb-3">

        <ul class="nav nav-tabs mb-0" id="statsTabs">
            <li class="nav-item">
                <a class="nav-link <?= $activeType === 'curatives' ? 'active' : '' ?>"
                    href="<?= BASE_URL ?>stats?type=curatives&limit=<?= $limit ?><?= !empty($filters['technician_id']) ? '&technician_id=' . $filters['technician_id'] : '' ?><?= !empty($filters['status_id']) ? '&status_id=' . $filters['status_id'] : '' ?><?= !empty($filters['priority_id']) ? '&priority_id=' . $filters['priority_id'] : '' ?>">
                    <i class="bi bi-tools me-1"></i>
                    Interventions Curatives
                    <span class="badge bg-primary ms-1">
                        <?= $statsByTab['curatives']['total'] ?? 0 ?>
                    </span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeType === 'preventives' ? 'active' : '' ?>"
                    href="<?= BASE_URL ?>stats?type=preventives&limit=<?= $limit ?><?= !empty($filters['technician_id']) ? '&technician_id=' . $filters['technician_id'] : '' ?><?= !empty($filters['status_id']) ? '&status_id=' . $filters['status_id'] : '' ?><?= !empty($filters['priority_id']) ? '&priority_id=' . $filters['priority_id'] : '' ?>">
                    <i class="bi bi-shield-check me-1"></i>
                    Interventions Préventives
                    <span class="badge bg-success ms-1">
                        <?= $statsByTab['preventives']['total'] ?? 0 ?>
                    </span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeType === 'all' ? 'active' : '' ?>"
                    href="<?= BASE_URL ?>stats?type=all&limit=<?= $limit ?><?= !empty($filters['technician_id']) ? '&technician_id=' . $filters['technician_id'] : '' ?><?= !empty($filters['status_id']) ? '&status_id=' . $filters['status_id'] : '' ?><?= !empty($filters['priority_id']) ? '&priority_id=' . $filters['priority_id'] : '' ?>">
                    <i class="bi bi-list-ul me-1"></i>
                    Toutes Les Interventions
                    <span class="badge bg-secondary ms-1">
                        <?= $statsByTab['all']['total'] ?? 0 ?>
                    </span>
                </a>
            </li>
        </ul>
        <div class="d-flex align-items-center gap-3  px-3 py-2">
            <span class="text-muted small fw-semibold">Temps passé :</span>
            <span class="fw-bold">
                <?= minutesToHuman($totalOnSiteMinutes + $totalRemoteMinutes) ?>
            </span>
            <span class="text-muted small fw-semibold ms-2">sur site :</span>
            <span class="fw-bold text-success">
                <?= minutesToHuman($totalOnSiteMinutes) ?>
            </span>
            <span class="text-muted small fw-semibold ms-2">Remote :</span>
            <span class="fw-bold text-primary">
                <?= minutesToHuman($totalRemoteMinutes) ?>
            </span>
            <canvas id="miniPieChart" width="40" height="40" title="Répartition Sur site / Remote"
                style="cursor:pointer; width:40px; height:40px;" data-bs-toggle="modal"
                data-bs-target="#pieChartModal"></canvas>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" action="<?= BASE_URL ?>stats" id="statsFilterForm">
                <input type="hidden" name="type" value="<?= htmlspecialchars($activeType) ?>">
                <div class="row g-2 align-items-end mb-2">
                    <div class="col-auto">
                        <label class="form-label fw-semibold mb-1 small">Filtrer par technicien :</label>
                        <select class="form-select form-select-sm bg-body text-body" name="technician_id"
                            style="width: 700px;" onchange="this.form.submit()">
                            <option value="">Tous les techniciens</option>
                            <?php foreach ($technicians as $tech): ?>
                                <option value="<?= $tech['id'] ?>" <?= ($filters['technician_id'] ?? '') == $tech['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tech['first_name'] . ' ' . $tech['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-primary btn-sm" onclick="setMyInterventions()">Mes
                            interventions</button>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
                    <a href="<?= BASE_URL ?>stats?type=<?= $activeType ?>&limit=<?= $limit ?><?= !empty($filters['technician_id']) ? '&technician_id=' . $filters['technician_id'] : '' ?>"
                        class="badge rounded-pill text-decoration-none px-3 py-2" style="
            background: <?= empty($filters['status_id']) ? '#613de4' : 'transparent' ?>;
            color: <?= empty($filters['status_id']) ? '#fff' : '#343a40' ?>;
            font-size: 0.78rem;
            font-weight: 600;
        ">
                        Tous les statuts
                    </a>
                    <?php foreach ($statuses as $status):
                        $isActive = ($filters['status_id'] ?? '') == $status['id'];
                        $color = $status['color'] ?? '#6c757d';
                        ?>
                        <a href="<?= BASE_URL ?>stats?type=<?= $activeType ?>&status_id=<?= $status['id'] ?><?= !empty($filters['technician_id']) ? '&technician_id=' . $filters['technician_id'] : '' ?>&limit=<?= $limit ?>"
                            class="badge rounded-pill text-decoration-none px-3 py-2" style="
                background: <?= $isActive ? $color : 'transparent' ?>;
                color: <?= $isActive ? '#fff' : $color ?>;
                border: 2px solid <?= $color ?>;
                font-size: 0.78rem;
                font-weight: 600;
            ">
                            <?= htmlspecialchars($status['name']) ?>
                        </a>
                    <?php endforeach; ?>
                    <span class="text-muted mx-1">|</span>

                    <a href="<?= BASE_URL ?>stats?type=<?= $activeType ?>&limit=<?= $limit ?><?= !empty($filters['technicien_id']) ? '&technicien_id=' . $filters['technicien_id'] : '' ?><?= !empty($filters['status_id']) ? '&status_id=' . $filters['status_id'] : '' ?>"
                        class="badge rounded text-decoration-none px-3 py-2" style="
            background: <?= empty($filters['priority_id']) ? '#686868' : 'transparent' ?>;
            color: <?= empty($filters['priority_id']) ? '#fff' : '#343a40' ?>;
            font-size: 0.78rem;
            font-weight: 600;
        ">
                        Toutes les priorités
                    </a>
                    <?php foreach ($priorities as $priority):
                        $isActive = ($filters['priority_id'] ?? '') == $priority['id'];
                        $color = $priority['color'] ?? '#6c757d';
                        ?>
                        <a href="<?= BASE_URL ?>stats?type=<?= $activeType ?>&priority_id=<?= $priority['id'] ?><?= !empty($filters['technician_id']) ? '&technician_id=' . $filters['technician_id'] : '' ?><?= !empty($filters['status_id']) ? '&status_id=' . $filters['status_id'] : '' ?>&limit=<?= $limit ?>"
                            class="badge rounded text-decoration-none px-3 py-2" style="
                background: <?= $isActive ? $color : 'transparent' ?>;
                color: <?= $isActive ? '#fff' : $color ?>;
                border: 2px solid <?= $color ?>;
                font-size: 0.78rem;
                font-weight: 600;
            ">
                            <?= htmlspecialchars($priority['name']) ?>
                        </a>
                    <?php endforeach; ?>

                </div>

            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <label class="text-muted small mb-0">Afficher :</label>
                <select id="rowsPerPage" class="form-select form-select-sm w-auto"
                    onchange="changeRowsPerPage(this.value)">
                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                    <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                    <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500</option>
                </select>
                <span class="text-muted small">entrées</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label class="text-muted small mb-0">Rechercher :</label>
                <input type="text" id="tableSearch" class="form-control form-control-sm" style="width:180px;"
                    placeholder="Rechercher..." oninput="filterTable(this.value)">
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($interventionsStats)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted"></i>
                    <p class="text-muted mt-2">Aucune intervention pour les critères sélectionnés.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="statsTable" class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:9%">RÉFÉRENCE</th>
                                <th style="width:18%">TITRE</th>
                                <th style="width:10%">CLIENT</th>
                                <th style="width:10%">SITE</th>
                                <th style="width:9%">SALLE</th>
                                <th style="width:7%">STATUT</th>
                                <th style="width:7%">PRIORITÉ</th>
                                <th style="width:9%">DATE PLANIFIÉE</th>
                                <th style="width:10%">TECHNICIEN</th>
                                <th style="width:9%">DATE CRÉATION</th>
                                <th style="width:5%">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="statsTableBody">
                            <?php foreach ($interventionsStats as $interv): ?>
                                <tr class="stats-row">
                                    <td>
                                        <a href="<?= BASE_URL ?>interventions/view/<?= $interv['id'] ?>"
                                            class="text-decoration-none fw-bold text-primary">
                                            <?= htmlspecialchars($interv['reference']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if (!empty($interv['title'])): ?>
                                            <span class="text-truncate d-inline-block" style="max-width: 220px;"
                                                title="<?= htmlspecialchars($interv['title']) ?>">
                                                <?= htmlspecialchars(mb_substr($interv['title'], 0, 60)) ?>
                                                <?= mb_strlen($interv['title']) > 60 ? '...' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($interv['client_name']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($interv['site_name']) ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($interv['room_name']) && $interv['room_name'] !== '-'): ?>
                                            <?= htmlspecialchars($interv['room_name']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: <?= $interv['status_color'] ?>; color: #fff;">
                                            <?= htmlspecialchars($interv['status_name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: <?= $interv['priority_color'] ?>; color: #fff;">
                                            <?= htmlspecialchars($interv['priority_name']) ?>
                                        </span>
                                    </td>
                                    <?php
                                    $plannedStartTime = $interv['planned_start_time'] ?? null;
                                    if (!empty($plannedStartTime)): ?>
                                        <td>
                                            <?= date('d/m/Y H:i', strtotime($plannedStartTime)) ?>
                                        </td>
                                    <?php else: ?>
                                        <td><span class="text-muted">-</span></td>
                                    <?php endif; ?>
                                    <td>
                                        <?php
                                        $techs = explode(',', $interv['technicians_list']);
                                        $techList = array_filter(array_map('trim', $techs), fn($t) => $t !== '' && $t !== '-');
                                        if (!empty($techList)):
                                            echo htmlspecialchars(implode(', ', $techList));
                                        else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($interv['created_at'])): ?>
                                            <?= date('d/m/Y H:i', strtotime($interv['created_at'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="<?= BASE_URL ?>interventions/view/<?= $interv['id'] ?>"
                                                class="btn btn-sm btn-outline-info p-1" title="Voir">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="<?= BASE_URL ?>interventions/edit/<?= $interv['id'] ?>"
                                                class="btn btn-sm btn-outline-secondary p-1" title="Modifier">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <td colspan="11" class="py-2">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div>
                                            Affichage de l'élément <span id="pageStart">1</span>
                                            à <span id="pageEnd">
                                                <?= min($limit, $totalInterventions) ?>
                                            </span>
                                            sur <span id="totalRows">
                                                <?= $totalInterventions ?>
                                            </span> éléments
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="d-flex justify-content-end align-items-center p-3 border-top">
                    <nav>
                        <ul class="pagination pagination-sm mb-0" id="paginationControls"></ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="pieChartModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pie-chart me-2"></i>Répartition détaillée des temps</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 text-center">
                        <h6 class="text-success mb-3">Temps sur site</h6>
                        <canvas id="modalOnSiteChart" height="200"></canvas>
                    </div>
                    <div class="col-md-6 text-center">
                        <h6 class="text-primary mb-3">Temps remote</h6>
                        <canvas id="modalRemoteChart" height="200"></canvas>
                    </div>
                </div>
                <hr>
                <div class="row text-center">
                    <div class="col-4">
                        <div class="small text-muted">Temps total</div>
                        <strong>
                            <?= minutesToHuman($totalMinutes) ?>
                        </strong>
                    </div>
                    <div class="col-4">
                        <div class="small text-muted">Sur site</div>
                        <strong class="text-success">
                            <?= minutesToHuman($totalOnSiteMinutes) ?>
                        </strong>
                        <div class="small">(
                            <?= $onSitePercent ?>%)
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="small text-muted">Remote</div>
                        <strong class="text-primary">
                            <?= minutesToHuman($totalRemoteMinutes) ?>
                        </strong>
                        <div class="small">(
                            <?= $remotePercent ?>%)
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <h6 class="text-center mb-3">Répartition par type d'intervention</h6>
                    <canvas id="typePieChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
    let currentPage = 1;
    let rowsPerPage = <?= $limit ?>;
    let allRows = Array.from(document.querySelectorAll('#statsTableBody .stats-row'));
    let filteredRows = [...allRows];

    function filterTable(query) {
        const q = query.toLowerCase();
        filteredRows = allRows.filter(row => row.textContent.toLowerCase().includes(q));
        currentPage = 1;
        displayPage(currentPage);
    }

    function displayPage(page) {
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;

        allRows.forEach(row => row.style.display = 'none');
        filteredRows.slice(start, end).forEach(row => row.style.display = '');

        const total = filteredRows.length;
        document.getElementById('pageStart').textContent = total > 0 ? start + 1 : 0;
        document.getElementById('pageEnd').textContent = Math.min(end, total);
        document.getElementById('totalRows').textContent = total;

        generatePaginationButtons(page, total);
        currentPage = page;
    }

    function generatePaginationButtons(currentPageNum, total) {
        const totalPages = Math.ceil(total / rowsPerPage);
        const container = document.getElementById('paginationControls');
        if (totalPages <= 1) { container.innerHTML = ''; return; }

        let html = '';
        html += `<li class="page-item ${currentPageNum === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${currentPageNum - 1}); return false;">&laquo;</a></li>`;

        let startPage = Math.max(1, currentPageNum - 2);
        let endPage = Math.min(totalPages, currentPageNum + 2);
        if (endPage - startPage < 4) {
            if (startPage === 1) endPage = Math.min(totalPages, 5);
            else if (endPage === totalPages) startPage = Math.max(1, totalPages - 4);
        }

        if (startPage > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(1); return false;">1</a></li>`;
            if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
        }
        for (let i = startPage; i <= endPage; i++) {
            html += `<li class="page-item ${i === currentPageNum ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a></li>`;
        }
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
            html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${totalPages}); return false;">${totalPages}</a></li>`;
        }
        html += `<li class="page-item ${currentPageNum === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${currentPageNum + 1}); return false;">&raquo;</a></li>`;

        container.innerHTML = html;
    }

    function changePage(page) {
        const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        if (page < 1 || page > totalPages) return;
        displayPage(page);
    }

    function changeRowsPerPage(value) {
        rowsPerPage = parseInt(value);
        const url = new URL(window.location.href);
        url.searchParams.set('limit', rowsPerPage);
        window.location.href = url.toString();
    }

    function setMyInterventions() {
        const form = document.getElementById('statsFilterForm');
        if (form) form.submit();
    }

    if (allRows.length > 0) {
        const urlParams = new URLSearchParams(window.location.search);
        const pageParam = urlParams.get('page');
        if (pageParam && !isNaN(parseInt(pageParam))) currentPage = parseInt(pageParam);
        displayPage(currentPage);
    }
    (function () {
        const totalOnSite = <?= $totalOnSiteMinutes ?>;
        const totalRemote = <?= $totalRemoteMinutes ?>;
        const curativesOnSiteH = <?= $curativesOnSite ?> / 60;
        const curativesRemoteH = <?= $curativesRemote ?> / 60;
        const preventivesOnSiteH = <?= $preventivesOnSite ?> / 60;
        const preventivesRemoteH = <?= $preventivesRemote ?> / 60;

        const miniCtx = document.getElementById('miniPieChart');
        if (miniCtx && (totalOnSite > 0 || totalRemote > 0)) {
            new Chart(miniCtx, {
                type: 'pie',  // Changé de 'doughnut' à 'pie'
                data: {
                    labels: ['Sur site', 'Remote'],
                    datasets: [{ data: [totalOnSite, totalRemote], backgroundColor: ['#1cc88a', '#4e73df'], borderWidth: 2, borderColor: '#fff' }]
                },
                options: { plugins: { legend: { display: false }, tooltip: { enabled: false } }, responsive: false }  // cutout supprimé
            });
        }

        const typeCtx = document.getElementById('typePieChart');
        if (typeCtx) {
            const curativeTotal = curativesOnSiteH + curativesRemoteH;
            const preventiveTotal = preventivesOnSiteH + preventivesRemoteH;
            if (curativeTotal > 0 || preventiveTotal > 0) {
                new Chart(typeCtx, {
                    type: 'pie',  // Changé de 'doughnut' à 'pie'
                    data: {
                        labels: ['Curatives', 'Préventives'],
                        datasets: [{ data: [curativeTotal, preventiveTotal], backgroundColor: ['#f6c23e', '#36b9cc'], borderWidth: 2, borderColor: '#fff' }]
                    },
                    options: { responsive: true, plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: ctx => `${ctx.label}: ${ctx.parsed.toFixed(1)}h` } } } }  // cutout supprimé
                });
            }
        }

        const onSiteCtx = document.getElementById('modalOnSiteChart');
        if (onSiteCtx && (curativesOnSiteH > 0 || preventivesOnSiteH > 0)) {
            new Chart(onSiteCtx, {
                type: 'pie',  // Changé de 'doughnut' à 'pie'
                data: { labels: ['Curatives', 'Préventives'], datasets: [{ data: [curativesOnSiteH, preventivesOnSiteH], backgroundColor: ['#f6c23e', '#36b9cc'], borderWidth: 2, borderColor: '#fff' }] },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } } }  // cutout supprimé
            });
        }

        const remoteCtx = document.getElementById('modalRemoteChart');
        if (remoteCtx && (curativesRemoteH > 0 || preventivesRemoteH > 0)) {
            new Chart(remoteCtx, {
                type: 'pie',  // Changé de 'doughnut' à 'pie'
                data: { labels: ['Curatives', 'Préventives'], datasets: [{ data: [curativesRemoteH, preventivesRemoteH], backgroundColor: ['#f6c23e', '#36b9cc'], borderWidth: 2, borderColor: '#fff' }] },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } } }  // cutout supprimé
            });
        }
    })();
</script>

<style>
    .card {
        border-radius: 10px;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    #miniPieChart {
        width: 40px !important;
        height: 40px !important;
    }

    .pagination {
        margin-bottom: 0;
    }

    .page-link {
        cursor: pointer;
    }

    .nav-tabs .nav-link {
        font-size: 0.875rem;
    }
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>