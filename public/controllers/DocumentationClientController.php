<?php
require_once __DIR__ . '/../models/DocumentationModel.php';
require_once __DIR__ . '/../models/DocumentationCategoryModel.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Services/AttachmentService.php';
require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';

class DocumentationClientController {
    use AccessControlTrait;
    private $db;
    private $documentationModel;
    private $categoryModel;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->documentationModel = new DocumentationModel($this->db);
        $this->categoryModel = new DocumentationCategoryModel($this->db);
    }

    /**
     * Vérifie si l'utilisateur est connecté et a les permissions client
     * Utilise AccessControlTrait::checkClientPermission()
     */
    private function checkAccess() {
        $this->checkClientPermission('client_view_documentation');
    }

    /**
     * Affiche la liste des documents du client selon ses localisations autorisées
     */
    public function index() {
        $this->checkAccess();

        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocations();
        custom_log("DocumentationClientController::index - userLocations: " . json_encode($userLocations), 'DEBUG');

        // Récupération des filtres
        $site_id = isset($_GET['site_id']) ? (int)$_GET['site_id'] : null;
        $salle_id = isset($_GET['salle_id']) ? (int)$_GET['salle_id'] : null;

        // Récupération des sites selon les localisations autorisées
        $sites = $this->getSitesByLocations($userLocations);
        
        // Récupération des salles selon le filtre site
        $salles = [];
        if ($site_id) {
            $salles = $this->getRoomsBySiteAndLocations($site_id, $userLocations);
        }

        // Initialiser la liste de documentation vide
        $documentation_list = [];

        // Construire les conditions de localisation pour la requête
        if (!empty($userLocations)) {
            $locationConditions = [];
            foreach ($userLocations as $location) {
                $clientId = $location['client_id'];
                $locationSiteId = $location['site_id'];
                $locationRoomId = $location['room_id'];
                
                // Condition pour ce client
                $condition = "(
                    c.id = {$clientId} 
                    OR c2.id = {$clientId} 
                    OR c3.id = {$clientId} 
                    OR s.client_id = {$clientId} 
                    OR s2.client_id = {$clientId} 
                    OR r.site_id IN (SELECT id FROM sites WHERE client_id = {$clientId})
                )";
                
                // Si accès spécifique à un site
                if ($locationSiteId !== null) {
                    $condition .= " AND (s.id = {$locationSiteId} OR s2.id = {$locationSiteId} OR r.site_id = {$locationSiteId})";
                    
                    // Si accès spécifique à une salle
                    if ($locationRoomId !== null) {
                        $condition .= " AND r.id = {$locationRoomId}";
                    }
                }
                
                $locationConditions[] = $condition;
            }
            
            $locationWhere = "(" . implode(" OR ", $locationConditions) . ")";
            
            // Requête pour récupérer les pièces jointes de documentation
            // On récupère toujours les informations complètes (client, site, salle) même si le document
            // est lié directement à une salle ou un site
            $query = "
                SELECT 
                    pj.*,
                    COALESCE(pj.content, pj.commentaire) as description,
                    COALESCE(c.name, c2.name, c3.name) as client_nom,
                    COALESCE(s.name, s2.name) as site_nom,
                    r.name as salle_nom,
                    COALESCE(c.id, c2.id, c3.id) as client_id,
                    COALESCE(s.id, s2.id) as site_id,
                    r.id as salle_id,
                    u.username as uploader_name,
                    pj.created_by
                FROM pieces_jointes pj
                INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                -- JOIN pour les documents liés directement au client
                LEFT JOIN clients c ON (lpj.type_liaison = 'documentation_client' AND lpj.entite_id = c.id)
                -- JOIN pour les documents liés directement au site
                LEFT JOIN sites s ON (lpj.type_liaison = 'documentation_site' AND lpj.entite_id = s.id)
                -- JOIN pour récupérer le client depuis le site (quand document lié au site)
                LEFT JOIN clients c2 ON s.client_id = c2.id
                -- JOIN pour les documents liés directement à la salle
                LEFT JOIN rooms r ON (lpj.type_liaison = 'documentation_room' AND lpj.entite_id = r.id)
                -- JOIN pour récupérer le site depuis la salle (quand document lié à la salle)
                LEFT JOIN sites s2 ON r.site_id = s2.id
                -- JOIN pour récupérer le client depuis le site de la salle (quand document lié à la salle)
                LEFT JOIN clients c3 ON s2.client_id = c3.id
                LEFT JOIN users u ON pj.created_by = u.id
                WHERE lpj.type_liaison IN ('documentation_client', 'documentation_site', 'documentation_room')
                AND pj.masque_client = 0
                AND {$locationWhere}
            ";

            $params = [];

            // Filtres optionnels
            if ($site_id) {
                $query .= " AND (s.id = ? OR s2.id = ? OR r.site_id = ?)";
                $params[] = $site_id;
                $params[] = $site_id;
                $params[] = $site_id;
            }

            if ($salle_id) {
                $query .= " AND r.id = ?";
                $params[] = $salle_id;
            }

            $query .= " ORDER BY client_nom, site_nom, salle_nom, pj.date_creation DESC";

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $documentation_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Préparer les données pour la vue
        $filters = [
            'site_id' => $site_id,
            'salle_id' => $salle_id
        ];

        // Passage des données à la vue
        require_once __DIR__ . '/../views/documentation_client/index.php';
    }

    /**
     * Récupère les sites selon les localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des sites
     */
    private function getSitesByLocations($userLocations) {
        if (empty($userLocations)) {
            return [];
        }
        
        // Extraire les client_id et site_id uniques des localisations
        $siteConditions = [];
        foreach ($userLocations as $location) {
            $clientId = $location['client_id'];
            $siteId = $location['site_id'];
            
            if ($siteId !== null) {
                // Accès spécifique à un site
                $siteConditions[] = "(s.client_id = {$clientId} AND s.id = {$siteId})";
            } else {
                // Accès au client entier
                $siteConditions[] = "(s.client_id = {$clientId})";
            }
        }
        
        $locationWhere = empty($siteConditions) ? "1=0" : "(" . implode(" OR ", $siteConditions) . ")";
        
        $sql = "SELECT DISTINCT s.* 
                FROM sites s
                WHERE {$locationWhere} AND s.status = 1
                ORDER BY s.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les salles d'un site selon les localisations autorisées
     * @param int $siteId ID du site
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des salles
     */
    private function getRoomsBySiteAndLocations($siteId, $userLocations) {
        if (empty($userLocations)) {
            return [];
        }
        
        // Extraire les conditions pour les salles
        $roomConditions = [];
        foreach ($userLocations as $location) {
            $clientId = $location['client_id'];
            $locationSiteId = $location['site_id'];
            $roomId = $location['room_id'];
            
            if ($locationSiteId == $siteId) {
                if ($roomId !== null) {
                    // Accès spécifique à une salle
                    $roomConditions[] = "(s.client_id = {$clientId} AND s.id = {$siteId} AND r.id = {$roomId})";
                } else {
                    // Accès à un site entier
                    $roomConditions[] = "(s.client_id = {$clientId} AND s.id = {$siteId})";
                }
            }
        }
        
        $locationWhere = empty($roomConditions) ? "1=0" : "(" . implode(" OR ", $roomConditions) . ")";
        
        $sql = "SELECT r.* 
                FROM rooms r
                JOIN sites s ON r.site_id = s.id
                WHERE r.site_id = ? AND {$locationWhere} AND r.status = 1
                ORDER BY r.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$siteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les documents d'une catégorie selon les localisations autorisées
     * @param int $categoryId ID de la catégorie
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @param int|null $siteId Filtre site optionnel
     * @param int|null $roomId Filtre salle optionnel
     * @return array Liste des documents
     */
    private function getDocumentsByCategoryAndLocations($categoryId, $userLocations, $siteId = null, $roomId = null) {
        if (empty($userLocations)) {
            return [];
        }
        
        // Construire les conditions de localisation
        $locationConditions = [];
        foreach ($userLocations as $location) {
            $clientId = $location['client_id'];
            $locationSiteId = $location['site_id'];
            $locationRoomId = $location['room_id'];
            
            $condition = "d.client_id = {$clientId}";
            
            if ($locationSiteId !== null) {
                $condition .= " AND (d.site_id IS NULL OR d.site_id = {$locationSiteId})";
                if ($locationRoomId !== null) {
                    $condition .= " AND (d.room_id IS NULL OR d.room_id = {$locationRoomId})";
                }
            }
            
            $locationConditions[] = "({$condition})";
        }
        
        $locationWhere = "(" . implode(" OR ", $locationConditions) . ")";
        
        // Construire la requête
        $sql = "SELECT d.*, s.name as site_name, r.name as room_name, 
                       u.first_name, u.last_name
                FROM documentation d
                LEFT JOIN sites s ON d.site_id = s.id
                LEFT JOIN rooms r ON d.room_id = r.id
                LEFT JOIN users u ON d.created_by = u.id
                WHERE d.category_id = ? 
                AND d.visible_by_client = 1
                AND {$locationWhere}";
        
        $params = [$categoryId];
        
        // Ajouter les filtres optionnels
        if ($siteId) {
            $sql .= " AND (d.site_id = ? OR d.site_id IS NULL)";
            $params[] = $siteId;
        }
        
        if ($roomId) {
            $sql .= " AND (d.room_id = ? OR d.room_id IS NULL)";
            $params[] = $roomId;
        }
        
        $sql .= " ORDER BY d.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les salles d'un site selon les localisations autorisées (AJAX)
     */
    public function get_rooms() {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Non autorisé']);
            exit;
        }

        if (!isset($_GET['site_id'])) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Site ID is required']);
            exit;
        }

        $siteId = (int)$_GET['site_id'];

        try {
            $userLocations = getUserLocations();
            $rooms = $this->getRoomsBySiteAndLocations($siteId, $userLocations);
            header('Content-Type: application/json');
            echo json_encode($rooms);
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
        }
    }

    /**
     * Affiche le formulaire d'ajout de document
     */
    public function add() {
        $this->checkAccess();
        
        // Vérifier que l'utilisateur a la permission d'ajouter de la documentation
        if (!hasPermission('client_add_documentation')) {
            $_SESSION['error'] = "Vous n'avez pas les permissions pour ajouter de la documentation.";
            header('Location: ' . BASE_URL . 'documentation_client');
            exit;
        }

        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocations();
        
        // Récupération des données pour les filtres
        $sites = $this->getSitesByLocations($userLocations);
        $rooms = [];
        $categories = $this->categoryModel->getAllCategories();
        
        // Définir la page courante pour le menu
        $currentPage = 'documentation_client';
        $pageTitle = 'Ajouter un document';

        // Inclure la vue
        require_once __DIR__ . '/../views/documentation_client/add.php';
    }

    /**
     * Traite l'ajout de documentation (upload multiple)
     */
    public function store() {
        $this->checkAccess();
        
        // Vérifier que l'utilisateur a la permission d'ajouter de la documentation
        if (!hasPermission('client_add_documentation')) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Vous n\'avez pas les permissions pour ajouter de la documentation.']);
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Méthode non autorisée']);
            exit;
        }
        
        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocations();
        
        if (empty($userLocations)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Aucune localisation autorisée']);
            exit;
        }
        
        // Récupérer le client_id depuis les localisations (premier client trouvé)
        $clientId = $userLocations[0]['client_id'];
        
        $siteId = isset($_POST['site_id']) && !empty($_POST['site_id']) ? (int)$_POST['site_id'] : null;
        $roomId = isset($_POST['room_id']) && !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
        
        // Vérifier que la localisation est autorisée
        if (!$this->isLocationAuthorized($clientId, $siteId, $roomId, $userLocations)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Vous n\'avez pas les permissions pour ajouter un document à cette localisation.']);
            exit;
        }
        
        if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Aucun fichier sélectionné']);
            exit;
        }
        
        $uploadedFiles = [];
        $errors = [];
        
        try {
            // Créer le répertoire de destination
            $uploadDir = __DIR__ . '/../../uploads/documentation/' . $clientId . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Traiter chaque fichier
            foreach ($_FILES['files']['tmp_name'] as $index => $tmpName) {
                if ($_FILES['files']['error'][$index] !== UPLOAD_ERR_OK) {
                    $errors[] = "Erreur lors de l'upload du fichier " . ($index + 1);
                    continue;
                }
                
                $originalFileName = $_FILES['files']['name'][$index];
                $customName = isset($_POST['custom_names'][$index]) ? $_POST['custom_names'][$index] : $originalFileName;
                $fileSize = $_FILES['files']['size'][$index];
                $fileTmpPath = $tmpName;
                
                // Vérifier la taille du fichier (limite du serveur)
                $maxFileSize = getServerMaxUploadSize();
                if ($fileSize > $maxFileSize) {
                    $errors[] = "Le fichier '$originalFileName' est trop volumineux (max " . formatFileSize($maxFileSize) . ")";
                    continue;
                }
                
                // Vérifier l'extension
                require_once INCLUDES_PATH . '/FileUploadValidator.php';
                $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
                
                if (!FileUploadValidator::isExtensionAllowed($fileExtension, $this->db)) {
                    $errors[] = "Le format du fichier '$originalFileName' n'est pas accepté";
                    continue;
                }
                
                // Générer un nom de fichier unique
                $fileName = $this->generateUniqueFileName($uploadDir, $originalFileName);
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($fileTmpPath, $filePath)) {
                    try {
                        $this->db->beginTransaction();
                        
                        // Insérer la pièce jointe (toujours visible pour les clients)
                        $query = "INSERT INTO pieces_jointes (
                                    nom_fichier, nom_personnalise, chemin_fichier, type_fichier, taille_fichier, 
                                    commentaire, masque_client, created_by, date_creation
                                  ) VALUES (
                                    :nom_fichier, :nom_personnalise, :chemin_fichier, :type_fichier, :taille_fichier,
                                    :commentaire, 0, :created_by, NOW()
                                  )";
                        
                        $stmt = $this->db->prepare($query);
                        $result = $stmt->execute([
                            ':nom_fichier' => $originalFileName,
                            ':nom_personnalise' => $customName,
                            ':chemin_fichier' => 'uploads/documentation/' . $clientId . '/' . $fileName,
                            ':type_fichier' => $fileExtension,
                            ':taille_fichier' => $fileSize,
                            ':commentaire' => null,
                            ':created_by' => $_SESSION['user']['id']
                        ]);
                        
                        if ($result) {
                            $pieceJointeId = $this->db->lastInsertId();
                            
                            // Créer la liaison selon le niveau
                            if ($roomId) {
                                // Liaison avec une salle
                                $linkQuery = "INSERT INTO liaisons_pieces_jointes (piece_jointe_id, type_liaison, entite_id) 
                                              VALUES (:piece_jointe_id, 'documentation_room', :room_id)";
                                $linkStmt = $this->db->prepare($linkQuery);
                                $linkStmt->execute([
                                    ':piece_jointe_id' => $pieceJointeId,
                                    ':room_id' => $roomId
                                ]);
                            } elseif ($siteId) {
                                // Liaison avec un site
                                $linkQuery = "INSERT INTO liaisons_pieces_jointes (piece_jointe_id, type_liaison, entite_id) 
                                              VALUES (:piece_jointe_id, 'documentation_site', :site_id)";
                                $linkStmt = $this->db->prepare($linkQuery);
                                $linkStmt->execute([
                                    ':piece_jointe_id' => $pieceJointeId,
                                    ':site_id' => $siteId
                                ]);
                            } else {
                                // Liaison avec le client
                                $linkQuery = "INSERT INTO liaisons_pieces_jointes (piece_jointe_id, type_liaison, entite_id) 
                                              VALUES (:piece_jointe_id, 'documentation_client', :client_id)";
                                $linkStmt = $this->db->prepare($linkQuery);
                                $linkStmt->execute([
                                    ':piece_jointe_id' => $pieceJointeId,
                                    ':client_id' => $clientId
                                ]);
                            }
                            
                            $this->db->commit();
                            $uploadedFiles[] = $customName;
                        } else {
                            $this->db->rollBack();
                            $errors[] = "Erreur lors de l'enregistrement du fichier '$originalFileName'";
                            // Supprimer le fichier uploadé
                            if (file_exists($filePath)) {
                                unlink($filePath);
                            }
                        }
                    } catch (Exception $e) {
                        $this->db->rollBack();
                        $errors[] = "Erreur lors de l'enregistrement du fichier '$originalFileName': " . $e->getMessage();
                        // Supprimer le fichier uploadé
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                } else {
                    $errors[] = "Erreur lors de l'upload du fichier '$originalFileName'";
                }
            }
            
            // Retourner la réponse
            header('Content-Type: application/json');
            if (count($uploadedFiles) > 0) {
                $message = count($uploadedFiles) . ' fichier(s) uploadé(s) avec succès';
                if (count($errors) > 0) {
                    $message .= '. ' . count($errors) . ' erreur(s): ' . implode(', ', $errors);
                }
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'uploaded_files' => $uploadedFiles,
                    'errors' => $errors
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Aucun fichier n\'a pu être uploadé. Erreurs: ' . implode(', ', $errors)
                ]);
            }
            exit;
            
        } catch (Exception $e) {
            custom_log("Erreur lors de l'upload de documentation client : " . $e->getMessage(), 'ERROR');
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Erreur de connexion: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * Génère un nom de fichier unique en conservant le nom original
     */
    private function generateUniqueFileName($uploadDir, $originalFileName) {
        // Nettoyer le nom de fichier (supprimer les caractères spéciaux dangereux)
        $cleanFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalFileName);
        
        // Séparer le nom et l'extension
        $pathInfo = pathinfo($cleanFileName);
        $name = $pathInfo['filename'];
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        
        $fileName = $name . $extension;
        $filePath = $uploadDir . $fileName;
        
        // Si le fichier n'existe pas, on peut l'utiliser
        if (!file_exists($filePath)) {
            return $fileName;
        }
        
        // Sinon, chercher un nom disponible avec un incrément
        $counter = 1;
        do {
            $fileName = $name . '_' . $counter . $extension;
            $filePath = $uploadDir . $fileName;
            $counter++;
        } while (file_exists($filePath));
        
        return $fileName;
    }

    /**
     * Traite l'ajout d'un nouveau document (ancienne méthode - conservée pour compatibilité)
     */
    public function create() {
        $this->checkAccess();
        
        // Vérifier que l'utilisateur a la permission d'ajouter de la documentation
        if (!hasPermission('client_add_documentation')) {
            $_SESSION['error'] = "Vous n'avez pas les permissions pour ajouter de la documentation.";
            header('Location: ' . BASE_URL . 'documentation_client');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Récupérer les localisations autorisées de l'utilisateur
            $userLocations = getUserLocations();
            
            // Vérifier que le client_id est autorisé
            $clientId = (int)$_POST['client_id'];
            $siteId = !empty($_POST['site_id']) ? (int)$_POST['site_id'] : null;
            $roomId = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
            
            if (!$this->isLocationAuthorized($clientId, $siteId, $roomId, $userLocations)) {
                $_SESSION['error'] = "Vous n'avez pas les permissions pour ajouter un document à cette localisation.";
                header('Location: ' . BASE_URL . 'documentation_client/add');
                exit;
            }

            $data = [
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'content' => $_POST['content'] ?? null,
                'attachment_path' => '',
                'client_id' => $clientId,
                'site_id' => $siteId,
                'room_id' => $roomId,
                'category_id' => $_POST['category_id'] ?? null,
                'visible_by_client' => 1 // Par défaut visible par les clients
            ];

            // Gestion de l'upload du fichier
            if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../uploads/documents/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Créer le répertoire du client s'il n'existe pas
                $clientDir = $uploadDir . $data['client_id'] . '/';
                if (!file_exists($clientDir)) {
                    mkdir($clientDir, 0777, true);
                }

                // Préparer le nom du fichier
                $originalName = $_FILES['document']['name'];
                $safeName = str_replace(' ', '_', $originalName);
                $extension = pathinfo($safeName, PATHINFO_EXTENSION);
                $baseName = pathinfo($safeName, PATHINFO_FILENAME);
                
                // Vérifier si le fichier existe déjà et ajouter un numéro incrémental si nécessaire
                $counter = 1;
                $finalName = $safeName;
                while (file_exists($clientDir . $finalName)) {
                    $finalName = $baseName . '_' . $counter . '.' . $extension;
                    $counter++;
                }

                $targetPath = $clientDir . $finalName;

                if (move_uploaded_file($_FILES['document']['tmp_name'], $targetPath)) {
                    $data['attachment_path'] = 'uploads/documents/' . $data['client_id'] . '/' . $finalName;
                } else {
                    $_SESSION['error'] = "Erreur lors de l'upload du fichier.";
                    header('Location: ' . BASE_URL . 'documentation_client/add');
                    exit;
                }
            }

            if ($this->documentationModel->addDocument($data)) {
                $_SESSION['success'] = "Document ajouté avec succès.";
                header('Location: ' . BASE_URL . 'documentation_client');
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de l'ajout du document.";
                header('Location: ' . BASE_URL . 'documentation_client/add');
                exit;
            }
        }
        
        header('Location: ' . BASE_URL . 'documentation_client/add');
        exit;
    }

    /**
     * Affiche le formulaire de modification d'un document
     */
    public function edit($id) {
        $this->checkAccess();
        
        // Vérifier que l'utilisateur a la permission d'ajouter de la documentation
        if (!hasPermission('client_add_documentation')) {
            $_SESSION['error'] = "Vous n'avez pas les permissions pour modifier la documentation.";
            header('Location: ' . BASE_URL . 'documentation_client');
            exit;
        }

        // Récupérer le document
        $document = $this->documentationModel->getDocumentById($id);
        if (!$document) {
            $_SESSION['error'] = "Document non trouvé.";
            header('Location: ' . BASE_URL . 'documentation_client');
            exit;
        }

        // Vérifier que l'utilisateur peut modifier ce document (créé par lui)
        if ($document['created_by'] != $_SESSION['user']['id']) {
            $_SESSION['error'] = "Vous ne pouvez modifier que vos propres documents.";
            header('Location: ' . BASE_URL . 'documentation_client');
            exit;
        }

        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocations();
        
        // Récupération des données pour les filtres
        $sites = $this->getSitesByLocations($userLocations);
        $rooms = [];
        if ($document['site_id']) {
            $rooms = $this->getRoomsBySiteAndLocations($document['site_id'], $userLocations);
        }
        $categories = $this->categoryModel->getAllCategories();
        
        // Définir la page courante pour le menu
        $currentPage = 'documentation_client';
        $pageTitle = 'Modifier un document';

        // Inclure la vue
        require_once __DIR__ . '/../views/documentation_client/edit.php';
    }

    /**
     * Traite la modification d'un document
     */
    public function update($id) {
        $this->checkAccess();
        
        // Vérifier que l'utilisateur a la permission d'ajouter de la documentation
        if (!hasPermission('client_add_documentation')) {
            $_SESSION['error'] = "Vous n'avez pas les permissions pour modifier la documentation.";
            header('Location: ' . BASE_URL . 'documentation_client');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Récupérer le document existant
            $existingDocument = $this->documentationModel->getDocumentById($id);
            if (!$existingDocument) {
                $_SESSION['error'] = "Document non trouvé.";
                header('Location: ' . BASE_URL . 'documentation_client');
                exit;
            }

            // Vérifier que l'utilisateur peut modifier ce document (créé par lui)
            if ($existingDocument['created_by'] != $_SESSION['user']['id']) {
                $_SESSION['error'] = "Vous ne pouvez modifier que vos propres documents.";
                header('Location: ' . BASE_URL . 'documentation_client');
                exit;
            }

            // Récupérer les localisations autorisées de l'utilisateur
            $userLocations = getUserLocations();
            
            // Vérifier que le client_id est autorisé
            $clientId = (int)$_POST['client_id'];
            $siteId = !empty($_POST['site_id']) ? (int)$_POST['site_id'] : null;
            $roomId = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
            
            if (!$this->isLocationAuthorized($clientId, $siteId, $roomId, $userLocations)) {
                $_SESSION['error'] = "Vous n'avez pas les permissions pour modifier un document à cette localisation.";
                header('Location: ' . BASE_URL . 'documentation_client/edit/' . $id);
                exit;
            }

            $data = [
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'content' => $_POST['content'] ?? null,
                'attachment_path' => $existingDocument['attachment_path'], // Garder l'ancien par défaut
                'client_id' => $clientId,
                'site_id' => $siteId,
                'room_id' => $roomId,
                'category_id' => $_POST['category_id'] ?? null,
                'visible_by_client' => 1 // Par défaut visible par les clients
            ];

            // Gestion de l'upload du fichier
            $fileUploadedSuccessfully = false;
            $newlyUploadedFilePath = null;

            if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../uploads/documents/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Créer le répertoire du client s'il n'existe pas
                $clientDir = $uploadDir . $data['client_id'] . '/';
                if (!file_exists($clientDir)) {
                    mkdir($clientDir, 0777, true);
                }

                // Préparer le nom du fichier
                $originalName = $_FILES['document']['name'];
                $safeName = str_replace(' ', '_', $originalName);
                $extension = pathinfo($safeName, PATHINFO_EXTENSION);
                $baseName = pathinfo($safeName, PATHINFO_FILENAME);
                
                // Vérifier si le fichier existe déjà et ajouter un numéro incrémental si nécessaire
                $counter = 1;
                $finalName = $safeName;
                while (file_exists($clientDir . $finalName)) {
                    $finalName = $baseName . '_' . $counter . '.' . $extension;
                    $counter++;
                }

                $targetPath = $clientDir . $finalName;

                if (move_uploaded_file($_FILES['document']['tmp_name'], $targetPath)) {
                    $data['attachment_path'] = 'uploads/documents/' . $data['client_id'] . '/' . $finalName;
                    $fileUploadedSuccessfully = true;
                    $newlyUploadedFilePath = $targetPath;
                } else {
                    $_SESSION['error'] = "Erreur lors de l'upload du fichier.";
                    header('Location: ' . BASE_URL . 'documentation_client/edit/' . $id);
                    exit;
                }
            }

            // Supprimer l'ancien fichier si un nouveau a été uploadé
            if ($fileUploadedSuccessfully && !empty($existingDocument['attachment_path'])) {
                $oldFilePath = __DIR__ . '/../../' . $existingDocument['attachment_path'];
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }

            if ($this->documentationModel->updateDocument($id, $data)) {
                $_SESSION['success'] = "Document mis à jour avec succès.";
                header('Location: ' . BASE_URL . 'documentation_client');
                exit;
            } else {
                // Si la mise à jour a échoué et qu'un nouveau fichier a été uploadé, le supprimer
                if ($fileUploadedSuccessfully && $newlyUploadedFilePath && file_exists($newlyUploadedFilePath)) {
                    unlink($newlyUploadedFilePath);
                }
                $_SESSION['error'] = "Erreur lors de la mise à jour du document.";
                header('Location: ' . BASE_URL . 'documentation_client/edit/' . $id);
                exit;
            }
        }
        
        header('Location: ' . BASE_URL . 'documentation_client/edit/' . $id);
        exit;
    }

    /**
     * Supprime un document
     */
    public function delete($id) {
        $this->checkAccess();
        
        // Vérifier que l'utilisateur a la permission d'ajouter de la documentation
        if (!hasPermission('client_add_documentation')) {
            $_SESSION['error'] = "Vous n'avez pas les permissions pour supprimer la documentation.";
            header('Location: ' . BASE_URL . 'documentation_client');
            exit;
        }

        try {
            // Récupérer le document depuis pieces_jointes
            $query = "SELECT pj.*, 
                            COALESCE(c.id, c2.id, c3.id) as client_id,
                            COALESCE(s.id, s2.id) as site_id,
                            r.id as salle_id
                     FROM pieces_jointes pj
                     INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                     LEFT JOIN clients c ON (lpj.type_liaison = 'documentation_client' AND lpj.entite_id = c.id)
                     LEFT JOIN sites s ON (lpj.type_liaison = 'documentation_site' AND lpj.entite_id = s.id)
                     LEFT JOIN clients c2 ON s.client_id = c2.id
                     LEFT JOIN rooms r ON (lpj.type_liaison = 'documentation_room' AND lpj.entite_id = r.id)
                     LEFT JOIN sites s2 ON r.site_id = s2.id
                     LEFT JOIN clients c3 ON s2.client_id = c3.id
                     WHERE pj.id = ? 
                     AND lpj.type_liaison IN ('documentation_client', 'documentation_site', 'documentation_room')
                     AND pj.masque_client = 0";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$document) {
                $_SESSION['error'] = "Document non trouvé ou non accessible.";
                header('Location: ' . BASE_URL . 'documentation_client');
                exit;
            }

            // Vérifier que l'utilisateur peut supprimer ce document (créé par lui)
            if ($document['created_by'] != $_SESSION['user']['id']) {
                $_SESSION['error'] = "Vous ne pouvez supprimer que vos propres documents.";
                header('Location: ' . BASE_URL . 'documentation_client');
                exit;
            }

            // Vérifier que l'utilisateur a accès à cette localisation
            $userLocations = getUserLocations();
            if (!$this->isLocationAuthorized($document['client_id'], $document['site_id'], $document['salle_id'], $userLocations)) {
                $_SESSION['error'] = "Vous n'avez pas accès à ce document.";
                header('Location: ' . BASE_URL . 'documentation_client');
                exit;
            }

            // Supprimer le fichier physique s'il existe
            if (!empty($document['chemin_fichier'])) {
                $filePath = __DIR__ . '/../../' . $document['chemin_fichier'];
                if (file_exists($filePath)) {
                    if (!unlink($filePath)) {
                        custom_log("Erreur lors de la suppression du fichier : " . $filePath, 'ERROR');
                        $_SESSION['error'] = "Erreur lors de la suppression du fichier physique.";
                        header('Location: ' . BASE_URL . 'documentation_client');
                        exit;
                    }
                }
            }

            // Supprimer les liaisons
            $deleteLiaisonQuery = "DELETE FROM liaisons_pieces_jointes WHERE piece_jointe_id = ?";
            $deleteLiaisonStmt = $this->db->prepare($deleteLiaisonQuery);
            $deleteLiaisonStmt->execute([$id]);

            // Supprimer l'entrée dans pieces_jointes
            $deleteQuery = "DELETE FROM pieces_jointes WHERE id = ?";
            $deleteStmt = $this->db->prepare($deleteQuery);
            
            if ($deleteStmt->execute([$id])) {
                $_SESSION['success'] = "Document supprimé avec succès.";
            } else {
                $_SESSION['error'] = "Erreur lors de la suppression du document dans la base de données.";
            }
            
        } catch (Exception $e) {
            custom_log("Erreur lors de la suppression du document : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de la suppression du document.";
        }
        
        // Rediriger avec les filtres de session si disponibles
        $redirectUrl = BASE_URL . 'documentation_client';
        if (isset($_SESSION['documentation_filters'])) {
            $filters = $_SESSION['documentation_filters'];
            $params = [];
            if (!empty($filters['site_id'])) {
                $params['site_id'] = $filters['site_id'];
            }
            if (!empty($filters['salle_id'])) {
                $params['salle_id'] = $filters['salle_id'];
            }
            if (!empty($params)) {
                $redirectUrl .= '?' . http_build_query($params);
            }
        }
        
        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Met à jour le nom personnalisé d'un document
     */
    public function updateName() {
        $this->checkAccess();
        
        // Vérifier que c'est une requête AJAX
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Requête non autorisée']);
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
            exit;
        }
        
        $attachmentId = isset($_POST['attachment_id']) ? (int)$_POST['attachment_id'] : null;
        $nomPersonnalise = isset($_POST['nom_personnalise']) ? trim($_POST['nom_personnalise']) : null;
        
        if (!$attachmentId) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ID du document manquant']);
            exit;
        }
        
        // Le nom personnalisé peut être vide (NULL), on utilisera nom_fichier dans ce cas
        if ($nomPersonnalise === '') {
            $nomPersonnalise = null;
        }
        
        try {
            // Récupérer le document avec les informations de localisation
            $query = "SELECT pj.*, 
                            COALESCE(c.id, c2.id, c3.id) as client_id,
                            COALESCE(s.id, s2.id) as site_id,
                            r.id as salle_id
                     FROM pieces_jointes pj
                     INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                     LEFT JOIN clients c ON (lpj.type_liaison = 'documentation_client' AND lpj.entite_id = c.id)
                     LEFT JOIN sites s ON (lpj.type_liaison = 'documentation_site' AND lpj.entite_id = s.id)
                     LEFT JOIN clients c2 ON s.client_id = c2.id
                     LEFT JOIN rooms r ON (lpj.type_liaison = 'documentation_room' AND lpj.entite_id = r.id)
                     LEFT JOIN sites s2 ON r.site_id = s2.id
                     LEFT JOIN clients c3 ON s2.client_id = c3.id
                     WHERE pj.id = ? 
                     AND lpj.type_liaison IN ('documentation_client', 'documentation_site', 'documentation_room')
                     AND pj.masque_client = 0";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$attachmentId]);
            $pieceJointe = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$pieceJointe) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Document non trouvé ou non accessible']);
                exit;
            }
            
            // Vérifier que l'utilisateur peut modifier ce document (créé par lui)
            if ($pieceJointe['created_by'] != $_SESSION['user']['id']) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Vous ne pouvez modifier que vos propres documents']);
                exit;
            }
            
            // Vérifier que l'utilisateur a accès à cette localisation
            $userLocations = getUserLocations();
            if (!$this->isLocationAuthorized($pieceJointe['client_id'], $pieceJointe['site_id'], $pieceJointe['salle_id'], $userLocations)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Vous n\'avez pas accès à ce document']);
                exit;
            }
            
            // Mettre à jour le nom personnalisé
            $updateQuery = "UPDATE pieces_jointes SET nom_personnalise = :nom_personnalise WHERE id = :id";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->execute([
                ':nom_personnalise' => $nomPersonnalise,
                ':id' => $attachmentId
            ]);
            
            // Récupérer le nom d'affichage (nom_personnalise ou nom_fichier)
            $displayName = $nomPersonnalise ?: $pieceJointe['nom_fichier'];
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'Nom mis à jour avec succès',
                'display_name' => $displayName
            ]);
            exit;
            
        } catch (Exception $e) {
            custom_log("Erreur lors de la mise à jour du nom : " . $e->getMessage(), 'ERROR');
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour']);
            exit;
        }
    }

    /**
     * Vérifie si une localisation est autorisée pour l'utilisateur
     * @param int $clientId ID du client
     * @param int|null $siteId ID du site
     * @param int|null $roomId ID de la salle
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return bool True si autorisé, false sinon
     */
    private function isLocationAuthorized($clientId, $siteId, $roomId, $userLocations) {
        foreach ($userLocations as $location) {
            if ($location['client_id'] == $clientId) {
                // Si l'utilisateur a accès au client entier
                if ($location['site_id'] === null) {
                    return true;
                }
                
                // Si le document est lié directement au client (pas de site/salle)
                if ($siteId === null && $roomId === null) {
                    // L'utilisateur a accès à un site du client, donc il peut voir les docs du client
                    return true;
                }
                
                // Si l'utilisateur a accès à un site spécifique
                if ($location['site_id'] == $siteId) {
                    // Si le document est lié au site (pas de salle spécifique)
                    if ($roomId === null) {
                        return true;
                    }
                    
                    // Si l'utilisateur a accès à une salle spécifique
                    if ($location['room_id'] === null) {
                        // L'utilisateur a accès au site entier, donc il peut voir les docs de toutes les salles
                        return true;
                    }
                    
                    // Si l'utilisateur a accès à une salle spécifique et le document est lié à cette salle
                    if ($location['room_id'] == $roomId) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Télécharge une pièce jointe de documentation
     */
    /**
     * Télécharge une pièce jointe de documentation (client)
     * Utilise AttachmentService pour centraliser la logique
     */
    public function download($attachmentId) {
        $this->checkAccess();

        if (!$attachmentId) {
            $_SESSION['error'] = "ID de pièce jointe manquant";
            header('Location: ' . BASE_URL . 'documentation_client');
            exit;
        }
        
        $attachmentId = (int)$attachmentId;

        try {
            // Récupérer les informations de la pièce jointe avec les informations de localisation
            $query = "
                SELECT 
                    pj.*,
                    COALESCE(c.id, c2.id, c3.id) as client_id,
                    COALESCE(s.id, s2.id) as site_id,
                    r.id as salle_id
                FROM pieces_jointes pj
                INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                LEFT JOIN clients c ON (lpj.type_liaison = 'documentation_client' AND lpj.entite_id = c.id)
                LEFT JOIN sites s ON (lpj.type_liaison = 'documentation_site' AND lpj.entite_id = s.id)
                LEFT JOIN clients c2 ON s.client_id = c2.id
                LEFT JOIN rooms r ON (lpj.type_liaison = 'documentation_room' AND lpj.entite_id = r.id)
                LEFT JOIN sites s2 ON r.site_id = s2.id
                LEFT JOIN clients c3 ON s2.client_id = c3.id
                WHERE pj.id = ? 
                AND lpj.type_liaison IN ('documentation_client', 'documentation_site', 'documentation_room')
                AND pj.masque_client = 0
            ";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$attachmentId]);
            $pieceJointe = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$pieceJointe) {
                throw new Exception("Pièce jointe non trouvée ou non accessible");
            }

            // Vérifier que l'utilisateur a accès à cette localisation
            $userLocations = getUserLocations();
            if (!$this->isLocationAuthorized($pieceJointe['client_id'], $pieceJointe['site_id'], $pieceJointe['salle_id'], $userLocations)) {
                throw new Exception("Vous n'avez pas accès à ce document");
            }

            // Utiliser AttachmentService pour gérer le téléchargement
            $attachmentService = new AttachmentService($this->db);
            $attachmentService->download($attachmentId, true);

        } catch (Exception $e) {
            custom_log("Erreur lors du téléchargement de la pièce jointe : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors du téléchargement : " . $e->getMessage();
            header('Location: ' . BASE_URL . 'documentation_client');
            exit;
        }
    }

    /**
     * Aperçu d'une pièce jointe de documentation
     */
    public function preview($attachmentId) {
        $this->checkAccess();

        if (!$attachmentId) {
            http_response_code(404);
            echo "Pièce jointe non trouvée";
            exit;
        }
        
        $attachmentId = (int)$attachmentId;

        try {
            // Récupérer les informations de la pièce jointe avec les informations de localisation
            $query = "
                SELECT 
                    pj.*,
                    COALESCE(c.id, c2.id, c3.id) as client_id,
                    COALESCE(s.id, s2.id) as site_id,
                    r.id as salle_id
                FROM pieces_jointes pj
                INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                LEFT JOIN clients c ON (lpj.type_liaison = 'documentation_client' AND lpj.entite_id = c.id)
                LEFT JOIN sites s ON (lpj.type_liaison = 'documentation_site' AND lpj.entite_id = s.id)
                LEFT JOIN clients c2 ON s.client_id = c2.id
                LEFT JOIN rooms r ON (lpj.type_liaison = 'documentation_room' AND lpj.entite_id = r.id)
                LEFT JOIN sites s2 ON r.site_id = s2.id
                LEFT JOIN clients c3 ON s2.client_id = c3.id
                WHERE pj.id = ? 
                AND lpj.type_liaison IN ('documentation_client', 'documentation_site', 'documentation_room')
                AND pj.masque_client = 0
            ";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$attachmentId]);
            $pieceJointe = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$pieceJointe) {
                throw new Exception("Pièce jointe non trouvée ou non accessible");
            }

            // Vérifier que l'utilisateur a accès à cette localisation
            $userLocations = getUserLocations();
            if (!$this->isLocationAuthorized($pieceJointe['client_id'], $pieceJointe['site_id'], $pieceJointe['salle_id'], $userLocations)) {
                throw new Exception("Vous n'avez pas accès à ce document");
            }

            // Utiliser AttachmentService pour gérer l'aperçu
            $attachmentService = new AttachmentService($this->db);
            $attachmentService->preview($attachmentId);

        } catch (Exception $e) {
            custom_log("Erreur lors de l'aperçu de la pièce jointe : " . $e->getMessage(), 'ERROR');
            http_response_code(404);
            echo "Erreur : " . $e->getMessage();
            exit;
        }
    }
} 