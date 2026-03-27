<?php

require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';

class MaterielV2Controller {
    use AccessControlTrait;
    private $db;
    private $materielModel;
    private $clientModel;
    private $siteModel;
    private $roomModel;
    private $accessLevelModel;

    public function __construct() {
        // Récupérer l'instance de la base de données
        $config = Config::getInstance();
        $this->db = $config->getDb();
        
        // Initialiser les modèles
        require_once MODELS_PATH . '/MaterielModel.php';
        require_once MODELS_PATH . '/ClientModel.php';
        require_once MODELS_PATH . '/SiteModel.php';
        require_once MODELS_PATH . '/RoomModel.php';
        require_once MODELS_PATH . '/AccessLevelModel.php';
        
        $this->materielModel = new MaterielModel($this->db);
        $this->clientModel = new ClientModel($this->db);
        $this->siteModel = new SiteModel($this->db);
        $this->roomModel = new RoomModel($this->db);
        $this->accessLevelModel = new AccessLevelModel($this->db);
    }

    /**
     * Vérifie si l'utilisateur a le droit d'accéder au matériel
     */

    /**
     * Affiche la liste du matériel (Version 2)
     */
    public function index() {
        $this->checkAccess();

        // Récupération des filtres (comme dans la documentation)
        $filters = [
            'client_id' => isset($_GET['client_id']) ? (int)$_GET['client_id'] : null,
            'site_id' => isset($_GET['site_id']) ? (int)$_GET['site_id'] : null,
            'salle_id' => isset($_GET['salle_id']) ? (int)$_GET['salle_id'] : null
        ];

        try {
            // Récupération des données pour les filtres (comme dans la documentation)
            $clients = $this->clientModel->getAllClients();
            
            // Initialiser les variables
            $sites = [];
            $salles = [];
            $materiel_list = [];
            $visibilites_champs = [];
            $pieces_jointes_count = [];

            // Ne charger les données que si un client est sélectionné
            if ($filters['client_id']) {
                // Récupération des sites selon le filtre client
                $sites = $this->siteModel->getSitesByClientId($filters['client_id']);

                // Récupération des salles selon le filtre site
                if ($filters['site_id']) {
                    $salles = $this->roomModel->getRoomsBySiteId($filters['site_id']);
                } elseif ($filters['client_id']) {
                    // Si on a un client mais pas de site, récupérer toutes les salles du client
                    $salles = $this->roomModel->getRoomsByClientId($filters['client_id']);
                }

                // Récupération du matériel avec filtres
                $materiel_list = $this->materielModel->getAllMateriel($filters);

                // Récupération des informations de visibilité des champs
                if (!empty($materiel_list)) {
                    $materiel_ids = array_column($materiel_list, 'id');
                    $visibilites_champs = $this->materielModel->getVisibiliteChampsForMateriels($materiel_ids);
                    
                    // Récupération du nombre de pièces jointes pour chaque matériel
                    foreach ($materiel_ids as $materiel_id) {
                        $pieces_jointes_count[$materiel_id] = $this->materielModel->getPiecesJointesCount($materiel_id);
                    }
                }
            }

        } catch (Exception $e) {
            // En cas d'erreur, initialiser les variables avec des tableaux vides
            $clients = [];
            $sites = [];
            $salles = [];
            $materiel_list = [];
            $visibilites_champs = [];
            $pieces_jointes_count = [];
            
            // Log de l'erreur
            custom_log("Erreur lors du chargement du matériel V2 : " . $e->getMessage(), 'ERROR');
        }

        // Définir la page courante pour le menu
        $currentPage = 'materiel_v2';
        $pageTitle = 'Gestion du Matériel V2';

        // Inclure la vue
        require_once VIEWS_PATH . '/materiel/index_v2.php';
    }

    /**
     * Met à jour un champ d'un matériel (AJAX)
     */
    public function updateField() {
        $this->checkAccess();

        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['id']) || !isset($input['field']) || !isset($input['value'])) {
            echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
            exit;
        }

        $id = (int)$input['id'];
        $field = $input['field'];
        $value = $input['value'];

        // Liste des champs autorisés pour l'édition
        $allowedFields = [
            'type_materiel', 'modele', 'marque', 'reference', 'usage_materiel',
            'numero_serie', 'version_firmware', 'ancien_firmware', 'adresse_mac', 'adresse_ip',
            'masque', 'passerelle', 'id_materiel', 'login', 'password', 'ip_primaire',
            'mac_primaire', 'ip_secondaire', 'mac_secondaire', 'stream_aes67_recu',
            'stream_aes67_transmis', 'ssid', 'type_cryptage', 'password_wifi',
            'libelle_pa_salle', 'numero_port_switch', 'vlan', 'date_fin_maintenance',
            'date_fin_garantie', 'date_derniere_inter', 'commentaire', 'url_github'
        ];

        // Vérifier que le champ est autorisé
        if (!in_array($field, $allowedFields)) {
            echo json_encode(['success' => false, 'message' => 'Champ non autorisé']);
            exit;
        }

        try {
            $data = [$field => $value === '' ? null : $value];
            $success = $this->materielModel->updateMaterielPartial($id, $data);

            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Champ mis à jour avec succès']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
            }
        } catch (Exception $e) {
            custom_log("Erreur lors de la mise à jour du matériel V2 : " . $e->getMessage(), 'ERROR');
            echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
        }
    }

    /**
     * Récupère les pièces jointes d'un matériel (AJAX)
     */
    public function getAttachments() {
        $this->checkAccess();

        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            exit;
        }

        $materielId = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if (!$materielId) {
            echo json_encode(['success' => false, 'message' => 'ID matériel manquant']);
            exit;
        }

        try {
            // Vérifier que le matériel existe
            $materiel = $this->materielModel->getMaterielById($materielId);
            if (!$materiel) {
                echo json_encode(['success' => false, 'message' => 'Matériel non trouvé']);
                exit;
            }

            // Récupérer les pièces jointes
            $attachments = $this->materielModel->getPiecesJointes($materielId);
            
            echo json_encode([
                'success' => true,
                'attachments' => $attachments
            ]);

        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des pièces jointes V2 : " . $e->getMessage(), 'ERROR');
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la récupération des pièces jointes'
            ]);
        }
    }
}
