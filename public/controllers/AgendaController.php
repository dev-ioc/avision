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
        // Vérifier les permissions
        checkInterventionManagementAccess();

        // Récupérer les techniciens pour les filtres
        $technicians = $this->getTechniciansWithScheduledInterventions();

        // Récupérer les données pour les filtres supplémentaires
        $clients = $this->clientModel->getAllClients();
        $sites = $this->siteModel->getAllSites();
        $rooms = $this->roomModel->getAllRooms();
        $statuses = $this->getAllStatuses();
        $priorities = $this->getAllPriorities();
        $types = $this->getAllTypes();

        // Rendre les variables disponibles dans la vue
        extract([
            'clients' => $clients,
            'sites' => $sites,
            'rooms' => $rooms,
            'technicians' => $technicians,
            'statuses' => $statuses,
            'priorities' => $priorities,
            'types' => $types
        ]);

        // Inclure la vue
        require_once __DIR__ . '/../views/agenda/index.php';
    }

    public function getEvents()
    {
        try {
            // Vérifier les permissions
            checkInterventionManagementAccess();

            // Récupérer la plage de dates
            $start = $_GET['start'] ?? null;
            $end = $_GET['end'] ?? null;

            // Log pour debug
            error_log("AgendaController::getEvents - start: $start, end: $end");

            // ... reste du code

        } catch (Exception $e) {
            error_log("Erreur dans AgendaController::getEvents: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
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

        // Filtrer par plage de dates
        if ($start) {
            $sql .= " AND ite.start_time >= ?";
            $params[] = $start;
        }
        if ($end) {
            $sql .= " AND ite.start_time <= ?";
            $params[] = $end;
        }

        // Filtrer par techniciens
        if (!empty($technicianFilter)) {
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
            }
        }

        $sql .= " ORDER BY ite.start_time ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formater pour FullCalendar
        $events = [];
        foreach ($results as $intervention) {
            $startDateTime = $intervention['start_time'];
            $endDateTime = $intervention['end_time'];

            // Si pas de date de fin, calculer basée sur la durée
            if (!$endDateTime && $intervention['duration']) {
                $endDateTime = date('Y-m-d H:i:s', strtotime($startDateTime . ' + ' . $intervention['duration'] . ' minutes'));
            }

            // Créer le titre
            $clientName = $intervention['client_name'] ?? 'Client inconnu';
            $interventionNumber = $intervention['reference'] ?? '#' . $intervention['id'];
            $displayTitle = $clientName . ' - ' . $interventionNumber;
            if ($intervention['technician_name']) {
                $displayTitle .= ' (' . $intervention['technician_name'] . ')';
            }

            // Déterminer la couleur selon le statut
            $color = $intervention['status_color'] ?? '#6c757d';

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
                    'priority_color' => $intervention['priority_color'],
                    'type' => $intervention['type_name'],
                    'original_title' => $intervention['title'],
                    'description' => $intervention['description'],
                    'start_time' => $startDateTime,
                    'end_time' => $endDateTime,
                    'duration' => $intervention['duration'],
                    'deplacement' => $intervention['deplacement'],
                    'technician_comment' => $intervention['technician_comment']
                ]
            ];
        }

        return $events;
    }
}