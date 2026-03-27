<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Models/BaseModel.php';

/**
 * Modèle pour la gestion des interventions
 */
class InterventionModel extends BaseModel {
    public function __construct($db) {
        parent::__construct($db);
        $this->table = 'interventions';
    }

    /**
     * Récupère toutes les interventions avec filtres
     */
    public function getAll($filters = []) {
        $sql = "SELECT i.*, 
                c.name as client_name,
                s.name as site_name,
                r.name as room_name,
                u.first_name as technician_first_name,
                u.last_name as technician_last_name,
                u.email as technician_email,
                its.name as status_name,
                its.color as status_color,
                it.name as type_name,
                it.requires_travel as type_requires_travel_default,
                COALESCE(i.type_requires_travel, it.requires_travel) as type_requires_travel,
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
        if (!empty($filters['technician_id'])) {
            $sql .= " AND i.technician_id = ?";
            $params[] = $filters['technician_id'];
        }

        // Tri par défaut : date de création décroissante
        $sql .= " ORDER BY i.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ajouter le nom complet du technicien pour chaque intervention
        foreach ($results as &$result) {
            if (!empty($result['technician_first_name']) && !empty($result['technician_last_name'])) {
                $result['technician_name'] = $result['technician_first_name'] . ' ' . $result['technician_last_name'];
            } else {
                $result['technician_name'] = null;
            }
        }
        
        return $results;
    }

    /**
     * Récupère une intervention par son ID
     */
    public function getById($id) {
        $sql = "SELECT i.*, 
                c.name as client_name,
                s.name as site_name,
                s.address as site_address,
                s.postal_code as site_postal_code,
                s.city as site_city,
                s.phone as site_phone,
                s.email as site_email,
                r.name as room_name,
                u.first_name as technician_first_name,
                u.last_name as technician_last_name,
                its.name as status_name,
                its.color as status_color,
                it.name as type_name,
                it.requires_travel as type_requires_travel_default,
                COALESCE(i.type_requires_travel, it.requires_travel) as type_requires_travel,
                ip.name as priority_name,
                ip.color as priority_color,
                co.name as contract_name,
                co.contract_type_id,
                ct.name as contract_type_name,
                cont.first_name as contact_first_name,
                cont.last_name as contact_last_name,
                cont.phone1 as contact_phone
                FROM " . $this->table . " i
                LEFT JOIN clients c ON i.client_id = c.id
                LEFT JOIN sites s ON i.site_id = s.id
                LEFT JOIN rooms r ON i.room_id = r.id
                LEFT JOIN users u ON i.technician_id = u.id
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
        
        // Créer le nom complet du technicien
        if ($result && !empty($result['technician_first_name']) && !empty($result['technician_last_name'])) {
            $result['technician_name'] = $result['technician_first_name'] . ' ' . $result['technician_last_name'];
        } else {
            $result['technician_name'] = null;
        }
        
        // Code de débogage temporaire
        error_log("DEBUG - InterventionModel::getById($id) - Résultat SQL: " . print_r($result, true));
        error_log("DEBUG - site_id existe? " . (array_key_exists('site_id', $result) ? 'OUI' : 'NON'));
        error_log("DEBUG - site_id valeur: " . ($result['site_id'] ?? 'NULL'));
        
        // Débogage spécifique pour les champs date_planif et heure_planif
        error_log("DEBUG - date_planif existe? " . (array_key_exists('date_planif', $result) ? 'OUI' : 'NON'));
        error_log("DEBUG - date_planif valeur: " . ($result['date_planif'] ?? 'NULL'));
        error_log("DEBUG - heure_planif existe? " . (array_key_exists('heure_planif', $result) ? 'OUI' : 'NON'));
        error_log("DEBUG - heure_planif valeur: " . ($result['heure_planif'] ?? 'NULL'));
        
        return $result;
    }

    /**
     * Récupère les statistiques des interventions
     */
    public function getStats($filters = []) {
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
        if (!empty($filters['technician_id'])) {
            $sql .= " AND i.technician_id = ?";
            $params[] = $filters['technician_id'];
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
    public function getStatsByStatus($filters = []) {
        $sql = "SELECT 
                its.id,
                its.name,
                its.color,
                COUNT(i.id) as count
                FROM intervention_statuses its
                LEFT JOIN " . $this->table . " i ON its.id = i.status_id";

        $whereConditions = [];
        $params = [];

        // Appliquer les filtres (mais pas le filtre de statut pour garder tous les statuts visibles)
        if (!empty($filters['client_id'])) {
            $whereConditions[] = "i.client_id = ?";
            $params[] = $filters['client_id'];
        }
        if (!empty($filters['site_id'])) {
            $whereConditions[] = "i.site_id = ?";
            $params[] = $filters['site_id'];
        }
        if (!empty($filters['room_id'])) {
            $whereConditions[] = "i.room_id = ?";
            $params[] = $filters['room_id'];
        }
        // Note: On ne met pas le filtre status_id ici pour garder tous les statuts visibles
        if (!empty($filters['priority_id'])) {
            $whereConditions[] = "i.priority_id = ?";
            $params[] = $filters['priority_id'];
        }
        if (!empty($filters['exclude_priority_ids'])) {
            $placeholders = str_repeat('?,', count($filters['exclude_priority_ids']) - 1) . '?';
            $whereConditions[] = "i.priority_id NOT IN ($placeholders)";
            $params = array_merge($params, $filters['exclude_priority_ids']);
        }
        if (!empty($filters['technician_id'])) {
            $whereConditions[] = "i.technician_id = ?";
            $params[] = $filters['technician_id'];
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
     * @param int $id L'ID de l'intervention à mettre à jour
     * @param array $data Les données à mettre à jour
     * @return bool True si la mise à jour a réussi, false sinon
     */
    public function update($id, $data) {
        // Construire la requête SQL
        $updates = [];
        $params = [];
        
        // Ajouter les champs à mettre à jour
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
        
        if (isset($data['room_id'])) {
            $updates[] = "room_id = :room_id";
            $params[':room_id'] = empty($data['room_id']) ? null : $data['room_id'];
        }
        
        if (isset($data['technician_id'])) {
            $updates[] = "technician_id = :technician_id";
            $params[':technician_id'] = empty($data['technician_id']) ? null : $data['technician_id'];
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
        
        if (isset($data['duration'])) {
            $updates[] = "duration = :duration";
            $params[':duration'] = $data['duration'];
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
        
        if (array_key_exists('date_planif', $data)) {
            $updates[] = "date_planif = :date_planif";
            $params[':date_planif'] = $data['date_planif'];
        }
        
        if (array_key_exists('heure_planif', $data)) {
            $updates[] = "heure_planif = :heure_planif";
            $params[':heure_planif'] = $data['heure_planif'];
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
        
        // Si aucun champ à mettre à jour, retourner false
        if (empty($updates)) {
            return false;
        }
        
        // Ajouter l'ID à la requête
        $params[':id'] = $id;
        
        // Construire la requête SQL
        $sql = "UPDATE " . $this->table . " SET " . implode(", ", $updates) . " WHERE id = :id";
        
        // Log de debug
        custom_log("SQL Update Intervention: " . $sql, "DEBUG", [
            'params' => $params,
            'updates' => $updates
        ]);
        
        // Exécuter la requête
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);
        
        if (!$result) {
            custom_log("Erreur SQL Update Intervention: " . implode(", ", $stmt->errorInfo()), "ERROR");
        }
        
        return $result;
    }

    /**
     * Récupère les informations d'un type d'intervention
     * @param int $typeId ID du type d'intervention
     * @return array|null Les informations du type d'intervention ou null si non trouvé
     */
    public function getTypeInfo($typeId) {
        $sql = "SELECT id, name, requires_travel, created_at FROM intervention_types WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$typeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère tous les types d'intervention
     * @return array Liste des types d'intervention
     */
    public function getAllTypes() {
        $sql = "SELECT id, name, requires_travel, created_at FROM intervention_types ORDER BY name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Génère une référence unique pour une intervention
     * Format : #VS{client_id}{année}-{numéro aléatoire}
     * Exemple : #VS2524-1234
     * 
     * @param int $clientId L'ID du client
     * @return string La référence générée
     */
    public function generateReference($clientId) {
        try {
            // Récupérer l'année courante
            $year = date('y');
            
            // Récupérer toutes les références existantes pour ce client et cette année
            $sql = "SELECT reference FROM interventions 
                    WHERE client_id = ? 
                    AND reference LIKE ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$clientId, "#VS{$clientId}{$year}-%"]);
            $existingReferences = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Générer un numéro aléatoire unique
            $maxAttempts = 100; // Limite de tentatives pour éviter une boucle infinie
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
    public function create($data) {
        try {
            // Générer la référence
            $reference = $this->generateReference($data['client_id']);
            if (!$reference) {
                return false;
            }

            $sql = "INSERT INTO interventions (
                        reference, title, client_id, site_id, room_id, 
                        technician_id, status_id, priority_id, type_id, 
                        duration, description, demande_par, ref_client, contact_client, 
                        contract_id, date_planif, heure_planif, type_requires_travel
                    ) VALUES (
                        :reference, :title, :client_id, :site_id, :room_id, 
                        :technician_id, :status_id, :priority_id, :type_id, 
                        :duration, :description, :demande_par, :ref_client, :contact_client, 
                        :contract_id, :date_planif, :heure_planif, :type_requires_travel
                    )";
            
            $stmt = $this->db->prepare($sql);
            $data['reference'] = $reference;
            
            // S'assurer que les champs date_planif et heure_planif existent dans le tableau de données
            if (!isset($data['date_planif'])) {
                $data['date_planif'] = null;
            }
            if (!isset($data['heure_planif'])) {
                $data['heure_planif'] = null;
            }
            
            // S'assurer que les nouveaux champs existent dans le tableau de données
            if (!isset($data['demande_par'])) {
                $data['demande_par'] = null;
            }
            if (!isset($data['ref_client'])) {
                $data['ref_client'] = null;
            }
            if (!isset($data['contact_client'])) {
                $data['contact_client'] = null;
            }
            if (!isset($data['type_requires_travel'])) {
                $data['type_requires_travel'] = 0;
            }
            
            return $stmt->execute($data);
        } catch (PDOException $e) {
            custom_log("Erreur lors de la création de l'intervention : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Récupère les pièces jointes d'une intervention (intervention + bi)
     * @param int $interventionId ID de l'intervention
     * @return array Liste des pièces jointes
     */
    public function getPiecesJointes($interventionId) {
        $query = "
            SELECT 
                pj.*,
                st.setting_value as type_nom,
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                lpj.type_liaison,
                lpj.pour_bon_intervention
            FROM pieces_jointes pj
            LEFT JOIN settings st ON pj.type_id = st.id
            LEFT JOIN users u ON pj.created_by = u.id
            INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
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
     * 
     * @param int $interventionId ID de l'intervention
     * @param array $data Données de la pièce jointe
     * @return int ID de la pièce jointe créée
     */
    public function addPieceJointe($interventionId, $data) {
        try {
            $this->db->beginTransaction();

            // Insérer la pièce jointe
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

            // Créer la liaison
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
     * Récupère uniquement les bons d'intervention d'une intervention
     * @param int $interventionId ID de l'intervention
     * @return array Liste des bons d'intervention
     */
    public function getBonsIntervention($interventionId) {
        $query = "
            SELECT 
                pj.*,
                st.setting_value as type_nom,
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                lpj.type_liaison
            FROM pieces_jointes pj
            LEFT JOIN settings st ON pj.type_id = st.id
            LEFT JOIN users u ON pj.created_by = u.id
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
     * Supprime une pièce jointe d'une intervention
     * 
     * @param int $pieceJointeId ID de la pièce jointe
     * @param int $interventionId ID de l'intervention (pour vérification)
     * @return bool Succès de la suppression
     */
    public function deletePieceJointe($pieceJointeId, $interventionId) {
        try {
            $this->db->beginTransaction();

            // Vérifier que la pièce jointe appartient bien à l'intervention
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

            // Supprimer le fichier physique
            $filePath = __DIR__ . '/../' . $pieceJointe['chemin_fichier'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Supprimer la liaison
            $query = "DELETE FROM liaisons_pieces_jointes 
                     WHERE piece_jointe_id = :piece_jointe_id 
                     AND (type_liaison = 'intervention' OR type_liaison = 'bi')
                     AND entite_id = :intervention_id";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':piece_jointe_id' => $pieceJointeId,
                ':intervention_id' => $interventionId
            ]);

            // Supprimer la pièce jointe
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
     * 
     * @param int $pieceJointeId ID de la pièce jointe
     * @return array|null La pièce jointe ou null
     */
    public function getPieceJointeById($pieceJointeId) {
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
     * 
     * @param int $pieceJointeId ID de la pièce jointe
     * @param int $masqueClient Nouvelle valeur de visibilité
     * @return bool Succès de la mise à jour
     */
    public function updatePieceJointeVisibility($pieceJointeId, $masqueClient) {
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
     * 
     * @param int $pieceJointeId ID de la pièce jointe
     * @param string $newName Nouveau nom
     * @return bool Succès de l'opération
     */
    public function updateAttachmentName($pieceJointeId, $newName) {
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
     * 
     * @param int $interventionId ID de l'intervention
     * @param array $selectedCommentIds IDs des commentaires sélectionnés
     * @return bool Succès de l'opération
     */
    public function updateCommentsForBon($interventionId, $selectedCommentIds) {
        try {
            $this->db->beginTransaction();

            // D'abord, désélectionner tous les commentaires de cette intervention
            $query = "UPDATE intervention_comments 
                     SET pour_bon_intervention = 0 
                     WHERE intervention_id = :intervention_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':intervention_id' => $interventionId]);

            // Ensuite, sélectionner les commentaires spécifiés
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
     * 
     * @param int $interventionId ID de l'intervention
     * @param array $selectedAttachmentIds IDs des pièces jointes sélectionnées
     * @return bool Succès de l'opération
     */
    public function updateAttachmentsForBon($interventionId, $selectedAttachmentIds) {
        try {
            $this->db->beginTransaction();

            // D'abord, désélectionner toutes les pièces jointes de cette intervention
            $query = "UPDATE liaisons_pieces_jointes 
                     SET pour_bon_intervention = 0 
                     WHERE entite_id = ? AND (type_liaison = 'intervention' OR type_liaison = 'bi')";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$interventionId]);

            // Ensuite, sélectionner les pièces jointes spécifiées
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
     * 
     * @param int $interventionId ID de l'intervention
     * @param array $data Données de la pièce jointe
     * @param string $typeLiaison Type de liaison ('intervention', 'bi', etc.)
     * @return int ID de la pièce jointe créée
     */
    public function addPieceJointeWithType($interventionId, $data, $typeLiaison) {
        try {
            $this->db->beginTransaction();

            // Insérer la pièce jointe
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

            // Créer la liaison avec le type spécifié
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
    public function getScheduledInterventions($filters = []) {
        $sql = "SELECT i.*, 
                c.name as client_name,
                s.name as site_name,
                r.name as room_name,
                u.first_name as technician_first_name,
                u.last_name as technician_last_name,
                its.name as status_name,
                its.color as status_color,
                it.name as type_name,
                it.requires_travel as type_requires_travel_default,
                COALESCE(i.type_requires_travel, it.requires_travel) as type_requires_travel,
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
                WHERE i.date_planif IS NOT NULL AND i.date_planif > '1900-01-01'";

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
        if (!empty($filters['technician_id'])) {
            $sql .= " AND i.technician_id = ?";
            $params[] = $filters['technician_id'];
        }
        
        // Filtre par technicien (nouveau système)
        if (!empty($filters['technician_filter'])) {
            $technicianFilter = $filters['technician_filter'];
            $conditions = [];
            
            if (!empty($technicianFilter['technician_ids'])) {
                $placeholders = str_repeat('?,', count($technicianFilter['technician_ids']) - 1) . '?';
                $conditions[] = "i.technician_id IN ($placeholders)";
                $params = array_merge($params, $technicianFilter['technician_ids']);
            }
            
            if ($technicianFilter['show_unassigned']) {
                $conditions[] = "i.technician_id IS NULL";
            }
            
            if (!empty($conditions)) {
                $sql .= " AND (" . implode(" OR ", $conditions) . ")";
            }
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND i.date_planif >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND i.date_planif <= ?";
            $params[] = $filters['date_to'];
        }

        // Tri par date planifiée puis par heure
        $sql .= " ORDER BY i.date_planif ASC, i.heure_planif ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ajouter le nom complet du technicien pour chaque intervention
        foreach ($results as &$result) {
            if (!empty($result['technician_first_name']) && !empty($result['technician_last_name'])) {
                $result['technician_name'] = $result['technician_first_name'] . ' ' . $result['technician_last_name'];
            } else {
                $result['technician_name'] = null;
            }
        }
        
        return $results;
    }

    /**
     * Récupère les commentaires solution d'une intervention (sans observations)
     * @param int $interventionId ID de l'intervention
     * @return array Liste des commentaires solution uniquement
     */
    public function getSolutionComments($interventionId) {
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
     * @param int $interventionId ID de l'intervention
     * @return array Liste des commentaires sélectionnés pour le bon
     */
    public function getCommentsForBon($interventionId) {
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
     * Récupère les interventions d'un client groupées par contrat et par type (préventif/non-préventif)
     * @param int $clientId ID du client
     * @return array Interventions groupées par contrat et par type
     */
    public function getInterventionsByClientGrouped($clientId) {
        $sql = "SELECT i.*, 
                c.name as client_name,
                s.name as site_name,
                r.name as room_name,
                u.first_name as technician_first_name,
                u.last_name as technician_last_name,
                its.name as status_name,
                its.color as status_color,
                it.name as type_name,
                it.requires_travel as type_requires_travel_default,
                COALESCE(i.type_requires_travel, it.requires_travel) as type_requires_travel,
                ip.name as priority_name,
                ip.color as priority_color,
                co.name as contract_name,
                co.id as contract_id,
                ct.name as contract_type_name
                FROM " . $this->table . " i
                LEFT JOIN clients c ON i.client_id = c.id
                LEFT JOIN sites s ON i.site_id = s.id
                LEFT JOIN rooms r ON i.room_id = r.id
                LEFT JOIN users u ON i.technician_id = u.id
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
        
        // Grouper les interventions par contrat et par type préventif
        $groupedInterventions = [];
        
        foreach ($interventions as $intervention) {
            $contractName = $intervention['contract_name'] ?: 'Sans contrat';
            $contractId = $intervention['contract_id'] ?: 'no_contract';
            
            // Déterminer si l'intervention est préventive basé sur la priorité
            $priorityName = strtolower($intervention['priority_name'] ?? '');
            $isPreventive = (strpos($priorityName, 'préventif') !== false || 
                           strpos($priorityName, 'preventif') !== false) ? 'preventive' : 'corrective';
            
            // Ajouter le nom complet du technicien
            if (!empty($intervention['technician_first_name']) && !empty($intervention['technician_last_name'])) {
                $intervention['technician_name'] = $intervention['technician_first_name'] . ' ' . $intervention['technician_last_name'];
            } else {
                $intervention['technician_name'] = null;
            }
            
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
} 