<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../models/ContractModel.php';
require_once __DIR__ . '/../models/ClientModel.php';
require_once __DIR__ . '/../models/SiteModel.php';
require_once __DIR__ . '/../models/RoomModel.php';
require_once __DIR__ . '/../models/AccessLevelModel.php';

require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';

class HorsContratFacturableController
{
    use AccessControlTrait;
    private $db;
    private $contractModel;
    private $clientModel;
    private $siteModel;
    private $roomModel;
    private $accessLevelModel;

    public function __construct()
    {
        global $db;
        $this->db = $db;
        $this->contractModel = new ContractModel($this->db);
        $this->clientModel = new ClientModel($this->db);
        $this->siteModel = new SiteModel($this->db);
        $this->roomModel = new RoomModel($this->db);
        $this->accessLevelModel = new AccessLevelModel($this->db);
    }

    /**
     * Vérifie si l'utilisateur est connecté et est staff
     */

    /**
     * Affiche la liste des contrats hors contrat facturable
     */
    public function index()
    {
        $this->checkAccess();

        // Récupérer le filtre d'affichage des statuts
        $show_status = $_GET['show_status'] ?? 'actif'; // Par défaut: 'actif'

        // Récupérer le filtre d'affichage des types de tickets
        $ticket_type = $_GET['ticket_type'] ?? 'all'; // Par défaut: 'all'

        // Récupérer les autres filtres
        $filters = [
            'client_id' => $_GET['client_id'] ?? null,
            'site_id' => $_GET['site_id'] ?? null,
            'room_id' => $_GET['room_id'] ?? null,
        ];

        // Appliquer le filtre de statut pour la requête SQL
        if ($show_status === 'actif') {
            $filters['status'] = 'actif';
        } elseif ($show_status === 'inactif') {
            $filters['status'] = 'inactif';
        } elseif ($show_status === 'en_attente') {
            $filters['status'] = 'en_attente';
        }

        // Appliquer le filtre de type de tickets
        if ($ticket_type === 'with_tickets') {
            $filters['ticket_type'] = 'with_tickets';
        } elseif ($ticket_type === 'without_tickets') {
            $filters['ticket_type'] = 'without_tickets';
        }

        // Récupérer les contrats hors contrat facturable
        $contracts = $this->contractModel->getHorsContratFacturableContracts($filters);

        // Récupérer les données pour les filtres
        $clients = $this->clientModel->getAllClients();
        $sites = [];
        if ($filters['client_id']) {
            $sites = $this->siteModel->getSitesByClientId($filters['client_id']);
        }
        $rooms = [];
        if ($filters['site_id']) {
            $rooms = $this->roomModel->getRoomsByBuildingId($filters['site_id']);
        }

        // Statistiques
        $stats = $this->contractModel->getHorsContratFacturableStats();

        // Définir les variables de page
        setPageVariables('Contrats Hors Contrat Facturable', 'hors_contrat_facturable');
        $currentPage = 'hors_contrat_facturable';

        // Inclure les vues
        include_once __DIR__ . '/../includes/header.php';
        include_once __DIR__ . '/../includes/sidebar.php';
        include_once __DIR__ . '/../includes/navbar.php';
        include_once VIEWS_PATH . '/contract/hors_contrat_facturable.php';
        include_once __DIR__ . '/../includes/footer.php';
    }


    /**
     * Affiche le détail d'un contrat hors contrat facturable
     */
    public function view($id)
    {
        $this->checkAccess();

        $contract = $this->contractModel->getContractById($id);

        if (!$contract) {
            $_SESSION['error'] = "Contrat introuvable.";
            header('Location: ' . BASE_URL . 'hors_contrat_facturable');
            exit;
        }

        // Vérifier que c'est bien un contrat hors contrat facturable
        if (!str_contains(strtolower($contract['name']), 'hors contrat facturable')) {
            $_SESSION['error'] = "Ce contrat n'est pas un contrat hors contrat facturable.";
            header('Location: ' . BASE_URL . 'hors_contrat_facturable');
            exit;
        }

        // Récupérer les informations du client
        $client = $this->clientModel->getClientById($contract['client_id']);

        // Récupérer les salles associées
        $rooms = $this->contractModel->getContractRooms($id);

        // Récupérer les interventions liées - CORRIGÉ (plus de LEFT JOIN sur users)
        $sql_interventions = "SELECT i.*, 
                ist.name as status_name,
                ist.color as status_color,
                it.name as type_name
                FROM interventions i
                LEFT JOIN intervention_statuses ist ON i.status_id = ist.id
                LEFT JOIN intervention_types it ON i.type_id = it.id
                WHERE i.contract_id = ?
                ORDER BY COALESCE( i.created_at) DESC";

        $stmt_interventions = $this->db->prepare($sql_interventions);
        $stmt_interventions->execute([$id]);
        $interventions = $stmt_interventions->fetchAll(PDO::FETCH_ASSOC);

        // Pour chaque intervention, récupérer les techniciens assignés
        $userModel = new UserModel($this->db);

        foreach ($interventions as &$intervention) {
            // Récupérer les techniciens assignés à cette intervention via la table intervention_techniciens
            $sql_technicians = "SELECT u.id, u.first_name, u.last_name, u.email
                                FROM intervention_techniciens it
                                JOIN users u ON it.technicien_id = u.id
                                WHERE it.intervention_id = ?";
            $stmt_tech = $this->db->prepare($sql_technicians);
            $stmt_tech->execute([$intervention['id']]);
            $technicians = $stmt_tech->fetchAll(PDO::FETCH_ASSOC);

            // Construire le nom des techniciens pour l'affichage
            $technician_names = [];
            foreach ($technicians as $tech) {
                $technician_names[] = $tech['first_name'] . ' ' . $tech['last_name'];
            }
            $intervention['technician_name'] = !empty($technician_names) ? implode(', ', $technician_names) : 'Non assigné';
        }

        // Définir les variables de page
        setPageVariables('Détail du Contrat Hors Contrat Facturable', 'hors_contrat_facturable');
        $currentPage = 'hors_contrat_facturable';

        // Inclure les vues
        include_once __DIR__ . '/../includes/header.php';
        include_once __DIR__ . '/../includes/sidebar.php';
        include_once __DIR__ . '/../includes/navbar.php';
        include_once VIEWS_PATH . '/contract/viewhc.php';
        include_once __DIR__ . '/../includes/footer.php';
    }
}
