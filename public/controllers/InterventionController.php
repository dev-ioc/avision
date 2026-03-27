<?php
/**
 * Contrôleur pour la gestion des interventions
 */
require_once __DIR__ . '/../classes/Services/AttachmentService.php';

require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';

class InterventionController {
    use AccessControlTrait;
    
    private $db;
    private $interventionModel;
    private $clientModel;
    private $siteModel;
    private $roomModel;
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
    const K_SMALL_RATIO = 2/3;

    public function __construct($db) {
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
        
        $this->interventionModel = new InterventionModel($db);
        $this->clientModel = new ClientModel($db);
        $this->siteModel = new SiteModel($db);
        $this->roomModel = new RoomModel($db);
        $this->userModel = new UserModel($db);
        $this->contractModel = new ContractModel($db);
        $this->contactModel = new ContactModel($db);
        $this->durationModel = new DurationModel($db);
        $this->mailService = new MailService($db);

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
    private function getInterventionsListUrl($priorityId = null) {
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
    public function index() {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer les filtres
        $filters = [
            'client_id' => $_GET['client_id'] ?? null,
            'site_id' => $_GET['site_id'] ?? null,
            'room_id' => $_GET['room_id'] ?? null,
            'status_id' => $_GET['status_id'] ?? null,
            'priority_id' => $_GET['priority_id'] ?? null,
            'technician_id' => $_GET['technician_id'] ?? null,
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
        $rooms = !empty($filters['site_id']) ? $this->roomModel->getRoomsBySiteId($filters['site_id']) : [];
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
    public function curatives() {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer les filtres
        $filters = [
            'client_id' => $_GET['client_id'] ?? null,
            'site_id' => $_GET['site_id'] ?? null,
            'room_id' => $_GET['room_id'] ?? null,
            'status_id' => $_GET['status_id'] ?? null,
            'priority_id' => $_GET['priority_id'] ?? null,
            'technician_id' => $_GET['technician_id'] ?? null,
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
        $rooms = !empty($filters['site_id']) ? $this->roomModel->getRoomsBySiteId($filters['site_id']) : [];
        $technicians = $this->userModel->getTechnicians();
        
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
        $canManageInterventions = $this->checkPermission('technicien', 'manage_interventions');
        
        // Charger la vue
        require_once __DIR__ . '/../views/interventions/index.php';
    }

    /**
     * Affiche la liste des interventions préventives
     */
    public function preventives() {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer les filtres
        $filters = [
            'client_id' => $_GET['client_id'] ?? null,
            'site_id' => $_GET['site_id'] ?? null,
            'room_id' => $_GET['room_id'] ?? null,
            'status_id' => $_GET['status_id'] ?? null,
            'priority_id' => $_GET['priority_id'] ?? null,
            'technician_id' => $_GET['technician_id'] ?? null,
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
        $rooms = !empty($filters['site_id']) ? $this->roomModel->getRoomsBySiteId($filters['site_id']) : [];
        $technicians = $this->userModel->getTechnicians();
        
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
        $canManageInterventions = $this->checkPermission('technicien', 'manage_interventions');
        
        // Charger la vue
        require_once __DIR__ . '/../views/interventions/index.php';
    }

    /**
     * Affiche les détails d'une intervention
     */
    public function view($id) {
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
            'technician_id' => null,
            'status_id' => null,
            'priority_id' => null,
            'type_id' => null,
            'duration' => null,
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
    public function edit($id) {
        // Vérifier les permissions
        checkInterventionManagementAccess();

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
            'technician_id' => null,
            'status_id' => null,
            'priority_id' => null,
            'type_id' => null,
            'duration' => null,
            'description' => null,
            'title' => null
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
        $room_id = isset($intervention['room_id']) ? $intervention['room_id'] : null;

        // Récupérer les données pour les formulaires
        $clients = $this->clientModel->getAllClientsWithStats();
        $sites = $this->siteModel->getSitesByClientId($client_id);
        $rooms = $this->roomModel->getRoomsBySiteId($site_id);
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

        // Charger la vue
        require_once __DIR__ . '/../views/interventions/edit.php';
    }

    /**
     * Génère un bon d'intervention au format PDF
     * @param array $intervention Les données de l'intervention
     * @return string Le chemin du fichier PDF généré
     */
    private function generateInterventionReport($intervention) {
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
    public function update($id) {
        // Code de débogage temporaire
        error_log("DEBUG - Début de update() pour l'intervention $id");
        error_log("DEBUG - POST data: " . print_r($_POST, true));
        
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
            'room_id' => null,
            'client_id' => null,
            'technician_id' => null,
            'status_id' => null,
            'priority_id' => null,
            'type_id' => null,
            'duration' => null,
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
            'room_id' => $_POST['room_id'] ?? $intervention['room_id'],
            'status_id' => $_POST['status_id'] ?? $intervention['status_id'],
            'priority_id' => $_POST['priority_id'] ?? $intervention['priority_id'],
            'type_id' => $_POST['type_id'] ?? $intervention['type_id'],
            'duration' => !empty($_POST['duration']) ? $_POST['duration'] : ($intervention['duration'] ?? null),
            'description' => $_POST['description'] ?? $intervention['description'],
            'demande_par' => $_POST['demande_par'] ?? $intervention['demande_par'],
            'ref_client' => $_POST['ref_client'] ?? $intervention['ref_client'],
            'contact_client' => $_POST['contact_client'] ?? $intervention['contact_client'],
            'date_planif' => !empty($_POST['date_planif']) ? $_POST['date_planif'] : $intervention['date_planif'] ?? null,
            'heure_planif' => !empty($_POST['heure_planif']) ? $_POST['heure_planif'] : $intervention['heure_planif'] ?? null,
            'type_requires_travel' => isset($_POST['type_requires_travel']) ? (int)$_POST['type_requires_travel'] : ($intervention['type_requires_travel'] ?? 0)
        ];

        // Traiter la date et l'heure de création
        $createdDate = $_POST['created_date'] ?? date('Y-m-d', strtotime($intervention['created_at']));
        $createdTime = $_POST['created_time'] ?? date('H:i', strtotime($intervention['created_at']));
        $data['created_at'] = $createdDate . ' ' . $createdTime . ':00';
        
        // Gérer le technician_id séparément pour s'assurer qu'il est correctement traité
        if (isset($_POST['technician_id']) && $_POST['technician_id'] !== '') {
            $data['technician_id'] = $_POST['technician_id'];
        } else {
            $data['technician_id'] = $intervention['technician_id'];
        }
        
        // Débogage pour les champs date_planif et heure_planif
        error_log("DEBUG - InterventionController::update - POST date_planif: " . ($_POST['date_planif'] ?? 'NON DÉFINI'));
        error_log("DEBUG - InterventionController::update - POST heure_planif: " . ($_POST['heure_planif'] ?? 'NON DÉFINI'));
        error_log("DEBUG - InterventionController::update - data date_planif: " . ($data['date_planif'] ?? 'NULL'));
        error_log("DEBUG - InterventionController::update - data heure_planif: " . ($data['heure_planif'] ?? 'NULL'));
        
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
        
        $isBeingClosed = isset($data['status_id']) && $data['status_id'] == 6 && $intervention['status_id'] != 6;
        custom_log("DEBUG - update() - isBeingClosed: " . ($isBeingClosed ? 'VRAI' : 'FAUX'), "DEBUG");
        
        // Si l'intervention est en train d'être fermée (et ce n'est pas une sauvegarde avant fermeture), vérifier que la durée est définie
        if ($isBeingClosed && !$isSaveBeforeClose) {
            if (empty($data['duration'])) {
                $_SESSION['error'] = "Impossible de fermer l'intervention sans avoir défini une durée.";
                header('Location: ' . BASE_URL . 'interventions/edit/' . $id);
                exit;
            }
            
            // Vérifier qu'un technicien est assigné
            if (empty($data['technician_id'])) {
                $_SESSION['error'] = "Impossible de fermer l'intervention sans avoir assigné un technicien.";
                header('Location: ' . BASE_URL . 'interventions/edit/' . $id);
                exit;
            }
            
            // Calculer le nombre de tickets utilisés seulement si c'est un contrat à tickets
            $ticketsUsed = 0;
            if (!empty($data['contract_id']) && isContractTicketById($data['contract_id'])) {
                custom_log("DEBUG - update() - Calcul des tickets pour l'intervention $id (contrat à tickets)", "DEBUG");
                custom_log("DEBUG - update() - Durée: " . $data['duration'], "DEBUG");
                custom_log("DEBUG - update() - Technicien ID: " . $data['technician_id'], "DEBUG");
                custom_log("DEBUG - update() - Type ID: " . $data['type_id'], "DEBUG");
                
                $ticketsUsed = $this->calculateTicketsUsed($data['duration'], $data['technician_id'], $data['type_id'], $data['type_requires_travel'] ?? null);
                custom_log("DEBUG - update() - Tickets calculés: " . $ticketsUsed, "DEBUG");
            } else {
                custom_log("DEBUG - update() - Pas de calcul de tickets (contrat sans tickets ou pas de contrat)", "DEBUG");
            }
            $data['tickets_used'] = $ticketsUsed;
            
            custom_log("DEBUG - update() - Data après calcul: " . print_r($data, true), "DEBUG");
            
            // Ajouter la date de fermeture seulement si ce n'est pas une sauvegarde avant fermeture
            if (!$isSaveBeforeClose) {
                $data['closed_at'] = date('Y-m-d H:i:s');

                // Déduire les tickets du contrat si un contrat est associé
                if (!empty($data['contract_id'])) {
                    $this->deductTicketsFromContract($data['contract_id'], $ticketsUsed, $id);
                }
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

        if ($result) {
            // Vérifier si le technicien a changé et si on doit envoyer un email
            $technicianChanged = false;
            $oldTechnicianId = $intervention['technician_id'] ?? null;
            $newTechnicianId = $data['technician_id'] ?? null;
            
            if ($oldTechnicianId != $newTechnicianId && !empty($newTechnicianId)) {
                $technicianChanged = true;
                
                // Vérifier si l'utilisateur a demandé l'envoi d'un email
                if (isset($_POST['notify_technician']) && $_POST['notify_technician'] == '1') {
                    try {
                        // Récupérer l'intervention mise à jour pour avoir toutes les données
                        $updatedIntervention = $this->interventionModel->getById($id);
                        if ($updatedIntervention) {
                            $emailSent = $this->mailService->sendTechnicianAssigned($id, $newTechnicianId);
                            if ($emailSent) {
                                custom_log_mail("Email de notification envoyé au technicien $newTechnicianId pour l'intervention $id", 'INFO');
                            } else {
                                custom_log_mail("Échec de l'envoi de l'email de notification au technicien $newTechnicianId pour l'intervention $id", 'WARNING');
                            }
                        }
                    } catch (Exception $e) {
                        custom_log_mail("Erreur lors de l'envoi de l'email de notification au technicien : " . $e->getMessage(), 'ERROR');
                    }
                }
            }
            
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
    private function calculateTicketsUsed($duration, $technicianId, $typeId, $typeRequiresTravel = null) {
        custom_log("DEBUG - calculateTicketsUsed() - Paramètres: durée=$duration, technicien=$technicianId, type=$typeId, type_requires_travel=" . ($typeRequiresTravel ?? 'null'), "DEBUG");
        
        // Récupérer le coefficient utilisateur
        $technician = $this->userModel->getUserById($technicianId);
        $coefUtilisateur = $technician['coef_utilisateur'] ?? 0;
        custom_log("DEBUG - calculateTicketsUsed() - Technicien: " . print_r($technician, true), "DEBUG");
        custom_log("DEBUG - calculateTicketsUsed() - Coef utilisateur: $coefUtilisateur", "DEBUG");

        // Utiliser la valeur stockée dans l'intervention si disponible, sinon celle du type
        if ($typeRequiresTravel !== null) {
            $requiresTravel = (bool)$typeRequiresTravel;
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
    private function recordChanges($interventionId, $oldData, $newData) {
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
            'technician_id' => 'Technicien',
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
    private function preloadDisplayValues($oldData, $newData) {
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
            if (isset($data['client_id']) && $data['client_id']) $clientIds[] = $data['client_id'];
            if (isset($data['site_id']) && $data['site_id']) $siteIds[] = $data['site_id'];
            if (isset($data['room_id']) && $data['room_id']) $roomIds[] = $data['room_id'];
            if (isset($data['technician_id']) && $data['technician_id']) $technicianIds[] = $data['technician_id'];
            if (isset($data['status_id']) && $data['status_id']) $statusIds[] = $data['status_id'];
            if (isset($data['priority_id']) && $data['priority_id']) $priorityIds[] = $data['priority_id'];
            if (isset($data['type_id']) && $data['type_id']) $typeIds[] = $data['type_id'];
            if (isset($data['contract_id']) && $data['contract_id']) $contractIds[] = $data['contract_id'];
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
    private function getDisplayValue($field, $value, $lookupData = []) {
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
                if (empty($value)) return 'Non spécifié';
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
                if (empty($value)) return 'Non spécifié';
                if (!empty($lookupData) && isset($lookupData['rooms'][$value])) {
                    return $lookupData['rooms'][$value];
                }
                // Fallback si lookupData n'est pas fourni (compatibilité)
                $sql = "SELECT name FROM rooms WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$value]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ? $result['name'] : 'Salle inconnue';
                
            case 'technician_id':
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
                if (!$value) return 'Hors contrat';
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
    private function getComments($interventionId) {
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
    private function getAttachments($interventionId) {
        return $this->interventionModel->getPiecesJointes($interventionId);
    }

    /**
     * Récupère l'historique d'une intervention
     */
    private function getHistory($interventionId) {
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
    public function addComment($interventionId) {
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
    public function addAttachment($interventionId) {
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
    public function addMultipleAttachments($interventionId) {
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
    public function download($attachmentId) {
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

            // Vérifier les permissions
            if (!$this->checkPermission('technicien', 'view_interventions') && 
                $_SESSION['user']['id'] !== $intervention['technician_id']) {
                $_SESSION['error'] = "Vous n'avez pas la permission de télécharger cette pièce jointe.";
                header('Location: ' . $this->getInterventionsListUrl());
                exit;
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
    public function preview($attachmentId) {
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
    public function deleteComment($commentId) {
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
    public function deleteAttachment($attachmentId) {
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
    public function getTypeInfo($typeId) {
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
    public function getSites($clientId) {
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
    public function getRooms($siteId) {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer les salles
        $rooms = $this->roomModel->getRoomsBySiteId($siteId);
        
        // Retourner les salles au format JSON
        header('Content-Type: application/json');
        echo json_encode(['rooms' => $rooms]);
    }

    /**
     * Vérifie les permissions d'un utilisateur
     */
    private function checkPermission($module, $action) {
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
    public function getContracts($clientId, $siteId = null, $roomId = null) {
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
    public function getContractByRoom($roomId) {
        // Vérifier les permissions
        $this->checkAccess();

        // Récupérer le contrat
        $contract = $this->contractModel->getContractByRoomId($roomId);
        
        // Retourner le contrat au format JSON
        header('Content-Type: application/json');
        echo json_encode($contract);
    }

    /**
     * Récupère les informations détaillées d'un contrat via AJAX
     */
    public function getContractInfo($contractId) {
        // Vérifier les permissions
        $this->checkAccess();

        header('Content-Type: application/json');
        
        try {
            // Récupérer les infos détaillées du contrat
            $contract = $this->contractModel->getContractById($contractId);
            
            if (!$contract) {
                http_response_code(404);
                echo json_encode(['error' => 'Contrat non trouvé']);
                return;
            }
            
            // Formater les données pour l'affichage
            $contractInfo = [
                'id' => $contract['id'],
                'name' => $contract['name'],
                'type_name' => $contract['contract_type_name'] ?? null,
                'start_date' => $contract['start_date'] ?? null,
                'end_date' => $contract['end_date'] ?? null,
                'tickets_remaining' => $contract['tickets_remaining'] ?? null,
                'isticketcontract' => $contract['isticketcontract'] ?? 0,
                'comment' => $contract['comment'] ?? null,
                'status' => $contract['status'] ?? null
            ];
            
            echo json_encode($contractInfo);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Récupère les contacts d'un client
     */
    public function getContacts($clientId) {
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
    public function quickCreateClient() {
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
    public function quickCreateSite() {
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
            echo json_encode(['error' => 'Une erreur est survenue lors de la création du site.']);
        }
    }

    /**
     * Crée rapidement une nouvelle salle via AJAX
     */
    public function quickCreateRoom() {
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
                'site_id' => $_POST['site_id'] ?? '',
                'name' => $_POST['name'] ?? '',
                'comment' => $_POST['comment'] ?? '',
                'status' => 1 // Par défaut actif
            ];
            
            // Valider les données essentielles
            if (empty($roomData['client_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Aucun client sélectionné']);
                return;
            }
            
            if (empty($roomData['site_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Aucun site sélectionné']);
                return;
            }
            
            if (empty($roomData['name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Le nom de la salle est obligatoire']);
                return;
            }
            
            // Vérifier si le site existe et appartient au client
            $sql = "SELECT id FROM sites WHERE id = ? AND client_id = ? AND status = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$roomData['site_id'], $roomData['client_id']]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Site introuvable ou ne correspond pas au client']);
                return;
            }
            
            // Vérifier si une salle avec ce nom existe déjà pour ce site
            $sql = "SELECT id FROM rooms WHERE name = ? AND site_id = ? AND status = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$roomData['name'], $roomData['site_id']]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Une salle avec ce nom existe déjà pour ce site']);
                return;
            }

            // Créer la salle
            $success = $this->roomModel->createRoom($roomData);
            if (!$success) {
                throw new Exception('Erreur lors de la création de la salle');
            }
            
            // Récupérer l'ID de la salle créée
            $roomId = $this->db->lastInsertId();
            
            // Récupérer les données de la salle créée
            $room = $this->roomModel->getRoomById($roomId);
            
            echo json_encode([
                'success' => true,
                'room' => [
                    'id' => $room['id'],
                    'name' => $room['name']
                ],
                'message' => 'Salle créée avec succès'
            ]);
            
        } catch (Exception $e) {
            custom_log("Erreur lors de la création rapide de la salle : " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(['error' => 'Une erreur est survenue lors de la création de la salle.']);
        }
    }

    /**
     * Crée rapidement un nouveau contact via AJAX
     */
    public function quickCreateContact() {
        // Vérifier les permissions
        $this->checkAccess();
        
        // Vérifier si l'utilisateur a les droits d'ajout
        if (!canModifyClients()) {
            http_response_code(403);
            echo json_encode(['error' => "Vous n'avez pas les droits nécessaires pour ajouter un contact."]);
            return;
        }

        header('Content-Type: application/json');
        
        try {
            // Récupérer les données du formulaire
            $contactData = [
                'client_id' => $_POST['client_id'] ?? '',
                'first_name' => $_POST['first_name'] ?? '',
                'last_name' => $_POST['last_name'] ?? '',
                'email' => $_POST['email'] ?? '',
                'phone1' => $_POST['phone1'] ?? '',
                'phone2' => $_POST['phone2'] ?? '',
                'fonction' => $_POST['fonction'] ?? '',
                'comment' => $_POST['comment'] ?? '',
                'has_user_account' => 0, // Par défaut pas de compte utilisateur
                'status' => 1 // Par défaut actif
            ];
            
            // Valider les données essentielles
            if (empty($contactData['client_id']) || !is_numeric($contactData['client_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Aucun client sélectionné ou ID invalide - reçu: ' . $contactData['client_id']]);
                return;
            }
            
            if (empty($contactData['first_name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Le prénom est obligatoire']);
                return;
            }
            
            if (empty($contactData['last_name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Le nom est obligatoire']);
                return;
            }
            
            // Vérifier si le client existe (peut être inactif lors de l'édition)
            $sql = "SELECT id FROM clients WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$contactData['client_id']]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Client introuvable']);
                return;
            }
            
            // Vérifier si un contact avec ce nom existe déjà pour ce client
            $sql = "SELECT id FROM contacts WHERE first_name = ? AND last_name = ? AND client_id = ? AND status = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$contactData['first_name'], $contactData['last_name'], $contactData['client_id']]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Un contact avec ce nom existe déjà pour ce client']);
                return;
            }

            // Créer le contact
            $success = $this->contactModel->createContact($contactData);
            if (!$success) {
                throw new Exception('Erreur lors de la création du contact');
            }
            
            // Récupérer l'ID du contact créé
            $contactId = $this->db->lastInsertId();
            
            // Récupérer les données du contact créé
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
            echo json_encode(['error' => 'Une erreur est survenue lors de la création du contact.']);
        }
    }

    /**
     * Crée automatiquement les contrats "hors contrat" pour un nouveau client
     */
    private function createDefaultContractsForClient($clientId) {
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
    public function create() {
        // Vérifier les permissions
        checkInterventionManagementAccess();
        $clients = $this->clientModel->getAllClientsWithStats(['status' => 1]); // Seulement les clients actifs
        $sites = [];
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

        // Récupérer les durées
        $durations = $this->durationModel->getAll();

        // Charger la vue
        require_once __DIR__ . '/../views/interventions/add.php';
    }

    /**
     * Enregistre une nouvelle intervention
     */
    public function store() {
        // Vérifier les permissions
        checkInterventionManagementAccess();

        // Récupérer les données du formulaire
        $data = [
            'title' => $_POST['title'] ?? '',
            'client_id' => !empty($_POST['client_id']) ? $_POST['client_id'] : null,
            'site_id' => !empty($_POST['site_id']) ? $_POST['site_id'] : null,
            'room_id' => !empty($_POST['room_id']) ? $_POST['room_id'] : null,
            'technician_id' => !empty($_POST['technician_id']) ? $_POST['technician_id'] : null,
            'status_id' => !empty($_POST['status_id']) ? $_POST['status_id'] : 1, // 1 = Nouveau par défaut
            'priority_id' => !empty($_POST['priority_id']) ? $_POST['priority_id'] : 2, // 2 = Normal par défaut
            'type_id' => !empty($_POST['type_id']) ? $_POST['type_id'] : null,
            'duration' => !empty($_POST['duration']) ? $_POST['duration'] : 0, // 0 par défaut au lieu de null
            'description' => $_POST['description'] ?? '',
            'demande_par' => !empty($_POST['demande_par']) ? $_POST['demande_par'] : null,
            'ref_client' => !empty($_POST['ref_client']) ? $_POST['ref_client'] : null,
            'contact_client' => !empty($_POST['contact_client']) ? $_POST['contact_client'] : null,
            'contract_id' => !empty($_POST['contract_id']) ? $_POST['contract_id'] : null,
            'date_planif' => !empty($_POST['date_planif']) ? $_POST['date_planif'] : null,
            'heure_planif' => !empty($_POST['heure_planif']) ? $_POST['heure_planif'] : null,
            'type_requires_travel' => isset($_POST['type_requires_travel']) ? (int)$_POST['type_requires_travel'] : 0
        ];

        // Traiter la date et l'heure de création
        $createdDate = $_POST['created_date'] ?? date('Y-m-d');
        $createdTime = $_POST['created_time'] ?? date('H:i');
        $data['created_at'] = $createdDate . ' ' . $createdTime . ':00';

        // Valider les données requises
        if (empty($data['title'])) {
            $_SESSION['error'] = "Le titre est obligatoire.";
            
            // Gérer le retour en cas d'erreur de validation
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

        if (empty($data['client_id'])) {
            $_SESSION['error'] = "Le client est obligatoire.";
            
            // Gérer le retour en cas d'erreur de validation
            $returnTo = $_GET['return_to'] ?? 'view_intervention';
            if ($returnTo === 'view') {
                header('Location: ' . BASE_URL . 'interventions/add?return_to=view');
            } else {
                header('Location: ' . BASE_URL . 'interventions/add');
            }
            exit;
        }

        if (empty($data['type_id'])) {
            $_SESSION['error'] = "Le type d'intervention est obligatoire.";
            
            // Gérer le retour en cas d'erreur de validation
            $returnTo = $_GET['return_to'] ?? 'view_intervention';
            if ($returnTo === 'view') {
                $clientId = $data['client_id'] ?? null;
                if ($clientId) {
                    header('Location: ' . BASE_URL . 'interventions/add?client_id=' . $clientId . '&return_to=view');
                } else {
                    header('Location: ' . BASE_URL . 'interventions/add?return_to=view');
                }
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

        // Valider le contrat (peut être un ID numérique)
        if (empty($data['contract_id'])) {
            $_SESSION['error'] = "Le contrat est obligatoire.";
            header('Location: ' . BASE_URL . 'interventions/add');
            exit;
        }

        // Vérifier si l'intervention est en train d'être créée avec le statut fermé
        if ($data['status_id'] == 6) { // 6 = Fermé
            // Vérifier que la durée est définie
            if (empty($data['duration'])) {
                $_SESSION['error'] = "Impossible de créer une intervention fermée sans avoir défini une durée.";
                header('Location: ' . BASE_URL . 'interventions/add');
                exit;
            }
            
            // Vérifier qu'un technicien est assigné
            if (empty($data['technician_id'])) {
                $_SESSION['error'] = "Impossible de créer une intervention fermée sans avoir assigné un technicien.";
                header('Location: ' . BASE_URL . 'interventions/add');
                exit;
            }
            
            // Calculer le nombre de tickets utilisés seulement si c'est un contrat à tickets
            $ticketsUsed = 0;
            if (!empty($data['contract_id']) && isContractTicketById($data['contract_id'])) {
                $ticketsUsed = $this->calculateTicketsUsed($data['duration'], $data['technician_id'], $data['type_id'], $data['type_requires_travel'] ?? null);
            }
            $data['tickets_used'] = $ticketsUsed;
            
            // Ajouter la date de fermeture
            $data['closed_at'] = date('Y-m-d H:i:s');
        }

        // Créer l'intervention
        $sql = "INSERT INTO interventions (
                    title, client_id, site_id, room_id, technician_id, status_id, 
                    priority_id, type_id, duration, description, demande_par, ref_client, contact_client, 
                    contract_id, reference, date_planif, heure_planif, tickets_used, closed_at, created_at, type_requires_travel
                ) VALUES (
                    :title, :client_id, :site_id, :room_id, :technician_id, :status_id, 
                    :priority_id, :type_id, :duration, :description, :demande_par, :ref_client, :contact_client, 
                    :contract_id, :reference, :date_planif, :heure_planif, :tickets_used, :closed_at, :created_at, :type_requires_travel
                )";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':title' => $data['title'],
            ':client_id' => $data['client_id'],
            ':site_id' => $data['site_id'],
            ':room_id' => $data['room_id'],
            ':technician_id' => $data['technician_id'],
            ':status_id' => $data['status_id'],
            ':priority_id' => $data['priority_id'],
            ':type_id' => $data['type_id'],
            ':duration' => $data['duration'],
            ':description' => $data['description'],
            ':demande_par' => $data['demande_par'],
            ':ref_client' => $data['ref_client'],
            ':contact_client' => $data['contact_client'],
            ':contract_id' => $data['contract_id'],
            ':reference' => $this->interventionModel->generateReference($data['client_id']),
            ':date_planif' => $data['date_planif'],
            ':heure_planif' => $data['heure_planif'],
            ':tickets_used' => $data['tickets_used'] ?? null,
            ':type_requires_travel' => $data['type_requires_travel'],
            ':closed_at' => $data['closed_at'] ?? null,
            ':created_at' => $data['created_at']
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
                ':description' => "Intervention créée"
            ]);
            
            // Envoyer l'email de création d'intervention
            try {
                $this->mailService->sendInterventionCreated($interventionId);
            } catch (Exception $e) {
                // Log l'erreur mais ne pas faire échouer la création
                custom_log_mail("Erreur envoi email création intervention $interventionId : " . $e->getMessage(), 'ERROR');
            }
            
            // Vérifier si un technicien a été affecté et si on doit envoyer un email
            if (!empty($data['technician_id']) && isset($_POST['notify_technician']) && $_POST['notify_technician'] == '1') {
                try {
                    $emailSent = $this->mailService->sendTechnicianAssigned($interventionId, $data['technician_id']);
                    if ($emailSent) {
                        custom_log_mail("Email de notification envoyé au technicien {$data['technician_id']} pour l'intervention $interventionId", 'INFO');
                    } else {
                        custom_log_mail("Échec de l'envoi de l'email de notification au technicien {$data['technician_id']} pour l'intervention $interventionId", 'WARNING');
                    }
                } catch (Exception $e) {
                    custom_log_mail("Erreur lors de l'envoi de l'email de notification au technicien : " . $e->getMessage(), 'ERROR');
                }
            }
            
            $successMessage = "Intervention créée avec succès.";
            if (!empty($data['technician_id']) && isset($_POST['notify_technician']) && $_POST['notify_technician'] == '1') {
                $successMessage .= " Le technicien a été notifié par email.";
            }
            $_SESSION['success'] = $successMessage;
            
            // Gérer le retour intelligent
            $returnTo = $_GET['return_to'] ?? 'view_intervention';
            if ($returnTo === 'view') {
                // Récupérer l'ID du client depuis les données POST
                $clientId = $data['client_id'] ?? null;
                if ($clientId) {
                    header('Location: ' . BASE_URL . 'clients/view/' . $clientId . '?active_tab=interventions-tab');
                } else {
                    header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
                }
            } else {
                header('Location: ' . BASE_URL . 'interventions/view/' . $interventionId);
            }
        } else {
            $_SESSION['error'] = "Erreur lors de la création de l'intervention.";
            
            // Gérer le retour en cas d'erreur
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
        }
        exit;
    }

    /**
     * Modifie un commentaire
     */
    public function editComment($commentId) {
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
    public function getAllStatuses() {
        $sql = "SELECT id, name, color, is_critical, created_at FROM intervention_statuses ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * S'auto-affecter une intervention
     */
    public function assignToMe($id) {
        // Vérifier les permissions
        checkInterventionManagementAccess();

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($id);
        
        if (!$intervention) {
            $_SESSION['error'] = "Intervention introuvable.";
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }

        // Vérifier si l'intervention est fermée
        if ($intervention['status_id'] == 6) {
            $_SESSION['error'] = "Impossible de modifier une intervention fermée.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        // Vérifier si l'intervention est déjà affectée au technicien connecté
        if ($intervention['technician_id'] == $_SESSION['user']['id']) {
            $_SESSION['info'] = "Cette intervention vous est déjà affectée.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        // Mettre à jour l'intervention avec le technicien actuel
        $sql = "UPDATE interventions SET technician_id = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$_SESSION['user']['id'], $id]);

        if ($result) {
            $_SESSION['success'] = "Vous avez été affecté à cette intervention.";
        } else {
            $_SESSION['error'] = "Une erreur est survenue lors de l'affectation.";
        }

        header('Location: ' . BASE_URL . 'interventions/view/' . $id);
        exit;
    }

    /**
     * Récupère les détails de calcul de tickets pour la fermeture d'intervention
     */
    public function getCloseDetails($id) {
        custom_log("DEBUG - getCloseDetails() - Début de la méthode avec ID: $id", "DEBUG");
        
        // Vérifier les permissions
        checkInterventionManagementAccess();
        custom_log("DEBUG - getCloseDetails() - Permissions vérifiées", "DEBUG");

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($id);
        custom_log("DEBUG - getCloseDetails() - Intervention récupérée: " . ($intervention ? 'OUI' : 'NON'), "DEBUG");
        
        if (!$intervention) {
            custom_log("DEBUG - getCloseDetails() - Intervention introuvable", "ERROR");
            http_response_code(404);
            echo json_encode(['error' => 'Intervention introuvable.']);
            exit;
        }

        // Vérifier si l'intervention est déjà fermée
        if ($intervention['status_id'] == 6) {
            http_response_code(400);
            echo json_encode(['error' => 'Cette intervention est déjà fermée.']);
            exit;
        }

        // Vérifier si l'intervention est affectée au technicien connecté
        if ($intervention['technician_id'] != $_SESSION['user']['id'] && !isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Vous ne pouvez fermer que les interventions qui vous sont affectées.']);
            exit;
        }

        // Vérifier tous les prérequis
        if (empty($intervention['type_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Impossible de fermer l\'intervention sans avoir défini un type d\'intervention.']);
            exit;
        }

        if (empty($intervention['duration'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Impossible de fermer l\'intervention sans avoir défini une durée.']);
            exit;
        }

        if (empty($intervention['technician_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Impossible de fermer l\'intervention sans avoir assigné un technicien.']);
            exit;
        }

        // Récupérer les informations nécessaires pour le calcul
        $technician = $this->userModel->getUserById($intervention['technician_id']);
        $type = $this->interventionModel->getTypeInfo($intervention['type_id']);
        
        // Utiliser la valeur stockée dans l'intervention (le modèle utilise COALESCE pour retourner 
        // la valeur de l'intervention si elle existe, sinon celle du type)
        $requiresTravel = (bool)($intervention['type_requires_travel'] ?? false);
        
        // Récupérer le coefficient d'intervention depuis les paramètres
        $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'coef_intervention'");
        $stmt->execute();
        $coefIntervention = floatval($stmt->fetchColumn()) ?? 0;

        // Calculer les tickets selon la formule
        $coefUtilisateur = $technician['coef_utilisateur'] ?? 0;
        
        if ($requiresTravel) {
            // Avec déplacement : durée + coef_utilisateur + 1 + coef_intervention
            $tickets = $intervention['duration'] + $coefUtilisateur + 1 + $coefIntervention;
        } else {
            // Sans déplacement : durée + coef_utilisateur + coef_intervention
            $tickets = $intervention['duration'] + $coefUtilisateur + $coefIntervention;
        }

        // Arrondir à l'entier supérieur
        $ticketsUsed = ceil($tickets);

        // Récupérer les informations du contrat si applicable
        $contractInfo = null;
        if (!empty($intervention['contract_id'])) {
            $stmt = $this->db->prepare("
                SELECT c.*, ct.name as type_name, 
                       (c.tickets_number - COALESCE(SUM(i.tickets_used), 0)) as tickets_remaining
                FROM contracts c
                LEFT JOIN contract_types ct ON c.contract_type_id = ct.id
                LEFT JOIN interventions i ON c.id = i.contract_id AND i.status_id = 6
                WHERE c.id = ?
                GROUP BY c.id
            ");
            $stmt->execute([$intervention['contract_id']]);
            $contractInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Préparer la réponse
        $response = [
            'success' => true,
            'intervention' => [
                'id' => $intervention['id'],
                'reference' => $intervention['reference'],
                'title' => $intervention['title'],
                'duration' => $intervention['duration'],
                'technician_name' => $technician['first_name'] . ' ' . $technician['last_name'],
                'type_name' => $type['name'],
                'requires_travel' => $requiresTravel
            ],
            'calculation' => [
                'duration' => $intervention['duration'],
                'coef_utilisateur' => $coefUtilisateur,
                'coef_intervention' => $coefIntervention,
                'requires_travel' => $requiresTravel,
                'travel_bonus' => $requiresTravel ? 1 : 0,
                'formula' => $requiresTravel ? 
                    "{$intervention['duration']} + {$coefUtilisateur} + 1 + {$coefIntervention} = {$tickets}" :
                    "{$intervention['duration']} + {$coefUtilisateur} + {$coefIntervention} = {$tickets}",
                'tickets_calculated' => $tickets,
                'tickets_used' => $ticketsUsed
            ],
            'contract' => $contractInfo
        ];

        custom_log("DEBUG - getCloseDetails() - Réponse préparée: " . json_encode($response), "DEBUG");
        
        header('Content-Type: application/json');
        echo json_encode($response);
        custom_log("DEBUG - getCloseDetails() - Réponse envoyée", "DEBUG");
        exit;
    }

    /**
     * Ferme une intervention
     */
    public function close($id) {
        // Vérifier les permissions
        checkInterventionManagementAccess();

        // Récupérer l'intervention
        $intervention = $this->interventionModel->getById($id);
        
        if (!$intervention) {
            $_SESSION['error'] = "Intervention introuvable.";
            header('Location: ' . $this->getInterventionsListUrl());
            exit;
        }

        // Vérifier si l'intervention est déjà fermée
        if ($intervention['status_id'] == 6) {
            $_SESSION['info'] = "Cette intervention est déjà fermée.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        // Vérifier si l'intervention est affectée au technicien connecté
        if ($intervention['technician_id'] != $_SESSION['user']['id'] && !isAdmin()) {
            $_SESSION['error'] = "Vous ne pouvez fermer que les interventions qui vous sont affectées.";
            header('Location: ' . BASE_URL . 'interventions/view/' . $id);
            exit;
        }

        // Vérifier tous les prérequis
        if (empty($intervention['type_id'])) {
            $_SESSION['error'] = "Impossible de fermer l'intervention sans avoir défini un type d'intervention.";
            header('Location: ' . BASE_URL . 'interventions/edit/' . $id);
            exit;
        }

        if (empty($intervention['duration'])) {
            $_SESSION['error'] = "Impossible de fermer l'intervention sans avoir défini une durée.";
            header('Location: ' . BASE_URL . 'interventions/edit/' . $id);
            exit;
        }

        if (empty($intervention['technician_id'])) {
            $_SESSION['error'] = "Impossible de fermer l'intervention sans avoir assigné un technicien.";
            header('Location: ' . BASE_URL . 'interventions/edit/' . $id);
            exit;
        }

        // Récupérer le nombre de tickets à utiliser (peut être personnalisé)
        $ticketsUsed = 0;
        if (!empty($intervention['contract_id']) && isContractTicketById($intervention['contract_id'])) {
            // Vérifier si un nombre de tickets personnalisé a été fourni
            if (isset($_POST['tickets_used']) && is_numeric($_POST['tickets_used'])) {
                $ticketsUsed = (int)$_POST['tickets_used'];
                error_log("DEBUG - close() - Tickets personnalisés: " . $ticketsUsed);
            } else {
                // Calculer automatiquement le nombre de tickets
                error_log("DEBUG - close() - Calcul des tickets pour l'intervention $id (contrat à tickets)");
                error_log("DEBUG - close() - Durée: " . $intervention['duration']);
                error_log("DEBUG - close() - Technicien ID: " . $intervention['technician_id']);
                error_log("DEBUG - close() - Type ID: " . $intervention['type_id']);
                
                $ticketsUsed = $this->calculateTicketsUsed(
                    $intervention['duration'],
                    $intervention['technician_id'],
                    $intervention['type_id'],
                    $intervention['type_requires_travel'] ?? null
                );
                
                error_log("DEBUG - close() - Tickets calculés: " . $ticketsUsed);
            }
        } else {
            error_log("DEBUG - close() - Pas de calcul de tickets (contrat sans tickets ou pas de contrat)");
        }

        // Mettre à jour l'intervention
        $sql = "UPDATE interventions SET 
                status_id = 6, 
                closed_at = NOW(),
                tickets_used = :tickets_used 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':tickets_used' => $ticketsUsed,
            ':id' => $id
        ]);
        
        error_log("DEBUG - close() - Résultat de la mise à jour: " . ($result ? 'SUCCÈS' : 'ÉCHEC'));

        if ($result) {
            // Déduire les tickets du contrat si un contrat est associé
            if (!empty($intervention['contract_id'])) {
                $this->deductTicketsFromContract($intervention['contract_id'], $ticketsUsed, $id);
            }

            // Enregistrer l'action dans l'historique
            // OPTIMISATION N+1 : Précharger les status_id nécessaires (ancien et nouveau)
            $statusIds = array_filter([$intervention['status_id'], 6]);
            $lookupData = ['statuses' => []];
            if (!empty($statusIds)) {
                $placeholders = implode(',', array_fill(0, count($statusIds), '?'));
                $stmt = $this->db->prepare("SELECT id, name FROM intervention_statuses WHERE id IN ($placeholders)");
                $stmt->execute(array_values($statusIds));
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $lookupData['statuses'][$row['id']] = $row['name'];
                }
            }
            
            $sql = "INSERT INTO intervention_history (
                        intervention_id, field_name, old_value, new_value, changed_by, description
                    ) VALUES (
                        :intervention_id, :field_name, :old_value, :new_value, :changed_by, :description
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':intervention_id' => $id,
                ':field_name' => 'Statut',
                ':old_value' => $this->getDisplayValue('status_id', $intervention['status_id'], $lookupData),
                ':new_value' => $this->getDisplayValue('status_id', 6, $lookupData),
                ':changed_by' => $_SESSION['user']['id'],
                ':description' => "Intervention fermée avec {$ticketsUsed} tickets utilisés"
            ]);

            // Envoyer l'email de fermeture d'intervention si demandé
            $sendEmail = isset($_POST['send_email']) && $_POST['send_email'] == '1';
            if ($sendEmail) {
                try {
                    // Forcer l'envoi même si l'auto-envoi est désactivé (envoi manuel)
                    $this->mailService->sendInterventionClosed($id, true);
                } catch (Exception $e) {
                    // Log l'erreur mais ne pas faire échouer la fermeture
                    custom_log_mail("Erreur envoi email fermeture intervention $id : " . $e->getMessage(), 'ERROR');
                }
            }

            $_SESSION['success'] = "L'intervention a été fermée avec succès.";
        } else {
            $_SESSION['error'] = "Une erreur est survenue lors de la fermeture de l'intervention.";
        }

        header('Location: ' . BASE_URL . 'interventions/view/' . $id);
        exit;
    }

    public function generateReport($id) {
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
    private function deductTicketsFromContract($contractId, $ticketsUsed, $interventionId = null) {
        error_log("DEBUG - deductTicketsFromContract appelée avec: contractId=$contractId, ticketsUsed=$ticketsUsed");
        
        if (!$contractId) {
            error_log("DEBUG - deductTicketsFromContract: Pas de contrat, pas de déduction");
            return; // Pas de contrat, pas de déduction
        }
        
        // Vérifier si le contrat est de type ticket (isticketcontract = 1)
        $sql = "SELECT isticketcontract FROM contracts WHERE id = :contract_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':contract_id' => $contractId]);
        $contract = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$contract || $contract['isticketcontract'] != 1) {
            error_log("DEBUG - deductTicketsFromContract: Contrat non-ticket ou inexistant, pas de déduction");
            // Ce n'est pas un contrat de type ticket, pas de déduction
            return;
        }
        
        error_log("DEBUG - deductTicketsFromContract: Enregistrement de l'historique AVANT mise à jour");
        
        // Enregistrer la déduction dans l'historique du contrat AVANT de modifier les tickets
        $contractModel = new ContractModel($this->db);
        
        // Construire le commentaire avec le code d'intervention si disponible
        $comment = 'Déduction automatique - Intervention fermée';
        if ($interventionId) {
            // Récupérer le code d'intervention
            $stmt = $this->db->prepare("SELECT reference FROM interventions WHERE id = ?");
            $stmt->execute([$interventionId]);
            $intervention = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($intervention && !empty($intervention['reference'])) {
                $comment = $intervention['reference'] . ' - ' . $comment;
            }
        }
        
        $historyResult = $contractModel->recordTicketDeduction($contractId, $ticketsUsed, $comment);
        
        if ($historyResult) {
            error_log("DEBUG - deductTicketsFromContract: Enregistrement dans l'historique réussi");
        } else {
            error_log("ERROR - deductTicketsFromContract: Échec de l'enregistrement dans l'historique");
        }
        
        error_log("DEBUG - deductTicketsFromContract: Mise à jour des tickets restants");
        
        // Maintenant mettre à jour les tickets restants
        $sql = "UPDATE contracts SET tickets_remaining = tickets_remaining - :tickets_used WHERE id = :contract_id";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':tickets_used' => $ticketsUsed,
            ':contract_id' => $contractId
        ]);
        
        if ($result) {
            error_log("DEBUG - deductTicketsFromContract: Mise à jour des tickets réussie");
        } else {
            error_log("ERROR - deductTicketsFromContract: Échec de la mise à jour des tickets");
        }
    }

    /**
     * Force le nombre de tickets utilisés pour une intervention fermée (admin seulement)
     */
    public function forceTickets($id) {
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
            
            $newTicketsUsed = (int)($_POST['tickets_used'] ?? 0);
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
            $oldTicketsUsed = (int)($intervention['tickets_used'] ?? 0);
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
    public function delete($id) {
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
    public function getAttachmentInfo($attachmentId) {
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
    public function updateAttachmentName($attachmentId) {
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
    public function generateBon($interventionId) {
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
    public function saveBonSelection($interventionId) {
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
    public function generateBonPdf($interventionId) {
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
    private function getCommentsForBon($interventionId) {
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
    private function getAttachmentsForBon($interventionId) {
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
    private function generateBonInterventionPdf($intervention, $comments, $attachments) {
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
    private function handleTicketManagementOnContractChange($interventionId, $oldIntervention, $newData) {
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
    private function getContractTicketInfo($contractId) {
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
    private function handleTicketContractToTicketContract($oldContractId, $newContractId, $ticketsUsed, $interventionId) {
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
    private function handleTicketContractToNonTicketContract($oldContractId, $ticketsUsed, $interventionId) {
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
    private function handleNonTicketContractToTicketContract($newContractId, $ticketsUsed, $interventionId) {
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
    private function getInterventionReference($interventionId) {
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
    private function recordContractChangeInInterventionHistory($interventionId, $oldContract, $newContract, $ticketsUsed) {
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
    public function getEmailData($id) {
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
            $activeTemplates = array_filter($templates, function($t) {
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
    public function getMailHistory($id) {
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
                $subject = (string)($r['subject'] ?? '');
                $createdAt = (string)($r['created_at'] ?? '');
                $sentAt = (string)($r['sent_at'] ?? '');
                $displayAt = $sentAt !== '' ? $sentAt : $createdAt;

                $sendUuid = isset($r['send_uuid']) ? trim((string)$r['send_uuid']) : '';

                // Clé de regroupement (send_uuid si présent) sinon: seconde + sujet + template
                $ts = $displayAt !== '' ? date('Y-m-d H:i:s', strtotime($displayAt)) : '';
                $templateId = $r['template_id'] ?? null;
                $key = $sendUuid !== '' ? ('uuid|' . $sendUuid) : ($ts . '|' . $subject . '|' . (string)($templateId ?? ''));

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

                $email = isset($r['recipient_email']) ? trim((string)$r['recipient_email']) : '';
                $name = isset($r['recipient_name']) ? trim((string)$r['recipient_name']) : '';
                if ($email !== '') {
                    $label = $name !== '' ? ($name . ' <' . $email . '>') : $email;
                    $grouped[$key]['recipients'][strtolower($email)] = $label;
                }
            }

            // Remettre en tableau, tri desc par datetime
            $items = array_values(array_map(function($g) {
                $to = implode(', ', array_values($g['recipients']));
                $cc = is_string($g['cc_snapshot'] ?? null) ? trim($g['cc_snapshot']) : '';
                $dest = $to !== '' ? ("À: " . $to) : "À: (aucun)";
                if ($cc !== '') {
                    $dest .= " | CC: " . $cc;
                }
                $g['recipients'] = $dest;
                return $g;
            }, $grouped));

            usort($items, function($a, $b) {
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
    public function sendEmail($id) {
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
    public function previewEmailTemplate($id) {
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
} 