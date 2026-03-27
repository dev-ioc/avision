<?php
require_once __DIR__ . '/../models/ContractsClientModel.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';
require_once __DIR__ . '/../classes/Services/AttachmentService.php';

class ContractsClientController {
    use AccessControlTrait;
    private $db;
    private $model;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->model = new ContractsClientModel($this->db);
    }

    /**
     * Vérifie si l'utilisateur est connecté et a les permissions client
     */
    /**
     * Vérifie si l'utilisateur est connecté et a les permissions client
     * Utilise AccessControlTrait::checkClientPermission()
     */
    private function checkAccess() {
        $this->checkClientPermission('client_view_contracts');
    }

    /**
     * Affiche la liste des contrats du client
     */
    public function index() {
        $this->checkAccess();

        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocations();

        // Récupérer les filtres
        $filters = [
            'client_id' => $_GET['client_id'] ?? null,
            'site_id' => $_GET['site_id'] ?? null,
            'room_id' => $_GET['room_id'] ?? null,
            'contract_type_id' => $_GET['contract_type_id'] ?? null,
            'status' => $_GET['status'] ?? 'actif', // Par défaut: contrats actifs
            'search' => $_GET['search'] ?? null
        ];

        // Récupérer les contrats filtrés selon les localisations autorisées
        $contracts = $this->model->getAllByLocations($userLocations, $filters);

        // Récupérer les données pour les filtres
        $clients = $this->model->getClientsByLocations($userLocations);
        $sites = [];
        if ($filters['client_id']) {
            $sites = $this->model->getSitesByClientAndLocations($filters['client_id'], $userLocations);
        }
        $rooms = [];
        if ($filters['site_id']) {
            $rooms = $this->model->getRoomsBySiteAndLocations($filters['site_id'], $userLocations);
        }
        $contractTypes = $this->model->getContractTypes();

        // Statistiques
        $stats = $this->model->getStatsByLocations($userLocations);

        // Charger la vue
        require_once __DIR__ . '/../views/contracts_client/index.php';
    }

    /**
     * Affiche le détail d'un contrat
     */
    public function view($id) {
        $this->checkAccess();

        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocations();

        // Récupérer le contrat avec vérification d'accès
        $contract = $this->model->getByIdWithAccess($id, $userLocations);

        if (!$contract) {
            $_SESSION['error'] = "Contrat introuvable ou vous n'avez pas les permissions pour y accéder.";
            header('Location: ' . BASE_URL . 'contracts_client');
            exit;
        }

        // Récupérer les interventions liées à ce contrat
        $interventions = $this->model->getInterventionsByContractAndLocations($id, $userLocations);

        // Récupérer les pièces jointes du contrat
        $attachments = $this->model->getPiecesJointesWithAccess($id, $userLocations);

        // Charger la vue
        require_once __DIR__ . '/../views/contracts_client/view.php';
    }

    /**
     * Récupère les salles d'un site selon les localisations autorisées (AJAX)
     */
    public function getRoomsBySiteAndLocations() {
        $this->checkAccess();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Méthode non autorisée']);
            return;
        }

        $siteId = $_POST['site_id'] ?? null;
        if (!$siteId) {
            echo json_encode(['error' => 'ID du site manquant']);
            return;
        }

        $userLocations = getUserLocations();
        $rooms = $this->model->getRoomsBySiteAndLocations($siteId, $userLocations);

        header('Content-Type: application/json');
        echo json_encode($rooms);
    }

    /**
     * Télécharge une pièce jointe (client)
     * Utilise AttachmentService pour centraliser la logique
     */
    public function download($attachmentId) {
        $this->checkClientPermission('client_view_contracts');
        
        try {
            $userLocations = getUserLocations();
            
            // Vérifier que la pièce jointe appartient à un contrat accessible
            $attachmentService = new AttachmentService($this->db);
            $attachmentData = $attachmentService->getAttachmentById($attachmentId);
            
            if (!$attachmentData || $attachmentData['type_liaison'] !== AttachmentService::TYPE_CONTRACT) {
                throw new Exception('Pièce jointe non trouvée.');
            }
            
            // Vérifier l'accès au contrat
            $contract = $this->model->getByIdWithAccess($attachmentData['entite_id'], $userLocations);
            if (!$contract) {
                throw new Exception('Vous n\'êtes pas autorisé à accéder à cette pièce jointe.');
            }
            
            // Utiliser AttachmentService pour gérer le téléchargement
            $attachmentService->download($attachmentId, true);
            
        } catch (Exception $e) {
            custom_log("Erreur lors du téléchargement de la pièce jointe (client contrat) : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors du téléchargement : " . $e->getMessage();
            $contractId = $attachmentData['entite_id'] ?? 0;
            header('Location: ' . BASE_URL . 'contracts_client/view/' . $contractId);
            exit;
        }
    }

    /**
     * Aperçu d'une pièce jointe (client)
     * Utilise AttachmentService pour centraliser la logique
     */
    public function preview($attachmentId) {
        $this->checkClientPermission('client_view_contracts');
        
        try {
            $userLocations = getUserLocations();
            
            // Vérifier que la pièce jointe appartient à un contrat accessible
            $attachmentService = new AttachmentService($this->db);
            $attachmentData = $attachmentService->getAttachmentById($attachmentId);
            
            if (!$attachmentData || $attachmentData['type_liaison'] !== AttachmentService::TYPE_CONTRACT) {
                throw new Exception('Pièce jointe non trouvée.');
            }
            
            // Vérifier l'accès au contrat
            $contract = $this->model->getByIdWithAccess($attachmentData['entite_id'], $userLocations);
            if (!$contract) {
                throw new Exception('Vous n\'êtes pas autorisé à accéder à cette pièce jointe.');
            }
            
            // Utiliser AttachmentService pour gérer l'aperçu
            $attachmentService->preview($attachmentId);
            
        } catch (Exception $e) {
            custom_log("Erreur lors de l'aperçu de la pièce jointe (client contrat) : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de l'aperçu : " . $e->getMessage();
            $contractId = $attachmentData['entite_id'] ?? 0;
            header('Location: ' . BASE_URL . 'contracts_client/view/' . $contractId);
            exit;
        }
    }
} 