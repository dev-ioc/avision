<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Models/BaseModel.php';

/**
 * Modèle pour la gestion des interventions
 */
class InterventionModel extends BaseModel
{
    public function __construct($db)
    {
        parent::__construct($db);
        $this->table = 'interventions';
    }

    /**
     * Récupère toutes les interventions avec filtres
     */
    public function getAll($filters = [])
    {
        $sql = "SELECT i.*, 
            c.name as client_name,
            s.name as site_name,
            b.name as building_name,
            r.name as room_name,
            its.name as status_name,
            its.color as status_color,
            it.name as type_name,
            ip.name as priority_name,
            ip.color as priority_color
            FROM " . $this->table . " i
            LEFT JOIN clients c ON i.client_id = c.id
            LEFT JOIN sites s ON i.site_id = s.id
            LEFT JOIN buildings b ON i.building_id = b.id
            LEFT JOIN rooms r ON i.room_id = r.id
            LEFT JOIN intervention_statuses its ON i.status_id = its.id
            LEFT JOIN intervention_types it ON i.type_id = it.id
            LEFT JOIN intervention_priorities ip ON i.priority_id = ip.id
            WHERE 1=1";

        $params = [];

        // Appliquer les filtres
        if (!empty($filters['client_id'])) {
            $sql .= " AND i.client_id = ?";
            $params[] = $filters['client_id'];
        }
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
        if (!empty($filters['priority_id'])) {
            $sql .= " AND i.priority_id = ?";
            $params[] = $filters['priority_id'];
        }
        if (!empty($filters['i.created_at'])) {
            $sql .= " AND i.created_at = ?";
            $params[] = $filters['created_at'];
        }
        // Filtre par type d'intervention (préventive/curative)
        if (isset($filters['is_preventive'])) {
            $sql .= " AND i.is_preventive = :is_preventive";
            $params[':is_preventive'] = $filters['is_preventive'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (i.title LIKE ? OR c.name LIKE ? OR s.name LIKE ? OR r.name LIKE ? OR i.reference LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        if (!empty($filters['exclude_status_ids'])) {
            $placeholders = str_repeat('?,', count($filters['exclude_status_ids']) - 1) . '?';
            $sql .= " AND i.status_id NOT IN ($placeholders)";
            $params = array_merge($params, $filters['exclude_status_ids']);
        }
        if (!empty($filters['exclude_priority_ids'])) {
            $placeholders = str_repeat('?,', count($filters['exclude_priority_ids']) - 1) . '?';
            $sql .= " AND i.priority_id NOT IN ($placeholders)";
            $params = array_merge($params, $filters['exclude_priority_ids']);
        }

        $sql .= " ORDER BY i.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $results;
    }

    /**
     * Récupère une intervention par son ID
     */
    public function getById($id)
    {
        $sql = "SELECT i.*, 
            c.name as client_name,
            s.name as site_name,
            s.address as site_address,
            s.postal_code as site_postal_code,
            s.city as site_city,
            s.phone as site_phone,
            s.email as site_email,
            b.name as building_name,
            r.name as room_name,
            its.name as status_name,
            its.color as status_color,
            it.name as type_name,
            ip.name as priority_name,
            ip.color as priority_color,
            co.name as contract_name,
            co.contract_type_id,
            ct.name as contract_type_name,
            cont.first_name as contact_first_name,
            cont.last_name as contact_last_name,
            cont.email as contact_email,
            cont.phone1 as contact_phone,
            i.is_preventive
            FROM " . $this->table . " i
            LEFT JOIN clients c ON i.client_id = c.id
            LEFT JOIN sites s ON i.site_id = s.id
            LEFT JOIN buildings b ON i.building_id = b.id
            LEFT JOIN rooms r ON i.room_id = r.id
            LEFT JOIN intervention_statuses its ON i.status_id = its.id
            LEFT JOIN intervention_types it ON i.type_id = it.id
            LEFT JOIN intervention_priorities ip ON i.priority_id = ip.id
            LEFT JOIN contracts co ON i.contract_id = co.id
            LEFT JOIN contract_types ct ON co.contract_type_id = ct.id
            LEFT JOIN contacts cont ON s.main_contact_id = cont.id
            WHERE i.id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result;
    }

    /**
     * Récupère les statistiques des interventions
     */
    public function getStats($filters = [])
    {
        $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status_id = (SELECT id FROM intervention_statuses WHERE name = 'Nouveau') THEN 1 ELSE 0 END) as new_count,
                SUM(CASE WHEN status_id = (SELECT id FROM intervention_statuses WHERE name = 'En cours') THEN 1 ELSE 0 END) as in_progress_count,
                SUM(CASE WHEN status_id = (SELECT id FROM intervention_statuses WHERE name = 'Fermé') THEN 1 ELSE 0 END) as closed_count
                FROM " . $this->table . " i
                WHERE 1=1";

        $params = [];

        // Appliquer les filtres
        if (!empty($filters['client_id'])) {
            $sql .= " AND i.client_id = ?";
            $params[] = $filters['client_id'];
        }
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
        if (!empty($filters['priority_id'])) {
            $sql .= " AND i.priority_id = ?";
            $params[] = $filters['priority_id'];
        }
        if (!empty($filters['exclude_priority_ids'])) {
            $placeholders = str_repeat('?,', count($filters['exclude_priority_ids']) - 1) . '?';
            $sql .= " AND i.priority_id NOT IN ($placeholders)";
            $params = array_merge($params, $filters['exclude_priority_ids']);
        }
        if (!empty($filters['exclude_status_ids'])) {
            $placeholders = str_repeat('?,', count($filters['exclude_status_ids']) - 1) . '?';
            $sql .= " AND i.status_id NOT IN ($placeholders)";
            $params = array_merge($params, $filters['exclude_status_ids']);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques par statut pour les filtres rapides
     */
    public function getStatsByStatus($filters = [])
    {
        $sql = "SELECT 
            its.id,
            its.name,
            its.color,
            COUNT(i.id) as count
            FROM intervention_statuses its
            LEFT JOIN " . $this->table . " i ON its.id = i.status_id";

        $whereConditions = [];
        $params = [];

        // Appliquer les filtres
        if (!empty($filters['client_id'])) {
            $whereConditions[] = "i.client_id = ?";
            $params[] = $filters['client_id'];
        }
        if (!empty($filters['site_id'])) {
            $whereConditions[] = "i.site_id = ?";
            $params[] = $filters['site_id'];
        }
        if (!empty($filters['building_id'])) {
            $whereConditions[] = "i.building_id = ?";
            $params[] = $filters['building_id'];
        }
        if (!empty($filters['room_id'])) {
            $whereConditions[] = "i.room_id = ?";
            $params[] = $filters['room_id'];
        }
        if (!empty($filters['priority_id'])) {
            $whereConditions[] = "i.priority_id = ?";
            $params[] = $filters['priority_id'];
        }

        // Filtre par type d'intervention
        if (isset($filters['is_preventive'])) {
            $whereConditions[] = "i.is_preventive = ?";
            $params[] = $filters['is_preventive'];
        }

        if (!empty($filters['exclude_priority_ids'])) {
            $placeholders = str_repeat('?,', count($filters['exclude_priority_ids']) - 1) . '?';
            $whereConditions[] = "i.priority_id NOT IN ($placeholders)";
            $params = array_merge($params, $filters['exclude_priority_ids']);
        }
        if (!empty($filters['exclude_status_ids'])) {
            $placeholders = str_repeat('?,', count($filters['exclude_status_ids']) - 1) . '?';
            $whereConditions[] = "i.status_id NOT IN ($placeholders)";
            $params = array_merge($params, $filters['exclude_status_ids']);
        }

        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(" AND ", $whereConditions);
        }

        $sql .= " GROUP BY its.id, its.name, its.color ORDER BY its.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Met à jour une intervention
     */
    public function update($id, $data)
    {
        $updates = [];
        $params = [];

        if (isset($data['title'])) {
            $updates[] = "title = :title";
            $params[':title'] = $data['title'];
        }

        if (isset($data['client_id'])) {
            $updates[] = "client_id = :client_id";
            $params[':client_id'] = $data['client_id'];
        }

        if (isset($data['site_id'])) {
            $updates[] = "site_id = :site_id";
            $params[':site_id'] = empty($data['site_id']) ? null : $data['site_id'];
        }

        if (isset($data['building_id'])) {
            $updates[] = "building_id = :building_id";
            $params[':building_id'] = empty($data['building_id']) ? null : $data['building_id'];
        }

        if (isset($data['room_id'])) {
            $updates[] = "room_id = :room_id";
            $params[':room_id'] = empty($data['room_id']) ? null : $data['room_id'];
        }

        if (isset($data['status_id'])) {
            $updates[] = "status_id = :status_id";
            $params[':status_id'] = $data['status_id'];
        }

        if (isset($data['priority_id'])) {
            $updates[] = "priority_id = :priority_id";
            $params[':priority_id'] = $data['priority_id'];
        }

        if (isset($data['type_id'])) {
            $updates[] = "type_id = :type_id";
            $params[':type_id'] = $data['type_id'];
        }

        if (isset($data['description'])) {
            $updates[] = "description = :description";
            $params[':description'] = $data['description'];
        }

        if (array_key_exists('demande_par', $data)) {
            $updates[] = "demande_par = :demande_par";
            $params[':demande_par'] = $data['demande_par'];
        }

        if (isset($data['type_requires_travel'])) {
            $updates[] = "type_requires_travel = :type_requires_travel";
            $params[':type_requires_travel'] = $data['type_requires_travel'];
        }

        if (isset($data['tickets_used'])) {
            $updates[] = "tickets_used = :tickets_used";
            $params[':tickets_used'] = $data['tickets_used'];
        }

        if (array_key_exists('contract_id', $data)) {
            $updates[] = "contract_id = :contract_id";
            $params[':contract_id'] = $data['contract_id'];
        }

        if (isset($data['closed_at'])) {
            $updates[] = "closed_at = :closed_at";
            $params[':closed_at'] = $data['closed_at'];
        }

        if (array_key_exists('is_preventive', $data)) {
            $updates[] = "is_preventive = :is_preventive";
            $params[':is_preventive'] = $data['is_preventive'];
        }

        if (array_key_exists('created_at', $data)) {
            $updates[] = "created_at = :created_at";
            $params[':created_at'] = $data['created_at'];
        }

        if (array_key_exists('ref_client', $data)) {
            $updates[] = "ref_client = :ref_client";
            $params[':ref_client'] = $data['ref_client'];
        }

        if (array_key_exists('contact_client', $data)) {
            $updates[] = "contact_client = :contact_client";
            $params[':contact_client'] = $data['contact_client'];
        }

        if (empty($updates)) {
            return false;
        }

        $params[':id'] = $id;
        $sql = "UPDATE " . $this->table . " SET " . implode(", ", $updates) . " WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);

        if (!$result) {
            custom_log("Erreur SQL Update Intervention: " . implode(", ", $stmt->errorInfo()), "ERROR");
        }

        return $result;
    }

    /**
     * Récupère les informations d'un type d'intervention
     */
    public function getTypeInfo($typeId)
    {
        $sql = "SELECT id, name, requires_travel, created_at FROM intervention_types WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$typeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère tous les types d'intervention
     */
    public function getAllTypes()
    {
        $sql = "SELECT id, name, requires_travel, created_at FROM intervention_types ORDER BY name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Génère une référence unique pour une intervention
     */
    public function generateReference($clientId)
    {
        try {
            $year = date('y');
            $sql = "SELECT reference FROM interventions 
                    WHERE client_id = ? 
                    AND reference LIKE ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$clientId, "#VS{$clientId}{$year}-%"]);
            $existingReferences = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $maxAttempts = 100;
            $attempt = 0;
            $reference = null;

            while ($attempt < $maxAttempts) {
                $randomNumber = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $newReference = "#VS{$clientId}{$year}-{$randomNumber}";

                if (!in_array($newReference, $existingReferences)) {
                    $reference = $newReference;
                    break;
                }
                $attempt++;
            }

            if ($reference === null) {
                custom_log("Impossible de générer une référence unique après {$maxAttempts} tentatives", 'ERROR');
                return false;
            }

            return $reference;
        } catch (PDOException $e) {
            custom_log("Erreur lors de la génération de la référence : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Crée une nouvelle intervention
     */
    public function create($data)
    {
        try {
            $reference = $this->generateReference($data['client_id']);
            if (!$reference) {
                return false;
            }

            $sql = "INSERT INTO interventions (
            reference, title, client_id, site_id, building_id, room_id, 
            status_id, type_id, 
            description, demande_par, ref_client, contact_client, 
            contract_id, is_preventive, created_at, duration
        ) VALUES (
            :reference, :title, :client_id, :site_id, :building_id, :room_id, 
            :status_id, :type_id, 
            :description, :demande_par, :ref_client, :contact_client, 
            :contract_id, :is_preventive, NOW(), :duration
        )";

            $stmt = $this->db->prepare($sql);
            $params = [
                ':reference' => $reference,
                ':title' => $data['title'],
                ':client_id' => $data['client_id'],
                ':site_id' => $data['site_id'] ?? null,
                ':building_id' => $data['building_id'] ?? null,
                ':room_id' => $data['room_id'] ?? null,
                ':status_id' => $data['status_id'],
                ':type_id' => $data['type_id'],
                ':description' => $data['description'] ?? null,
                ':demande_par' => $data['demande_par'] ?? null,
                ':ref_client' => $data['ref_client'] ?? null,
                ':contact_client' => $data['contact_client'] ?? null,
                ':contract_id' => $data['contract_id'] ?? null,
                ':is_preventive' => $data['is_preventive'] ?? 0,
                'duration' => $data['duration'] ?? null
            ];

            $result = $stmt->execute($params);
            return $result ? $this->db->lastInsertId() : false;

        } catch (PDOException $e) {
            custom_log("Erreur lors de la création de l'intervention : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Récupère les pièces jointes d'une intervention
     */
    public function getPiecesJointes($interventionId)
    {
        $query = "
            SELECT 
    pj.*,
    st.setting_value as type_nom,
    lpj.type_liaison,
    lpj.pour_bon_intervention,
    u.username as created_by_name
FROM pieces_jointes pj
LEFT JOIN settings st ON pj.type_id = st.id
INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
LEFT JOIN users u ON u.id = pj.created_by
WHERE (lpj.type_liaison = 'intervention' OR lpj.type_liaison = 'bi')
AND lpj.entite_id = :intervention_id
ORDER BY pj.date_creation DESC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':intervention_id', $interventionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajoute une pièce jointe à une intervention
     */
    public function addPieceJointe($interventionId, $data)
    {
        try {
            $this->db->beginTransaction();

            $query = "INSERT INTO pieces_jointes (
                        nom_fichier, nom_personnalise, chemin_fichier, type_fichier, taille_fichier, 
                        commentaire, masque_client, type_id, created_by
                    ) VALUES (
                        :nom_fichier, :nom_personnalise, :chemin_fichier, :type_fichier, :taille_fichier,
                        :commentaire, :masque_client, :type_id, :created_by
                    )";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':nom_fichier' => $data['nom_fichier'],
                ':nom_personnalise' => $data['nom_personnalise'] ?? $data['nom_fichier'],
                ':chemin_fichier' => $data['chemin_fichier'],
                ':type_fichier' => $data['type_fichier'],
                ':taille_fichier' => $data['taille_fichier'],
                ':commentaire' => $data['commentaire'] ?? null,
                ':masque_client' => $data['masque_client'] ?? 0,
                ':type_id' => $data['type_id'] ?? null,
                ':created_by' => $data['created_by'] ?? null
            ]);

            $pieceJointeId = $this->db->lastInsertId();

            $query = "INSERT INTO liaisons_pieces_jointes (
                        piece_jointe_id, type_liaison, entite_id
                    ) VALUES (
                        :piece_jointe_id, 'intervention', :intervention_id
                    )";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':piece_jointe_id' => $pieceJointeId,
                ':intervention_id' => $interventionId
            ]);

            $this->db->commit();
            return $pieceJointeId;
        } catch (Exception $e) {
            $this->db->rollBack();
            custom_log("Erreur lors de l'ajout de la pièce jointe : " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Récupère uniquement les bons d'intervention
     */
    public function getBonsIntervention($interventionId)
    {
        $query = "
            SELECT 
                pj.*,
                st.setting_value as type_nom,
                lpj.type_liaison
            FROM pieces_jointes pj
            LEFT JOIN settings st ON pj.type_id = st.id
            INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
            WHERE lpj.type_liaison = 'bi'
            AND lpj.entite_id = :intervention_id
            ORDER BY pj.date_creation DESC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':intervention_id', $interventionId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Supprime une pièce jointe
     */
    public function deletePieceJointe($pieceJointeId, $interventionId)
    {
        try {
            $this->db->beginTransaction();

            $query = "SELECT pj.* FROM pieces_jointes pj
                     INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                     WHERE (lpj.type_liaison = 'intervention' OR lpj.type_liaison = 'bi')
                     AND lpj.entite_id = :intervention_id 
                     AND pj.id = :piece_jointe_id";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':intervention_id' => $interventionId,
                ':piece_jointe_id' => $pieceJointeId
            ]);

            $pieceJointe = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$pieceJointe) {
                throw new Exception("Pièce jointe non trouvée ou n'appartient pas à cette intervention");
            }

            $filePath = __DIR__ . '/../' . $pieceJointe['chemin_fichier'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $query = "DELETE FROM liaisons_pieces_jointes 
                     WHERE piece_jointe_id = :piece_jointe_id 
                     AND (type_liaison = 'intervention' OR type_liaison = 'bi')
                     AND entite_id = :intervention_id";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':piece_jointe_id' => $pieceJointeId,
                ':intervention_id' => $interventionId
            ]);

            $query = "DELETE FROM pieces_jointes WHERE id = :piece_jointe_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':piece_jointe_id' => $pieceJointeId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            custom_log("Erreur lors de la suppression de la pièce jointe : " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Récupère une pièce jointe par son ID
     */
    public function getPieceJointeById($pieceJointeId)
    {
        $query = "SELECT pj.*, lpj.type_liaison, lpj.entite_id
                 FROM pieces_jointes pj
                 INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                 WHERE pj.id = :piece_jointe_id";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':piece_jointe_id' => $pieceJointeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Met à jour la visibilité d'une pièce jointe
     */
    public function updatePieceJointeVisibility($pieceJointeId, $masqueClient)
    {
        $query = "UPDATE pieces_jointes 
                 SET masque_client = :masque_client 
                 WHERE id = :piece_jointe_id";

        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':masque_client' => $masqueClient,
            ':piece_jointe_id' => $pieceJointeId
        ]);
    }

    /**
     * Met à jour le nom d'une pièce jointe
     */
    public function updateAttachmentName($pieceJointeId, $newName)
    {
        try {
            $query = "UPDATE pieces_jointes 
                     SET nom_personnalise = :nom_personnalise 
                     WHERE id = :piece_jointe_id";

            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                ':nom_personnalise' => $newName,
                ':piece_jointe_id' => $pieceJointeId
            ]);
        } catch (Exception $e) {
            custom_log("Erreur lors de la mise à jour du nom de la pièce jointe : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Met à jour la sélection des commentaires pour le bon d'intervention
     */
    public function updateCommentsForBon($interventionId, $selectedCommentIds)
    {
        try {
            $this->db->beginTransaction();

            $query = "UPDATE intervention_comments 
                     SET pour_bon_intervention = 0 
                     WHERE intervention_id = :intervention_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':intervention_id' => $interventionId]);

            if (!empty($selectedCommentIds)) {
                $placeholders = str_repeat('?,', count($selectedCommentIds) - 1) . '?';
                $query = "UPDATE intervention_comments 
                         SET pour_bon_intervention = 1 
                         WHERE id IN ($placeholders) AND intervention_id = ?";
                $stmt = $this->db->prepare($query);
                $params = array_merge($selectedCommentIds, [$interventionId]);
                $stmt->execute($params);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            custom_log("Erreur lors de la mise à jour de la sélection des commentaires : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Met à jour la sélection des pièces jointes pour le bon d'intervention
     */
    public function updateAttachmentsForBon($interventionId, $selectedAttachmentIds)
    {
        try {
            $this->db->beginTransaction();

            $query = "UPDATE liaisons_pieces_jointes 
                     SET pour_bon_intervention = 0 
                     WHERE entite_id = ? AND (type_liaison = 'intervention' OR type_liaison = 'bi')";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$interventionId]);

            if (!empty($selectedAttachmentIds)) {
                $placeholders = str_repeat('?,', count($selectedAttachmentIds) - 1) . '?';
                $query = "UPDATE liaisons_pieces_jointes 
                         SET pour_bon_intervention = 1 
                         WHERE piece_jointe_id IN ($placeholders) AND entite_id = ? AND (type_liaison = 'intervention' OR type_liaison = 'bi')";
                $stmt = $this->db->prepare($query);
                $params = array_merge($selectedAttachmentIds, [$interventionId]);
                $stmt->execute($params);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            custom_log("Erreur lors de la mise à jour de la sélection des pièces jointes : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Ajoute une pièce jointe avec un type de liaison spécifique
     */
    public function addPieceJointeWithType($interventionId, $data, $typeLiaison)
    {
        try {
            $this->db->beginTransaction();

            $query = "INSERT INTO pieces_jointes (
                        nom_fichier, nom_personnalise, chemin_fichier, type_fichier, taille_fichier, 
                        commentaire, masque_client, type_id, created_by
                    ) VALUES (
                        :nom_fichier, :nom_personnalise, :chemin_fichier, :type_fichier, :taille_fichier,
                        :commentaire, :masque_client, :type_id, :created_by
                    )";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':nom_fichier' => $data['nom_fichier'],
                ':nom_personnalise' => $data['nom_personnalise'] ?? $data['nom_fichier'],
                ':chemin_fichier' => $data['chemin_fichier'],
                ':type_fichier' => $data['type_fichier'],
                ':taille_fichier' => $data['taille_fichier'],
                ':commentaire' => $data['commentaire'] ?? null,
                ':masque_client' => $data['masque_client'] ?? 0,
                ':type_id' => $data['type_id'] ?? null,
                ':created_by' => $data['created_by'] ?? null
            ]);

            $pieceJointeId = $this->db->lastInsertId();

            $query = "INSERT INTO liaisons_pieces_jointes (
                        piece_jointe_id, type_liaison, entite_id
                    ) VALUES (
                        :piece_jointe_id, :type_liaison, :intervention_id
                    )";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':piece_jointe_id' => $pieceJointeId,
                ':type_liaison' => $typeLiaison,
                ':intervention_id' => $interventionId
            ]);

            $this->db->commit();
            return $pieceJointeId;
        } catch (Exception $e) {
            $this->db->rollBack();
            custom_log("Erreur lors de l'ajout de la pièce jointe : " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Récupère les interventions planifiées pour l'agenda
     */
    public function getScheduledInterventions($filters = [])
    {
        $sql = "SELECT DISTINCT i.*, 
            c.name as client_name,
            s.name as site_name,
            b.name as building_name,
            r.name as room_name,
            its.name as status_name,
            its.color as status_color,
            it.name as type_name,
            ip.name as priority_name,
            ip.color as priority_color,
            ite.start_time as date_planif,
            ite.end_time as end_time,
            ite.temps_passe as duration,
            u.id as technician_id,
            CONCAT(u.first_name, ' ', u.last_name) as technician_name
            FROM " . $this->table . " i
            LEFT JOIN clients c ON i.client_id = c.id
            LEFT JOIN sites s ON i.site_id = s.id
            LEFT JOIN buildings b ON i.building_id = b.id
            LEFT JOIN rooms r ON i.room_id = r.id
            LEFT JOIN intervention_statuses its ON i.status_id = its.id
            LEFT JOIN intervention_types it ON i.type_id = it.id
            LEFT JOIN intervention_priorities ip ON i.priority_id = ip.id
            INNER JOIN intervention_techniciens ite ON i.id = ite.intervention_id
            LEFT JOIN users u ON ite.technicien_id = u.id
            WHERE ite.start_time IS NOT NULL AND ite.start_time > '1900-01-01'";

        $params = [];

        if (!empty($filters['client_id'])) {
            $sql .= " AND i.client_id = ?";
            $params[] = $filters['client_id'];
        }
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
        if (!empty($filters['priority_id'])) {
            $sql .= " AND i.priority_id = ?";
            $params[] = $filters['priority_id'];
        }

        if (!empty($filters['technician_filter'])) {
            if (!empty($filters['technician_filter']['technician_ids'])) {
                $placeholders = str_repeat('?,', count($filters['technician_filter']['technician_ids']) - 1) . '?';
                $sql .= " AND u.id IN ($placeholders)";
                $params = array_merge($params, $filters['technician_filter']['technician_ids']);
            }

            if (!empty($filters['technician_filter']['show_unassigned'])) {
                $sql .= " OR (i.id IN (SELECT intervention_id FROM intervention_techniciens WHERE start_time IS NOT NULL AND technicien_id IS NULL))";
            }
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND ite.start_time >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND ite.start_time <= ?";
            $params[] = $filters['date_to'];
        }

        $sql .= " ORDER BY ite.start_time ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $results;
    }

    /**
     * Récupère les commentaires solution d'une intervention
     */
    public function getSolutionComments($interventionId)
    {
        $sql = "SELECT ic.*, 
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM intervention_comments ic
                LEFT JOIN users u ON ic.created_by = u.id
                WHERE ic.intervention_id = ? AND ic.is_solution = 1 
                ORDER BY ic.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$interventionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les commentaires pour le bon d'intervention
     */
    public function getCommentsForBon($interventionId)
    {
        $sql = "SELECT ic.*, 
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM intervention_comments ic
                LEFT JOIN users u ON ic.created_by = u.id
                WHERE ic.intervention_id = ? AND ic.pour_bon_intervention = 1
                ORDER BY ic.is_solution DESC, ic.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$interventionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les interventions d'un client groupées par contrat et par type
     */
    public function getInterventionsByClientGrouped($clientId)
    {
        $sql = "SELECT i.*, 
            c.name as client_name,
            s.name as site_name,
            b.name as building_name,
            r.name as room_name,
            its.name as status_name,
            its.color as status_color,
            it.name as type_name,
            ip.name as priority_name,
            ip.color as priority_color,
            co.name as contract_name,
            co.id as contract_id,
            ct.name as contract_type_name,
            i.is_preventive
            FROM " . $this->table . " i
            LEFT JOIN clients c ON i.client_id = c.id
            LEFT JOIN sites s ON i.site_id = s.id
            LEFT JOIN buildings b ON i.building_id = b.id
            LEFT JOIN rooms r ON i.room_id = r.id
            LEFT JOIN intervention_statuses its ON i.status_id = its.id
            LEFT JOIN intervention_types it ON i.type_id = it.id
            LEFT JOIN intervention_priorities ip ON i.priority_id = ip.id
            LEFT JOIN contracts co ON i.contract_id = co.id
            LEFT JOIN contract_types ct ON co.contract_type_id = ct.id
            WHERE i.client_id = ?
            ORDER BY co.name ASC, i.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clientId]);
        $interventions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $groupedInterventions = [];

        foreach ($interventions as $intervention) {
            $contractName = $intervention['contract_name'] ?: 'Sans contrat';
            $contractId = $intervention['contract_id'] ?: 'no_contract';
            $isPreventive = $intervention['is_preventive'] == 1 ? 'preventive' : 'corrective';

            if (!isset($groupedInterventions[$contractId])) {
                $groupedInterventions[$contractId] = [
                    'contract_name' => $contractName,
                    'contract_id' => $contractId,
                    'preventive' => [],
                    'corrective' => []
                ];
            }

            $groupedInterventions[$contractId][$isPreventive][] = $intervention;
        }

        return $groupedInterventions;
    }

    /**
     * Récupère les interventions flash qui nécessitent d'être complétées
     */
    public function getFlashInterventionsNeedingCompletion($technicianId = null)
    {
        $sql = "SELECT i.*, 
                   c.name as client_name,
                   s.name as site_name,
                   b.name as building_name,
                   r.name as room_name,
                   st.name as status_name,
                   st.color as status_color,
                   p.name as priority_name,
                   p.color as priority_color,
                   t.name as type_name
            FROM interventions i
            LEFT JOIN clients c ON i.client_id = c.id
            LEFT JOIN sites s ON i.site_id = s.id
            LEFT JOIN buildings b ON i.building_id = b.id
            LEFT JOIN rooms r ON i.room_id = r.id
            LEFT JOIN intervention_statuses st ON i.status_id = st.id
            LEFT JOIN intervention_priorities p ON i.priority_id = p.id
            LEFT JOIN intervention_types t ON i.type_id = t.id
            WHERE i.is_flash = 1 
              AND i.needs_completion = 1
              AND i.status_id != 6";

        if ($technicianId) {
            $sql .= " AND EXISTS (SELECT 1 FROM intervention_techniciens it WHERE it.intervention_id = i.id AND it.technicien_id = :technician_id)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':technician_id' => $technicianId]);
        } else {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marque une intervention flash comme complétée
     */
    public function markFlashAsCompleted($interventionId)
    {
        $sql = "UPDATE interventions SET needs_completion = 0 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $interventionId]);
    }
    /**
     * Calcule le nombre réel de tickets utilisés pour une intervention
     */
    public function calculateRealTicketsUsed($interventionId)
    {
        // Vérifier d'abord si l'intervention est liée à un contrat à tickets
        $sql = "SELECT i.contract_id, c.tickets_number 
            FROM interventions i
            INNER JOIN contracts c ON i.contract_id = c.id
            WHERE i.id = ? AND c.tickets_number IS NOT NULL AND c.tickets_number > 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$interventionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return 0;
        }

        // Compter les tickets utilisés pour cette intervention
        // À adapter selon votre logique métier (par exemple, basé sur la durée)
        $sql_tickets = "SELECT tickets_used as total 
                    FROM interventions 
                    WHERE id = ?";
        $stmt_tickets = $this->db->prepare($sql_tickets);
        $stmt_tickets->execute([$interventionId]);
        $ticketResult = $stmt_tickets->fetch(PDO::FETCH_ASSOC);

        return (int) ($ticketResult['total'] ?? 0);
    }
}