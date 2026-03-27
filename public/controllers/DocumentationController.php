<?php
require_once __DIR__ . '/../models/DocumentationModel.php';
require_once __DIR__ . '/../models/DocumentationCategoryModel.php';
require_once __DIR__ . '/../models/ClientModel.php';
require_once __DIR__ . '/../models/SiteModel.php';
require_once __DIR__ . '/../models/RoomModel.php';
require_once __DIR__ . '/../classes/Services/AttachmentService.php';
require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';

class DocumentationController {
    use AccessControlTrait;
    private $db;
    private $documentationModel;
    private $categoryModel;
    private $clientModel;
    private $siteModel;
    private $roomModel;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->documentationModel = new DocumentationModel($this->db);
        $this->categoryModel = new DocumentationCategoryModel($this->db);
        $this->clientModel = new ClientModel($this->db);
        $this->siteModel = new SiteModel($this->db);
        $this->roomModel = new RoomModel($this->db);
    }

    /**
     * Génère un nom de fichier unique en conservant le nom original
     * En cas de doublon, ajoute un incrément à la fin du nom
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
     * Vérifie si l'utilisateur est connecté
     * Utilise AccessControlTrait::checkAccessWithAjax() pour gérer les requêtes AJAX
     */
    private function checkAccess() {
        $this->checkAccessWithAjax();
    }

    /**
     * Affiche la liste des documents avec filtres
     */
    public function index() {
        $this->checkAccess();
        
        // Récupération des filtres
        $client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;
        $site_id = isset($_GET['site_id']) ? (int)$_GET['site_id'] : null;
        $salle_id = isset($_GET['salle_id']) ? (int)$_GET['salle_id'] : null;
        
        // Sauvegarder les filtres dans la session pour la redirection après suppression
        $_SESSION['documentation_filters'] = [
            'client_id' => $client_id,
            'site_id' => $site_id,
            'salle_id' => $salle_id
        ];

        // Récupération des données pour les filtres
        $clients = $this->clientModel->getAllClients();
        $sites = $client_id ? $this->siteModel->getSitesByClientId($client_id) : [];
        $salles = $site_id ? $this->roomModel->getRoomsBySiteId($site_id) : [];

        // Initialiser la liste de documentation vide
        $documentation_list = [];

        // Ne charger les documents que si un client est sélectionné
        if ($client_id) {
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
                    u.username as uploader_name
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
            ";

            $params = [];

            // Filtres - vérifier le client_id dans toutes les possibilités
            $query .= " AND (
                c.id = ? 
                OR c2.id = ? 
                OR c3.id = ? 
                OR s.client_id = ? 
                OR s2.client_id = ? 
                OR r.site_id IN (SELECT id FROM sites WHERE client_id = ?)
            )";
            $params[] = $client_id;
            $params[] = $client_id;
            $params[] = $client_id;
            $params[] = $client_id;
            $params[] = $client_id;
            $params[] = $client_id;

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
            'client_id' => $client_id,
            'site_id' => $site_id,
            'salle_id' => $salle_id
        ];

        // Passage des données à la vue
        require_once __DIR__ . '/../views/documentation/index.php';
    }

    /**
     * Affiche les documents d'un utilisateur
     */
    public function userDocuments($userId) {
        $this->checkAccess();
        
        $documents = $this->documentationModel->getUserDocuments($userId);
        require_once __DIR__ . '/../views/documentation/user_documents.php';
    }

    /**
     * Affiche le formulaire d'ajout de document
     */
    public function add() {
        $this->checkAccess();
        
        $clients = $this->clientModel->getAllClients();
        $sites = [];
        $rooms = [];
        $categories = $this->categoryModel->getAllCategories();
        
        if (isset($_GET['client_id'])) {
            $sites = $this->siteModel->getSitesByClientId($_GET['client_id']);
        }
        
        if (isset($_GET['site_id'])) {
            $rooms = $this->roomModel->getRoomsBySiteId($_GET['site_id']);
        }
        
        require_once __DIR__ . '/../views/documentation/add.php';
    }

    /**
     * Traite l'ajout d'un nouveau document (méthode store pour upload multiple)
     */
    public function store() {
        $this->checkAccess();
        
        // Log pour debug
        error_log("[DEBUG] DocumentationController::store - Début de la méthode");
        error_log("[DEBUG] DocumentationController::store - REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
        error_log("[DEBUG] DocumentationController::store - _POST: " . json_encode($_POST));
        error_log("[DEBUG] DocumentationController::store - _FILES: " . json_encode($_FILES));
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error_log("[ERROR] DocumentationController::store - Méthode non autorisée");
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Méthode non autorisée']);
            exit;
        }
        
        $clientId = isset($_POST['client_id']) ? (int)$_POST['client_id'] : null;
        $siteId = isset($_POST['site_id']) && !empty($_POST['site_id']) ? (int)$_POST['site_id'] : null;
        $roomId = isset($_POST['room_id']) && !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
        
        if (!$clientId) {
            error_log("[ERROR] DocumentationController::store - Client ID manquant");
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Client ID requis']);
            exit;
        }
        
        if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
            error_log("[ERROR] DocumentationController::store - Aucun fichier sélectionné");
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Aucun fichier sélectionné']);
            exit;
        }
        
        error_log("[DEBUG] DocumentationController::store - clientId: $clientId, siteId: $siteId, roomId: $roomId");
        
        $uploadedFiles = [];
        $errors = [];
        
        try {
            // Créer le répertoire de destination
            $uploadDir = __DIR__ . '/../../uploads/documentation/' . $clientId . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            error_log("[DEBUG] DocumentationController::store - Répertoire d'upload: $uploadDir");
        
        // Traiter chaque fichier
        foreach ($_FILES['files']['tmp_name'] as $index => $tmpName) {
            if ($_FILES['files']['error'][$index] !== UPLOAD_ERR_OK) {
                $errors[] = "Erreur lors de l'upload du fichier " . ($index + 1);
                continue;
            }
            
            $originalFileName = $_FILES['files']['name'][$index];
            $customName = isset($_POST['custom_names'][$index]) ? $_POST['custom_names'][$index] : $originalFileName;
            $visibleByClient = isset($_POST['visible_by_client'][$index]) ? 0 : 1; // 0 = visible, 1 = masqué
            $fileSize = $_FILES['files']['size'][$index];
            $fileTmpPath = $tmpName;
            
            error_log("[DEBUG] DocumentationController::store - Traitement fichier $index: $originalFileName");
            
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
            
            // Générer un nom de fichier unique en conservant le nom original
            $fileName = $this->generateUniqueFileName($uploadDir, $originalFileName);
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($fileTmpPath, $filePath)) {
                try {
                    $this->db->beginTransaction();
                    
                    // Insérer la pièce jointe
                    $query = "INSERT INTO pieces_jointes (
                                nom_fichier, nom_personnalise, chemin_fichier, type_fichier, taille_fichier, 
                                commentaire, masque_client, created_by, date_creation
                              ) VALUES (
                                :nom_fichier, :nom_personnalise, :chemin_fichier, :type_fichier, :taille_fichier,
                                :commentaire, :masque_client, :created_by, NOW()
                              )";
                    
                    $stmt = $this->db->prepare($query);
                    $result = $stmt->execute([
                        ':nom_fichier' => $originalFileName,
                        ':nom_personnalise' => $customName,
                        ':chemin_fichier' => 'uploads/documentation/' . $clientId . '/' . $fileName,
                        ':type_fichier' => $fileExtension,
                        ':taille_fichier' => $fileSize,
                        ':commentaire' => null,
                        ':masque_client' => $visibleByClient,
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
            error_log("[ERROR] DocumentationController::store - Erreur générale: " . $e->getMessage());
            error_log("[ERROR] DocumentationController::store - Stack trace: " . $e->getTraceAsString());
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Erreur de connexion: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * Traite l'ajout d'un nouveau document (ancienne méthode create)
     */
    public function create() {
        $this->checkAccess();
        
        error_log("[DEBUG] Documentation Create: Method called, REQUEST_METHOD = " . $_SERVER['REQUEST_METHOD']);
        error_log("[DEBUG] Documentation Create: _FILES = " . json_encode($_FILES));
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'nom_fichier' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'content' => $_POST['content'] ?? null,
                'chemin_fichier' => null,
                'type_fichier' => null,
                'taille_fichier' => 0,
                'client_id' => isset($_POST['client_id']) ? (int)$_POST['client_id'] : null,
                'site_id' => isset($_POST['site_id']) && !empty($_POST['site_id']) ? (int)$_POST['site_id'] : null,
                'room_id' => isset($_POST['room_id']) && !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null,
                'category_id' => isset($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                'masque_client' => isset($_POST['visible_by_client']) ? 0 : 1, // Inversé : 0 = visible, 1 = masqué
                'created_by' => $_SESSION['user']['id']
            ];

            if (empty($data['nom_fichier']) || empty($data['client_id']) || empty($data['category_id'])) {
                $_SESSION['error'] = "Les champs Titre, Client et Catégorie sont obligatoires.";
                $redirectParams = http_build_query([
                    'client_id' => $data['client_id'],
                    'site_id' => $data['site_id'],
                    'room_id' => $data['room_id'],
                    'form_category_id' => $data['category_id'],
                    'form_title' => $data['nom_fichier'],
                    'form_description' => $data['description'],
                    'form_visible_by_client' => $data['masque_client']
                ]);
                header('Location: ' . BASE_URL . 'documentation/add?' . $redirectParams);
                exit;
            }

            if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
                // --- Adopt InterventionController's style for path and validation ---
                $file = $_FILES['document_file'];
                $originalFileName = $file['name'];
                $fileTmpPath = $file['tmp_name'];
                $fileSize = $file['size'];
                // $fileError = $file['error']; // Already checked UPLOAD_ERR_OK
                
                error_log("[DEBUG] Documentation Upload: File detected - Name: " . $originalFileName . ", Size: " . $fileSize . ", Tmp: " . $fileTmpPath);

                // 1. Server-side File Size Check (limite du serveur)
                $maxFileSize = getServerMaxUploadSize();


                if ($fileSize > $maxFileSize) {
                    $_SESSION['error'] = "Le fichier est trop volumineux. Taille maximale autorisée: " . formatFileSize($maxFileSize) . ".";
                    // Redirect logic from before...
                    $this->redirectBackToFormWithError($data); // Helper function might be good
                    exit;
                }

                // 2. Server-side File Type Check
                require_once INCLUDES_PATH . '/FileUploadValidator.php';
                
                $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
                
                if (!FileUploadValidator::isExtensionAllowed($fileExtension, $this->db)) {
                    $_SESSION['error'] = "Ce format n'est pas accepté, rapprochez-vous de l'administrateur du site, ou utilisez un format compressé.";
                    $this->redirectBackToFormWithError($data);
                    exit;
                }

                // Path construction (assuming 'uploads' is sibling of 'controllers', 'models')
                // If 'uploads' is at project root, it should be /../../
                $baseUploadDir = __DIR__ . '/../../uploads/documents/'; // Adjusted to /../../ to reach root uploads
                $clientSpecificDir = $baseUploadDir . $data['client_id'] . '/';

                if (!is_dir($clientSpecificDir)) {
                    if (!mkdir($clientSpecificDir, 0775, true)) {
                        error_log("[ERROR] Documentation Upload: Failed to create client directory: " . $clientSpecificDir);
                        $_SESSION['error'] = "Erreur technique: Impossible de créer le répertoire de destination.";
                        $this->redirectBackToFormWithError($data);
                        exit;
                    }
                    error_log("[DEBUG] Documentation Upload: Created client directory: " . $clientSpecificDir);
                } else {
                     error_log("[DEBUG] Documentation Upload: Client directory exists: " . $clientSpecificDir);
                }
                
                if (!is_writable($clientSpecificDir)){
                     error_log("[ERROR] Documentation Upload: Client directory IS NOT WRITABLE: " . $clientSpecificDir);
                     $_SESSION['error'] = "Erreur technique: Le répertoire de destination n'est pas accessible en écriture.";
                     $this->redirectBackToFormWithError($data);
                     exit;
                } else {
                    error_log("[DEBUG] Documentation Upload: Client directory IS WRITABLE: " . $clientSpecificDir);
                }


                // Filename sanitization and uniqueness - from InterventionController
                $fileExt = pathinfo($originalFileName, PATHINFO_EXTENSION); // Keep original extension
                $fileNameOnly = pathinfo($originalFileName, PATHINFO_FILENAME);
                $fileNameOnly = str_replace(' ', '_', $fileNameOnly);
                $fileNameOnly = preg_replace('/[^a-zA-Z0-9_-]/', '', $fileNameOnly);
                
                $finalSanitizedName = $fileNameOnly;
                $counter = 1;
                while (file_exists($clientSpecificDir . $finalSanitizedName . '.' . $fileExt)) {
                    $finalSanitizedName = $fileNameOnly . '_' . $counter;
                    $counter++;
                }
                $finalFileNameWithExt = $finalSanitizedName . '.' . $fileExt;
                $targetPath = $clientSpecificDir . $finalFileNameWithExt;

                error_log("[DEBUG] Documentation Upload: Attempting move_uploaded_file. Source: '" . $fileTmpPath . "', Target: '" . $targetPath . "'");

                if (move_uploaded_file($fileTmpPath, $targetPath)) {
                    $data['chemin_fichier'] = 'uploads/documents/' . $data['client_id'] . '/' . $finalFileNameWithExt; // Relative path for DB
                    $data['taille_fichier'] = $fileSize;
                    $data['type_fichier'] = $fileExtension;
                    error_log("[DEBUG] Documentation Upload: Move successful for '" . $targetPath . "'. DB Path: " . $data['chemin_fichier']);
                    error_log("[DEBUG] Documentation Upload: File size: " . $data['taille_fichier'] . ", Type: " . $data['type_fichier']);
                    if (!file_exists($targetPath)) {
                         error_log("[WARN] Documentation Upload: File '" . $targetPath . "' NOT FOUND IMMEDIATELY AFTER successful move_uploaded_file! AV or other interference highly suspected.");
                    }
                } else {
                    $php_upload_error = $_FILES['document_file']['error']; // Error might be from original check if not UPLOAD_ERR_OK
                    $move_error_details = error_get_last();
                    error_log("[ERROR] Documentation Upload: move_uploaded_file FAILED for '" . $targetPath . "'. PHP Upload Error Code (from _FILES): " . $php_upload_error . ". System error during move: " . ($move_error_details['message'] ?? 'N/A'));
                    $_SESSION['error'] = "Erreur lors de l'upload du fichier (interne)."; // Simplified message like interventions
                    $this->redirectBackToFormWithError($data);
                    exit;
                }
                 // --- End of InterventionController style adoption ---
            } elseif (isset($_FILES['document_file']) && $_FILES['document_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                // This block handles other _FILES errors, e.g. UPLOAD_ERR_INI_SIZE if file too big for PHP's global settings
                $_SESSION['error'] = "Erreur lors de l'upload du fichier: Code " . $_FILES['document_file']['error'] . ". Vérifiez la taille du fichier.";
                $this->redirectBackToFormWithError($data); // Use helper
                exit;
            }
            // No specific 'else' needed for UPLOAD_ERR_NO_FILE, chemin_fichier remains null.
            } else {
                // Log pour debug si aucun fichier n'est détecté
                if (isset($_FILES['document_file'])) {
                    error_log("[DEBUG] Documentation Upload: File upload error - Error code: " . $_FILES['document_file']['error']);
                } else {
                    error_log("[DEBUG] Documentation Upload: No file detected in _FILES");
                }
            }

            if (empty($data['site_id'])) $data['site_id'] = null;
            if (empty($data['room_id'])) $data['room_id'] = null;

            // Insérer dans pieces_jointes (avec category_id et content)
            $insertQuery = "INSERT INTO pieces_jointes (nom_fichier, chemin_fichier, type_fichier, taille_fichier, category_id, content, masque_client, created_by, date_creation, commentaire) 
                           VALUES (:nom_fichier, :chemin_fichier, :type_fichier, :taille_fichier, :category_id, :content, :masque_client, :created_by, NOW(), :commentaire)";
            
            // Log des données avant insertion
            error_log("[DEBUG] Documentation Create: Data to insert: " . json_encode($data));
            
            $stmt = $this->db->prepare($insertQuery);
            $result = $stmt->execute([
                ':nom_fichier' => $data['nom_fichier'],
                ':chemin_fichier' => $data['chemin_fichier'],
                ':type_fichier' => $data['type_fichier'],
                ':taille_fichier' => $data['taille_fichier'],
                ':category_id' => $data['category_id'],
                ':content' => $data['content'],
                ':masque_client' => $data['masque_client'],
                ':created_by' => $data['created_by'],
                ':commentaire' => $data['description'] // Garder aussi dans commentaire pour compatibilité
            ]);

            if ($result) {
                $pieceJointeId = $this->db->lastInsertId();
                
                // Créer la liaison selon le niveau
                if ($data['room_id']) {
                    // Liaison avec une salle
                    $linkQuery = "INSERT INTO liaisons_pieces_jointes (piece_jointe_id, type_liaison, entite_id) 
                                  VALUES (:piece_jointe_id, 'documentation', :room_id)";
                    $linkStmt = $this->db->prepare($linkQuery);
                    $linkStmt->execute([
                        ':piece_jointe_id' => $pieceJointeId,
                        ':room_id' => $data['room_id']
                    ]);
                } elseif ($data['site_id']) {
                    // Liaison avec un site
                    $linkQuery = "INSERT INTO liaisons_pieces_jointes (piece_jointe_id, type_liaison, entite_id) 
                                  VALUES (:piece_jointe_id, 'documentation', :site_id)";
                    $linkStmt = $this->db->prepare($linkQuery);
                    $linkStmt->execute([
                        ':piece_jointe_id' => $pieceJointeId,
                        ':site_id' => $data['site_id']
                    ]);
                } else {
                    // Liaison avec le client
                    $linkQuery = "INSERT INTO liaisons_pieces_jointes (piece_jointe_id, type_liaison, entite_id) 
                                  VALUES (:piece_jointe_id, 'documentation', :client_id)";
                    $linkStmt = $this->db->prepare($linkQuery);
                    $linkStmt->execute([
                        ':piece_jointe_id' => $pieceJointeId,
                        ':client_id' => $data['client_id']
                    ]);
                }
                
                $_SESSION['success'] = "Document ajouté avec succès.";
                header('Location: ' . BASE_URL . 'documentation/view/' . $data['client_id']);
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de l'ajout du document à la base de données.";
                // If DB insert fails after a successful file move, unlink the orphaned file
                if (!empty($data['chemin_fichier']) && file_exists($targetPath)) { // Check if file was moved and path exists
                    error_log("[CLEANUP] Documentation Upload: DB insert failed. Unlinking orphaned file: " . $targetPath);
                    unlink($targetPath);
                }
                $redirectParams = http_build_query([
                    'client_id' => $data['client_id'],
                    'site_id' => $data['site_id'],
                    'room_id' => $data['room_id'],
                    'form_category_id' => $data['category_id'],
                    'form_title' => $data['nom_fichier'],
                    'form_description' => $data['description'],
                    'form_visible_by_client' => $data['masque_client']
                ]);
                header('Location: ' . BASE_URL . 'documentation/add?' . $redirectParams);
                exit;
            }
    }

    /**
     * Affiche le formulaire de modification d'un document existant.
     */
    public function edit($id) {
        $this->checkAccess();

        // Log pour debug
        error_log("[DEBUG] DocumentationController::edit - Attempting to edit document ID: " . $id);

        // Récupérer le document depuis pieces_jointes
        $query = "SELECT pj.*, lpj.type_liaison, lpj.entite_id 
                  FROM pieces_jointes pj 
                  LEFT JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id 
                  WHERE pj.id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$document) {
            error_log("[ERROR] DocumentationController::edit - Document not found for ID: " . $id);
            $_SESSION['error'] = "Document non trouvé.";
            header('Location: ' . BASE_URL . 'documentation/view/' . ($_GET['client_id'] ?? ''));
            exit;
        }

        // Log pour debug
        error_log("[DEBUG] DocumentationController::edit - Document found: " . json_encode($document));

        // Déterminer le client, site et room selon l'entité liée
        $client_id = null;
        $site_id = null;
        $room_id = null;
        
        if ($document['type_liaison'] === 'documentation') {
            $entite_id = $document['entite_id'];
            
            // Vérifier si c'est un client
            $clientQuery = "SELECT id FROM clients WHERE id = ?";
            $clientStmt = $this->db->prepare($clientQuery);
            $clientStmt->execute([$entite_id]);
            if ($clientStmt->fetch()) {
                $client_id = $entite_id;
            } else {
                // Vérifier si c'est un site
                $siteQuery = "SELECT id, client_id FROM sites WHERE id = ?";
                $siteStmt = $this->db->prepare($siteQuery);
                $siteStmt->execute([$entite_id]);
                $site = $siteStmt->fetch(PDO::FETCH_ASSOC);
                if ($site) {
                    $site_id = $entite_id;
                    $client_id = $site['client_id'];
                } else {
                    // C'est probablement une salle
                    $roomQuery = "SELECT r.id, r.site_id, s.client_id FROM rooms r 
                                 LEFT JOIN sites s ON r.site_id = s.id 
                                 WHERE r.id = ?";
                    $roomStmt = $this->db->prepare($roomQuery);
                    $roomStmt->execute([$entite_id]);
                    $room = $roomStmt->fetch(PDO::FETCH_ASSOC);
                    if ($room) {
                        $room_id = $entite_id;
                        $site_id = $room['site_id'];
                        $client_id = $room['client_id'];
                    }
                }
            }
        }

        // Récupérer les données nécessaires pour les listes déroulantes
        $clients = $this->clientModel->getAllClients();
        $categories = $this->categoryModel->getAllCategories();
        $sites = $client_id ? $this->siteModel->getSitesByClientId($client_id) : [];
        $rooms = $site_id ? $this->roomModel->getRoomsBySiteId($site_id) : [];
        
        // Récupérer les valeurs du formulaire depuis GET si elles existent (pour la persistance après rechargement client/site)
        $form_category_id = $_GET['form_category_id'] ?? $document['category_id'];
        $form_title = $_GET['form_title'] ?? $document['nom_fichier'];
        $form_description = $_GET['form_description'] ?? $document['description'];
        $form_visible_by_client_val = $_GET['form_visible_by_client'] ?? ($document['masque_client'] == 0 ? 1 : 0);
        // $form_content est géré via sessionStorage par JavaScript, et sera initialisé avec $document['content'] dans la vue.

        // Le chemin du fichier actuel pour l'affichage (non modifiable directement ici, géré par la logique d'update)
        $current_attachment_path = $document['chemin_fichier'];

        // Ajouter les informations de liaison au document pour la vue
        $document['client_id'] = $client_id;
        $document['site_id'] = $site_id;
        $document['room_id'] = $room_id;
        $document['title'] = $document['nom_fichier'];
        $document['visible_by_client'] = $document['masque_client'] == 0 ? 1 : 0;
        $document['attachment_path'] = $document['chemin_fichier'];

        require_once __DIR__ . '/../views/documentation/edit.php';
    }


    // Helper function to redirect back to add form with error and preserved data
    private function redirectBackToFormWithError($data_from_controller) {
        $redirectParams = http_build_query([
            'client_id' => $data_from_controller['client_id'] ?? null,
            'site_id' => $data_from_controller['site_id'] ?? null,
            'room_id' => $data_from_controller['room_id'] ?? null,
            'form_category_id' => $data_from_controller['category_id'] ?? null,
            'form_title' => $data_from_controller['title'] ?? '',
            'form_description' => $data_from_controller['description'] ?? '',
            'form_visible_by_client' => $data_from_controller['visible_by_client'] ?? '1'
            // Content is handled by sessionStorage on client-side
        ]);
        header('Location: ' . BASE_URL . 'documentation/add?' . $redirectParams);
        // Make sure to exit after header redirect
    }

    /**
     * Traite la mise à jour d'un document existant.
     */
    public function update($id) {
        $this->checkAccess();
        $documentId = (int)$id;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error'] = "Requête invalide.";
            header('Location: ' . BASE_URL . 'documentation/edit/' . $documentId);
            exit;
        }

        // Récupérer le document existant depuis pieces_jointes
        $query = "SELECT pj.*, lpj.type_liaison, lpj.entite_id 
                  FROM pieces_jointes pj 
                  LEFT JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id 
                  WHERE pj.id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$documentId]);
        $existingDocument = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existingDocument) {
            $_SESSION['error'] = "Document non trouvé pour la mise à jour.";
            header('Location: ' . BASE_URL . 'documentation');
            exit;
        }

        $data = [
            'id' => $documentId,
            'nom_fichier' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'content' => $_POST['content'] ?? null,
            'client_id' => isset($_POST['client_id']) ? (int)$_POST['client_id'] : null,
            'site_id' => isset($_POST['site_id']) && !empty($_POST['site_id']) ? (int)$_POST['site_id'] : null,
            'room_id' => isset($_POST['room_id']) && !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null,
            'category_id' => isset($_POST['category_id']) ? (int)$_POST['category_id'] : null,
            'masque_client' => isset($_POST['visible_by_client']) ? 0 : 1, // Inversé : 0 = visible, 1 = masqué
            'user_id' => $_SESSION['user']['id'] // For updated_by or similar if model supports it
        ];

        // Validate required fields
        if (empty($data['nom_fichier']) || empty($data['client_id']) || empty($data['category_id'])) {
            $_SESSION['error'] = "Les champs Titre, Client et Catégorie sont obligatoires.";
            $this->redirectBackToEditFormWithError($documentId, $data);
            exit;
        }
        
        $currentAttachmentPathOnServer = null;
        if (!empty($existingDocument['chemin_fichier'])) {
             // Convert relative DB path to absolute server path for file operations
            $currentAttachmentPathOnServer = __DIR__ . '/../../' . $existingDocument['chemin_fichier'];
        }
        $data['chemin_fichier'] = $existingDocument['chemin_fichier']; // Assume keeping old attachment initially

        $fileUploadedSuccessfully = false;
        $newlyUploadedFilePath = null;


        // 1. Handle explicit removal of attachment
        if (isset($_POST['remove_attachment']) && $_POST['remove_attachment'] == '1') {
            if ($currentAttachmentPathOnServer && file_exists($currentAttachmentPathOnServer)) {
                if (unlink($currentAttachmentPathOnServer)) {
                    error_log("[INFO] Documentation Update: Attachment " . $currentAttachmentPathOnServer . " removed by user request.");
                    $data['attachment_path'] = null;
                } else {
                    error_log("[ERROR] Documentation Update: Failed to remove attachment " . $currentAttachmentPathOnServer . " by user request.");
                    $_SESSION['error'] = "Erreur lors de la suppression de l'ancienne pièce jointe.";
                    // Potentially non-fatal, allow update of other fields to proceed or redirect
                }
            } else {
                 error_log("[INFO] Documentation Update: User requested removal, but no attachment found or path invalid: " . $currentAttachmentPathOnServer);
            }
             $currentAttachmentPathOnServer = null; // Mark as removed for subsequent logic
        }

        // 2. Handle new file upload (if any, and if not removed explicitly)
        if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['document_file'];
            // Perform validation (size, type) - reusing logic from create()
            $maxFileSize = getServerMaxUploadSize();

            if ($file['size'] > $maxFileSize) {
                $_SESSION['error'] = "Le nouveau fichier est trop volumineux. Taille maximale autorisée: " . formatFileSize($maxFileSize) . ".";
                $this->redirectBackToEditFormWithError($documentId, $data);
                exit;
            }

            require_once INCLUDES_PATH . '/FileUploadValidator.php';
            
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!FileUploadValidator::isExtensionAllowed($fileExtension, $this->db)) {
                $_SESSION['error'] = "Ce format n'est pas accepté, rapprochez-vous de l'administrateur du site, ou utilisez un format compressé.";
                $this->redirectBackToEditFormWithError($documentId, $data);
                exit;
            }

            // If a new file is valid, delete the old one (if it existed and wasn't already removed)
            if ($currentAttachmentPathOnServer && file_exists($currentAttachmentPathOnServer)) {
                if (unlink($currentAttachmentPathOnServer)) {
                    error_log("[INFO] Documentation Update: Old attachment " . $currentAttachmentPathOnServer . " removed to be replaced by new upload.");
                } else {
                    error_log("[ERROR] Documentation Update: Failed to remove old attachment " . $currentAttachmentPathOnServer . " before new upload.");
                    // Decide if this is fatal. For now, proceed with new upload.
                }
            }

            // Proceed with upload logic (similar to create())
            $baseUploadDir = __DIR__ . '/../../uploads/documents/';
            $clientSpecificDir = $baseUploadDir . $data['client_id'] . '/';
            if (!is_dir($clientSpecificDir)) {
                if (!mkdir($clientSpecificDir, 0775, true)) {
                    $_SESSION['error'] = "Erreur technique: Impossible de créer le répertoire de destination pour la nouvelle pièce jointe.";
                    $this->redirectBackToEditFormWithError($documentId, $data);
                    exit;
                }
            }

            $originalFileName = $file['name'];
            $fileExt = pathinfo($originalFileName, PATHINFO_EXTENSION);
            $fileNameOnly = pathinfo($originalFileName, PATHINFO_FILENAME);
            $fileNameOnly = str_replace(' ', '_', $fileNameOnly);
            $fileNameOnly = preg_replace('/[^a-zA-Z0-9_-]/', '', $fileNameOnly);
            
            $finalSanitizedName = $fileNameOnly;
            $counter = 1;
            while (file_exists($clientSpecificDir . $finalSanitizedName . '.' . $fileExt)) {
                $finalSanitizedName = $fileNameOnly . '_' . $counter;
                $counter++;
            }
            $finalFileNameWithExt = $finalSanitizedName . '.' . $fileExt;
            $newlyUploadedFilePath = $clientSpecificDir . $finalFileNameWithExt; // Absolute path

            if (move_uploaded_file($file['tmp_name'], $newlyUploadedFilePath)) {
                $data['chemin_fichier'] = 'uploads/documents/' . $data['client_id'] . '/' . $finalFileNameWithExt; // Relative path for DB
                $data['taille_fichier'] = $file['size'];
                $data['type_fichier'] = $fileExtension;
                $fileUploadedSuccessfully = true;
                error_log("[INFO] Documentation Update: New file uploaded successfully: " . $newlyUploadedFilePath);
            } else {
                $_SESSION['error'] = "Erreur lors de l'upload du nouveau fichier.";
                $this->redirectBackToEditFormWithError($documentId, $data);
                exit;
            }
        } elseif (isset($_FILES['document_file']) && $_FILES['document_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Handle other _FILES errors if a file was attempted but failed for other reasons
            $_SESSION['error'] = "Erreur lors de l'upload du nouveau fichier: Code " . $_FILES['document_file']['error'];
            $this->redirectBackToEditFormWithError($documentId, $data);
            exit;
        }
        // If no new file and not removed, $data['attachment_path'] retains $existingDocument['attachment_path']

        // Ensure site_id and room_id are null if empty (consistency with create)
        if (empty($data['site_id'])) $data['site_id'] = null;
        if (empty($data['room_id'])) $data['room_id'] = null;

        // Log pour debug avant la mise à jour
        error_log("[DEBUG] DocumentationController::update - About to update document ID: " . $documentId);
        error_log("[DEBUG] DocumentationController::update - Final data: " . json_encode($data));

        try {
            // Mettre à jour la table pieces_jointes (avec category_id et content)
            $updateQuery = "UPDATE pieces_jointes 
                           SET nom_fichier = :nom_fichier, 
                               chemin_fichier = :chemin_fichier, 
                               type_fichier = :type_fichier, 
                               taille_fichier = :taille_fichier, 
                               category_id = :category_id,
                               content = :content,
                               masque_client = :masque_client,
                               commentaire = :commentaire
                           WHERE id = :id";
            
            $stmt = $this->db->prepare($updateQuery);
            $result = $stmt->execute([
                ':nom_fichier' => $data['nom_fichier'],
                ':chemin_fichier' => $data['chemin_fichier'],
                ':type_fichier' => $data['type_fichier'] ?? $existingDocument['type_fichier'],
                ':taille_fichier' => $data['taille_fichier'] ?? $existingDocument['taille_fichier'],
                ':category_id' => $data['category_id'],
                ':content' => $data['content'],
                ':masque_client' => $data['masque_client'],
                ':commentaire' => $data['description'],
                ':id' => $documentId
            ]);

            if ($result) {
                // Mettre à jour la liaison si nécessaire
                $this->updateDocumentLiaison($documentId, $data);
                
                $_SESSION['success'] = "Document mis à jour avec succès.";
                // Redirect to the view page of the client, or index if client_id is somehow missing
                $redirectClientId = $data['client_id'];
                if ($redirectClientId) {
                     header('Location: ' . BASE_URL . 'documentation/view/' . $redirectClientId);
                } else {
                     header('Location: ' . BASE_URL . 'documentation');
                }
                exit;
            } else {
                $_SESSION['error'] = "Erreur lors de la mise à jour du document dans la base de données.";
                // If DB update failed AND a new file was successfully uploaded, attempt to delete the orphaned new file
                if ($fileUploadedSuccessfully && $newlyUploadedFilePath && file_exists($newlyUploadedFilePath)) {
                    error_log("[CLEANUP] Documentation Update: DB update failed. Unlinking orphaned newly uploaded file: " . $newlyUploadedFilePath);
                    unlink($newlyUploadedFilePath);
                }
                $this->redirectBackToEditFormWithError($documentId, $data);
                exit;
            }
        } catch (Exception $e) {
            error_log("[ERROR] DocumentationController::update - Exception caught: " . $e->getMessage());
            error_log("[ERROR] DocumentationController::update - Exception trace: " . $e->getTraceAsString());
            $_SESSION['error'] = "Erreur lors de la mise à jour du document : " . $e->getMessage();
            $this->redirectBackToEditFormWithError($documentId, $data);
            exit;
        }
    }

    /**
     * Met à jour la liaison d'un document
     */
    private function updateDocumentLiaison($documentId, $data) {
        // Supprimer l'ancienne liaison
        $deleteQuery = "DELETE FROM liaisons_pieces_jointes WHERE piece_jointe_id = ?";
        $deleteStmt = $this->db->prepare($deleteQuery);
        $deleteStmt->execute([$documentId]);
        
        // Créer la nouvelle liaison selon le niveau
        if ($data['room_id']) {
            // Liaison avec une salle
            $linkQuery = "INSERT INTO liaisons_pieces_jointes (piece_jointe_id, type_liaison, entite_id) 
                          VALUES (:piece_jointe_id, 'documentation', :room_id)";
            $linkStmt = $this->db->prepare($linkQuery);
            $linkStmt->execute([
                ':piece_jointe_id' => $documentId,
                ':room_id' => $data['room_id']
            ]);
        } elseif ($data['site_id']) {
            // Liaison avec un site
            $linkQuery = "INSERT INTO liaisons_pieces_jointes (piece_jointe_id, type_liaison, entite_id) 
                          VALUES (:piece_jointe_id, 'documentation', :site_id)";
            $linkStmt = $this->db->prepare($linkQuery);
            $linkStmt->execute([
                ':piece_jointe_id' => $documentId,
                ':site_id' => $data['site_id']
            ]);
        } else {
            // Liaison avec le client
            $linkQuery = "INSERT INTO liaisons_pieces_jointes (piece_jointe_id, type_liaison, entite_id) 
                          VALUES (:piece_jointe_id, 'documentation', :client_id)";
            $linkStmt = $this->db->prepare($linkQuery);
            $linkStmt->execute([
                ':piece_jointe_id' => $documentId,
                ':client_id' => $data['client_id']
            ]);
        }
    }

    // Helper function to redirect back to edit form with error and preserved data
    private function redirectBackToEditFormWithError($documentId, $data_from_controller) {
        $redirectParams = http_build_query([
            // Use 'client_id' etc. from $data_from_controller to preserve what user *attempted* to set
            'client_id' => $data_from_controller['client_id'] ?? null, 
            'site_id' => $data_from_controller['site_id'] ?? null,
            'room_id' => $data_from_controller['room_id'] ?? null,
            'form_category_id' => $data_from_controller['category_id'] ?? null,
            'form_title' => $data_from_controller['nom_fichier'] ?? '',
            'form_description' => $data_from_controller['description'] ?? '',
            'form_visible_by_client' => $data_from_controller['masque_client'] == 0 ? 1 : 0
            // Content is handled by sessionStorage on client-side
        ]);
        // The base URL should be the edit page for this specific document
        header('Location: ' . BASE_URL . 'documentation/edit/' . $documentId . '?' . $redirectParams);
        exit; // Always exit after a header redirect
    }

    /**
     * Supprime un document
     */
    public function delete($id) {
        // Vérifier les permissions de suppression
        checkDocumentationDeleteAccess();
        
        // Récupérer le document depuis pieces_jointes
        $query = "SELECT pj.*, lpj.type_liaison, lpj.entite_id 
                  FROM pieces_jointes pj 
                  LEFT JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id 
                  WHERE pj.id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$document) {
            $_SESSION['error'] = "Document non trouvé.";
            header('Location: ' . BASE_URL . 'documentation');
            exit;
        }

        // Déterminer le client pour la redirection
        $client_id = null;
        if ($document['type_liaison'] === 'documentation') {
            $entite_id = $document['entite_id'];
            
            // Vérifier si c'est un client
            $clientQuery = "SELECT id FROM clients WHERE id = ?";
            $clientStmt = $this->db->prepare($clientQuery);
            $clientStmt->execute([$entite_id]);
            if ($clientStmt->fetch()) {
                $client_id = $entite_id;
            } else {
                // Vérifier si c'est un site
                $siteQuery = "SELECT client_id FROM sites WHERE id = ?";
                $siteStmt = $this->db->prepare($siteQuery);
                $siteStmt->execute([$entite_id]);
                $site = $siteStmt->fetch(PDO::FETCH_ASSOC);
                if ($site) {
                    $client_id = $site['client_id'];
                } else {
                    // C'est probablement une salle
                    $roomQuery = "SELECT r.site_id, s.client_id FROM rooms r 
                                 LEFT JOIN sites s ON r.site_id = s.id 
                                 WHERE r.id = ?";
                    $roomStmt = $this->db->prepare($roomQuery);
                    $roomStmt->execute([$entite_id]);
                    $room = $roomStmt->fetch(PDO::FETCH_ASSOC);
                    if ($room) {
                        $client_id = $room['client_id'];
                    }
                }
            }
        }

        // Supprimer le fichier physique s'il existe
        if (!empty($document['chemin_fichier'])) {
            $filePath = __DIR__ . '/../../' . $document['chemin_fichier'];
            if (file_exists($filePath)) {
                if (!unlink($filePath)) {
                    error_log("[ERROR] Documentation Delete: Failed to delete file: " . $filePath);
                    $_SESSION['error'] = "Erreur lors de la suppression du fichier physique.";
                    if ($client_id) {
                        header('Location: ' . BASE_URL . 'documentation/view/' . $client_id);
                    } else {
                        header('Location: ' . BASE_URL . 'documentation');
                    }
                    exit;
                }
                error_log("[INFO] Documentation Delete: File deleted successfully: " . $filePath);
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
        
        // Rediriger vers la page de documentation avec les filtres conservés
        $redirectUrl = BASE_URL . 'documentation';
        $params = [];
        
        // Récupérer les filtres depuis la session ou les paramètres
        if (isset($_SESSION['documentation_filters'])) {
            $filters = $_SESSION['documentation_filters'];
        } else {
            // Essayer de récupérer depuis les paramètres de requête
            $filters = [
                'client_id' => $_GET['client_id'] ?? null,
                'site_id' => $_GET['site_id'] ?? null,
                'salle_id' => $_GET['salle_id'] ?? null
            ];
        }
        
        // Ajouter les filtres à l'URL
        if (!empty($filters['client_id'])) {
            $params['client_id'] = $filters['client_id'];
        }
        if (!empty($filters['site_id'])) {
            $params['site_id'] = $filters['site_id'];
        }
        if (!empty($filters['salle_id'])) {
            $params['salle_id'] = $filters['salle_id'];
        }
        
        // Construire l'URL avec les paramètres
        if (!empty($params)) {
            $redirectUrl .= '?' . http_build_query($params);
        }
        
        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Ajoute plusieurs pièces jointes de documentation (Drag & Drop)
     */
    public function addMultipleAttachments() {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
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
            // Récupérer les données du formulaire
            $clientId = $_POST['client_id'] ?? null;
            $siteId = $_POST['site_id'] ?? null;
            $roomId = $_POST['room_id'] ?? null;
            $categoryId = $_POST['category_id'] ?? null;

            if (!$clientId || !$categoryId) {
                throw new Exception("Client et catégorie sont obligatoires");
            }

            // Vérifier qu'il y a des fichiers
            if (!isset($_FILES['attachments']) || empty($_FILES['attachments']['name'][0])) {
                throw new Exception("Aucun fichier à uploader");
            }

            require_once INCLUDES_PATH . '/FileUploadValidator.php';
            
            $uploadedFiles = [];
            $errors = [];
            
            // Traiter chaque fichier
            foreach ($_FILES['attachments']['tmp_name'] as $index => $tmpName) {
                if ($_FILES['attachments']['error'][$index] !== UPLOAD_ERR_OK) {
                    $errors[] = "Erreur lors de l'upload du fichier " . ($index + 1);
                    continue;
                }

                $originalFileName = $_FILES['attachments']['name'][$index];
                $fileSize = $_FILES['attachments']['size'][$index];
                $fileTmpPath = $tmpName;

                // Vérifier la taille du fichier
                $maxFileSize = getServerMaxUploadSize();
                if ($fileSize > $maxFileSize) {
                    $errors[] = "Le fichier '$originalFileName' est trop volumineux (max " . formatFileSize($maxFileSize) . ")";
                    continue;
                }

                // Vérifier l'extension
                $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
                if (!FileUploadValidator::isExtensionAllowed($fileExtension, $this->db)) {
                    $errors[] = "Le format du fichier '$originalFileName' n'est pas accepté";
                    continue;
                }

                // Créer le répertoire de destination
                $uploadDir = __DIR__ . '/../../uploads/documents/' . $clientId;
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Générer un nom de fichier unique en gardant le nom original
                $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalFileName);
                
                // Vérifier si le fichier existe déjà et ajouter un suffixe si nécessaire
                $baseName = $fileName;
                $counter = 0;
                
                do {
                    $finalFileName = $counter === 0 ? $baseName : 
                                    pathinfo($baseName, PATHINFO_FILENAME) . '_' . $counter . '.' . $fileExtension;
                    $filePath = $uploadDir . '/' . $finalFileName;
                    $counter++;
                } while (file_exists($filePath));

                // Déplacer le fichier
                if (move_uploaded_file($fileTmpPath, $filePath)) {
                    // Récupérer les options pour ce fichier
                    $description = $_POST['file_description'][$index] ?? null;
                    $masqueClient = isset($_POST['file_masque_client'][$index]) ? 1 : 0;

                    // Préparer les données pour la base
                    $data = [
                        'nom_fichier' => $originalFileName,
                        'chemin_fichier' => 'uploads/documents/' . $clientId . '/' . $finalFileName,
                        'type_fichier' => $fileExtension,
                        'taille_fichier' => $fileSize,
                        'category_id' => $categoryId,
                        'content' => null,
                        'commentaire' => $description,
                        'masque_client' => $masqueClient,
                        'created_by' => $_SESSION['user']['id']
                    ];

                    // Insérer dans pieces_jointes
                    $insertQuery = "INSERT INTO pieces_jointes (nom_fichier, chemin_fichier, type_fichier, taille_fichier, category_id, content, masque_client, created_by, date_creation, commentaire) 
                                   VALUES (:nom_fichier, :chemin_fichier, :type_fichier, :taille_fichier, :category_id, :content, :masque_client, :created_by, NOW(), :commentaire)";
                    
                    $stmt = $this->db->prepare($insertQuery);
                    $result = $stmt->execute([
                        ':nom_fichier' => $data['nom_fichier'],
                        ':chemin_fichier' => $data['chemin_fichier'],
                        ':type_fichier' => $data['type_fichier'],
                        ':taille_fichier' => $data['taille_fichier'],
                        ':category_id' => $data['category_id'],
                        ':content' => $data['content'],
                        ':masque_client' => $data['masque_client'],
                        ':created_by' => $data['created_by'],
                        ':commentaire' => $data['commentaire']
                    ]);

                    if ($result) {
                        $pieceJointeId = $this->db->lastInsertId();
                        
                        // Créer la liaison selon le niveau
                        if ($roomId) {
                            // Liaison avec une salle
                            $linkQuery = "INSERT INTO liaisons_pieces_jointes (piece_jointe_id, type_liaison, entite_id) 
                                          VALUES (:piece_jointe_id, 'documentation', :room_id)";
                            $linkStmt = $this->db->prepare($linkQuery);
                            $linkStmt->execute([
                                ':piece_jointe_id' => $pieceJointeId,
                                ':room_id' => $roomId
                            ]);
                        } elseif ($siteId) {
                            // Liaison avec un site
                            $linkQuery = "INSERT INTO liaisons_pieces_jointes (piece_jointe_id, type_liaison, entite_id) 
                                          VALUES (:piece_jointe_id, 'documentation', :site_id)";
                            $linkStmt = $this->db->prepare($linkQuery);
                            $linkStmt->execute([
                                ':piece_jointe_id' => $pieceJointeId,
                                ':site_id' => $siteId
                            ]);
                        } else {
                            // Liaison avec le client
                            $linkQuery = "INSERT INTO liaisons_pieces_jointes (piece_jointe_id, type_liaison, entite_id) 
                                          VALUES (:piece_jointe_id, 'documentation', :client_id)";
                            $linkStmt = $this->db->prepare($linkQuery);
                            $linkStmt->execute([
                                ':piece_jointe_id' => $pieceJointeId,
                                ':client_id' => $clientId
                            ]);
                        }
                        
                        $uploadedFiles[] = $originalFileName;
                    } else {
                        $errors[] = "Erreur lors de l'insertion en base pour '$originalFileName'";
                    }
                } else {
                    $errors[] = "Erreur lors du déplacement du fichier '$originalFileName'";
                }
            }

            // Préparer la réponse
            $response = [
                'success' => true,
                'uploaded_files' => $uploadedFiles,
                'errors' => $errors
            ];

            if (!empty($errors)) {
                $response['message'] = count($uploadedFiles) . " fichier(s) uploadé(s), " . count($errors) . " erreur(s)";
            } else {
                $response['message'] = count($uploadedFiles) . " fichier(s) uploadé(s) avec succès !";
            }

            header('Content-Type: application/json');
            echo json_encode($response);

        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Supprime un document via AJAX
     */
    /**
     * Supprime une pièce jointe de documentation
     * Utilise AttachmentService pour centraliser la logique
     */
    public function deleteAttachment($id) {
        $this->checkAccess();
        
        try {
            // Récupérer le document pour obtenir le type de liaison
            $query = "SELECT pj.*, lpj.type_liaison, lpj.entite_id 
                      FROM pieces_jointes pj 
                      LEFT JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id 
                      WHERE pj.id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$document) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Document non trouvé.']);
                exit;
            }

            // Utiliser AttachmentService pour gérer la suppression
            $attachmentService = new AttachmentService($this->db);
            $typeLiaison = $document['type_liaison'] ?? 'documentation_client';
            $entityId = $document['entite_id'] ?? null;
            
            $attachmentService->delete($id, $typeLiaison, $entityId);
            
            echo json_encode(['success' => true, 'message' => 'Document supprimé avec succès.']);

        } catch (Exception $e) {
            custom_log("Erreur lors de la suppression du document : " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression du document : ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Affiche les documents d'un client par site et salle
     */
    public function view($clientId) {
        $this->checkAccess();
        
        // Récupération des filtres
        $siteId = isset($_GET['site_id']) ? (int)$_GET['site_id'] : null;
        $salleId = isset($_GET['salle_id']) ? (int)$_GET['salle_id'] : null;
        
        // Récupération des données pour les filtres
        $client = $this->clientModel->getClientById($clientId);
        $sites = $this->siteModel->getSitesByClientId($clientId);
        $rooms = $siteId ? $this->roomModel->getRoomsBySiteId($siteId) : [];
        
        // Requête pour récupérer les pièces jointes de documentation
        $query = "
            SELECT 
                pj.*,
                COALESCE(pj.content, pj.commentaire) as description,
                c.name as client_nom,
                s.name as site_nom,
                r.name as salle_nom,
                c.id as client_id,
                s.id as site_id,
                r.id as salle_id
            FROM pieces_jointes pj
            INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
            LEFT JOIN clients c ON (lpj.type_liaison = 'documentation' AND lpj.entite_id = c.id)
            LEFT JOIN sites s ON (lpj.type_liaison = 'documentation' AND lpj.entite_id = s.id)
            LEFT JOIN rooms r ON (lpj.type_liaison = 'documentation' AND lpj.entite_id = r.id)
            WHERE lpj.type_liaison = 'documentation' 
            AND (c.id = ? OR s.client_id = ? OR r.site_id IN (SELECT id FROM sites WHERE client_id = ?))
        ";

        $params = [$clientId, $clientId, $clientId];

        // Filtres
        if ($siteId) {
            $query .= " AND (s.id = ? OR r.site_id = ?)";
            $params[] = $siteId;
            $params[] = $siteId;
        }

        if ($salleId) {
            $query .= " AND r.id = ?";
            $params[] = $salleId;
        }

        $query .= " ORDER BY s.name, r.name, pj.date_creation DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $documentation_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Préparer les données pour la vue
        $filters = [
            'site_id' => $siteId,
            'salle_id' => $salleId
        ];
        
        require_once __DIR__ . '/../views/documentation/view.php';
    }

    /**
     * Récupère les sites d'un client spécifique via AJAX
     */
    public function get_sites() {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Non autorisé']);
            exit;
        }

        if (!isset($_GET['client_id'])) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Client ID is required']);
            exit;
        }

        $client_id = (int)$_GET['client_id'];

        try {
            $sites = $this->siteModel->getSitesByClientId($client_id);
            header('Content-Type: application/json');
            echo json_encode($sites);
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
        }
    }

    /**
     * Récupère les salles d'un site spécifique via AJAX
     */
    public function get_rooms() {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Non autorisé']);
            return;
        }

        $siteId = $_GET['site_id'] ?? null;
        
        if (!$siteId) {
            http_response_code(400);
            echo json_encode(['error' => 'ID du site manquant']);
            return;
        }

        try {
            $rooms = $this->roomModel->getRoomsBySiteId($siteId);
            echo json_encode($rooms);
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des salles : " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la récupération des salles']);
        }
    }

    /**
     * Récupère les pièces jointes pour un client avec filtres
     */
    public function getAttachments() {
        $this->checkAccess();
        
        $clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;
        $siteId = isset($_GET['site_id']) ? (int)$_GET['site_id'] : null;
        $roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : null;
        $typeFilter = isset($_GET['type_filter']) ? $_GET['type_filter'] : null;
        
        if (!$clientId) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Client ID requis']);
            exit;
        }
        
        $query = "SELECT pj.*, s.name as site_name, r.name as room_name
                  FROM pieces_jointes pj
                  INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                  LEFT JOIN sites s ON lpj.entite_id = s.id AND lpj.type_liaison = 'documentation_site'
                  LEFT JOIN rooms r ON lpj.entite_id = r.id AND lpj.type_liaison = 'documentation_room'
                  WHERE (lpj.type_liaison = 'documentation_client' AND lpj.entite_id = :client_id1)
                     OR (lpj.type_liaison = 'documentation_site' AND s.client_id = :client_id2)
                     OR (lpj.type_liaison = 'documentation_room' AND r.site_id IN (SELECT id FROM sites WHERE client_id = :client_id3))";
        
        $params = [
            ':client_id1' => $clientId,
            ':client_id2' => $clientId,
            ':client_id3' => $clientId
        ];
        
        if ($siteId) {
            $query .= " AND ((lpj.type_liaison = 'documentation_site' AND lpj.entite_id = :site_id) OR (lpj.type_liaison = 'documentation_room' AND r.site_id = :site_id_room))";
            $params[':site_id'] = $siteId;
            $params[':site_id_room'] = $siteId;
        }
        
        if ($roomId) {
            $query .= " AND lpj.type_liaison = 'documentation_room' AND lpj.entite_id = :room_id";
            $params[':room_id'] = $roomId;
        }
        
        if ($typeFilter) {
            switch ($typeFilter) {
                case 'pdf':
                    $query .= " AND pj.type_fichier = 'pdf'";
                    break;
                case 'image':
                    $query .= " AND pj.type_fichier IN ('jpg', 'jpeg', 'png', 'gif')";
                    break;
                case 'excel':
                    $query .= " AND pj.type_fichier IN ('xls', 'xlsx')";
                    break;
                case 'word':
                    $query .= " AND pj.type_fichier IN ('doc', 'docx')";
                    break;
                case 'other':
                    $query .= " AND pj.type_fichier NOT IN ('pdf', 'jpg', 'jpeg', 'png', 'gif', 'xls', 'xlsx', 'doc', 'docx')";
                    break;
            }
        }
        
        $query .= " ORDER BY pj.date_creation DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode(['attachments' => $attachments]);
        exit;
    }

    /**
     * Upload de pièces jointes pour la documentation
     */
    /**
     * Upload de pièces jointes pour la documentation
     * Utilise AttachmentService pour centraliser la logique
     */
    public function uploadAttachment() {
        $this->checkAccess();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Méthode non autorisée']);
            exit;
        }
        
        $clientId = isset($_POST['client_id']) ? (int)$_POST['client_id'] : null;
        $siteId = isset($_POST['site_id']) ? (int)$_POST['site_id'] : null;
        $roomId = isset($_POST['room_id']) ? (int)$_POST['room_id'] : null;
        $masqueClient = isset($_POST['masque_client']) ? 1 : 0;
        
        if (!$clientId) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Client ID requis']);
            exit;
        }
        
        if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Aucun fichier sélectionné']);
            exit;
        }

        try {
            // Déterminer le type de liaison et l'ID de l'entité
            if ($roomId) {
                $typeLiaison = 'documentation_room';
                $entityId = $roomId;
            } elseif ($siteId) {
                $typeLiaison = 'documentation_site';
                $entityId = $siteId;
            } else {
                $typeLiaison = 'documentation_client';
                $entityId = $clientId;
            }

            // Utiliser AttachmentService pour gérer l'upload
            // Note: Le répertoire sera 'documentation/{clientId}' grâce à la logique dans getUploadDirectory
            $attachmentService = new AttachmentService($this->db);
            
            // Préparer les options
            $fileCount = count($_FILES['files']['name']);
            $options = [
                'masque_client' => array_fill(0, $fileCount, $masqueClient)
            ];

            // Upload des fichiers (utilise clientId pour le répertoire, mais typeLiaison pour la liaison)
            // On doit passer clientId comme entityId pour le répertoire, mais créer la bonne liaison après
            $result = $attachmentService->upload(
                $typeLiaison,
                $clientId, // Le répertoire est toujours basé sur clientId pour la documentation
                $_FILES['files'],
                $options,
                $_SESSION['user']['id']
            );

            // Mettre à jour les liaisons pour utiliser le bon entityId (roomId, siteId ou clientId)
            if ($result['success'] && !empty($result['attachment_ids'])) {
                foreach ($result['attachment_ids'] as $attachmentId) {
                    // Supprimer la liaison créée par défaut
                    $deleteQuery = "DELETE FROM liaisons_pieces_jointes WHERE piece_jointe_id = :attachment_id";
                    $deleteStmt = $this->db->prepare($deleteQuery);
                    $deleteStmt->execute([':attachment_id' => $attachmentId]);
                    
                    // Créer la bonne liaison
                    $linkQuery = "INSERT INTO liaisons_pieces_jointes (piece_jointe_id, type_liaison, entite_id) 
                                 VALUES (:piece_jointe_id, :type_liaison, :entity_id)";
                    $linkStmt = $this->db->prepare($linkQuery);
                    $linkStmt->execute([
                        ':piece_jointe_id' => $attachmentId,
                        ':type_liaison' => $typeLiaison,
                        ':entity_id' => $entityId
                    ]);
                }
            }

            // Retourner le résultat
            header('Content-Type: application/json');
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'uploaded_files' => $result['uploaded_files'],
                    'errors' => $result['errors']
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => !empty($result['errors']) ? implode(', ', $result['errors']) : 'Aucun fichier n\'a pu être uploadé',
                    'errors' => $result['errors']
                ]);
            }

        } catch (Exception $e) {
            custom_log("Erreur lors de l'upload de documentation : " . $e->getMessage(), 'ERROR');
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * Télécharge une pièce jointe de documentation
     * Utilise AttachmentService pour centraliser la logique
     */
    public function download($pieceJointeId) {
        $this->checkAccess();

        try {
            // Utiliser AttachmentService pour gérer le téléchargement
            $attachmentService = new AttachmentService($this->db);
            $attachmentService->download($pieceJointeId, true);

        } catch (Exception $e) {
            custom_log("Erreur lors du téléchargement de la pièce jointe : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors du téléchargement : " . $e->getMessage();
            header('Location: ' . BASE_URL . 'documentation');
            exit;
        }
    }

    /**
     * Aperçu d'une pièce jointe de documentation
     * Utilise AttachmentService pour centraliser la logique
     */
    public function preview($attachmentId) {
        $this->checkAccess();

        try {
            // Utiliser AttachmentService pour gérer l'aperçu
            $attachmentService = new AttachmentService($this->db);
            $attachmentService->preview($attachmentId);

        } catch (Exception $e) {
            custom_log("Erreur lors de l'aperçu de la pièce jointe : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de l'aperçu : " . $e->getMessage();
            header('Location: ' . BASE_URL . 'documentation');
            exit;
        }
    }

    /**
     * Change la visibilité d'une pièce jointe de documentation
     */
    public function toggleAttachmentVisibility($pieceJointeId) {
        $this->checkAccess();

        try {
            // Récupérer les informations de la pièce jointe
            $query = "SELECT id, nom_fichier, nom_personnalise, chemin_fichier, type_fichier, taille_fichier, commentaire, date_creation, masque_client, type_id, created_by FROM pieces_jointes WHERE id = :piece_jointe_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':piece_jointe_id' => $pieceJointeId]);
            $pieceJointe = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pieceJointe) {
                throw new Exception("Pièce jointe non trouvée");
            }

            // Inverser la visibilité
            $newVisibility = $pieceJointe['masque_client'] == 1 ? 0 : 1;
            
            // Mettre à jour dans la base de données
            $updateQuery = "UPDATE pieces_jointes SET masque_client = :masque_client WHERE id = :id";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->execute([
                ':masque_client' => $newVisibility,
                ':id' => $pieceJointeId
            ]);
            
            $_SESSION['success'] = $newVisibility == 1 ? 
                "Document masqué aux clients" : 
                "Document rendu visible aux clients";

        } catch (Exception $e) {
            custom_log("Erreur lors du changement de visibilité : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors du changement de visibilité : " . $e->getMessage();
        }

        // Rediriger vers la page de documentation avec les filtres conservés
        $redirectUrl = BASE_URL . 'documentation';
        $params = [];
        
        // Récupérer les filtres depuis la session ou les paramètres
        if (isset($_SESSION['documentation_filters'])) {
            $filters = $_SESSION['documentation_filters'];
        } else {
            $filters = [
                'client_id' => $_GET['client_id'] ?? null,
                'site_id' => $_GET['site_id'] ?? null,
                'salle_id' => $_GET['salle_id'] ?? null
            ];
        }
        
        // Ajouter les filtres à l'URL
        if (!empty($filters['client_id'])) {
            $params['client_id'] = $filters['client_id'];
        }
        if (!empty($filters['site_id'])) {
            $params['site_id'] = $filters['site_id'];
        }
        if (!empty($filters['salle_id'])) {
            $params['salle_id'] = $filters['salle_id'];
        }
        
        // Construire l'URL avec les paramètres
        if (!empty($params)) {
            $redirectUrl .= '?' . http_build_query($params);
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
            // Vérifier que le document existe
            $query = "SELECT id, nom_fichier, nom_personnalise, chemin_fichier, type_fichier, taille_fichier, commentaire, date_creation, masque_client, type_id, created_by FROM pieces_jointes WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$attachmentId]);
            $pieceJointe = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$pieceJointe) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Document non trouvé']);
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

} 