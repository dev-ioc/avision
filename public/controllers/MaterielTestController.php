<?php
require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';

class MaterielTestController {
    use AccessControlTrait;
    private $db;
    private $materielModel;
    private $clientModel;
    private $siteModel;
    private $roomModel;
    private $documentationModel;

    public function __construct() {
        // Récupérer l'instance de la base de données
        $config = Config::getInstance();
        $this->db = $config->getDb();
        
        // Initialiser les modèles
        require_once MODELS_PATH . '/MaterielModel.php';
        require_once MODELS_PATH . '/ClientModel.php';
        require_once MODELS_PATH . '/SiteModel.php';
        require_once MODELS_PATH . '/RoomModel.php';
        require_once MODELS_PATH . '/DocumentationModel.php';
        
        $this->materielModel = new MaterielModel($this->db);
        $this->clientModel = new ClientModel($this->db);
        $this->siteModel = new SiteModel($this->db);
        $this->roomModel = new RoomModel($this->db);
        $this->documentationModel = new DocumentationModel($this->db);
    }

    /**
     * Affiche la page combinée matériel et documentation avec filtres site et salle
     */
    public function index() {
        // Vérifier que l'utilisateur est un technicien ou admin
        if (!isStaff()) {
            $_SESSION['error'] = "Accès non autorisé";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Récupération des filtres (client, site et salle)
        $filters = [
            'client_id' => isset($_GET['client_id']) ? (int)$_GET['client_id'] : null,
            'site_id' => isset($_GET['site_id']) ? (int)$_GET['site_id'] : null,
            'salle_id' => isset($_GET['salle_id']) ? (int)$_GET['salle_id'] : null
        ];

        try {
            // Récupération de tous les clients
            $clients = $this->clientModel->getAllClients();
            
            // Initialiser les variables
            $sites = [];
            $salles = [];
            $materiel_list = [];
            $documentation_list = [];
            $pieces_jointes_count = [];
            $selectedSite = null;
            $selectedRoom = null;

            // Si un client est sélectionné, récupérer les sites
            if ($filters['client_id']) {
                $sites = $this->siteModel->getSitesByClientId($filters['client_id']);
            }

            // Si un site est sélectionné, récupérer les salles
            if ($filters['site_id']) {
                $salles = $this->roomModel->getRoomsBySiteId($filters['site_id']);
                $selectedSite = $this->siteModel->getSiteById($filters['site_id']);
            }

            // Si une salle est sélectionnée, récupérer les données
            if ($filters['salle_id']) {
                $selectedRoom = $this->roomModel->getRoomById($filters['salle_id']);
                
                // Log pour debug
                custom_log("MaterielTestController::index - salle_id: " . $filters['salle_id'] . ", selectedRoom: " . ($selectedRoom ? 'found' : 'not found'), 'DEBUG');
                
                if ($selectedRoom) {
                    // Test direct de la requête SQL pour le matériel
                    $testQuery = "SELECT COUNT(*) as count FROM materiel WHERE salle_id = :salle_id";
                    $testStmt = $this->db->prepare($testQuery);
                    $testStmt->bindValue(':salle_id', $filters['salle_id'], PDO::PARAM_INT);
                    $testStmt->execute();
                    $testResult = $testStmt->fetch(PDO::FETCH_ASSOC);
                    custom_log("MaterielTestController::index - Direct SQL count for salle_id " . $filters['salle_id'] . ": " . $testResult['count'], 'DEBUG');
                    
                    // Récupération du matériel de la salle
                    // Utiliser seulement salle_id pour être sûr que ça fonctionne
                    $materiel_filters = [
                        'salle_id' => $filters['salle_id']
                    ];
                    
                    custom_log("MaterielTestController::index - materiel_filters: " . json_encode($materiel_filters), 'DEBUG');
                    
                    $materiel_list = $this->materielModel->getAllMateriel($materiel_filters);
                    
                    // Log pour debug
                    custom_log("MaterielTestController::index - materiel_list count: " . count($materiel_list), 'DEBUG');
                    if (!empty($materiel_list)) {
                        custom_log("MaterielTestController::index - First materiel: " . json_encode($materiel_list[0]), 'DEBUG');
                    } else {
                        // Tester la requête complète
                        $fullQuery = "
                            SELECT m.*, r.name as salle_nom, s.name as site_nom, c.name as client_nom
                            FROM materiel m
                            LEFT JOIN rooms r ON m.salle_id = r.id
                            LEFT JOIN sites s ON r.site_id = s.id
                            LEFT JOIN clients c ON s.client_id = c.id
                            WHERE m.salle_id = :salle_id
                        ";
                        $fullStmt = $this->db->prepare($fullQuery);
                        $fullStmt->bindValue(':salle_id', $filters['salle_id'], PDO::PARAM_INT);
                        $fullStmt->execute();
                        $fullResult = $fullStmt->fetchAll(PDO::FETCH_ASSOC);
                        custom_log("MaterielTestController::index - Full query result count: " . count($fullResult), 'DEBUG');
                    }
                    
                    // Récupération du nombre de pièces jointes pour chaque matériel
                    if (!empty($materiel_list)) {
                        $materiel_ids = array_column($materiel_list, 'id');
                        foreach ($materiel_ids as $materiel_id) {
                            $pieces_jointes_count[$materiel_id] = $this->materielModel->getPiecesJointesCount($materiel_id);
                        }
                    }

                    // Récupération de la documentation de la salle
                    $documentation_list = $this->getRoomDocumentation($filters['salle_id']);
                    
                    // Log pour debug
                    custom_log("MaterielTestController::index - documentation_list count: " . count($documentation_list), 'DEBUG');
                }
            }

        } catch (Exception $e) {
            // En cas d'erreur, initialiser les variables avec des tableaux vides
            $clients = [];
            $sites = [];
            $salles = [];
            $materiel_list = [];
            $documentation_list = [];
            $pieces_jointes_count = [];
            
            // Log de l'erreur
            custom_log("Erreur lors du chargement de materiel_test : " . $e->getMessage(), 'ERROR');
        }

        // Définir la page courante pour le menu
        $currentPage = 'materiel_test';
        $pageTitle = 'Matériel et Documentation';

        // Log final pour debug
        custom_log("MaterielTestController::index - Final: materiel_list=" . count($materiel_list) . ", documentation_list=" . count($documentation_list) . ", selectedRoom=" . ($selectedRoom ? 'yes' : 'no'), 'DEBUG');

        // Inclure la vue
        require_once VIEWS_PATH . '/materiel_test/index.php';
    }

    /**
     * Récupère la documentation d'une salle
     */
    private function getRoomDocumentation($roomId) {
        // Récupérer d'abord les infos de la salle pour avoir le site_id et client_id
        $room = $this->roomModel->getRoomById($roomId);
        if (!$room) {
            return [];
        }
        
        $siteId = $room['site_id'];
        $clientId = null;
        
        // Récupérer le client_id depuis le site
        if ($siteId) {
            $site = $this->siteModel->getSiteById($siteId);
            if ($site) {
                $clientId = $site['client_id'];
            }
        }
        
        // Requête similaire à DocumentationController pour récupérer tous les documents
        // liés à la salle, au site ou au client
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
                CASE 
                    WHEN pj.nom_fichier LIKE '%.pdf' THEN 'pdf'
                    WHEN pj.nom_fichier LIKE '%.dwg' THEN 'dwg'
                    WHEN pj.nom_fichier LIKE '%.vsd' THEN 'vsd'
                    WHEN pj.nom_fichier LIKE '%.doc%' THEN 'doc'
                    WHEN pj.nom_fichier LIKE '%.xls%' THEN 'xls'
                    WHEN pj.nom_fichier LIKE '%.txt' THEN 'txt'
                    WHEN pj.nom_fichier LIKE '%.jpg' OR pj.nom_fichier LIKE '%.jpeg' OR pj.nom_fichier LIKE '%.png' THEN 'image'
                    WHEN pj.nom_fichier LIKE '%.mp4' OR pj.nom_fichier LIKE '%.avi' THEN 'video'
                    ELSE 'other'
                END as file_type
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
            AND (
                -- Documents liés directement à la salle
                (lpj.type_liaison = 'documentation_room' AND lpj.entite_id = :room_id)
        ";
        
        $params = [':room_id' => $roomId];
        
        // Ajouter les conditions pour le site et le client seulement s'ils existent
        if ($siteId) {
            $query .= " OR (lpj.type_liaison = 'documentation_site' AND lpj.entite_id = :site_id)";
            $params[':site_id'] = $siteId;
        }
        
        if ($clientId) {
            $query .= " OR (lpj.type_liaison = 'documentation_client' AND lpj.entite_id = :client_id)";
            $params[':client_id'] = $clientId;
        }
        
        $query .= "
            )
            ORDER BY pj.date_creation DESC
        ";

        // Test direct pour voir s'il y a des documents liés à cette salle
        $testQuery = "SELECT COUNT(*) as count FROM liaisons_pieces_jointes WHERE type_liaison = 'documentation_room' AND entite_id = :room_id";
        $testStmt = $this->db->prepare($testQuery);
        $testStmt->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $testStmt->execute();
        $testResult = $testStmt->fetch(PDO::FETCH_ASSOC);
        custom_log("getRoomDocumentation - Direct SQL count for room_id $roomId: " . $testResult['count'], 'DEBUG');
        
        // Test avec tous les types de liaison
        $testQuery2 = "SELECT type_liaison, COUNT(*) as count FROM liaisons_pieces_jointes WHERE entite_id = :room_id GROUP BY type_liaison";
        $testStmt2 = $this->db->prepare($testQuery2);
        $testStmt2->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $testStmt2->execute();
        $testResult2 = $testStmt2->fetchAll(PDO::FETCH_ASSOC);
        custom_log("getRoomDocumentation - All types for room_id $roomId: " . json_encode($testResult2), 'DEBUG');
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log pour debug
        custom_log("getRoomDocumentation - roomId: $roomId, siteId: " . ($siteId ?? 'null') . ", clientId: " . ($clientId ?? 'null') . ", count: " . count($result), 'DEBUG');
        if (!empty($result)) {
            custom_log("getRoomDocumentation - First doc: " . json_encode($result[0]), 'DEBUG');
        }
        
        return $result;
    }

    /**
     * API pour récupérer les sites d'un client (AJAX)
     */
    public function get_sites() {
        if (!isStaff()) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès non autorisé']);
            exit;
        }

        $client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;
        
        if (!$client_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Client ID manquant']);
            exit;
        }

        try {
            $sites = $this->siteModel->getSitesByClientId($client_id);
            header('Content-Type: application/json');
            echo json_encode($sites);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la récupération des sites']);
        }
        exit;
    }

    /**
     * API pour récupérer les salles d'un site (AJAX)
     */
    public function get_rooms() {
        if (!isStaff()) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès non autorisé']);
            exit;
        }

        $site_id = isset($_GET['site_id']) ? (int)$_GET['site_id'] : null;
        
        if (!$site_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Site ID manquant']);
            exit;
        }

        try {
            $salles = $this->roomModel->getRoomsBySiteId($site_id);
            header('Content-Type: application/json');
            echo json_encode($salles);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la récupération des salles']);
        }
        exit;
    }
}
