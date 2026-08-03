<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/InterventionModel.php';
require_once __DIR__ . '/../models/ClientModel.php';
require_once __DIR__ . '/../models/SiteModel.php';
require_once __DIR__ . '/../models/RoomModel.php';
require_once __DIR__ . '/../models/UserModel.php';

/**
 * Contrôleur pour la gestion de l'agenda des interventions
 */
class AgendaController
{
    private $db;
    private $interventionModel;
    private $clientModel;
    private $siteModel;
    private $roomModel;
    private $userModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->interventionModel = new InterventionModel($db);
        $this->clientModel = new ClientModel($db);
        $this->siteModel = new SiteModel($db);
        $this->roomModel = new RoomModel($db);
        $this->userModel = new UserModel($db);
    }

    /**
     * Récupère tous les statuts disponibles
     */
    private function getAllStatuses()
    {
        $sql = "SELECT id, name, color, is_critical, created_at FROM intervention_statuses ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère toutes les priorités disponibles
     */
    private function getAllPriorities()
    {
        $sql = "SELECT id, name, color, created_at FROM intervention_priorities ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère tous les types d'intervention
     */
    private function getAllTypes()
    {
        $sql = "SELECT id, name, requires_travel, created_at FROM intervention_types ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère la liste des techniciens ayant des interventions planifiées
     */
    private function getTechniciansWithScheduledInterventions()
    {
        $sql = "SELECT DISTINCT u.id, u.first_name, u.last_name 
                FROM users u
                INNER JOIN intervention_techniciens it ON u.id = it.technicien_id
                WHERE u.user_type_id = '1'
                AND it.start_time IS NOT NULL
                ORDER BY u.last_name, u.first_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Affiche la page principale de l'agenda
     */
    public function index()
    {
        checkInterventionManagementAccess();

        $technicians = $this->getTechniciansWithScheduledInterventions();

        $clients = $this->clientModel->getAllClients();
        $sites = $this->siteModel->getAllSites();
        $rooms = $this->roomModel->getAllRooms();
        $statuses = $this->getAllStatuses();
        $priorities = $this->getAllPriorities();
        $types = $this->getAllTypes();

        extract([
            'clients' => $clients,
            'sites' => $sites,
            'rooms' => $rooms,
            'technicians' => $technicians,
            'statuses' => $statuses,
            'priorities' => $priorities,
            'types' => $types
        ]);

        require_once __DIR__ . '/../views/agenda/index.php';
    }

    public function getEvents()
    {
        header('Content-Type: application/json');

        try {
            checkInterventionManagementAccess();

            $start = $_GET['start'] ?? null;
            $end = $_GET['end'] ?? null;
            $filtersParam = $_GET['filters'] ?? null;
            $filtersProvided = ($filtersParam !== null);

            $technicianFilter = [
                'technician_ids' => [],
                'show_unassigned' => false,
            ];

            if ($filtersProvided) {
                $decoded = json_decode($filtersParam, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $value) {
                        if (strpos($value, 'technician_') === 0) {
                            $technicianFilter['technician_ids'][] = (int) str_replace('technician_', '', $value);
                        } elseif ($value === 'sans_affectation') {
                            $technicianFilter['show_unassigned'] = true;
                        }
                    }
                }
            }
            $technicianFilter['_explicit'] = $filtersProvided;

            $scheduledEvents = $this->getScheduledInterventionsFromTechnicians($start, $end, $technicianFilter);
            $plannedEvents = $this->getPlannedInterventionsWithoutSchedule($start, $end, $technicianFilter);

            echo json_encode(array_merge($scheduledEvents, $plannedEvents));
        } catch (Exception $e) {
            error_log("Erreur dans AgendaController::getEvents: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
    /**
     * Récupère les interventions ayant une date prévisionnelle (planned_date)
     * mais n'ayant pas encore de technicien avec une heure de début fixée.
     * Prend en compte le(s) technicien(s) déjà assigné(s) à l'intervention
     * (même sans start_time) pour les afficher sous le bon filtre plutôt
     * que systématiquement sous "Sans affectation".
     */
    private function getPlannedInterventionsWithoutSchedule($start = null, $end = null, $technicianFilter = [])
    {
        $sql = "SELECT 
        i.id, i.reference, i.title, i.description, i.client_id, i.site_id, i.room_id,
        i.status_id, i.priority_id, i.type_id, i.planned_date, i.planned_time, i.is_preventive,
        c.name as client_name,
        s.name as site_name,
        r.name as room_name,
        its.name as status_name,
        its.color as status_color,
        ip.name as priority_name,
        ip.color as priority_color,
        it.name as type_name,
        GROUP_CONCAT(DISTINCT ite.technicien_id) as assigned_technician_ids,
        GROUP_CONCAT(DISTINCT CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') as assigned_technician_names
    FROM interventions i
    LEFT JOIN clients c ON i.client_id = c.id
    LEFT JOIN sites s ON i.site_id = s.id
    LEFT JOIN rooms r ON i.room_id = r.id
    LEFT JOIN intervention_statuses its ON i.status_id = its.id
    LEFT JOIN intervention_priorities ip ON i.priority_id = ip.id
    LEFT JOIN intervention_types it ON i.type_id = it.id
    LEFT JOIN intervention_techniciens ite ON ite.intervention_id = i.id
    LEFT JOIN users u ON u.id = ite.technicien_id
    WHERE i.planned_date IS NOT NULL
      AND i.status_id != 6
      AND NOT EXISTS (
          SELECT 1 FROM intervention_techniciens ite2
          WHERE ite2.intervention_id = i.id AND ite2.start_time IS NOT NULL
      )";

        $params = [];
        if ($start) {
            $sql .= " AND i.planned_date >= ?";
            $params[] = $start;
        }
        if ($end) {
            $sql .= " AND i.planned_date <= ?";
            $params[] = $end;
        }

        $sql .= " GROUP BY i.id ORDER BY i.planned_date ASC, i.planned_time ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filterIsExplicit = !empty($technicianFilter['_explicit']);
        $filteredTechIds = $technicianFilter['technician_ids'] ?? [];
        $showUnassigned = !empty($technicianFilter['show_unassigned']);

        $events = [];
        foreach ($results as $intervention) {
            $assignedIds = $intervention['assigned_technician_ids']
                ? array_map('intval', explode(',', $intervention['assigned_technician_ids']))
                : [];

            if ($filterIsExplicit) {
                if (empty($assignedIds)) {
                    if (!$showUnassigned) {
                        continue;
                    }
                } else {
                    if (empty(array_intersect($assignedIds, $filteredTechIds))) {
                        continue;
                    }
                }
            }

            $startDateTime = $intervention['planned_date'];
            if (!empty($intervention['planned_time'])) {
                $startDateTime .= 'T' . $intervention['planned_time'];
            }

            $events[] = [
                'id' => 'planned_' . $intervention['id'],
                'title' => $intervention['title'],
                'start' => $startDateTime,
                'allDay' => false,
                'backgroundColor' => '#fd7e14',
                'borderColor' => '#c2570a',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'reference' => $intervention['reference'],
                    'client' => $intervention['client_name'],
                    'site' => $intervention['site_name'],
                    'room' => $intervention['room_name'],
                    'status' => $intervention['status_name'],
                    'priority' => $intervention['priority_name'],
                    'priority_color' => $intervention['priority_color'],
                    'type' => $intervention['type_name'],
                    'original_title' => $intervention['title'],
                    'description' => $intervention['description'],
                    'planned_date' => $intervention['planned_date'],
                    'planned_time' => $intervention['planned_time'] ? substr($intervention['planned_time'], 0, 5) : '09:00',
                    'intervention_id' => $intervention['id'],
                    'technician' => $intervention['assigned_technician_names'] ?: 'Non attribué',
                ]
            ];
        }
        return $events;
    }


    /**
     * Récupère les interventions planifiées depuis la table intervention_techniciens
     */
    private function getScheduledInterventionsFromTechnicians($start = null, $end = null, $technicianFilter = [])
    {
        $sql = "SELECT DISTINCT 
                i.id,
                i.reference,
                i.title,
                i.description,
                i.client_id,
                i.site_id,
                i.room_id,
                i.status_id,
                i.priority_id,
                i.type_id,
                i.created_at,
                i.planned_date,
                c.name as client_name,
                s.name as site_name,
                r.name as room_name,
                its.name as status_name,
                its.color as status_color,
                ip.name as priority_name,
                ip.color as priority_color,
                it.name as type_name,
                ite.start_time,
                ite.end_time,
                ite.temps_passe as duration,
                ite.deplacement,
                ite.commentaire as technician_comment,
                u.id as technician_id,
                CONCAT(u.first_name, ' ', u.last_name) as technician_name
                FROM interventions i
                LEFT JOIN clients c ON i.client_id = c.id
                LEFT JOIN sites s ON i.site_id = s.id
                LEFT JOIN rooms r ON i.room_id = r.id
                LEFT JOIN intervention_statuses its ON i.status_id = its.id
                LEFT JOIN intervention_priorities ip ON i.priority_id = ip.id
                LEFT JOIN intervention_types it ON i.type_id = it.id
                INNER JOIN intervention_techniciens ite ON i.id = ite.intervention_id
                LEFT JOIN users u ON ite.technicien_id = u.id
                WHERE ite.start_time IS NOT NULL";

        $params = [];

        if ($start) {
            $sql .= " AND ite.start_time >= ?";
            $params[] = $start;
        }
        if ($end) {
            $sql .= " AND ite.start_time <= ?";
            $params[] = $end;
        }
        if (!empty($technicianFilter['_explicit'])) {
            $conditions = [];

            if (!empty($technicianFilter['technician_ids'])) {
                $placeholders = str_repeat('?,', count($technicianFilter['technician_ids']) - 1) . '?';
                $conditions[] = "u.id IN ($placeholders)";
                $params = array_merge($params, $technicianFilter['technician_ids']);
            }

            if (!empty($technicianFilter['show_unassigned'])) {
                $conditions[] = "u.id IS NULL";
            }

            if (!empty($conditions)) {
                $sql .= " AND (" . implode(" OR ", $conditions) . ")";
            } else {
                $sql .= " AND 1=0";
            }
        }

        $sql .= " ORDER BY ite.start_time ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $events = [];
        foreach ($results as $intervention) {
            $startDateTime = $intervention['start_time'];
            $endDateTime = $intervention['end_time'];

            if (!$endDateTime && $intervention['duration']) {
                $endDateTime = date('Y-m-d H:i:s', strtotime($startDateTime . ' + ' . $intervention['duration'] . ' minutes'));
            }

            $clientName = $intervention['client_name'] ?? 'Client inconnu';
            $interventionNumber = $intervention['reference'] ?? '#' . $intervention['id'];
            $displayTitle = $clientName . ' - ' . $interventionNumber;
            if ($intervention['technician_name']) {
                $displayTitle .= ' (' . $intervention['technician_name'] . ')';
            }
            $color = $intervention['status_color'] ?? '#f82213';
            $events[] = [
                'id' => $intervention['id'],
                'title' => $displayTitle,
                'start' => $startDateTime,
                'end' => $endDateTime,
                'allDay' => false,
                'color' => $color,
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'reference' => $intervention['reference'],
                    'reference_number' => $interventionNumber,
                    'client' => $intervention['client_name'],
                    'client_id' => $intervention['client_id'],
                    'site' => $intervention['site_name'],
                    'site_id' => $intervention['site_id'],
                    'room' => $intervention['room_name'],
                    'room_id' => $intervention['room_id'],
                    'technician' => $intervention['technician_name'],
                    'technician_id' => $intervention['technician_id'],
                    'status' => $intervention['status_name'],
                    'status_color' => $intervention['status_color'],
                    'priority' => $intervention['priority_name'],
                    'priority_color' => $intervention['priority_color'] ?? $color,
                    'type' => $intervention['type_name'],
                    'original_title' => $intervention['title'],
                    'description' => $intervention['description'],
                    'start_time' => $startDateTime,
                    'end_time' => $endDateTime,
                    'duration' => $intervention['duration'],
                    'deplacement' => $intervention['deplacement'],
                    'planned_date' => $intervention['planned_date'],
                    'planned_time' => $intervention['planned_time'] ?? '09:00',
                    'technician_comment' => $intervention['technician_comment']
                ]
            ];
        }

        return $events;
    }

    /**
     * Trouve la date la plus proche (future en priorité, sinon passée) où
     * une intervention existe pour les filtres actifs. Permet de retrouver
     * un technicien même sans connaître la date de son intervention.
     */
    public function getNearestEventDate()
    {
        header('Content-Type: application/json');
        try {
            checkInterventionManagementAccess();

            $referenceDate = $_GET['reference'] ?? date('Y-m-d');
            $filtersParam = $_GET['filters'] ?? null;

            $technicianFilter = [
                'technician_ids' => [],
                'show_unassigned' => false,
            ];
            if ($filtersParam !== null) {
                $decoded = json_decode($filtersParam, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $value) {
                        if (strpos($value, 'technician_') === 0) {
                            $technicianFilter['technician_ids'][] = (int) str_replace('technician_', '', $value);
                        } elseif ($value === 'sans_affectation') {
                            $technicianFilter['show_unassigned'] = true;
                        }
                    }
                }
            }

            if (empty($technicianFilter['technician_ids']) && empty($technicianFilter['show_unassigned'])) {
                echo json_encode(['date' => null]);
                exit;
            }

            $unionParts = [];
            $params = [];

            if (!empty($technicianFilter['technician_ids'])) {
                $placeholders = str_repeat('?,', count($technicianFilter['technician_ids']) - 1) . '?';
                $unionParts[] = "SELECT DATE(ite.start_time) as event_date
                              FROM intervention_techniciens ite
                              WHERE ite.start_time IS NOT NULL
                                AND ite.technicien_id IN ($placeholders)";
                $params = array_merge($params, $technicianFilter['technician_ids']);
            }

            if (!empty($technicianFilter['show_unassigned'])) {
                $unionParts[] = "SELECT i.planned_date as event_date
                              FROM interventions i
                              WHERE i.planned_date IS NOT NULL
                                AND i.status_id != 6
                                AND NOT EXISTS (
                                    SELECT 1 FROM intervention_techniciens ite2
                                    WHERE ite2.intervention_id = i.id AND ite2.start_time IS NOT NULL
                                )";
            }

            $unionSql = implode(' UNION ALL ', $unionParts);
            $stmt = $this->db->prepare("SELECT MIN(event_date) FROM ($unionSql) t WHERE event_date >= ?");
            $stmt->execute(array_merge($params, [$referenceDate]));
            $next = $stmt->fetchColumn();

            if ($next) {
                echo json_encode(['date' => $next, 'direction' => 'future']);
                exit;
            }

            $stmt = $this->db->prepare("SELECT MAX(event_date) FROM ($unionSql) t WHERE event_date < ?");
            $stmt->execute(array_merge($params, [$referenceDate]));
            $prev = $stmt->fetchColumn();

            echo json_encode(['date' => $prev ?: null, 'direction' => $prev ? 'past' : null]);
        } catch (Exception $e) {
            error_log("Erreur dans AgendaController::getNearestEventDate: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
}