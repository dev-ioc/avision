<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';

/**
 * Modèle pour la gestion des interventions clients
 * Filtre automatiquement selon les localisations autorisées
 */
class InterventionsClientModel extends BaseModel
{
    public function __construct($db)
    {
        parent::__construct($db);
        $this->table = 'interventions';
    }

    /**
     * Récupère toutes les interventions d'un client selon ses localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @param array $filters Filtres supplémentaires
     * @return array Liste des interventions
     */
    public function getAllByLocations($userLocations, $filters = [])
    {
        // Extraire les IDs des clients auxquels l'utilisateur a accès
        $clientIds = [];

        foreach ($userLocations as $location) {
            if (isset($location['client_id']) && !in_array($location['client_id'], $clientIds)) {
                $clientIds[] = (int) $location['client_id'];
            }
        }

        if (empty($clientIds)) {
            return [];
        }

        // Requête avec jointures pour les bâtiments
        $placeholders = str_repeat('?,', count($clientIds) - 1) . '?';

        $sql = "SELECT i.*, 
            c.name as client_name,
            s.name as site_name,
            b.name as building_name,
            r.name as room_name,
            its.name as status_name,
            its.color as status_color,
            it.name as type_name,
            it.requires_travel as type_requires_travel,
            ip.name as priority_name,
            ip.color as priority_color,
            GROUP_CONCAT(DISTINCT CONCAT(ut.first_name, ' ', ut.last_name) ORDER BY ut.first_name SEPARATOR ', ') as technicians_names,
            GROUP_CONCAT(DISTINCT ut.id ORDER BY ut.first_name SEPARATOR ',') as technicien_ids
            FROM " . $this->table . " i
            LEFT JOIN clients c ON i.client_id = c.id
            LEFT JOIN sites s ON i.site_id = s.id
            LEFT JOIN buildings b ON i.building_id = b.id
            LEFT JOIN rooms r ON i.room_id = r.id
            LEFT JOIN intervention_techniciens itech ON i.id = itech.intervention_id
            LEFT JOIN users ut ON itech.technicien_id = ut.id
            LEFT JOIN intervention_statuses its ON i.status_id = its.id
            LEFT JOIN intervention_types it ON i.type_id = it.id
            LEFT JOIN intervention_priorities ip ON i.priority_id = ip.id
            WHERE i.client_id IN ({$placeholders})";

        $params = $clientIds;

        // Appliquer les filtres supplémentaires
        if (!empty($filters['site_id'])) {
            $sql .= " AND i.site_id = ?";
            $params[] = $filters['site_id'];
        }
        if (!empty($filters['building_id'])) {
            $sql .= " AND i.building_id = ?";
            $params[] = $filters['building_id'];
        }
        if (!empty($filters['room_id'])) {
            $sql .= " AND i.room_id = ?";
            $params[] = $filters['room_id'];
        }
        if (!empty($filters['status_id'])) {
            $sql .= " AND i.status_id = ?";
            $params[] = $filters['status_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (i.title LIKE ? OR s.name LIKE ? OR b.name LIKE ? OR r.name LIKE ? OR i.reference LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        if (!empty($filters['exclude_status_ids'])) {
            $excludePlaceholders = str_repeat('?,', count($filters['exclude_status_ids']) - 1) . '?';
            $sql .= " AND i.status_id NOT IN ($excludePlaceholders)";
            $params = array_merge($params, $filters['exclude_status_ids']);
        }

        // Tri par défaut : date de création décroissante
        $sql .= " GROUP BY i.id ORDER BY i.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère une intervention par son ID avec vérification d'accès
     * @param int $id ID de l'intervention
     *param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array|null L'intervention ou null si pas d'accès
     */
    public function getByIdWithAccess($id, $userLocations)
    {
        // Extraire les IDs des clients auxquels l'utilisateur a accès
        $clientIds = [];

        foreach ($userLocations as $location) {
            if (isset($location['client_id']) && !in_array($location['client_id'], $clientIds)) {
                $clientIds[] = (int) $location['client_id'];
            }
        }

        if (empty($clientIds)) {
            return null;
        }

        // Requête avec jointures pour les bâtiments
        $placeholders = str_repeat('?,', count($clientIds) - 1) . '?';

        $sql = "SELECT i.*, 
            c.name as client_name,
            s.name as site_name,
            b.name as building_name,
            r.name as room_name,
            its.name as status_name,
            its.color as status_color,
            it.name as type_name,
            it.requires_travel as type_requires_travel,
            ip.name as priority_name,
            ip.color as priority_color,
            co.name as contract_name,
            GROUP_CONCAT(DISTINCT CONCAT(ut.first_name, ' ', ut.last_name) ORDER BY ut.first_name SEPARATOR ', ') as technicians_names
            FROM " . $this->table . " i
            LEFT JOIN clients c ON i.client_id = c.id
            LEFT JOIN sites s ON i.site_id = s.id
            LEFT JOIN buildings b ON i.building_id = b.id
            LEFT JOIN rooms r ON i.room_id = r.id
            LEFT JOIN intervention_techniciens itech ON i.id = itech.intervention_id
            LEFT JOIN users ut ON itech.technicien_id = ut.id
            LEFT JOIN intervention_statuses its ON i.status_id = its.id
            LEFT JOIN intervention_types it ON i.type_id = it.id
            LEFT JOIN intervention_priorities ip ON i.priority_id = ip.id
            LEFT JOIN contracts co ON i.contract_id = co.id
            WHERE i.id = ? AND i.client_id IN ({$placeholders})
            GROUP BY i.id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$id], $clientIds));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les bâtiments d'un site selon les localisations autorisées
     * @param int $siteId ID du site
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des bâtiments
     */
    public function getBuildingsBySiteAndLocations($siteId, $userLocations)
    {
        $locationWhere = buildLocationWhereClause($userLocations, 's.client_id', 's.id', 'b.id');

        $sql = "SELECT DISTINCT b.id, b.name, b.site_id, b.status, b.comment, b.created_at, b.updated_at
                FROM buildings b
                JOIN sites s ON b.site_id = s.id
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

        $sql = "SELECT r.id, r.name, r.building_id, r.status, r.comment, 
                       r.created_at, r.updated_at,
                       c.first_name, c.last_name, c.phone1, c.email
                FROM rooms r
                JOIN buildings b ON r.building_id = b.id
                JOIN sites s ON b.site_id = s.id
                LEFT JOIN contacts c ON r.main_contact_id = c.id
                WHERE r.building_id = ? AND {$locationWhere} AND r.status = 1
                ORDER BY r.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$buildingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les salles d'un site selon les localisations autorisées (déprécié - utiliser getRoomsByBuildingAndLocations)
     * @param int $siteId ID du site
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des salles
     * @deprecated Utiliser getRoomsByBuildingAndLocations à la place
     */
    public function getRoomsBySiteAndLocations($siteId, $userLocations)
    {
        $locationWhere = buildLocationWhereClause($userLocations, 's.client_id', 's.id', 'r.id');

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
     * Récupère les statistiques selon les localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Statistiques des interventions
     */
    public function getStatsByLocations($userLocations)
    {
        // Extraire les IDs des clients auxquels l'utilisateur a accès
        $clientIds = [];

        foreach ($userLocations as $location) {
            if (isset($location['client_id']) && !in_array($location['client_id'], $clientIds)) {
                $clientIds[] = (int) $location['client_id'];
            }
        }

        if (empty($clientIds)) {
            return ['total' => 0, 'new_count' => 0, 'in_progress_count' => 0, 'closed_count' => 0];
        }

        $placeholders = str_repeat('?,', count($clientIds) - 1) . '?';

        $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status_id = (SELECT id FROM intervention_statuses WHERE name = 'Nouveau') THEN 1 ELSE 0 END) as new_count,
                SUM(CASE WHEN status_id = (SELECT id FROM intervention_statuses WHERE name = 'En cours') THEN 1 ELSE 0 END) as in_progress_count,
                SUM(CASE WHEN status_id = (SELECT id FROM intervention_statuses WHERE name = 'Fermé') THEN 1 ELSE 0 END) as closed_count
                FROM " . $this->table . " i
                WHERE i.client_id IN ({$placeholders})";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($clientIds);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques par statut selon les localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Statistiques par statut
     */
    public function getStatsByStatusAndLocations($userLocations)
    {
        $locationWhere = buildLocationWhereClause($userLocations, 'i.client_id', 'i.site_id', 'i.room_id');

        $sql = "SELECT 
                its.id,
                its.name,
                its.color,
                COUNT(i.id) as count
                FROM intervention_statuses its
                LEFT JOIN " . $this->table . " i ON its.id = i.status_id AND {$locationWhere}
                GROUP BY its.id, its.name, its.color
                ORDER BY its.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les sites selon les localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des sites
     */
    public function getSitesByLocations($userLocations)
    {
        if (empty($userLocations)) {
            return [];
        }

        $conditions = [];
        $params = [];

        foreach ($userLocations as $location) {
            $clientId = $location['client_id'] ?? null;
            $siteId = $location['site_id'] ?? null;

            if ($clientId === null) {
                // Accès global (administrateur)
                $sql = "SELECT DISTINCT s.id, s.name, s.client_id, s.status, s.address, s.city, s.postal_code,
                           s.phone, s.email, s.comment, s.created_at, s.updated_at
                    FROM sites s
                    WHERE s.status = 1
                    ORDER BY s.name";
                $stmt = $this->db->prepare($sql);
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $subConditions = ["s.client_id = ?"];
            $params[] = $clientId;

            if ($siteId !== null) {
                $subConditions[] = "s.id = ?";
                $params[] = $siteId;
            }

            $conditions[] = '(' . implode(' AND ', $subConditions) . ')';
        }

        $whereClause = implode(' OR ', $conditions);

        $sql = "SELECT DISTINCT s.id, s.name, s.client_id, s.status, s.address, s.city, s.postal_code,
                   s.phone, s.email, s.comment, s.created_at, s.updated_at
            FROM sites s
            WHERE ({$whereClause}) AND s.status = 1
            ORDER BY s.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les interventions récentes selon les localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @param int $limit Nombre d'interventions à récupérer
     * @return array Liste des interventions récentes
     */
    public function getRecentByLocations($userLocations, $limit = 10)
    {
        $locationWhere = buildLocationWhereClause($userLocations, 'i.client_id', 'i.site_id', 'i.room_id');

        $sql = "SELECT i.*, 
                c.name as client_name,
                s.name as site_name,
                b.name as building_name,
                r.name as room_name,
                its.name as status_name,
                its.color as status_color
                FROM " . $this->table . " i
                LEFT JOIN clients c ON i.client_id = c.id
                LEFT JOIN sites s ON i.site_id = s.id
                LEFT JOIN buildings b ON i.building_id = b.id
                LEFT JOIN rooms r ON i.room_id = r.id
                LEFT JOIN intervention_statuses its ON i.status_id = its.id
                WHERE {$locationWhere}
                ORDER BY i.created_at DESC
                LIMIT ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les commentaires d'une intervention avec vérification d'accès
     * @param int $interventionId ID de l'intervention
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @param bool $isClient Si l'utilisateur est un client (pour filtrer la visibilité)
     * @param int $userId ID de l'utilisateur connecté (pour filtrer ses propres commentaires)
     * @return array Liste des commentaires
     */
    public function getCommentsWithAccess($interventionId, $userLocations, $isClient = false, $userId = null)
    {
        // Vérifier d'abord que l'utilisateur a accès à l'intervention
        $intervention = $this->getByIdWithAccess($interventionId, $userLocations);
        if (!$intervention) {
            return [];
        }

        // Requête simple sans la clause de localisation complexe
        $sql = "SELECT ic.*, 
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            u.first_name, u.last_name, u.user_type_id
            FROM intervention_comments ic
            LEFT JOIN users u ON ic.created_by = u.id
            WHERE ic.intervention_id = ?";

        $params = [$interventionId];

        // Si c'est un client, filtrer les commentaires visibles
        if ($isClient) {
            $currentUserId = $userId ?? ($_SESSION['user']['id'] ?? 0);
            $sql .= " AND (ic.visible_by_client = 1 OR ic.created_by = ?)";
            $params[] = $currentUserId;
        }

        $sql .= " ORDER BY ic.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Log pour débogage
        custom_log("Commentaires récupérés pour l'intervention $interventionId: " . count($comments), 'DEBUG');

        return $comments;
    }

    /**
     * Récupère les pièces jointes d'une intervention avec vérification d'accès
     * @param int $interventionId ID de l'intervention
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des pièces jointes
     */
    public function getAttachmentsWithAccess($interventionId, $userLocations)
    {
        // Vérifier d'abord que l'utilisateur a accès à l'intervention
        $intervention = $this->getByIdWithAccess($interventionId, $userLocations);
        if (!$intervention) {
            return [];
        }

        // Requête simplifiée sans la clause de localisation complexe
        $sql = "SELECT pj.*, st.setting_value as type_nom,
            CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
            lpj.type_liaison
            FROM pieces_jointes pj
            LEFT JOIN settings st ON pj.type_id = st.id
            LEFT JOIN users u ON pj.created_by = u.id
            INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
            WHERE (lpj.type_liaison = 'intervention' OR lpj.type_liaison = 'bi')
            AND lpj.entite_id = ?
            AND pj.masque_client = 0
            ORDER BY pj.date_creation DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$interventionId]);
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Log pour débogage
        custom_log("Pièces jointes récupérées pour l'intervention $interventionId: " . count($attachments), 'DEBUG');

        return $attachments;
    }
    /**
     * Récupère les techniciens assignés à une intervention
     * @param int $interventionId ID de l'intervention
     * @return array Liste des techniciens
     */
    public function getTechniciansByIntervention($interventionId)
    {
        $sql = "SELECT 
        MAX(it.deplacement) AS type_requires_travel,
        GROUP_CONCAT(
            DISTINCT CONCAT(u.first_name, ' ', u.last_name)
            ORDER BY u.first_name
            SEPARATOR '\n'
        ) AS technicians_names,
        GROUP_CONCAT(
            DISTINCT u.id
            ORDER BY u.first_name
            SEPARATOR ','
        ) AS technicien_ids
    FROM intervention_techniciens it
    JOIN users u ON it.technicien_id = u.id
    WHERE it.intervention_id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$interventionId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    /**
     * Ajouter un commentaire
     * @param int $interventionId ID de l'intervention
     * @param int $userId ID de l'utilisateur
     * @param string $comment Contenu du commentaire
     * @param bool $isClient Si l'utilisateur est un client (pour auto-marquer comme visible)
     * @return bool Succès de l'opération
     */
    public function addComment($interventionId, $userId, $comment, $isClient = false)
    {
        try {
            // Si c'est un client, le commentaire est automatiquement visible par le client
            $visibleByClient = $isClient ? 1 : 0;

            $sql = "INSERT INTO intervention_comments (intervention_id, created_by, comment, visible_by_client, is_solution, is_observation, created_at) 
                    VALUES (?, ?, ?, ?, 0, 0, NOW())";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$interventionId, $userId, $comment, $visibleByClient]);
        } catch (Exception $e) {
            custom_log("Erreur lors de l'ajout du commentaire: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Récupérer un commentaire par son ID
     * @param int $commentId ID du commentaire
     * @return array|null Le commentaire ou null
     */
    public function getCommentById($commentId)
    {
        try {
            $sql = "SELECT ic.*, i.client_id, i.site_id, i.room_id
                    FROM intervention_comments ic
                    JOIN " . $this->table . " i ON ic.intervention_id = i.id
                    WHERE ic.id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$commentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération du commentaire: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Modifier un commentaire
     * @param int $commentId ID du commentaire
     * @param string $comment Nouveau contenu du commentaire
     * @return bool Succès de l'opération
     */
    public function updateComment($commentId, $comment)
    {
        try {
            $sql = "UPDATE intervention_comments SET comment = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$comment, $commentId]);

            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                custom_log("Erreur SQL lors de la modification du commentaire ID {$commentId}: " . print_r($errorInfo, true), 'ERROR');
                return false;
            }

            return true;
        } catch (Exception $e) {
            custom_log("Erreur lors de la modification du commentaire ID {$commentId}: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Supprimer un commentaire
     * @param int $commentId ID du commentaire
     * @return bool Succès de l'opération
     */
    public function deleteComment($commentId)
    {
        try {
            $sql = "DELETE FROM intervention_comments WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$commentId]);
        } catch (Exception $e) {
            custom_log("Erreur lors de la suppression du commentaire: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Récupère tous les statuts d'intervention
     * @return array Liste des statuts
     */
    public function getAllStatuses()
    {
        $sql = "SELECT id, name, color, is_critical, created_at FROM intervention_statuses ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère toutes les priorités d'intervention
     * @return array Liste des priorités
     */
    public function getAllPriorities()
    {
        $sql = "SELECT id, name, color, created_at FROM intervention_priorities ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les contrats d'un client
     * @param int $clientId ID du client
     * @return array Liste des contrats
     */
    public function getContractsByClient($clientId)
    {
        $sql = "SELECT c.*, ct.name as contract_type_name
                FROM contracts c
                LEFT JOIN contract_types ct ON c.contract_type_id = ct.id
                WHERE c.client_id = ? AND c.status = 'actif' 
                ORDER BY c.name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les contacts d'un client
     * @param int $clientId ID du client
     * @return array Liste des contacts
     */
    public function getContactsByClient($clientId)
    {
        $sql = "SELECT id, client_id, first_name, last_name, fonction, phone1, phone2, email, comment, status, has_user_account, user_id, created_at, updated_at FROM contacts 
                WHERE client_id = ? AND status = 1 
                ORDER BY last_name, first_name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crée une nouvelle intervention
     * @param array $data Données de l'intervention
     * @return int|false ID de l'intervention créée ou false en cas d'erreur
     */
    public function create($data)
    {
        try {
            $this->db->beginTransaction();

            // Générer une référence unique si elle n'existe pas
            if (empty($data['reference'])) {
                $data['reference'] = $this->generateReference();
            }

            $sql = "INSERT INTO interventions (
                        reference, title, description, demande_par, client_id, site_id, building_id, room_id, 
                        contract_id, type_id, status_id, priority_id, duration,  ref_client, contact_client, created_at
                    ) VALUES (
                        :reference, :title, :description, :demande_par, :client_id, :site_id, :building_id, :room_id,
                        :contract_id, :type_id, :status_id, :priority_id, :duration, :ref_client, :contact_client, NOW()
                    )";

            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute([
                ':reference' => $data['reference'],
                ':title' => $data['title'],
                ':description' => $data['description'],
                ':demande_par' => $data['demande_par'] ?? null,
                ':client_id' => $data['client_id'],
                ':site_id' => $data['site_id'] ?? null,
                ':building_id' => $data['building_id'] ?? null,
                ':room_id' => $data['room_id'] ?? null,
                ':contract_id' => $data['contract_id'] ?? null,
                ':type_id' => $data['type_id'] ?? 1,
                ':status_id' => $data['status_id'],
                ':priority_id' => $data['priority_id'],
                ':duration' => $data['duration'] ?? 0,
                ':ref_client' => $data['ref_client'] ?? null,
                ':contact_client' => $data['contact_client'] ?? null
            ]);

            if ($success) {
                $interventionId = $this->db->lastInsertId();
                $this->db->commit();
                return $interventionId;
            } else {
                $this->db->rollBack();
                return false;
            }
        } catch (Exception $e) {
            $this->db->rollBack();
            custom_log("Erreur lors de la création de l'intervention: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Génère une référence unique pour une intervention
     * @return string Référence générée
     */
    private function generateReference()
    {
        // Format: INT-YYYY-NNNNNN (ex: INT-2025-000001)
        $year = date('Y');

        // Récupérer le dernier numéro de l'année
        $sql = "SELECT reference FROM interventions 
                WHERE reference LIKE ? 
                ORDER BY reference DESC 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(["INT-{$year}-%"]);
        $lastRef = $stmt->fetchColumn();

        if ($lastRef) {
            // Extraire le numéro et l'incrémenter
            $parts = explode('-', $lastRef);
            $number = (int) end($parts) + 1;
        } else {
            $number = 1;
        }

        return sprintf("INT-%s-%06d", $year, $number);
    }
    /**
     * Récupère une pièce jointe par son ID
     * @param int $attachmentId ID de la pièce jointe
     * @return array|null La pièce jointe ou null
     */
    public function getAttachmentById($attachmentId)
    {
        try {
            $sql = "SELECT pj.*, lpj.type_liaison, lpj.entite_id as intervention_id,
                i.client_id, i.site_id, i.building_id, i.room_id
                FROM pieces_jointes pj
                INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                JOIN " . $this->table . " i ON lpj.entite_id = i.id
                WHERE pj.id = ? AND lpj.type_liaison = 'intervention'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$attachmentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération de la pièce jointe: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }
    /**
     * Récupère le contrat associé à une salle, en vérifiant que la salle
     * appartient bien aux localisations autorisées de l'utilisateur
     */
    public function getContractByRoomAndLocations($roomId, $userLocations)
    {
        $locationWhere = buildLocationWhereClause($userLocations, 's.client_id', 's.id', 'b.id', 'r.id');

        $sql = "SELECT c.*, ct.name as contract_type_name
            FROM contracts c
            JOIN contract_rooms cr ON c.id = cr.contract_id
            JOIN rooms r ON cr.room_id = r.id
            JOIN buildings b ON r.building_id = b.id
            JOIN sites s ON b.site_id = s.id
            LEFT JOIN contract_types ct ON c.contract_type_id = ct.id
            WHERE cr.room_id = ? AND c.status = 'actif' AND {$locationWhere}
            ORDER BY c.name
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$roomId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    /**
     * Récupère les interventions pour export CSV, selon localisations autorisées et filtres date/type
     * @param array $userLocations
     * @param array $filters ['date_start' => 'Y-m-d'|null, 'date_end' => 'Y-m-d'|null, 'type' => 'all'|'curative'|'preventive']
     * @return array
     */
    public function getForExport($userLocations, $filters = [])
    {
        custom_log("EXPORT DEBUG - userLocations reçues: " . json_encode($userLocations), 'DEBUG');
        custom_log("EXPORT DEBUG - filters reçus: " . json_encode($filters), 'DEBUG');

        $clientIds = [];
        foreach ($userLocations as $location) {
            if (isset($location['client_id']) && !in_array($location['client_id'], $clientIds)) {
                $clientIds[] = (int) $location['client_id'];
            }
        }

        custom_log("EXPORT DEBUG - clientIds extraits: " . json_encode($clientIds), 'DEBUG');

        if (empty($clientIds)) {
            custom_log("EXPORT DEBUG - clientIds vide, retour tableau vide", 'DEBUG');
            return [];
        }

        if (empty($clientIds)) {
            return [];
        }

        $placeholders = str_repeat('?,', count($clientIds) - 1) . '?';

        $sql = "SELECT i.*,
        c.name as client_name,
        s.name as site_name,
        b.name as building_name,
        r.name as room_name,
        its.name as status_name,
        its.color as status_color,
        ip.name as priority_name,
        ip.color as priority_color,
        GROUP_CONCAT(DISTINCT CONCAT(ut.first_name, ' ', ut.last_name) ORDER BY ut.first_name SEPARATOR ', ') as technicians_names
        FROM " . $this->table . " i
        LEFT JOIN clients c ON i.client_id = c.id
        LEFT JOIN sites s ON i.site_id = s.id
        LEFT JOIN buildings b ON i.building_id = b.id
        LEFT JOIN rooms r ON i.room_id = r.id
        LEFT JOIN intervention_techniciens itech ON i.id = itech.intervention_id
        LEFT JOIN users ut ON itech.technicien_id = ut.id
        LEFT JOIN intervention_statuses its ON i.status_id = its.id
        LEFT JOIN intervention_priorities ip ON i.priority_id = ip.id
        WHERE i.client_id IN ({$placeholders})";

        $params = $clientIds;

        if (!empty($filters['date_start'])) {
            $sql .= " AND i.created_at >= ?";
            $params[] = $filters['date_start'] . ' 00:00:00';
        }

        if (!empty($filters['date_end'])) {
            $sql .= " AND i.created_at <= ?";
            $params[] = $filters['date_end'] . ' 23:59:59';
        }

        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $sql .= " AND i.is_preventive = ?";
            $params[] = ($filters['type'] === 'preventive') ? 1 : 0;
        }

        $sql .= " GROUP BY i.id ORDER BY i.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        custom_log("EXPORT DEBUG - SQL: " . $sql, 'DEBUG');
        custom_log("EXPORT DEBUG - nombre de lignes retournées: " . count($result), 'DEBUG');
        return $result;
    }
    /**
     * Récupère les comptes-rendus techniciens qu'un technicien a explicitement
     * marqués comme visibles par le client (visible_by_client = 1), avec un
     * contenu non vide. Le champ interne `commentaire` reste, lui, toujours
     * invisible pour le client.
     *
     * @param int $interventionId ID de l'intervention
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des comptes-rendus visibles par le client
     */
    public function getTechnicianReportsWithAccess($interventionId, $userLocations)
    {
        // Vérifier d'abord que l'utilisateur a accès à l'intervention
        $intervention = $this->getByIdWithAccess($interventionId, $userLocations);
        if (!$intervention) {
            return [];
        }

        $sql = "SELECT it.id, it.technicien_id, it.start_time, it.end_time, it.temps_passe,
                   it.compte_rendu_client, it.visible_by_client,
                   CONCAT(u.first_name, ' ', u.last_name) as technicien_name
            FROM intervention_techniciens it
            JOIN users u ON it.technicien_id = u.id
            WHERE it.intervention_id = ?
              AND it.visible_by_client = 1
              AND it.compte_rendu_client IS NOT NULL
              AND it.compte_rendu_client != ''
            ORDER BY it.start_time ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$interventionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


}