<?php
require_once __DIR__ . '/../models/MaterielClientModel.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';
require_once __DIR__ . '/../classes/Services/AttachmentService.php';
require_once __DIR__ . '/../models/MaterielModel.php';

class MaterielClientController
{
    use AccessControlTrait;
    private $db;
    private $model;
    private $models;

    public function __construct()
    {
        global $db;
        $this->db = $db;
        $this->model = new MaterielClientModel($this->db);
        $this->models = new MaterielModel($this->db);
    }

    public function indexExcel()
    {
        require __DIR__ . '/../views/excel.php';
    }

    /**
     * create material using excel
     */
    public function save()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['data'])) {
            echo json_encode(["status" => "error"]);
            return;
        }

        $this->models->saveAll($data['data']);

        echo json_encode(["status" => "success"]);
    }

    public function load()
    {
        $data = $this->models->getAll();
        echo json_encode($data);
    }

    /**
     * Affiche la liste du matériel du client
     */
    public function index()
    {
        $this->checkClientPermission('client_view_materiel', "Vous n'avez pas les permissions pour accéder au matériel.");

        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocations();
        custom_log("MaterielClientController::index - userLocations: " . json_encode($userLocations), 'DEBUG');

        // Récupération des filtres
        $filters = [
            'site_id' => isset($_GET['site_id']) ? (int) $_GET['site_id'] : null,
            'building_id' => isset($_GET['building_id']) ? (int) $_GET['building_id'] : null,
            'salle_id' => isset($_GET['salle_id']) ? (int) $_GET['salle_id'] : null,
            'search' => $_GET['search'] ?? null
        ];

        try {
            // Récupération des données pour les filtres
            $clients = $this->model->getClientsByLocations($userLocations);

            // Initialiser les variables
            $sites = [];
            $buildings = [];
            $salles = [];
            $materiel_list = [];
            $visibilites_champs = [];
            $pieces_jointes_count = [];

            // Récupération des sites selon les localisations autorisées
            $sites = $this->model->getSitesByLocations($userLocations);

            // Récupération des bâtiments selon le filtre site
            if ($filters['site_id']) {
                $buildings = $this->model->getBuildingsBySiteAndLocations($filters['site_id'], $userLocations);
            } else {
                // Si pas de site sélectionné, récupérer tous les bâtiments selon les localisations
                $buildings = $this->model->getBuildingsByLocations($userLocations);
            }

            // Récupération des salles selon le filtre bâtiment
            if ($filters['building_id']) {
                $salles = $this->model->getRoomsByBuildingAndLocations($filters['building_id'], $userLocations);
            } else if ($filters['site_id']) {
                // Si un site est sélectionné mais pas de bâtiment, récupérer toutes les salles du site via ses bâtiments
                $salles = $this->model->getRoomsBySiteAndLocations($filters['site_id'], $userLocations);
            } else {
                // Si pas de site sélectionné, récupérer toutes les salles selon les localisations
                $salles = $this->model->getRoomsByLocations($userLocations);
            }

            // Récupération du matériel avec filtres et localisations autorisées
            $materiel_list = $this->model->getAllByLocations($userLocations, $filters);

            // Récupération des informations de visibilité des champs
            if (!empty($materiel_list)) {
                $materiel_ids = array_column($materiel_list, 'id');
                $visibilites_champs = $this->model->getVisibiliteChampsForMateriels($materiel_ids);

                // OPTIMISATION N+1 : Récupération du nombre de pièces jointes pour tous les matériels en une seule requête
                $pieces_jointes_count = $this->model->getPiecesJointesCountForMultiple($materiel_ids);

                // Initialiser à 0 pour les matériels sans pièces jointes
                foreach ($materiel_ids as $id) {
                    if (!isset($pieces_jointes_count[$id])) {
                        $pieces_jointes_count[$id] = 0;
                    }
                }
            }

        } catch (Exception $e) {
            // En cas d'erreur, initialiser les variables avec des tableaux vides
            $clients = [];
            $sites = [];
            $buildings = [];
            $salles = [];
            $materiel_list = [];
            $visibilites_champs = [];
            $pieces_jointes_count = [];

            // Log de l'erreur
            custom_log("Erreur lors du chargement du matériel client : " . $e->getMessage(), 'ERROR');
        }

        // Définir la page courante pour le menu
        $currentPage = 'materiel_client';
        $pageTitle = 'Mon Matériel';

        // Inclure la vue
        require_once __DIR__ . '/../views/materiel_client/index.php';
    }

    /**
     * Affiche le matériel d'une salle spécifique (vue compacte pour client)
     */
    public function salle($salleId)
    {
        error_log("DEBUG: MaterielClientController::salle() appelé avec salleId = $salleId");

        try {
            $this->checkClientPermission('client_view_materiel', "Vous n'avez pas les permissions pour accéder au matériel.");
            error_log("DEBUG: checkAccess() OK");

            // Récupérer les localisations autorisées de l'utilisateur
            $userLocations = getUserLocations();
            error_log("DEBUG: userLocations = " . json_encode($userLocations));

            // Récupérer les informations de la salle avec vérification d'accès (via bâtiment)
            $salle = $this->model->getRoomByIdWithAccess($salleId, $userLocations);
            error_log("DEBUG: salle = " . json_encode($salle));

            if (!$salle) {
                error_log("DEBUG: Salle non trouvée, redirection");
                $_SESSION['error'] = "Salle non trouvée ou vous n'avez pas les permissions pour y accéder.";
                header('Location: ' . BASE_URL . 'materiel_client');
                exit;
            }

            // Récupérer le matériel de cette salle avec vérification d'accès
            $filters = ['salle_id' => $salleId];
            $materiel_list = $this->model->getAllByLocations($userLocations, $filters);
            error_log("DEBUG: materiel_list count = " . count($materiel_list));

            // Récupérer les informations de visibilité des champs
            $visibilites_champs = [];
            if (!empty($materiel_list)) {
                $materiel_ids = array_column($materiel_list, 'id');
                $visibilites_champs = $this->model->getVisibiliteChampsForMateriels($materiel_ids);
            }

            error_log("DEBUG: Chargement de la vue");
            $currentPage = 'materiel_client';
            $pageTitle = "Matériel - " . ($salle['site_name'] ?? '') . " - " . ($salle['building_name'] ?? '') . " - " . ($salle['room_name'] ?? $salle['name'] ?? '');
            require_once __DIR__ . '/../views/materiel_client/salle.php';
            error_log("DEBUG: Vue chargée avec succès");

        } catch (Exception $e) {
            error_log("DEBUG: Erreur dans salle(): " . $e->getMessage());
            error_log("DEBUG: Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Affiche les détails d'un matériel
     */
    public function view($id)
    {
        $this->checkClientPermission('client_view_materiel', "Vous n'avez pas les permissions pour accéder au matériel.");

        $userLocations = getUserLocations();

        try {
            // Récupérer le matériel avec vérification d'accès
            $materiel = $this->model->getByIdWithAccess($id, $userLocations);

            if (!$materiel) {
                $_SESSION['error'] = "Matériel introuvable ou vous n'avez pas les permissions pour y accéder.";
                header('Location: ' . BASE_URL . 'materiel_client');
                exit;
            }

            // DEBUG: Vérifier directement en SQL
            $sql_check = "SELECT COUNT(*) as count FROM pieces_jointes pj 
                      INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id 
                      WHERE lpj.type_liaison = 'materiel' AND lpj.entite_id = ? AND pj.masque_client = 0";
            $stmt_check = $this->db->prepare($sql_check);
            $stmt_check->execute([$id]);
            $direct_count = $stmt_check->fetch(PDO::FETCH_ASSOC);
            custom_log("VIEW - Direct SQL count for materiel $id: " . ($direct_count['count'] ?? 0), 'DEBUG');

            // Récupérer les informations de visibilité des champs
            $visibilites_champs = [];
            $visibilites = $this->model->getVisibiliteChampsForMateriels([$id]);
            if (isset($visibilites[$id])) {
                $visibilites_champs[$id] = $visibilites[$id];
            }

            // Récupérer les pièces jointes
            $attachments = $this->model->getPiecesJointesWithAccess($id, $userLocations);
            custom_log("VIEW - Attachments from model: " . count($attachments), 'DEBUG');

        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération du matériel client : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de la récupération du matériel";
            header('Location: ' . BASE_URL . 'materiel_client');
            exit;
        }

        $currentPage = 'materiel_client';
        $pageTitle = 'Détails du Matériel';

        require_once __DIR__ . '/../views/materiel_client/view.php';
    }
    /**
     * Récupère les sites selon les localisations autorisées (AJAX)
     */
    public function get_sites()
    {
        $this->checkClientPermission('client_view_materiel', "Vous n'avez pas les permissions pour accéder au matériel.");

        $userLocations = getUserLocations();
        $sites = $this->model->getSitesByLocations($userLocations);

        header('Content-Type: application/json');
        echo json_encode($sites);
    }

    /**
     * Récupère les bâtiments d'un site selon les localisations autorisées (AJAX)
     */
    public function get_buildings()
    {
        $this->checkClientPermission('client_view_materiel', "Vous n'avez pas les permissions pour accéder au matériel.");

        $siteId = $_GET['site_id'] ?? null;
        if (!$siteId) {
            echo json_encode(['error' => 'ID du site manquant']);
            return;
        }

        $userLocations = getUserLocations();
        $buildings = $this->model->getBuildingsBySiteAndLocations($siteId, $userLocations);

        header('Content-Type: application/json');
        echo json_encode($buildings);
    }

    /**
     * Récupère les salles d'un bâtiment selon les localisations autorisées (AJAX)
     */
    public function get_rooms_by_building()
    {
        $this->checkClientPermission('client_view_materiel', "Vous n'avez pas les permissions pour accéder au matériel.");

        $buildingId = $_GET['building_id'] ?? null;
        if (!$buildingId) {
            echo json_encode(['error' => 'ID du bâtiment manquant']);
            return;
        }

        $userLocations = getUserLocations();
        $rooms = $this->model->getRoomsByBuildingAndLocations($buildingId, $userLocations);

        header('Content-Type: application/json');
        echo json_encode($rooms);
    }

    /**
     * Récupère les salles d'un site selon les localisations autorisées (AJAX - pour compatibilité)
     */
    public function get_rooms()
    {
        $this->checkClientPermission('client_view_materiel', "Vous n'avez pas les permissions pour accéder au matériel.");

        $siteId = $_GET['site_id'] ?? null;
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
     */
    public function download($attachmentId)
    {
        $this->checkClientPermission('client_view_materiel');

        if (!$attachmentId) {
            $_SESSION['error'] = "ID de pièce jointe manquant";
            header('Location: ' . BASE_URL . 'materiel_client');
            exit;
        }

        $attachmentId = (int) $attachmentId;

        try {
            $userLocations = getUserLocations();

            // Récupérer les informations de la pièce jointe
            $sql = "SELECT pj.*, lpj.type_liaison, lpj.entite_id 
                FROM pieces_jointes pj
                INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                WHERE pj.id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$attachmentId]);
            $attachmentData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$attachmentData) {
                throw new Exception('Pièce jointe non trouvée.');
            }

            if ($attachmentData['type_liaison'] !== 'materiel') {
                throw new Exception('Type de pièce jointe non supporté.');
            }

            // Vérifier l'accès au matériel
            $materiel = $this->model->getByIdWithAccess($attachmentData['entite_id'], $userLocations);
            if (!$materiel) {
                throw new Exception('Vous n\'êtes pas autorisé à accéder à cette pièce jointe.');
            }

            // CORRECTION : Construire le chemin absolu correctement
            // Remplacer les antislashs par des slashes et nettoyer le chemin
            $relativePath = str_replace('\\', '/', $attachmentData['chemin_fichier']);
            $relativePath = ltrim($relativePath, '/');

            // Chemin absolu depuis la racine du projet
            $filePath = __DIR__ . '/../../' . $relativePath;

            // Normaliser le chemin (remplacer \ par /)
            $filePath = str_replace('\\', '/', $filePath);

            // Alternative: utiliser le chemin absolu du projet
            // $filePath = $_SERVER['DOCUMENT_ROOT'] . '/avision/public/' . $relativePath;

            custom_log("Relative path: " . $relativePath, 'DEBUG');
            custom_log("Full file path: " . $filePath, 'DEBUG');
            custom_log("File exists: " . (file_exists($filePath) ? 'YES' : 'NO'), 'DEBUG');

            if (!file_exists($filePath)) {
                // Essayer un autre chemin
                $altPath = $_SERVER['DOCUMENT_ROOT'] . '/avision/public/' . $relativePath;
                custom_log("Alternative path: " . $altPath, 'DEBUG');
                custom_log("Alternative exists: " . (file_exists($altPath) ? 'YES' : 'NO'), 'DEBUG');

                if (file_exists($altPath)) {
                    $filePath = $altPath;
                } else {
                    throw new Exception('Le fichier n\'existe pas sur le serveur. Chemin recherché: ' . $filePath);
                }
            }

            // Nom du fichier
            $fileName = !empty($attachmentData['nom_personnalise'])
                ? $attachmentData['nom_personnalise']
                : $attachmentData['nom_fichier'];

            $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);

            // Type MIME
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $mimeTypes = [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'txt' => 'text/plain',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'zip' => 'application/zip',
            ];
            $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

            // Nettoyer les buffers
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Headers pour forcer le téléchargement
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            header('Expires: 0');

            // Envoyer le fichier
            readfile($filePath);
            exit;

        } catch (Exception $e) {
            custom_log("ERROR in download: " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors du téléchargement : " . $e->getMessage();
            header('Location: ' . BASE_URL . 'materiel_client/view/' . ($attachmentData['entite_id'] ?? 0));
            exit;
        }
    }
    /**
     * Aperçu d'une pièce jointe (client)
     * Utilise AttachmentService pour centraliser la logique
     */
    public function preview($attachmentId)
    {
        $this->checkClientPermission('client_view_materiel');

        try {
            $userLocations = getUserLocations();

            // Vérifier que la pièce jointe appartient à un matériel accessible
            $attachmentService = new AttachmentService($this->db);
            $attachmentData = $attachmentService->getAttachmentById($attachmentId);

            if (!$attachmentData || $attachmentData['type_liaison'] !== AttachmentService::TYPE_MATERIEL) {
                throw new Exception('Pièce jointe non trouvée.');
            }

            // Vérifier l'accès au matériel
            $materiel = $this->model->getByIdWithAccess($attachmentData['entite_id'], $userLocations);
            if (!$materiel) {
                throw new Exception('Vous n\'êtes pas autorisé à accéder à cette pièce jointe.');
            }

            // Utiliser AttachmentService pour gérer l'aperçu
            $attachmentService->preview($attachmentId);

        } catch (Exception $e) {
            custom_log("Erreur lors de l'aperçu de la pièce jointe (client matériel) : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de l'aperçu : " . $e->getMessage();
            $materielId = $attachmentData['entite_id'] ?? 0;
            header('Location: ' . BASE_URL . 'materiel_client/view/' . $materielId);
            exit;
        }
    }
    /**
     * Récupère un bâtiment par son ID avec vérification d'accès (API)
     */
    public function getBuildingByIdWithAccess()
    {
        // Définir le header JSON
        header('Content-Type: application/json');

        try {
            // Vérifier si l'utilisateur est connecté
            if (!isset($_SESSION['user'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Non authentifié']);
                return;
            }

            // Récupérer l'ID du bâtiment depuis l'URL ou les paramètres
            $buildingId = null;

            // Vérifier dans l'URL (ex: /materiel_client/getBuildingByIdWithAccess/123)
            global $parts;
            if (isset($parts[3]) && is_numeric($parts[3])) {
                $buildingId = (int) $parts[3];
            }
            // Vérifier dans les paramètres GET
            elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $buildingId = (int) $_GET['id'];
            }
            // Vérifier dans les paramètres POST
            elseif (isset($_POST['building_id']) && is_numeric($_POST['building_id'])) {
                $buildingId = (int) $_POST['building_id'];
            }

            if (!$buildingId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID du bâtiment manquant']);
                return;
            }

            // Récupérer les localisations autorisées
            $userLocations = getUserLocations();

            if (empty($userLocations)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Aucune localisation autorisée']);
                return;
            }

            // Instancier le modèle
            $config = Config::getInstance();
            $db = $config->getDb();
            $materielClientModel = new MaterielClientModel($db);

            // Récupérer le bâtiment avec vérification d'accès
            $building = $materielClientModel->getBuildingByIdWithAccess($buildingId, $userLocations);

            if (!$building) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Bâtiment non trouvé ou accès non autorisé']);
                return;
            }

            echo json_encode(['success' => true, 'building' => $building]);

        } catch (Exception $e) {
            custom_log("Erreur dans getBuildingByIdWithAccess: " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()]);
        }
    }

}