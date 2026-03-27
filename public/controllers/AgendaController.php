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
class AgendaController {
    private $db;
    private $interventionModel;
    private $clientModel;
    private $siteModel;
    private $roomModel;
    private $userModel;

    public function __construct($db) {
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
    private function getAllStatuses() {
        $sql = "SELECT id, name, color, is_critical, created_at FROM intervention_statuses ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère toutes les priorités disponibles
     */
    private function getAllPriorities() {
        $sql = "SELECT id, name, color, created_at FROM intervention_priorities ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère tous les types d'intervention
     */
    private function getAllTypes() {
        $sql = "SELECT id, name, requires_travel, created_at FROM intervention_types ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Affiche la page principale de l'agenda
     */
    public function index() {
        // Vérifier les permissions
        checkInterventionManagementAccess();

        // Récupérer les filtres
        $filters = [
            'client_id' => $_GET['client_id'] ?? null,
            'site_id' => $_GET['site_id'] ?? null,
            'room_id' => $_GET['room_id'] ?? null,
            'status_id' => $_GET['status_id'] ?? null,
            'priority_id' => $_GET['priority_id'] ?? null,
            'technician_id' => $_GET['technician_id'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null
        ];

        // Récupérer les interventions planifiées
        $interventions = $this->interventionModel->getScheduledInterventions($filters);

        // Récupérer les données pour les filtres
        $clients = $this->clientModel->getAllClients();
        $sites = $this->siteModel->getAllSites();
        $rooms = $this->roomModel->getAllRooms();
        $technicians = $this->userModel->getTechnicians();
        $statuses = $this->getAllStatuses();
        $priorities = $this->getAllPriorities();
        $types = $this->getAllTypes();

        // Préparer les données pour le calendrier
        $calendarEvents = [];
        foreach ($interventions as $intervention) {
            $startDate = $intervention['date_planif'];
            $startTime = $intervention['heure_planif'] ?? '09:00:00';
            $startDateTime = $startDate . ' ' . $startTime;
            
            // Calculer la fin basée sur la durée
            $duration = $intervention['duration'] ?? 1;
            $endDateTime = date('Y-m-d H:i:s', strtotime($startDateTime . ' + ' . ($duration * 60) . ' minutes'));
            
            // Créer le titre avec client et numéro d'intervention
            $clientName = $intervention['client_name'] ?? 'Client inconnu';
            $interventionNumber = $intervention['reference'] ?? '#' . $intervention['id'];
            $displayTitle = $clientName . "\n" . $interventionNumber;
            
            $calendarEvents[] = [
                'id' => $intervention['id'],
                'title' => $displayTitle,
                'start' => $startDateTime,
                'end' => $endDateTime,
                'allDay' => false,
                'url' => BASE_URL . 'interventions/view/' . $intervention['id'],
                'extendedProps' => [
                    'reference' => $intervention['reference'],
                    'client' => $intervention['client_name'],
                    'site' => $intervention['site_name'],
                    'room' => $intervention['room_name'],
                    'technician' => $intervention['technician_name'],
                    'technician_id' => $intervention['technician_id'],
                    'status' => $intervention['status_name'],
                    'priority' => $intervention['priority_name'],
                    'type' => $intervention['type_name'],
                    'status_color' => $intervention['status_color'],
                    'priority_color' => $intervention['priority_color'],
                    'original_title' => $intervention['title'],
                    'planned_date' => $intervention['date_planif'] ? date('d/m/Y', strtotime($intervention['date_planif'])) : null,
                    'planned_time' => $intervention['heure_planif'],
                    'duration' => $intervention['duration']
                ]
            ];
        }

        // Rendre les variables disponibles dans la vue
        extract([
            'clients' => $clients,
            'sites' => $sites,
            'rooms' => $rooms,
            'technicians' => $technicians,
            'statuses' => $statuses,
            'priorities' => $priorities,
            'types' => $types,
            'calendarEvents' => $calendarEvents
        ]);
        
        // Inclure la vue
        require_once __DIR__ . '/../views/agenda/index.php';
    }

    /**
     * API pour récupérer les événements du calendrier
     */
    public function getEvents() {
        // Vérifier les permissions
        checkInterventionManagementAccess();

        // Récupérer les filtres
        $filters = [
            'client_id' => $_GET['client_id'] ?? null,
            'site_id' => $_GET['site_id'] ?? null,
            'room_id' => $_GET['room_id'] ?? null,
            'status_id' => $_GET['status_id'] ?? null,
            'priority_id' => $_GET['priority_id'] ?? null,
            'technician_id' => $_GET['technician_id'] ?? null,
            'date_from' => $_GET['start'] ?? null,
            'date_to' => $_GET['end'] ?? null
        ];

        // Traiter les filtres par technicien
        if (isset($_GET['filters'])) {
            $activeFilters = json_decode($_GET['filters'], true);
            if ($activeFilters) {
                $technicianIds = [];
                $showUnassigned = false;
                
                foreach ($activeFilters as $filter) {
                    if (strpos($filter, 'technician_') === 0) {
                        $technicianId = str_replace('technician_', '', $filter);
                        $technicianIds[] = $technicianId;
                    } elseif ($filter === 'sans_affectation') {
                        $showUnassigned = true;
                    }
                }
                
                if (!empty($technicianIds) || $showUnassigned) {
                    $filters['technician_filter'] = [
                        'technician_ids' => $technicianIds,
                        'show_unassigned' => $showUnassigned
                    ];
                }
            }
        }

        // Récupérer les interventions planifiées
        $interventions = $this->interventionModel->getScheduledInterventions($filters);

        // Préparer les données pour le calendrier
        $calendarEvents = [];
        foreach ($interventions as $intervention) {
            $startDate = $intervention['date_planif'];
            $startTime = $intervention['heure_planif'] ?? '09:00:00';
            $startDateTime = $startDate . ' ' . $startTime;
            
            // Calculer la fin basée sur la durée
            $duration = $intervention['duration'] ?? 1;
            $endDateTime = date('Y-m-d H:i:s', strtotime($startDateTime . ' + ' . ($duration * 60) . ' minutes'));
            
            // Créer le titre avec client et numéro d'intervention
            $clientName = $intervention['client_name'] ?? 'Client inconnu';
            $interventionNumber = $intervention['reference'] ?? '#' . $intervention['id'];
            $displayTitle = $clientName . "\n" . $interventionNumber;
            
            $calendarEvents[] = [
                'id' => $intervention['id'],
                'title' => $displayTitle,
                'start' => $startDateTime,
                'end' => $endDateTime,
                'allDay' => false,
                'url' => BASE_URL . 'interventions/view/' . $intervention['id'],
                'extendedProps' => [
                    'reference' => $intervention['reference'],
                    'client' => $intervention['client_name'],
                    'site' => $intervention['site_name'],
                    'room' => $intervention['room_name'],
                    'technician' => $intervention['technician_name'],
                    'status' => $intervention['status_name'],
                    'priority' => $intervention['priority_name'],
                    'type' => $intervention['type_name'],
                    'status_color' => $intervention['status_color'],
                    'priority_color' => $intervention['priority_color'],
                    'original_title' => $intervention['title'],
                    'planned_date' => $intervention['date_planif'] ? date('d/m/Y', strtotime($intervention['date_planif'])) : null,
                    'planned_time' => $intervention['heure_planif'],
                    'duration' => $intervention['duration']
                ]
            ];
        }

        // Retourner les événements en JSON
        header('Content-Type: application/json');
        echo json_encode($calendarEvents);
    }
} 