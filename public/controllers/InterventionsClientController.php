<?php
/**
 * Contrôleur pour la gestion des interventions clients
 * Filtre automatiquement selon les localisations autorisées du client
 */
require_once __DIR__ . '/../classes/Services/AttachmentService.php';

class InterventionsClientController
{
    private $db;
    private $model;
    private $clientModel;
    private $siteModel;
    private $buildingModel;
    private $roomModel;
    private $table;

    public function __construct($db)
    {
        $this->db = $db;

        // Charger les modèles nécessaires
        require_once __DIR__ . '/../models/InterventionsClientModel.php';
        require_once __DIR__ . '/../models/ClientModel.php';
        require_once __DIR__ . '/../models/SiteModel.php';
        require_once __DIR__ . '/../models/BuildingModel.php';
        require_once __DIR__ . '/../models/RoomModel.php';

        $this->model = new InterventionsClientModel($db);
        $this->clientModel = new ClientModel($db);
        $this->siteModel = new SiteModel($db);
        $this->buildingModel = new BuildingModel($db);
        $this->roomModel = new RoomModel($db);
        $this->table = 'interventions';
    }

    /**
     * Affiche la liste des interventions du client
     */
    public function index()
    {
        // Vérifier si l'utilisateur est connecté et est un client
        if (!isset($_SESSION['user']) || !isClient()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Vérifier la permission spécifique pour voir les interventions
        if (!hasPermission('client_view_interventions')) {
            $_SESSION['error'] = "Vous n'avez pas la permission d'accéder aux interventions";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Récupérer l'ID du client depuis la session
        $clientId = $_SESSION['user']['client_id'] ?? null;

        if (!$clientId) {
            $_SESSION['error'] = "Aucun client associé à votre compte";
            header('Location: ' . BASE_URL . 'auth/logout');
            exit;
        }

        // Récupérer les localisations autorisées
        $userLocations = getUserLocations();

        // Si l'utilisateur n'a pas de localisations définies, utiliser le client_id par défaut
        if (empty($userLocations)) {
            $userLocations = [['client_id' => $clientId, 'site_id' => null, 'building_id' => null, 'room_id' => null]];
        }

        // Récupérer les filtres depuis l'URL
        $filters = [
            'site_id' => $_GET['site_id'] ?? null,
            'building_id' => $_GET['building_id'] ?? null,
            'room_id' => $_GET['room_id'] ?? null,
            'status_id' => $_GET['status_id'] ?? null,
            'search' => $_GET['search'] ?? null
        ];
        // Construire la clause WHERE pour les localisations
        $locationWhere = buildLocationWhereClause($userLocations, 'i.client_id', 'i.site_id', 'i.building_id', 'i.room_id');

        // Récupérer les interventions filtrées selon les localisations
        $interventions = $this->model->getAllByLocations($userLocations, $filters);

        // Récupérer les données pour les filtres
        $sites = $this->model->getSitesByLocations($userLocations);
        $buildings = !empty($filters['site_id']) ? $this->model->getBuildingsBySiteAndLocations($filters['site_id'], $userLocations) : [];
        $rooms = !empty($filters['building_id']) ? $this->model->getRoomsByBuildingAndLocations($filters['building_id'], $userLocations) : [];

        // Récupérer les statuts
        $statuses = $this->model->getAllStatuses();

        // Récupérer les statistiques
        $stats = $this->model->getStatsByLocations($userLocations);

        // Récupérer les statistiques par statut pour les filtres rapides
        $statsByStatus = $this->model->getStatsByStatusAndLocations($userLocations);

        // Charger la vue
        require_once __DIR__ . '/../views/interventions_client/index.php';
    }

    /**
     * Affiche les détails d'une intervention
     */
    public function view($id)
    {
        // Vérifier si l'utilisateur est connecté et est un client
        if (!isset($_SESSION['user']) || !isClient()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Vérifier la permission spécifique pour voir les interventions
        if (!hasPermission('client_view_interventions')) {
            $_SESSION['error'] = "Vous n'avez pas la permission d'accéder aux interventions";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Récupérer l'ID du client depuis la session
        $clientId = $_SESSION['user']['client_id'] ?? null;

        if (!$clientId) {
            $_SESSION['error'] = "Aucun client associé à votre compte";
            header('Location: ' . BASE_URL . 'auth/logout');
            exit;
        }

        // Récupérer les localisations autorisées
        $userLocations = getUserLocations();

        // Si l'utilisateur n'a pas de localisations définies, utiliser le client_id par défaut
        if (empty($userLocations)) {
            $userLocations = [['client_id' => $clientId, 'site_id' => null, 'building_id' => null, 'room_id' => null]];
        }

        // Récupérer l'intervention
        $intervention = $this->model->getByIdWithAccess($id, $userLocations);

        if (!$intervention) {
            custom_log("Intervention non trouvée ou non autorisée pour l'ID: $id", 'ERROR');
            $_SESSION['error'] = "Intervention non trouvée ou non autorisée";
            header('Location: ' . BASE_URL . 'interventions_client');
            exit;
        }
        // Récupérer les noms du site, bâtiment et salle
        if (!empty($intervention['site_id'])) {
            $site = $this->siteModel->getSiteById($intervention['site_id']);
            $intervention['site_name'] = $site['name'] ?? 'N/A';
        }

        if (!empty($intervention['building_id'])) {
            $building = $this->buildingModel->getBuildingById($intervention['building_id']);
            $intervention['building_name'] = $building['name'] ?? 'N/A';
        }

        if (!empty($intervention['room_id'])) {
            $room = $this->roomModel->getRoomById($intervention['room_id']);
            $intervention['room_name'] = $room['name'] ?? 'N/A';
        }

        // Récupérer les techniciens assignés
        $technicians = $this->model->getTechniciansByIntervention($id);

        $intervention['technicians_names'] = $technicians['technicians_names'] ?? '';
        $intervention['technicien_ids'] = $technicians['technicien_ids'] ?? '';
        $intervention['type_requires_travel'] = $technicians['type_requires_travel'] ?? 0;
        // Récupérer le contrat associé directement via contract_id pour les informations de tickets
        $contract = null;
        if (!empty($intervention['contract_id'])) {
            require_once __DIR__ . '/../models/ContractModel.php';
            $contractModel = new ContractModel($this->db);
            $contract = $contractModel->getContractById($intervention['contract_id']);
        }

        // Ajouter les informations du contrat pour l'affichage des tickets
        if ($contract && isContractTicketById($contract['id'])) {
            $intervention['contract_tickets_number'] = $contract['tickets_number'];
            $intervention['contract_tickets_remaining'] = $contract['tickets_remaining'];
        } else {
            $intervention['contract_tickets_number'] = 0;
            $intervention['contract_tickets_remaining'] = 0;
        }

        // Récupérer les commentaires (filtrés pour les clients)
        $comments = $this->model->getCommentsWithAccess($id, $userLocations, true, $_SESSION['user']['id']);

        // Récupérer les pièces jointes
        $attachments = $this->model->getAttachmentsWithAccess($id, $userLocations);
        // Récupérer les comptes-rendus techniciens visibles par le client
        $technicianReports = $this->model->getTechnicianReportsWithAccess($id, $userLocations);

        // Charger la vue
        require_once __DIR__ . '/../views/interventions_client/view.php';
    }

    /**
     * Récupère les bâtiments d'un site selon les localisations autorisées
     */
    public function getBuildingsBySiteAndLocations($siteId, $userLocations)
    {
        return $this->model->getBuildingsBySiteAndLocations($siteId, $userLocations);
    }

    /**
     * Récupère les salles d'un bâtiment selon les localisations autorisées
     */
    public function getRoomsByBuildingAndLocations($buildingId, $userLocations)
    {
        return $this->model->getRoomsByBuildingAndLocations($buildingId, $userLocations);
    }

    /**
     * Récupère les salles d'un site selon les localisations autorisées (pour compatibilité)
     * @deprecated Utiliser getRoomsByBuildingAndLocations à la place
     */
    public function getRoomsBySiteAndLocations($siteId, $userLocations)
    {
        // Cette méthode est maintenue pour compatibilité mais est dépréciée
        // Elle retourne les salles de tous les bâtiments du site
        $buildings = $this->model->getBuildingsBySiteAndLocations($siteId, $userLocations);
        $rooms = [];
        foreach ($buildings as $building) {
            $buildingRooms = $this->model->getRoomsByBuildingAndLocations($building['id'], $userLocations);
            $rooms = array_merge($rooms, $buildingRooms);
        }
        return $rooms;
    }

    /**
     * Ajouter un commentaire
     */
    public function addComment($interventionId)
    {
        if (!hasPermission('client_view_interventions')) {
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $comment = trim($_POST['comment'] ?? '');

            if (empty($comment)) {
                $_SESSION['error'] = 'Le commentaire ne peut pas être vide.';
                header('Location: ' . BASE_URL . 'interventions_client/view/' . $interventionId);
                exit;
            }

            // Vérifier que l'intervention appartient aux locations autorisées du client
            $userLocations = getUserLocations();
            $intervention = $this->model->getByIdWithAccess($interventionId, $userLocations);

            if (!$intervention) {
                $_SESSION['error'] = 'Intervention non trouvée ou non autorisée.';
                header('Location: ' . BASE_URL . 'interventions_client');
                exit;
            }

            $userId = $_SESSION['user']['id'];
            $success = $this->model->addComment($interventionId, $userId, $comment, true);

            if ($success) {
                $_SESSION['success'] = 'Commentaire ajouté avec succès.';
            } else {
                $_SESSION['error'] = 'Erreur lors de l\'ajout du commentaire.';
            }
        }

        header('Location: ' . BASE_URL . 'interventions_client/view/' . $interventionId);
        exit;
    }

    /**
     * Modifier un commentaire
     */
    public function editComment($commentId)
    {
        if (!hasPermission('client_view_interventions')) {
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $comment = trim($_POST['comment'] ?? '');

            if (empty($comment)) {
                $_SESSION['error'] = 'Le commentaire ne peut pas être vide.';
                header('Location: ' . BASE_URL . 'interventions_client/view/' . $this->getInterventionIdFromComment($commentId));
                exit;
            }

            $userId = $_SESSION['user']['id'];

            // Vérifier que le commentaire appartient à l'utilisateur connecté
            $commentData = $this->model->getCommentById($commentId);
            if (!$commentData || $commentData['created_by'] != $userId) {
                $_SESSION['error'] = 'Vous n\'êtes pas autorisé à modifier ce commentaire.';
                header('Location: ' . BASE_URL . 'interventions_client/view/' . $this->getInterventionIdFromComment($commentId));
                exit;
            }

            $success = $this->model->updateComment($commentId, $comment);

            if ($success) {
                $_SESSION['success'] = 'Commentaire modifié avec succès.';
            } else {
                custom_log("Échec de la modification du commentaire ID {$commentId} par l'utilisateur " . ($_SESSION['user']['id'] ?? 'unknown'), 'ERROR');
                $_SESSION['error'] = 'Erreur lors de la modification du commentaire. Veuillez vérifier les logs pour plus de détails.';
            }
        }

        header('Location: ' . BASE_URL . 'interventions_client/view/' . $this->getInterventionIdFromComment($commentId));
        exit;
    }

    /**
     * Supprimer un commentaire
     */
    public function deleteComment($commentId)
    {
        if (!hasPermission('client_view_interventions')) {
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        $userId = $_SESSION['user']['id'];

        // Vérifier que le commentaire appartient à l'utilisateur connecté
        $commentData = $this->model->getCommentById($commentId);
        if (!$commentData || $commentData['created_by'] != $userId) {
            $_SESSION['error'] = 'Vous n\'êtes pas autorisé à supprimer ce commentaire.';
            $interventionId = $commentData ? $commentData['intervention_id'] : 0;
            header('Location: ' . BASE_URL . 'interventions_client/view/' . $interventionId);
            exit;
        }

        // Récupérer l'ID de l'intervention avant de supprimer le commentaire
        $interventionId = $commentData['intervention_id'];

        $success = $this->model->deleteComment($commentId);

        if ($success) {
            $_SESSION['success'] = 'Commentaire supprimé avec succès.';
        } else {
            $_SESSION['error'] = 'Erreur lors de la suppression du commentaire.';
        }

        header('Location: ' . BASE_URL . 'interventions_client/view/' . $interventionId);
        exit;
    }

    /**
     * Obtenir l'ID de l'intervention à partir de l'ID du commentaire
     */
    private function getInterventionIdFromComment($commentId)
    {
        $comment = $this->model->getCommentById($commentId);
        return $comment ? $comment['intervention_id'] : 0;
    }

    /**
     * Ajouter une pièce jointe
     */
    public function addAttachment($interventionId)
    {
        if (!hasPermission('client_view_interventions')) {
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // Vérifier que l'intervention appartient aux locations autorisées du client
                $userLocations = getUserLocations();
                $intervention = $this->model->getByIdWithAccess($interventionId, $userLocations);

                if (!$intervention) {
                    throw new Exception('Intervention non trouvée ou non autorisée.');
                }

                if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Erreur lors du téléchargement du fichier.');
                }

                // Utiliser AttachmentService pour gérer l'upload
                $attachmentService = new AttachmentService($this->db);

                // Préparer les options
                $customName = isset($_POST['custom_name']) && !empty(trim($_POST['custom_name']))
                    ? trim($_POST['custom_name'])
                    : null;

                $options = [
                    'custom_names' => [$customName]
                ];

                // Upload du fichier
                $result = $attachmentService->upload(
                    AttachmentService::TYPE_INTERVENTION,
                    $interventionId,
                    $_FILES['attachment'],
                    $options,
                    $_SESSION['user']['id']
                );

                if ($result['success']) {
                    $_SESSION['success'] = 'Pièce jointe ajoutée avec succès.';
                } else {
                    $errorMessage = !empty($result['errors']) ? implode(', ', $result['errors']) : 'Erreur lors de l\'ajout de la pièce jointe.';
                    throw new Exception($errorMessage);
                }

            } catch (Exception $e) {
                custom_log("Erreur lors de l'ajout de la pièce jointe : " . $e->getMessage(), 'ERROR');
                $_SESSION['error'] = $e->getMessage();
            }
        }

        header('Location: ' . BASE_URL . 'interventions_client/view/' . $interventionId);
        exit;
    }

    /**
     * Ajouter plusieurs pièces jointes (Drag & Drop)
     */
    public function addMultipleAttachments($interventionId)
    {
        if (!hasPermission('client_view_interventions')) {
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
            // Vérifier que l'intervention appartient aux locations autorisées du client
            $userLocations = getUserLocations();
            $intervention = $this->model->getByIdWithAccess($interventionId, $userLocations);

            if (!$intervention) {
                throw new Exception("Intervention non trouvée ou non autorisée");
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

            // Retourner le résultat
            header('Content-Type: application/json');
            if ($result['success']) {
                $message = count($result['uploaded_files']) . " fichier(s) uploadé(s) avec succès";
                if (!empty($result['errors'])) {
                    $message .= ". " . count($result['errors']) . " erreur(s) : " . implode(', ', $result['errors']);
                }

                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'uploaded_files' => $result['uploaded_files'],
                    'errors' => $result['errors']
                ]);
            } else {
                throw new Exception("Aucun fichier n'a pu être uploadé. " . implode(', ', $result['errors']));
            }

        } catch (Exception $e) {
            custom_log("Erreur dans InterventionsClientController::addMultipleAttachments : " . $e->getMessage(), 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Supprimer une pièce jointe
     */
    public function deleteAttachment($attachmentId)
    {
        if (!hasPermission('client_view_interventions')) {
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        try {
            $userId = $_SESSION['user']['id'];

            // Vérifier que la pièce jointe appartient à l'utilisateur connecté
            $attachmentService = new AttachmentService($this->db);
            $attachmentData = $attachmentService->getAttachmentById($attachmentId);

            if (!$attachmentData || $attachmentData['created_by'] != $userId) {
                throw new Exception('Vous n\'êtes pas autorisé à supprimer cette pièce jointe.');
            }

            // Récupérer l'ID de l'intervention pour la redirection
            $interventionId = $attachmentData['entite_id'] ?? $this->getInterventionIdFromAttachment($attachmentId);

            // Utiliser AttachmentService pour gérer la suppression
            $attachmentService->delete($attachmentId, AttachmentService::TYPE_INTERVENTION, $interventionId);

            $_SESSION['success'] = 'Pièce jointe supprimée avec succès.';

        } catch (Exception $e) {
            custom_log("Erreur lors de la suppression de la pièce jointe : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . 'interventions_client/view/' . ($interventionId ?? ''));
        exit;
    }

    /**
     * Télécharge une pièce jointe (client)
     */
    public function download($attachmentId)
    {
        if (!hasPermission('client_view_interventions')) {
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        try {
            $userId = $_SESSION['user']['id'];
            $userLocations = getUserLocations();

            // Récupérer la pièce jointe
            $attachmentService = new AttachmentService($this->db);
            $attachmentData = $attachmentService->getAttachmentById($attachmentId);

            if (!$attachmentData) {
                throw new Exception('Pièce jointe non trouvée.');
            }

            // Vérifier que la pièce jointe est visible par le client
            if (isset($attachmentData['masque_client']) && $attachmentData['masque_client'] == 1) {
                throw new Exception('Cette pièce jointe n\'est pas accessible.');
            }

            // Vérifier que l'intervention appartient aux locations autorisées du client
            $interventionId = $attachmentData['entite_id'] ?? null;
            if (!$interventionId) {
                throw new Exception('Impossible de déterminer l\'intervention associée.');
            }

            $intervention = $this->model->getByIdWithAccess($interventionId, $userLocations);

            if (!$intervention) {
                throw new Exception('Intervention non trouvée ou non autorisée.');
            }

            // Utiliser AttachmentService pour gérer le téléchargement
            $attachmentService->download($attachmentId, true);

        } catch (Exception $e) {
            custom_log("Erreur lors du téléchargement de la pièce jointe (client) : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors du téléchargement : " . $e->getMessage();
            $interventionId = $attachmentData['entite_id'] ?? 0;
            header('Location: ' . BASE_URL . 'interventions_client/view/' . $interventionId);
            exit;
        }
    }

    /**
     * Affiche l'aperçu d'une pièce jointe (client)
     */
    public function preview($attachmentId)
    {
        if (!hasPermission('client_view_interventions')) {
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        try {
            $userId = $_SESSION['user']['id'];
            $userLocations = getUserLocations();

            // Récupérer la pièce jointe
            $attachmentService = new AttachmentService($this->db);
            $attachmentData = $attachmentService->getAttachmentById($attachmentId);

            if (!$attachmentData) {
                throw new Exception('Pièce jointe non trouvée.');
            }

            // Vérifier que la pièce jointe est visible par le client
            if (isset($attachmentData['masque_client']) && $attachmentData['masque_client'] == 1) {
                throw new Exception('Cette pièce jointe n\'est pas accessible.');
            }

            // Vérifier que l'intervention appartient aux locations autorisées du client
            $interventionId = $attachmentData['entite_id'] ?? null;
            if (!$interventionId) {
                throw new Exception('Impossible de déterminer l\'intervention associée.');
            }

            $intervention = $this->model->getByIdWithAccess($interventionId, $userLocations);

            if (!$intervention) {
                throw new Exception('Intervention non trouvée ou non autorisée.');
            }

            // Utiliser AttachmentService pour gérer l'aperçu
            $attachmentService->preview($attachmentId);

        } catch (Exception $e) {
            custom_log("Erreur lors de l'aperçu de la pièce jointe (client) : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de l'aperçu : " . $e->getMessage();
            $interventionId = $attachmentData['entite_id'] ?? 0;
            header('Location: ' . BASE_URL . 'interventions_client/view/' . $interventionId);
            exit;
        }
    }

    /**
     * Obtenir l'ID de l'intervention à partir de l'ID de la pièce jointe
     */
    private function getInterventionIdFromAttachment($attachmentId)
    {
        $attachment = $this->model->getAttachmentById($attachmentId);
        return $attachment ? $attachment['intervention_id'] : 0;
    }

    /**
     * Affiche le formulaire de création d'intervention pour les clients
     */
    public function add()
    {
        // Vérifier si l'utilisateur est connecté et est un client
        if (!isset($_SESSION['user']) || !isClient()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Vérifier la permission spécifique pour créer des interventions
        if (!hasPermission('client_add_intervention')) {
            $_SESSION['error'] = "Vous n'avez pas la permission de créer des interventions";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Récupérer l'ID du client depuis la session
        $clientId = $_SESSION['user']['client_id'] ?? null;

        if (!$clientId) {
            $_SESSION['error'] = "Aucun client associé à votre compte";
            header('Location: ' . BASE_URL . 'auth/logout');
            exit;
        }

        // Récupérer les localisations autorisées
        $userLocations = getUserLocations();

        // Si l'utilisateur n'a pas de localisations définies, utiliser le client_id par défaut
        if (empty($userLocations)) {
            $userLocations = [['client_id' => $clientId, 'site_id' => null, 'building_id' => null, 'room_id' => null]];
        }

        // Récupérer les données nécessaires pour le formulaire
        $sites = $this->model->getSitesByLocations($userLocations);
        $buildings = [];
        $rooms = [];
        $contracts = $this->model->getContractsByClient($clientId);
        $contacts = $this->model->getContactsByClient($clientId);

        // Récupérer les statuts et priorités par défaut
        $statuses = $this->model->getAllStatuses();
        $priorities = $this->model->getAllPriorities();

        // Trouver les IDs par défaut
        $defaultStatusId = null;
        $defaultPriorityId = null;

        foreach ($statuses as $status) {
            if (strtolower($status['name']) === 'nouveau') {
                $defaultStatusId = $status['id'];
                break;
            }
        }

        foreach ($priorities as $priority) {
            if (strtolower($priority['name']) === 'normale') {
                $defaultPriorityId = $priority['id'];
                break;
            }
        }

        // Charger la vue
        require_once __DIR__ . '/../views/interventions_client/add.php';
    }

    /**
     * Traite la soumission du formulaire de création d'intervention
     */
    public function store()
    {
        // Vérifier si l'utilisateur est connecté et est un client
        if (!isset($_SESSION['user']) || !isClient()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Vérifier la permission spécifique pour créer des interventions
        if (!hasPermission('client_add_intervention')) {
            $_SESSION['error'] = "Vous n'avez pas la permission de créer des interventions";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'interventions_client/add');
            exit;
        }

        // Récupérer l'ID du client depuis la session
        $clientId = $_SESSION['user']['client_id'] ?? null;

        if (!$clientId) {
            $_SESSION['error'] = "Aucun client associé à votre compte";
            header('Location: ' . BASE_URL . 'auth/logout');
            exit;
        }

        // Récupérer les statuts et priorités pour définir les valeurs par défaut
        $statuses = $this->model->getAllStatuses();
        $priorities = $this->model->getAllPriorities();

        // Trouver les IDs par défaut
        $defaultStatusId = null;
        $defaultPriorityId = null;

        foreach ($statuses as $status) {
            if (strtolower($status['name']) === 'nouveau') {
                $defaultStatusId = $status['id'];
                break;
            }
        }

        foreach ($priorities as $priority) {
            if (strtolower($priority['name']) === 'normale') {
                $defaultPriorityId = $priority['id'];
                break;
            }
        }

        // Récupérer et valider les données du formulaire
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'demande_par' => null,
            'client_id' => $clientId,
            'site_id' => !empty($_POST['site_id']) ? (int) $_POST['site_id'] : null,
            'building_id' => !empty($_POST['building_id']) ? (int) $_POST['building_id'] : null,
            'room_id' => !empty($_POST['room_id']) ? (int) $_POST['room_id'] : null,
            'contract_id' => !empty($_POST['contract_id']) ? (int) $_POST['contract_id'] : null,
            'status_id' => !empty($_POST['status_id']) ? (int) $_POST['status_id'] : $defaultStatusId,
            'priority_id' => !empty($_POST['priority_id']) ? (int) $_POST['priority_id'] : $defaultPriorityId,
            'ref_client' => trim($_POST['ref_client'] ?? ''),
            'contact_client' => trim($_POST['contact_client'] ?? ''),
            'duration' => 0,
            'type_id' => 1,
        ];

        // Validation des champs obligatoires
        $errors = [];

        if (empty($data['title'])) {
            $errors[] = 'Le titre est obligatoire';
        }

        if (empty($data['description'])) {
            $errors[] = 'La description est obligatoire';
        }

        if (empty($data['status_id'])) {
            $errors[] = 'Le statut est obligatoire';
        }

        if (empty($data['priority_id'])) {
            $errors[] = 'La priorité est obligatoire';
        }

        // Validation de l'email si fourni
        if (!empty($data['contact_client']) && !filter_var($data['contact_client'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adresse email du contact client n\'est pas valide';
        }

        // Si il y a des erreurs, rediriger vers le formulaire
        if (!empty($errors)) {
            $_SESSION['error'] = implode('<br>', $errors);
            $_SESSION['form_data'] = $data;
            header('Location: ' . BASE_URL . 'interventions_client/add');
            exit;
        }

        // Vérifier que l'utilisateur a accès aux localisations sélectionnées
        $userLocations = getUserLocations();
        $hasAccess = false;

        foreach ($userLocations as $location) {
            if ($location['client_id'] == $clientId) {
                if ($location['site_id'] === null || $location['site_id'] == $data['site_id']) {
                    if ($location['building_id'] === null || $location['building_id'] == $data['building_id']) {
                        if ($location['room_id'] === null || $location['room_id'] == $data['room_id']) {
                            $hasAccess = true;
                            break;
                        }
                    }
                }
            }
        }

        if (!$hasAccess) {
            $_SESSION['error'] = "Vous n'avez pas accès aux localisations sélectionnées";
            header('Location: ' . BASE_URL . 'interventions_client/add');
            exit;
        }

        // Log des données pour débogage
        custom_log("Données d'intervention à créer: " . json_encode($data), 'DEBUG');

        // Créer l'intervention
        $interventionId = $this->model->create($data);

        if ($interventionId) {
            custom_log("Intervention créée avec succès, ID: " . $interventionId, 'INFO');
            $_SESSION['success'] = 'Intervention créée avec succès';
            header('Location: ' . BASE_URL . 'interventions_client/view/' . $interventionId);
        } else {
            custom_log("Échec de la création de l'intervention", 'ERROR');
            $_SESSION['error'] = 'Erreur lors de la création de l\'intervention';
            header('Location: ' . BASE_URL . 'interventions_client/add');
        }
        exit;
    }

    /**
     * Récupère les bâtiments d'un site via AJAX
     */
    public function ajaxGetBuildings()
    {
        // Nettoyer les buffers de sortie
        if (ob_get_level()) {
            ob_clean();
        }

        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Non autorisé']);
            exit;
        }

        $siteId = $_GET['site_id'] ?? null;
        if (!$siteId) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ID du site manquant']);
            exit;
        }

        try {
            // Récupérer les bâtiments
            $sql = "SELECT b.id, b.name 
                FROM buildings b
                WHERE b.site_id = :site_id
                ORDER BY b.name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':site_id' => $siteId]);
            $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // S'assurer qu'il n'y a rien avant l'envoi
            if (ob_get_level()) {
                ob_clean();
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'buildings' => $buildings]);

        } catch (Exception $e) {
            custom_log("Erreur ajaxGetBuildings: " . $e->getMessage(), 'ERROR');

            if (ob_get_level()) {
                ob_clean();
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Erreur lors du chargement des bâtiments']);
        }
        exit;
    }

    /**
     * Récupère les salles d'un bâtiment via AJAX
     */
    public function ajaxGetRooms()
    {
        // Nettoyer les buffers de sortie
        if (ob_get_level()) {
            ob_clean();
        }

        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Non autorisé']);
            exit;
        }

        $buildingId = $_GET['building_id'] ?? null;
        if (!$buildingId) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ID du bâtiment manquant']);
            exit;
        }

        try {
            // Récupérer les salles
            $sql = "SELECT r.id, r.name 
                FROM rooms r
                WHERE r.building_id = :building_id
                ORDER BY r.name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':building_id' => $buildingId]);
            $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // S'assurer qu'il n'y a rien avant l'envoi
            if (ob_get_level()) {
                ob_clean();
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'rooms' => $rooms]);

        } catch (Exception $e) {
            custom_log("Erreur ajaxGetRooms: " . $e->getMessage(), 'ERROR');

            if (ob_get_level()) {
                ob_clean();
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Erreur lors du chargement des salles']);
        }
        exit;
    }
    /**
     * Récupère le contrat associé à une salle (avec vérification d'accès client)
     */
    public function getContractByRoom($roomId)
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user']) || !isClient()) {
            http_response_code(401);
            echo json_encode(['error' => 'Non autorisé']);
            exit;
        }

        if (!hasPermission('client_add_intervention')) {
            http_response_code(403);
            echo json_encode(['error' => 'Non autorisé']);
            exit;
        }

        try {
            $clientId = $_SESSION['user']['client_id'] ?? null;
            $userLocations = getUserLocations();

            if (empty($userLocations)) {
                $userLocations = [['client_id' => $clientId, 'site_id' => null, 'building_id' => null, 'room_id' => null]];
            }

            $contract = $this->model->getContractByRoomAndLocations($roomId, $userLocations);

            echo json_encode($contract ?: null);
        } catch (Exception $e) {
            custom_log("Erreur getContractByRoom (client): " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(['error' => 'Erreur serveur']);
        }
        exit;
    }
    /**
     * Exporte les interventions du client dans un vrai fichier XLSX.
     *
     * Important : PhpSpreadsheet doit être installé dans le projet avec Composer :
     * composer require phpoffice/phpspreadsheet
     *
     * Le fichier XLSX permet d'enregistrer réellement les largeurs de colonnes
     * et le retour automatique à la ligne, sans avertissement Excel.
     */
    public function export()
    {
        custom_log("EXPORT DEBUG - Méthode export() (téléchargement XLSX) appelée", 'DEBUG');

        if (!isset($_SESSION['user']) || !isClient()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        if (!hasPermission('client_view_interventions')) {
            $_SESSION['error'] = "Vous n'avez pas la permission d'accéder aux interventions";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        $clientId = $_SESSION['user']['client_id'] ?? null;
        if (!$clientId) {
            $_SESSION['error'] = "Aucun client associé à votre compte";
            header('Location: ' . BASE_URL . 'auth/logout');
            exit;
        }

        $userLocations = getUserLocations();
        if (empty($userLocations)) {
            $userLocations = [
                [
                    'client_id' => $clientId,
                    'site_id' => null,
                    'building_id' => null,
                    'room_id' => null
                ]
            ];
        }

        $availableColumns = [
            'reference' => 'Reference',
            'title' => 'Titre',
            'site_name' => 'Site',
            'building_name' => 'Batiment',
            'room_name' => 'Salle',
            'status_name' => 'Statut',
            'priority_name' => 'Priorite',
            'type_label' => 'Type',
            'technicians_names' => 'Technicien(s)',
            'date_planif' => 'Date planifiee',
            'created_at' => 'Date de creation',
            'closed_at' => 'Date de cloture',
            'description' => 'Description',
            'ref_client' => 'Reference client',
        ];

        $requestedColumns = $_GET['columns'] ?? array_keys($availableColumns);
        if (!is_array($requestedColumns)) {
            $requestedColumns = [$requestedColumns];
        }

        $selectedColumns = array_values(array_intersect(
            $requestedColumns,
            array_keys($availableColumns)
        ));

        if (empty($selectedColumns)) {
            $selectedColumns = array_keys($availableColumns);
        }

        // Validation basique des dates (format YYYY-MM-DD)
        $dateStart = (!empty($_GET['date_start']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_start']))
            ? $_GET['date_start'] : null;
        $dateEnd = (!empty($_GET['date_end']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_end']))
            ? $_GET['date_end'] : null;

        $type = $_GET['type'] ?? 'all';
        if (!in_array($type, ['all', 'curative', 'preventive'], true)) {
            $type = 'all';
        }

        $filters = $this->buildExportFilters();

        try {
            $interventions = $this->model->getForExport($userLocations, $filters);
        } catch (Exception $e) {
            custom_log("Erreur lors de l'export des interventions (client): " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de la génération de l'export.";
            header('Location: ' . BASE_URL . 'interventions_client');
            exit;
        }

        // Préparation des données exportées.
        $rows = [];

        foreach ($interventions as $intervention) {
            $row = [];

            foreach ($selectedColumns as $col) {
                switch ($col) {
                    case 'type_label':
                        $value = !empty($intervention['is_preventive'])
                            ? 'Preventive'
                            : 'Curative';
                        break;

                    case 'date_planif':
                        $value = !empty($intervention['date_planif'])
                            ? date('d/m/Y', strtotime($intervention['date_planif']))
                            : '';
                        break;

                    case 'created_at':
                        $value = !empty($intervention['created_at'])
                            ? date('d/m/Y H:i', strtotime($intervention['created_at']))
                            : '';
                        break;

                    case 'closed_at':
                        $value = !empty($intervention['closed_at'])
                            ? date('d/m/Y H:i', strtotime($intervention['closed_at']))
                            : '';
                        break;

                    default:
                        $value = $intervention[$col] ?? '';
                        break;
                }

                // Normalise les retours à la ligne déjà présents.
                $row[] = str_replace(["\r\n", "\r"], "\n", (string) $value);
            }

            $rows[] = $row;
        }

        // Charger l'autoloader Composer du projet.
        $autoloadPath = __DIR__ . '/../vendor/autoload.php';

        if (!is_file($autoloadPath)) {
            throw new RuntimeException(
                'PhpSpreadsheet est absent. Lancez « composer require phpoffice/phpspreadsheet » dans le dossier public. '
                . 'Chemin recherché : ' . $autoloadPath
            );
        }

        require_once $autoloadPath;

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Interventions');

        // En-têtes.
        $headers = array_map(
            static fn(string $columnKey): string => $availableColumns[$columnKey],
            $selectedColumns
        );
        $worksheet->fromArray($headers, null, 'A1');

        // Données à partir de la ligne 2.
        if (!empty($rows)) {
            $worksheet->fromArray($rows, null, 'A2');
        }

        $columnCount = count($selectedColumns);
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);
        $lastRow = max(1, count($rows) + 1);

        // Style de l'en-tête.
        $worksheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E78'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        // Bordures, alignement vertical et retour à la ligne de toutes les cellules.
        $worksheet->getStyle('A1:' . $lastColumn . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'B7B7B7'],
                ],
            ],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
                'wrapText' => true,
            ],
        ]);

        // Ajustement automatique de chaque colonne selon son contenu.
        for ($columnIndex = 1; $columnIndex <= $columnCount; $columnIndex++) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex);
            $worksheet->getColumnDimension($columnLetter)->setAutoSize(true);
        }

        // Limite de sécurité pour éviter une colonne Description trop large.
        $descriptionIndex = array_search('description', $selectedColumns, true);
        if ($descriptionIndex !== false) {
            $descriptionColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($descriptionIndex + 1);
            $worksheet->getColumnDimension($descriptionColumn)->setAutoSize(false);
            $worksheet->getColumnDimension($descriptionColumn)->setWidth(45);
        }

        $worksheet->freezePane('A2');
        $worksheet->setAutoFilter('A1:' . $lastColumn . $lastRow);

        $filename = 'interventions_export_' . date('Y-m-d_His') . '.xlsx';

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }
    /**
     * Retourne en JSON les interventions filtrées, pour aperçu dans le tableau avant export
     */
    public function previewExport()
    {
        custom_log("EXPORT DEBUG - Méthode previewExport() (apercu tableau) appelée", 'DEBUG');
        header('Content-Type: application/json');
        if (!isset($_SESSION['user']) || !isClient()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Non autorisé']);
            exit;
        }

        if (!hasPermission('client_view_interventions')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Non autorisé']);
            exit;
        }

        $clientId = $_SESSION['user']['client_id'] ?? null;
        if (!$clientId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Aucun client associé']);
            exit;
        }

        $userLocations = getUserLocations();
        if (empty($userLocations)) {
            $userLocations = [['client_id' => $clientId, 'site_id' => null, 'building_id' => null, 'room_id' => null]];
        }

        $dateStart = (!empty($_GET['date_start']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_start']))
            ? $_GET['date_start'] : null;
        $dateEnd = (!empty($_GET['date_end']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_end']))
            ? $_GET['date_end'] : null;

        $type = $_GET['type'] ?? 'all';
        if (!in_array($type, ['all', 'curative', 'preventive'], true)) {
            $type = 'all';
        }

        $filters = $this->buildExportFilters();

        try {
            $interventions = $this->model->getForExport($userLocations, $filters);

            $formatted = array_map(function ($i) {
                return [
                    'id' => $i['id'],
                    'reference' => $i['reference'],
                    'title' => $i['title'],
                    'client_name' => $i['client_name'],
                    'site_name' => $i['site_name'],
                    'building_name' => $i['building_name'],
                    'room_name' => $i['room_name'],
                    'status_id' => $i['status_id'],
                    'status_name' => $i['status_name'],
                    'status_color' => $i['status_color'] ?? '',
                    'priority_id' => $i['priority_id'],
                    'priority_name' => $i['priority_name'],
                    'priority_color' => $i['priority_color'] ?? '',
                    'technicians_names' => $i['technicians_names'],
                    'date_planif_formatted' => !empty($i['date_planif']) ? date('d/m/Y', strtotime($i['date_planif'])) : '-',
                    'created_at_formatted' => !empty($i['created_at']) ? date('d/m/Y H:i', strtotime($i['created_at'])) : '',
                ];
            }, $interventions);

            echo json_encode(['success' => true, 'interventions' => $formatted, 'count' => count($formatted)]);
        } catch (Exception $e) {
            custom_log("Erreur previewExport (client): " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        }
        exit;
    }
    public function getForExport($userLocations, $filters = [])
    {
        $clientIds = [];
        foreach ($userLocations as $location) {
            if (isset($location['client_id']) && !in_array($location['client_id'], $clientIds)) {
                $clientIds[] = (int) $location['client_id'];
            }
        }

        if (empty($clientIds)) {
            return [];
        }

        $placeholders = str_repeat('?,', count($clientIds) - 1) . '?';

        $sql = "SELECT i.*,
    c.name as client_name,
    s.name as site_name,
    b.name as building_name,
    r.name as room_name,
    its.name as status_name,
    its.color as status_color,
    ip.name as priority_name,
    ip.color as priority_color,
    GROUP_CONCAT(DISTINCT CONCAT(ut.first_name, ' ', ut.last_name) ORDER BY ut.first_name SEPARATOR ', ') as technicians_names
    FROM " . $this->table . " i
    LEFT JOIN clients c ON i.client_id = c.id
    LEFT JOIN sites s ON i.site_id = s.id
    LEFT JOIN buildings b ON i.building_id = b.id
    LEFT JOIN rooms r ON i.room_id = r.id
    LEFT JOIN intervention_techniciens itech ON i.id = itech.intervention_id
    LEFT JOIN users ut ON itech.technicien_id = ut.id
    LEFT JOIN intervention_statuses its ON i.status_id = its.id
    LEFT JOIN intervention_priorities ip ON i.priority_id = ip.id
    WHERE i.client_id IN ({$placeholders})";

        $params = $clientIds;

        if (!empty($filters['site_id'])) {
            $sql .= " AND i.site_id = ?";
            $params[] = $filters['site_id'];
        }
        if (!empty($filters['building_id'])) {
            $sql .= " AND i.building_id = ?";
            $params[] = $filters['building_id'];
        }
        if (!empty($filters['room_id'])) {
            $sql .= " AND i.room_id = ?";
            $params[] = $filters['room_id'];
        }
        if (!empty($filters['status_id'])) {
            $sql .= " AND i.status_id = ?";
            $params[] = $filters['status_id'];
        } elseif (!empty($filters['exclude_status_ids'])) {
            $excludePlaceholders = str_repeat('?,', count($filters['exclude_status_ids']) - 1) . '?';
            $sql .= " AND i.status_id NOT IN ($excludePlaceholders)";
            $params = array_merge($params, $filters['exclude_status_ids']);
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (i.title LIKE ? OR s.name LIKE ? OR b.name LIKE ? OR r.name LIKE ? OR i.reference LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        if (!empty($filters['date_start'])) {
            $sql .= " AND i.created_at >= ?";
            $params[] = $filters['date_start'] . ' 00:00:00';
        }

        if (!empty($filters['date_end'])) {
            $sql .= " AND i.created_at <= ?";
            $params[] = $filters['date_end'] . ' 23:59:59';
        }

        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $sql .= " AND i.is_preventive = ?";
            $params[] = ($filters['type'] === 'preventive') ? 1 : 0;
        }

        $sql .= " GROUP BY i.id ORDER BY i.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    private function buildExportFilters()
    {
        $dateStart = (!empty($_GET['date_start']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_start']))
            ? $_GET['date_start'] : null;
        $dateEnd = (!empty($_GET['date_end']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_end']))
            ? $_GET['date_end'] : null;

        $type = $_GET['type'] ?? 'all';
        if (!in_array($type, ['all', 'curative', 'preventive'], true)) {
            $type = 'all';
        }

        $siteId = !empty($_GET['site_id']) ? (int) $_GET['site_id'] : null;
        $buildingId = !empty($_GET['building_id']) ? (int) $_GET['building_id'] : null;
        $roomId = !empty($_GET['room_id']) ? (int) $_GET['room_id'] : null;
        $statusId = !empty($_GET['status_id']) ? (int) $_GET['status_id'] : null;
        $search = !empty($_GET['search']) ? trim($_GET['search']) : null;

        $filters = [
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'type' => $type,
            'site_id' => $siteId,
            'building_id' => $buildingId,
            'room_id' => $roomId,
            'status_id' => $statusId,
            'search' => $search,
        ];

        // Même comportement par défaut que la liste : masquer Fermé/Annulé si aucun statut choisi
        if (empty($statusId)) {
            $filters['exclude_status_ids'] = [6, 7];
        }

        return $filters;
    }
}