<?php
require_once __DIR__ . '/../classes/Services/AttachmentService.php';
require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';


class MaterielController
{
    use AccessControlTrait;
    private $db;
    private $materielModel;
    private $clientModel;
    private $siteModel;
    private $roomModel;
    private $accessLevelModel;

    public function __construct()
    {
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
     * Affiche la liste du matériel
     */
    public function index()
    {
        $this->checkAccess();

        // Récupération des filtres (comme dans la documentation)
        $filters = [
            'client_id' => isset($_GET['client_id']) ? (int) $_GET['client_id'] : null,
            'site_id' => isset($_GET['site_id']) ? (int) $_GET['site_id'] : null,
            'salle_id' => isset($_GET['salle_id']) ? (int) $_GET['salle_id'] : null
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
            custom_log("Erreur lors du chargement du matériel : " . $e->getMessage(), 'ERROR');
        }

        // Définir la page courante pour le menu
        $currentPage = 'materiel';
        $pageTitle = 'Gestion du Matériel';

        // Inclure la vue
        require_once VIEWS_PATH . '/materiel/index.php';
    }


    /**
     * Affiche les détails d'un matériel
     */
    public function view($id)
    {
        $this->checkAccess();

        try {
            $materiel = $this->materielModel->getMaterielById($id);

            if (!$materiel) {
                $_SESSION['error'] = "Matériel non trouvé";
                header('Location: ' . BASE_URL . 'materiel');
                exit;
            }

            // Récupérer les informations de visibilité des champs
            $visibilites_champs = [];
            $visibilites = $this->materielModel->getVisibiliteChampsForMateriels([$id]);
            if (isset($visibilites[$id])) {
                $visibilites_champs[$id] = $visibilites[$id];
            }

            // Récupérer les pièces jointes
            $attachments = $this->materielModel->getPiecesJointes($id);

            // Récupérer les informations client, site et salle
            $room = $this->roomModel->getRoomById($materiel['salle_id']);
            $site = $this->siteModel->getSiteById($room['site_id']);
            $client = $this->clientModel->getClientById($site['client_id']);

            // Récupérer le nom du type d'équipement
            $type_nom = $materiel['type_materiel'] ?? '';

        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération du matériel : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de la récupération du matériel";
            header('Location: ' . BASE_URL . 'materiel');
            exit;
        }

        $currentPage = 'materiel';
        $pageTitle = 'Détails du Matériel';

        require_once VIEWS_PATH . '/materiel/view.php';
    }

    /**
     * Affiche le formulaire d'ajout
     */
    public function add()
    {
        $this->checkAccess();

        try {
            $clients = $this->clientModel->getAllClients();

            // Initialiser les variables
            $sites = [];
            $salles = [];

            // Récupérer les sites selon le client sélectionné
            if (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
                $sites = $this->siteModel->getSitesByClientId($_GET['client_id']);

                // Récupérer les salles selon le site sélectionné
                if (isset($_GET['site_id']) && !empty($_GET['site_id'])) {
                    $salles = $this->roomModel->getRoomsBySiteId($_GET['site_id']);
                } elseif (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
                    // Si on a un client mais pas de site, récupérer toutes les salles du client
                    $salles = $this->roomModel->getRoomsByClientId($_GET['client_id']);
                }
            }

            // Récupérer le contrat à partir de la salle sélectionnée
            $contractId = null;
            $contractAccessLevel = null;

            if (isset($_GET['salle_id']) && !empty($_GET['salle_id'])) {
                // Récupérer le contrat de la salle
                $sql = "SELECT c.id as contract_id, c.access_level_id 
                        FROM contracts c 
                        INNER JOIN contract_rooms cr ON c.id = cr.contract_id 
                        WHERE cr.room_id = :room_id 
                        ORDER BY c.created_at DESC 
                        LIMIT 1";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([':room_id' => $_GET['salle_id']]);
                $contract = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($contract) {
                    $contractId = $contract['contract_id'];
                    $contractAccessLevel = $this->accessLevelModel->getContractAccessLevel($contractId);
                }
            }

            // Récupérer les champs de visibilité avec le niveau d'accès par défaut
            $champs_visibilite = $this->materielModel->getChampsVisibilite(null, $contractId);

        } catch (Exception $e) {
            custom_log("Erreur lors du chargement des données : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors du chargement des données";
            header('Location: ' . BASE_URL . 'materiel');
            exit;
        }

        $currentPage = 'materiel';
        $pageTitle = 'Ajouter du Matériel';

        require_once VIEWS_PATH . '/materiel/add.php';
    }

    /**
     * Traite la création d'un matériel
     */
    public function store()
    {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'materiel');
            exit;
        }

        try {
            $data = [
                'salle_id' => $_POST['salle_id'] ?? null,
                'type_materiel' => $_POST['type_materiel'] ?? null,
                'modele' => $_POST['modele'] ?? '',
                'marque' => $_POST['marque'] ?? '',
                'reference' => $_POST['reference'] ?? null,
                'usage_materiel' => $_POST['usage_materiel'] ?? null,
                'numero_serie' => $_POST['numero_serie'] ?? null,
                'version_firmware' => $_POST['version_firmware'] ?? null,
                'ancien_firmware' => $_POST['ancien_firmware'] ?? null,
                'url_github' => $_POST['url_github'] ?? null,
                'adresse_mac' => $_POST['adresse_mac'] ?? null,
                'adresse_ip' => $_POST['adresse_ip'] ?? null,
                'masque' => $_POST['masque'] ?? null,
                'passerelle' => $_POST['passerelle'] ?? null,
                'login' => $_POST['login'] ?? null,
                'password' => $_POST['password'] ?? null,
                'id_materiel' => $_POST['id_materiel'] ?? null,
                'ip_primaire' => $_POST['ip_primaire'] ?? null,
                'mac_primaire' => $_POST['mac_primaire'] ?? null,
                'ip_secondaire' => $_POST['ip_secondaire'] ?? null,
                'mac_secondaire' => $_POST['mac_secondaire'] ?? null,
                'stream_aes67_recu' => $_POST['stream_aes67_recu'] ?? null,
                'stream_aes67_transmis' => $_POST['stream_aes67_transmis'] ?? null,
                'ssid' => $_POST['ssid'] ?? null,
                'type_cryptage' => $_POST['type_cryptage'] ?? null,
                'password_wifi' => $_POST['password_wifi'] ?? null,
                'libelle_pa_salle' => $_POST['libelle_pa_salle'] ?? null,
                'numero_port_switch' => $_POST['numero_port_switch'] ?? null,
                'vlan' => $_POST['vlan'] ?? null,
                'date_fin_maintenance' => !empty($_POST['date_fin_maintenance']) ? $_POST['date_fin_maintenance'] : null,
                'date_fin_garantie' => !empty($_POST['date_fin_garantie']) ? $_POST['date_fin_garantie'] : null,
                'date_derniere_inter' => !empty($_POST['date_derniere_inter']) ? $_POST['date_derniere_inter'] : null,
                'commentaire' => $_POST['commentaire'] ?? null
            ];

            // Validation
            if (empty($data['salle_id']) || empty($data['modele']) || empty($data['marque'])) {
                throw new Exception("Les champs salle, modèle et marque sont obligatoires");
            }

            $materielId = $this->materielModel->createMateriel($data);

            // Sauvegarder la visibilité des champs
            if ($materielId) {
                // Récupérer le contrat de la salle pour appliquer les règles par défaut
                $contractId = null;
                if ($data['salle_id']) {
                    $sql = "SELECT c.id as contract_id 
                            FROM contracts c 
                            INNER JOIN contract_rooms cr ON c.id = cr.contract_id 
                            WHERE cr.room_id = :room_id 
                            ORDER BY c.created_at DESC 
                            LIMIT 1";

                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([':room_id' => $data['salle_id']]);
                    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($contract) {
                        $contractId = $contract['contract_id'];
                    }
                }

                // Récupérer les visibilités par défaut du contrat
                $champs_visibilite = $this->materielModel->getChampsVisibilite(null, $contractId);

                $visibilites = [];
                foreach ($champs_visibilite as $nom_champ => $info) {
                    // Utiliser les valeurs du formulaire si présentes, sinon les valeurs par défaut du contrat
                    if (isset($_POST['visibilite_' . $nom_champ])) {
                        $visibilites[$nom_champ] = true;
                    } else {
                        $visibilites[$nom_champ] = $info['visible_client'];
                    }
                }

                $this->materielModel->saveVisibiliteChamps($materielId, $visibilites);
            }

            $_SESSION['success'] = "Matériel créé avec succès";

            // Construire l'URL de redirection avec les filtres
            $redirectParams = [];
            if (isset($_POST['return_client_id']) && !empty($_POST['return_client_id'])) {
                $redirectParams['client_id'] = $_POST['return_client_id'];
            }
            if (isset($_POST['return_site_id']) && !empty($_POST['return_site_id'])) {
                $redirectParams['site_id'] = $_POST['return_site_id'];
            }
            if (isset($_POST['return_salle_id']) && !empty($_POST['return_salle_id'])) {
                $redirectParams['salle_id'] = $_POST['return_salle_id'];
            }

            $redirectUrl = BASE_URL . 'materiel/view/' . $materielId;
            if (!empty($redirectParams)) {
                $redirectUrl .= '?' . http_build_query($redirectParams);
            }

            header('Location: ' . $redirectUrl);
            exit;

        } catch (Exception $e) {
            custom_log("Erreur lors de la création du matériel : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de la création du matériel : " . $e->getMessage();

            // Construire l'URL de redirection avec les filtres
            $redirectParams = [];
            if (isset($_POST['return_client_id']) && !empty($_POST['return_client_id'])) {
                $redirectParams['client_id'] = $_POST['return_client_id'];
            }
            if (isset($_POST['return_site_id']) && !empty($_POST['return_site_id'])) {
                $redirectParams['site_id'] = $_POST['return_site_id'];
            }
            if (isset($_POST['return_salle_id']) && !empty($_POST['return_salle_id'])) {
                $redirectParams['salle_id'] = $_POST['return_salle_id'];
            }

            $redirectUrl = BASE_URL . 'materiel/add';
            if (!empty($redirectParams)) {
                $redirectUrl .= '?' . http_build_query($redirectParams);
            }

            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    /**
     * Affiche le formulaire de modification
     */
    public function edit($id)
    {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        try {
            $materiel = $this->materielModel->getMaterielById($id);

            if (!$materiel) {
                $_SESSION['error'] = "Matériel non trouvé";
                header('Location: ' . BASE_URL . 'materiel');
                exit;
            }

            $clients = $this->clientModel->getAllClients();

            // Récupérer les informations client, site et salle du matériel
            $room = $this->roomModel->getRoomById($materiel['salle_id']);
            $site = $this->siteModel->getSiteById($room['site_id']);
            $client = $this->clientModel->getClientById($site['client_id']);

            // Récupérer les sites du client du matériel
            $sites = $this->siteModel->getSitesByClientId($client['id']);

            // Récupérer les salles du site du matériel
            $salles = $this->roomModel->getRoomsBySiteId($site['id']);

            $champs_visibilite = $this->materielModel->getChampsVisibilite($id);

        } catch (Exception $e) {
            custom_log("Erreur lors du chargement des données : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors du chargement des données";
            header('Location: ' . BASE_URL . 'materiel');
            exit;
        }

        $currentPage = 'materiel';
        $pageTitle = 'Modifier le Matériel';

        require_once VIEWS_PATH . '/materiel/edit.php';
    }

    /**
     * Traite la modification d'un matériel
     */
    public function update($id)
    {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'materiel');
            exit;
        }

        try {
            $data = [
                'salle_id' => $_POST['salle_id'] ?? null,
                'type_materiel' => $_POST['type_materiel'] ?? null,
                'modele' => $_POST['modele'] ?? '',
                'marque' => $_POST['marque'] ?? '',
                'reference' => $_POST['reference'] ?? null,
                'usage_materiel' => $_POST['usage_materiel'] ?? null,
                'numero_serie' => $_POST['numero_serie'] ?? null,
                'version_firmware' => $_POST['version_firmware'] ?? null,
                'ancien_firmware' => $_POST['ancien_firmware'] ?? null,
                'url_github' => $_POST['url_github'] ?? null,
                'adresse_mac' => $_POST['adresse_mac'] ?? null,
                'adresse_ip' => $_POST['adresse_ip'] ?? null,
                'masque' => $_POST['masque'] ?? null,
                'passerelle' => $_POST['passerelle'] ?? null,
                'login' => $_POST['login'] ?? null,
                'password' => $_POST['password'] ?? null,
                'id_materiel' => $_POST['id_materiel'] ?? null,
                'ip_primaire' => $_POST['ip_primaire'] ?? null,
                'mac_primaire' => $_POST['mac_primaire'] ?? null,
                'ip_secondaire' => $_POST['ip_secondaire'] ?? null,
                'mac_secondaire' => $_POST['mac_secondaire'] ?? null,
                'stream_aes67_recu' => $_POST['stream_aes67_recu'] ?? null,
                'stream_aes67_transmis' => $_POST['stream_aes67_transmis'] ?? null,
                'ssid' => $_POST['ssid'] ?? null,
                'type_cryptage' => $_POST['type_cryptage'] ?? null,
                'password_wifi' => $_POST['password_wifi'] ?? null,
                'libelle_pa_salle' => $_POST['libelle_pa_salle'] ?? null,
                'numero_port_switch' => $_POST['numero_port_switch'] ?? null,
                'vlan' => $_POST['vlan'] ?? null,
                'date_fin_maintenance' => !empty($_POST['date_fin_maintenance']) ? $_POST['date_fin_maintenance'] : null,
                'date_fin_garantie' => !empty($_POST['date_fin_garantie']) ? $_POST['date_fin_garantie'] : null,
                'date_derniere_inter' => !empty($_POST['date_derniere_inter']) ? $_POST['date_derniere_inter'] : null,
                'commentaire' => $_POST['commentaire'] ?? null
            ];

            // Validation
            if (empty($data['salle_id']) || empty($data['modele']) || empty($data['marque'])) {
                throw new Exception("Les champs salle, modèle et marque sont obligatoires");
            }

            $success = $this->materielModel->updateMateriel($id, $data);

            if ($success) {
                // Sauvegarder la visibilité des champs
                $visibilites = [];
                $champs_visibilite = $this->materielModel->getChampsVisibilite($id);

                foreach ($champs_visibilite as $nom_champ => $info) {
                    $visibilites[$nom_champ] = isset($_POST['visibilite_' . $nom_champ]) ? true : false;
                }

                $this->materielModel->saveVisibiliteChamps($id, $visibilites);

                $_SESSION['success'] = "Matériel modifié avec succès";

                // Construire l'URL de redirection avec les filtres
                $redirectParams = [];
                if (isset($_POST['return_client_id']) && !empty($_POST['return_client_id'])) {
                    $redirectParams['client_id'] = $_POST['return_client_id'];
                }
                if (isset($_POST['return_site_id']) && !empty($_POST['return_site_id'])) {
                    $redirectParams['site_id'] = $_POST['return_site_id'];
                }
                if (isset($_POST['return_salle_id']) && !empty($_POST['return_salle_id'])) {
                    $redirectParams['salle_id'] = $_POST['return_salle_id'];
                }

                $redirectUrl = BASE_URL . 'materiel/view/' . $id;
                if (!empty($redirectParams)) {
                    $redirectUrl .= '?' . http_build_query($redirectParams);
                }

                header('Location: ' . $redirectUrl);
            } else {
                throw new Exception("Erreur lors de la modification");
            }
            exit;

        } catch (Exception $e) {
            custom_log("Erreur lors de la modification du matériel : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de la modification du matériel : " . $e->getMessage();

            // Construire l'URL de redirection avec les filtres
            $redirectParams = [];
            if (isset($_POST['return_client_id']) && !empty($_POST['return_client_id'])) {
                $redirectParams['client_id'] = $_POST['return_client_id'];
            }
            if (isset($_POST['return_site_id']) && !empty($_POST['return_site_id'])) {
                $redirectParams['site_id'] = $_POST['return_site_id'];
            }
            if (isset($_POST['return_salle_id']) && !empty($_POST['return_salle_id'])) {
                $redirectParams['salle_id'] = $_POST['return_salle_id'];
            }

            $redirectUrl = BASE_URL . 'materiel/edit/' . $id;
            if (!empty($redirectParams)) {
                $redirectUrl .= '?' . http_build_query($redirectParams);
            }

            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    /**
     * Supprime un matériel
     */
    public function delete($id)
    {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        try {
            if ($this->materielModel->deleteMateriel($id)) {
                $_SESSION['success'] = "Matériel supprimé avec succès.";
            } else {
                $_SESSION['error'] = "Erreur lors de la suppression du matériel.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors de la suppression : " . $e->getMessage();
            custom_log("Erreur lors de la suppression du matériel : " . $e->getMessage(), 'ERROR');
        }

        // Construire l'URL de redirection avec les filtres
        $redirectParams = [];
        if (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
            $redirectParams['client_id'] = $_GET['client_id'];
        }
        if (isset($_GET['site_id']) && !empty($_GET['site_id'])) {
            $redirectParams['site_id'] = $_GET['site_id'];
        }
        if (isset($_GET['salle_id']) && !empty($_GET['salle_id'])) {
            $redirectParams['salle_id'] = $_GET['salle_id'];
        }

        $redirectUrl = BASE_URL . 'materiel';
        if (!empty($redirectParams)) {
            $redirectUrl .= '?' . http_build_query($redirectParams);
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Supprime plusieurs matériels en masse
     */
    public function deleteBulk()
    {
        custom_log("Début de deleteBulk - Paramètres GET: " . print_r($_GET, true), 'INFO');

        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            custom_log("Utilisateur non connecté, redirection vers login", 'WARNING');
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Vérifier que l'utilisateur est administrateur
        if (!canDelete()) {
            custom_log("Utilisateur non autorisé pour la suppression en masse", 'WARNING');
            $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour effectuer cette action.";
            header('Location: ' . BASE_URL . 'materiel');
            exit;
        }

        try {
            // Récupérer les IDs depuis les paramètres GET
            $idsParam = $_GET['ids'] ?? '';
            custom_log("Paramètre ids reçu: '$idsParam'", 'INFO');

            if (empty($idsParam)) {
                $errorMsg = "Aucun matériel sélectionné pour la suppression.";
                $_SESSION['error'] = $errorMsg;
                custom_log($errorMsg, 'ERROR');
            } else {
                // Convertir la chaîne d'IDs en tableau
                $ids = explode(',', $idsParam);
                $ids = array_map('trim', $ids);
                custom_log("IDs convertis en tableau: " . print_r($ids, true), 'INFO');

                // Appeler la méthode du modèle
                $result = $this->materielModel->deleteMaterielBulk($ids);
                custom_log("Résultat de deleteMaterielBulk: " . print_r($result, true), 'INFO');

                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                    custom_log("Suppression réussie: " . $result['message'], 'INFO');

                    // Ajouter les erreurs individuelles s'il y en a
                    if (!empty($result['errors'])) {
                        $warningMsg = "Attention: " . implode('; ', $result['errors']);
                        $_SESSION['warning'] = $warningMsg;
                        custom_log($warningMsg, 'WARNING');
                    }
                } else {
                    $errorMsg = $result['message'];
                    $_SESSION['error'] = $errorMsg;
                    custom_log("Suppression échouée: " . $errorMsg, 'ERROR');

                    // Ajouter les erreurs détaillées
                    if (!empty($result['errors'])) {
                        $errorMsg .= " Détails: " . implode('; ', $result['errors']);
                        $_SESSION['error'] = $errorMsg;
                        custom_log("Erreurs détaillées: " . implode('; ', $result['errors']), 'ERROR');
                    }
                }
            }
        } catch (Exception $e) {
            $errorMsg = "Erreur lors de la suppression en masse : " . $e->getMessage();
            $_SESSION['error'] = $errorMsg;
            custom_log($errorMsg, 'ERROR');
        }

        // Construire l'URL de redirection avec les filtres
        $redirectParams = [];
        if (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
            $redirectParams['client_id'] = $_GET['client_id'];
        }
        if (isset($_GET['site_id']) && !empty($_GET['site_id'])) {
            $redirectParams['site_id'] = $_GET['site_id'];
        }
        if (isset($_GET['salle_id']) && !empty($_GET['salle_id'])) {
            $redirectParams['salle_id'] = $_GET['salle_id'];
        }

        $redirectUrl = BASE_URL . 'materiel';
        if (!empty($redirectParams)) {
            $redirectUrl .= '?' . http_build_query($redirectParams);
        }

        custom_log("Redirection vers: $redirectUrl", 'INFO');
        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Ajoute une pièce jointe à un matériel
     */
    /**
     * Ajoute une pièce jointe à un matériel
     * Utilise AttachmentService pour centraliser la logique
     */
    public function addAttachment($materielId)
    {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'materiel/view/' . $materielId);
            exit;
        }

        try {
            // Vérifier que le matériel existe
            $materiel = $this->materielModel->getMaterielById($materielId);
            if (!$materiel) {
                throw new Exception("Matériel non trouvé");
            }

            // Vérifier qu'un fichier a été uploadé
            if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Erreur lors de l'upload du fichier");
            }

            // Utiliser AttachmentService pour gérer l'upload
            $attachmentService = new AttachmentService($this->db);

            // Préparer les options
            $options = [
                'descriptions' => [$_POST['description'] ?? null],
                'masque_client' => [isset($_POST['masque_client']) ? 1 : 0]
            ];

            // Upload du fichier
            $result = $attachmentService->upload(
                AttachmentService::TYPE_MATERIEL,
                $materielId,
                $_FILES['attachment'],
                $options,
                $_SESSION['user']['id']
            );

            if ($result['success']) {
                $_SESSION['success'] = "Pièce jointe ajoutée avec succès";
            } else {
                $errorMessage = !empty($result['errors']) ? implode(', ', $result['errors']) : "Erreur lors de l'ajout de la pièce jointe";
                throw new Exception($errorMessage);
            }

        } catch (Exception $e) {
            custom_log("Erreur lors de l'ajout de la pièce jointe : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de l'ajout de la pièce jointe : " . $e->getMessage();
        }

        header('Location: ' . BASE_URL . 'materiel/view/' . $materielId);
        exit;
    }

    /**
     * Ajoute plusieurs pièces jointes à un matériel (Drag & Drop)
     * Utilise AttachmentService pour centraliser la logique
     */
    public function addMultipleAttachments($materielId)
    {
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
            // Vérifier que le matériel existe
            $materiel = $this->materielModel->getMaterielById($materielId);
            if (!$materiel) {
                throw new Exception("Matériel non trouvé");
            }

            // Vérifier qu'il y a des fichiers
            if (!isset($_FILES['attachments']) || empty($_FILES['attachments']['name'][0])) {
                throw new Exception("Aucun fichier à uploader");
            }

            // Utiliser AttachmentService pour gérer l'upload
            $attachmentService = new AttachmentService($this->db);

            // Préparer les options
            $options = [
                'descriptions' => $_POST['file_description'] ?? [],
                'masque_client' => $_POST['file_masque_client'] ?? []
            ];

            // Upload des fichiers
            $result = $attachmentService->upload(
                AttachmentService::TYPE_MATERIEL,
                $materielId,
                $_FILES['attachments'],
                $options,
                $_SESSION['user']['id']
            );

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
     * Supprime une pièce jointe d'un matériel
     * Utilise AttachmentService pour centraliser la logique
     */
    public function deleteAttachment($materielId, $pieceJointeId)
    {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        try {
            // Utiliser AttachmentService pour gérer la suppression
            $attachmentService = new AttachmentService($this->db);
            $attachmentService->delete($pieceJointeId, AttachmentService::TYPE_MATERIEL, $materielId);

            $_SESSION['success'] = "Pièce jointe supprimée avec succès";

        } catch (Exception $e) {
            custom_log("Erreur lors de la suppression de la pièce jointe : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de la suppression de la pièce jointe : " . $e->getMessage();
        }

        header('Location: ' . BASE_URL . 'materiel/view/' . $materielId);
        exit;
    }

    /**
     * Télécharge une pièce jointe
     * Utilise AttachmentService pour centraliser la logique
     */
    public function download($pieceJointeId)
    {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        try {
            // Utiliser AttachmentService pour gérer le téléchargement
            $attachmentService = new AttachmentService($this->db);
            $attachmentService->download($pieceJointeId, true);

        } catch (Exception $e) {
            custom_log("Erreur lors du téléchargement : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors du téléchargement : " . $e->getMessage();
            header('Location: ' . BASE_URL . 'materiel');
            exit;
        }
    }

    /**
     * Affiche l'aperçu d'une pièce jointe
     */
    /**
     * Affiche l'aperçu d'une pièce jointe
     * Utilise AttachmentService pour centraliser la logique
     */
    public function preview($attachmentId)
    {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        try {
            // Utiliser AttachmentService pour gérer l'aperçu
            $attachmentService = new AttachmentService($this->db);
            $attachmentService->preview($attachmentId);

        } catch (Exception $e) {
            custom_log("Erreur lors de l'aperçu : " . $e->getMessage(), 'ERROR');

            // Retourner une réponse JSON pour les requêtes AJAX
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
                exit;
            }

            // Redirection pour les requêtes normales
            $_SESSION['error'] = "Erreur lors de l'aperçu : " . $e->getMessage();
            header('Location: ' . BASE_URL . 'materiel');
            exit;
        }
    }

    /**
     * Bascule la visibilité d'une pièce jointe (AJAX)
     * Utilise AttachmentService pour centraliser la logique
     */
    public function toggleAttachmentVisibility()
    {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Non autorisé']);
            exit;
        }

        try {
            $attachmentId = $_POST['attachment_id'] ?? null;
            $masqueClient = $_POST['masque_client'] ?? null;

            if (!$attachmentId) {
                throw new Exception("ID de la pièce jointe manquant");
            }

            if ($masqueClient === null) {
                throw new Exception("Paramètre de visibilité manquant");
            }

            // Récupérer les informations de la pièce jointe pour obtenir materiel_id
            $attachmentService = new AttachmentService($this->db);
            $pieceJointe = $attachmentService->getAttachmentById($attachmentId);

            if (!$pieceJointe) {
                throw new Exception("Pièce jointe non trouvée");
            }

            // Utiliser AttachmentService pour basculer la visibilité
            $attachmentService->toggleVisibility($attachmentId, (int) $masqueClient);

            // Récupérer materiel_id depuis la liaison
            $query = "SELECT entite_id as materiel_id 
                     FROM liaisons_pieces_jointes 
                     WHERE piece_jointe_id = :attachment_id AND type_liaison = 'materiel'";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':attachment_id' => $attachmentId]);
            $liaison = $stmt->fetch(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'materiel_id' => $liaison['materiel_id'] ?? null,
                'message' => 'Visibilité mise à jour avec succès'
            ]);

        } catch (Exception $e) {
            custom_log("Erreur lors du basculement de la visibilité : " . $e->getMessage(), 'ERROR');
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Bascule la visibilité d'une pièce jointe (lien direct)
     * Utilise AttachmentService pour centraliser la logique
     */
    public function toggleAttachmentVisibilityDirect($materielId, $pieceJointeId)
    {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        try {
            // Utiliser AttachmentService pour récupérer et basculer la visibilité
            $attachmentService = new AttachmentService($this->db);
            $pieceJointe = $attachmentService->getAttachmentById($pieceJointeId);

            if (!$pieceJointe) {
                throw new Exception("Pièce jointe non trouvée");
            }

            // Inverser la visibilité
            $newVisibility = $pieceJointe['masque_client'] == 1 ? 0 : 1;

            // Utiliser AttachmentService pour basculer la visibilité
            $attachmentService->toggleVisibility($pieceJointeId, $newVisibility);

            $_SESSION['success'] = $newVisibility == 1 ?
                "Pièce jointe masquée aux clients" :
                "Pièce jointe rendue visible aux clients";

        } catch (Exception $e) {
            custom_log("Erreur lors du changement de visibilité : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors du changement de visibilité : " . $e->getMessage();
        }

        header('Location: ' . BASE_URL . 'materiel/view/' . $materielId);
        exit;
    }

    /**
     * Récupère les pièces jointes d'un matériel via AJAX
     */
    public function getAttachments($materielId)
    {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Non autorisé']);
            exit;
        }

        try {
            // Vérifier que le matériel existe
            $materiel = $this->materielModel->getMaterielById($materielId);
            if (!$materiel) {
                header('Content-Type: application/json');
                http_response_code(404);
                echo json_encode(['error' => 'Matériel non trouvé']);
                exit;
            }

            // Récupérer les pièces jointes
            $attachments = $this->materielModel->getPiecesJointes($materielId);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'attachments' => $attachments
            ]);

        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des pièces jointes : " . $e->getMessage(), 'ERROR');
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Erreur lors de la récupération des pièces jointes'
            ]);
        }
    }

    /**
     * Télécharge toutes les pièces jointes d'un matériel en ZIP
     */
    public function downloadAll($materielId)
    {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        try {
            // Vérifier que le matériel existe
            $materiel = $this->materielModel->getMaterielById($materielId);
            if (!$materiel) {
                throw new Exception("Matériel non trouvé");
            }

            // Récupérer les pièces jointes
            $attachments = $this->materielModel->getPiecesJointes($materielId);

            if (empty($attachments)) {
                $_SESSION['error'] = "Aucune pièce jointe à télécharger";
                header('Location: ' . BASE_URL . 'materiel/view/' . $materielId);
                exit;
            }

            // Créer un fichier ZIP temporaire
            $zipName = 'materiel_' . $materielId . '_pieces_jointes_' . date('Y-m-d_H-i-s') . '.zip';
            $zipPath = sys_get_temp_dir() . '/' . $zipName;

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
                throw new Exception("Impossible de créer le fichier ZIP");
            }

            // Ajouter chaque fichier au ZIP
            foreach ($attachments as $attachment) {
                $filePath = __DIR__ . '/../../' . $attachment['chemin_fichier'];
                if (file_exists($filePath)) {
                    $zip->addFile($filePath, $attachment['nom_fichier']);
                }
            }

            $zip->close();

            // Envoyer le fichier ZIP
            if (file_exists($zipPath)) {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zipName . '"');
                header('Content-Length: ' . filesize($zipPath));
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');

                readfile($zipPath);
                unlink($zipPath); // Supprimer le fichier temporaire
                exit;
            } else {
                throw new Exception("Erreur lors de la création du fichier ZIP");
            }

        } catch (Exception $e) {
            custom_log("Erreur lors du téléchargement ZIP : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors du téléchargement : " . $e->getMessage();
            header('Location: ' . BASE_URL . 'materiel/view/' . $materielId);
            exit;
        }
    }

    /**
     * Récupère les sites d'un client via AJAX
     */
    public function get_sites()
    {
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

        $client_id = (int) $_GET['client_id'];

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
     * Récupère les salles d'un site (AJAX)
     */
    public function get_rooms()
    {
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
     * Récupère le niveau d'accès d'une salle (AJAX)
     */
    public function get_room_access_level()
    {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Non autorisé']);
            return;
        }

        $roomId = $_GET['room_id'] ?? null;

        if (!$roomId) {
            http_response_code(400);
            echo json_encode(['error' => 'ID de la salle manquant']);
            return;
        }

        try {
            // Récupérer le contrat lié à cette salle
            $sql = "SELECT c.id as contract_id, c.access_level_id, cal.name as access_level_name, cal.id as access_level_id
                    FROM contracts c
                    INNER JOIN contract_rooms cr ON c.id = cr.contract_id
                    INNER JOIN contract_access_levels cal ON c.access_level_id = cal.id
                    WHERE cr.room_id = :room_id
                    LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':room_id' => $roomId]);
            $contract = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($contract) {
                // Récupérer les règles de visibilité par défaut pour ce niveau d'accès
                $visibilityRules = $this->accessLevelModel->getVisibilityRulesForLevel($contract['access_level_id']);

                $response = [
                    'contract_id' => $contract['contract_id'],
                    'access_level_id' => $contract['access_level_id'],
                    'access_level_name' => $contract['access_level_name'],
                    'visibility_rules' => $visibilityRules
                ];

                echo json_encode($response);
            } else {
                echo json_encode(['error' => 'Aucun contrat trouvé pour cette salle']);
            }
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération du niveau d'accès : " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la récupération du niveau d\'accès']);
        }
    }

    /**
     * Affiche la page d'import Excel
     */
    public function import()
    {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        require_once __DIR__ . '/../includes/functions.php';
        if (!canImportMateriel()) {
            $_SESSION['error'] = "Vous n'avez pas les droits pour importer du matériel.";
            header('Location: ' . BASE_URL . 'materiel');
            exit;
        }

        try {
            $clients = $this->clientModel->getAllClients();
            $sites = [];
            $salles = [];
            if (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
                $sites = $this->siteModel->getSitesByClientId($_GET['client_id']);
                if (isset($_GET['site_id']) && !empty($_GET['site_id'])) {
                    $salles = $this->roomModel->getRoomsBySiteId($_GET['site_id']);
                } elseif (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
                    $salles = $this->roomModel->getRoomsByClientId($_GET['client_id']);
                }
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors du chargement des données : " . $e->getMessage();
            header('Location: ' . BASE_URL . 'materiel');
            exit;
        }

        require_once VIEWS_PATH . '/materiel/import.php';
    }

    /**
     * Traite l'import Excel de matériels
     */
    public function process_import()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'materiel/import');
            exit;
        }
        // Vérification des champs de localisation
        $client_id = $_POST['client_id'] ?? null;
        $site_id = $_POST['site_id'] ?? null;
        $salle_id = $_POST['salle_id'] ?? null;
        if (!$client_id || !$site_id || !$salle_id) {
            $_SESSION['error'] = "Veuillez sélectionner une localisation complète.";
            header('Location: ' . BASE_URL . 'materiel/import');
            exit;
        }
        // Vérification du fichier
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "Erreur lors de l'upload du fichier.";
            header('Location: ' . BASE_URL . 'materiel/import');
            exit;
        }
        $file = $_FILES['excel_file']['tmp_name'];
        // Charger PhpSpreadsheet
        require_once ROOT_PATH . '/vendor/autoload.php';
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        // S'assurer qu'on lit toujours le premier onglet (index 0) qui contient les données de matériel
        $sheet = $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, true);
        $imported = 0;
        $errors = [];
        // Récupérer le contrat de la salle pour les règles de visibilité
        $contractId = null;
        try {
            $sql = "SELECT c.id as contract_id 
                    FROM contracts c
                    INNER JOIN contract_rooms cr ON c.id = cr.contract_id
                    WHERE cr.room_id = :room_id
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':room_id' => $salle_id]);
            $contract = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($contract) {
                $contractId = $contract['contract_id'];
                custom_log("Contrat trouvé pour la salle $salle_id : $contractId", 'DEBUG');
            } else {
                custom_log("Aucun contrat trouvé pour la salle $salle_id", 'DEBUG');
            }
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération du contrat : " . $e->getMessage(), 'ERROR');
        }

        foreach ($rows as $i => $row) {
            if ($i == 1)
                continue; // Ignorer l'en-tête
            // Fonction pour convertir les dates du format français vers MySQL
            $convertDate = function ($dateString) {
                if (empty($dateString))
                    return null;

                // Nettoyer la chaîne
                $dateString = trim($dateString);

                // Vérifier si c'est déjà au format MySQL (AAAA-MM-JJ)
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateString)) {
                    return $dateString;
                }

                // Convertir du format français (JJ/MM/AAAA) vers MySQL
                if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateString, $matches)) {
                    $jour = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                    $mois = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    $annee = $matches[3];
                    return "$annee-$mois-$jour";
                }

                // Si le format n'est pas reconnu, retourner null
                return null;
            };

            $data = [
                'salle_id' => $salle_id,
                'type_materiel' => trim($row['K'] ?? ''),
                'modele' => $row['B'] ?? '',
                'marque' => $row['A'] ?? '',
                'numero_serie' => $row['C'] ?? null,
                'version_firmware' => $row['D'] ?? null,
                'ancien_firmware' => $row['E'] ?? null,
                'url_github' => $row['F'] ?? null, // NOUVEAU CHAMP
                'adresse_ip' => $row['G'] ?? null, // DÉCALÉ
                'adresse_mac' => $row['H'] ?? null, // DÉCALÉ
                'masque' => $row['I'] ?? null, // DÉCALÉ
                'passerelle' => $row['J'] ?? null, // DÉCALÉ
                'login' => $row['L'] ?? null, // DÉCALÉ
                'password' => $row['M'] ?? null, // DÉCALÉ
                'date_fin_maintenance' => $convertDate($row['N'] ?? null), // DÉCALÉ
                'date_fin_garantie' => $convertDate($row['O'] ?? null), // DÉCALÉ
                'commentaire' => $row['P'] ?? null // DÉCALÉ
            ];
            // Champs obligatoires
            if (empty($data['marque']) || empty($data['modele'])) {
                $errors[] = "Ligne $i : Marque et Modèle sont obligatoires.";
                continue;
            }
            try {
                // Créer le matériel
                $materielId = $this->materielModel->createMateriel($data);

                // Appliquer les règles de visibilité selon le contrat
                if ($materielId && $contractId) {
                    custom_log("Application des règles de visibilité pour matériel $materielId avec contrat $contractId", 'DEBUG');
                    $champs_visibilite = $this->materielModel->getChampsVisibilite(null, $contractId);
                    $visibilites = [];

                    foreach ($champs_visibilite as $nom_champ => $info) {
                        $visibilites[$nom_champ] = $info['visible_client'];
                    }

                    custom_log("Règles de visibilité pour matériel $materielId : " . json_encode($visibilites), 'DEBUG');
                    $this->materielModel->saveVisibiliteChamps($materielId, $visibilites);
                } else {
                    custom_log("Pas de règles de visibilité appliquées - materielId: $materielId, contractId: $contractId", 'DEBUG');
                }

                $imported++;
            } catch (Exception $e) {
                $errors[] = "Ligne $i : " . $e->getMessage();
            }
        }
        $_SESSION['success'] = "$imported matériels importés.";
        if ($errors) {
            $_SESSION['error'] = implode('<br>', $errors);
        }

        // Rediriger vers l'index avec les filtres de la localisation importée
        $redirectUrl = BASE_URL . 'materiel';
        $filters = [];
        if ($client_id) {
            $filters['client_id'] = $client_id;
        }
        if ($site_id) {
            $filters['site_id'] = $site_id;
        }
        if ($salle_id) {
            $filters['salle_id'] = $salle_id;
        }

        if (!empty($filters)) {
            $redirectUrl .= '?' . http_build_query($filters);
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Téléchargement du template Excel
     */
    public function download_template()
    {
        require_once __DIR__ . '/../includes/functions.php';
        if (!canImportMateriel()) {
            $_SESSION['error'] = "Vous n'avez pas les droits pour importer du matériel.";
            header('Location: ' . BASE_URL . 'materiel');
            exit;
        }

        $file = ROOT_PATH . '/assets/templates/materiel_import_template.xlsx';
        if (!file_exists($file)) {
            header('HTTP/1.0 404 Not Found');
            exit('Template non trouvé');
        }

        // Nettoyer le buffer de sortie
        if (ob_get_level()) {
            ob_end_clean();
        }

        // En-têtes pour le téléchargement
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="materiel_import_template.xlsx"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));

        // Lire et envoyer le fichier
        readfile($file);
        exit;
    }

    /**
     * Upload rapide de pièces jointes via AJAX
     */
    /**
     * Upload de pièces jointes pour le matériel (AJAX)
     * Utilise AttachmentService pour centraliser la logique
     */
    public function uploadAttachment()
    {
        $this->checkAccess();

        header('Content-Type: application/json');

        try {
            $materielId = $_POST['materiel_id'] ?? null;

            if (!$materielId) {
                throw new Exception('ID du matériel manquant');
            }

            // Vérifier que le matériel existe
            $materiel = $this->materielModel->getMaterielById($materielId);
            if (!$materiel) {
                throw new Exception('Matériel non trouvé');
            }

            // Vérifier qu'il y a des fichiers
            if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
                throw new Exception("Aucun fichier à uploader");
            }

            // Utiliser AttachmentService pour gérer l'upload
            $attachmentService = new AttachmentService($this->db);

            // Préparer les options
            $options = [
                'descriptions' => $_POST['descriptions'] ?? [],
                'masque_client' => $_POST['masque_client'] ?? []
            ];

            // Upload des fichiers
            $result = $attachmentService->upload(
                AttachmentService::TYPE_MATERIEL,
                $materielId,
                $_FILES['files'],
                $options,
                $_SESSION['user']['id'] ?? null
            );

            // Retourner le résultat
            $response = [
                'success' => $result['success'],
                'uploaded_files' => $result['uploaded_files'],
                'errors' => $result['errors']
            ];

            if (!$result['success'] && !empty($result['errors'])) {
                $response['error'] = implode(', ', $result['errors']);
            }

            echo json_encode($response);

        } catch (Exception $e) {
            custom_log("Erreur lors de l'upload des pièces jointes : " . $e->getMessage(), 'ERROR');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Affiche le matériel d'une salle spécifique (vue compacte pour staff)
     */
    public function salle($salleId)
    {
        $this->checkAccess();

        // Debug temporaire
        error_log("DEBUG: MaterielController::salle() appelé avec salleId = $salleId");

        // Récupérer les informations de la salle
        $salle = $this->roomModel->getRoomById($salleId);
        if (!$salle) {
            error_log("DEBUG: Salle $salleId non trouvée");
            $_SESSION['error'] = "Salle non trouvée.";
            header('Location: ' . BASE_URL . 'materiel');
            exit;
        }

        error_log("DEBUG: Salle trouvée: " . json_encode($salle));

        // Récupérer les informations du site
        $site = $this->siteModel->getSiteById($salle['site_id']);
        if (!$site) {
            error_log("DEBUG: Site {$salle['site_id']} non trouvé");
            $_SESSION['error'] = "Site non trouvé.";
            header('Location: ' . BASE_URL . 'materiel');
            exit;
        }

        error_log("DEBUG: Site trouvé: " . json_encode($site));

        // Récupérer le matériel de cette salle
        $filters = ['salle_id' => $salleId];
        $materiel_list = $this->materielModel->getAllMateriel($filters);

        error_log("DEBUG: Matériel trouvé: " . count($materiel_list) . " éléments");

        // Récupérer les informations de visibilité des champs
        $visibilites_champs = [];
        if (!empty($materiel_list)) {
            $materiel_ids = array_column($materiel_list, 'id');
            $visibilites_champs = $this->materielModel->getVisibiliteChampsForMateriels($materiel_ids);
        }

        error_log("DEBUG: Chargement de la vue salle.php");

        $pageTitle = "Matériel - " . $site['name'] . " - " . $salle['name'];
        require_once VIEWS_PATH . '/materiel/salle.php';
    }
}