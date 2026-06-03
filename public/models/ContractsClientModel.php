<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';

/**
 * Modèle pour la gestion des contrats clients
 * Filtre automatiquement selon les localisations autorisées
 */
class ContractsClientModel extends BaseModel
{
    public function __construct($db)
    {
        parent::__construct($db);
        $this->table = 'contracts';
    }

    /**
     * Récupère tous les contrats selon les localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @param array $filters Filtres supplémentaires
     * @return array Liste des contrats
     */
    public function getAllByLocations($userLocations, $filters = [])
    {
        $locationWhere = buildLocationWhereClause($userLocations, 'c.client_id', 's.id', 'b.id', 'r.id');

        $sql = "SELECT DISTINCT c.*, 
                cl.name as client_name,
                ct.name as contract_type_name,
                COUNT(DISTINCT i.id) as interventions_count,
                SUM(CASE WHEN i.status_id = (SELECT id FROM intervention_statuses WHERE name = 'Fermé') THEN 1 ELSE 0 END) as closed_interventions_count,
                COUNT(DISTINCT CASE WHEN pj.masque_client = 0 THEN pj.id END) as attachments_count
                FROM " . $this->table . " c
                LEFT JOIN clients cl ON c.client_id = cl.id
                LEFT JOIN contract_types ct ON c.contract_type_id = ct.id
                LEFT JOIN contract_rooms cr ON c.id = cr.contract_id
                LEFT JOIN rooms r ON cr.room_id = r.id
                LEFT JOIN buildings b ON r.building_id = b.id
                LEFT JOIN sites s ON b.site_id = s.id
                LEFT JOIN interventions i ON c.id = i.contract_id
                LEFT JOIN liaisons_pieces_jointes lpj ON c.id = lpj.entite_id AND lpj.type_liaison = 'contract'
                LEFT JOIN pieces_jointes pj ON lpj.piece_jointe_id = pj.id
                WHERE {$locationWhere}";

        $params = [];

        // Appliquer les filtres supplémentaires
        if (!empty($filters['client_id'])) {
            $sql .= " AND c.client_id = ?";
            $params[] = $filters['client_id'];
        }
        if (!empty($filters['site_id'])) {
            $sql .= " AND s.id = ? AND s.client_id = c.client_id";
            $params[] = $filters['site_id'];
        }
        if (!empty($filters['building_id'])) {
            $sql .= " AND b.id = ? AND b.site_id = s.id AND s.client_id = c.client_id";
            $params[] = $filters['building_id'];
        }
        if (!empty($filters['room_id'])) {
            $sql .= " AND r.id = ? AND r.building_id = b.id AND b.site_id = s.id AND s.client_id = c.client_id";
            $params[] = $filters['room_id'];
        }
        if (!empty($filters['contract_type_id'])) {
            $sql .= " AND c.contract_type_id = ?";
            $params[] = $filters['contract_type_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND c.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (c.name LIKE ? OR cl.name LIKE ? OR ct.name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        }

        $sql .= " GROUP BY c.id ORDER BY c.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un contrat par son ID avec vérification d'accès
     * @param int $id ID du contrat
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array|null Le contrat ou null si pas d'accès
     */
    public function getByIdWithAccess($id, $userLocations)
    {
        $locationWhere = buildLocationWhereClause($userLocations, 'c.client_id', 's.id', 'b.id', 'r.id');

        $sql = "SELECT DISTINCT c.*, 
                cl.name as client_name,
                ct.name as contract_type_name
                FROM " . $this->table . " c
                LEFT JOIN clients cl ON c.client_id = cl.id
                LEFT JOIN contract_types ct ON c.contract_type_id = ct.id
                LEFT JOIN contract_rooms cr ON c.id = cr.contract_id
                LEFT JOIN rooms r ON cr.room_id = r.id
                LEFT JOIN buildings b ON r.building_id = b.id
                LEFT JOIN sites s ON b.site_id = s.id
                WHERE c.id = ? AND {$locationWhere}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $contract = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($contract) {
            // Récupérer les salles associées
            $contract['rooms'] = $this->getContractRoomsWithAccess($id, $userLocations);
        }

        return $contract;
    }

    /**
     * Récupère les salles d'un contrat avec vérification d'accès
     * @param int $contractId ID du contrat
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des salles
     */
    public function getContractRoomsWithAccess($contractId, $userLocations)
    {
        $locationWhere = buildLocationWhereClause($userLocations, 's.client_id', 's.id', 'b.id', 'r.id');

        $sql = "SELECT r.*, b.name as building_name, s.name as site_name, s.client_id
                FROM contract_rooms cr
                JOIN rooms r ON cr.room_id = r.id
                JOIN buildings b ON r.building_id = b.id
                JOIN sites s ON b.site_id = s.id
                WHERE cr.contract_id = ? AND {$locationWhere}
                ORDER BY s.name, b.name, r.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$contractId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les interventions d'un contrat selon les localisations autorisées
     * @param int $contractId ID du contrat
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des interventions avec techniciens et durées
     */
    public function getInterventionsByContractAndLocations($contractId, $userLocations)
    {
        // Construire la clause WHERE pour les localisations
        $locationWhere = buildLocationWhereClause($userLocations, 'c.id', 's.id', 'b.id', 'r.id');

        $sql = "
        SELECT 
            i.*,
            ist.name as status_name,
            ist.color as status_color,
            it.name as type_name,
            ip.name as priority_name,
            ip.color as priority_color,
            -- Calcul de la durée totale de l'intervention (somme des temps passés par technicien)
            COALESCE(SUM(it_tech.temps_passe), 0) as total_duration_minutes,
            -- Informations du client
            c.name as client_name,
            -- Informations du site
            s.name as site_name,
            -- Informations du bâtiment
            b.name as building_name,
            -- Informations de la salle
            r.name as room_name,
            -- Récupérer les techniciens sous forme groupée
            GROUP_CONCAT(DISTINCT CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') as technicians_names,
            GROUP_CONCAT(DISTINCT it_tech.temps_passe SEPARATOR ', ') as technicians_durations,
            -- Tickets utilisés
            i.tickets_used
        FROM interventions i
        INNER JOIN clients c ON i.client_id = c.id
        LEFT JOIN sites s ON i.site_id = s.id
        LEFT JOIN buildings b ON i.building_id = b.id
        LEFT JOIN rooms r ON i.room_id = r.id
        INNER JOIN intervention_statuses ist ON i.status_id = ist.id
        INNER JOIN intervention_types it ON i.type_id = it.id
        INNER JOIN intervention_priorities ip ON i.priority_id = ip.id
        LEFT JOIN intervention_techniciens it_tech ON i.id = it_tech.intervention_id
        LEFT JOIN users u ON it_tech.technicien_id = u.id
        WHERE i.contract_id = ? 
        AND {$locationWhere}
        GROUP BY i.id
        ORDER BY i.created_at DESC
    ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$contractId]);
        $interventions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Traitement post-récupération pour formater les durées
        foreach ($interventions as &$intervention) {
            // Calculer la durée en heures
            $totalMinutes = (int) ($intervention['total_duration_minutes'] ?? 0);
            $hours = floor($totalMinutes / 60);
            $minutes = $totalMinutes % 60;
            $intervention['duration_display'] = $hours . 'h' . ($minutes > 0 ? $minutes : '');
            $intervention['duration_hours'] = round($totalMinutes / 60, 2);

            // S'assurer que technicians_names est défini
            if (empty($intervention['technicians_names'])) {
                $intervention['technicians_names'] = 'Non assigné';
            }

            // S'assurer que tickets_used est défini
            if (!isset($intervention['tickets_used']) || $intervention['tickets_used'] === null) {
                $intervention['tickets_used'] = 0;
            }
        }

        return $interventions;
    }
    /**
     * Récupère les clients selon les localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des clients
     */
    public function getClientsByLocations($userLocations)
    {
        $locationWhere = buildLocationWhereClause($userLocations, 'cl.id', 's.id', 'b.id', 'r.id');

        $sql = "SELECT DISTINCT cl.* 
                FROM clients cl
                LEFT JOIN sites s ON cl.id = s.client_id AND s.status = 1
                LEFT JOIN buildings b ON s.id = b.site_id AND b.status = 1
                LEFT JOIN rooms r ON b.id = r.building_id AND r.status = 1
                WHERE ({$locationWhere}) AND cl.status = 1
                ORDER BY cl.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les sites d'un client selon les localisations autorisées
     * @param int $clientId ID du client
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des sites
     */
    public function getSitesByClientAndLocations($clientId, $userLocations)
    {
        $locationWhere = buildLocationWhereClause($userLocations, 's.client_id', 's.id', 'b.id', 'r.id');

        $sql = "SELECT DISTINCT s.* 
                FROM sites s
                LEFT JOIN buildings b ON s.id = b.site_id AND b.status = 1
                LEFT JOIN rooms r ON b.id = r.building_id AND r.status = 1
                WHERE s.client_id = ? AND {$locationWhere} AND s.status = 1
                ORDER BY s.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les bâtiments d'un site selon les localisations autorisées
     * @param int $siteId ID du site
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des bâtiments
     */
    public function getBuildingsBySiteAndLocations($siteId, $userLocations)
    {
        $locationWhere = buildLocationWhereClause($userLocations, 's.client_id', 's.id', 'b.id', 'r.id');

        $sql = "SELECT DISTINCT b.* 
                FROM buildings b
                JOIN sites s ON b.site_id = s.id
                LEFT JOIN rooms r ON b.id = r.building_id AND r.status = 1
                WHERE b.site_id = ? AND {$locationWhere} AND b.status = 1
                ORDER BY b.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$siteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les salles d'un bâtiment selon les localisations autorisées
     * @param int $buildingId ID du bâtiment
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des salles
     */
    public function getRoomsByBuildingAndLocations($buildingId, $userLocations)
    {
        $locationWhere = buildLocationWhereClause($userLocations, 's.client_id', 's.id', 'b.id', 'r.id');

        $sql = "SELECT r.*, b.name as building_name, s.name as site_name
                FROM rooms r
                JOIN buildings b ON r.building_id = b.id
                JOIN sites s ON b.site_id = s.id
                WHERE r.building_id = ? AND {$locationWhere} AND r.status = 1
                ORDER BY r.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$buildingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les salles d'un site (via les bâtiments) selon les localisations autorisées
     * @param int $siteId ID du site
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des salles
     */
    public function getRoomsBySiteAndLocations($siteId, $userLocations)
    {
        $locationWhere = buildLocationWhereClause($userLocations, 's.client_id', 's.id', 'b.id', 'r.id');

        $sql = "SELECT r.*, b.name as building_name, s.name as site_name
                FROM rooms r
                JOIN buildings b ON r.building_id = b.id
                JOIN sites s ON b.site_id = s.id
                WHERE s.id = ? AND {$locationWhere} AND r.status = 1
                ORDER BY b.name, r.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$siteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les types de contrats
     * @return array Liste des types de contrats
     */
    public function getContractTypes()
    {
        $sql = "SELECT id, name, description, default_tickets, nb_inter_prev, ordre_affichage, created_at, updated_at FROM contract_types ORDER BY ordre_affichage, name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques selon les localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Statistiques des contrats
     */
    public function getStatsByLocations($userLocations)
    {
        $locationWhere = buildLocationWhereClause($userLocations, 'c.client_id', 's.id', 'b.id', 'r.id');

        $sql = "SELECT 
                COUNT(DISTINCT c.id) as total,
                SUM(CASE WHEN c.status = 'actif' THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN c.status = 'inactif' THEN 1 ELSE 0 END) as inactive_count,
                SUM(c.tickets_number) as total_tickets,
                SUM(c.tickets_remaining) as remaining_tickets
                FROM " . $this->table . " c
                LEFT JOIN contract_rooms cr ON c.id = cr.contract_id
                LEFT JOIN rooms r ON cr.room_id = r.id
                LEFT JOIN buildings b ON r.building_id = b.id
                LEFT JOIN sites s ON b.site_id = s.id
                WHERE {$locationWhere} AND c.contract_type_id IS NOT NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les pièces jointes d'un contrat avec vérification d'accès
     * @param int $contractId ID du contrat
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des pièces jointes
     */
    public function getPiecesJointesWithAccess($contractId, $userLocations)
    {
        try {
            // Vérifier d'abord que l'utilisateur a accès à ce contrat
            $locationWhere = buildLocationWhereClause($userLocations, 'c.client_id', 's.id', 'b.id', 'r.id');

            $sql = "SELECT c.id FROM " . $this->table . " c
                    LEFT JOIN contract_rooms cr ON c.id = cr.contract_id
                    LEFT JOIN rooms r ON cr.room_id = r.id
                    LEFT JOIN buildings b ON r.building_id = b.id
                    LEFT JOIN sites s ON b.site_id = s.id
                    WHERE c.id = ? AND ({$locationWhere} OR c.client_id IN (SELECT client_id FROM user_locations WHERE user_id = ?))";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$contractId, $_SESSION['user']['id'] ?? 0]);
            $hasAccess = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$hasAccess) {
                return [];
            }

            // Si l'utilisateur a accès, récupérer les pièces jointes
            $sql = "SELECT pj.*, st.setting_value as type_nom, CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                    FROM pieces_jointes pj
                    LEFT JOIN settings st ON pj.type_id = st.id
                    LEFT JOIN users u ON pj.created_by = u.id
                    INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                    WHERE lpj.type_liaison = 'contract' 
                    AND lpj.entite_id = ? 
                    AND pj.masque_client = 0
                    ORDER BY pj.date_creation DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$contractId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            custom_log("Table pieces_jointes non trouvée, retour d'un tableau vide: " . $e->getMessage(), 'DEBUG');
            return [];
        }
    }
}