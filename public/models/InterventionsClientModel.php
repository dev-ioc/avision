<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';

/**
 * Modèle pour la gestion des interventions clients
 * Filtre automatiquement selon les localisations autorisées
 */
class InterventionsClientModel extends BaseModel {
    public function __construct($db) {
        parent::__construct($db);
        $this->table = 'interventions';
    }

    /**
     * Récupère toutes les interventions d'un client selon ses localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @param array $filters Filtres supplémentaires
     * @return array Liste des interventions
     */
    public function getAllByLocations($userLocations, $filters = []) {
        // Extraire les IDs des clients auxquels l'utilisateur a accès
        $clientIds = [];
        
        // CORRECTION : getUserLocations() retourne un tableau simple, pas indexé par client_id
        foreach ($userLocations as $location) {
            if (isset($location['client_id']) && !in_array($location['client_id'], $clientIds)) {
                $clientIds[] = (int)$location['client_id'];
            }
        }

        if (empty($clientIds)) {
            return [];
        }

        // Requête SIMPLIFIÉE et SÉCURISÉE : tous les interventions des clients autorisés
        $placeholders = str_repeat('?,', count($clientIds) - 1) . '?';
        
        $sql = "SELECT i.*, 
                c.name as client_name,
                s.name as site_name,
                r.name as room_name,
                u.first_name as technician_first_name,
                u.last_name as technician_last_name,
                its.name as status_name,
                its.color as status_color,
                it.name as type_name,
                it.requires_travel as type_requires_travel,
                ip.name as priority_name,
                ip.color as priority_color
                FROM " . $this->table . " i
                LEFT JOIN clients c ON i.client_id = c.id
                LEFT JOIN sites s ON i.site_id = s.id
                LEFT JOIN rooms r ON i.room_id = r.id
                LEFT JOIN users u ON i.technician_id = u.id
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
        if (!empty($filters['room_id'])) {
            $sql .= " AND i.room_id = ?";
            $params[] = $filters['room_id'];
        }
        if (!empty($filters['status_id'])) {
            $sql .= " AND i.status_id = ?";
            $params[] = $filters['status_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (i.title LIKE ? OR s.name LIKE ? OR r.name LIKE ? OR i.reference LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        if (!empty($filters['exclude_status_ids'])) {
            $placeholders = str_repeat('?,', count($filters['exclude_status_ids']) - 1) . '?';
            $sql .= " AND i.status_id NOT IN ($placeholders)";
            $params = array_merge($params, $filters['exclude_status_ids']);
        }

        // Tri par défaut : date de création décroissante
        $sql .= " ORDER BY i.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère une intervention par son ID avec vérification d'accès
     * @param int $id ID de l'intervention
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array|null L'intervention ou null si pas d'accès
     */
    public function getByIdWithAccess($id, $userLocations) {
        // Extraire les IDs des clients auxquels l'utilisateur a accès
        $clientIds = [];
        
        // CORRECTION : getUserLocations() retourne un tableau simple, pas indexé par client_id
        foreach ($userLocations as $location) {
            if (isset($location['client_id']) && !in_array($location['client_id'], $clientIds)) {
                $clientIds[] = (int)$location['client_id'];
            }
        }

        if (empty($clientIds)) {
            return null;
        }

        // Requête SIMPLIFIÉE et SÉCURISÉE
        $placeholders = str_repeat('?,', count($clientIds) - 1) . '?';
        
        $sql = "SELECT i.*, 
                c.name as client_name,
                s.name as site_name,
                r.name as room_name,
                u.first_name as technician_first_name,
                u.last_name as technician_last_name,
                its.name as status_name,
                its.color as status_color,
                it.name as type_name,
                it.requires_travel as type_requires_travel,
                ip.name as priority_name,
                ip.color as priority_color,
                co.name as contract_name
                FROM " . $this->table . " i
                LEFT JOIN clients c ON i.client_id = c.id
                LEFT JOIN sites s ON i.site_id = s.id
                LEFT JOIN rooms r ON i.room_id = r.id
                LEFT JOIN users u ON i.technician_id = u.id
                LEFT JOIN intervention_statuses its ON i.status_id = its.id
                LEFT JOIN intervention_types it ON i.type_id = it.id
                LEFT JOIN intervention_priorities ip ON i.priority_id = ip.id
                LEFT JOIN contracts co ON i.contract_id = co.id
                WHERE i.id = ? AND i.client_id IN ({$placeholders})";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$id], $clientIds));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques selon les localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Statistiques des interventions
     */
    public function getStatsByLocations($userLocations) {
        // Extraire les IDs des clients auxquels l'utilisateur a accès
        $clientIds = [];
        
        // CORRECTION : getUserLocations() retourne un tableau simple, pas indexé par client_id
        foreach ($userLocations as $location) {
            if (isset($location['client_id']) && !in_array($location['client_id'], $clientIds)) {
                $clientIds[] = (int)$location['client_id'];
            }
        }

        if (empty($clientIds)) {
            return ['total' => 0, 'new_count' => 0, 'in_progress_count' => 0, 'closed_count' => 0];
        }

        // Requête SIMPLIFIÉE et SÉCURISÉE
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
    public function getStatsByStatusAndLocations($userLocations) {
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
    public function getSitesByLocations($userLocations) {
        $locationWhere = buildLocationWhereClause($userLocations, 's.client_id', 's.id', 'r.id');
        
        $sql = "SELECT DISTINCT s.* 
                FROM sites s
                LEFT JOIN rooms r ON s.id = r.site_id
                WHERE {$locationWhere} AND s.status = 1
                ORDER BY s.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les salles d'un site selon les localisations autorisées
     * @param int $siteId ID du site
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des salles
     */
    public function getRoomsBySiteAndLocations($siteId, $userLocations) {
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
     * Récupère les interventions récentes selon les localisations autorisées
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @param int $limit Nombre d'interventions à récupérer
     * @return array Liste des interventions récentes
     */
    public function getRecentByLocations($userLocations, $limit = 10) {
        $locationWhere = buildLocationWhereClause($userLocations, 'i.client_id', 'i.site_id', 'i.room_id');
        
        $sql = "SELECT i.*, 
                c.name as client_name,
                s.name as site_name,
                r.name as room_name,
                its.name as status_name,
                its.color as status_color
                FROM " . $this->table . " i
                LEFT JOIN clients c ON i.client_id = c.id
                LEFT JOIN sites s ON i.site_id = s.id
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
    public function getCommentsWithAccess($interventionId, $userLocations, $isClient = false, $userId = null) {
        $locationWhere = buildLocationWhereClause($userLocations, 'i.client_id', 'i.site_id', 'i.room_id');
        
        // Si c'est un client, filtrer les commentaires visibles
        $visibilityFilter = "";
        if ($isClient) {
            $currentUserId = $userId ?? ($_SESSION['user']['id'] ?? 0);
            $visibilityFilter = "AND (ic.visible_by_client = 1 OR ic.created_by = {$currentUserId})";
        }
        
        $sql = "SELECT ic.*, u.first_name, u.last_name, u.user_type_id,
                CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM intervention_comments ic
                JOIN " . $this->table . " i ON ic.intervention_id = i.id
                LEFT JOIN users u ON ic.created_by = u.id
                WHERE ic.intervention_id = ? AND {$locationWhere} {$visibilityFilter}
                ORDER BY ic.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$interventionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les pièces jointes d'une intervention avec vérification d'accès
     * @param int $interventionId ID de l'intervention
     * @param array $userLocations Les localisations autorisées de l'utilisateur
     * @return array Liste des pièces jointes
     */
    public function getAttachmentsWithAccess($interventionId, $userLocations) {
        $locationWhere = buildLocationWhereClause($userLocations, 'i.client_id', 'i.site_id', 'i.room_id');
        
        $sql = "SELECT pj.*, st.setting_value as type_nom,
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                lpj.type_liaison
                FROM pieces_jointes pj
                LEFT JOIN settings st ON pj.type_id = st.id
                LEFT JOIN users u ON pj.created_by = u.id
                INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                JOIN " . $this->table . " i ON lpj.entite_id = i.id
                WHERE (lpj.type_liaison = 'intervention' OR lpj.type_liaison = 'bi')
                AND lpj.entite_id = ? AND {$locationWhere}
                AND pj.masque_client = 0
                ORDER BY pj.date_creation DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$interventionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajouter un commentaire
     * @param int $interventionId ID de l'intervention
     * @param int $userId ID de l'utilisateur
     * @param string $comment Contenu du commentaire
     * @param bool $isClient Si l'utilisateur est un client (pour auto-marquer comme visible)
     * @return bool Succès de l'opération
     */
    public function addComment($interventionId, $userId, $comment, $isClient = false) {
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
    public function getCommentById($commentId) {
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
    public function updateComment($commentId, $comment) {
        try {
            // Vérifier si la colonne updated_at existe, sinon ne pas l'utiliser
            $sql = "UPDATE intervention_comments SET comment = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$comment, $commentId]);
            
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                custom_log("Erreur SQL lors de la modification du commentaire ID {$commentId}: " . print_r($errorInfo, true), 'ERROR');
                return false;
            }
            
            // Si la colonne updated_at existe, la mettre à jour
            try {
                $sqlUpdate = "UPDATE intervention_comments SET updated_at = NOW() WHERE id = ?";
                $stmtUpdate = $this->db->prepare($sqlUpdate);
                $stmtUpdate->execute([$commentId]);
            } catch (Exception $e) {
                // La colonne updated_at n'existe peut-être pas, ce n'est pas grave
                custom_log("Note: colonne updated_at non disponible pour intervention_comments", 'DEBUG');
            }
            
            return true;
        } catch (Exception $e) {
            custom_log("Erreur lors de la modification du commentaire ID {$commentId}: " . $e->getMessage(), 'ERROR');
            custom_log("Stack trace: " . $e->getTraceAsString(), 'ERROR');
            return false;
        }
    }

    /**
     * Supprimer un commentaire
     * @param int $commentId ID du commentaire
     * @return bool Succès de l'opération
     */
    public function deleteComment($commentId) {
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
     * Ajouter une pièce jointe
     * @param int $interventionId ID de l'intervention
     * @param int $userId ID de l'utilisateur
     * @param array $file Fichier uploadé
     * @return bool Succès de l'opération
     */
    public function addAttachment($interventionId, $userId, $file, $customName = null) {
        try {
            $this->db->beginTransaction();

            // Créer le dossier de destination s'il n'existe pas
            $uploadDir = 'uploads/interventions/' . $interventionId . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Générer un nom de fichier unique
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $filepath = $uploadDir . $filename;

            // Déplacer le fichier
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Insérer la pièce jointe
                $sql = "INSERT INTO pieces_jointes (
                            nom_fichier, nom_personnalise, chemin_fichier, type_fichier, taille_fichier, 
                            commentaire, masque_client, created_by
                        ) VALUES (
                            :nom_fichier, :nom_personnalise, :chemin_fichier, :type_fichier, :taille_fichier,
                            :commentaire, :masque_client, :created_by
                        )";

                // Utiliser le nom personnalisé s'il existe, sinon le nom original
                $displayName = $customName ?: $file['name'];
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':nom_fichier' => $filename, // Nom physique du fichier
                    ':nom_personnalise' => $displayName, // Nom d'affichage
                    ':chemin_fichier' => $filepath,
                    ':type_fichier' => $extension,
                    ':taille_fichier' => $file['size'],
                    ':commentaire' => null,
                    ':masque_client' => 0, // Visible par les clients
                    ':created_by' => $userId
                ]);

                $pieceJointeId = $this->db->lastInsertId();

                // Créer la liaison
                $sql = "INSERT INTO liaisons_pieces_jointes (
                            piece_jointe_id, type_liaison, entite_id
                        ) VALUES (
                            :piece_jointe_id, 'intervention', :intervention_id
                        )";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':piece_jointe_id' => $pieceJointeId,
                    ':intervention_id' => $interventionId
                ]);

                $this->db->commit();
                return true;
            }
            
            $this->db->rollBack();
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            custom_log("Erreur lors de l'ajout de la pièce jointe: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Récupérer une pièce jointe par son ID
     * @param int $attachmentId ID de la pièce jointe
     * @return array|null La pièce jointe ou null
     */
    public function getAttachmentById($attachmentId) {
        try {
            $sql = "SELECT pj.*, lpj.type_liaison, lpj.entite_id as intervention_id,
                    i.client_id, i.site_id, i.room_id
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
     * Supprimer une pièce jointe
     * @param int $attachmentId ID de la pièce jointe
     * @return bool Succès de l'opération
     */
    public function deleteAttachment($attachmentId) {
        try {
            $this->db->beginTransaction();

            // Récupérer les informations de la pièce jointe
            $attachment = $this->getAttachmentById($attachmentId);
            if (!$attachment) {
                $this->db->rollBack();
                return false;
            }

            // Supprimer le fichier physique
            $filePath = __DIR__ . '/../' . $attachment['chemin_fichier'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Supprimer la liaison
            $sql = "DELETE FROM liaisons_pieces_jointes 
                     WHERE piece_jointe_id = :piece_jointe_id 
                     AND type_liaison = 'intervention' 
                     AND entite_id = :intervention_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':piece_jointe_id' => $attachmentId,
                ':intervention_id' => $attachment['intervention_id']
            ]);

            // Supprimer la pièce jointe
            $sql = "DELETE FROM pieces_jointes WHERE id = :piece_jointe_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':piece_jointe_id' => $attachmentId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            custom_log("Erreur lors de la suppression de la pièce jointe: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Récupère tous les statuts d'intervention
     * @return array Liste des statuts
     */
    public function getAllStatuses() {
        $sql = "SELECT id, name, color, is_critical, created_at FROM intervention_statuses ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère toutes les priorités d'intervention
     * @return array Liste des priorités
     */
    public function getAllPriorities() {
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
    public function getContractsByClient($clientId) {
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
    public function getContactsByClient($clientId) {
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
    public function create($data) {
        try {
            $this->db->beginTransaction();

            // Générer une référence unique si elle n'existe pas
            if (empty($data['reference'])) {
                $data['reference'] = $this->generateReference();
            }

            $sql = "INSERT INTO interventions (
                        reference, title, description, demande_par, client_id, site_id, room_id, 
                        contract_id, type_id, status_id, priority_id, duration, 
                        date_planif, heure_planif, technician_id, ref_client, contact_client, created_at
                    ) VALUES (
                        :reference, :title, :description, :demande_par, :client_id, :site_id, :room_id,
                        :contract_id, :type_id, :status_id, :priority_id, :duration,
                        :date_planif, :heure_planif, :technician_id, :ref_client, :contact_client, NOW()
                    )";

            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute([
                ':reference' => $data['reference'],
                ':title' => $data['title'],
                ':description' => $data['description'],
                ':demande_par' => $data['demande_par'],
                ':client_id' => $data['client_id'],
                ':site_id' => $data['site_id'],
                ':room_id' => $data['room_id'],
                ':contract_id' => $data['contract_id'],
                ':type_id' => $data['type_id'],
                ':status_id' => $data['status_id'],
                ':priority_id' => $data['priority_id'],
                ':duration' => $data['duration'],
                ':date_planif' => $data['date_planif'],
                ':heure_planif' => $data['heure_planif'],
                ':technician_id' => $data['technician_id'],
                ':ref_client' => $data['ref_client'],
                ':contact_client' => $data['contact_client']
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
    private function generateReference() {
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
            $number = (int)end($parts) + 1;
        } else {
            $number = 1;
        }
        
        return sprintf("INT-%s-%06d", $year, $number);
    }
} 