<?php
/**
 * Contrôleur pour la gestion des interventions
 */
require_once __DIR__ . '/../classes/Services/AttachmentService.php';

require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';

class InterventionController
{
    use AccessControlTrait;

    private $db;
    private $interventionModel;
    private $clientModel;
    private $siteModel;
    private $roomModel;
    private $buildingModel;
    private $userModel;
    private $contractModel;
    private $contactModel;
    private $durationModel;
    private $mailService;

    // Constantes pour la configuration du PDF
    const PDF_PAGE_ORIENTATION = 'P'; // P = Portrait, L = Landscape
    const PDF_UNIT = 'mm';
    const PDF_PAGE_FORMAT = 'A4';
    const PDF_CREATOR = 'VideoSonic Support';
    const PDF_MARGIN_LEFT = 15;
    const PDF_MARGIN_TOP = 15;
    const PDF_MARGIN_RIGHT = 15;
    const PDF_MARGIN_BOTTOM = 15;
    const PDF_FONT_NAME_MAIN = 'helvetica';
    const PDF_FONT_SIZE_MAIN = 10;
    const PDF_FONT_NAME_DATA = 'helvetica';
    const PDF_FONT_SIZE_DATA = 8;
    const PDF_FONT_MONOSPACED = 'courier';
    const PDF_IMAGE_SCALE_RATIO = 1.25;
    const HEAD_MAGNIFICATION = 1.1;
    const K_CELL_HEIGHT_RATIO = 1.25;
    const K_TITLE_MAGNIFICATION = 1.3;
    const K_SMALL_RATIO = 2 / 3;

    public function __construct($db)
    {
        $this->db = $db;

        // Charger les modèles nécessaires
        require_once __DIR__ . '/../models/InterventionModel.php';
        require_once __DIR__ . '/../models/ClientModel.php';
        require_once __DIR__ . '/../models/SiteModel.php';
        require_once __DIR__ . '/../models/RoomModel.php';
        require_once __DIR__ . '/../models/UserModel.php';
        require_once __DIR__ . '/../models/ContractModel.php';
        require_once __DIR__ . '/../models/ContactModel.php';
        require_once __DIR__ . '/../models/DurationModel.php';
        require_once __DIR__ . '/../classes/MailService.php';
        require_once __DIR__ . '/../models/BuildingModel.php';

        $this->interventionModel = new InterventionModel($db);
        $this->clientModel = new ClientModel($db);
        $this->siteModel = new SiteModel($db);
        $this->roomModel = new RoomModel($db);
        $this->userModel = new UserModel($db);
        $this->contractModel = new ContractModel($db);
        $this->contactModel = new ContactModel($db);
        $this->durationModel = new DurationModel($db);
        $this->mailService = new MailService($db);
        $this->buildingModel = new BuildingModel($db);

        // Charger le fichier d'autoload de TCPDF
        require_once __DIR__ . '/../vendor/TCPDF-6.6.2/tcpdf.php';
    }

    /**
     * Vérifie si l'utilisateur a le droit d'accéder aux interventions
     */

    /**
     * Retourne l'URL de la liste des interventions selon le type
     * @param int|null $priorityId ID de la priorité pour déterminer si préventive ou curative
     * @return string URL de la liste
     */
    private function getInterventionsListUrl($priorityId = null)
    {
        // Si une priorité est fournie, vérifier si c'est préventive
        if ($priorityId) {
            $sql = "SELECT id, name, color, created_at FROM intervention_priorities WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$priorityId]);
            $priority = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($priority && (stripos($priority['name'], 'préventif') !== false || stripos($priority['name'], 'preventive') !== false)) {
                return BASE_URL . 'interventions/preventives';
            }
        }

        // Par défaut, retourner vers les curatives
        return BASE_URL . 'interventions/curatives';
    }

    /**
     * Affiche la liste des interventions
     */
    public function index()
    {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer les filtres
        $filters = [
            'client_id' => $_GET['client_id'] ?? null,
            'site_id' => $_GET['site_id'] ?? null,
            'room_id' => $_GET['room_id'] ?? null,
            'status_id' => $_GET['status_id'] ?? null,
            'priority_id' => $_GET['priority_id'] ?? null,
            'search' => $_GET['search'] ?? null
        ];

        // Récupérer les priorités pour identifier les préventives
        $sql = "SELECT id, name, color, created_at FROM intervention_priorities ORDER BY id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $priorities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Identifier la priorité préventive
        $preventivePriorityId = null;
        foreach ($priorities as $priority) {
            if (stripos($priority['name'], 'préventif') !== false || stripos($priority['name'], 'preventive') !== false) {
                $preventivePriorityId = $priority['id'];
                break;
            }
        }

        // Déterminer l'onglet actif (par défaut: non-préventives)
        $activeTab = $_GET['tab'] ?? 'non-preventive';

        // Récupérer les interventions selon l'onglet actif
        $interventions = [];
        if ($activeTab === 'preventive' && $preventivePriorityId) {
            // Onglet préventives
            $filters['priority_id'] = $preventivePriorityId;
            $interventions = $this->interventionModel->getAll($filters);
        } elseif ($activeTab === 'all') {
            // Onglet toutes
            $interventions = $this->interventionModel->getAll($filters);
        } else {
            // Onglet non-préventives (par défaut)
            if ($preventivePriorityId) {
                $filters['exclude_priority_ids'] = [$preventivePriorityId];
            }
            $interventions = $this->interventionModel->getAll($filters);
        }

        // Récupérer les données pour les filtres
        $clients = $this->clientModel->getAllClientsWithStats();
        $sites = !empty($filters['client_id']) ? $this->siteModel->getSitesByClientId($filters['client_id']) : [];
        $buildings = !empty($filters['site_id']) ? $this->buildingModel->getBuildingsBySiteId($filters['site_id']) : [];
        $rooms = !empty($filters['building_id']) ? $this->roomModel->getRoomsByBuildingId($filters['building_id']) : [];
        $technicians = $this->userModel->getTechnicians();

        // Récupérer les statuts
        $statuses = $this->getAllStatuses();

        // Récupérer les statistiques globales par onglet (sans filtres)
        $statsByTab = [];

        // Statistiques globales pour non-préventives (sans filtres)
        if ($preventivePriorityId) {
            $globalNonPreventiveFilters = ['exclude_priority_ids' => [$preventivePriorityId]];
        } else {
            $globalNonPreventiveFilters = [];
        }
        $statsByTab['non-preventive'] = $this->interventionModel->getStats($globalNonPreventiveFilters);

        // Statistiques globales pour préventives (sans filtres)
        if ($preventivePriorityId) {
            $globalPreventiveFilters = ['priority_id' => $preventivePriorityId];
            $statsByTab['preventive'] = $this->interventionModel->getStats($globalPreventiveFilters);
        }

        // Statistiques globales pour toutes (sans filtres)
        $statsByTab['all'] = $this->interventionModel->getStats([]);

        // Récupérer les statistiques par statut pour les filtres rapides (selon l'onglet actif)
        $statsByStatus = [];
        if ($activeTab === 'preventive' && $preventivePriorityId) {
            // Statistiques pour l'onglet préventives
            $preventiveFilters = $filters;
            $preventiveFilters['priority_id'] = $preventivePriorityId;
            $statsByStatus = $this->interventionModel->getStatsByStatus($preventiveFilters);
        } elseif ($activeTab === 'all') {
            // Statistiques pour l'onglet toutes
            $statsByStatus = $this->interventionModel->getStatsByStatus($filters);
        } else {
            // Statistiques pour l'onglet non-préventives
            $nonPreventiveFilters = $filters;
            if ($preventivePriorityId) {
                $nonPreventiveFilters['exclude_priority_ids'] = [$preventivePriorityId];
            }
            $statsByStatus = $this->interventionModel->getStatsByStatus($nonPreventiveFilters);
        }

        // Vérifier la permission de gestion des interventions
        $canManageInterventions = $this->checkPermission('technicien', 'manage_interventions');

        // Charger la vue
        require_once __DIR__ . '/../views/interventions/index.php';
    }

    /**
     * Affiche la liste des interventions curatives
     */
    public function curatives()
    {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer les filtres
        $filters = [
            'client_id' => $_GET['client_id'] ?? null,
            'site_id' => $_GET['site_id'] ?? null,
            'building_id' => $_GET['building_id'] ?? null,
            'room_id' => $_GET['room_id'] ?? null,
            'status_id' => $_GET['status_id'] ?? null,
            'priority_id' => $_GET['priority_id'] ?? null,
            'search' => $_GET['search'] ?? null,
            'is_preventive' => 0
        ];

        // Récupérer les priorités pour identifier les préventives
        $sql = "SELECT id, name, color, created_at FROM intervention_priorities ORDER BY id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $priorities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Identifier la priorité préventive
        $preventivePriorityId = null;
        foreach ($priorities as $priority) {
            if (stripos($priority['name'], 'préventif') !== false || stripos($priority['name'], 'preventive') !== false) {
                $preventivePriorityId = $priority['id'];
                break;
            }
        }

        // Fixer le type d'intervention aux curatives (non-préventives)
        $activeTab = 'non-preventive';

        // Récupérer les interventions curatives
        $interventions = [];
        if ($preventivePriorityId) {
            $filters['exclude_priority_ids'] = [$preventivePriorityId];
        }
        $interventions = $this->interventionModel->getAll($filters);

        // Récupérer les données pour les filtres
        $clients = $this->clientModel->getAllClientsWithStats();
        $sites = !empty($filters['client_id']) ? $this->siteModel->getSitesByClientId($filters['client_id']) : [];
        $buildings = !empty($filters['site_id'])
            ? $this->buildingModel->getBuildingsBySiteId($filters['site_id'])
            : [];

        $rooms = !empty($filters['building_id'])
            ? $this->roomModel->getRoomsByBuildingId($filters['building_id'])
            : [];
        // $technicians = $this->userModel->getTechnicians();

        // Récupérer les statuts
        $statuses = $this->getAllStatuses();

        // Récupérer les statistiques globales (sans filtres)
        $statsByTab = [];

        // Statistiques globales pour non-préventives (sans filtres)
        if ($preventivePriorityId) {
            $globalNonPreventiveFilters = ['exclude_priority_ids' => [$preventivePriorityId]];
        } else {
            $globalNonPreventiveFilters = [];
        }
        $statsByTab['non-preventive'] = $this->interventionModel->getStats($globalNonPreventiveFilters);

        // Statistiques globales pour préventives (sans filtres) - pour affichage dans le menu
        if ($preventivePriorityId) {
            $globalPreventiveFilters = ['priority_id' => $preventivePriorityId];
            $statsByTab['preventive'] = $this->interventionModel->getStats($globalPreventiveFilters);
        }

        // Récupérer les statistiques par statut pour les filtres rapides
        $statsByStatus = [];
        $nonPreventiveFilters = $filters;
        if ($preventivePriorityId) {
            $nonPreventiveFilters['exclude_priority_ids'] = [$preventivePriorityId];
        }
        $statsByStatus = $this->interventionModel->getStatsByStatus($nonPreventiveFilters);

        // Vérifier la permission de gestion des interventions
        // $canManageInterventions = $this->checkPermission('technicien', 'manage_interventions');

        // Charger la vue
        require_once __DIR__ . '/../views/interventions/index.php';
    }

    /**
     * Affiche la liste des interventions préventives
     */
    public function preventives()
    {

        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer les filtres
        $filters = [
            'client_id' => $_GET['client_id'] ?? null,
            'site_id' => $_GET['site_id'] ?? null,
            'room_id' => $_GET['room_id'] ?? null,
            'status_id' => $_GET['status_id'] ?? null,
            'priority_id' => $_GET['priority_id'] ?? null,
            'search' => $_GET['search'] ?? null,
            'is_preventive' => 1
        ];

        // Récupérer les priorités pour identifier les préventives
        $sql = "SELECT id, name, color, created_at FROM intervention_priorities ORDER BY id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $priorities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Identifier la priorité préventive
        $preventivePriorityId = null;
        foreach ($priorities as $priority) {
            if (stripos($priority['name'], 'préventif') !== false || stripos($priority['name'], 'preventive') !== false) {
                $preventivePriorityId = $priority['id'];
                break;
            }
        }

        // Vérifier si les interventions préventives existent
        if (!$preventivePriorityId) {
            $_SESSION['error'] = "Aucune priorité préventive configurée.";
            header('Location: ' . BASE_URL . 'interventions/curatives');
            exit;
        }

        // Fixer le type d'intervention aux préventives
        $activeTab = 'preventive';

        // Récupérer les interventions préventives
        $filters['priority_id'] = $preventivePriorityId;
        $interventions = $this->interventionModel->getAll($filters);

        // Récupérer les données pour les filtres
        $clients = $this->clientModel->getAllClientsWithStats();
        $sites = !empty($filters['client_id']) ? $this->siteModel->getSitesByClientId($filters['client_id']) : [];
        $buildings = !empty($filters['site_id']) ? $this->buildingModel->getBuildingsBySiteId($filters['site_id']) : [];
        $rooms = !empty($filters['building_id']) ? $this->roomModel->getRoomsByBuildingId($filters['building_id']) : [];
        // $technicians = $this->userModel->getTechnicians();
        // Récupérer les statuts
        $statuses = $this->getAllStatuses();

        // Récupérer les statistiques globales (sans filtres)
        $statsByTab = [];

        // Statistiques globales pour non-préventives (sans filtres) - pour affichage dans le menu
        if ($preventivePriorityId) {
            $globalNonPreventiveFilters = ['exclude_priority_ids' => [$preventivePriorityId]];
        } else {
            $globalNonPreventiveFilters = [];
        }
        $statsByTab['non-preventive'] = $this->interventionModel->getStats($globalNonPreventiveFilters);

        // Statistiques globales pour préventives (sans filtres)
        $globalPreventiveFilters = ['priority_id' => $preventivePriorityId];
        $statsByTab['preventive'] = $this->interventionModel->getStats($globalPreventiveFilters);

        // Récupérer les statistiques par statut pour les filtres rapides
        $statsByStatus = [];
        $preventiveFilters = $filters;
        $preventiveFilters['priority_id'] = $preventivePriorityId;
        $statsByStatus = $this->interventionModel->getStatsByStatus($preventiveFilters);

        // Vérifier la permission de gestion des interventions
        // $canManageInterventions = $this->checkPermission('technicien', 'manage_interventions');

        // Charger la vue
        require_once __DIR__ . '/../views/interventions/index.php';
    }

    /**
     * Affiche les détails d'une intervention
     */
    public function view($id)
    {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($id);

        if (!$intervention) {
            // Rediriger vers la liste si l'intervention n'existe pas
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }

        // S'assurer que toutes les clés nécessaires existent
        $intervention = array_merge([
            'site_id' => null,
            'room_id' => null,
            'client_id' => null,
            // 'technicien_id' => null,
            'status_id' => null,
            'priority_id' => null,
            'type_id' => null,
            // 'duration' => null,
            'description' => null,
            'title' => null
        ], $intervention);

        // Récupérer le contrat associé directement via contract_id
        $contract = null;
        if (!empty($intervention['contract_id'])) {
            $contract = $this->contractModel->getContractById($intervention['contract_id']);
        }

        // Ajouter les informations du contrat pour le calcul JavaScript
        if ($contract && isContractTicketById($contract['id'])) {
            $intervention['contract_tickets_number'] = $contract['tickets_number'];
            $intervention['contract_tickets_remaining'] = $contract['tickets_remaining'];
        } else {
            $intervention['contract_tickets_number'] = 0;
            $intervention['contract_tickets_remaining'] = 0;
        }

        // Récupérer les commentaires
        $comments = $this->getComments($id);

        // Récupérer les pièces jointes
        $attachments = $this->getAttachments($id);

        // Récupérer l'historique
        $history = $this->getHistory($id);

        // Récupérer les priorités pour identifier les préventives
        $sql = "SELECT id, name, color, created_at FROM intervention_priorities ORDER BY id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $priorities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Identifier la priorité préventive
        $preventivePriorityId = null;
        foreach ($priorities as $priority) {
            if (stripos($priority['name'], 'préventif') !== false || stripos($priority['name'], 'preventive') !== false) {
                $preventivePriorityId = $priority['id'];
                break;
            }
        }

        // Charger la vue
        require_once __DIR__ . '/../views/interventions/view.php';
    }
    /**
     * Affiche le formulaire d'édition d'une intervention
     */
    public function edit($id)
    {
        if (empty($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        checkInterventionManagementAccess();

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($id);

        if (!$intervention) {
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }

        // Vérifier si c'est une intervention flash et ajouter un message
        if (isset($intervention['is_flash']) && $intervention['is_flash'] == 1 && $intervention['needs_completion'] == 1) {
            $_SESSION['info'] = "⚠️ Cette intervention a été créée rapidement. Veuillez compléter les informations manquantes : Site, Bâtiment, Salle, Description.";
        }

        // S'assurer que toutes les clés nécessaires existent
        $intervention = array_merge([
            'site_id' => null,
            'building_id' => null,
            'room_id' => null,
            'client_id' => null,
            'status_id' => null,
            'priority_id' => null,
            'type_id' => null,
            'description' => null,
            'title' => null,
            'is_preventive' => 0
        ], $intervention);

        // Vérifier si l'intervention est fermée
        if ($intervention['status_id'] == 6 && !isAdmin()) { // 6 = Fermé
            $_SESSION['error'] = "Impossible de modifier une intervention fermée.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        // Récupérer le contrat associé directement via contract_id
        $contract = null;
        if (!empty($intervention['contract_id'])) {
            $contract = $this->contractModel->getContractById($intervention['contract_id']);
        }

        // Définir les variables pour les formulaires
        $client_id = isset($intervention['client_id']) ? $intervention['client_id'] : null;
        $site_id = isset($intervention['site_id']) ? $intervention['site_id'] : null;
        $building_id = isset($intervention['building_id']) ? $intervention['building_id'] : null;
        $room_id = isset($intervention['room_id']) ? $intervention['room_id'] : null;

        // Récupérer les données pour les formulaires
        $clients = $this->clientModel->getAllClientsWithStats();

        // Récupérer les sites du client
        $sites = [];
        if ($client_id) {
            $sites = $this->siteModel->getSitesByClientId($client_id);
        }

        // Récupérer les bâtiments du site (tous les bâtiments du site, pas seulement celui sélectionné)
        $buildings = [];
        if ($site_id) {
            $buildings = $this->buildingModel->getBuildingsBySiteId($site_id);
        }

        // Récupérer les salles - priorité au bâtiment sélectionné, sinon au site
        $rooms = [];
        if ($building_id) {
            // Si un bâtiment est sélectionné, récupérer uniquement les salles de ce bâtiment
            $rooms = $this->roomModel->getRoomsByBuildingId($building_id);
        }

        // Récupérer les informations du bâtiment sélectionné pour l'affichage (même s'il n'est pas dans la liste)
        $selectedBuilding = null;
        if ($building_id && !empty($buildings)) {
            foreach ($buildings as $building) {
                if ($building['id'] == $building_id) {
                    $selectedBuilding = $building;
                    break;
                }
            }
        }

        // Récupérer les informations de la salle sélectionnée pour l'affichage
        $selectedRoom = null;
        if ($room_id && !empty($rooms)) {
            foreach ($rooms as $room) {
                if ($room['id'] == $room_id) {
                    $selectedRoom = $room;
                    break;
                }
            }
        }

        $technicians = $this->userModel->getTechnicians();

        // Récupérer les contrats du client pour le formulaire
        $contracts = [];
        if (!empty($client_id)) {
            $contracts = $this->contractModel->getContractsByClientId($client_id, $site_id, $room_id);
        }

        // Récupérer les statuts, priorités et types
        $statuses = $this->getAllStatuses();

        $sql = "SELECT id, name, color, created_at FROM intervention_priorities ORDER BY id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $priorities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT id, name, requires_travel, created_at FROM intervention_types ORDER BY name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les durées
        $durations = $this->durationModel->getAll();

        // Récupérer les commentaires
        $comments = $this->getComments($id);

        // Récupérer les pièces jointes
        $attachments = $this->getAttachments($id);

        // Récupérer l'historique
        $history = $this->getHistory($id);

        // Charger la vue avec toutes les variables nécessaires
        require_once __DIR__ . '/../views/interventions/edit.php';
    }

    /**
     * Génère un bon d'intervention au format PDF
     * @param array $intervention Les données de l'intervention
     * @return string Le chemin du fichier PDF généré
     */
    private function generateInterventionReport($intervention)
    {
        // Récupérer les commentaires marqués comme solution
        $sql = "SELECT id, intervention_id, comment, visible_by_client, is_solution, is_observation, pour_bon_intervention, created_by, created_at FROM intervention_comments 
                WHERE intervention_id = ? AND is_solution = 1 
                ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$intervention['id']]);
        $solutions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Créer le dossier de stockage s'il n'existe pas
        $uploadDir = __DIR__ . '/../../uploads/interventions/' . $intervention['id'];
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Générer un nom de fichier unique
        $fileName = 'bon_intervention_' . $intervention['id'] . '_' . date('Y-m-d_H-i-s') . '.pdf';
        $filePath = $uploadDir . '/' . $fileName;

        // Charger la classe InterventionPDF
        require_once __DIR__ . '/../classes/InterventionPDF.php';

        // Créer et générer le PDF
        $pdf = new InterventionPDF();
        $pdf->generate($intervention, $solutions);
        $pdf->Output($filePath, 'F');

        // Ajouter le PDF comme pièce jointe via le modèle
        $data = [
            'nom_fichier' => $fileName,
            'chemin_fichier' => 'uploads/interventions/' . $intervention['id'] . '/' . $fileName,
            'type_fichier' => 'pdf',
            'taille_fichier' => filesize($filePath),
            'commentaire' => 'Bon d\'intervention généré automatiquement',
            'masque_client' => 0, // Visible par les clients
            'created_by' => $_SESSION['user']['id']
        ];

        // Ajouter la pièce jointe avec le type de liaison 'bi' (Bon d'Intervention)
        $pieceJointeId = $this->interventionModel->addPieceJointeWithType($intervention['id'], $data, 'bi');

        // Enregistrer l'action dans l'historique
        $sql = "INSERT INTO intervention_history (
                    intervention_id, field_name, old_value, new_value, changed_by, description
                ) VALUES (
                    :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':intervention_id' => $intervention['id'],
            ':field_name' => 'Pièce jointe',
            ':old_value' => '',
            ':new_value' => $fileName,
            ':changed_by' => $_SESSION['user']['id'],
            ':description' => "Bon d'intervention généré : " . $fileName
        ]);

        return 'uploads/interventions/' . $intervention['id'] . '/' . $fileName;
    }

    /**
     * Met à jour une intervention
     */
    public function update($id)
    {
        // Code de débogage temporaire
        error_log("DEBUG - Début de update() pour l'intervention $id");
        error_log("DEBUG - POST data: " . print_r($_POST, true));
        error_log("=== DEBUG CSRF ===");
        error_log("POST csrf_token: " . ($_POST['csrf_token'] ?? 'NON PRESENT'));
        error_log("SESSION csrf_token: " . ($_SESSION['csrf_token'] ?? 'NON PRESENT'));
        error_log("Session ID: " . session_id());
        error_log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? ''));

        // Vérifier manuellement le token avant toute chose
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            error_log("CSRF MISMATCH !!!");
            $_SESSION['error'] = "Token CSRF invalide. Veuillez réessayer.";
            header('Location: ' . BASE_URL . 'interventions/edit/' . $id);
            exit;
        }

        // Vérifier les permissions
        checkInterventionManagementAccess();

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($id);
        error_log("DEBUG - Intervention récupérée: " . print_r($intervention, true));

        if (!$intervention) {
            // Rediriger vers la liste si l'intervention n'existe pas
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }

        // S'assurer que toutes les clés nécessaires existent
        $intervention = array_merge([
            'site_id' => null,
            'building_id' => null,
            'room_id' => null,
            'client_id' => null,
            'status_id' => null,
            'priority_id' => null,
            'type_id' => null,
            'description' => null,
            'title' => null
        ], $intervention);

        // Vérifier si l'intervention est fermée
        if ($intervention['status_id'] == 6 && !isAdmin()) { // 6 = Fermé
            $_SESSION['error'] = "Impossible de modifier une intervention fermée.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        // Récupérer les données du formulaire
        $data = [
            'title' => $_POST['title'] ?? $intervention['title'],
            'client_id' => $_POST['client_id'] ?? $intervention['client_id'],
            'site_id' => $_POST['site_id'] ?? $intervention['site_id'],
            'building_id' => $_POST['building_id'] ?? $intervention['building_id'],
            'room_id' => $_POST['room_id'] ?? $intervention['room_id'],
            'status_id' => $_POST['status_id'] ?? $intervention['status_id'],
            'priority_id' => $_POST['priority_id'] ?? $intervention['priority_id'],
            'type_id' => $_POST['type_id'] ?? $intervention['type_id'],
            'description' => $_POST['description'] ?? $intervention['description'],
            'demande_par' => $_POST['demande_par'] ?? $intervention['demande_par'],
            'ref_client' => $_POST['ref_client'] ?? $intervention['ref_client'],
            'contact_client' => $_POST['contact_client'] ?? $intervention['contact_client'],
            'is_preventive' => isset($_POST['is_preventive']) ? 1 : 0,
        ];

        // Traiter la date et l'heure de création
        $createdDate = $_POST['created_date'] ?? date('Y-m-d', strtotime($intervention['created_at']));
        $createdTime = $_POST['created_time'] ?? date('H:i', strtotime($intervention['created_at']));
        $data['created_at'] = $createdDate . ' ' . $createdTime . ':00';

        // Gérer le contract_id séparément pour s'assurer qu'il est correctement traité
        if (isset($_POST['contract_id']) && $_POST['contract_id'] !== '') {
            $data['contract_id'] = $_POST['contract_id'];
        } else {
            $data['contract_id'] = null;
        }

        // Vérifier si c'est une sauvegarde avant fermeture
        $isSaveBeforeClose = isset($_POST['save_before_close']) && $_POST['save_before_close'] == '1';

        // Vérifier si l'intervention est en train d'être fermée
        custom_log("DEBUG - update() - Vérification de la fermeture", "DEBUG");
        custom_log("DEBUG - update() - data['status_id']: " . ($data['status_id'] ?? 'NON DÉFINI'), "DEBUG");
        custom_log("DEBUG - update() - intervention['status_id']: " . ($intervention['status_id'] ?? 'NON DÉFINI'), "DEBUG");
        custom_log("DEBUG - update() - isSaveBeforeClose: " . ($isSaveBeforeClose ? 'VRAI' : 'FAUX'), "DEBUG");

        $alreadyClosed = ($intervention['status_id'] == 6);
        $isBeingClosed = !$alreadyClosed
            && isset($data['status_id'])
            && $data['status_id'] == 6;

        if ($isBeingClosed && !$isSaveBeforeClose) {
            // ── Fermeture fraîche ──────────────────────────────────────────────
            if (empty($data['duration'])) {
                $_SESSION['error'] = "Impossible de fermer l'intervention sans avoir défini une durée.";
                header('Location: ' . BASE_URL . 'interventions/edit/' . $id);
                exit;
            }

            $data['closed_at'] = date('Y-m-d H:i:s');
            $ticketsUsed = 0;

            if (!empty($data['contract_id']) && isContractTicketById($data['contract_id'])) {
                $ticketsUsed = $this->calculateTotalTicketsUsed(
                    $id,
                    $data['duration'],
                    $data['type_id'] ?? $intervention['type_id']
                );
                $this->deductTicketsFromContract($data['contract_id'], $ticketsUsed, $id);
            }
            $data['tickets_used'] = $ticketsUsed;

        } elseif ($alreadyClosed && !$isSaveBeforeClose) {
            // ── Modification d'une intervention déjà fermée ────────────────────
            // 1. Gestion du changement de contrat (recrédit + nouvelle déduction)
            $this->handleTicketManagementOnContractChange($id, $intervention, $data);

            // 2. Si un champ ticketable a changé ET que le contrat n'a pas changé
            //    (si le contrat a changé, handleTicketManagementOnContractChange
            //    a déjà tout recalculé via le montant d'origine)
            $contractChanged = ($intervention['contract_id'] ?? null) != ($data['contract_id'] ?? null);
            if (!$contractChanged && $this->ticketableFieldChanged($intervention, $data)) {
                // On ajuste après le update() pour avoir les techniciens à jour
                // On stocke un flag pour le faire juste après
                $needsTicketAdjust = true;
            }
        }
        // Gestion des tickets lors du changement de contrat pour une intervention fermée
        $ticketManagementResult = $this->handleTicketManagementOnContractChange($id, $intervention, $data);

        // Valider le format de l'email si renseigné
        if (!empty($data['contact_client'])) {
            if (!filter_var($data['contact_client'], FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = "Le format de l'email de contact est invalide.";
                header('Location: ' . BASE_URL . 'interventions/edit/' . $id);
                exit;
            }
        }

        // Mettre à jour l'intervention
        $result = $this->interventionModel->update($id, $data);
        if (!empty($needsTicketAdjust) && $result) {
            $this->adjustTicketsOnClosedIntervention($id, $intervention, $data);
        }

        if ($result) {
            // Vérifier si le technicien a changé et si on doit envoyer un email
            $technicianChanged = false;
            // Vérifier si des modifications ont été apportées
            $hasChanges = false;
            foreach ($data as $key => $value) {
                if (isset($intervention[$key]) && $intervention[$key] != $value) {
                    $hasChanges = true;
                    break;
                }
            }

            // Enregistrer les modifications dans l'historique seulement si des changements ont été effectués
            if ($hasChanges) {
                $this->recordChanges($id, $intervention, $data);
            }

            // Si c'est une sauvegarde avant fermeture, retourner du JSON
            if ($isSaveBeforeClose) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Données sauvegardées avec succès']);
                exit;
            }

            $successMessage = "Intervention mise à jour avec succès.";
            if ($ticketManagementResult) {
                $successMessage .= " La gestion des tickets a été effectuée automatiquement.";
            }
            if ($technicianChanged && isset($_POST['notify_technician']) && $_POST['notify_technician'] == '1') {
                $successMessage .= " Le technicien a été notifié par email.";
            }
            $_SESSION['success'] = $successMessage;
        } else {
            // Si c'est une sauvegarde avant fermeture, retourner du JSON même en cas d'erreur
            if ($isSaveBeforeClose) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Erreur lors de la sauvegarde des données']);
                exit;
            }

            $_SESSION['error'] = "Erreur lors de la mise à jour de l'intervention.";
        }

        header('Location: ' . BASE_URL . 'interventions/view/' . $id);
        exit;
    }

    /**
     * Calcule le nombre de tickets utilisés en fonction de la durée et des coefficients
     * @param float $duration Durée en heures
     * @param int $technicianId ID du technicien
     * @param int $typeId ID du type d'intervention
     * @return int Nombre de tickets utilisés
     */
    private function calculateTicketsUsed($duration, $technicianId, $typeId, $typeRequiresTravel = null)
    {
        // custom_log("DEBUG - calculateTicketsUsed() - Paramètres: durée=$duration, technicien=$technicianId, type=$typeId, type_requires_travel=" . ($typeRequiresTravel ?? 'null'), "DEBUG");

        // Récupérer le coefficient utilisateur
        $technician = $this->userModel->getUserById($technicianId);
        $coefUtilisateur = $technician['coef_utilisateur'] ?? 0;
        custom_log("DEBUG - calculateTicketsUsed() - Technicien: " . print_r($technician, true), "DEBUG");
        custom_log("DEBUG - calculateTicketsUsed() - Coef utilisateur: $coefUtilisateur", "DEBUG");

        // Utiliser la valeur stockée dans l'intervention si disponible, sinon celle du type
        if ($typeRequiresTravel !== null) {
            $requiresTravel = (bool) $typeRequiresTravel;
            custom_log("DEBUG - calculateTicketsUsed() - Utilisation de la valeur stockée dans l'intervention: " . ($requiresTravel ? 'OUI' : 'NON'), "DEBUG");
        } else {
            // Récupérer le type d'intervention pour savoir s'il y a déplacement
            $type = $this->interventionModel->getTypeInfo($typeId);
            $requiresTravel = $type['requires_travel'] ?? false;
            custom_log("DEBUG - calculateTicketsUsed() - Type: " . print_r($type, true), "DEBUG");
            custom_log("DEBUG - calculateTicketsUsed() - Déplacement requis (depuis type): " . ($requiresTravel ? 'OUI' : 'NON'), "DEBUG");
        }

        // Récupérer le coefficient d'intervention depuis les paramètres
        $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'coef_intervention'");
        $stmt->execute();
        $coefIntervention = floatval($stmt->fetchColumn()) ?? 0;
        custom_log("DEBUG - calculateTicketsUsed() - Coef intervention: $coefIntervention", "DEBUG");

        // Calculer les tickets selon la formule
        if ($requiresTravel) {
            // Avec déplacement : durée + coef_utilisateur + 1 + coef_intervention
            $tickets = $duration + $coefUtilisateur + 1 + $coefIntervention;
            custom_log("DEBUG - calculateTicketsUsed() - Calcul avec déplacement: $duration + $coefUtilisateur + 1 + $coefIntervention = $tickets", "DEBUG");
        } else {
            // Sans déplacement : durée + coef_utilisateur + coef_intervention
            $tickets = $duration + $coefUtilisateur + $coefIntervention;
            custom_log("DEBUG - calculateTicketsUsed() - Calcul sans déplacement: $duration + $coefUtilisateur + $coefIntervention = $tickets", "DEBUG");
        }

        // Arrondir à l'entier supérieur
        $result = ceil($tickets);
        custom_log("DEBUG - calculateTicketsUsed() - Résultat final (arrondi): $result", "DEBUG");
        return $result;
    }

    /**
     * Enregistre les modifications dans l'historique
     */
    private function recordChanges($interventionId, $oldData, $newData)
    {
        // Code de débogage temporaire
        error_log("DEBUG - recordChanges() - oldData: " . print_r($oldData, true));
        error_log("DEBUG - recordChanges() - newData: " . print_r($newData, true));
        error_log("DEBUG - site_id existe dans oldData? " . (array_key_exists('site_id', $oldData) ? 'OUI' : 'NON'));
        error_log("DEBUG - site_id existe dans newData? " . (array_key_exists('site_id', $newData) ? 'OUI' : 'NON'));

        $fieldsToTrack = [
            'title' => 'Titre',
            'client_id' => 'Client',
            'site_id' => 'Site',
            'room_id' => 'Salle',
            'status_id' => 'Statut',
            'priority_id' => 'Priorité',
            'type_id' => 'Type',
            'duration' => 'Durée',
            'description' => 'Description',
            'demande_par' => 'Demande par',
            'contract_id' => 'Contrat',
            'date_planif' => 'Date planifiée',
            'heure_planif' => 'Heure planifiée',
            'created_at' => 'Date de création'
        ];

        // OPTIMISATION N+1 : Précharger toutes les données nécessaires en une seule fois
        // Au lieu de faire une requête SQL pour chaque appel à getDisplayValue(),
        // on collecte tous les IDs et on fait des requêtes batch
        $lookupData = $this->preloadDisplayValues($oldData, $newData);

        $changes = [];
        foreach ($fieldsToTrack as $field => $label) {
            // Vérifier si le champ existe dans les nouvelles données
            if (isset($newData[$field])) {
                // Traitement spécial pour le champ description
                if ($field === 'description') {
                    // Pour la description, on vérifie simplement si elle a changé
                    if (!isset($oldData[$field]) || $oldData[$field] !== $newData[$field]) {
                        $changes[] = "Description modifiée";

                        $sql = "INSERT INTO intervention_history (
                                    intervention_id, field_name, old_value, new_value, changed_by, description
                                ) VALUES (
                                    :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                                )";

                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([
                            ':intervention_id' => $interventionId,
                            ':field_name' => $label,
                            ':old_value' => 'Ancienne description',
                            ':new_value' => 'Nouvelle description',
                            ':changed_by' => $_SESSION['user']['id'],
                            ':description' => "Description modifiée"
                        ]);
                    }
                } else {
                    // S'assurer que la clé existe dans oldData avant d'y accéder
                    $oldFieldValue = array_key_exists($field, $oldData) ? $oldData[$field] : null;

                    // Pour les autres champs, on compare les valeurs d'affichage
                    // Utiliser les données préchargées pour éviter les requêtes N+1
                    $oldValue = $this->getDisplayValue($field, $oldFieldValue, $lookupData);
                    $newValue = $this->getDisplayValue($field, $newData[$field], $lookupData);

                    // Ne créer une entrée que si la valeur a réellement changé
                    if ($oldValue !== $newValue) {
                        $changes[] = "$label : $oldValue → $newValue";

                        $sql = "INSERT INTO intervention_history (
                                    intervention_id, field_name, old_value, new_value, changed_by, description
                                ) VALUES (
                                    :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                                )";

                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([
                            ':intervention_id' => $interventionId,
                            ':field_name' => $label,
                            ':old_value' => $oldValue,
                            ':new_value' => $newValue,
                            ':changed_by' => $_SESSION['user']['id'],
                            ':description' => "$label : $oldValue → $newValue"
                        ]);
                    }
                }
            }
        }
    }

    /**
     * OPTIMISATION N+1 : Précharge toutes les données nécessaires pour getDisplayValue()
     * @param array $oldData Données anciennes
     * @param array $newData Données nouvelles
     * @return array Tableau de lookup avec toutes les données préchargées
     */
    private function preloadDisplayValues($oldData, $newData)
    {
        $lookupData = [
            'clients' => [],
            'sites' => [],
            'rooms' => [],
            'technicians' => [],
            'statuses' => [],
            'priorities' => [],
            'types' => [],
            'contracts' => []
        ];

        // Collecter tous les IDs nécessaires
        $clientIds = [];
        $siteIds = [];
        $roomIds = [];
        $technicianIds = [];
        $statusIds = [];
        $priorityIds = [];
        $typeIds = [];
        $contractIds = [];

        foreach (['oldData' => $oldData, 'newData' => $newData] as $source => $data) {
            if (isset($data['client_id']) && $data['client_id'])
                $clientIds[] = $data['client_id'];
            if (isset($data['site_id']) && $data['site_id'])
                $siteIds[] = $data['site_id'];
            if (isset($data['room_id']) && $data['room_id'])
                $roomIds[] = $data['room_id'];
            if (isset($data['technicien_id']) && $data['technicien_id'])
                $technicianIds[] = $data['technicien_id'];
            if (isset($data['status_id']) && $data['status_id'])
                $statusIds[] = $data['status_id'];
            if (isset($data['priority_id']) && $data['priority_id'])
                $priorityIds[] = $data['priority_id'];
            if (isset($data['type_id']) && $data['type_id'])
                $typeIds[] = $data['type_id'];
            if (isset($data['contract_id']) && $data['contract_id'])
                $contractIds[] = $data['contract_id'];
        }

        // Supprimer les doublons
        $clientIds = array_unique($clientIds);
        $siteIds = array_unique($siteIds);
        $roomIds = array_unique($roomIds);
        $technicianIds = array_unique($technicianIds);
        $statusIds = array_unique($statusIds);
        $priorityIds = array_unique($priorityIds);
        $typeIds = array_unique($typeIds);
        $contractIds = array_unique($contractIds);

        // Précharger les clients
        if (!empty($clientIds)) {
            $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
            $stmt = $this->db->prepare("SELECT id, name FROM clients WHERE id IN ($placeholders)");
            $stmt->execute($clientIds);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $lookupData['clients'][$row['id']] = $row['name'];
            }
        }

        // Précharger les sites
        if (!empty($siteIds)) {
            $placeholders = implode(',', array_fill(0, count($siteIds), '?'));
            $stmt = $this->db->prepare("SELECT id, name FROM sites WHERE id IN ($placeholders)");
            $stmt->execute($siteIds);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $lookupData['sites'][$row['id']] = $row['name'];
            }
        }

        // Précharger les salles
        if (!empty($roomIds)) {
            $placeholders = implode(',', array_fill(0, count($roomIds), '?'));
            $stmt = $this->db->prepare("SELECT id, name FROM rooms WHERE id IN ($placeholders)");
            $stmt->execute($roomIds);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $lookupData['rooms'][$row['id']] = $row['name'];
            }
        }

        // Précharger les techniciens
        if (!empty($technicianIds)) {
            $placeholders = implode(',', array_fill(0, count($technicianIds), '?'));
            $stmt = $this->db->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE id IN ($placeholders)");
            $stmt->execute($technicianIds);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $lookupData['technicians'][$row['id']] = $row['name'];
            }
        }

        // Précharger les statuts
        if (!empty($statusIds)) {
            $placeholders = implode(',', array_fill(0, count($statusIds), '?'));
            $stmt = $this->db->prepare("SELECT id, name FROM intervention_statuses WHERE id IN ($placeholders)");
            $stmt->execute($statusIds);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $lookupData['statuses'][$row['id']] = $row['name'];
            }
        }

        // Précharger les priorités
        if (!empty($priorityIds)) {
            $placeholders = implode(',', array_fill(0, count($priorityIds), '?'));
            $stmt = $this->db->prepare("SELECT id, name FROM intervention_priorities WHERE id IN ($placeholders)");
            $stmt->execute($priorityIds);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $lookupData['priorities'][$row['id']] = $row['name'];
            }
        }

        // Précharger les types
        if (!empty($typeIds)) {
            $placeholders = implode(',', array_fill(0, count($typeIds), '?'));
            $stmt = $this->db->prepare("SELECT id, name FROM intervention_types WHERE id IN ($placeholders)");
            $stmt->execute($typeIds);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $lookupData['types'][$row['id']] = $row['name'];
            }
        }

        // Précharger les contrats
        if (!empty($contractIds)) {
            $placeholders = implode(',', array_fill(0, count($contractIds), '?'));
            $stmt = $this->db->prepare("SELECT id, name FROM contracts WHERE id IN ($placeholders)");
            $stmt->execute($contractIds);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $lookupData['contracts'][$row['id']] = $row['name'];
            }
        }

        return $lookupData;
    }

    /**
     * Récupère la valeur d'affichage d'un champ
     * @param string $field Nom du champ
     * @param mixed $value Valeur du champ
     * @param array $lookupData Données préchargées (optionnel, pour éviter les requêtes N+1)
     */
    private function getDisplayValue($field, $value, $lookupData = [])
    {
        // Code de débogage temporaire
        error_log("DEBUG - getDisplayValue() - field: $field, value: " . var_export($value, true));

        if ($value === null) {
            return 'Non défini';
        }

        switch ($field) {
            case 'client_id':
                if (!empty($lookupData) && isset($lookupData['clients'][$value])) {
                    return $lookupData['clients'][$value];
                }
                // Fallback si lookupData n'est pas fourni (compatibilité)
                $sql = "SELECT name FROM clients WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ? $result['name'] : 'Client inconnu';

            case 'site_id':
                error_log("DEBUG - getDisplayValue() - site_id spécifique, value: " . var_export($value, true));
                if (empty($value))
                    return 'Non spécifié';
                if (!empty($lookupData) && isset($lookupData['sites'][$value])) {
                    return $lookupData['sites'][$value];
                }
                // Fallback si lookupData n'est pas fourni (compatibilité)
                $sql = "SELECT name FROM sites WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                error_log("DEBUG - getDisplayValue() - site_id résultat SQL: " . print_r($result, true));
                return $result ? $result['name'] : 'Site inconnu';

            case 'room_id':
                if (empty($value))
                    return 'Non spécifié';
                if (!empty($lookupData) && isset($lookupData['rooms'][$value])) {
                    return $lookupData['rooms'][$value];
                }
                // Fallback si lookupData n'est pas fourni (compatibilité)
                $sql = "SELECT name FROM rooms WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ? $result['name'] : 'Salle inconnue';

            case 'technicien_id':
                if (!empty($lookupData) && isset($lookupData['technicians'][$value])) {
                    return $lookupData['technicians'][$value];
                }
                // Fallback si lookupData n'est pas fourni (compatibilité)
                $sql = "SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ? $result['name'] : 'Technicien inconnu';

            case 'status_id':
                if (!empty($lookupData) && isset($lookupData['statuses'][$value])) {
                    return $lookupData['statuses'][$value];
                }
                // Fallback si lookupData n'est pas fourni (compatibilité)
                $sql = "SELECT name FROM intervention_statuses WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ? $result['name'] : 'Statut inconnu';

            case 'priority_id':
                if (!empty($lookupData) && isset($lookupData['priorities'][$value])) {
                    return $lookupData['priorities'][$value];
                }
                // Fallback si lookupData n'est pas fourni (compatibilité)
                $sql = "SELECT name FROM intervention_priorities WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ? $result['name'] : 'Priorité inconnue';

            case 'type_id':
                if (!empty($lookupData) && isset($lookupData['types'][$value])) {
                    return $lookupData['types'][$value];
                }
                // Fallback si lookupData n'est pas fourni (compatibilité)
                $sql = "SELECT name FROM intervention_types WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ? $result['name'] : 'Type inconnu';

            case 'contract_id':
                if (!$value)
                    return 'Hors contrat';
                if (!empty($lookupData) && isset($lookupData['contracts'][$value])) {
                    return $lookupData['contracts'][$value];
                }
                // Fallback si lookupData n'est pas fourni (compatibilité)
                $sql = "SELECT name FROM contracts WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ? $result['name'] : 'Contrat inconnu';

            case 'duration':
                return $value . ' heure(s)';

            case 'date_planif':
                return date('d/m/Y', strtotime($value));

            case 'heure_planif':
                return $value;

            case 'demande_par':
                return $value ?: 'Non spécifié';

            case 'created_at':
                return date('d/m/Y H:i', strtotime($value));

            default:
                return $value;
        }
    }

    /**
     * Récupère les commentaires d'une intervention
     */
    private function getComments($interventionId)
    {
        $sql = "SELECT c.*, 
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM intervention_comments c
                LEFT JOIN users u ON c.created_by = u.id
                WHERE c.intervention_id = ?
                ORDER BY c.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$interventionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les pièces jointes d'une intervention
     */
    private function getAttachments($interventionId)
    {
        return $this->interventionModel->getPiecesJointes($interventionId);
    }

    /**
     * Récupère l'historique d'une intervention
     */
    private function getHistory($interventionId)
    {
        $sql = "SELECT h.*, 
                CONCAT(u.first_name, ' ', u.last_name) as changed_by_name
                FROM intervention_history h
                LEFT JOIN users u ON h.changed_by = u.id
                WHERE h.intervention_id = ?
                ORDER BY h.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$interventionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajoute un commentaire à une intervention
     */
    public function addComment($interventionId)
    {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($interventionId);

        if (!$intervention) {
            // Rediriger vers la liste si l'intervention n'existe pas
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }

        // Vérifier si l'intervention est fermée
        if ($intervention['status_id'] == 6) { // 6 = Fermé
            $_SESSION['error'] = "Impossible d'ajouter un commentaire à une intervention fermée.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
            exit;
        }

        // Récupérer les données du formulaire
        $comment = $_POST['comment'] ?? '';
        $visibleByClient = isset($_POST['visible_by_client']) ? 1 : 0;
        $isSolution = isset($_POST['is_solution']) ? 1 : 0;
        $isObservation = isset($_POST['is_observation']) ? 1 : 0;

        if (empty($comment)) {
            $_SESSION['error'] = "Le commentaire ne peut pas être vide.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
            exit;
        }

        // Ajouter le commentaire
        $sql = "INSERT INTO intervention_comments (
                    intervention_id, comment, visible_by_client, is_solution, is_observation, created_by
                ) VALUES (
                    :intervention_id, :comment, :visible_by_client, :is_solution, :is_observation, :created_by
                )";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':intervention_id' => $interventionId,
            ':comment' => $comment,
            ':visible_by_client' => $visibleByClient,
            ':is_solution' => $isSolution,
            ':is_observation' => $isObservation,
            ':created_by' => $_SESSION['user']['id']
        ]);

        if ($result) {
            // Enregistrer l'action dans l'historique
            $sql = "INSERT INTO intervention_history (
                        intervention_id, field_name, old_value, new_value, changed_by, description
                    ) VALUES (
                        :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':intervention_id' => $interventionId,
                ':field_name' => 'Commentaire',
                ':old_value' => '',
                ':new_value' => '',
                ':changed_by' => $_SESSION['user']['id'],
                ':description' => "Commentaire ajouté" . ($isSolution ? " (marqué comme solution)" : "") . ($visibleByClient ? " (visible par le client)" : "")
            ]);

            $_SESSION['success'] = "Commentaire ajouté avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de l'ajout du commentaire.";
        }

        header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
        exit;
    }

    /**
     * Ajoute une pièce jointe à une intervention
     */
    /**
     * Ajoute une pièce jointe à une intervention
     * Utilise AttachmentService pour centraliser la logique
     */
    public function addAttachment($interventionId)
    {
        // Vérifier les permissions
        checkInterventionManagementAccess();

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($interventionId);

        if (!$intervention) {
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }

        // Vérifier si l'intervention est fermée
        if ($intervention['status_id'] == 6) { // 6 = Fermé
            $_SESSION['error'] = "Impossible d'ajouter une pièce jointe à une intervention fermée.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
            exit;
        }

        // Vérifier si un fichier a été uploadé
        if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "Erreur lors de l'upload du fichier.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
            exit;
        }

        try {
            // Utiliser AttachmentService pour gérer l'upload
            $attachmentService = new AttachmentService($this->db);

            // Préparer les options
            $options = [
                'custom_names' => [isset($_POST['custom_name']) && !empty(trim($_POST['custom_name'])) ? trim($_POST['custom_name']) : null],
                'descriptions' => [$_POST['description'] ?? null],
                'masque_client' => [isset($_POST['masque_client']) ? 1 : 0]
            ];

            // Upload du fichier
            $result = $attachmentService->upload(
                AttachmentService::TYPE_INTERVENTION,
                $interventionId,
                $_FILES['attachment'],
                $options,
                $_SESSION['user']['id']
            );

            if ($result['success'] && !empty($result['attachment_ids'])) {
                // Enregistrer l'action dans l'historique
                $displayName = $result['uploaded_files'][0] ?? $_FILES['attachment']['name'];
                $sql = "INSERT INTO intervention_history (
                            intervention_id, field_name, old_value, new_value, changed_by, description
                        ) VALUES (
                            :intervention_id, 'attachment', '', :filename, :changed_by, 'Ajout de pièce jointe'
                        )";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':intervention_id' => $interventionId,
                    ':filename' => $displayName,
                    ':changed_by' => $_SESSION['user']['id']
                ]);

                $_SESSION['success'] = "Pièce jointe ajoutée avec succès.";
            } else {
                $errorMessage = !empty($result['errors']) ? implode(', ', $result['errors']) : "Erreur lors de l'ajout de la pièce jointe.";
                $_SESSION['error'] = $errorMessage;
            }

        } catch (Exception $e) {
            custom_log("Erreur lors de l'ajout de la pièce jointe : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de l'ajout de la pièce jointe : " . $e->getMessage();
        }

        header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
        exit;
    }

    /**
     * Ajoute plusieurs pièces jointes à une intervention (Drag & Drop)
     * Utilise AttachmentService pour centraliser la logique
     */
    public function addMultipleAttachments($interventionId)
    {
        // Vérifier les permissions
        if (!isset($_SESSION['user']) || (!isStaff() && !isAdmin())) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Non autorisé']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
            exit;
        }

        try {
            // Récupérer l'intervention
            $intervention = $this->interventionModel->getById($interventionId);

            if (!$intervention) {
                throw new Exception("Intervention non trouvée");
            }

            // Vérifier si l'intervention est fermée
            if ($intervention['status_id'] == 6) { // 6 = Fermé
                throw new Exception("Impossible d'ajouter une pièce jointe à une intervention fermée");
            }

            // Vérifier qu'il y a des fichiers
            if (!isset($_FILES['attachments']) || empty($_FILES['attachments']['name'][0])) {
                throw new Exception("Aucun fichier à uploader");
            }

            // Utiliser AttachmentService pour gérer l'upload
            $attachmentService = new AttachmentService($this->db);

            // Préparer les options
            $options = [
                'custom_names' => $_POST['custom_names'] ?? []
            ];

            // Upload des fichiers
            $result = $attachmentService->upload(
                AttachmentService::TYPE_INTERVENTION,
                $interventionId,
                $_FILES['attachments'],
                $options,
                $_SESSION['user']['id']
            );

            // Enregistrer dans l'historique pour chaque fichier uploadé
            if ($result['success'] && !empty($result['attachment_ids'])) {
                foreach ($result['uploaded_files'] as $index => $displayName) {
                    $sql = "INSERT INTO intervention_history (
                                intervention_id, field_name, old_value, new_value, changed_by, description
                            ) VALUES (
                                :intervention_id, 'attachment', '', :filename, :changed_by, 'Ajout de pièce jointe'
                            )";

                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        ':intervention_id' => $interventionId,
                        ':filename' => $displayName,
                        ':changed_by' => $_SESSION['user']['id']
                    ]);
                }
            }

            // Retourner le résultat
            header('Content-Type: application/json');
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => count($result['uploaded_files']) . ' fichier(s) uploadé(s) avec succès',
                    'uploaded_files' => $result['uploaded_files']
                ]);
            } else {
                $errorMessage = !empty($result['errors']) ? implode(', ', $result['errors']) : 'Aucun fichier uploadé';
                echo json_encode([
                    'success' => false,
                    'error' => $errorMessage,
                    'uploaded_files' => $result['uploaded_files']
                ]);
            }

        } catch (Exception $e) {
            custom_log("Erreur lors de l'ajout des pièces jointes : " . $e->getMessage(), 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Télécharge une pièce jointe
     * Utilise AttachmentService pour centraliser la logique
     */
    public function download($attachmentId)
    {
        // Vérifier les permissions
        $this->checkAccess();

        try {
            // Récupérer la pièce jointe pour vérifier les permissions
            $attachment = $this->interventionModel->getPieceJointeById($attachmentId);

            if (!$attachment || ($attachment['type_liaison'] !== 'intervention' && $attachment['type_liaison'] !== 'bi')) {
                $_SESSION['error'] = "La pièce jointe n'existe pas.";
                header('Location: ' . $this->getInterventionsListUrl());
                exit;
            }

            // Récupérer l'intervention
            $intervention = $this->interventionModel->getById($attachment['entite_id']);

            // Vérifier si l'utilisateur est autorisé à télécharger
            // Soit il a la permission 'view_interventions', soit il est assigné comme technicien à cette intervention
            $isAuthorized = $this->checkPermission('technicien', 'view_interventions');

            if (!$isAuthorized) {
                // Vérifier si l'utilisateur est assigné comme technicien à cette intervention
                $sql = "SELECT COUNT(*) FROM intervention_techniciens WHERE intervention_id = :intervention_id AND technicien_id = :user_id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':intervention_id' => $intervention['id'],
                    ':user_id' => $_SESSION['user']['id']
                ]);
                $isAssigned = $stmt->fetchColumn() > 0;

                if (!$isAssigned) {
                    $_SESSION['error'] = "Vous n'avez pas la permission de télécharger cette pièce jointe.";
                    header('Location: ' . $this->getInterventionsListUrl());
                    exit;
                }
            }

            // Utiliser AttachmentService pour gérer le téléchargement
            $attachmentService = new AttachmentService($this->db);
            $attachmentService->download($attachmentId, true);

        } catch (Exception $e) {
            custom_log("Erreur lors du téléchargement de la pièce jointe : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors du téléchargement : " . $e->getMessage();
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }
    }

    /**
     * Affiche l'aperçu d'une pièce jointe
     * Utilise AttachmentService pour centraliser la logique
     */
    public function preview($attachmentId)
    {
        // Vérifier les permissions
        $this->checkAccess();

        try {
            // Récupérer la pièce jointe pour vérifier les permissions
            $attachment = $this->interventionModel->getPieceJointeById($attachmentId);

            if (!$attachment || ($attachment['type_liaison'] !== 'intervention' && $attachment['type_liaison'] !== 'bi')) {
                $_SESSION['error'] = "La pièce jointe n'existe pas.";
                header('Location: ' . $this->getInterventionsListUrl());
                exit;
            }

            // Utiliser AttachmentService pour gérer l'aperçu
            $attachmentService = new AttachmentService($this->db);
            $attachmentService->preview($attachmentId);

        } catch (Exception $e) {
            custom_log("Erreur lors de l'aperçu de la pièce jointe : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de l'aperçu : " . $e->getMessage();
            header('Location: ' . BASE_URL . 'interventions/view/' . ($attachment['entite_id'] ?? ''));
            exit;
        }
    }

    /**
     * Supprime un commentaire
     */
    public function deleteComment($commentId)
    {
        // Vérifier les permissions
        if (!isset($_SESSION['user']) || !isAdmin()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Récupérer le commentaire
        $sql = "SELECT id, intervention_id, comment, visible_by_client, is_solution, is_observation, pour_bon_intervention, created_by, created_at FROM intervention_comments WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment) {
            $_SESSION['error'] = "Commentaire introuvable.";
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }

        // Supprimer le commentaire
        $sql = "DELETE FROM intervention_comments WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$commentId]);

        if ($result) {
            // Enregistrer l'action dans l'historique
            $sql = "INSERT INTO intervention_history (
                        intervention_id, field_name, old_value, new_value, changed_by, description
                    ) VALUES (
                        :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':intervention_id' => $comment['intervention_id'],
                ':field_name' => 'Commentaire',
                ':old_value' => $comment['comment'],
                ':new_value' => '',
                ':changed_by' => $_SESSION['user']['id'],
                ':description' => "Commentaire supprimé"
            ]);

            $_SESSION['success'] = "Commentaire supprimé avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la suppression du commentaire.";
        }

        header('Location: ' . BASE_URL . 'interventions/view/' . $comment['intervention_id']);
        exit;
    }

    /**
     * Supprime une pièce jointe
     * Utilise AttachmentService pour centraliser la logique
     */
    public function deleteAttachment($attachmentId)
    {
        // Vérifier les permissions
        if (!isset($_SESSION['user']) || !isAdmin()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        try {
            // Récupérer la pièce jointe pour vérifier et obtenir l'ID de l'intervention
            $attachment = $this->interventionModel->getPieceJointeById($attachmentId);

            if (!$attachment || ($attachment['type_liaison'] !== 'intervention' && $attachment['type_liaison'] !== 'bi')) {
                $_SESSION['error'] = "Pièce jointe introuvable.";
                header('Location: ' . $this->getInterventionsListUrl());
                exit;
            }

            $interventionId = $attachment['entite_id'];

            // Utiliser AttachmentService pour gérer la suppression
            $attachmentService = new AttachmentService($this->db);
            $attachmentService->delete($attachmentId, $attachment['type_liaison'], $interventionId);

            // Enregistrer l'action dans l'historique
            $sql = "INSERT INTO intervention_history (
                        intervention_id, field_name, old_value, new_value, changed_by, description
                    ) VALUES (
                        :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':intervention_id' => $interventionId,
                ':field_name' => 'Pièce jointe',
                ':old_value' => $attachment['nom_fichier'],
                ':new_value' => '',
                ':changed_by' => $_SESSION['user']['id'],
                ':description' => "Pièce jointe supprimée : " . $attachment['nom_fichier']
            ]);

            $_SESSION['success'] = "Pièce jointe supprimée avec succès.";

        } catch (Exception $e) {
            custom_log("Erreur lors de la suppression de la pièce jointe : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de la suppression de la pièce jointe : " . $e->getMessage();
        }

        header('Location: ' . BASE_URL . 'interventions/view/' . ($interventionId ?? ''));
        exit;
    }

    /**
     * Récupère les informations d'un type d'intervention
     */
    public function getTypeInfo($typeId)
    {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer les informations du type
        $sql = "SELECT id, name, requires_travel, created_at FROM intervention_types WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$typeId]);
        $type = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$type) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Type introuvable']);
            exit;
        }

        // Retourner les informations au format JSON
        header('Content-Type: application/json');
        echo json_encode($type);
    }

    /**
     * Récupère les sites d'un client
     */
    public function getSites($clientId)
    {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer les sites
        $sites = $this->siteModel->getSitesByClientId($clientId);

        header('Content-Type: application/json');
        echo json_encode(['sites' => $sites]);
        exit;
    }

    /**
     * Récupère les salles d'un site
     */
    public function getRooms($siteId)
    {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer les salles
        $rooms = $this->roomModel->getRoomsByBuildingId($siteId);

        // Retourner les salles au format JSON
        header('Content-Type: application/json');
        echo json_encode(['rooms' => $rooms]);
    }

    /**
     * Vérifie les permissions d'un utilisateur
     */
    private function checkPermission($module, $action)
    {
        if (!isset($_SESSION['user'])) {
            return false;
        }

        // Les administrateurs ont toutes les permissions
        if (isAdmin()) {
            return true;
        }

        // Vérifier les permissions spécifiques
        $permission = 'tech_' . $action; // Utiliser le préfixe 'tech_' au lieu de 'technicien_'

        // Log temporaire pour debug
        custom_log("Vérification permission pour {$permission} : " . json_encode($_SESSION['user']['permissions']), 'DEBUG');

        return isset($_SESSION['user']['permissions']['rights'][$permission]) && $_SESSION['user']['permissions']['rights'][$permission] === true;
    }

    /**
     * Récupère les contrats d'un client
     */
    public function getContracts($clientId, $siteId = null, $roomId = null)
    {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer tous les contrats du client
        $contracts = $this->contractModel->getContractsByClientId($clientId, $siteId, $roomId);

        // Retourner les contrats au format JSON
        header('Content-Type: application/json');
        echo json_encode($contracts);
    }

    /**
     * Récupère le contrat associé à une salle
     */
    public function getContractByRoom($roomId)
    {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer le contrat
        $contract = $this->contractModel->getContractByRoomId($roomId);

        // Retourner le contrat au format JSON
        header('Content-Type: application/json');
        echo json_encode($contract);
    }

    /**
     * Récupère les informations d'un contrat via AJAX
     */
    public function getContractInfo($contractId)
    {
        // Nettoyage total
        while (ob_get_level())
            ob_end_clean();

        header('Content-Type: application/json; charset=utf-8');

        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            if (!isset($_SESSION['user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Non authentifié']);
                exit;
            }

            $contractId = (int) $contractId;

            $sql = "SELECT c.id, c.name, c.start_date, c.end_date, 
                       c.tickets_remaining, c.isticketcontract, c.comment,
                       ct.name as contract_type_name
                FROM contracts c
                LEFT JOIN contract_types ct ON c.contract_type_id = ct.id
                WHERE c.id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $contractId]);

            $contract = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$contract) {
                throw new Exception("Contrat non trouvé");
            }

            echo json_encode($contract, JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }

        exit;
    }
    /**
     * Récupère les contacts d'un client
     */
    public function getContacts($clientId)
    {
        // Vérifier les permissions
        $this->checkAccess();

        header('Content-Type: application/json');

        try {
            // Valider l'ID du client
            if (!is_numeric($clientId) || $clientId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'ID client invalide']);
                return;
            }

            // Récupérer les contacts du client avec index optimisé
            // L'index composite (client_id, status) optimise cette requête
            $sql = "SELECT id, first_name, last_name, email 
                    FROM contacts 
                    WHERE client_id = ? AND status = 1 
                    ORDER BY last_name, first_name
                    LIMIT 1000"; // Limite de sécurité pour éviter les résultats trop volumineux

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$clientId]);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($contacts);
        } catch (PDOException $e) {
            // Log l'erreur pour le débogage
            custom_log("Erreur getContacts pour client_id $clientId: " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la récupération des contacts']);
        } catch (Exception $e) {
            custom_log("Erreur getContacts pour client_id $clientId: " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la récupération des contacts']);
        }
    }

    /**
     * Crée rapidement un nouveau client via AJAX
     */
    public function quickCreateClient()
    {
        // Vérifier les permissions
        $this->checkAccess();

        // Vérifier si l'utilisateur a les droits d'ajout
        if (!canModifyClients()) {
            http_response_code(403);
            echo json_encode(['error' => "Vous n'avez pas les droits nécessaires pour ajouter un client."]);
            return;
        }

        header('Content-Type: application/json');

        try {
            // Récupérer les données du formulaire
            $clientData = [
                'name' => $_POST['name'] ?? '',
                'email' => $_POST['email'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'website' => $_POST['website'] ?? '',
                'address' => $_POST['address'] ?? '',
                'postal_code' => $_POST['postal_code'] ?? '',
                'city' => $_POST['city'] ?? '',
                'comment' => $_POST['comment'] ?? '',
                'status' => 1 // Par défaut actif
            ];

            // Valider les données essentielles
            if (empty($clientData['name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Le nom du client est obligatoire']);
                return;
            }

            // Vérifier si un client avec ce nom existe déjà
            $sql = "SELECT id FROM clients WHERE name = ? AND status = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$clientData['name']]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Un client avec ce nom existe déjà']);
                return;
            }

            // Créer le client
            $clientId = $this->clientModel->createClient($clientData);

            // Créer automatiquement les contrats "hors contrat"
            $this->createDefaultContractsForClient($clientId);

            // Récupérer les données du client créé
            $client = $this->clientModel->getClientById($clientId);

            echo json_encode([
                'success' => true,
                'client' => [
                    'id' => $client['id'],
                    'name' => $client['name']
                ],
                'message' => 'Client créé avec succès'
            ]);

        } catch (Exception $e) {
            custom_log("Erreur lors de la création rapide du client : " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(['error' => 'Une erreur est survenue lors de la création du client.']);
        }
    }

    /**
     * Crée rapidement un nouveau site via AJAX
     */
    public function quickCreateSite()
    {
        // Vérifier les permissions
        $this->checkAccess();

        // Vérifier si l'utilisateur a les droits d'ajout
        if (!canModifyClients()) {
            http_response_code(403);
            echo json_encode(['error' => "Vous n'avez pas les droits nécessaires pour ajouter un site."]);
            return;
        }

        header('Content-Type: application/json');

        try {
            // Récupérer les données du formulaire
            $siteData = [
                'client_id' => $_POST['client_id'] ?? '',
                'name' => $_POST['name'] ?? '',
                'address' => $_POST['address'] ?? '',
                'postal_code' => $_POST['postal_code'] ?? '',
                'city' => $_POST['city'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'email' => $_POST['email'] ?? '',
                'comment' => $_POST['comment'] ?? '',
                'status' => 1 // Par défaut actif
            ];

            // Valider les données essentielles
            if (empty($siteData['client_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Aucun client sélectionné']);
                return;
            }

            if (empty($siteData['name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Le nom du site est obligatoire']);
                return;
            }

            // Vérifier si le client existe
            $sql = "SELECT id FROM clients WHERE id = ? AND status = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$siteData['client_id']]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Client introuvable']);
                return;
            }

            // Vérifier si un site avec ce nom existe déjà pour ce client
            $sql = "SELECT id FROM sites WHERE name = ? AND client_id = ? AND status = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$siteData['name'], $siteData['client_id']]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Un site avec ce nom existe déjà pour ce client']);
                return;
            }

            // Créer le site
            $success = $this->siteModel->createSite($siteData);
            if (!$success) {
                throw new Exception('Erreur lors de la création du site');
            }

            // Récupérer l'ID du site créé
            $siteId = $this->db->lastInsertId();

            // Récupérer les données du site créé
            $site = $this->siteModel->getSiteById($siteId);

            echo json_encode([
                'success' => true,
                'site' => [
                    'id' => $site['id'],
                    'name' => $site['name']
                ],
                'message' => 'Site créé avec succès'
            ]);

        } catch (Exception $e) {
            custom_log("Erreur lors de la création rapide du site : " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(['error' => ' lors de la création du site.']);
        }
    }

    /**
     * Crée rapidement une nouveau bâtiment via AJAX
     */
    public function quickCreateBuilding()
    {
        // Vérifier les permissions
        $this->checkAccess();

        // Vérifier si l'utilisateur a les droits d'ajout
        if (!canModifyClients()) {
            http_response_code(403);
            echo json_encode(['error' => "Vous n'avez pas les droits nécessaires pour ajouter un bâtiment."]);
            return;
        }

        header('Content-Type: application/json');

        try {
            // Récupérer les données du formulaire
            $buildingData = [
                'client_id' => $_POST['client_id'] ?? '',
                'site_id' => $_POST['site_id'] ?? '',
                'name' => $_POST['name'] ?? '',
                'comment' => $_POST['comment'] ?? '',
                'status' => 1 // Par défaut actif
            ];

            // Valider les données essentielles
            if (empty($buildingData['client_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Aucun client sélectionné']);
                return;
            }

            if (empty($buildingData['site_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Aucun site sélectionné']);
                return;
            }

            if (empty($buildingData['name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Le nom du bâtiment est obligatoire']);
                return;
            }

            // Vérifier si le site existe et appartient au client
            $sql = "SELECT id FROM sites WHERE id = ? AND client_id = ? AND status = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$buildingData['site_id'], $buildingData['client_id']]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Site introuvable ou ne correspond pas au client']);
                return;
            }

            // Vérifier si un bâtiment avec ce nom existe déjà pour ce site
            $sql = "SELECT id FROM buildings WHERE name = ? AND site_id = ? AND status = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$buildingData['name'], $buildingData['site_id']]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Un bâtiment avec ce nom existe déjà pour ce site']);
                return;
            }

            // Créer le bâtiment
            $success = $this->buildingModel->createBuilding($buildingData);
            if (!$success) {
                throw new Exception('Erreur lors de la création du bâtiment');
            }

            // Récupérer l'ID du bâtiment créé
            $buildingId = $this->db->lastInsertId();

            // Récupérer les données du bâtiment créé
            $building = $this->buildingModel->getBuildingById($buildingId);

            echo json_encode([
                'success' => true,
                'building' => [
                    'id' => $building['id'],
                    'name' => $building['name']
                ],
                'message' => 'Bâtiment créé avec succès'
            ]);

        } catch (Exception $e) {
            custom_log("Erreur lors de la création rapide du bâtiment : " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(['error' => 'Une erreur est survenue lors de la création du bâtiment.']);
        }
    }
    /**
     * Crée rapidement une nouvelle salle via AJAX
     */
    public function quickCreateRoom()
    {
        // Vérifier les permissions
        $this->checkAccess();

        // Vérifier si l'utilisateur a les droits d'ajout
        if (!canModifyClients()) {
            http_response_code(403);
            echo json_encode(['error' => "Vous n'avez pas les droits nécessaires pour ajouter une salle."]);
            return;
        }

        header('Content-Type: application/json');

        try {
            // Récupérer les données du formulaire
            $roomData = [
                'client_id' => $_POST['client_id'] ?? '',
                'building_id' => $_POST['building_id'] ?? '',
                'name' => trim($_POST['name'] ?? ''),
                'comment' => $_POST['comment'] ?? '',
                'status' => 1
            ];

            // Valider les données essentielles
            if (empty($roomData['client_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Aucun client sélectionné']);
                return;
            }

            if (empty($roomData['building_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Aucun bâtiment sélectionné']);
                return;
            }

            if (empty($roomData['name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Le nom de la salle est obligatoire']);
                return;
            }

            // Vérifier si le bâtiment existe et appartient au client via le site
            $sql = "SELECT b.id, b.site_id, s.client_id 
                FROM buildings b 
                JOIN sites s ON b.site_id = s.id 
                WHERE b.id = ? AND s.client_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$roomData['building_id'], $roomData['client_id']]);
            $building = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$building) {
                http_response_code(400);
                echo json_encode(['error' => 'Bâtiment introuvable ou ne correspond pas au client']);
                return;
            }

            // Vérifier si la salle avec ce nom existe déjà pour ce bâtiment
            $sql = "SELECT id FROM rooms WHERE name = ? AND building_id = ? ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$roomData['name'], $roomData['building_id']]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Une salle avec ce nom existe déjà pour ce bâtiment']);
                return;
            }

            // Créer la salle
            $sql = "INSERT INTO rooms (name, building_id, comment, status, created_at, updated_at) 
                VALUES (:name, :building_id, :comment, :status, NOW(), NOW())";
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute([
                ':name' => $roomData['name'],
                ':building_id' => $roomData['building_id'],
                ':comment' => $roomData['comment'],
                ':status' => $roomData['status']
            ]);

            if (!$success) {
                throw new Exception('Erreur lors de la création de la salle');
            }

            // Récupérer l'ID de la salle créée
            $roomId = $this->db->lastInsertId();

            echo json_encode([
                'success' => true,
                'room' => [
                    'id' => $roomId,
                    'name' => $roomData['name']
                ],
                'message' => 'Salle créée avec succès'
            ]);

        } catch (Exception $e) {
            custom_log("Erreur lors de la création rapide de la salle : " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(['error' => 'Une erreur est survenue lors de la création de la salle: ' . $e->getMessage()]);
        }
    }
    /**
     * Crée rapidement un nouveau contact via AJAX
     */
    public function quickCreateContact()
    {
        // Désactiver l'affichage des erreurs pour l'API
        error_reporting(0);
        ini_set('display_errors', 0);

        // Nettoyer les buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Définir les headers pour l'API
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-cache, must-revalidate');

        // Vérifier les permissions
        $this->checkAccess();

        // Vérifier si l'utilisateur a les droits d'ajout
        if (!canModifyClients()) {
            http_response_code(403);
            echo json_encode(['error' => "Vous n'avez pas les droits nécessaires pour ajouter un contact."]);
            return;
        }

        // Récupérer les données du formulaire (POST, pas JSON)
        $clientId = $_POST['client_id'] ?? '';
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone1 = trim($_POST['phone1'] ?? '');
        $phone2 = trim($_POST['phone2'] ?? '');
        $fonction = trim($_POST['fonction'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        try {
            // Valider les données essentielles
            if (empty($clientId) || !is_numeric($clientId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Aucun client sélectionné ou ID invalide']);
                return;
            }

            if (empty($firstName)) {
                http_response_code(400);
                echo json_encode(['error' => 'Le prénom est obligatoire']);
                return;
            }

            if (empty($lastName)) {
                http_response_code(400);
                echo json_encode(['error' => 'Le nom est obligatoire']);
                return;
            }

            // Vérifier si le client existe
            $sql = "SELECT id FROM clients WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$clientId]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Client introuvable']);
                return;
            }

            // Vérifier si un contact avec ce nom existe déjà pour ce client
            $sql = "SELECT id FROM contacts WHERE first_name = ? AND last_name = ? AND client_id = ? AND status = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$firstName, $lastName, $clientId]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Un contact avec ce nom existe déjà pour ce client']);
                return;
            }

            // Créer le contact
            $contactData = [
                'client_id' => $clientId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone1' => $phone1,
                'phone2' => $phone2,
                'fonction' => $fonction,
                'comment' => $comment,
                'has_user_account' => 0,
                'status' => 1
            ];

            $success = $this->contactModel->createContact($contactData);
            if (!$success) {
                throw new Exception('Erreur lors de la création du contact');
            }

            $contactId = $this->db->lastInsertId();
            $contact = $this->contactModel->getContactById($contactId);

            echo json_encode([
                'success' => true,
                'contact' => [
                    'id' => $contact['id'],
                    'first_name' => $contact['first_name'],
                    'last_name' => $contact['last_name'],
                    'email' => $contact['email']
                ],
                'message' => 'Contact créé avec succès'
            ]);

        } catch (Exception $e) {
            custom_log("Erreur lors de la création rapide du contact : " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(['error' => 'Une erreur est survenue lors de la création du contact: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Crée automatiquement les contrats "hors contrat" pour un nouveau client
     */
    private function createDefaultContractsForClient($clientId)
    {
        try {
            // Récupérer le niveau d'accès par défaut
            $sql = "SELECT id FROM access_levels WHERE name = 'Standard' LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $defaultAccessLevel = $stmt->fetch(PDO::FETCH_ASSOC);
            $defaultAccessLevelId = $defaultAccessLevel ? $defaultAccessLevel['id'] : 1;

            // Créer le contrat "Hors contrat facturable"
            $this->contractModel->createContract([
                'client_id' => $clientId,
                'contract_type_id' => null,
                'name' => 'Hors contrat facturable',
                'access_level_id' => $defaultAccessLevelId,
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+1 year')),
                'status' => 1
            ]);

            // Créer le contrat "Hors contrat non facturable"
            $this->contractModel->createContract([
                'client_id' => $clientId,
                'contract_type_id' => null,
                'name' => 'Hors contrat non facturable',
                'access_level_id' => $defaultAccessLevelId,
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+1 year')),
                'status' => 1
            ]);

        } catch (Exception $e) {
            custom_log("Erreur lors de la création des contrats par défaut : " . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Affiche le formulaire de création d'une intervention
     */
    public function create()
    {
        // Vérifier les permissions
        checkInterventionManagementAccess();
        $clients = $this->clientModel->getAllClientsWithStats(['status' => 1]);
        $sites = [];
        $buildings = [];
        $rooms = [];
        $technicians = $this->userModel->getTechnicians();

        // Récupérer les statuts, priorités et types
        $statuses = $this->getAllStatuses();

        $sql = "SELECT id, name, color, created_at FROM intervention_priorities ORDER BY id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $priorities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT id, name, requires_travel, created_at FROM intervention_types ORDER BY name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les bâtiments et salles si des IDs sont passés en GET
        if (isset($_GET['site_id']) && !empty($_GET['site_id'])) {
            $buildings = $this->buildingModel->getBuildingsBySiteId($_GET['site_id']);
        }
        if (isset($_GET['building_id']) && !empty($_GET['building_id'])) {
            $rooms = $this->roomModel->getRoomsByBuildingId($_GET['building_id']);
        }

        // Charger la vue
        require_once __DIR__ . '/../views/interventions/add.php';
    }

    /**
     * Enregistre une nouvelle intervention
     */
    public function store()
    {
        // Vérifier les permissions
        checkInterventionManagementAccess();

        // Récupérer les données du formulaire
        $isPreventive = isset($_POST['is_preventive']) ? 1 : 0;

        // Déterminer la priorité : si préventive, forcer à 5, sinon utiliser la valeur du formulaire
        $priorityId = !empty($_POST['priority_id']) ? $_POST['priority_id'] : 2;
        if ($isPreventive == 1) {
            $priorityId = 5; // ID de la priorité "Préventive"
        }

        $data = [
            'title' => $_POST['title'] ?? '',
            'client_id' => !empty($_POST['client_id']) ? $_POST['client_id'] : null,
            'site_id' => !empty($_POST['site_id']) ? $_POST['site_id'] : null,
            'building_id' => !empty($_POST['building_id']) ? $_POST['building_id'] : null,
            'room_id' => !empty($_POST['room_id']) ? $_POST['room_id'] : null,
            'status_id' => !empty($_POST['status_id']) ? $_POST['status_id'] : 1,
            'priority_id' => $priorityId, // Priorité forcée si préventive
            'type_id' => !empty($_POST['type_id']) ? $_POST['type_id'] : null,
            'description' => $_POST['description'] ?? '',
            'demande_par' => !empty($_POST['demande_par']) ? $_POST['demande_par'] : null,
            'ref_client' => !empty($_POST['ref_client']) ? $_POST['ref_client'] : null,
            'contact_client' => !empty($_POST['contact_client']) ? $_POST['contact_client'] : null,
            'contract_id' => !empty($_POST['contract_id']) ? $_POST['contract_id'] : null,
            'is_preventive' => $isPreventive,
        ];

        // Traiter la date et l'heure de création
        $createdDate = $_POST['created_date'] ?? date('Y-m-d');
        $createdTime = $_POST['created_time'] ?? date('H:i');
        $data['created_at'] = $createdDate . ' ' . $createdTime . ':00';

        // Valider les données requises
        if (empty($data['title'])) {
            $_SESSION['error'] = "Le titre est obligatoire.";
            if (isset($data['client_id'])) {
                header('Location: ' . BASE_URL . 'interventions/add?client_id=' . $data['client_id']);
            } else {
                header('Location: ' . BASE_URL . 'interventions/add');
            }
            exit;
        }

        if (empty($data['client_id'])) {
            $_SESSION['error'] = "Le client est obligatoire.";
            header('Location: ' . BASE_URL . 'interventions/add');
            exit;
        }

        if (empty($data['type_id'])) {
            $_SESSION['error'] = "Le type d'intervention est obligatoire.";
            if (isset($data['client_id'])) {
                header('Location: ' . BASE_URL . 'interventions/add?client_id=' . $data['client_id']);
            } else {
                header('Location: ' . BASE_URL . 'interventions/add');
            }
            exit;
        }

        // Valider le format de l'email si renseigné
        if (!empty($data['contact_client'])) {
            if (!filter_var($data['contact_client'], FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = "Le format de l'email de contact est invalide.";
                header('Location: ' . BASE_URL . 'interventions/add');
                exit;
            }
        }

        // Valider le contrat
        if (empty($data['contract_id'])) {
            $_SESSION['error'] = "Le contrat est obligatoire.";
            if (isset($data['client_id'])) {
                header('Location: ' . BASE_URL . 'interventions/add?client_id=' . $data['client_id']);
            } else {
                header('Location: ' . BASE_URL . 'interventions/add');
            }
            exit;
        }

        // Vérifier si l'intervention est en train d'être créée avec le statut fermé
        if ($data['status_id'] == 6) {
            $data['tickets_used'] = null;
            $data['closed_at'] = null;
        }

        // Créer l'intervention
        $sql = "INSERT INTO interventions (
            title, client_id, site_id,building_id, room_id, status_id, 
            priority_id, type_id, description, demande_par, ref_client, contact_client, 
            contract_id, reference, tickets_used, closed_at, created_at, is_preventive
        ) VALUES (
            :title, :client_id, :site_id, :building_id, :room_id, :status_id, 
            :priority_id, :type_id, :description, :demande_par, :ref_client, :contact_client, 
            :contract_id, :reference, :tickets_used, :closed_at, :created_at, :is_preventive
        )";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':title' => $data['title'],
            ':client_id' => $data['client_id'],
            ':site_id' => $data['site_id'],
            ':building_id' => $data['building_id'],
            ':room_id' => $data['room_id'],
            ':status_id' => $data['status_id'],
            ':priority_id' => $data['priority_id'],
            ':type_id' => $data['type_id'],
            ':description' => $data['description'],
            ':demande_par' => $data['demande_par'],
            ':ref_client' => $data['ref_client'],
            ':contact_client' => $data['contact_client'],
            ':contract_id' => $data['contract_id'],
            ':reference' => $this->interventionModel->generateReference($data['client_id']),
            ':tickets_used' => $data['tickets_used'] ?? null,
            ':closed_at' => $data['closed_at'] ?? null,
            ':created_at' => $data['created_at'],
            ':is_preventive' => $data['is_preventive']
        ]);

        if ($result) {
            $interventionId = $this->db->lastInsertId();

            // Déduire les tickets du contrat si l'intervention est créée avec le statut fermé
            if ($data['status_id'] == 6 && !empty($data['contract_id']) && !empty($data['tickets_used'])) {
                $this->deductTicketsFromContract($data['contract_id'], $data['tickets_used'], $interventionId);
            }

            // Enregistrer l'action dans l'historique
            $sql = "INSERT INTO intervention_history (
                    intervention_id, field_name, old_value, new_value, changed_by, description
                ) VALUES (
                    :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':intervention_id' => $interventionId,
                ':field_name' => 'Création',
                ':old_value' => '',
                ':new_value' => '',
                ':changed_by' => $_SESSION['user']['id'],
                ':description' => "Intervention créée" . ($data['is_preventive'] == 1 ? " (Préventive)" : "")
            ]);

            // Envoyer l'email de création d'intervention
            try {
                $this->mailService->sendInterventionCreated($interventionId);
            } catch (Exception $e) {
                custom_log_mail("Erreur envoi email création intervention $interventionId : " . $e->getMessage(), 'ERROR');
            }

            $_SESSION['success'] = "Intervention créée avec succès.";

            // Gérer le retour intelligent
            $returnTo = $_GET['return_to'] ?? 'view_intervention';
            if ($returnTo === 'view') {
                $clientId = $data['client_id'] ?? null;
                if ($clientId) {
                    header('Location: ' . BASE_URL . 'clients/view/' . $clientId . '?active_tab=interventions-tab');
                } else {
                    header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
                }
            } else {
                header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
            }
            exit;
        } else {
            $_SESSION['error'] = "Erreur lors de la création de l'intervention.";

            $returnTo = $_GET['return_to'] ?? 'view_intervention';
            if ($returnTo === 'view') {
                $clientId = $data['client_id'] ?? null;
                if ($clientId) {
                    header('Location: ' . BASE_URL . 'interventions/add?client_id=' . $clientId . '&return_to=view');
                } else {
                    header('Location: ' . BASE_URL . 'interventions/add');
                }
            } else {
                header('Location: ' . BASE_URL . 'interventions/add');
            }
            exit;
        }
    }
    /**
     * Modifie un commentaire
     */
    public function editComment($commentId)
    {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer le commentaire
        $sql = "SELECT id, intervention_id, comment, visible_by_client, is_solution, is_observation, pour_bon_intervention, created_by, created_at FROM intervention_comments WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment) {
            $_SESSION['error'] = "Commentaire introuvable.";
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($comment['intervention_id']);

        if (!$intervention) {
            $_SESSION['error'] = "Intervention introuvable.";
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }

        // Vérifier si l'intervention est fermée
        if ($intervention['status_id'] == 6) { // 6 = Fermé
            $_SESSION['error'] = "Impossible de modifier un commentaire d'une intervention fermée.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $intervention['id']);
            exit;
        }

        // Récupérer les données du formulaire
        $newComment = $_POST['comment'] ?? '';
        $visibleByClient = isset($_POST['visible_by_client']) ? 1 : 0;
        $isSolution = isset($_POST['is_solution']) ? 1 : 0;
        $isObservation = isset($_POST['is_observation']) ? 1 : 0;

        if (empty($newComment)) {
            $_SESSION['error'] = "Le commentaire ne peut pas être vide.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $intervention['id']);
            exit;
        }

        // Mettre à jour le commentaire
        $sql = "UPDATE intervention_comments SET 
                comment = :comment,
                visible_by_client = :visible_by_client,
                is_solution = :is_solution,
                is_observation = :is_observation
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':comment' => $newComment,
            ':visible_by_client' => $visibleByClient,
            ':is_solution' => $isSolution,
            ':is_observation' => $isObservation,
            ':id' => $commentId
        ]);

        if ($result) {
            // Enregistrer l'action dans l'historique
            $sql = "INSERT INTO intervention_history (
                        intervention_id, field_name, old_value, new_value, changed_by, description
                    ) VALUES (
                        :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':intervention_id' => $intervention['id'],
                ':field_name' => 'Commentaire',
                ':old_value' => $comment['comment'],
                ':new_value' => $newComment,
                ':changed_by' => $_SESSION['user']['id'],
                ':description' => "Commentaire modifié" . ($isSolution ? " (marqué comme solution)" : "") . ($visibleByClient ? " (visible par le client)" : "")
            ]);

            $_SESSION['success'] = "Commentaire modifié avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la modification du commentaire.";
        }

        header('Location: ' . BASE_URL . 'interventions/view/' . $intervention['id']);
        exit;
    }

    /**
     * Récupère tous les statuts disponibles
     */
    public function getAllStatuses()
    {
        $sql = "SELECT id, name, color, is_critical, created_at FROM intervention_statuses ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * S'auto-affecter une intervention
     */
    public function assignToMe($id)
    {
        checkInterventionManagementAccess();

        $intervention = $this->interventionModel->getById($id);

        if (!$intervention) {
            $_SESSION['error'] = "Intervention introuvable.";
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }

        if ($intervention['status_id'] == 6) {
            $_SESSION['error'] = "Impossible de modifier une intervention fermée.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        // Vérifier si le technicien est déjà assigné
        $sql = "SELECT COUNT(*) FROM intervention_techniciens WHERE intervention_id = :id AND technicien_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':user_id' => $_SESSION['user']['id']]);
        $alreadyAssigned = $stmt->fetchColumn() > 0;

        if ($alreadyAssigned) {
            $_SESSION['info'] = "Vous êtes déjà affecté à cette intervention.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        // Ajouter le technicien à l'intervention
        $sql = "INSERT INTO intervention_techniciens (intervention_id, technicien_id, deplacement, created_at) 
            VALUES (:id, :user_id, 0, NOW())";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([':id' => $id, ':user_id' => $_SESSION['user']['id']]);

        if ($result) {
            $_SESSION['success'] = "Vous avez été affecté à cette intervention.";
        } else {
            $_SESSION['error'] = "Une erreur est survenue lors de l'affectation.";
        }

        header('Location: ' . BASE_URL . 'interventions/view/' . $id);
        exit;
    }
    /**
     * Calcule le total de tickets pour tous les techs d'une intervention.
     *
     * @param int   $interventionId
     * @param mixed $durationUnused  (ignoré, on lit temps_passe par tech)
     * @param int   $typeId
     * @return float
     */
    private function calculateTotalTicketsUsed(int $interventionId, $durationUnused, $typeId): float
    {
        $sql = "SELECT technicien_id, temps_passe, is_qualified, deplacement
            FROM intervention_techniciens
            WHERE intervention_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$interventionId]);
        $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($technicians)) {
            return 0;
        }

        $type = $this->interventionModel->getTypeInfo($typeId);
        $isRemote = empty($type['requires_travel']) || (int) ($type['requires_travel'] ?? 0) === 0;

        $total = 0.0;
        foreach ($technicians as $tech) {
            $total += $this->computeTicketsForTech($tech, $isRemote);
        }
        return $total;
    }
    /**
     * Calcule les tickets pour UN technicien selon les nouvelles règles.
     *
     * @param array $tech  Ligne de intervention_techniciens
     *   - temps_passe  : minutes (peut être null)
     *   - is_qualified : 0|1
     *   - deplacement  : 0|1
     * @param bool  $isRemote  true = inter à distance ou tél
     * @return float
     */
    /**
     * Calcule les tickets pour UN technicien selon les nouvelles règles.
     * On utilise is_qualified stocké dans intervention_techniciens, pas le coef_utilisateur du profil.
     *
     * @param array $tech  Ligne de intervention_techniciens
     *   - temps_passe  : minutes (peut être null)
     *   - is_qualified : 0|1 (stocké dans l'intervention, pas dans le profil)
     *   - deplacement  : 0|1
     * @param bool  $isRemote  true = inter à distance ou tél
     * @return float
     */
    private function computeTicketsForTech(array $tech, bool $isRemote): float
    {
        $minutes = (float) ($tech['temps_passe'] ?? 0);
        // Utiliser is_qualified de l'intervention_techniciens, PAS du profil utilisateur
        $isQualified = (int) ($tech['is_qualified'] ?? 0) === 1;
        $hasTravel = (int) ($tech['deplacement'] ?? 0) === 1;

        $hours = $minutes / 60.0;
        $raw = 0.0;

        // Déplacement : +1 ticket
        if ($hasTravel) {
            $raw += 1.0;
        }

        // Main d'œuvre : 1h = 1 ticket
        $raw += $hours;

        // Technicien qualifié : la première heure compte double (+1 ticket supplémentaire)
        // Uniquement si la durée est d'au moins 1 heure
        if ($isQualified && $hours >= 1.0) {
            $raw += 1.0;
        }

        // Arrondi selon type d'intervention
        if ($isRemote) {
            return round($raw * 2) / 2;   // ½ ticket
        } else {
            return (float) ceil($raw);    // entier supérieur
        }
    }

    /**
     * Retourne le détail du calcul de tickets pour la modale de fermeture.
     * Appelé en GET par la modale via fetch().
     */
    public function getCloseDetails($id)
    {
        checkInterventionManagementAccess();
        header('Content-Type: application/json');

        $intervention = $this->interventionModel->getById($id);

        if (!$intervention) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Intervention introuvable.']);
            exit;
        }

        // Déjà fermée : on ne peut pas recalculer
        if ((int) $intervention['status_id'] === 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Cette intervention est déjà fermée.']);
            exit;
        }

        // Vérifier qu'au moins un technicien est assigné
        $sql = "SELECT it.technicien_id,
               it.temps_passe,
               it.is_qualified,
               it.deplacement,
               CONCAT(u.first_name,' ',u.last_name) AS technician_name
        FROM intervention_techniciens it
        JOIN users u ON it.technicien_id = u.id
        WHERE it.intervention_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($technicians)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => "Impossible de fermer l'intervention sans avoir assigné au moins un technicien.",
            ]);
            exit;
        }

        // Infos type d'intervention
        $type = $this->interventionModel->getTypeInfo($intervention['type_id'] ?? null);
        $isRemote = empty($type['requires_travel']) || (int) ($type['requires_travel'] ?? 0) === 0;

        // Calcul détaillé par technicien
        $ticketsPerTech = [];
        $totalTickets = 0.0;

        foreach ($technicians as $tech) {
            $minutes = (float) ($tech['temps_passe'] ?? 0);
            $isQualified = (int) ($tech['is_qualified'] ?? 0) === 1;
            $hasTravel = (int) ($tech['deplacement'] ?? 0) === 1;
            $hours = $minutes / 60.0;

            $raw = 0.0;
            $parts = [];

            // 1. Déplacement = 1 ticket
            if ($hasTravel) {
                $raw += 1.0;
                $parts[] = '+1 déplacement';
            }

            // 2. Main d'œuvre : 1h = 1 ticket
            $raw += $hours;

            // Affichage de la MO (sans le détail des heures décimales inutiles)
            if ($hours == floor($hours)) {
                $parts[] = number_format($hours, 0, '.', '') . 'h de main d\'œuvre';
            } else {
                $parts[] = number_format($hours, 2, '.', '') . 'h de main d\'œuvre';
            }

            // 3. Technicien qualifié : la première heure compte double
            //    Cela signifie +1 ticket supplémentaire si la durée atteint au moins 1h
            if ($isQualified && $hours >= 1.0) {
                $raw += 1.0;
                $parts[] = '(+1 (prime 1ère heure qualifié))';
            }

            // 4. Arrondi selon le type d'intervention
            if ($isRemote) {
                // À distance/téléphone : arrondi à la 1/2 ticket
                $rounded = round($raw * 2) / 2;
            } else {
                // Sur site : arrondi à l'entier supérieur
                $rounded = (float) ceil($raw);
            }

            $totalTickets += $rounded;

            $ticketsPerTech[] = [
                'technicien_id' => (int) $tech['technicien_id'],
                'name' => $tech['technician_name'],
                'duration_minutes' => $minutes,
                'duration_hours' => round($hours, 2),
                'is_qualified' => $isQualified,
                'has_travel' => $hasTravel,
                'tickets_raw' => round($raw, 2),
                'tickets_rounded' => $rounded,
                'formula' => implode(' + ', $parts)
                    . ' = ' . round($raw, 2)
                    . ' → ' . $rounded
            ];
        }

        // Infos contrat
        $contractInfo = null;
        if (!empty($intervention['contract_id']) && isContractTicketById($intervention['contract_id'])) {
            $contract = $this->contractModel->getContractById($intervention['contract_id']);
            if ($contract) {
                $contractInfo = [
                    'id' => $contract['id'],
                    'name' => $contract['name'],
                    'tickets_remaining' => (float) ($contract['tickets_remaining'] ?? 0),
                    'tickets_number' => (float) ($contract['tickets_number'] ?? 0),
                    'tickets_after_close' => max(0, (float) ($contract['tickets_remaining'] ?? 0) - $totalTickets),
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'intervention' => [
                'id' => $intervention['id'],
                'reference' => $intervention['reference'],
                'title' => $intervention['title'],
                'type_name' => $type['name'] ?? '',
                // 'is_remote' => $isRemote,
                'technician_count' => count($technicians),
            ],
            'technicians' => $ticketsPerTech,
            'total_tickets' => $totalTickets,
            'is_remote' => $isRemote,
            'contract' => $contractInfo,
        ]);
        exit;
    }

    /**
     * Ferme une intervention.
     *
     * POST params attendus (via modale JS) :
     *  - tickets_used  (float)  : décompte validé par l'opérateur
     *  - send_email    (0|1)    : envoyer ou non un email
     *
     * Guard anti-double-déduction : si status_id == 6 on bloque.
     */
    public function close($id)
    {
        checkInterventionManagementAccess();

        $intervention = $this->interventionModel->getById($id);

        if (!$intervention) {
            $_SESSION['error'] = 'Intervention introuvable.';
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }

        // ── GUARD : déjà fermée → impossible de re-déduire ──────────────────────
        if ((int) $intervention['status_id'] === 6) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Cette intervention est déjà fermée.',
                ]);
                exit;
            }
            $_SESSION['info'] = 'Cette intervention est déjà fermée.';
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        // Vérifier qu'au moins un technicien est assigné
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM intervention_techniciens WHERE intervention_id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() === 0) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => "Impossible de fermer l'intervention sans technicien assigné.",
                ]);
                exit;
            }
            $_SESSION['error'] = "Impossible de fermer l'intervention sans technicien assigné.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        try {
            $this->db->beginTransaction();

            // Nombre de tickets : priorité au POST (choix de l'opérateur dans la modale)
            $ticketsUsed = 0.0;
            if (isset($_POST['tickets_used']) && is_numeric($_POST['tickets_used'])) {
                $ticketsUsed = max(0.0, (float) $_POST['tickets_used']);
            } else {
                // Fallback automatique (ne devrait pas arriver si la modale est utilisée)
                $ticketsUsed = $this->calculateTotalTicketsUsed($id, null, $intervention['type_id']);
            }

            custom_log("=== FERMETURE INTERVENTION $id ===", 'INFO');
            custom_log("Tickets à déduire: $ticketsUsed", 'INFO');
            custom_log("Contrat ID: " . ($intervention['contract_id'] ?? 'aucun'), 'INFO');

            // ── VÉRIFICATION DU SOLDE CONTRAT AVANT DÉDUCTION ──────────────────────
            if ($ticketsUsed > 0 && !empty($intervention['contract_id']) && isContractTicketById($intervention['contract_id'])) {
                // Récupérer le solde actuel du contrat
                $stmtCheck = $this->db->prepare("SELECT tickets_remaining FROM contracts WHERE id = ?");
                $stmtCheck->execute([$intervention['contract_id']]);
                $currentRemaining = (float) $stmtCheck->fetchColumn();

                custom_log("Solde contrat AVANT déduction: $currentRemaining", 'INFO');

                // Vérifier si le solde est suffisant
                if ($currentRemaining < $ticketsUsed) {
                    // Solde insuffisant
                    $errorMsg = "Solde de tickets insuffisant sur le contrat. Solde actuel: $currentRemaining, Tickets à déduire: $ticketsUsed";
                    custom_log($errorMsg, 'ERROR');

                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => false,
                            'error' => $errorMsg . ". Veuillez ajuster le nombre de tickets ou ajouter des tickets au contrat."
                        ]);
                        exit;
                    }
                    $_SESSION['error'] = $errorMsg;
                    header('Location: ' . BASE_URL . 'interventions/view/' . $id);
                    exit;
                }
            }

            $closedAt = date('Y-m-d H:i:s');

            // Fermer l'intervention
            $sql = "UPDATE interventions
            SET status_id       = 6,
                closed_at       = :ca,
                tickets_used    = :tu,
                needs_completion= 0,
                updated_at      = NOW()
            WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':ca' => $closedAt, ':tu' => $ticketsUsed, ':id' => $id]);

            // Déduire les tickets du contrat (seulement si contrat à tickets)
            if ($ticketsUsed > 0 && !empty($intervention['contract_id']) && isContractTicketById($intervention['contract_id'])) {
                $this->deductTicketsFromContract($intervention['contract_id'], $ticketsUsed, $id);
            }

            // Vérifier le nouveau solde du contrat
            if (!empty($intervention['contract_id']) && isContractTicketById($intervention['contract_id'])) {
                $stmtCheck = $this->db->prepare("SELECT tickets_remaining FROM contracts WHERE id = ?");
                $stmtCheck->execute([$intervention['contract_id']]);
                $newRemaining = $stmtCheck->fetchColumn();
                custom_log("NOUVEAU SOLDE CONTRAT APRÈS FERMETURE: $newRemaining", 'INFO');

                // Alerter si le solde est négatif
                if ($newRemaining < 0) {
                    custom_log("⚠️ ATTENTION: Solde contrat négatif: $newRemaining", 'WARNING');
                }
            }

            // Historique intervention
            $oldStatusName = $this->getStatusName($intervention['status_id']);
            $this->insertHistory(
                $id,
                'Statut',
                $oldStatusName,
                'Fermé',
                "Intervention fermée — {$ticketsUsed} ticket(s) déduit(s)"
            );

            // Email optionnel
            if (!empty($_POST['send_email']) && (int) $_POST['send_email'] === 1) {
                try {
                    $this->mailService->sendInterventionClosed($id, true);
                } catch (\Exception $e) {
                    custom_log_mail("Erreur email fermeture $id : " . $e->getMessage(), 'ERROR');
                }
            }

            $this->db->commit();

            $msg = "Intervention fermée avec succès.";
            if ($ticketsUsed > 0) {
                $msg .= " {$ticketsUsed} ticket(s) déduit(s) du contrat.";
            }

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $msg]);
                exit;
            }

            $_SESSION['success'] = $msg;

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            custom_log("Erreur close() intervention $id : " . $e->getMessage(), 'ERROR');

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => "Erreur lors de la fermeture."]);
                exit;
            }
            $_SESSION['error'] = "Erreur lors de la fermeture de l'intervention.";
        }

        header('Location: ' . BASE_URL . 'interventions/view/' . $id);
        exit;
    }

    /**
     * Insère une ligne dans intervention_history.
     */
    private function insertHistory(
        int $interventionId,
        string $field,
        string $old,
        string $new,
        string $desc
    ): void {
        $sql = "INSERT INTO intervention_history
                (intervention_id, field_name, old_value, new_value, changed_by, description)
            VALUES (:iid, :fn, :ov, :nv, :by, :desc)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':iid' => $interventionId,
            ':fn' => $field,
            ':ov' => $old,
            ':nv' => $new,
            ':by' => $_SESSION['user']['id'],
            ':desc' => $desc,
        ]);
    }
    /**
     * Re-crédite les tickets d'un contrat suite à la réouverture d'une inter.
     * Met à jour tickets_remaining ET enregistre dans l'historique du contrat.
     */
    private function recreditTicketsForIntervention($interventionId, $intervention): void
    {
        custom_log("=== RECRÉDIT TICKETS POUR INTERVENTION $interventionId ===", 'INFO');

        if (empty($intervention['contract_id'])) {
            custom_log("Pas de contrat associé, recrédit ignoré", 'INFO');
            return;
        }

        if (!isContractTicketById($intervention['contract_id'])) {
            custom_log("Contrat non ticket, recrédit ignoré", 'INFO');
            return;
        }

        $ticketsToRecredit = (float) ($intervention['tickets_used'] ?? 0);
        if ($ticketsToRecredit <= 0) {
            custom_log("Pas de tickets à recréditer (tickets_used = 0)", 'INFO');
            return;
        }

        // Récupérer le solde actuel AVANT recrédit
        $stmt = $this->db->prepare("SELECT tickets_remaining FROM contracts WHERE id = :contract_id");
        $stmt->execute([':contract_id' => $intervention['contract_id']]);
        $currentRemaining = (float) $stmt->fetchColumn();
        custom_log("Solde contrat AVANT recrédit: $currentRemaining", 'INFO');
        custom_log("Tickets à recréditer: $ticketsToRecredit", 'INFO');

        // Mise à jour du solde du contrat (ADDITION des tickets) - UNE SEULE FOIS
        $sql = "UPDATE contracts SET tickets_remaining = tickets_remaining + :tickets WHERE id = :contract_id";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':tickets' => $ticketsToRecredit,
            ':contract_id' => $intervention['contract_id'],
        ]);

        if (!$result) {
            custom_log("ERREUR: Échec de la mise à jour du contrat", 'ERROR');
            throw new \Exception("Erreur lors du recrédit des tickets");
        }

        // Vérifier le nouveau solde
        $stmt = $this->db->prepare("SELECT tickets_remaining FROM contracts WHERE id = :contract_id");
        $stmt->execute([':contract_id' => $intervention['contract_id']]);
        $newRemaining = (float) $stmt->fetchColumn();
        custom_log("Solde contrat APRÈS recrédit: $newRemaining", 'INFO');

        // Enregistrer dans l'historique (sans modifier le solde)
        $sqlHistory = "INSERT INTO contract_history (
                contract_id, field_name, old_value, new_value, changed_by, description
            ) VALUES (
                :contract_id, :field_name, :old_value, :new_value, :changed_by, :description
            )";
        $stmtHistory = $this->db->prepare($sqlHistory);
        $interventionRef = $this->getInterventionReference($interventionId);
        $stmtHistory->execute([
            ':contract_id' => $intervention['contract_id'],
            ':field_name' => 'Tickets restants',
            ':old_value' => $currentRemaining,
            ':new_value' => $newRemaining,
            ':changed_by' => $_SESSION['user']['id'],
            ':description' => $interventionRef . ' — Recrédit automatique suite à réouverture : +' . $ticketsToRecredit . ' tickets'
        ]);

        custom_log("Recrédit de {$ticketsToRecredit} tickets effectué pour le contrat {$intervention['contract_id']}", 'INFO');
    }
    public function generateReport($id)
    {
        // Vérifier les permissions
        checkInterventionManagementAccess();

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($id);

        if (!$intervention) {
            // Rediriger vers la liste si l'intervention n'existe pas
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }

        // Générer le PDF
        $pdfPath = $this->generateInterventionReport($intervention);

        // Enregistrer le message de succès
        $_SESSION['success'] = "Le bon d'intervention a été généré avec succès.";

        // Lire et afficher le PDF
        $fullPath = __DIR__ . '/../' . $pdfPath;
        if (file_exists($fullPath)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($pdfPath) . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            readfile($fullPath);
            exit;
        } else {
            header('Location: ' . BASE_URL . 'interventions/edit/' . $id);
            exit;
        }
    }

    /**
     * Déduit les tickets utilisés d'un contrat
     */
    private function deductTicketsFromContract($contractId, $ticketsUsed, $interventionId = null)
    {
        custom_log("=== DÉDUCTION TICKETS CONTRAT $contractId ===", 'INFO');
        custom_log("Tickets à déduire: $ticketsUsed", 'INFO');

        if (!$contractId) {
            custom_log("Pas de contrat, déduction ignorée", 'INFO');
            return;
        }

        // Vérifier si le contrat est de type ticket
        $sql = "SELECT isticketcontract FROM contracts WHERE id = :contract_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':contract_id' => $contractId]);
        $contract = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$contract || $contract['isticketcontract'] != 1) {
            custom_log("Contrat non-ticket ou inexistant, déduction ignorée", 'INFO');
            return;
        }

        // Récupérer le solde avant déduction
        $stmt = $this->db->prepare("SELECT tickets_remaining FROM contracts WHERE id = :contract_id");
        $stmt->execute([':contract_id' => $contractId]);
        $currentRemaining = (float) $stmt->fetchColumn();
        custom_log("Solde AVANT déduction: $currentRemaining", 'INFO');

        // Construire le commentaire
        $comment = 'Déduction automatique - Intervention fermée';
        if ($interventionId) {
            $stmt = $this->db->prepare("SELECT reference FROM interventions WHERE id = ?");
            $stmt->execute([$interventionId]);
            $intervention = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($intervention && !empty($intervention['reference'])) {
                $comment = $intervention['reference'] . ' - ' . $comment;
            }
        }

        // Mettre à jour les tickets restants (UNE SEULE FOIS)
        $sql = "UPDATE contracts SET tickets_remaining = tickets_remaining - :tickets_used WHERE id = :contract_id";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':tickets_used' => $ticketsUsed,
            ':contract_id' => $contractId
        ]);

        if ($result) {
            // Vérifier le nouveau solde
            $stmt = $this->db->prepare("SELECT tickets_remaining FROM contracts WHERE id = :contract_id");
            $stmt->execute([':contract_id' => $contractId]);
            $newRemaining = (float) $stmt->fetchColumn();
            custom_log("Solde APRÈS déduction: $newRemaining", 'INFO');
            custom_log("Déduction de $ticketsUsed tickets réussie", 'INFO');

            // Enregistrer uniquement dans l'historique (sans modifier le solde)
            $sqlHistory = "INSERT INTO contract_history (
                    contract_id, field_name, old_value, new_value, changed_by, description
                ) VALUES (
                    :contract_id, :field_name, :old_value, :new_value, :changed_by, :description
                )";
            $stmtHistory = $this->db->prepare($sqlHistory);
            $stmtHistory->execute([
                ':contract_id' => $contractId,
                ':field_name' => 'Tickets restants',
                ':old_value' => $currentRemaining,
                ':new_value' => $newRemaining,
                ':changed_by' => $_SESSION['user']['id'],
                ':description' => $comment . ' : -' . $ticketsUsed . ' tickets'
            ]);
        } else {
            custom_log("ERREUR: Échec de la déduction des tickets", 'ERROR');
        }
    }
    /**
     * Force le nombre de tickets utilisés pour une intervention fermée (admin seulement)
     */
    public function forceTickets($id)
    {
        // Debug: Log de début
        error_log("DEBUG: forceTickets appelé avec ID: " . $id);

        // Vérifier les permissions
        $this->checkAccess();

        // Vérifier que l'utilisateur est admin
        if (!isAdmin()) {
            error_log("DEBUG: Utilisateur non admin: " . (isAdmin() ? "admin" : "non-admin"));
            $_SESSION['error'] = "Seuls les administrateurs peuvent forcer les tickets utilisés.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($id);
        if (!$intervention) {
            error_log("DEBUG: Intervention non trouvée: " . $id);
            $_SESSION['error'] = "Intervention non trouvée.";
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }

        // Vérifier que l'intervention est fermée
        if ($intervention['status_id'] != 6) {
            error_log("DEBUG: Intervention non fermée, status_id: " . $intervention['status_id']);
            $_SESSION['error'] = "Seules les interventions fermées peuvent avoir leurs tickets forcés.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log("DEBUG: Méthode POST détectée");
            error_log("DEBUG: POST data: " . print_r($_POST, true));

            $newTicketsUsed = (int) ($_POST['tickets_used'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');

            error_log("DEBUG: newTicketsUsed: " . $newTicketsUsed);
            error_log("DEBUG: reason: " . $reason);

            // Validation
            if ($newTicketsUsed < 0) {
                error_log("DEBUG: Tickets négatifs rejetés");
                $_SESSION['error'] = "Le nombre de tickets utilisés ne peut pas être négatif.";
                header('Location: ' . BASE_URL . 'interventions/view/' . $id);
                exit;
            }

            if (empty($reason)) {
                error_log("DEBUG: Raison vide rejetée");
                $_SESSION['error'] = "La raison de la modification est obligatoire.";
                header('Location: ' . BASE_URL . 'interventions/view/' . $id);
                exit;
            }

            // Calculer la différence
            $oldTicketsUsed = (int) ($intervention['tickets_used'] ?? 0);
            $difference = $newTicketsUsed - $oldTicketsUsed;

            error_log("DEBUG: oldTicketsUsed: " . $oldTicketsUsed);
            error_log("DEBUG: difference: " . $difference);

            try {
                $this->db->beginTransaction();

                // Mettre à jour les tickets utilisés de l'intervention
                $updateQuery = "UPDATE interventions SET tickets_used = :tickets_used, updated_at = NOW() WHERE id = :id";
                $stmt = $this->db->prepare($updateQuery);
                $stmt->bindParam(':tickets_used', $newTicketsUsed, PDO::PARAM_INT);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $result = $stmt->execute();

                error_log("DEBUG: Update intervention result: " . ($result ? 'success' : 'failed'));

                // Mettre à jour les tickets utilisés du contrat (seulement si c'est un contrat de type ticket)
                if ($intervention['contract_id']) {
                    // Vérifier si le contrat est de type ticket
                    $contract = $this->contractModel->getContractById($intervention['contract_id']);
                    if ($contract && isContractTicketById($contract['id'])) {
                        $contractQuery = "UPDATE contracts SET tickets_remaining = tickets_remaining - :difference WHERE id = :contract_id";
                        $stmt = $this->db->prepare($contractQuery);
                        $stmt->bindParam(':difference', $difference, PDO::PARAM_INT);
                        $stmt->bindParam(':contract_id', $intervention['contract_id'], PDO::PARAM_INT);
                        $result = $stmt->execute();

                        error_log("DEBUG: Update contract result: " . ($result ? 'success' : 'failed'));

                        // Enregistrer la modification dans l'historique du contrat
                        if ($difference != 0) {
                            // Construire le message avec la référence de l'intervention
                            $interventionRef = $intervention['reference'] ?? '#' . $intervention['id'];
                            $message = $interventionRef . ' - Modification forcée des tickets : ' . $reason;

                            $this->contractModel->recordTicketModification(
                                $intervention['contract_id'],
                                $difference,
                                $message
                            );
                        }
                    } else {
                        error_log("DEBUG: Contrat non-ticket, pas de mise à jour des tickets");
                    }
                }

                // Enregistrer l'historique de la modification (optionnel)
                try {
                    $historyDescription = "Changement manuel tickets utilisés : " . $newTicketsUsed . " avant : " . $oldTicketsUsed;
                    if (!empty($reason)) {
                        $historyDescription .= "\nRaison : " . $reason;
                    }

                    $historyQuery = "INSERT INTO intervention_history (intervention_id, field_name, old_value, new_value, changed_by, description, created_at) 
                                   VALUES (:intervention_id, 'tickets_used', :old_value, :new_value, :changed_by, :description, NOW())";
                    $stmt = $this->db->prepare($historyQuery);
                    $stmt->bindParam(':intervention_id', $id, PDO::PARAM_INT);
                    $stmt->bindParam(':old_value', $oldTicketsUsed, PDO::PARAM_INT);
                    $stmt->bindParam(':new_value', $newTicketsUsed, PDO::PARAM_INT);
                    $stmt->bindParam(':changed_by', $_SESSION['user']['id'], PDO::PARAM_INT);
                    $stmt->bindParam(':description', $historyDescription, PDO::PARAM_STR);
                    $result = $stmt->execute();

                    error_log("DEBUG: Insert history result: " . ($result ? 'success' : 'failed'));
                } catch (Exception $historyError) {
                    error_log("DEBUG: Erreur lors de l'insertion dans l'historique : " . $historyError->getMessage());
                    // On continue même si l'historique échoue
                }

                $this->db->commit();
                error_log("DEBUG: Transaction commité avec succès");

                $_SESSION['success'] = "Tickets utilisés modifiés avec succès. Différence : " . ($difference >= 0 ? '+' : '') . $difference . " tickets.";

            } catch (Exception $e) {
                $this->db->rollBack();
                error_log("DEBUG: Exception lors du forçage des tickets : " . $e->getMessage());
                error_log("DEBUG: Stack trace : " . $e->getTraceAsString());
                error_log("Erreur lors du forçage des tickets : " . $e->getMessage());
                $_SESSION['error'] = "Erreur lors de la modification des tickets utilisés. Détails : " . $e->getMessage();
            }

            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        error_log("DEBUG: Méthode non POST, redirection");
        // Si ce n'est pas un POST, rediriger vers la vue
        header('Location: ' . BASE_URL . 'interventions/view/' . $id);
        exit;
    }

    /**
     * Supprime une intervention (admin seulement)
     * Re-crédite les tickets si l'intervention avait consommé des tickets
     */
    public function delete($id)
    {
        // Vérifier les permissions - admin seulement
        if (!isset($_SESSION['user']) || !isAdmin()) {
            $_SESSION['error'] = "Seuls les administrateurs peuvent supprimer des interventions.";
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }

        // Sécurité: ne pas autoriser une suppression via GET (confirmation + CSRF via POST)
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $_SESSION['error'] = "Suppression non autorisée sans confirmation (POST requis).";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($id);

        if (!$intervention) {
            $_SESSION['error'] = "Intervention introuvable.";
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }

        try {
            $this->db->beginTransaction();

            // Si l'intervention a des tickets utilisés et un contrat associé ET si c'est un contrat de type ticket, re-créditer les tickets
            if (!empty($intervention['tickets_used']) && !empty($intervention['contract_id'])) {
                // Vérifier si le contrat est de type ticket
                $contract = $this->contractModel->getContractById($intervention['contract_id']);
                if ($contract && isContractTicketById($contract['id'])) {
                    $ticketsToRecredit = $intervention['tickets_used'];

                    // Mettre à jour le nombre de tickets restants dans le contrat
                    $sql = "UPDATE contracts SET tickets_remaining = tickets_remaining + :tickets_used WHERE id = :contract_id";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        ':tickets_used' => $ticketsToRecredit,
                        ':contract_id' => $intervention['contract_id']
                    ]);

                    // Enregistrer le re-crédit dans l'historique du contrat
                    $reference = $intervention['reference'] ?? "ID: {$id}";
                    $this->contractModel->recordTicketAddition(
                        $intervention['contract_id'],
                        $ticketsToRecredit,
                        date('Y-m-d'),
                        "Re-crédit automatique - Suppression intervention annulée {$reference}"
                    );
                }
            }

            // Supprimer les commentaires de l'intervention
            $sql = "DELETE FROM intervention_comments WHERE intervention_id = :intervention_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':intervention_id' => $id]);

            // Supprimer l'historique de l'intervention
            $sql = "DELETE FROM intervention_history WHERE intervention_id = :intervention_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':intervention_id' => $id]);

            // Récupérer et supprimer les pièces jointes physiques
            $sql = "SELECT pj.* FROM pieces_jointes pj
                    INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                    WHERE lpj.type_liaison = 'intervention' AND lpj.entite_id = :intervention_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':intervention_id' => $id]);
            $piecesJointes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Supprimer les fichiers physiques
            foreach ($piecesJointes as $pieceJointe) {
                $filePath = __DIR__ . '/../../' . $pieceJointe['chemin_fichier'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            // Supprimer les pièces jointes de l'intervention
            $sql = "DELETE pj FROM pieces_jointes pj 
                    INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id 
                    WHERE lpj.type_liaison = 'intervention' AND lpj.entite_id = :intervention_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':intervention_id' => $id]);

            // Supprimer les liaisons de pièces jointes
            $sql = "DELETE FROM liaisons_pieces_jointes 
                    WHERE type_liaison = 'intervention' AND entite_id = :intervention_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':intervention_id' => $id]);

            // Supprimer l'intervention elle-même
            $sql = "DELETE FROM interventions WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);

            $this->db->commit();

            // Message de succès
            $message = "L'intervention a été supprimée avec succès.";
            if (!empty($intervention['tickets_used']) && !empty($intervention['contract_id'])) {
                $message .= " {$intervention['tickets_used']} tickets ont été re-crédités au contrat.";
            }
            $_SESSION['success'] = $message;

        } catch (Exception $e) {
            $this->db->rollBack();
            custom_log("Erreur lors de la suppression de l'intervention : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors de la suppression de l'intervention.";
        }

        header('Location: ' . $this->getInterventionsListUrl());
        exit;
    }

    /**
     * Récupère les informations d'une pièce jointe
     */
    public function getAttachmentInfo($attachmentId)
    {
        // Vérifier les permissions
        if (!isset($_SESSION['user']) || (!isStaff() && !isAdmin())) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Non autorisé']);
            exit;
        }

        try {
            $attachment = $this->interventionModel->getPieceJointeById($attachmentId);

            if (!$attachment) {
                throw new Exception("Pièce jointe non trouvée");
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'attachment' => $attachment
            ]);
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des informations de la pièce jointe : " . $e->getMessage(), 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Met à jour le nom d'une pièce jointe
     */
    public function updateAttachmentName($attachmentId)
    {
        // Vérifier les permissions
        if (!isset($_SESSION['user']) || (!isStaff() && !isAdmin())) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Non autorisé']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
            exit;
        }

        try {
            // Récupérer les données JSON
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['nom_fichier']) || empty(trim($input['nom_fichier']))) {
                throw new Exception("Le nom du fichier ne peut pas être vide");
            }

            $newName = trim($input['nom_fichier']);

            // Vérifier que la pièce jointe existe
            $attachment = $this->interventionModel->getPieceJointeById($attachmentId);
            if (!$attachment) {
                throw new Exception("Pièce jointe non trouvée");
            }

            // Mettre à jour le nom
            $success = $this->interventionModel->updateAttachmentName($attachmentId, $newName);

            if ($success) {
                $oldDisplayName = $attachment['nom_personnalise'] ?? $attachment['nom_fichier'];
                // Enregistrer l'action dans l'historique
                $sql = "INSERT INTO intervention_history (
                            intervention_id, field_name, old_value, new_value, changed_by, description
                        ) VALUES (
                            :intervention_id, 'attachment_name', :old_value, :new_value, :changed_by, :description
                        )";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':intervention_id' => $attachment['entite_id'],
                    ':old_value' => $oldDisplayName,
                    ':new_value' => $newName,
                    ':changed_by' => $_SESSION['user']['id'],
                    ':description' => "Nom de la pièce jointe modifié : " . $oldDisplayName . " → " . $newName
                ]);

                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Nom mis à jour avec succès']);
            } else {
                throw new Exception("Erreur lors de la mise à jour du nom");
            }
        } catch (Exception $e) {
            custom_log("Erreur lors de la mise à jour du nom de la pièce jointe : " . $e->getMessage(), 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Afficher la page de génération du bon d'intervention
     */
    public function generateBon($interventionId)
    {
        if (!canModifyInterventions()) {
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }

        try {
            // Récupérer l'intervention avec toutes les données nécessaires
            $intervention = $this->interventionModel->getById($interventionId);

            if (!$intervention) {
                $_SESSION['error'] = 'Intervention non trouvée';
                header('Location: ' . $this->getInterventionsListUrl());
                exit;
            }

            // Récupérer les commentaires
            $comments = $this->getComments($interventionId);

            // Récupérer les pièces jointes
            $attachments = $this->getAttachments($interventionId);

            // Récupérer les informations du contrat si disponible
            if (!empty($intervention['contract_id'])) {
                $contract = $this->contractModel->getContractById($intervention['contract_id']);
                if ($contract) {
                    $intervention['contract_type_name'] = $contract['contract_type_name'] ?? '';
                    $intervention['tickets_remaining'] = $contract['tickets_remaining'] ?? 0;
                }
            }

            // Inclure la vue
            include __DIR__ . '/../views/interventions/generate_bon.php';

        } catch (Exception $e) {
            custom_log("Erreur lors de l'affichage de la génération du bon d'intervention : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = 'Erreur lors du chargement de la page';
            header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
            exit;
        }
    }

    /**
     * Sauvegarder la sélection des éléments pour le bon d'intervention
     */
    public function saveBonSelection($interventionId)
    {
        if (!canModifyInterventions()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Accès refusé']);
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            $selectedComments = $input['comments'] ?? [];
            $selectedAttachments = $input['attachments'] ?? [];

            // Mettre à jour les commentaires
            $this->interventionModel->updateCommentsForBon($interventionId, $selectedComments);

            // Mettre à jour les pièces jointes
            $this->interventionModel->updateAttachmentsForBon($interventionId, $selectedAttachments);

            echo json_encode(['success' => true, 'message' => 'Sélection sauvegardée avec succès']);

        } catch (Exception $e) {
            custom_log("Erreur lors de la sauvegarde de la sélection du bon d'intervention : " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
        }
    }

    /**
     * Génère le PDF du bon d'intervention avec les éléments sélectionnés
     */
    public function generateBonPdf($interventionId)
    {
        if (!canModifyInterventions()) {
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }

        try {
            // Récupérer l'intervention avec toutes les données nécessaires
            $intervention = $this->interventionModel->getById($interventionId);

            if (!$intervention) {
                $_SESSION['error'] = 'Intervention non trouvée';
                header('Location: ' . $this->getInterventionsListUrl());
                exit;
            }


            // Récupérer les commentaires sélectionnés pour le bon
            $selectedComments = $this->getCommentsForBon($interventionId);

            // Récupérer les pièces jointes sélectionnées pour le bon
            $selectedAttachments = $this->getAttachmentsForBon($interventionId);

            // Récupérer les informations du contrat si disponible
            if (!empty($intervention['contract_id'])) {
                $contract = $this->contractModel->getContractById($intervention['contract_id']);
                if ($contract) {
                    $intervention['contract_type_name'] = $contract['contract_type_name'] ?? '';
                    $intervention['tickets_remaining'] = $contract['tickets_remaining'] ?? 0;
                }
            }

            // Générer le PDF
            try {
                $pdfPath = $this->generateBonInterventionPdf($intervention, $selectedComments, $selectedAttachments);
                custom_log("PDF généré avec succès: $pdfPath", 'INFO');
            } catch (Exception $e) {
                custom_log("Erreur lors de la génération du PDF: " . $e->getMessage(), 'ERROR');
                $_SESSION['error'] = 'Erreur lors de la génération du PDF: ' . $e->getMessage();
                header('Location: ' . BASE_URL . 'interventions/generateBon/' . $interventionId);
                exit;
            }

            // Lire et afficher le PDF
            if (file_exists($pdfPath)) {
                // Extraire le nom du fichier depuis le chemin
                $filename = basename($pdfPath);

                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . $filename . '"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                readfile($pdfPath);
                exit;
            } else {
                custom_log("Fichier PDF non trouvé: $pdfPath", 'ERROR');
                $_SESSION['error'] = 'Fichier PDF non trouvé: ' . $pdfPath;
                header('Location: ' . BASE_URL . 'interventions/generateBon/' . $interventionId);
                exit;
            }

        } catch (Exception $e) {
            custom_log("Erreur lors de la génération du PDF du bon d'intervention : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = 'Erreur lors de la génération du PDF';
            header('Location: ' . BASE_URL . 'interventions/generateBon/' . $interventionId);
            exit;
        }
    }

    /**
     * Récupère les commentaires sélectionnés pour le bon d'intervention
     */
    private function getCommentsForBon($interventionId)
    {
        $sql = "SELECT c.*, 
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM intervention_comments c
                LEFT JOIN users u ON c.created_by = u.id
                WHERE c.intervention_id = ? AND c.pour_bon_intervention = 1
                ORDER BY c.is_solution DESC, c.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$interventionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les pièces jointes sélectionnées pour le bon d'intervention
     */
    private function getAttachmentsForBon($interventionId)
    {
        $query = "
            SELECT 
                pj.*,
                st.setting_value as type_nom,
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                lpj.type_liaison,
                lpj.pour_bon_intervention
            FROM pieces_jointes pj
            LEFT JOIN settings st ON pj.type_id = st.id
            LEFT JOIN users u ON pj.created_by = u.id
            INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
            WHERE (lpj.type_liaison = 'intervention' OR lpj.type_liaison = 'bi')
            AND lpj.entite_id = :intervention_id
            AND lpj.pour_bon_intervention = 1
            ORDER BY pj.date_creation ASC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':intervention_id', $interventionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Génère le PDF du bon d'intervention avec les éléments sélectionnés
     * 
     * @param array $intervention Données de l'intervention
     * @param array $comments Commentaires sélectionnés
     * @param array $attachments Pièces jointes sélectionnées
     * @return string Chemin du fichier PDF généré
     */
    private function generateBonInterventionPdf($intervention, $comments, $attachments)
    {
        // Créer le dossier de stockage s'il n'existe pas
        $uploadDir = __DIR__ . '/../../uploads/interventions/' . $intervention['id'];
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Générer un nom de fichier unique avec la date et l'heure
        $fileName = 'BI_' . $intervention['reference'] . '_' . date('Ymd') . '_' . date('Hi') . '.pdf';
        $filePath = $uploadDir . '/' . $fileName;

        custom_log("Génération PDF - Dossier: $uploadDir", 'INFO');
        custom_log("Génération PDF - Fichier: $fileName", 'INFO');
        custom_log("Génération PDF - Chemin complet: $filePath", 'INFO');

        // Charger la classe InterventionPDF
        require_once __DIR__ . '/../classes/InterventionPDF.php';

        // Créer et générer le PDF avec les éléments sélectionnés
        $pdf = new InterventionPDF();
        $pdf->generateBonIntervention($intervention, $comments, $attachments);
        $pdf->Output($filePath, 'F');

        custom_log("PDF généré - Vérification existence: " . (file_exists($filePath) ? 'OUI' : 'NON'), 'INFO');

        // Ajouter le PDF comme pièce jointe via le modèle
        $data = [
            'nom_fichier' => $fileName, // Nom du fichier physique avec l'heure
            'nom_personnalise' => 'Bon_intervention_' . date('Ymd'), // Nom d'affichage personnalisé
            'chemin_fichier' => 'uploads/interventions/' . $intervention['id'] . '/' . $fileName,
            'type_fichier' => 'pdf',
            'taille_fichier' => filesize($filePath),
            'commentaire' => 'Bon d\'intervention généré automatiquement',
            'masque_client' => 0, // Visible par les clients
            'created_by' => $_SESSION['user']['id']
        ];

        // Ajouter la pièce jointe avec le type de liaison 'bi' (Bon d'Intervention)
        $pieceJointeId = $this->interventionModel->addPieceJointeWithType($intervention['id'], $data, 'bi');

        // Enregistrer l'action dans l'historique
        $sql = "INSERT INTO intervention_history (
                    intervention_id, field_name, old_value, new_value, changed_by, description
                ) VALUES (
                    :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':intervention_id' => $intervention['id'],
            ':field_name' => 'bon_intervention',
            ':old_value' => '',
            ':new_value' => 'Bon_intervention_' . date('Ymd'),
            ':changed_by' => $_SESSION['user']['id'],
            ':description' => 'Bon d\'intervention généré avec les éléments sélectionnés'
        ]);

        return $filePath;
    }

    /**
     * Gère les tickets lors du changement de contrat pour une intervention fermée
     * @param int $interventionId ID de l'intervention
     * @param array $oldIntervention Données de l'intervention avant modification
     * @param array $newData Nouvelles données de l'intervention
     */
    private function handleTicketManagementOnContractChange($interventionId, $oldIntervention, $newData)
    {
        // Vérifier si l'intervention était fermée (tickets déjà déduits)
        if ($oldIntervention['status_id'] != 6) {
            return false; // Intervention pas fermée, pas de gestion des tickets
        }

        // Vérifier si le contrat a changé
        $oldContractId = $oldIntervention['contract_id'] ?? null;
        $newContractId = $newData['contract_id'] ?? null;

        if ($oldContractId == $newContractId) {
            return false; // Pas de changement de contrat
        }

        // Vérifier si l'intervention avait des tickets utilisés
        $ticketsUsed = $oldIntervention['tickets_used'] ?? 0;
        if ($ticketsUsed <= 0) {
            return false; // Pas de tickets utilisés
        }

        // Récupérer les informations des contrats
        $oldContract = $this->getContractTicketInfo($oldContractId);
        $newContract = $this->getContractTicketInfo($newContractId);

        // Déterminer les actions à effectuer
        $oldIsTicketContract = $oldContract && isContractTicketById($oldContract['id']);
        $newIsTicketContract = $newContract && isContractTicketById($newContract['id']);

        // Historiser le changement de contrat dans l'historique de l'intervention
        $this->recordContractChangeInInterventionHistory($interventionId, $oldContract, $newContract, $ticketsUsed);

        // Si on passe d'un contrat à tickets à un autre contrat à tickets
        if ($oldIsTicketContract && $newIsTicketContract) {
            $this->handleTicketContractToTicketContract($oldContractId, $newContractId, $ticketsUsed, $interventionId);
            return true;
        }
        // Si on passe d'un contrat à tickets à un contrat sans tickets
        elseif ($oldIsTicketContract && !$newIsTicketContract) {
            $this->handleTicketContractToNonTicketContract($oldContractId, $ticketsUsed, $interventionId);
            return true;
        }
        // Si on passe d'un contrat sans tickets à un contrat à tickets
        elseif (!$oldIsTicketContract && $newIsTicketContract) {
            $this->handleNonTicketContractToTicketContract($newContractId, $ticketsUsed, $interventionId);
            return true;
        }

        return true; // Retourner true car on a historisé le changement même sans gestion de tickets
    }

    /**
     * Récupère les informations d'un contrat pour la gestion des tickets
     * @param int|null $contractId ID du contrat
     * @return array|null Informations du contrat
     */
    private function getContractTicketInfo($contractId)
    {
        if (!$contractId) {
            return null;
        }

        $sql = "SELECT id, name, tickets_number, tickets_remaining FROM contracts WHERE id = :contract_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':contract_id' => $contractId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Gère le passage d'un contrat à tickets à un autre contrat à tickets
     * @param int $oldContractId ID de l'ancien contrat
     * @param int $newContractId ID du nouveau contrat
     * @param int $ticketsUsed Nombre de tickets utilisés
     * @param int $interventionId ID de l'intervention
     */
    private function handleTicketContractToTicketContract($oldContractId, $newContractId, $ticketsUsed, $interventionId)
    {
        $contractModel = new ContractModel($this->db);

        // Récupérer la référence de l'intervention
        $interventionRef = $this->getInterventionReference($interventionId);

        // Recréditer l'ancien contrat
        $creditComment = $interventionRef . ' - Recrédit automatique - Changement de contrat';
        $contractModel->recordTicketModification($oldContractId, -$ticketsUsed, $creditComment);

        // Déduire du nouveau contrat
        $debitComment = $interventionRef . ' - Déduction automatique - Changement de contrat';
        $contractModel->recordTicketModification($newContractId, $ticketsUsed, $debitComment);

        custom_log("Tickets transférés de contrat $oldContractId vers contrat $newContractId pour intervention $interventionId", 'INFO');
    }

    /**
     * Gère le passage d'un contrat à tickets à un contrat sans tickets
     * @param int $oldContractId ID de l'ancien contrat
     * @param int $ticketsUsed Nombre de tickets utilisés
     * @param int $interventionId ID de l'intervention
     */
    private function handleTicketContractToNonTicketContract($oldContractId, $ticketsUsed, $interventionId)
    {
        $contractModel = new ContractModel($this->db);

        // Récupérer la référence de l'intervention
        $interventionRef = $this->getInterventionReference($interventionId);

        // Recréditer l'ancien contrat
        $creditComment = $interventionRef . ' - Recrédit automatique - Changement vers contrat sans tickets';
        $contractModel->recordTicketModification($oldContractId, -$ticketsUsed, $creditComment);

        custom_log("Tickets recrédités au contrat $oldContractId pour intervention $interventionId (changement vers contrat sans tickets)", 'INFO');
    }

    /**
     * Gère le passage d'un contrat sans tickets à un contrat à tickets
     * @param int $newContractId ID du nouveau contrat
     * @param int $ticketsUsed Nombre de tickets utilisés
     * @param int $interventionId ID de l'intervention
     */
    private function handleNonTicketContractToTicketContract($newContractId, $ticketsUsed, $interventionId)
    {
        $contractModel = new ContractModel($this->db);

        // Récupérer la référence de l'intervention
        $interventionRef = $this->getInterventionReference($interventionId);

        // Déduire du nouveau contrat
        $debitComment = $interventionRef . ' - Déduction automatique - Changement depuis contrat sans tickets';
        $contractModel->recordTicketModification($newContractId, $ticketsUsed, $debitComment);

        custom_log("Tickets déduits du contrat $newContractId pour intervention $interventionId (changement depuis contrat sans tickets)", 'INFO');
    }

    /**
     * Récupère la référence d'une intervention
     * @param int $interventionId ID de l'intervention
     * @return string Référence de l'intervention
     */
    private function getInterventionReference($interventionId)
    {
        $sql = "SELECT reference FROM interventions WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $interventionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['reference'] : "Intervention #$interventionId";
    }

    /**
     * Enregistre le changement de contrat dans l'historique de l'intervention
     * @param int $interventionId ID de l'intervention
     * @param array|null $oldContract Ancien contrat
     * @param array|null $newContract Nouveau contrat
     * @param int $ticketsUsed Nombre de tickets utilisés
     */
    private function recordContractChangeInInterventionHistory($interventionId, $oldContract, $newContract, $ticketsUsed)
    {
        try {
            // Préparer les valeurs d'affichage
            $oldContractName = $oldContract ? $oldContract['name'] : 'Aucun contrat';
            $newContractName = $newContract ? $newContract['name'] : 'Aucun contrat';

            // Construire la description détaillée
            $description = "Changement de contrat : $oldContractName → $newContractName";

            // Ajouter des détails sur la gestion des tickets
            $oldIsTicketContract = $oldContract && isContractTicketById($oldContract['id']);
            $newIsTicketContract = $newContract && isContractTicketById($newContract['id']);

            if ($oldIsTicketContract && $newIsTicketContract) {
                $description .= " (Transfert de $ticketsUsed tickets)";
            } elseif ($oldIsTicketContract && !$newIsTicketContract) {
                $description .= " (Recrédit de $ticketsUsed tickets à l'ancien contrat)";
            } elseif (!$oldIsTicketContract && $newIsTicketContract) {
                $description .= " (Déduction de $ticketsUsed tickets du nouveau contrat)";
            } elseif ($oldIsTicketContract || $newIsTicketContract) {
                $description .= " (Gestion des tickets effectuée)";
            }

            // Enregistrer dans l'historique de l'intervention
            $sql = "INSERT INTO intervention_history (
                        intervention_id, field_name, old_value, new_value, changed_by, description
                    ) VALUES (
                        :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                    )";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':intervention_id' => $interventionId,
                ':field_name' => 'Contrat associé',
                ':old_value' => $oldContractName,
                ':new_value' => $newContractName,
                ':changed_by' => $_SESSION['user']['id'],
                ':description' => $description
            ]);

            if ($result) {
                custom_log("Changement de contrat historisé pour intervention $interventionId : $oldContractName → $newContractName", 'INFO');
            } else {
                custom_log("Erreur lors de l'historisation du changement de contrat pour intervention $interventionId", 'ERROR');
            }

        } catch (Exception $e) {
            custom_log("Exception lors de l'historisation du changement de contrat pour intervention $interventionId : " . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Récupère les données pour l'envoi d'email (intervention + observations)
     * @param int $id ID de l'intervention
     */
    public function getEmailData($id)
    {
        header('Content-Type: application/json');

        try {
            // Vérifier les permissions
            $this->checkAccess();

            // Récupérer l'intervention
            $intervention = $this->interventionModel->getById($id);

            if (!$intervention) {
                echo json_encode(['success' => false, 'error' => 'Intervention introuvable']);
                exit;
            }

            // Récupérer les observations (commentaires avec is_observation = 1)
            $sql = "SELECT c.*, 
                    CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                    DATE_FORMAT(c.created_at, '%d/%m/%Y %H:%i') as created_at
                    FROM intervention_comments c
                    LEFT JOIN users u ON c.created_by = u.id
                    WHERE c.intervention_id = ? AND c.is_observation = 1
                    ORDER BY c.created_at ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $observations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Récupérer l'email du destinataire (site_email ou contact_client)
            $recipientEmail = !empty($intervention['site_email']) ? $intervention['site_email'] :
                (!empty($intervention['contact_client']) ? $intervention['contact_client'] : '');

            // Récupérer l'email de test si configuré
            $config = Config::getInstance();
            $testEmail = $config->get('test_email', '');

            // URL publique de l'intervention pour le client
            $interventionUrl = BASE_URL . 'interventions_client/view/' . $id;

            // Préparer les données de l'intervention pour l'affichage
            $interventionData = [
                'id' => $intervention['id'],
                'reference' => $intervention['reference'] ?? '',
                'title' => $intervention['title'] ?? '',
                'client_name' => $intervention['client_name'] ?? '',
                'site_name' => $intervention['site_name'] ?? '',
                'status_name' => $intervention['status_name'] ?? ''
            ];

            // Récupérer les templates disponibles (actifs)
            require_once __DIR__ . '/../models/MailTemplateModel.php';
            $mailTemplateModel = new MailTemplateModel($this->db);
            $templates = $mailTemplateModel->getAll();
            $activeTemplates = array_filter($templates, function ($t) {
                return $t['is_active'] == 1;
            });

            // Récupérer les pièces jointes disponibles pour l'intervention
            $attachments = $this->interventionModel->getPiecesJointes($id);

            // Récupérer le dernier bon d'intervention (type_liaison = 'bi', le plus récent)
            $lastBonIntervention = null;
            $sql = "SELECT pj.*, lpj.type_liaison
                    FROM pieces_jointes pj
                    INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                    WHERE lpj.type_liaison = 'bi'
                    AND lpj.entite_id = ?
                    ORDER BY pj.date_creation DESC
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $lastBonIntervention = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'intervention' => $interventionData,
                'observations' => $observations,
                'recipient_email' => $recipientEmail,
                'technician_email' => $intervention['technician_email'] ?? '',
                'technician_name' => $intervention['technician_name'] ?? '',
                'test_email' => $testEmail,
                'intervention_url' => $interventionUrl,
                'templates' => array_values($activeTemplates),
                'attachments' => $attachments,
                'last_bon_intervention' => $lastBonIntervention
            ]);

        } catch (Exception $e) {
            custom_log_mail("Erreur lors de la récupération des données email pour intervention $id : " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'error' => 'Erreur lors de la récupération des données']);
        }
        exit;
    }

    /**
     * Récupère l'historique des emails envoyés pour une intervention
     * (groupé par "envoi" pour afficher une ligne avec plusieurs destinataires)
     * @param int $id ID de l'intervention
     */
    public function getMailHistory($id)
    {
        header('Content-Type: application/json');

        try {
            $this->checkAccess();

            $intervention = $this->interventionModel->getById($id);
            if (!$intervention) {
                echo json_encode(['success' => false, 'error' => 'Intervention introuvable']);
                exit;
            }

            require_once __DIR__ . '/../models/MailHistoryModel.php';
            $mailHistoryModel = new MailHistoryModel($this->db);
            $rows = $mailHistoryModel->getByIntervention($id);

            // mail_history est stocké "1 ligne par destinataire".
            // On regroupe par send_uuid si disponible (sinon fallback ancien: seconde + sujet + template).
            $grouped = [];
            foreach ($rows as $r) {
                $subject = (string) ($r['subject'] ?? '');
                $createdAt = (string) ($r['created_at'] ?? '');
                $sentAt = (string) ($r['sent_at'] ?? '');
                $displayAt = $sentAt !== '' ? $sentAt : $createdAt;

                $sendUuid = isset($r['send_uuid']) ? trim((string) $r['send_uuid']) : '';

                // Clé de regroupement (send_uuid si présent) sinon: seconde + sujet + template
                $ts = $displayAt !== '' ? date('Y-m-d H:i:s', strtotime($displayAt)) : '';
                $templateId = $r['template_id'] ?? null;
                $key = $sendUuid !== '' ? ('uuid|' . $sendUuid) : ($ts . '|' . $subject . '|' . (string) ($templateId ?? ''));

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'title' => $subject,
                        'datetime' => $ts,
                        'recipients' => [],
                        'cc_snapshot' => $r['cc_snapshot'] ?? '',
                        'template_name' => $r['template_name'] ?? null,
                        'template_type' => $r['template_type'] ?? null,
                    ];
                }

                $email = isset($r['recipient_email']) ? trim((string) $r['recipient_email']) : '';
                $name = isset($r['recipient_name']) ? trim((string) $r['recipient_name']) : '';
                if ($email !== '') {
                    $label = $name !== '' ? ($name . ' <' . $email . '>') : $email;
                    $grouped[$key]['recipients'][strtolower($email)] = $label;
                }
            }

            // Remettre en tableau, tri desc par datetime
            $items = array_values(array_map(function ($g) {
                $to = implode(', ', array_values($g['recipients']));
                $cc = is_string($g['cc_snapshot'] ?? null) ? trim($g['cc_snapshot']) : '';
                $dest = $to !== '' ? ("À: " . $to) : "À: (aucun)";
                if ($cc !== '') {
                    $dest .= " | CC: " . $cc;
                }
                $g['recipients'] = $dest;
                return $g;
            }, $grouped));

            usort($items, function ($a, $b) {
                return strcmp($b['datetime'] ?? '', $a['datetime'] ?? '');
            });

            echo json_encode([
                'success' => true,
                'items' => $items,
            ]);
        } catch (Exception $e) {
            custom_log_mail("Erreur getMailHistory intervention $id : " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'error' => 'Erreur lors de la récupération de l\'historique des emails']);
        }
        exit;
    }

    /**
     * Envoie un email au client avec les données de l'intervention et des observations
     * @param int $id ID de l'intervention
     */
    public function sendEmail($id)
    {
        header('Content-Type: application/json');

        try {
            // Vérifier les permissions
            $this->checkAccess();

            // Récupérer l'intervention
            $intervention = $this->interventionModel->getById($id);

            if (!$intervention) {
                echo json_encode(['success' => false, 'error' => 'Intervention introuvable']);
                exit;
            }

            // Récupérer les données du formulaire
            $templateId = $_POST['template_id'] ?? null;
            $customSubject = $_POST['subject'] ?? '';
            $customMessage = $_POST['message'] ?? '';

            // DEBUG: Logger tout le POST pour voir ce qui est reçu
            custom_log_mail("DEBUG sendEmail - POST reçu : " . json_encode($_POST), 'INFO');

            // Récupérer les observations
            $sql = "SELECT c.*, 
                    CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                    DATE_FORMAT(c.created_at, '%d/%m/%Y %H:%i') as created_at
                    FROM intervention_comments c
                    LEFT JOIN users u ON c.created_by = u.id
                    WHERE c.intervention_id = ? AND c.is_observation = 1
                    ORDER BY c.created_at ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $observations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Récupérer les pièces jointes sélectionnées
            $attachmentIds = [];
            if (!empty($_POST['attachments']) && is_array($_POST['attachments'])) {
                $attachmentIds = array_map('intval', $_POST['attachments']);
                custom_log_mail("Pièces jointes sélectionnées reçues : " . json_encode($attachmentIds), 'INFO');
            } else {
                custom_log_mail("Aucune pièce jointe sélectionnée dans le formulaire", 'INFO');
            }

            // Vérifier si un template est sélectionné
            if (!empty($templateId)) {
                // Utiliser le template
                try {
                    $success = $this->mailService->sendCustomEmail($id, $templateId, $observations, $attachmentIds, true, true);

                    if ($success) {
                        custom_log_mail("Email envoyé avec succès pour l'intervention $id via template $templateId", 'INFO');
                        echo json_encode(['success' => true, 'message' => 'Email envoyé avec succès']);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Échec de l\'envoi de l\'email']);
                    }
                } catch (Exception $e) {
                    custom_log_mail("Erreur lors de l'envoi de l'email pour intervention $id : " . $e->getMessage(), 'ERROR');
                    echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'envoi : ' . $e->getMessage()]);
                }
            } else {
                // Utiliser le message personnalisé
                if (empty($customSubject) || empty($customMessage)) {
                    echo json_encode(['success' => false, 'error' => 'Le sujet et le message sont requis']);
                    exit;
                }

                // Préparer le corps de l'email (convertir les retours à la ligne en HTML)
                $body = nl2br(htmlspecialchars($customMessage));

                // Envoyer l'email via MailService avec support des pièces jointes
                try {
                    $success = $this->mailService->sendCustomMessage($id, $customSubject, $body, $attachmentIds, true);

                    if ($success) {
                        custom_log_mail("Email personnalisé envoyé avec succès pour l'intervention $id", 'INFO');
                        echo json_encode(['success' => true, 'message' => 'Email envoyé avec succès']);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Échec de l\'envoi de l\'email']);
                    }
                } catch (Exception $e) {
                    custom_log_mail("Erreur lors de l'envoi de l'email personnalisé pour intervention $id : " . $e->getMessage(), 'ERROR');
                    echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'envoi : ' . $e->getMessage()]);
                    exit;
                }
            }

        } catch (Exception $e) {
            custom_log_mail("Erreur lors de l'envoi de l'email pour intervention $id : " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'error' => 'Erreur : ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Prévise le template avec les variables remplacées
     * @param int $id ID de l'intervention
     */
    public function previewEmailTemplate($id)
    {
        header('Content-Type: application/json');

        try {
            // Vérifier les permissions
            $this->checkAccess();

            // Récupérer l'intervention
            $intervention = $this->interventionModel->getById($id);

            if (!$intervention) {
                echo json_encode(['success' => false, 'error' => 'Intervention introuvable']);
                exit;
            }

            // Récupérer l'ID du template
            $templateId = $_GET['template_id'] ?? null;

            if (empty($templateId)) {
                echo json_encode(['success' => false, 'error' => 'Template ID manquant']);
                exit;
            }

            // Récupérer le template
            require_once __DIR__ . '/../models/MailTemplateModel.php';
            $mailTemplateModel = new MailTemplateModel($this->db);
            $template = $mailTemplateModel->getById($templateId);

            if (!$template) {
                echo json_encode(['success' => false, 'error' => 'Template introuvable']);
                exit;
            }

            // Récupérer les observations
            $sql = "SELECT c.*, 
                    CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                    DATE_FORMAT(c.created_at, '%d/%m/%Y %H:%i') as created_at
                    FROM intervention_comments c
                    LEFT JOIN users u ON c.created_by = u.id
                    WHERE c.intervention_id = ? AND c.is_observation = 1
                    ORDER BY c.created_at ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $observations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Préparer les données pour le remplacement (s'assurer que technician_name est défini)
            if (!isset($intervention['technician_name'])) {
                $intervention['technician_name'] = '';
                if (!empty($intervention['technician_first_name']) && !empty($intervention['technician_last_name'])) {
                    $intervention['technician_name'] = $intervention['technician_first_name'] . ' ' . $intervention['technician_last_name'];
                }
            }

            // Remplacer les variables dans le sujet et le corps via MailService
            $previewSubject = $this->mailService->previewTemplate($template['subject'], $intervention, $observations);
            $previewBody = $this->mailService->previewTemplate($template['body'], $intervention, $observations);

            echo json_encode([
                'success' => true,
                'subject' => $previewSubject,
                'body' => $previewBody
            ]);

        } catch (Exception $e) {
            custom_log_mail("Erreur lors de la prévisualisation du template pour intervention $id : " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'error' => 'Erreur lors de la prévisualisation']);
        }
        exit;
    }
    /**
     * API: Récupérer les techniciens pour une intervention
     * GET /api/interventions/technicians/{id}
     */
    public function apiGetTechnicians($id)
    {
        while (ob_get_level())
            ob_end_clean();
        header('Content-Type: application/json');

        try {
            if (!$id || !is_numeric($id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID intervention invalide']);
                return;
            }

            $stmt = $this->db->prepare("
            SELECT
                u.id             AS technicien_id,
                u.first_name,
                u.last_name,
                u.email,
                it.start_time,
                it.end_time,
                it.deplacement,
                it.temps_passe,
                COALESCE(it.is_qualified, 0) AS is_qualified,
                it.commentaire,
                CASE WHEN it.technicien_id IS NOT NULL THEN 1 ELSE 0 END AS is_assigned
            FROM users u
            LEFT JOIN intervention_techniciens it
                ON u.id = it.technicien_id AND it.intervention_id = ?
            WHERE u.user_type_id = 1
            ORDER BY u.first_name, u.last_name
        ");
            $stmt->execute([$id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $technicians = [];
            $assigned = [];

            foreach ($results as $row) {
                $technicians[] = [
                    'id' => (int) $row['technicien_id'],
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'full_name' => $row['first_name'] . ' ' . $row['last_name'],
                    'email' => $row['email'] ?? '',
                ];

                if ($row['is_assigned']) {
                    $assigned[] = [
                        'technicien_id' => (int) $row['technicien_id'],
                        'start_time' => $row['start_time'],
                        'end_time' => $row['end_time'],
                        'deplacement' => (int) $row['deplacement'],
                        'temps_passe' => $row['temps_passe'] !== null ? (int) $row['temps_passe'] : null,
                        'is_qualified' => (int) ($row['is_qualified'] ?? 0),
                        'commentaire' => $row['commentaire'],
                        'full_name' => $row['first_name'] . ' ' . $row['last_name'],
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'intervention_id' => (int) $id,
                    'assigned' => $assigned,
                    'technicians' => $technicians,
                ],
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur serveur : ' . $e->getMessage()]);
        }
    }

    /**
     * API: Assigner des techniciens à une intervention
     * POST /api/interventions/assignTechnicians
     */
    /**
     * API: Assigner des techniciens à une intervention
     * POST /api/interventions/assignTechnicians
     */
    /**
     * API: Assigner des techniciens à une intervention
     * POST /api/interventions/assignTechnicians
     */
    public function apiAssignTechnicians()
    {
        while (ob_get_level())
            ob_end_clean();
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Données JSON invalides']);
                return;
            }

            $interventionId = $input['intervention_id'] ?? null;
            $technicians = $input['technicians'] ?? [];
            $replace = (bool) ($input['replace'] ?? false); // Si true, on remplace complètement la liste

            if (!$interventionId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID intervention manquant']);
                return;
            }

            $this->db->beginTransaction();

            // 1. Récupérer les IDs actuellement assignés
            $stmt = $this->db->prepare(
                'SELECT technicien_id FROM intervention_techniciens WHERE intervention_id = ?'
            );
            $stmt->execute([$interventionId]);
            $currentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // 2. IDs dans la nouvelle liste
            $newIds = array_column($technicians, 'technicien_id');

            // 3. Si mode replace = true, supprimer ceux qui ne sont plus dans la liste
            if ($replace) {
                $toDelete = array_diff($currentIds, $newIds);
                if (!empty($toDelete)) {
                    $delStmt = $this->db->prepare(
                        'DELETE FROM intervention_techniciens WHERE intervention_id = ? AND technicien_id = ?'
                    );
                    foreach ($toDelete as $tid) {
                        $delStmt->execute([$interventionId, $tid]);
                        custom_log("Technicien $tid supprimé de l'intervention $interventionId", 'INFO');
                    }
                }
            }

            // 4. Insérer ou mettre à jour les techniciens
            $checkStmt = $this->db->prepare(
                'SELECT COUNT(*) FROM intervention_techniciens WHERE intervention_id = ? AND technicien_id = ?'
            );
            $insertStmt = $this->db->prepare("
            INSERT INTO intervention_techniciens
                (intervention_id, technicien_id, start_time, end_time,
                 deplacement, temps_passe, is_qualified, commentaire, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
            $updateStmt = $this->db->prepare("
            UPDATE intervention_techniciens
            SET start_time  = ?,
                end_time    = ?,
                deplacement = ?,
                temps_passe = ?,
                is_qualified = ?,
                commentaire = ?,
                updated_at = NOW()
            WHERE intervention_id = ? AND technicien_id = ?
        ");

            $notify = (int) ($input['notify_technician'] ?? 0);
            $assignedCount = 0;

            foreach ($technicians as $tech) {
                $technicienId = $tech['technicien_id'] ?? null;
                if (!$technicienId) {
                    continue;
                }

                $isQualified = (int) ($tech['is_qualified'] ?? 0);
                $startTime = !empty($tech['start_time']) ? $tech['start_time'] : null;
                $endTime = !empty($tech['end_time']) ? $tech['end_time'] : null;
                $deplacement = (int) ($tech['deplacement'] ?? 0);
                $tempsPasse = !empty($tech['temps_passe']) ? (int) $tech['temps_passe'] : null;
                $commentaire = $tech['commentaire'] ?? null;

                $checkStmt->execute([$interventionId, $technicienId]);
                $exists = (int) $checkStmt->fetchColumn() > 0;

                if ($exists) {
                    // Mise à jour du technicien existant
                    $updateStmt->execute([
                        $startTime,
                        $endTime,
                        $deplacement,
                        $tempsPasse,
                        $isQualified,
                        $commentaire,
                        $interventionId,
                        $technicienId,
                    ]);
                    custom_log("Technicien $technicienId mis à jour pour l'intervention $interventionId", 'INFO');
                } else {
                    // Ajout d'un nouveau technicien
                    $insertStmt->execute([
                        $interventionId,
                        $technicienId,
                        $startTime,
                        $endTime,
                        $deplacement,
                        $tempsPasse,
                        $isQualified,
                        $commentaire,
                    ]);
                    custom_log("Technicien $technicienId ajouté à l'intervention $interventionId", 'INFO');
                }

                $assignedCount++;

                // Email si demandé
                if ($notify === 1 && !empty($technicienId)) {
                    try {
                        $this->mailService->sendTechnicianAssigned($interventionId, $technicienId);
                        custom_log_mail("Email envoyé au technicien $technicienId pour l'intervention $interventionId", 'INFO');
                    } catch (Exception $e) {
                        custom_log_mail('Erreur mail tech ' . $technicienId . ' : ' . $e->getMessage(), 'ERROR');
                    }
                }
            }

            $this->db->commit();

            $message = $assignedCount . ' technicien(s) affecté(s) avec succès';
            if ($replace && !empty($toDelete)) {
                $message .= ' (' . count($toDelete) . ' technicien(s) retiré(s))';
            }

            echo json_encode([
                'success' => true,
                'message' => $message,
                'assigned_count' => $assignedCount,
                'deleted_count' => $replace ? (count($toDelete ?? [])) : 0
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction())
                $this->db->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur serveur : ' . $e->getMessage()]);
        }
    }
    public function interventionsTechnician($id)
    {

        $this->apiGetTechnicians($id);
    }

    public function assignTechnicians()
    {
        $this->apiAssignTechnicians();
    }
    /**
     * Supprime un technicien d'une intervention
     * POST /api/interventions/removeTechnician
     */
    /**
     * Supprime un technicien d'une intervention
     * POST /api/interventions/removeTechnician
     */
    /**
     * Supprime un technicien d'une intervention
     * POST /interventions/removeTechnician
     */
    public function removeTechnician()
    {
        // Nettoyer tous les buffers existants
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Désactiver l'affichage des erreurs pour l'API
        error_reporting(0);
        ini_set('display_errors', 0);

        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-cache, must-revalidate');

        try {
            // Lire les données JSON
            $input = json_decode(file_get_contents('php://input'), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Données JSON invalides: ' . json_last_error_msg());
            }

            if (!$input) {
                throw new Exception('Données JSON invalides ou vides');
            }

            $interventionId = $input['intervention_id'] ?? null;
            $technicianId = $input['technician_id'] ?? null;

            if (!$interventionId) {
                throw new Exception('ID intervention manquant');
            }

            if (!$technicianId) {
                throw new Exception('ID technicien manquant');
            }

            // Vérifier les permissions
            if (!isset($_SESSION['user']) || !canModifyInterventions()) {
                throw new Exception('Permissions insuffisantes');
            }

            // Vérifier si l'intervention existe et n'est pas fermée
            $intervention = $this->interventionModel->getById($interventionId);
            if (!$intervention) {
                throw new Exception('Intervention introuvable');
            }

            if ((int) $intervention['status_id'] === 6) {
                throw new Exception('Impossible de modifier une intervention fermée');
            }

            // Vérifier si le technicien est assigné à cette intervention
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM intervention_techniciens WHERE intervention_id = ? AND technicien_id = ?'
            );
            $stmt->execute([$interventionId, $technicianId]);
            $exists = (int) $stmt->fetchColumn() > 0;

            if (!$exists) {
                throw new Exception('Ce technicien n\'est pas assigné à cette intervention');
            }

            // Récupérer le nom du technicien avant suppression
            $stmt = $this->db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
            $stmt->execute([$technicianId]);
            $technician = $stmt->fetch(PDO::FETCH_ASSOC);
            $technicianName = $technician['name'] ?? '#' . $technicianId;

            // Supprimer le technicien
            $stmt = $this->db->prepare(
                'DELETE FROM intervention_techniciens WHERE intervention_id = ? AND technicien_id = ?'
            );
            $result = $stmt->execute([$interventionId, $technicianId]);

            if (!$result) {
                throw new Exception('Erreur lors de la suppression en base de données');
            }

            // Enregistrer dans l'historique de l'intervention
            $sql = "INSERT INTO intervention_history (
                    intervention_id, field_name, old_value, new_value, changed_by, description
                ) VALUES (
                    :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                )";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':intervention_id' => $interventionId,
                ':field_name' => 'Technicien',
                ':old_value' => $technicianName,
                ':new_value' => '',
                ':changed_by' => $_SESSION['user']['id'],
                ':description' => "Technicien retiré : " . $technicianName
            ]);

            custom_log("Technicien $technicianId supprimé de l'intervention $interventionId", 'INFO');

            // Réponse JSON propre
            echo json_encode([
                'success' => true,
                'message' => 'Technicien retiré avec succès',
                'technician_id' => $technicianId,
                'technician_name' => $technicianName
            ]);
            exit;

        } catch (Exception $e) {
            custom_log("Erreur removeTechnician: " . $e->getMessage(), 'ERROR');

            // S'assurer qu'aucun output n'a été généré avant
            while (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }
    /**
     * Flash Intervention - Création rapide en 1 clic
     */
    /**
     * Flash Intervention - Création rapide en 1 clic
     */
    public function flash()
    {
        // Désactiver l'affichage des erreurs pour l'API
        error_reporting(0);
        ini_set('display_errors', 0);

        // Nettoyer les buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Définir les headers pour l'API
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-cache, must-revalidate');

        // Vérifier l'authentification et les permissions
        if (!isset($_SESSION['user']) || !canModifyInterventions()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Permissions insuffisantes']);
            exit;
        }

        // Récupérer les données
        $clientId = $_POST['client_id'] ?? null;
        $title = trim($_POST['title'] ?? '');

        if (!$clientId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Client requis']);
            exit;
        }

        try {
            // Vérifier que le client existe
            $stmt = $this->db->prepare("SELECT id, name FROM clients WHERE id = ? AND status = 1");
            $stmt->execute([$clientId]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$client) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Client introuvable']);
                exit;
            }

            // Générer une référence unique
            $reference = $this->generateReference();

            // Définir les valeurs par défaut
            $now = date('Y-m-d H:i:s');
            $defaultTitle = $title ?: 'Flash Intervention - ' . $client['name'];

            // Récupérer les IDs par défaut
            $typeId = $this->getDefaultTypeId('Assistance téléphonique');
            $statusId = $this->getDefaultStatusId('En cours'); // Statut "En cours" ou "Nouveau"
            $priorityId = $this->getDefaultPriorityId('Moyenne');

            // Durée par défaut : 30 minutes
            $duration = 30;

            // Insérer l'intervention avec les flags flash
            $sql = "INSERT INTO interventions (
                    reference, title, client_id, type_id, status_id, priority_id,
                    duration, created_at, updated_at, is_flash, needs_completion
                ) VALUES (
                    :reference, :title, :client_id, :type_id, :status_id, :priority_id,
                    :duration, :created_at, :updated_at, 1, 1
                )";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':reference' => $reference,
                ':title' => $defaultTitle,
                ':client_id' => $clientId,
                ':type_id' => $typeId,
                ':status_id' => $statusId,
                ':priority_id' => $priorityId,
                ':duration' => $duration,
                ':created_at' => $now,
                ':updated_at' => $now
            ]);

            if (!$result) {
                throw new Exception('Erreur lors de l\'insertion en base de données');
            }

            $interventionId = $this->db->lastInsertId();

            // Enregistrer l'action dans l'historique
            $sql = "INSERT INTO intervention_history (
                    intervention_id, field_name, old_value, new_value, changed_by, description
                ) VALUES (
                    :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':intervention_id' => $interventionId,
                ':field_name' => 'Création flash',
                ':old_value' => '',
                ':new_value' => '',
                ':changed_by' => $_SESSION['user']['id'],
                ':description' => "Intervention flash créée rapidement - À compléter"
            ]);

            echo json_encode([
                'success' => true,
                'intervention_id' => $interventionId,
                'reference' => $reference,
                'message' => 'Intervention flash créée avec succès'
            ]);

        } catch (Exception $e) {
            error_log('Flash Intervention Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur lors de la création: ' . $e->getMessage()]);
        }
        exit;
    }
    /* @return array Liste des interventions flash incomplètes
     */
    public function getFlashInterventions()
    {
        // Vérifier l'authentification
        if (!isset($_SESSION['user'])) {
            return [];
        }

        $user = $_SESSION['user'];
        $userId = $user['id'];
        $userType = $user['user_type'] ?? null;

        // Récupérer les interventions flash incomplètes
        $flashInterventions = [];

        try {
            if ($userType === 'technicien') {
                // Pour un technicien, récupérer ses interventions assignées
                $sql = "SELECT i.*, 
                           c.name as client_name,
                           s.name as site_name,
                           r.name as room_name,
                           st.name as status_name,
                           st.color as status_color,
                           p.name as priority_name,
                           p.color as priority_color,
                           t.name as type_name
                    FROM interventions i
                    LEFT JOIN clients c ON i.client_id = c.id
                    LEFT JOIN sites s ON i.site_id = s.id
                    LEFT JOIN rooms r ON i.room_id = r.id
                    LEFT JOIN intervention_statuses st ON i.status_id = st.id
                    LEFT JOIN intervention_priorities p ON i.priority_id = p.id
                    LEFT JOIN intervention_types t ON i.type_id = t.id
                    WHERE i.is_flash = 1 
                      AND i.needs_completion = 1
                      AND i.status_id != 6
                      AND EXISTS (
                          SELECT 1 FROM intervention_techniciens it 
                          WHERE it.intervention_id = i.id AND it.technicien_id = :technicien_id
                      )
                    ORDER BY i.created_at DESC";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([':technicien_id' => $userId]);
                $flashInterventions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } elseif ($userType === 'admin') {
                // Pour l'admin, récupérer toutes les interventions flash incomplètes
                $sql = "SELECT i.*, 
                           c.name as client_name,
                           s.name as site_name,
                           r.name as room_name,
                           st.name as status_name,
                           st.color as status_color,
                           p.name as priority_name,
                           p.color as priority_color,
                           t.name as type_name
                    FROM interventions i
                    LEFT JOIN clients c ON i.client_id = c.id
                    LEFT JOIN sites s ON i.site_id = s.id
                    LEFT JOIN rooms r ON i.room_id = r.id
                    LEFT JOIN intervention_statuses st ON i.status_id = st.id
                    LEFT JOIN intervention_priorities p ON i.priority_id = p.id
                    LEFT JOIN intervention_types t ON i.type_id = t.id
                    WHERE i.is_flash = 1 
                      AND i.needs_completion = 1
                      AND i.status_id != 6
                    ORDER BY i.created_at DESC";

                $stmt = $this->db->prepare($sql);
                $stmt->execute();
                $flashInterventions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

        } catch (Exception $e) {
            error_log('Erreur getFlashInterventions: ' . $e->getMessage());
            return [];
        }

        return $flashInterventions;
    }
    /**
     * Génère une référence unique pour l'intervention
     */
    private function generateReference()
    {
        $year = date('Y');
        $month = date('m');
        $prefix = "INT-{$year}{$month}-";

        $sql = "SELECT COUNT(*) as count FROM interventions WHERE reference LIKE :prefix";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':prefix' => $prefix . '%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $number = str_pad(($result['count'] ?? 0) + 1, 4, '0', STR_PAD_LEFT);
        return $prefix . $number;
    }

    /**
     * Récupère l'ID d'un type par son nom
     */
    private function getDefaultTypeId($typeName)
    {
        $sql = "SELECT id FROM intervention_types WHERE name = :name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':name' => $typeName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return $result['id'];
        }

        // Créer le type s'il n'existe pas
        $sql = "INSERT INTO intervention_types (name, created_at) VALUES (:name, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':name' => $typeName]);
        return $this->db->lastInsertId();
    }

    /**
     * Récupère l'ID d'un statut par son nom
     */
    private function getDefaultStatusId($statusName)
    {
        $sql = "SELECT id FROM intervention_statuses WHERE name = :name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':name' => $statusName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['id'] : 1;
    }

    /**
     * Récupère l'ID d'une priorité par son nom
     */
    private function getDefaultPriorityId($priorityName)
    {
        $sql = "SELECT id FROM intervention_priorities WHERE name = :name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':name' => $priorityName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['id'] : 2;
    }
    /**
     * Envoie un email au technicien assigné
     */
    /**
     * Envoie un email à un technicien spécifique
     */
    public function sendTechnicianEmail()
    {
        // Désactiver l'affichage des erreurs pour l'API
        error_reporting(0);
        ini_set('display_errors', 0);

        // Nettoyer les buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Définir les headers pour l'API
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-cache, must-revalidate');

        // Vérifier l'authentification et les permissions
        if (!isset($_SESSION['user']) || !canModifyInterventions()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Permissions insuffisantes']);
            exit;
        }

        // Récupérer les données
        $interventionId = $_POST['intervention_id'] ?? null;
        $technicianId = $_POST['technicien_id'] ?? null;

        if (!$interventionId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID intervention manquant']);
            exit;
        }

        if (!$technicianId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID technicien manquant']);
            exit;
        }

        try {
            // Récupérer l'intervention
            $intervention = $this->interventionModel->getById($interventionId);

            if (!$intervention) {
                throw new Exception('Intervention non trouvée');
            }

            // Envoyer l'email
            $emailSent = $this->mailService->sendTechnicianAssigned($interventionId, $technicianId);

            if ($emailSent) {
                custom_log_mail("Email de notification renvoyé au technicien $technicianId pour l'intervention $interventionId", 'INFO');
                echo json_encode([
                    'success' => true,
                    'message' => 'Email envoyé avec succès au technicien'
                ]);
            } else {
                custom_log_mail("Échec de l'envoi de l'email de notification au technicien $technicianId pour l'intervention $interventionId", 'WARNING');
                echo json_encode([
                    'success' => false,
                    'error' => 'Échec de l\'envoi de l\'email. Vérifiez la configuration du serveur mail.'
                ]);
            }

        } catch (Exception $e) {
            custom_log_mail("Erreur lors de l'envoi de l'email de notification au technicien : " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Erreur lors de l\'envoi: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    public function getFileData($attachmentId)
    {
        // Vérifier les permissions
        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            exit;
        }

        try {
            $attachment = $this->interventionModel->getPieceJointeById($attachmentId);

            if (!$attachment) {
                http_response_code(404);
                exit;
            }

            $filePath = __DIR__ . '/../../' . $attachment['chemin_fichier'];

            if (!file_exists($filePath)) {
                http_response_code(404);
                exit;
            }

            $extension = strtolower(pathinfo($attachment['nom_fichier'], PATHINFO_EXTENSION));
            $mimeTypes = [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml'
            ];

            $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

            header('Content-Type: ' . $mimeType);
            header('Cache-Control: public, max-age=3600');
            readfile($filePath);

        } catch (Exception $e) {
            http_response_code(500);
            exit;
        }
    }
    /**
     * Calcule les tickets pour chaque technicien d'une intervention
     * 
     * @param int $interventionId ID de l'intervention
     * @return array Liste des tickets par technicien
     */
    public function calculateTicketsPerTechnician($interventionId)
    {
        $intervention = $this->interventionModel->getById($interventionId);
        if (!$intervention) {
            return [];
        }

        $sql = "SELECT it.technicien_id, it.temps_passe,
                   CONCAT(u.first_name, ' ', u.last_name) as technician_name,
                   u.coef_utilisateur
            FROM intervention_techniciens it
            JOIN users u ON it.technicien_id = u.id
            WHERE it.intervention_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$interventionId]);
        $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $type = $this->interventionModel->getTypeInfo($intervention['type_id']);
        $requiresTravel = $type['requires_travel'] ?? false;

        $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'coef_intervention'");
        $stmt->execute();
        $coefIntervention = floatval($stmt->fetchColumn()) ?? 0;

        $results = [];

        foreach ($technicians as $tech) {
            $tempsPasse = $tech['temps_passe'] ?? $intervention['duration'];
            $durationHours = $tempsPasse / 60;
            $coefUtilisateur = $tech['coef_utilisateur'] ?? 0;

            if ($requiresTravel) {
                $tickets = $durationHours + $coefUtilisateur + 1 + $coefIntervention;
            } else {
                $tickets = $durationHours + $coefUtilisateur + $coefIntervention;
            }

            $results[] = [
                'technicien_id' => $tech['technicien_id'],
                'technician_name' => $tech['technician_name'],
                'duration_minutes' => $tempsPasse,
                'coef_utilisateur' => $coefUtilisateur,
                'tickets' => ceil($tickets)
            ];
        }

        return $results;
    }

    /**
     * Retourne true si un champ qui impacte le calcul des tickets a changé.
     * Champs ticketables : duration, type_id (déplacement), technicians (via table séparée).
     */
    private function ticketableFieldChanged(array $oldData, array $newData): bool
    {
        foreach (['duration', 'type_id'] as $field) {
            if (array_key_exists($field, $newData) && (string) ($oldData[$field] ?? '') !== (string) ($newData[$field] ?? '')) {
                return true;
            }
        }
        return false;
    }
    /**
     * Sur une intervention déjà fermée, recalcule le nouveau total de tickets,
     * calcule la différence avec l'ancien total et ajuste le contrat en conséquence.
     * Ne fait rien si le contrat n'est pas de type ticket.
     */
    private function adjustTicketsOnClosedIntervention(int $interventionId, array $oldIntervention, array $newData): void
    {
        $contractId = $newData['contract_id'] ?? $oldIntervention['contract_id'] ?? null;
        if (!$contractId || !isContractTicketById($contractId)) {
            return;
        }

        $oldTickets = (int) ($oldIntervention['tickets_used'] ?? 0);

        // Recalcule avec les nouvelles données (techniciens déjà enregistrés en base à ce stade)
        $newTickets = $this->calculateTotalTicketsUsed(
            $interventionId,
            $newData['duration'] ?? $oldIntervention['duration'],
            $newData['type_id'] ?? $oldIntervention['type_id']
        );

        if ($newTickets === $oldTickets) {
            return; // Rien à faire
        }

        $delta = $newTickets - $oldTickets;

        // Met à jour tickets_used sur l'intervention
        $stmt = $this->db->prepare("UPDATE interventions SET tickets_used = :t WHERE id = :id");
        $stmt->execute([':t' => $newTickets, ':id' => $interventionId]);

        // Ajuste le contrat (delta peut être positif ou négatif)
        $stmt = $this->db->prepare(
            "UPDATE contracts SET tickets_remaining = tickets_remaining - :delta WHERE id = :cid"
        );
        $stmt->execute([':delta' => $delta, ':cid' => $contractId]);

        // Historique contrat
        $contractModel = new ContractModel($this->db);
        $ref = $this->getInterventionReference($interventionId);
        $contractModel->recordTicketModification(
            $contractId,
            $delta,
            $ref . ' - Ajustement automatique (modification intervention fermée)'
        );

        // Historique intervention
        $stmt = $this->db->prepare(
            "INSERT INTO intervention_history
            (intervention_id, field_name, old_value, new_value, changed_by, description)
         VALUES (:iid, 'tickets_used', :old, :new, :by, :desc)"
        );
        $stmt->execute([
            ':iid' => $interventionId,
            ':old' => $oldTickets,
            ':new' => $newTickets,
            ':by' => $_SESSION['user']['id'],
            ':desc' => "Tickets recalculés suite à modification : $oldTickets → $newTickets (delta $delta)"
        ]);
    }

    /**
     * Récupère les bâtiments d'un site (API)
     */
    public function getBuildings($siteId)
    {
        header('Content-Type: application/json');

        try {
            $query = "SELECT id, name FROM buildings WHERE site_id = :site_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':site_id' => $siteId]);
            $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($buildings);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Récupère les salles d'un bâtiment (API)
     */
    public function getRoomsByBuilding($buildingId)
    {
        header('Content-Type: application/json');

        try {
            $query = "SELECT id, name FROM rooms WHERE building_id = :building_id  ORDER BY name";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':building_id' => $buildingId]);
            $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($rooms);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    /**
     * Réouvre une intervention fermée.
     *
     * Si l'intervention avait consommé des tickets, ils sont re-crédités
     * AVANT que l'inter repasse en statut ouvert, afin qu'une éventuelle
     * re-fermeture ultérieure puisse les déduire à nouveau sans double-déduction.
     */
    public function reopen($id)
    {
        checkInterventionManagementAccess();

        $intervention = $this->interventionModel->getById($id);

        if (!$intervention) {
            $_SESSION['error'] = 'Intervention introuvable.';
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }

        if ((int) $intervention['status_id'] !== 6) {
            $_SESSION['error'] = 'Seules les interventions fermées peuvent être réouvertes.';
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        try {
            $this->db->beginTransaction();

            $ticketsToRecredit = (float) ($intervention['tickets_used'] ?? 0);
            $hadTickets = $ticketsToRecredit > 0
                && !empty($intervention['contract_id'])
                && isContractTicketById($intervention['contract_id']);

            custom_log("=== RÉOUVERTURE INTERVENTION $id ===", 'INFO');
            custom_log("Tickets à recréditer: $ticketsToRecredit", 'INFO');
            custom_log("Contrat ID: " . ($intervention['contract_id'] ?? 'aucun'), 'INFO');
            custom_log("Had tickets: " . ($hadTickets ? 'oui' : 'non'), 'INFO');

            // ── Re-créditer les tickets AVANT de changer le statut ──────────────
            if ($hadTickets) {
                $this->recreditTicketsForIntervention($id, $intervention);
            }

            // Repasser en statut "Nouveau" et RAZ des tickets_used
            $newStatusId = 1;
            $sql = "UPDATE interventions
            SET status_id       = :s,
                tickets_used    = 0,
                closed_at       = NULL,
                needs_completion= 1,
                updated_at      = NOW()
            WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':s' => $newStatusId, ':id' => $id]);

            // Historique
            $oldName = $this->getStatusName($intervention['status_id']);
            $newName = $this->getStatusName($newStatusId);
            $desc = "Intervention réouverte";
            if ($hadTickets) {
                $desc .= " — {$ticketsToRecredit} ticket(s) re-crédité(s) au contrat";
            }
            $this->insertHistory($id, 'Statut', $oldName, $newName, $desc);

            $this->db->commit();

            $msg = "Intervention réouverte avec succès.";
            if ($hadTickets) {
                $msg .= " {$ticketsToRecredit} ticket(s) re-crédité(s) au contrat.";
            }
            $_SESSION['success'] = $msg;

            custom_log("RÉOUVERTURE RÉUSSIE - Intervention $id", 'INFO');

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            custom_log("Erreur reopen() intervention $id : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de la réouverture de l'intervention.";
        }

        header('Location: ' . BASE_URL . 'interventions/view/' . $id);
        exit;
    }

    /**
     * Retourne le nom d'un statut par son id.
     */
    private function getStatusName($statusId): string
    {
        $stmt = $this->db->prepare(
            "SELECT name FROM intervention_statuses WHERE id = ?"
        );
        $stmt->execute([$statusId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['name'] : 'Inconnu';
    }

    /**
     * Retourne le détail du calcul de tickets pour la modale de réouverture.
     * Appelé en GET par la modale via fetch().
     */
    public function getReopenDetails($id)
    {
        checkInterventionManagementAccess();
        header('Content-Type: application/json');

        $intervention = $this->interventionModel->getById($id);

        if (!$intervention) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Intervention introuvable.']);
            exit;
        }

        // Vérifier que l'intervention est fermée
        if ((int) $intervention['status_id'] !== 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Seules les interventions fermées peuvent être réouvertes.']);
            exit;
        }

        // Vérifier qu'au moins un technicien est assigné
        $sql = "SELECT it.technicien_id,
               it.temps_passe,
               it.is_qualified,
               it.deplacement,
               CONCAT(u.first_name,' ',u.last_name) AS technician_name
        FROM intervention_techniciens it
        JOIN users u ON it.technicien_id = u.id
        WHERE it.intervention_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($technicians)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => "Impossible de réouvrir l'intervention sans technicien assigné.",
            ]);
            exit;
        }

        // Infos type d'intervention
        $type = $this->interventionModel->getTypeInfo($intervention['type_id'] ?? null);
        $isRemote = empty($type['requires_travel']) || (int) ($type['requires_travel'] ?? 0) === 0;

        // Calcul détaillé par technicien
        $ticketsPerTech = [];
        $totalTickets = 0.0;

        foreach ($technicians as $tech) {
            $minutes = (float) ($tech['temps_passe'] ?? 0);
            $isQualified = (int) ($tech['is_qualified'] ?? 0) === 1;
            $hasTravel = (int) ($tech['deplacement'] ?? 0) === 1;
            $hours = $minutes / 60.0;

            $raw = 0.0;
            $parts = [];

            if ($hasTravel) {
                $raw += 1.0;
                $parts[] = '+1 déplacement';
            }
            $raw += $hours;

            if ($hours == floor($hours)) {
                $parts[] = number_format($hours, 0, '.', '') . 'h de main d\'œuvre';
            } else {
                $parts[] = number_format($hours, 2, '.', '') . 'h de main d\'œuvre';
            }

            if ($isQualified && $hours >= 1.0) {
                $raw += 1.0;
                $parts[] = '(+1 prime qualifié)';
            }

            if ($isRemote) {
                $rounded = round($raw * 2) / 2;
            } else {
                $rounded = (float) ceil($raw);
            }

            $totalTickets += $rounded;

            $ticketsPerTech[] = [
                'technicien_id' => (int) $tech['technicien_id'],
                'name' => $tech['technician_name'],
                'duration_minutes' => $minutes,
                'duration_hours' => round($hours, 2),
                'is_qualified' => $isQualified,
                'has_travel' => $hasTravel,
                'tickets_raw' => round($raw, 2),
                'tickets_rounded' => $rounded,
                'formula' => implode(' + ', $parts) . ' = ' . round($raw, 2) . ' → ' . $rounded,
            ];
        }

        // Infos contrat
        $contractInfo = null;
        $ticketsToRecredit = (float) ($intervention['tickets_used'] ?? 0);
        $hasTicketsToRecredit = $ticketsToRecredit > 0 && !empty($intervention['contract_id']) && isContractTicketById($intervention['contract_id']);

        if (!empty($intervention['contract_id']) && isContractTicketById($intervention['contract_id'])) {
            $contract = $this->contractModel->getContractById($intervention['contract_id']);
            if ($contract) {
                $contractInfo = [
                    'id' => $contract['id'],
                    'name' => $contract['name'],
                    'tickets_remaining' => (float) ($contract['tickets_remaining'] ?? 0),
                    'tickets_number' => (float) ($contract['tickets_number'] ?? 0),
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'intervention' => [
                'id' => $intervention['id'],
                'reference' => $intervention['reference'],
                'title' => $intervention['title'],
                'type_name' => $type['name'] ?? '',
                'is_remote' => $isRemote,
                'technician_count' => count($technicians),
            ],
            'technicians' => $ticketsPerTech,
            'total_tickets' => $totalTickets,
            'is_remote' => $isRemote,
            'contract' => $contractInfo,
            'has_tickets' => $hasTicketsToRecredit,
            'tickets_to_recredit' => $ticketsToRecredit,
        ]);
        exit;
    }

}