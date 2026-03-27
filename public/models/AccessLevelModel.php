<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';

/**
 * Modèle pour la gestion des niveaux d'accès des contrats
 */
class AccessLevelModel extends BaseModel {
    public function __construct($db) {
        parent::__construct($db);
        $this->table = 'contract_access_levels';
    }

    /**
     * Récupère tous les niveaux d'accès
     * @return array Liste des niveaux d'accès
     */
    public function getAllAccessLevels() {
        try {
            $sql = "SELECT id, name, description, ordre_affichage, created_at, updated_at FROM contract_access_levels ORDER BY ordre_affichage, name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des niveaux d'accès : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Récupère un niveau d'accès par son ID
     * @param int $id ID du niveau d'accès
     * @return array|null Le niveau d'accès ou null
     */
    public function getAccessLevelById($id) {
        try {
            return $this->find($id);
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération du niveau d'accès : " . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Récupère les règles de visibilité pour un niveau d'accès
     * @param int $accessLevelId ID du niveau d'accès
     * @return array Règles de visibilité par champ
     */
    public function getVisibilityRulesForLevel($accessLevelId) {
        try {
            $sql = "SELECT field_name, visible_by_default 
                    FROM access_level_material_visibility 
                    WHERE access_level_id = :access_level_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':access_level_id' => $accessLevelId]);
            
            $rules = [];
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                $rules[$row['field_name']] = (bool)$row['visible_by_default'];
            }
            
            return $rules;
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des règles de visibilité : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Récupère le niveau d'accès d'un contrat
     * @param int $contractId ID du contrat
     * @return array|null Le niveau d'accès ou null
     */
    public function getContractAccessLevel($contractId) {
        try {
            $sql = "SELECT cal.* 
                    FROM contract_access_levels cal
                    INNER JOIN contracts c ON c.access_level_id = cal.id
                    WHERE c.id = :contract_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':contract_id' => $contractId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération du niveau d'accès du contrat : " . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Met à jour le niveau d'accès d'un contrat
     * @param int $contractId ID du contrat
     * @param int $accessLevelId ID du nouveau niveau d'accès
     * @return bool Succès de la mise à jour
     */
    public function updateContractAccessLevel($contractId, $accessLevelId) {
        try {
            $sql = "UPDATE contracts SET access_level_id = :access_level_id WHERE id = :contract_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':access_level_id' => $accessLevelId,
                ':contract_id' => $contractId
            ]);
            return true;
        } catch (Exception $e) {
            custom_log("Erreur lors de la mise à jour du niveau d'accès : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Récupère tous les matériels d'un contrat
     * @param int $contractId ID du contrat
     * @return array Liste des matériels
     */
    public function getMaterialsByContract($contractId) {
        try {
            $sql = "SELECT m.*, r.name as salle_nom, s.name as site_nom
                    FROM materiel m
                    INNER JOIN rooms r ON m.salle_id = r.id
                    INNER JOIN sites s ON r.site_id = s.id
                    INNER JOIN contract_rooms cr ON r.id = cr.room_id
                    WHERE cr.contract_id = :contract_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':contract_id' => $contractId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des matériels du contrat : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Applique les règles de visibilité par défaut à un matériel
     * @param int $materialId ID du matériel
     * @param int $accessLevelId ID du niveau d'accès
     * @return bool Succès de l'application
     */
    public function applyDefaultVisibilityToMaterial($materialId, $accessLevelId) {
        try {
            // Récupérer les règles de visibilité du niveau d'accès
            $visibilityRules = $this->getVisibilityRulesForLevel($accessLevelId);
            
            if (empty($visibilityRules)) {
                custom_log("Aucune règle de visibilité trouvée pour le niveau d'accès : " . $accessLevelId, 'WARNING');
                return false;
            }

            // Supprimer les anciennes visibilités (sauf exceptions manuelles)
            $sql = "DELETE FROM visibilite_champs_materiel 
                    WHERE materiel_id = :material_id 
                    AND nom_champ IN (" . implode(',', array_fill(0, count($visibilityRules), '?')) . ")";
            
            $stmt = $this->db->prepare($sql);
            $params = array_merge([$materialId], array_keys($visibilityRules));
            $stmt->execute($params);

            // Insérer seulement les champs visibles
            $sql = "INSERT INTO visibilite_champs_materiel (materiel_id, nom_champ) VALUES (:material_id, :nom_champ)";
            $stmt = $this->db->prepare($sql);

            foreach ($visibilityRules as $fieldName => $visible) {
                if ($visible) {
                    $stmt->execute([
                        ':material_id' => $materialId,
                        ':nom_champ' => $fieldName
                    ]);
                }
            }

            return true;
        } catch (Exception $e) {
            custom_log("Erreur lors de l'application des règles de visibilité : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Met à jour la visibilité de tous les matériels d'un contrat selon le nouveau niveau d'accès
     * @param int $contractId ID du contrat
     * @param int $newAccessLevelId ID du nouveau niveau d'accès
     * @return bool Succès de la mise à jour
     */
    public function updateContractMaterialsVisibility($contractId, $newAccessLevelId) {
        try {
            $this->db->beginTransaction();

            // Récupérer tous les matériels du contrat
            $materials = $this->getMaterialsByContract($contractId);
            
            if (empty($materials)) {
                custom_log("Aucun matériel trouvé pour le contrat : " . $contractId, 'INFO');
                $this->db->commit();
                return true;
            }

            // Récupérer les nouvelles règles de visibilité
            $newVisibilityRules = $this->getVisibilityRulesForLevel($newAccessLevelId);
            
            if (empty($newVisibilityRules)) {
                throw new Exception("Aucune règle de visibilité trouvée pour le niveau d'accès : " . $newAccessLevelId);
            }

            // Pour chaque matériel, préserver les exceptions manuelles et appliquer les nouvelles règles
            foreach ($materials as $material) {
                $materialId = $material['id'];
                
                // Récupérer les exceptions manuelles existantes
                $sql = "SELECT nom_champ FROM visibilite_champs_materiel WHERE materiel_id = :material_id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([':material_id' => $materialId]);
                $existingExceptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // Supprimer les visibilités qui ne sont pas des exceptions manuelles
                $fieldsToUpdate = array_keys($newVisibilityRules);
                $fieldsToUpdate = array_diff($fieldsToUpdate, $existingExceptions);
                
                if (!empty($fieldsToUpdate)) {
                    $placeholders = str_repeat('?,', count($fieldsToUpdate) - 1) . '?';
                    $sql = "DELETE FROM visibilite_champs_materiel 
                            WHERE materiel_id = ? AND nom_champ IN ($placeholders)";
                    $stmt = $this->db->prepare($sql);
                    $params = array_merge([$materialId], $fieldsToUpdate);
                    $stmt->execute($params);

                    // Insérer seulement les champs visibles (sauf exceptions manuelles)
                    $sql = "INSERT INTO visibilite_champs_materiel (materiel_id, nom_champ) VALUES (?, ?)";
                    $stmt = $this->db->prepare($sql);
                    
                    foreach ($fieldsToUpdate as $fieldName) {
                        if ($newVisibilityRules[$fieldName]) {
                            $stmt->execute([
                                $materialId,
                                $fieldName
                            ]);
                        }
                    }
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            custom_log("Erreur lors de la mise à jour de la visibilité des matériels : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Récupère les exceptions manuelles d'un matériel
     * @param int $materialId ID du matériel
     * @return array Exceptions manuelles par champ
     */
    public function getMaterialExceptions($materialId) {
        try {
            $sql = "SELECT nom_champ, visible_client FROM visibilite_champs_materiel WHERE materiel_id = :material_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':material_id' => $materialId]);
            
            $exceptions = [];
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                $exceptions[$row['nom_champ']] = (bool)$row['visible_client'];
            }
            
            return $exceptions;
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des exceptions matériel : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Crée un nouveau niveau d'accès
     */
    public function createAccessLevel($name, $description) {
        try {
            // Récupérer le prochain ordre d'affichage disponible
            $nextOrder = $this->getNextDisplayOrder();
            
            $data = [
                'name' => $name,
                'description' => $description,
                'ordre_affichage' => $nextOrder
            ];
            return parent::create($data);
        } catch (Exception $e) {
            custom_log("Erreur lors de la création d'un niveau d'accès : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Met à jour les règles de visibilité pour un niveau d'accès
     * @param int $accessLevelId
     * @param array $fields ['nom_champ' => true/false]
     * @return bool
     */
    public function updateVisibilityRules($accessLevelId, $fields) {
        try {
            $this->db->beginTransaction();
            // Supprimer les anciennes règles
            $sql = "DELETE FROM access_level_material_visibility WHERE access_level_id = :access_level_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':access_level_id' => $accessLevelId]);
            // Insérer les nouvelles règles
            $sql = "INSERT INTO access_level_material_visibility (access_level_id, field_name, visible_by_default) VALUES (:access_level_id, :field_name, :visible_by_default)";
            $stmt = $this->db->prepare($sql);
            
            foreach ($fields as $fieldName => $visible) {
                $stmt->execute([
                    ':access_level_id' => $accessLevelId,
                    ':field_name' => $fieldName,
                    ':visible_by_default' => $visible ? 1 : 0
                ]);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            custom_log("Erreur lors de la mise à jour des règles de visibilité : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Récupère les contrats par niveau d'accès avec le nombre de matériels
     * @param int $accessLevelId ID du niveau d'accès
     * @return array Liste des contrats avec le nombre de matériels
     */
    public function getContractsByAccessLevel($accessLevelId) {
        try {
            $sql = "SELECT 
                        c.id,
                        c.name,
                        cl.name as client_name,
                        COUNT(m.id) as materials_count
                    FROM contracts c
                    LEFT JOIN clients cl ON c.client_id = cl.id
                    LEFT JOIN contract_rooms cr ON c.id = cr.contract_id
                    LEFT JOIN rooms r ON cr.room_id = r.id
                    LEFT JOIN materiel m ON r.id = m.salle_id
                    WHERE c.access_level_id = :access_level_id
                    GROUP BY c.id, c.name, cl.name
                    ORDER BY cl.name, c.name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':access_level_id' => $accessLevelId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des contrats par niveau d'accès : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Applique la visibilité par défaut à tous les matériels existants pour un niveau d'accès
     * @param int $accessLevelId ID du niveau d'accès
     * @return int Nombre de matériels mis à jour
     */
    public function applyVisibilityToAllMaterials($accessLevelId) {
        try {
            // Récupérer tous les matériels des contrats avec ce niveau d'accès
            $sql = "SELECT DISTINCT m.id
                    FROM materiel m
                    INNER JOIN rooms r ON m.salle_id = r.id
                    INNER JOIN contract_rooms cr ON r.id = cr.room_id
                    INNER JOIN contracts c ON cr.contract_id = c.id
                    WHERE c.access_level_id = :access_level_id
                    AND EXISTS (
                        SELECT 1 FROM contract_rooms cr2 
                        WHERE cr2.room_id = r.id 
                        AND cr2.contract_id = c.id
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':access_level_id' => $accessLevelId]);
            $materials = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($materials)) {
                return true;
            }
            
            // Récupérer les règles de visibilité pour ce niveau d'accès
            $visibilityRules = $this->getVisibilityRulesForLevel($accessLevelId);
            
            if (empty($visibilityRules)) {
                return false;
            }
            
            // Pour chaque matériel, appliquer les règles de visibilité
            foreach ($materials as $materialId) {
                // Supprimer TOUTES les anciennes visibilités pour ce matériel
                $deleteSql = "DELETE FROM visibilite_champs_materiel WHERE materiel_id = :material_id";
                $deleteStmt = $this->db->prepare($deleteSql);
                $deleteStmt->execute([':material_id' => $materialId]);
                
                // Insérer seulement les champs visibles
                $insertSql = "INSERT INTO visibilite_champs_materiel (materiel_id, nom_champ) VALUES (:material_id, :nom_champ)";
                $insertStmt = $this->db->prepare($insertSql);
                
                foreach ($visibilityRules as $fieldName => $visible) {
                    if ($visible) {
                        $insertStmt->execute([
                            ':material_id' => $materialId,
                            ':nom_champ' => $fieldName
                        ]);
                    }
                }
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Génère un aperçu des changements pour un niveau d'accès
     * @param int $accessLevelId ID du niveau d'accès
     * @return array Aperçu des changements
     */
    public function getVisibilityPreview($accessLevelId) {
        try {
            // Récupérer les contrats avec le nombre de matériels
            $contracts = $this->getContractsByAccessLevel($accessLevelId);
            
            $totalMaterials = 0;
            foreach ($contracts as $contract) {
                $totalMaterials += $contract['materials_count'];
            }
            
            $summary = sprintf(
                "Ce changement affectera %d contrats et %d matériels au total.",
                count($contracts),
                $totalMaterials
            );
            
            return [
                'summary' => $summary,
                'details' => $contracts
            ];
        } catch (Exception $e) {
            custom_log("Erreur lors de la génération de l'aperçu : " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Met à jour un niveau d'accès
     * @param int $id ID du niveau d'accès
     * @param string $name Nouveau nom
     * @param string $description Nouvelle description
     * @return bool Succès de la mise à jour
     */
    public function updateAccessLevel($id, $name, $description) {
        try {
            $data = [
                'name' => $name,
                'description' => $description
            ];
            return parent::update($id, $data);
        } catch (Exception $e) {
            custom_log("Erreur lors de la mise à jour du niveau d'accès : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Supprime un niveau d'accès
     * @param int $id ID du niveau d'accès
     * @return bool Succès de la suppression
     */
    public function deleteAccessLevel($id) {
        require_once __DIR__ . '/../classes/Services/CacheService.php';
        
        try {
            $this->db->beginTransaction();

            // Vérifier si le niveau d'accès est utilisé par des contrats
            $sql = "SELECT COUNT(*) as count FROM contracts WHERE access_level_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                throw new Exception("Ce niveau d'accès est utilisé par " . $result['count'] . " contrat(s). Impossible de le supprimer.");
            }

            // Supprimer les règles de visibilité associées
            $sql = "DELETE FROM access_level_material_visibility WHERE access_level_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);

            // Supprimer le niveau d'accès (utilise BaseModel::delete())
            $deleteResult = parent::delete($id);
            if (!$deleteResult) {
                throw new Exception("Erreur lors de la suppression du niveau d'accès");
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            custom_log("Erreur lors de la suppression du niveau d'accès : " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Vérifie si un niveau d'accès peut être supprimé
     * @param int $id ID du niveau d'accès
     * @return array Informations sur la suppression possible
     */
    public function canDeleteAccessLevel($id) {
        try {
            // Compter les contrats utilisant ce niveau d'accès
            $sql = "SELECT COUNT(*) as count FROM contracts WHERE access_level_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'can_delete' => $result['count'] == 0,
                'contracts_count' => $result['count']
            ];
        } catch (Exception $e) {
            custom_log("Erreur lors de la vérification de suppression : " . $e->getMessage(), 'ERROR');
            return ['can_delete' => false, 'contracts_count' => 0];
        }
    }

    /**
     * Met à jour l'ordre d'affichage d'un niveau d'accès
     * @param int $id ID du niveau d'accès
     * @param int $ordre Nouvel ordre d'affichage
     * @return bool True si la mise à jour a réussi
     */
    public function updateDisplayOrder($id, $ordre) {
        try {
            $sql = "UPDATE contract_access_levels SET ordre_affichage = :ordre WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':ordre' => $ordre
            ]);
            return true;
        } catch (Exception $e) {
            custom_log("Erreur lors de la mise à jour de l'ordre d'affichage : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Récupère le prochain ordre d'affichage disponible
     * @return int Prochain ordre disponible
     */
    public function getNextDisplayOrder() {
        try {
            $sql = "SELECT COALESCE(MAX(ordre_affichage), 0) + 1 as next_order FROM contract_access_levels";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['next_order'];
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération du prochain ordre : " . $e->getMessage(), 'ERROR');
            return 1;
        }
    }

    /**
     * Récupère le nombre de contrats utilisant ce niveau d'accès
     * @param int $id ID du niveau d'accès
     * @return int Nombre de contrats utilisant ce niveau d'accès
     */
    public function getContractCountByAccessLevel($id) {
        try {
            $query = "SELECT COUNT(*) as count FROM contracts WHERE access_level_id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['count'];
        } catch (Exception $e) {
            custom_log("Erreur lors du comptage des contrats par niveau d'accès : " . $e->getMessage(), 'ERROR');
            return 0;
        }
    }
} 