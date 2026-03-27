<?php
/**
 * Contrôleur pour la gestion des interventions clients
 * Filtre automatiquement selon les localisations autorisées du client
 */
require_once __DIR__ . '/../classes/Services/AttachmentService.php';

class InterventionsClientController {
    private $db;
    private $model;
    private $clientModel;
    private $siteModel;
    private $roomModel;

    public function __construct($db) {
        $this->db = $db;
        
        // Charger les modèles nécessaires
        require_once __DIR__ . '/../models/InterventionsClientModel.php';
        require_once __DIR__ . '/../models/ClientModel.php';
        require_once __DIR__ . '/../models/SiteModel.php';
        require_once __DIR__ . '/../models/RoomModel.php';
        
        $this->model = new InterventionsClientModel($db);
        $this->clientModel = new ClientModel($db);
        $this->siteModel = new SiteModel($db);
        $this->roomModel = new RoomModel($db);
    }

    /**
     * Affiche la liste des interventions du client
     */
    public function index() {
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
            $userLocations = [['client_id' => $clientId, 'site_id' => null, 'room_id' => null]];
        }

        // Récupérer les filtres depuis l'URL
        $filters = [
            'site_id' => $_GET['site_id'] ?? null,
            'room_id' => $_GET['room_id'] ?? null,
            'status_id' => $_GET['status_id'] ?? null,
            'search' => $_GET['search'] ?? null
        ];
        
        // Si aucun filtre de statut n'est spécifié, filtrer par défaut sur les interventions non fermées ou annulées
        if (empty($filters['status_id'])) {
            $filters['exclude_status_ids'] = [6, 7]; // 6 = Fermé, 7 = Annulé
        }

        // Construire la clause WHERE pour les localisations
        $locationWhere = buildLocationWhereClause($userLocations, 'i.client_id', 'i.site_id', 'i.room_id');
        
        // Récupérer les interventions filtrées selon les localisations
        $interventions = $this->model->getAllByLocations($userLocations, $filters);
        
        // Récupérer les données pour les filtres
        $sites = $this->model->getSitesByLocations($userLocations);
        $rooms = !empty($filters['site_id']) ? $this->model->getRoomsBySiteAndLocations($filters['site_id'], $userLocations) : [];
        
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
    public function view($id) {
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
            $userLocations = [['client_id' => $clientId, 'site_id' => null, 'room_id' => null]];
        }

        // Log pour débogage
        custom_log("Tentative d'accès à l'intervention ID: $id", 'DEBUG');
        custom_log("Localisations utilisateur: " . json_encode($userLocations), 'DEBUG');

        // Récupérer l'intervention
        $intervention = $this->model->getByIdWithAccess($id, $userLocations);
        
        if (!$intervention) {
            custom_log("Intervention non trouvée ou non autorisée pour l'ID: $id", 'ERROR');
            $_SESSION['error'] = "Intervention non trouvée ou non autorisée";
            header('Location: ' . BASE_URL . 'interventions_client');
            exit;
        }

        custom_log("Intervention trouvée: " . json_encode($intervention), 'DEBUG');

        // Si getByIdWithAccess() retourne l'intervention, c'est que l'utilisateur y a déjà accès
        // Pas besoin de double vérification

        // Récupérer le contrat associé directement via contract_id pour les informations de tickets
        $contract = null;
        if (!empty($intervention['contract_id'])) {
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

        // Charger la vue
        require_once __DIR__ . '/../views/interventions_client/view.php';
    }

    /**
     * Récupère les salles d'un site selon les localisations autorisées
     */
    public function getRoomsBySiteAndLocations($siteId, $userLocations) {
        return $this->model->getRoomsBySiteAndLocations($siteId, $userLocations);
    }

    /**
     * Ajouter un commentaire
     */
    public function addComment($interventionId) {
        if (!hasPermission('client_view_interventions')) {
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $comment = trim($_POST['comment'] ?? '');
            
            if (empty($comment)) {
                $_SESSION['error'] = 'Le commentaire ne peut pas etre vide.';
                header('Location: ' . BASE_URL . 'interventions_client/view/' . $interventionId);
                exit;
            }

            // Verifier que l'intervention appartient aux locations autorisees du client
            $userLocations = getUserLocations();
            $intervention = $this->model->getByIdWithAccess($interventionId, $userLocations);
            
            if (!$intervention) {
                $_SESSION['error'] = 'Intervention non trouvee ou non autorisee.';
                header('Location: ' . BASE_URL . 'interventions_client');
                exit;
            }

            $userId = $_SESSION['user']['id'];
            $success = $this->model->addComment($interventionId, $userId, $comment, true);

            if ($success) {
                $_SESSION['success'] = 'Commentaire ajoute avec succes.';
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
    public function editComment($commentId) {
        if (!hasPermission('client_view_interventions')) {
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $comment = trim($_POST['comment'] ?? '');
            
            if (empty($comment)) {
                $_SESSION['error'] = 'Le commentaire ne peut pas etre vide.';
                header('Location: ' . BASE_URL . 'interventions_client/view/' . $this->getInterventionIdFromComment($commentId));
                exit;
            }

            $userId = $_SESSION['user']['id'];
            
            // Verifier que le commentaire appartient a l'utilisateur connecte
            $commentData = $this->model->getCommentById($commentId);
            if (!$commentData || $commentData['created_by'] != $userId) {
                $_SESSION['error'] = 'Vous n\'etes pas autorise a modifier ce commentaire.';
                header('Location: ' . BASE_URL . 'interventions_client/view/' . $this->getInterventionIdFromComment($commentId));
                exit;
            }

            $success = $this->model->updateComment($commentId, $comment);

            if ($success) {
                $_SESSION['success'] = 'Commentaire modifie avec succes.';
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
    public function deleteComment($commentId) {
        if (!hasPermission('client_view_interventions')) {
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        $userId = $_SESSION['user']['id'];
        
        // Verifier que le commentaire appartient a l'utilisateur connecte
        // IMPORTANT: Récupérer l'ID de l'intervention AVANT de supprimer le commentaire
        $commentData = $this->model->getCommentById($commentId);
        if (!$commentData || $commentData['created_by'] != $userId) {
            $_SESSION['error'] = 'Vous n\'etes pas autorise a supprimer ce commentaire.';
            $interventionId = $commentData ? $commentData['intervention_id'] : 0;
            header('Location: ' . BASE_URL . 'interventions_client/view/' . $interventionId);
            exit;
        }

        // Récupérer l'ID de l'intervention avant de supprimer le commentaire
        $interventionId = $commentData['intervention_id'];

        $success = $this->model->deleteComment($commentId);

        if ($success) {
            $_SESSION['success'] = 'Commentaire supprime avec succes.';
        } else {
            $_SESSION['error'] = 'Erreur lors de la suppression du commentaire.';
        }

        header('Location: ' . BASE_URL . 'interventions_client/view/' . $interventionId);
        exit;
    }

    /**
     * Obtenir l'ID de l'intervention a partir de l'ID du commentaire
     */
    private function getInterventionIdFromComment($commentId) {
        $comment = $this->model->getCommentById($commentId);
        return $comment ? $comment['intervention_id'] : 0;
    }

    /**
     * Ajouter une piece jointe
     */
    /**
     * Ajoute une pièce jointe à une intervention (client)
     * Utilise AttachmentService pour centraliser la logique
     */
    public function addAttachment($interventionId) {
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
     * Ajouter plusieurs pieces jointes (Drag & Drop)
     */
    public function addMultipleAttachments($interventionId) {
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
     * Supprimer une piece jointe
     */
    /**
     * Supprime une pièce jointe (client)
     * Utilise AttachmentService pour centraliser la logique
     */
    public function deleteAttachment($attachmentId) {
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
     * Utilise AttachmentService pour centraliser la logique
     */
    public function download($attachmentId) {
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
     * Utilise AttachmentService pour centraliser la logique
     */
    public function preview($attachmentId) {
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
     * Obtenir l'ID de l'intervention a partir de l'ID de la piece jointe
     */
    private function getInterventionIdFromAttachment($attachmentId) {
        $attachment = $this->model->getAttachmentById($attachmentId);
        return $attachment ? $attachment['intervention_id'] : 0;
    }

    /**
     * Affiche le formulaire de création d'intervention pour les clients
     */
    public function add() {
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
            $userLocations = [['client_id' => $clientId, 'site_id' => null, 'room_id' => null]];
        }

        // Récupérer les données nécessaires pour le formulaire
        $sites = $this->model->getSitesByLocations($userLocations);
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
    public function store() {
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
            'demande_par' => null, // Champ supprimé du formulaire client
            'client_id' => $clientId,
            'site_id' => !empty($_POST['site_id']) ? (int)$_POST['site_id'] : null,
            'room_id' => !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null,
            'contract_id' => !empty($_POST['contract_id']) ? (int)$_POST['contract_id'] : null,
            'status_id' => !empty($_POST['status_id']) ? (int)$_POST['status_id'] : $defaultStatusId,
            'priority_id' => !empty($_POST['priority_id']) ? (int)$_POST['priority_id'] : $defaultPriorityId,
            'ref_client' => trim($_POST['ref_client'] ?? ''),
            'contact_client' => trim($_POST['contact_client'] ?? ''),
            'duration' => 0, // Durée par défaut pour les clients
            'type_id' => 1, // Type par défaut (à adapter selon votre logique)
            'technician_id' => null, // Pas de technicien assigné par défaut
            'date_planif' => null, // Pas de date planifiée par défaut
            'heure_planif' => null // Pas d'heure planifiée par défaut
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
            $_SESSION['form_data'] = $data; // Sauvegarder les données pour les réafficher
            header('Location: ' . BASE_URL . 'interventions_client/add');
            exit;
        }

        // Vérifier que l'utilisateur a accès aux localisations sélectionnées
        $userLocations = getUserLocations();
        $hasAccess = false;
        
        foreach ($userLocations as $location) {
            if ($location['client_id'] == $clientId) {
                if ($location['site_id'] === null || $location['site_id'] == $data['site_id']) {
                    if ($location['room_id'] === null || $location['room_id'] == $data['room_id']) {
                        $hasAccess = true;
                        break;
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
} 