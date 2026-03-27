<?php

// Inclure les fonctions utilitaires
require_once __DIR__ . '/../includes/functions.php';

class DashboardController {
    /**
     * Affiche le tableau de bord avec les informations de session
     */
    public function index() {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Récupérer les informations de l'utilisateur
        $userInfo = $_SESSION['user'];
        
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
    private function staffDashboard() {
        // Vérifier que l'utilisateur est staff (sécurité)
        if (!isStaff()) {
            $_SESSION['error'] = 'Accès non autorisé. Vous devez être membre du personnel pour accéder à cette page.';
            header('Location: ' . BASE_URL . 'auth/logout');
            exit;
        }
        
        // Récupérer les permissions de l'utilisateur
        $permissions = [];
        
        // Si les permissions sont dans la session
        if (isset($_SESSION['user']['permissions'])) {
            // Si les permissions sont stockées avec la structure 'rights'
            if (isset($_SESSION['user']['permissions']['rights'])) {
                $permissions = $_SESSION['user']['permissions']['rights'];
            } else {
                // Sinon, utiliser directement les permissions
                $permissions = $_SESSION['user']['permissions'];
            }
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
            
            // Préparer les données pour les graphiques camembert
            $pieChartLabelsNonPreventive = [];
            $pieChartSeriesNonPreventive = [];
            $pieChartColorsNonPreventive = [];
            
            foreach ($statsByStatusNonPreventive as $stat) {
                $pieChartLabelsNonPreventive[] = $stat['status'];
                $pieChartSeriesNonPreventive[] = (int)$stat['count'];
                $pieChartColorsNonPreventive[] = $stat['color'];
            }
            
            $pieChartLabelsPreventive = [];
            $pieChartSeriesPreventive = [];
            $pieChartColorsPreventive = [];
            
            foreach ($statsByStatusPreventive as $stat) {
                $pieChartLabelsPreventive[] = $stat['status'];
                $pieChartSeriesPreventive[] = (int)$stat['count'];
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
            
            // Log de l'erreur
            custom_log("Erreur lors du chargement des statistiques du dashboard : " . $e->getMessage(), 'ERROR');
        }
        
        // Inclure la vue du dashboard staff
        require_once VIEWS_PATH . '/dashboard/staff.php';
    }

    /**
     * Dashboard pour les clients
     */
    private function clientDashboard() {
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
            $userLocations = [['client_id' => $clientId, 'site_id' => null, 'room_id' => null]];
        }
        
        // Récupérer les informations du client
        $config = Config::getInstance();
        $db = $config->getDb();
        
        try {
            // Récupérer les informations du client
            custom_log("DEBUG - Dashboard client - Début, client_id: $clientId", 'DEBUG');
            $stmt = $db->prepare("
                SELECT id, name, city, email, phone, status, address, postal_code, 
                       comment, created_at, updated_at
                FROM clients 
                WHERE id = :client_id
            ");
            $stmt->execute(['client_id' => $clientId]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            custom_log("DEBUG - Dashboard client - Client récupéré: " . ($client ? 'OUI' : 'NON'), 'DEBUG');
            
            if (!$client) {
                $_SESSION['error'] = "Client non trouvé";
                header('Location: ' . BASE_URL . 'auth/logout');
                exit;
            }
            
            // Récupérer TOUS les sites du client
            custom_log("DEBUG - Dashboard client - Récupération des sites...", 'DEBUG');
            $stmt = $db->prepare("
                SELECT s.id, s.name, s.client_id, s.status, s.address, s.city, s.postal_code, 
                       s.phone, s.email, s.comment, 
                       s.created_at, s.updated_at, COUNT(r.id) as room_count
                FROM sites s
                LEFT JOIN rooms r ON s.id = r.site_id AND r.status = 1
                WHERE s.client_id = :client_id AND s.status = 1
                GROUP BY s.id, s.name, s.client_id, s.status, s.address, s.city, s.postal_code, 
                         s.phone, s.email, s.comment, s.created_at, s.updated_at
                ORDER BY s.name
            ");
            $stmt->execute(['client_id' => $clientId]);
            $allSites = $stmt->fetchAll(PDO::FETCH_ASSOC);
            custom_log("DEBUG - Dashboard client - Sites récupérés: " . count($allSites), 'DEBUG');
            
            // Pour chaque site, récupérer toutes ses salles
            custom_log("DEBUG - Dashboard client - Récupération des salles...", 'DEBUG');
            foreach ($allSites as &$site) {
                $stmt = $db->prepare("
                    SELECT r.id, r.name, r.status, r.comment, r.created_at, r.updated_at
                    FROM rooms r 
                    WHERE r.site_id = :site_id AND r.status = 1 
                    ORDER BY r.name
                ");
                $stmt->execute(['site_id' => $site['id']]);
                $site['rooms'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                custom_log("DEBUG - Dashboard client - Salles récupérées pour site {$site['id']}: " . count($site['rooms']), 'DEBUG');
            }
            
            custom_log("DEBUG - Dashboard client - Nombre de sites après array_values: " . count($allSites), 'DEBUG');
            custom_log("DEBUG - Dashboard client - userLocations: " . json_encode($userLocations), 'DEBUG');
            
            // Marquer les sites et salles autorisés
            custom_log("DEBUG - Dashboard client - Avant markAuthorizedLocations - Nombre de sites: " . count($allSites), 'DEBUG');
            $sitesWithAccess = $this->markAuthorizedLocations($allSites, $userLocations);
            custom_log("DEBUG - Dashboard client - Après markAuthorizedLocations - Nombre de sites: " . count($sitesWithAccess), 'DEBUG');
            
            // Récupérer les contrats ticket du client
            $ticketContracts = $this->getTicketContracts($db, $clientId);
            
            // Debug des permissions de l'utilisateur
            $this->debugUserPermissions();
            
            // Récupérer les interventions ouvertes si l'utilisateur a la permission
            $openInterventions = [];
            if (hasPermission('client_view_interventions')) {
                custom_log("DEBUG - Utilisateur a la permission client_view_interventions", 'DEBUG');
                $openInterventions = $this->getOpenInterventions($db, $clientId, $userLocations);
                custom_log("DEBUG - Nombre d'interventions ouvertes trouvées : " . count($openInterventions), 'DEBUG');
            } else {
                custom_log("DEBUG - Utilisateur n'a PAS la permission client_view_interventions", 'DEBUG');
            }
            
        } catch (Exception $e) {
            custom_log("Erreur lors du chargement du dashboard client : " . $e->getMessage(), 'ERROR');
            custom_log("DEBUG - Stack trace : " . $e->getTraceAsString(), 'ERROR');
            custom_log("DEBUG - Fichier : " . $e->getFile() . " - Ligne : " . $e->getLine(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors du chargement des données";
            $sitesWithAccess = [];
            $ticketContracts = [];
            $openInterventions = [];
        }
        
        // Inclure la vue du dashboard client
        require_once VIEWS_PATH . '/dashboard/client.php';
    }
    
    /**
     * Marque les sites et salles autorisés pour l'utilisateur
     * @param array $sites Tous les sites du client
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Les sites avec les informations d'accès
     */
    private function markAuthorizedLocations($sites, $userLocations) {
        $sitesWithAccess = [];
        
        foreach ($sites as $site) {
            $siteData = $site;
            $siteData['authorized'] = false;
            $siteData['rooms_authorized'] = [];
            
            // Vérifier si l'utilisateur a accès au site entier
            foreach ($userLocations as $location) {
                // Conversion explicite en entiers pour éviter les problèmes de type
                $locClientId = (int)$location['client_id'];
                $locSiteId = $location['site_id'] !== null ? (int)$location['site_id'] : null;
                $locRoomId = $location['room_id'] !== null ? (int)$location['room_id'] : null;
                $siteClientId = (int)$site['client_id'];
                $siteId = (int)$site['id'];
                
                if ($locClientId === $siteClientId) {
                    // Accès au client entier
                    if ($locSiteId === null && $locRoomId === null) {
                        $siteData['authorized'] = true;
                        // Toutes les salles sont autorisées
                        foreach ($site['rooms'] as $room) {
                            $siteData['rooms_authorized'][(int)$room['id']] = true;
                        }
                        break;
                    }
                    // Accès au site entier
                    elseif ($locSiteId === $siteId && $locRoomId === null) {
                        $siteData['authorized'] = true;
                        // Toutes les salles du site sont autorisées
                        foreach ($site['rooms'] as $room) {
                            $siteData['rooms_authorized'][(int)$room['id']] = true;
                        }
                        break;
                    }
                    // Accès à des salles spécifiques
                    elseif ($locSiteId === $siteId && $locRoomId !== null) {
                        $siteData['rooms_authorized'][$locRoomId] = true;
                        // Si l'utilisateur a accès à au moins une salle du site, le site est autorisé
                        $siteData['authorized'] = true;
                    }
                }
            }
            
            // Marquer les salles individuelles
            foreach ($siteData['rooms'] as $roomIndex => $room) {
                $roomId = (int)$room['id'];
                $siteData['rooms'][$roomIndex]['authorized'] = isset($siteData['rooms_authorized'][$roomId]) && $siteData['rooms_authorized'][$roomId] === true;
            }
            
            $sitesWithAccess[] = $siteData;
        }
        
        return $sitesWithAccess;
    }
    
    /**
     * Récupère les contrats ticket du client avec leurs informations
     * @param PDO $db Connexion à la base de données
     * @param int $clientId ID du client
     * @return array Liste des contrats ticket
     */
    private function getTicketContracts($db, $clientId) {
        try {
            // Récupérer les contrats avec tickets
            $stmt = $db->prepare("
                SELECT c.id, c.client_id, c.contract_type_id, c.access_level_id, c.name, c.start_date, c.end_date, 
                       c.tickets_number, c.tickets_remaining, c.comment, c.status, c.reminder_enabled, c.reminder_days, 
                       c.num_facture, c.tarif, c.indice, c.renouvellement_tacite, c.created_at, c.updated_at, 
                       ct.name as contract_type_name
                FROM contracts c
                LEFT JOIN contract_types ct ON c.contract_type_id = ct.id
                WHERE c.client_id = :client_id 
                AND c.status = 'actif' 
                AND c.tickets_number > 0
                ORDER BY c.end_date ASC
            ");
            $stmt->execute(['client_id' => $clientId]);
            $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Pour chaque contrat, récupérer la date du dernier achat
            foreach ($contracts as &$contract) {
                $contract['last_purchase_date'] = $this->getLastTicketPurchaseDate($db, $contract['id']);
                
                // Debug : afficher l'historique complet pour ce contrat
                $this->debugContractHistory($db, $contract['id']);
            }
            
            return $contracts;
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des contrats ticket : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Récupère la date du dernier achat de tickets pour un contrat
     * @param PDO $db Connexion à la base de données
     * @param int $contractId ID du contrat
     * @return string|null Date du dernier achat ou null
     */
    private function getLastTicketPurchaseDate($db, $contractId) {
        try {
            // D'abord, récupérer toutes les entrées liées aux tickets pour debug
            $debugStmt = $db->prepare("
                SELECT field_name, description, created_at
                FROM contract_history
                WHERE contract_id = :contract_id 
                AND (
                    field_name LIKE '%tickets%' 
                    OR field_name LIKE '%Tickets%'
                    OR description LIKE '%tickets%'
                    OR description LIKE '%Tickets%'
                )
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $debugStmt->execute(['contract_id' => $contractId]);
            $debugResults = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Log pour debug
            custom_log("DEBUG - Historique tickets pour contrat $contractId : " . json_encode($debugResults), 'DEBUG');
            
            // Maintenant chercher spécifiquement les ajouts
            $stmt = $db->prepare("
                SELECT created_at
                FROM contract_history
                WHERE contract_id = :contract_id 
                AND (
                    (field_name = 'Tickets initiaux' AND description LIKE '%Ajout de%tickets initiaux%')
                    OR (field_name = 'Tickets restants' AND description LIKE '%Ajout de%tickets restants%')
                    OR (field_name = 'Nombre de tickets' AND description LIKE '%Tickets initiaux définis%')
                    OR (description LIKE '%Ajout de%tickets%' AND description NOT LIKE '%Déduction%')
                )
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute(['contract_id' => $contractId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['created_at'] : null;
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération de la date du dernier achat : " . $e->getMessage(), 'ERROR');
            return null;
        }
    }
    
    /**
     * Récupère les interventions ouvertes du client
     * @param PDO $db Connexion à la base de données
     * @param int $clientId ID du client
     * @param array $userLocations Localisations autorisées de l'utilisateur
     * @return array Liste des interventions ouvertes
     */
    private function getOpenInterventions($db, $clientId, $userLocations) {
        try {
            custom_log("DEBUG - getOpenInterventions appelée pour client_id: $clientId", 'DEBUG');
            
            // Récupérer les interventions ouvertes
            $stmt = $db->prepare("
                SELECT i.*, 
                       s.name as site_name,
                       r.name as room_name,
                       its.name as status_name,
                       its.color as status_color,
                       it.name as type_name,
                       ip.name as priority_name,
                       ip.color as priority_color,
                       CONCAT(u.first_name, ' ', u.last_name) as technician_name
                FROM interventions i
                LEFT JOIN sites s ON i.site_id = s.id
                LEFT JOIN rooms r ON i.room_id = r.id
                LEFT JOIN intervention_statuses its ON i.status_id = its.id
                LEFT JOIN intervention_types it ON i.type_id = it.id
                LEFT JOIN intervention_priorities ip ON i.priority_id = ip.id
                LEFT JOIN users u ON i.technician_id = u.id
                WHERE i.client_id = :client_id 
                AND its.name NOT IN ('Fermé', 'Annulé', 'Terminé')
                ORDER BY i.created_at DESC
                LIMIT 10
            ");
            $stmt->execute(['client_id' => $clientId]);
            $interventions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            custom_log("DEBUG - Interventions trouvées avant filtrage : " . count($interventions), 'DEBUG');
            
            // Filtrer selon les autorisations de l'utilisateur
            $authorizedInterventions = [];
            foreach ($interventions as $intervention) {
                if ($this->isInterventionAuthorized($intervention, $userLocations)) {
                    $authorizedInterventions[] = $intervention;
                }
            }
            
            custom_log("DEBUG - Interventions autorisées après filtrage : " . count($authorizedInterventions), 'DEBUG');
            
            return $authorizedInterventions;
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des interventions ouvertes : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Vérifie si une intervention est autorisée pour l'utilisateur
     * @param array $intervention Données de l'intervention
     * @param array $userLocations Localisations autorisées de l'utilisateur
     * @return bool true si autorisée
     */
    private function isInterventionAuthorized($intervention, $userLocations) {
        foreach ($userLocations as $location) {
            $locClientId = (int)$location['client_id'];
            $locSiteId = $location['site_id'] !== null ? (int)$location['site_id'] : null;
            $locRoomId = $location['room_id'] !== null ? (int)$location['room_id'] : null;
            
            // Accès au client entier
            if ($locSiteId === null && $locRoomId === null) {
                custom_log("DEBUG - Intervention autorisée (accès client complet) : " . ($intervention['id'] ?? 'N/A'), 'DEBUG');
                return true;
            }
            
            $interventionSiteId = !empty($intervention['site_id']) ? (int)$intervention['site_id'] : null;
            $interventionRoomId = !empty($intervention['room_id']) ? (int)$intervention['room_id'] : null;
            
            // Accès au site entier
            if ($locSiteId === $interventionSiteId && $locRoomId === null && $interventionSiteId !== null) {
                custom_log("DEBUG - Intervention autorisée (accès site entier) : " . ($intervention['id'] ?? 'N/A'), 'DEBUG');
                return true;
            }
            
            // Accès à la salle spécifique
            if ($locSiteId === $interventionSiteId && $locRoomId === $interventionRoomId && $interventionSiteId !== null && $interventionRoomId !== null) {
                custom_log("DEBUG - Intervention autorisée (accès salle spécifique) : " . ($intervention['id'] ?? 'N/A'), 'DEBUG');
                return true;
            }
        }
        
        custom_log("DEBUG - Intervention NON autorisée : " . ($intervention['id'] ?? 'N/A') . " - site_id: " . ($intervention['site_id'] ?? 'null') . " - room_id: " . ($intervention['room_id'] ?? 'null'), 'DEBUG');
        return false;
    }
    
    /**
     * Méthode de debug pour afficher l'historique complet d'un contrat
     * @param PDO $db Connexion à la base de données
     * @param int $contractId ID du contrat
     */
    private function debugContractHistory($db, $contractId) {
        try {
            $stmt = $db->prepare("
                SELECT field_name, description, created_at, old_value, new_value
                FROM contract_history
                WHERE contract_id = :contract_id 
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $stmt->execute(['contract_id' => $contractId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            custom_log("DEBUG - Historique complet pour contrat $contractId : " . json_encode($history), 'DEBUG');
        } catch (Exception $e) {
            custom_log("Erreur lors du debug de l'historique : " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * Méthode de debug pour afficher les permissions de l'utilisateur
     */
    private function debugUserPermissions() {
        try {
            $user = $_SESSION['user'] ?? null;
            if ($user) {
                custom_log("DEBUG - Utilisateur connecté : " . json_encode([
                    'id' => $user['id'] ?? 'N/A',
                    'user_type' => $user['user_type'] ?? 'N/A',
                    'client_id' => $user['client_id'] ?? 'N/A',
                    'permissions' => $user['permissions'] ?? 'N/A'
                ]), 'DEBUG');
                
                // Test de la permission spécifique
                $hasPermission = hasPermission('client_view_interventions');
                custom_log("DEBUG - hasPermission('client_view_interventions') = " . ($hasPermission ? 'true' : 'false'), 'DEBUG');
            } else {
                custom_log("DEBUG - Aucun utilisateur connecté", 'DEBUG');
            }
        } catch (Exception $e) {
            custom_log("Erreur lors du debug des permissions : " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * Récupère les statistiques des interventions par statut
     */
    private function getInterventionStatsByStatus($db) {
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
    private function getInterventionStatsByStatusNonPreventive($db) {
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
    private function getInterventionStatsByStatusPreventive($db) {
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
    private function getInterventionStatsByPriority($db) {
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
    private function getExpiringContracts($db) {
        $query = "
            SELECT c.id, c.name, c.client_id, c.contract_type_id, c.access_level_id, c.start_date, c.end_date, 
                   c.status, c.tickets_number, c.tickets_remaining, c.tarif, 
                   c.created_at, c.updated_at, cl.name as client_name,
                   GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as site_names
            FROM contracts c
            JOIN clients cl ON c.client_id = cl.id
            LEFT JOIN contract_rooms cr ON c.id = cr.contract_id
            LEFT JOIN rooms r ON cr.room_id = r.id
            LEFT JOIN sites s ON r.site_id = s.id AND s.status = 1
            WHERE c.status = 'actif'
            AND c.contract_type_id IS NOT NULL
            AND (
                (c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY))
                OR (c.end_date < CURDATE())
            )
            GROUP BY c.id, c.name, c.client_id, c.contract_type_id, c.access_level_id, c.start_date, c.end_date, 
                     c.status, c.tickets_number, c.tickets_remaining, c.tarif, 
                     c.created_at, c.updated_at, cl.name
            ORDER BY c.end_date ASC
        ";
        return $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère les contrats actifs avec moins de 5 tickets
     */
    private function getLowTicketsContracts($db) {
        $query = "
            SELECT c.id, c.name, c.client_id, c.contract_type_id, c.access_level_id, c.start_date, c.end_date, 
                   c.status, c.tickets_number, c.tickets_remaining, c.tarif, 
                   c.created_at, c.updated_at, cl.name as client_name,
                   GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as site_names
            FROM contracts c
            JOIN clients cl ON c.client_id = cl.id
            LEFT JOIN contract_rooms cr ON c.id = cr.contract_id
            LEFT JOIN rooms r ON cr.room_id = r.id
            LEFT JOIN sites s ON r.site_id = s.id AND s.status = 1
            WHERE c.status = 'actif'
            AND c.tickets_remaining < 5
            AND c.tickets_number > 0
            AND c.contract_type_id IS NOT NULL
            GROUP BY c.id, c.name, c.client_id, c.contract_type_id, c.access_level_id, c.start_date, c.end_date, 
                     c.status, c.tickets_number, c.tickets_remaining, c.tarif, 
                     c.created_at, c.updated_at, cl.name
            ORDER BY c.tickets_remaining ASC
        ";
        return $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère les interventions avec statut "Nouveau" (hors préventives)
     */
    private function getNewInterventions($db) {
        $query = "
            SELECT i.id, i.reference, i.title, i.client_id, i.site_id, i.room_id, i.status_id, i.priority_id, 
                   i.type_id, i.technician_id, i.description, i.created_at, i.updated_at,
                   c.name as client_name, s.name as site_name, r.name as room_name,
                   p.name as priority, p.color as color, t.name as type
            FROM interventions i
            JOIN clients c ON i.client_id = c.id
            LEFT JOIN sites s ON i.site_id = s.id
            LEFT JOIN rooms r ON i.room_id = r.id
            JOIN intervention_priorities p ON i.priority_id = p.id
            JOIN intervention_types t ON i.type_id = t.id
            JOIN intervention_statuses st ON i.status_id = st.id
            WHERE st.name = 'Nouveau'
            AND p.name != 'Préventif'
            ORDER BY i.created_at DESC
        ";
        return $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère les prochaines interventions planifiées
     */
    private function getPlannedInterventions($db) {
        $query = "
            SELECT i.id, i.reference, i.title, c.name as client_name, 
                   i.date_planif, i.heure_planif, u.first_name, u.last_name
            FROM interventions i
            JOIN clients c ON i.client_id = c.id
            LEFT JOIN users u ON i.technician_id = u.id
            WHERE i.date_planif IS NOT NULL 
            AND i.date_planif >= CURDATE()
            AND i.status_id NOT IN (6, 7)
            ORDER BY i.date_planif ASC, i.heure_planif ASC
            LIMIT 5
        ";
        return $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère les salles sans contrat affecté
     */
    private function getRoomsWithoutContract($db) {
        $query = "
            SELECT r.id, r.name as room_name, r.comment, r.status,
                   c.name as client_name, s.name as site_name,
                   CONCAT(cont.first_name, ' ', cont.last_name) as contact_name
            FROM rooms r
            JOIN sites s ON r.site_id = s.id
            JOIN clients c ON s.client_id = c.id
            LEFT JOIN contacts cont ON r.main_contact_id = cont.id
            LEFT JOIN contract_rooms cr ON r.id = cr.room_id
            LEFT JOIN contracts co ON cr.contract_id = co.id AND co.status = 'actif'
            WHERE r.status = 1
            AND cr.contract_id IS NULL
            ORDER BY c.name, s.name, r.name
        ";
        return $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère les données financières (valeur des tickets et contrats)
     */
    private function getFinancialData($db) {
        // 1. Récupérer le tarif d'un ticket depuis les settings
        $tarifTicket = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'tarif_ticket'")->fetchColumn();
        $tarifTicket = $tarifTicket ? (float)$tarifTicket : 90.0;
        
        // 2. Calculer la valeur des tickets restants
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(tickets_remaining * :tarif_ticket), 0) as total_value
            FROM contracts 
            WHERE status = 'actif' 
            AND contract_type_id IS NOT NULL
            AND tickets_remaining > 0
        ");
        $stmt->execute([':tarif_ticket' => $tarifTicket]);
        $ticketsValue = $stmt->fetchColumn();
        
        // 3. Calculer la somme des montants des contrats actifs
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