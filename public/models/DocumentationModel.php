<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';

class DocumentationModel extends BaseModel
{
    public function __construct($db)
    {
        parent::__construct($db);
        $this->table = 'pieces_jointes'; // La documentation utilise la table pieces_jointes
    }

    /**
     * Récupère tous les documents avec filtres (version corrigée)
     */
    public function getAllDocuments($clientId = null, $siteId = null, $buildingId = null, $roomId = null)
    {
        $query = "
            SELECT 
                pj.*,
                COALESCE(pj.content, pj.commentaire) as description,
                c.name as client_name,
                c.id as client_id,
                s.name as site_name,
                s.id as site_id,
                b.name as building_name,
                b.id as building_id,
                r.name as room_name,
                r.id as room_id,
                u.username as uploader_name,
                u.first_name as user_first_name,
                u.last_name as user_last_name
            FROM pieces_jointes pj
            INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
            LEFT JOIN clients c ON (lpj.type_liaison = 'documentation_client' AND lpj.entite_id = c.id)
            LEFT JOIN sites s ON (lpj.type_liaison = 'documentation_site' AND lpj.entite_id = s.id)
            LEFT JOIN rooms r ON (lpj.type_liaison = 'documentation_room' AND lpj.entite_id = r.id)
            LEFT JOIN buildings b ON r.building_id = b.id
            LEFT JOIN users u ON pj.created_by = u.id
            WHERE lpj.type_liaison IN ('documentation_client', 'documentation_site', 'documentation_room')
        ";

        $params = [];

        if ($clientId) {
            $query .= " AND (c.id = ? OR s.client_id = ? OR b.site_id IN (SELECT id FROM sites WHERE client_id = ?))";
            $params[] = $clientId;
            $params[] = $clientId;
            $params[] = $clientId;
        }

        if ($siteId) {
            $query .= " AND (s.id = ? OR b.site_id = ?)";
            $params[] = $siteId;
            $params[] = $siteId;
        }

        if ($buildingId) {
            $query .= " AND b.id = ?";
            $params[] = $buildingId;
        }

        if ($roomId) {
            $query .= " AND r.id = ?";
            $params[] = $roomId;
        }

        $query .= " ORDER BY c.name, s.name, b.name, r.name, pj.date_creation DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les documents d'un utilisateur spécifique
     */
    public function getUserDocuments($userId)
    {
        $query = "
            SELECT 
                pj.*,
                c.name as client_name,
                s.name as site_name,
                b.name as building_name,
                r.name as room_name
            FROM pieces_jointes pj
            INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
            LEFT JOIN clients c ON (lpj.type_liaison = 'documentation_client' AND lpj.entite_id = c.id)
            LEFT JOIN sites s ON (lpj.type_liaison = 'documentation_site' AND lpj.entite_id = s.id)
            LEFT JOIN rooms r ON (lpj.type_liaison = 'documentation_room' AND lpj.entite_id = r.id)
            LEFT JOIN buildings b ON r.building_id = b.id
            WHERE pj.created_by = :user_id
            ORDER BY pj.date_creation DESC
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les documents d'un client (version simplifiée)
     */
    public function getClientDocuments($clientId)
    {
        return $this->getAllDocuments($clientId, null, null, null);
    }

    /**
     * Ajoute un nouveau document (dans pieces_jointes et liaison)
     */
    public function addDocument($data)
    {
        try {
            $this->db->beginTransaction();

            // Insérer dans pieces_jointes
            $query = "INSERT INTO pieces_jointes (
                        nom_fichier, chemin_fichier, type_fichier, taille_fichier, 
                        category_id, content, masque_client, created_by, date_creation, commentaire
                    ) VALUES (
                        :nom_fichier, :chemin_fichier, :type_fichier, :taille_fichier,
                        :category_id, :content, :masque_client, :created_by, NOW(), :commentaire
                    )";

            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':nom_fichier' => $data['title'],
                ':chemin_fichier' => $data['attachment_path'],
                ':type_fichier' => $data['type_fichier'] ?? null,
                ':taille_fichier' => $data['taille_fichier'] ?? 0,
                ':category_id' => $data['category_id'],
                ':content' => $data['content'] ?? null,
                ':masque_client' => $data['visible_by_client'] ?? 0,
                ':created_by' => $data['created_by'],
                ':commentaire' => $data['description'] ?? null
            ]);

            if (!$result) {
                throw new Exception("Erreur lors de l'insertion du document");
            }

            $pieceJointeId = $this->db->lastInsertId();

            // Déterminer le type de liaison
            if (!empty($data['room_id'])) {
                $typeLiaison = 'documentation_room';
                $entiteId = $data['room_id'];
            } elseif (!empty($data['site_id'])) {
                $typeLiaison = 'documentation_site';
                $entiteId = $data['site_id'];
            } else {
                $typeLiaison = 'documentation_client';
                $entiteId = $data['client_id'];
            }

            // Créer la liaison
            $linkQuery = "INSERT INTO liaisons_pieces_jointes (piece_jointe_id, type_liaison, entite_id) 
                         VALUES (:piece_jointe_id, :type_liaison, :entite_id)";
            $linkStmt = $this->db->prepare($linkQuery);
            $linkStmt->execute([
                ':piece_jointe_id' => $pieceJointeId,
                ':type_liaison' => $typeLiaison,
                ':entite_id' => $entiteId
            ]);

            $this->db->commit();
            return $pieceJointeId;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("[ERROR] DocumentationModel::addDocument - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprime un document (soft delete)
     */
    public function deleteDocument($documentId)
    {
        try {
            $this->db->beginTransaction();

            // Supprimer la liaison
            $query = "DELETE FROM liaisons_pieces_jointes WHERE piece_jointe_id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $documentId]);

            // Supprimer la pièce jointe
            $query = "DELETE FROM pieces_jointes WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([':id' => $documentId]);

            $this->db->commit();
            return $result;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("[ERROR] DocumentationModel::deleteDocument - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les statistiques des documents par niveau
     */
    public function getDocumentStats()
    {
        $query = "
            SELECT 
                c.id as client_id,
                c.name as client_name,
                s.id as site_id,
                s.name as site_name,
                b.id as building_id,
                b.name as building_name,
                r.id as room_id,
                r.name as room_name,
                COUNT(pj.id) as doc_count
            FROM clients c
            LEFT JOIN sites s ON s.client_id = c.id
            LEFT JOIN buildings b ON b.site_id = s.id
            LEFT JOIN rooms r ON r.building_id = b.id
            LEFT JOIN pieces_jointes pj ON pj.id IN (
                SELECT piece_jointe_id FROM liaisons_pieces_jointes 
                WHERE (type_liaison = 'documentation_client' AND entite_id = c.id)
                   OR (type_liaison = 'documentation_site' AND entite_id = s.id)
                   OR (type_liaison = 'documentation_room' AND entite_id = r.id)
            )
            WHERE c.status = 1
            GROUP BY c.id, s.id, b.id, r.id
            ORDER BY c.name, s.name, b.name, r.name
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un document spécifique par son ID
     */
    public function getDocumentById($documentId)
    {
        $query = "
            SELECT 
                pj.*,
                COALESCE(pj.content, pj.commentaire) as description,
                c.id as client_id,
                c.name as client_name,
                s.id as site_id,
                s.name as site_name,
                b.id as building_id,
                b.name as building_name,
                r.id as room_id,
                r.name as room_name,
                u.first_name as author_first_name,
                u.last_name as author_last_name,
                u.username as uploader_name
            FROM pieces_jointes pj
            INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
            LEFT JOIN clients c ON (lpj.type_liaison = 'documentation_client' AND lpj.entite_id = c.id)
            LEFT JOIN sites s ON (lpj.type_liaison = 'documentation_site' AND lpj.entite_id = s.id)
            LEFT JOIN rooms r ON (lpj.type_liaison = 'documentation_room' AND lpj.entite_id = r.id)
            LEFT JOIN buildings b ON r.building_id = b.id
            LEFT JOIN users u ON pj.created_by = u.id
            WHERE pj.id = :id
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $documentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Met à jour un document existant
     */
    public function updateDocument($id, $data)
    {
        try {
            $this->db->beginTransaction();

            // Mettre à jour la pièce jointe
            $query = "UPDATE pieces_jointes SET 
                        nom_fichier = :nom_fichier,
                        chemin_fichier = :chemin_fichier,
                        type_fichier = :type_fichier,
                        taille_fichier = :taille_fichier,
                        category_id = :category_id,
                        content = :content,
                        masque_client = :masque_client,
                        commentaire = :commentaire,
                        updated_at = NOW()
                      WHERE id = :id";

            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':id' => $id,
                ':nom_fichier' => $data['title'],
                ':chemin_fichier' => $data['attachment_path'],
                ':type_fichier' => $data['type_fichier'] ?? null,
                ':taille_fichier' => $data['taille_fichier'] ?? 0,
                ':category_id' => $data['category_id'],
                ':content' => $data['content'] ?? null,
                ':masque_client' => $data['visible_by_client'] ?? 0,
                ':commentaire' => $data['description'] ?? null
            ]);

            if (!$result) {
                throw new Exception("Erreur lors de la mise à jour du document");
            }

            // Mettre à jour la liaison
            $query = "DELETE FROM liaisons_pieces_jointes WHERE piece_jointe_id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $id]);

            // Déterminer le nouveau type de liaison
            if (!empty($data['room_id'])) {
                $typeLiaison = 'documentation_room';
                $entiteId = $data['room_id'];
            } elseif (!empty($data['site_id'])) {
                $typeLiaison = 'documentation_site';
                $entiteId = $data['site_id'];
            } else {
                $typeLiaison = 'documentation_client';
                $entiteId = $data['client_id'];
            }

            $linkQuery = "INSERT INTO liaisons_pieces_jointes (piece_jointe_id, type_liaison, entite_id) 
                         VALUES (:piece_jointe_id, :type_liaison, :entite_id)";
            $linkStmt = $this->db->prepare($linkQuery);
            $linkStmt->execute([
                ':piece_jointe_id' => $id,
                ':type_liaison' => $typeLiaison,
                ':entite_id' => $entiteId
            ]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("[ERROR] DocumentationModel::updateDocument - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les documents par catégorie
     */
    public function getDocumentsByCategory($categoryId, $clientId = null)
    {
        $query = "
            SELECT 
                pj.*,
                c.name as client_name,
                s.name as site_name,
                b.name as building_name,
                r.name as room_name
            FROM pieces_jointes pj
            INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
            LEFT JOIN clients c ON (lpj.type_liaison = 'documentation_client' AND lpj.entite_id = c.id)
            LEFT JOIN sites s ON (lpj.type_liaison = 'documentation_site' AND lpj.entite_id = s.id)
            LEFT JOIN rooms r ON (lpj.type_liaison = 'documentation_room' AND lpj.entite_id = r.id)
            LEFT JOIN buildings b ON r.building_id = b.id
            WHERE pj.category_id = :category_id
        ";

        $params = [':category_id' => $categoryId];

        if ($clientId) {
            $query .= " AND (c.id = :client_id OR s.client_id = :client_id2 OR b.site_id IN (SELECT id FROM sites WHERE client_id = :client_id3))";
            $params[':client_id'] = $clientId;
            $params[':client_id2'] = $clientId;
            $params[':client_id3'] = $clientId;
        }

        $query .= " ORDER BY pj.date_creation DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Récupère tous les documents avec filtres (pour la vue unifiée)
     * 
     * @param array $filters Les filtres à appliquer
     * @return array Liste des documents
     */
    public function getAllWithFilters($filters = [])
    {
        $where = [];
        $params = [];

        $query = "
        SELECT 
            pj.*,
            c.id as client_id,
            c.name as client_nom,
            s.id as site_id,
            s.name as site_nom,
            b.id as building_id,
            b.name as building_nom,
            r.id as salle_id,
            r.name as salle_nom,
            u.first_name as uploader_first_name,
            u.last_name as uploader_last_name,
            CONCAT(u.first_name, ' ', u.last_name) as uploader_name
        FROM pieces_jointes pj
        INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
        LEFT JOIN clients c ON (lpj.type_liaison = 'documentation_client' AND lpj.entite_id = c.id)
        LEFT JOIN sites s ON (lpj.type_liaison = 'documentation_site' AND lpj.entite_id = s.id)
        LEFT JOIN rooms r ON (lpj.type_liaison = 'documentation_room' AND lpj.entite_id = r.id)
        LEFT JOIN buildings b ON r.building_id = b.id
        LEFT JOIN users u ON pj.created_by = u.id
        WHERE lpj.type_liaison IN ('documentation_client', 'documentation_site', 'documentation_room')
    ";

        // Appliquer les filtres
        if (!empty($filters['client_id'])) {
            $where[] = "(c.id = :client_id OR s.client_id = :client_id2 OR b.client_id = :client_id3)";
            $params[':client_id'] = $filters['client_id'];
            $params[':client_id2'] = $filters['client_id'];
            $params[':client_id3'] = $filters['client_id'];
        }

        if (!empty($filters['site_id'])) {
            $where[] = "(s.id = :site_id OR b.site_id = :site_id2)";
            $params[':site_id'] = $filters['site_id'];
            $params[':site_id2'] = $filters['site_id'];
        }

        if (!empty($filters['building_id'])) {
            $where[] = "b.id = :building_id";
            $params[':building_id'] = $filters['building_id'];
        }

        if (!empty($filters['salle_id'])) {
            $where[] = "r.id = :salle_id";
            $params[':salle_id'] = $filters['salle_id'];
        }

        // Ajouter les conditions WHERE
        if (!empty($where)) {
            $query .= " AND " . implode(" AND ", $where);
        }

        $query .= " ORDER BY c.name, s.name, b.name, r.name, pj.date_creation DESC";

        $stmt = $this->db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}