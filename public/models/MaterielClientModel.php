<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';

/**
 * Modèle pour la gestion du matériel clients
 * Filtre automatiquement selon les localisations autorisées
 */
class MaterielClientModel extends BaseModel
{
    public function __construct($db)
    {
        parent::__construct($db);
        $this->table = 'materiel';
    }

    /**
     * Récupère tous les matériels selon les localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @param array $filters Filtres supplémentaires
     * @return array Liste des matériels
     */
    public function getAllByLocations($userLocations, $filters = [])
    {
        // NETTOYER les userLocations pour enlever les valeurs 0 et null
        $cleanUserLocations = [];
        foreach ($userLocations as $location) {
            $cleanLocation = [];
            $cleanLocation['client_id'] = isset($location['client_id']) && $location['client_id'] > 0 ? (int) $location['client_id'] : null;
            $cleanLocation['site_id'] = isset($location['site_id']) && $location['site_id'] > 0 ? (int) $location['site_id'] : null;
            $cleanLocation['building_id'] = isset($location['building_id']) && $location['building_id'] > 0 ? (int) $location['building_id'] : null;
            $cleanLocation['room_id'] = isset($location['room_id']) && $location['room_id'] > 0 ? (int) $location['room_id'] : null;

            if ($cleanLocation['client_id'] !== null) {
                $cleanUserLocations[] = $cleanLocation;
            }
        }

        // Si aucune localisation valide, essayer d'utiliser le client_id de la session
        if (empty($cleanUserLocations)) {
            $clientId = $_SESSION['user']['client_id'] ?? null;
            if ($clientId) {
                $cleanUserLocations = [['client_id' => (int) $clientId, 'site_id' => null, 'building_id' => null, 'room_id' => null]];
            } else {
                return [];
            }
        }

        // Construire la clause WHERE
        $conditions = [];
        foreach ($cleanUserLocations as $location) {
            $clientId = $location['client_id'];

            if ($location['room_id'] !== null) {
                $conditions[] = "(r.id = {$location['room_id']})";
            } elseif ($location['building_id'] !== null) {
                $conditions[] = "(b.id = {$location['building_id']})";
            } elseif ($location['site_id'] !== null) {
                $conditions[] = "(s.id = {$location['site_id']})";
            } else {
                $conditions[] = "(c.id = {$clientId})";
            }
        }

        $locationWhere = empty($conditions) ? "1=0" : "(" . implode(" OR ", $conditions) . ")";

        custom_log("getAllByLocations - Cleaned locationWhere: " . $locationWhere, 'DEBUG');

        $sql = "SELECT 
        m.*,
        r.name as salle_nom,
        b.name as building_nom,
        s.name as site_nom,
        c.name as client_nom,
        m.type_materiel as type_nom
        FROM " . $this->table . " m
        LEFT JOIN rooms r ON m.salle_id = r.id
        LEFT JOIN buildings b ON r.building_id = b.id
        LEFT JOIN sites s ON b.site_id = s.id
        LEFT JOIN clients c ON s.client_id = c.id
        WHERE {$locationWhere}";

        $params = [];

        // Appliquer les filtres supplémentaires
        if (!empty($filters['client_id'])) {
            $sql .= " AND c.id = ?";
            $params[] = $filters['client_id'];
        }
        if (!empty($filters['site_id'])) {
            $sql .= " AND s.id = ?";
            $params[] = $filters['site_id'];
        }
        if (!empty($filters['building_id'])) {
            $sql .= " AND b.id = ?";
            $params[] = $filters['building_id'];
        }
        if (!empty($filters['salle_id'])) {
            $sql .= " AND m.salle_id = ?";
            $params[] = $filters['salle_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (m.marque LIKE ? OR m.modele LIKE ? OR m.numero_serie LIKE ? OR c.name LIKE ? OR s.name LIKE ? OR r.name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        $sql .= " ORDER BY c.name, s.name, b.name, r.name, m.marque, m.modele";

        custom_log("getAllByLocations - Final SQL: " . $sql, 'DEBUG');
        custom_log("getAllByLocations - Params: " . json_encode($params), 'DEBUG');

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        custom_log("getAllByLocations - Results count: " . count($results), 'DEBUG');

        return $results;
    }
    /**
     * Récupère un matériel par son ID avec vérification d'accès
     * @param int $id ID du matériel
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array|null Le matériel ou null si pas d'accès
     */
    public function getByIdWithAccess($id, $userLocations)
    {
        $locationWhere = buildLocationWhereClause($userLocations, 'c.id', 's.id', 'r.id');

        $sql = "SELECT 
            m.*,
            r.name as salle_nom,
            b.name as building_nom,
            s.name as site_nom,
            s.id as site_id,
            c.name as client_nom,
            c.id as client_id,
            m.type_materiel as type_nom
            FROM " . $this->table . " m
            LEFT JOIN rooms r ON m.salle_id = r.id
            LEFT JOIN buildings b ON r.building_id = b.id
            LEFT JOIN sites s ON b.site_id = s.id
            LEFT JOIN clients c ON s.client_id = c.id
            WHERE m.id = ? AND {$locationWhere}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Debug: loguer le résultat
        custom_log("getByIdWithAccess result for id $id: " . json_encode($result), 'DEBUG');

        return $result;
    }
    /**
     * Récupère les clients selon les localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des clients
     */
    public function getClientsByLocations($userLocations)
    {
        $locationWhere = buildLocationWhereClause($userLocations, 'c.id', 's.id', 'r.id');

        $sql = "SELECT DISTINCT c.* 
                FROM clients c
                LEFT JOIN sites s ON c.id = s.client_id
                LEFT JOIN buildings b ON s.id = b.site_id
                LEFT JOIN rooms r ON b.id = r.building_id
                WHERE {$locationWhere} AND c.status = 1
                ORDER BY c.name";

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
        $locationWhere = buildLocationWhereClause($userLocations, 's.client_id', 's.id', 'r.id');

        $sql = "SELECT DISTINCT s.* 
                FROM sites s
                LEFT JOIN buildings b ON s.id = b.site_id
                LEFT JOIN rooms r ON b.id = r.building_id
                WHERE s.client_id = ? AND {$locationWhere} AND s.status = 1
                ORDER BY s.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les salles d'un site selon les localisations autorisées
     * @param int $siteId ID du site
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des salles
     */
    public function getRoomsBySiteAndLocations($siteId, $userLocations)
    {
        $locationWhere = buildLocationWhereClause($userLocations, 's.client_id', 's.id', 'r.id');

        $sql = "SELECT r.* 
                FROM rooms r
                JOIN buildings b ON r.building_id = b.id
                JOIN sites s ON b.site_id = s.id
                WHERE s.id = ? AND {$locationWhere} AND r.status = 1
                ORDER BY r.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$siteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les salles d'un client selon les localisations autorisées
     * @param int $clientId ID du client
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des salles
     */
    public function getRoomsByClientAndLocations($clientId, $userLocations)
    {
        $locationWhere = buildLocationWhereClause($userLocations, 's.client_id', 's.id', 'r.id');

        $sql = "SELECT r.* 
                FROM rooms r
                JOIN buildings b ON r.building_id = b.id
                JOIN sites s ON b.site_id = s.id
                WHERE s.client_id = ? AND {$locationWhere} AND r.status = 1
                ORDER BY s.name, r.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les pièces jointes d'un matériel avec vérification d'accès
     * @param int $materielId ID du matériel
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des pièces jointes
     */
    public function getPiecesJointesWithAccess($materielId, $userLocations)
    {
        try {
            $locationWhere = buildLocationWhereClause($userLocations, 'c.id', 's.id', 'r.id');

            $sql = "SELECT pj.*, st.setting_value as type_nom, CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                    FROM pieces_jointes pj
                    LEFT JOIN settings st ON pj.type_id = st.id
                    LEFT JOIN users u ON pj.created_by = u.id
                    INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                    INNER JOIN " . $this->table . " m ON lpj.entite_id = m.id
                    INNER JOIN rooms r ON m.salle_id = r.id
                    INNER JOIN buildings b ON r.building_id = b.id
                    INNER JOIN sites s ON b.site_id = s.id
                    INNER JOIN clients c ON s.client_id = c.id
                    WHERE lpj.type_liaison = 'materiel' 
                    AND lpj.entite_id = ? 
                    AND {$locationWhere}
                    AND pj.masque_client = 0
                    ORDER BY pj.date_creation DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$materielId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            custom_log("Table pieces_jointes non trouvée, retour d'un tableau vide: " . $e->getMessage(), 'DEBUG');
            return [];
        }
    }

    /**
     * Récupère les informations de visibilité des champs pour plusieurs matériels
     * @param array $materielIds IDs des matériels
     * @return array Informations de visibilité
     */
    public function getVisibiliteChampsForMateriels($materielIds)
    {
        if (empty($materielIds)) {
            return [];
        }

        try {
            $placeholders = str_repeat('?,', count($materielIds) - 1) . '?';
            $sql = "SELECT materiel_id, nom_champ FROM visibilite_champs_materiel WHERE materiel_id IN ($placeholders)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($materielIds);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $visibilites = [];
            foreach ($results as $row) {
                $materielId = $row['materiel_id'] ?? null;
                $champ = $row['nom_champ'] ?? null;

                if ($materielId && $champ !== null) {
                    if (!isset($visibilites[$materielId])) {
                        $visibilites[$materielId] = [];
                    }
                    $visibilites[$materielId][$champ] = true;
                }
            }

            return $visibilites;
        } catch (Exception $e) {
            custom_log("Table visibilite_champs_materiel non trouvée, retour d'un tableau vide: " . $e->getMessage(), 'DEBUG');
            return [];
        }
    }

    /**
     * Récupère le nombre de pièces jointes visibles pour un matériel
     * @param int $materielId ID du matériel
     * @return int Nombre de pièces jointes
     */
    public function getPiecesJointesCount($materielId)
    {
        try {
            $sql = "SELECT COUNT(*) as count
                    FROM pieces_jointes pj
                    INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                    WHERE lpj.type_liaison = 'materiel' 
                    AND lpj.entite_id = ?
                    AND pj.masque_client = 0";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$materielId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            custom_log("Table pieces_jointes non trouvée, retour de 0: " . $e->getMessage(), 'DEBUG');
            return 0;
        }
    }

    /**
     * OPTIMISATION N+1 : Récupère le nombre de pièces jointes pour plusieurs matériels en une seule requête
     * @param array $materielIds Liste des IDs de matériels
     * @return array Tableau associatif [materiel_id => count]
     */
    public function getPiecesJointesCountForMultiple($materielIds)
    {
        if (empty($materielIds)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($materielIds), '?'));
            $sql = "SELECT 
                        lpj.entite_id as materiel_id,
                        COUNT(*) as count
                    FROM pieces_jointes pj
                    INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                    WHERE lpj.type_liaison = 'materiel' 
                    AND lpj.entite_id IN ($placeholders)
                    AND pj.masque_client = 0
                    GROUP BY lpj.entite_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($materielIds);

            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[$row['materiel_id']] = (int) $row['count'];
            }

            return $results;
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des pièces jointes (batch): " . $e->getMessage(), 'DEBUG');
            return [];
        }
    }

    /**
     * Récupère les types de matériel
     * @return array Liste des types
     */
    public function getTypesMateriel()
    {
        $sql = "SELECT id, setting_key, setting_value, setting_description, setting_group, created_at, updated_at FROM settings WHERE setting_key = 'materiel_type' ORDER BY setting_value";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques selon les localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Statistiques du matériel
     */
    public function getStatsByLocations($userLocations)
    {
        $locationWhere = buildLocationWhereClause($userLocations, 'c.id', 's.id', 'r.id');

        $sql = "SELECT 
                COUNT(DISTINCT m.id) as total,
                COUNT(DISTINCT c.id) as clients_count,
                COUNT(DISTINCT s.id) as sites_count,
                COUNT(DISTINCT r.id) as rooms_count
                FROM " . $this->table . " m
                LEFT JOIN rooms r ON m.salle_id = r.id
                LEFT JOIN buildings b ON r.building_id = b.id
                LEFT JOIN sites s ON b.site_id = s.id
                LEFT JOIN clients c ON s.client_id = c.id
                WHERE {$locationWhere}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les sites selon les localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des sites
     */
    public function getSitesByLocations($userLocations)
    {
        custom_log("getSitesByLocations - userLocations: " . json_encode($userLocations), 'DEBUG');

        if (empty($userLocations)) {
            custom_log("getSitesByLocations - aucune localisation trouvée", 'DEBUG');
            return [];
        }

        // Extraire les client_id et site_id uniques des localisations
        $siteConditions = [];
        foreach ($userLocations as $location) {
            $clientId = $location['client_id'];
            $siteId = $location['site_id'];

            if ($siteId !== null && $siteId != 0) {
                // Accès spécifique à un site
                $siteConditions[] = "(s.client_id = {$clientId} AND s.id = {$siteId})";
            } else {
                // Accès au client entier
                $siteConditions[] = "(s.client_id = {$clientId})";
            }
        }

        $locationWhere = empty($siteConditions) ? "1=0" : "(" . implode(" OR ", $siteConditions) . ")";
        custom_log("getSitesByLocations - locationWhere: " . $locationWhere, 'DEBUG');

        $sql = "SELECT DISTINCT s.* 
                FROM sites s
                WHERE {$locationWhere} AND s.status = 1
                ORDER BY s.name";

        custom_log("getSitesByLocations - SQL: " . $sql, 'DEBUG');

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        custom_log("getSitesByLocations - result count: " . count($result), 'DEBUG');
        return $result;
    }

    /**
     * Récupère tous les sites (méthode temporaire pour les clients)
     * @return array Liste de tous les sites
     */
    public function getAllSites()
    {
        $sql = "SELECT s.* 
                FROM sites s
                WHERE s.status = 1
                ORDER BY s.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les salles selon les localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des salles
     */
    public function getRoomsByLocations($userLocations)
    {
        if (empty($userLocations)) {
            return [];
        }

        // Extraire les conditions pour les salles
        $roomConditions = [];
        foreach ($userLocations as $location) {
            $clientId = $location['client_id'];
            $siteId = $location['site_id'];
            $roomId = $location['room_id'];

            if ($roomId !== null && $roomId != 0) {
                // Accès spécifique à une salle
                $roomConditions[] = "(s.client_id = {$clientId} AND s.id = {$siteId} AND r.id = {$roomId})";
            } elseif ($siteId !== null && $siteId != 0) {
                // Accès à un site entier
                $roomConditions[] = "(s.client_id = {$clientId} AND s.id = {$siteId})";
            } else {
                // Accès au client entier
                $roomConditions[] = "(s.client_id = {$clientId})";
            }
        }

        $locationWhere = empty($roomConditions) ? "1=0" : "(" . implode(" OR ", $roomConditions) . ")";

        $sql = "SELECT r.* 
                FROM rooms r
                JOIN buildings b ON r.building_id = b.id
                JOIN sites s ON b.site_id = s.id
                WHERE {$locationWhere} AND r.status = 1
                ORDER BY s.name, r.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère une salle par son ID avec vérification d'accès
     * 
     * @param int $roomId ID de la salle
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array|null Les données de la salle ou null si pas d'accès
     */
    public function getRoomByIdWithAccess($roomId, $userLocations)
    {
        if (empty($userLocations)) {
            return null;
        }

        // Extraire les conditions pour les salles
        $roomConditions = [];
        foreach ($userLocations as $location) {
            $clientId = $location['client_id'];
            $siteId = $location['site_id'];
            $roomIdParam = $location['room_id'];

            if ($roomIdParam !== null && $roomIdParam != 0) {
                // Accès spécifique à une salle
                $roomConditions[] = "(s.client_id = {$clientId} AND s.id = {$siteId} AND r.id = {$roomIdParam})";
            } elseif ($siteId !== null && $siteId != 0) {
                // Accès à un site entier
                $roomConditions[] = "(s.client_id = {$clientId} AND s.id = {$siteId})";
            } else {
                // Accès au client entier
                $roomConditions[] = "(s.client_id = {$clientId})";
            }
        }

        $locationWhere = empty($roomConditions) ? "1=0" : "(" . implode(" OR ", $roomConditions) . ")";

        $query = "
            SELECT 
                r.id,
                r.name as salle_name,
                b.name as building_name,
                s.name as site_name,
                s.id as site_id,
                c.name as client_name,
                c.id as client_id
            FROM rooms r
            INNER JOIN buildings b ON r.building_id = b.id
            INNER JOIN sites s ON b.site_id = s.id
            INNER JOIN clients c ON s.client_id = c.id
            WHERE r.id = :room_id AND {$locationWhere}
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':room_id', $roomId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les bâtiments selon les localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des bâtiments
     */
    public function getBuildingsByLocations($userLocations)
    {
        custom_log("getBuildingsByLocations - userLocations: " . json_encode($userLocations), 'DEBUG');

        if (empty($userLocations)) {
            custom_log("getBuildingsByLocations - aucune localisation trouvée", 'DEBUG');
            return [];
        }

        // Extraire les conditions pour les bâtiments
        $buildingConditions = [];
        foreach ($userLocations as $location) {
            $clientId = $location['client_id'];
            $siteId = $location['site_id'];
            $buildingId = $location['building_id'];

            if ($buildingId !== null && $buildingId != 0) {
                // Accès spécifique à un bâtiment
                $buildingConditions[] = "(s.client_id = {$clientId} AND s.id = {$siteId} AND b.id = {$buildingId})";
            } elseif ($siteId !== null && $siteId != 0) {
                // Accès à un site entier
                $buildingConditions[] = "(s.client_id = {$clientId} AND s.id = {$siteId})";
            } else {
                // Accès au client entier
                $buildingConditions[] = "(s.client_id = {$clientId})";
            }
        }

        $locationWhere = empty($buildingConditions) ? "1=0" : "(" . implode(" OR ", $buildingConditions) . ")";

        $sql = "SELECT DISTINCT b.* 
            FROM buildings b
            JOIN sites s ON b.site_id = s.id
            WHERE {$locationWhere} AND b.status = 1
            ORDER BY s.name, b.name";

        custom_log("getBuildingsByLocations - SQL: " . $sql, 'DEBUG');

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        custom_log("getBuildingsByLocations - result count: " . count($result), 'DEBUG');
        return $result;
    }

    /**
     * Récupère les bâtiments d'un site selon les localisations autorisées
     * @param int $siteId ID du site
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des bâtiments
     */
    public function getBuildingsBySiteAndLocations($siteId, $userLocations)
    {
        custom_log("getBuildingsBySiteAndLocations - siteId: {$siteId}, userLocations: " . json_encode($userLocations), 'DEBUG');

        if (empty($userLocations)) {
            return [];
        }

        // Extraire les conditions pour les bâtiments
        $buildingConditions = [];
        foreach ($userLocations as $location) {
            $clientId = $location['client_id'];
            $locationSiteId = $location['site_id'];
            $buildingId = $location['building_id'];

            if ($buildingId !== null && $buildingId != 0) {
                // Accès spécifique à un bâtiment
                $buildingConditions[] = "(s.client_id = {$clientId} AND s.id = {$locationSiteId} AND b.id = {$buildingId})";
            } elseif ($locationSiteId !== null && $locationSiteId != 0) {
                // Accès à un site entier
                if ($locationSiteId == $siteId) {
                    $buildingConditions[] = "(s.client_id = {$clientId} AND s.id = {$siteId})";
                }
            } else {
                // Accès au client entier
                $buildingConditions[] = "(s.client_id = {$clientId})";
            }
        }

        $locationWhere = empty($buildingConditions) ? "1=0" : "(" . implode(" OR ", $buildingConditions) . ")";

        $sql = "SELECT DISTINCT b.* 
            FROM buildings b
            JOIN sites s ON b.site_id = s.id
            WHERE s.id = ? AND {$locationWhere} AND b.status = 1
            ORDER BY b.name";

        custom_log("getBuildingsBySiteAndLocations - SQL: " . $sql, 'DEBUG');

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$siteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un bâtiment par son ID avec vérification d'accès
     * @param int $buildingId ID du bâtiment
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array|null Les données du bâtiment ou null si pas d'accès
     */
    public function getBuildingByIdWithAccess($buildingId, $userLocations)
    {
        custom_log("getBuildingByIdWithAccess - buildingId: {$buildingId}, userLocations: " . json_encode($userLocations), 'DEBUG');

        if (empty($userLocations)) {
            return null;
        }

        // Extraire les conditions pour les bâtiments
        $buildingConditions = [];
        foreach ($userLocations as $location) {
            $clientId = $location['client_id'];
            $siteId = $location['site_id'];
            $locationBuildingId = $location['building_id'];

            if ($locationBuildingId !== null && $locationBuildingId != 0) {
                // Accès spécifique à un bâtiment
                if ($locationBuildingId == $buildingId) {
                    $buildingConditions[] = "(s.client_id = {$clientId} AND s.id = {$siteId} AND b.id = {$buildingId})";
                }
            } elseif ($siteId !== null && $siteId != 0) {
                // Accès à un site entier
                $buildingConditions[] = "(s.client_id = {$clientId} AND s.id = {$siteId})";
            } else {
                // Accès au client entier
                $buildingConditions[] = "(s.client_id = {$clientId})";
            }
        }

        $locationWhere = empty($buildingConditions) ? "1=0" : "(" . implode(" OR ", $buildingConditions) . ")";

        $sql = "SELECT b.*, s.name as site_name, s.id as site_id, c.name as client_name, c.id as client_id
            FROM buildings b
            JOIN sites s ON b.site_id = s.id
            JOIN clients c ON s.client_id = c.id
            WHERE b.id = ? AND {$locationWhere}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$buildingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    /**
     * Récupère les salles d'un bâtiment selon les localisations autorisées
     * @param int $buildingId ID du bâtiment
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des salles
     */
    public function getRoomsByBuildingAndLocations($buildingId, $userLocations)
    {
        custom_log("getRoomsByBuildingAndLocations - buildingId: {$buildingId}, userLocations: " . json_encode($userLocations), 'DEBUG');

        if (empty($userLocations)) {
            return [];
        }

        // Extraire les conditions pour les salles
        $roomConditions = [];
        foreach ($userLocations as $location) {
            $clientId = $location['client_id'];
            $siteId = $location['site_id'];
            $roomId = $location['room_id'];

            if ($roomId !== null && $roomId != 0) {
                // Accès spécifique à une salle
                $roomConditions[] = "(s.client_id = {$clientId} AND s.id = {$siteId} AND r.id = {$roomId})";
            } elseif ($siteId !== null && $siteId != 0) {
                // Accès à un site entier
                $roomConditions[] = "(s.client_id = {$clientId} AND s.id = {$siteId})";
            } else {
                // Accès au client entier
                $roomConditions[] = "(s.client_id = {$clientId})";
            }
        }

        $locationWhere = empty($roomConditions) ? "1=0" : "(" . implode(" OR ", $roomConditions) . ")";

        $sql = "SELECT r.* 
            FROM rooms r
            JOIN buildings b ON r.building_id = b.id
            JOIN sites s ON b.site_id = s.id
            WHERE r.building_id = ? AND {$locationWhere} AND r.status = 1
            ORDER BY r.name";

        custom_log("getRoomsByBuildingAndLocations - SQL: " . $sql, 'DEBUG');

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$buildingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}