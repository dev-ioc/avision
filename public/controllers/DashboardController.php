<?php

// Inclure les fonctions utilitaires
require_once __DIR__ . '/../includes/functions.php';

class DashboardController
{
    /**
     * Affiche le tableau de bord avec les informations de session
     */
    public function index()
    {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Utiliser les fonctions helper pour déterminer le type d'utilisateur
        if (isClient()) {
            $this->clientDashboard();
        } else {
            $this->staffDashboard();
        }
    }

    /**
     * Dashboard pour le personnel (admin, technicien)
     */

    private function staffDashboard()
    {
        // Vérifier que l'utilisateur est staff (sécurité)
        if (!isStaff()) {
            $_SESSION['error'] = 'Accès non autorisé. Vous devez être membre du personnel pour accéder à cette page.';
            header('Location: ' . BASE_URL . 'auth/logout');
            exit;
        }

        // Récupération de l'instance de la base de données
        $config = Config::getInstance();
        $db = $config->getDb();

        // Récupération des statistiques des interventions
        try {
            $statsByStatus = $this->getInterventionStatsByStatus($db);
            $statsByStatusNonPreventive = $this->getInterventionStatsByStatusNonPreventive($db);
            $statsByStatusPreventive = $this->getInterventionStatsByStatusPreventive($db);
            $statsByPriority = $this->getInterventionStatsByPriority($db);
            $expiringContracts = $this->getExpiringContracts($db);
            $lowTicketsContracts = $this->getLowTicketsContracts($db);
            $newInterventions = $this->getNewInterventions($db);
            $plannedInterventions = $this->getPlannedInterventions($db);
            $roomsWithoutContract = $this->getRoomsWithoutContract($db);
            $financialData = $this->getFinancialData($db);

            // AJOUT : Récupérer la liste des clients pour le modal Flash Intervention
            $clients = $this->getAllClients($db);

            // Préparer les données pour les graphiques camembert
            $pieChartLabelsNonPreventive = [];
            $pieChartSeriesNonPreventive = [];
            $pieChartColorsNonPreventive = [];

            foreach ($statsByStatusNonPreventive as $stat) {
                $pieChartLabelsNonPreventive[] = $stat['status'];
                $pieChartSeriesNonPreventive[] = (int) $stat['count'];
                $pieChartColorsNonPreventive[] = $stat['color'];
            }

            $pieChartLabelsPreventive = [];
            $pieChartSeriesPreventive = [];
            $pieChartColorsPreventive = [];

            foreach ($statsByStatusPreventive as $stat) {
                $pieChartLabelsPreventive[] = $stat['status'];
                $pieChartSeriesPreventive[] = (int) $stat['count'];
                $pieChartColorsPreventive[] = $stat['color'];
            }

        } catch (Exception $e) {
            // En cas d'erreur, initialiser les variables avec des tableaux vides
            $statsByStatus = [];
            $statsByStatusNonPreventive = [];
            $statsByStatusPreventive = [];
            $statsByPriority = [];
            $expiringContracts = [];
            $lowTicketsContracts = [];
            $newInterventions = [];
            $plannedInterventions = [];
            $roomsWithoutContract = [];
            $financialData = ['ticketsValue' => 0, 'contractsValue' => 0, 'tarifTicket' => 90.0];
            $pieChartLabelsNonPreventive = [];
            $pieChartSeriesNonPreventive = [];
            $pieChartColorsNonPreventive = [];
            $pieChartLabelsPreventive = [];
            $pieChartSeriesPreventive = [];
            $pieChartColorsPreventive = [];
            $clients = []; // AJOUT : Initialiser $clients vide en cas d'erreur

            // Log de l'erreur
            custom_log("Erreur lors du chargement des statistiques du dashboard : " . $e->getMessage(), 'ERROR');
        }

        // AJOUT : Passer $clients à la vue via des variables globales ou inclure le fichier avec les données
        // La vue dashboard/staff.php a besoin de $clients
        require_once VIEWS_PATH . '/dashboard/staff.php';
    }
    /**
     * Récupère la liste de tous les clients
     */
    private function getAllClients($db)
    {
        // Supprimer "WHERE deleted_at IS NULL" car cette colonne n'existe pas
        $sql = "SELECT id, name FROM clients ORDER BY name ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Dashboard pour les clients
     */
    private function clientDashboard()
    {
        // Vérifier que l'utilisateur est client (sécurité)
        if (!isClient()) {
            $_SESSION['error'] = 'Accès non autorisé. Cette page est réservée aux clients.';
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

        // Récupérer les localisations autorisées de l'utilisateur
        $userLocations = getUserLocations();

        // Si l'utilisateur n'a pas de localisations définies, utiliser le client_id par défaut
        if (empty($userLocations)) {
            $userLocations = [['client_id' => $clientId, 'site_id' => null, 'building_id' => null, 'room_id' => null]];
        }

        // Récupérer les informations du client
        $config = Config::getInstance();
        $db = $config->getDb();

        try {
            // Récupérer les informations du client
            $stmt = $db->prepare("
                SELECT id, name, city, email, phone, status, address, postal_code, 
                       comment, created_at, updated_at
                FROM clients 
                WHERE id = :client_id
            ");
            $stmt->execute(['client_id' => $clientId]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$client) {
                $_SESSION['error'] = "Client non trouvé";
                header('Location: ' . BASE_URL . 'auth/logout');
                exit;
            }

            // Récupérer TOUS les sites du client
            $stmt = $db->prepare("
                SELECT s.id, s.name, s.client_id, s.status, s.address, s.city, s.postal_code, 
                       s.phone, s.email, s.comment, 
                       s.created_at, s.updated_at
                FROM sites s
                WHERE s.client_id = :client_id AND s.status = 1
                ORDER BY s.name
            ");
            $stmt->execute(['client_id' => $clientId]);
            $allSites = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Pour chaque site, récupérer tous ses bâtiments
            foreach ($allSites as &$site) {
                $stmt = $db->prepare("
                    SELECT b.id, b.name, b.site_id, b.status, b.comment, 
                           b.created_at, b.updated_at
                    FROM buildings b 
                    WHERE b.site_id = :site_id AND b.status = 1 
                    ORDER BY b.name
                ");
                $stmt->execute(['site_id' => $site['id']]);
                $site['buildings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Pour chaque bâtiment, récupérer toutes ses salles
                foreach ($site['buildings'] as &$building) {
                    $stmt = $db->prepare("
                        SELECT r.id, r.name, r.building_id, r.status, r.comment, 
                               r.created_at, r.updated_at
                        FROM rooms r 
                        WHERE r.building_id = :building_id AND r.status = 1 
                        ORDER BY r.name
                    ");
                    $stmt->execute(['building_id' => $building['id']]);
                    $building['rooms'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }

            // Marquer les sites, bâtiments et salles autorisés
            $sitesWithAccess = $this->markAuthorizedLocations($allSites, $userLocations);

            // Récupérer les contrats ticket du client
            $ticketContracts = $this->getTicketContracts($db, $clientId);

            // Récupérer les interventions ouvertes si l'utilisateur a la permission
            $openInterventions = [];
            if (hasPermission('client_view_interventions')) {
                $openInterventions = $this->getOpenInterventions($db, $clientId, $userLocations);
            }

        } catch (Exception $e) {
            custom_log("Erreur lors du chargement du dashboard client : " . $e->getMessage(), 'ERROR');
            $sitesWithAccess = [];
            $ticketContracts = [];
            $openInterventions = [];
        }

        // Inclure la vue du dashboard client
        require_once VIEWS_PATH . '/dashboard/client.php';
    }

    /**
     * Marque les sites, bâtiments et salles autorisés pour l'utilisateur
     */
    private function markAuthorizedLocations($sites, $userLocations)
    {
        $sitesWithAccess = [];

        foreach ($sites as $site) {
            $siteData = $site;
            $siteData['authorized'] = false;
            $siteData['buildings_authorized'] = [];

            foreach ($userLocations as $location) {
                $locClientId = (int) $location['client_id'];
                $locSiteId = $location['site_id'] !== null ? (int) $location['site_id'] : null;
                $locBuildingId = $location['building_id'] !== null ? (int) $location['building_id'] : null;
                $locRoomId = $location['room_id'] !== null ? (int) $location['room_id'] : null;
                $siteClientId = (int) $site['client_id'];
                $siteId = (int) $site['id'];

                if ($locClientId === $siteClientId) {
                    // Accès au client entier
                    if ($locSiteId === null && $locBuildingId === null && $locRoomId === null) {
                        $siteData['authorized'] = true;
                        foreach ($site['buildings'] as $building) {
                            $siteData['buildings_authorized'][(int) $building['id']] = true;
                            foreach ($building['rooms'] as $room) {
                                $siteData['buildings_authorized']['rooms'][(int) $room['id']] = true;
                            }
                        }
                        break;
                    }
                    // Accès au site entier
                    elseif ($locSiteId === $siteId && $locBuildingId === null && $locRoomId === null) {
                        $siteData['authorized'] = true;
                        foreach ($site['buildings'] as $building) {
                            $siteData['buildings_authorized'][(int) $building['id']] = true;
                            foreach ($building['rooms'] as $room) {
                                $siteData['buildings_authorized']['rooms'][(int) $room['id']] = true;
                            }
                        }
                        break;
                    }
                    // Accès à des bâtiments spécifiques
                    elseif ($locSiteId === $siteId && $locBuildingId !== null && $locRoomId === null) {
                        $siteData['buildings_authorized'][$locBuildingId] = true;
                        $siteData['authorized'] = true;
                        foreach ($site['buildings'] as &$building) {
                            if ((int) $building['id'] === $locBuildingId) {
                                foreach ($building['rooms'] as $room) {
                                    $siteData['buildings_authorized']['rooms'][(int) $room['id']] = true;
                                }
                            }
                        }
                    }
                    // Accès à des salles spécifiques
                    elseif ($locSiteId === $siteId && $locBuildingId !== null && $locRoomId !== null) {
                        $siteData['buildings_authorized']['rooms'][$locRoomId] = true;
                        $siteData['buildings_authorized'][$locBuildingId] = true;
                        $siteData['authorized'] = true;
                    }
                }
            }

            // Marquer les bâtiments et salles individuels
            foreach ($siteData['buildings'] as $buildingIndex => $building) {
                $buildingId = (int) $building['id'];
                $siteData['buildings'][$buildingIndex]['authorized'] =
                    isset($siteData['buildings_authorized'][$buildingId]) &&
                    $siteData['buildings_authorized'][$buildingId] === true;

                foreach ($building['rooms'] as $roomIndex => $room) {
                    $roomId = (int) $room['id'];
                    $siteData['buildings'][$buildingIndex]['rooms'][$roomIndex]['authorized'] =
                        isset($siteData['buildings_authorized']['rooms'][$roomId]) &&
                        $siteData['buildings_authorized']['rooms'][$roomId] === true;
                }
            }

            $sitesWithAccess[] = $siteData;
        }

        return $sitesWithAccess;
    }

    /**
     * Récupère les contrats ticket du client
     */
    private function getTicketContracts($db, $clientId)
    {
        try {
            $stmt = $db->prepare("
                SELECT c.id, c.name, c.start_date, c.end_date, 
                       c.tickets_number, c.tickets_remaining, c.tarif,
                       ct.name as contract_type_name
                FROM contracts c
                LEFT JOIN contract_types ct ON c.contract_type_id = ct.id
                WHERE c.client_id = :client_id 
                AND c.status = 'actif' 
                AND c.tickets_number > 0
                ORDER BY c.end_date ASC
            ");
            $stmt->execute(['client_id' => $clientId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des contrats ticket : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Récupère les interventions ouvertes du client
     */
    private function getOpenInterventions($db, $clientId, $userLocations)
    {
        try {
            $stmt = $db->prepare("
                SELECT i.*, 
                       s.name as site_name,
                       b.name as building_name,
                       r.name as room_name,
                       its.name as status_name,
                       its.color as status_color,
                       it.name as type_name,
                       ip.name as priority_name,
                       ip.color as priority_color,
                       GROUP_CONCAT(DISTINCT CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') as technicians_names
                FROM interventions i
                LEFT JOIN sites s ON i.site_id = s.id
                LEFT JOIN buildings b ON i.building_id = b.id
                LEFT JOIN rooms r ON i.room_id = r.id
                LEFT JOIN intervention_statuses its ON i.status_id = its.id
                LEFT JOIN intervention_types it ON i.type_id = it.id
                LEFT JOIN intervention_priorities ip ON i.priority_id = ip.id
                LEFT JOIN intervention_techniciens itech ON i.id = itech.intervention_id
                LEFT JOIN users u ON itech.technicien_id = u.id
                WHERE i.client_id = :client_id 
                AND its.name NOT IN ('Fermé', 'Annulé', 'Terminé')
                GROUP BY i.id
                ORDER BY i.created_at DESC
                LIMIT 10
            ");
            $stmt->execute(['client_id' => $clientId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des interventions ouvertes : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Récupère les statistiques des interventions par statut
     */
    private function getInterventionStatsByStatus($db)
    {
        $query = "
            SELECT s.name as status, s.color as color, COUNT(i.id) as count
            FROM interventions i
            JOIN intervention_statuses s ON i.status_id = s.id
            WHERE i.status_id NOT IN (6, 7)
            GROUP BY s.name, s.id, s.color
            ORDER BY s.id
        ";
        return $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques des interventions NON préventives par statut
     */
    private function getInterventionStatsByStatusNonPreventive($db)
    {
        $query = "
            SELECT s.name as status, s.color as color, COUNT(i.id) as count
            FROM interventions i
            JOIN intervention_statuses s ON i.status_id = s.id
            JOIN intervention_priorities p ON i.priority_id = p.id
            WHERE i.status_id NOT IN (6, 7)
            AND p.name != 'Préventif'
            GROUP BY s.name, s.id, s.color
            ORDER BY s.id
        ";
        return $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques des interventions préventives par statut
     */
    private function getInterventionStatsByStatusPreventive($db)
    {
        $query = "
            SELECT s.name as status, s.color as color, COUNT(i.id) as count
            FROM interventions i
            JOIN intervention_statuses s ON i.status_id = s.id
            JOIN intervention_priorities p ON i.priority_id = p.id
            WHERE i.status_id NOT IN (6, 7)
            AND p.name = 'Préventif'
            GROUP BY s.name, s.id, s.color
            ORDER BY s.id
        ";
        return $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques des interventions par priorité
     */
    private function getInterventionStatsByPriority($db)
    {
        $query = "
            SELECT p.name as priority, p.color as color, COUNT(i.id) as count
            FROM interventions i
            JOIN intervention_priorities p ON i.priority_id = p.id
            WHERE i.status_id NOT IN (6, 7)
            GROUP BY p.name, p.id, p.color
            ORDER BY p.id
        ";
        return $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les contrats expirant dans les 30 prochains jours
     */
    private function getExpiringContracts($db)
    {
        $query = "
            SELECT c.id, c.name, c.client_id, c.end_date, c.status, 
                   c.tickets_number, c.tickets_remaining, cl.name as client_name
            FROM contracts c
            JOIN clients cl ON c.client_id = cl.id
            WHERE c.status = 'actif'
            AND c.contract_type_id IS NOT NULL
            AND c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY c.end_date ASC
        ";
        return $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les contrats actifs avec moins de 5 tickets
     */
    private function getLowTicketsContracts($db)
    {
        $query = "
            SELECT c.id, c.name, c.client_id, c.end_date, c.status, 
                   c.tickets_number, c.tickets_remaining, cl.name as client_name
            FROM contracts c
            JOIN clients cl ON c.client_id = cl.id
            WHERE c.status = 'actif'
            AND c.tickets_remaining < 5
            AND c.tickets_number > 0
            AND c.contract_type_id IS NOT NULL
            ORDER BY c.tickets_remaining ASC
        ";
        return $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les interventions avec statut "Nouveau"
     */
    private function getNewInterventions($db)
    {
        $query = "
            SELECT i.id, i.reference, i.title, i.client_id, i.created_at,
                   c.name as client_name, s.name as site_name, b.name as building_name, r.name as room_name,
                   p.name as priority, p.color as color, t.name as type,
                   GROUP_CONCAT(DISTINCT CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') as technicians_names
            FROM interventions i
            JOIN clients c ON i.client_id = c.id
            LEFT JOIN sites s ON i.site_id = s.id
            LEFT JOIN buildings b ON i.building_id = b.id
            LEFT JOIN rooms r ON i.room_id = r.id
            JOIN intervention_priorities p ON i.priority_id = p.id
            JOIN intervention_types t ON i.type_id = t.id
            JOIN intervention_statuses st ON i.status_id = st.id
            LEFT JOIN intervention_techniciens itech ON i.id = itech.intervention_id
            LEFT JOIN users u ON itech.technicien_id = u.id
            WHERE st.name = 'Nouveau'
            AND p.name != 'Préventif'
            GROUP BY i.id
            ORDER BY i.created_at DESC
        ";
        return $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les prochaines interventions planifiées
     */
    private function getPlannedInterventions($db)
    {
        $query = "
            SELECT i.id, i.reference, i.title, c.name as client_name,
                   GROUP_CONCAT(DISTINCT CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') as technicians_names
            FROM interventions i
            JOIN clients c ON i.client_id = c.id
            LEFT JOIN intervention_techniciens itech ON i.id = itech.intervention_id
            LEFT JOIN users u ON itech.technicien_id = u.id
            AND i.status_id NOT IN (6, 7)
            GROUP BY i.id
            LIMIT 5
        ";
        return $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les salles sans contrat affecté
     */
    private function getRoomsWithoutContract($db)
    {
        $query = "
            SELECT r.id, r.name as room_name, r.comment, r.status,
                   c.name as client_name, s.name as site_name, b.name as building_name
            FROM rooms r
            JOIN buildings b ON r.building_id = b.id
            JOIN sites s ON b.site_id = s.id
            JOIN clients c ON s.client_id = c.id
            LEFT JOIN contract_rooms cr ON r.id = cr.room_id
            WHERE r.status = 1
            AND cr.contract_id IS NULL
            ORDER BY c.name, s.name, b.name, r.name
        ";
        return $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les données financières
     */
    private function getFinancialData($db)
    {
        $tarifTicket = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'tarif_ticket'")->fetchColumn();
        $tarifTicket = $tarifTicket ? (float) $tarifTicket : 90.0;

        $stmt = $db->prepare("
            SELECT COALESCE(SUM(tickets_remaining * :tarif_ticket), 0) as total_value
            FROM contracts 
            WHERE status = 'actif' 
            AND contract_type_id IS NOT NULL
            AND tickets_remaining > 0
        ");
        $stmt->execute([':tarif_ticket' => $tarifTicket]);
        $ticketsValue = $stmt->fetchColumn();

        $contractsValue = $db->query("
            SELECT COALESCE(SUM(CAST(tarif AS DECIMAL(10,2))), 0) as total_value
            FROM contracts 
            WHERE status = 'actif' 
            AND contract_type_id IS NOT NULL
            AND tarif IS NOT NULL 
            AND tarif != ''
            AND tarif != '0.00'
        ")->fetchColumn();

        return [
            'ticketsValue' => $ticketsValue,
            'contractsValue' => $contractsValue,
            'tarifTicket' => $tarifTicket
        ];
    }
}