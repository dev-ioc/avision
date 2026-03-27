<?php
/**
 * Classe de base pour tous les modèles
 * Fournit des méthodes génériques CRUD et des utilitaires pour les requêtes
 */
abstract class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';

    /**
     * Constructeur
     * @param PDO $db Instance de la base de données
     */
    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Récupère un enregistrement par son ID
     * @param int $id ID de l'enregistrement
     * @param array $columns Colonnes à récupérer (par défaut toutes)
     * @return array|null Les données de l'enregistrement ou null si non trouvé
     */
    protected function find($id, $columns = ['*']) {
        $columnsStr = is_array($columns) ? implode(', ', $columns) : $columns;
        $sql = "SELECT {$columnsStr} FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Récupère tous les enregistrements avec filtres optionnels
     * @param array $filters Filtres à appliquer
     * @param array $options Options supplémentaires (order, limit, columns)
     * @return array Liste des enregistrements
     */
    protected function findAll($filters = [], $options = []) {
        $columns = $options['columns'] ?? ['*'];
        $columnsStr = is_array($columns) ? implode(', ', $columns) : $columns;
        
        $sql = "SELECT {$columnsStr} FROM {$this->table}";
        $params = [];

        // Construire la clause WHERE
        if (!empty($filters)) {
            $whereClause = $this->buildWhereClause($filters, $params);
            if ($whereClause) {
                $sql .= " WHERE " . $whereClause;
            }
        }

        // Ajouter ORDER BY
        if (isset($options['order'])) {
            $sql .= " ORDER BY " . $options['order'];
        }

        // Ajouter LIMIT
        if (isset($options['limit'])) {
            $sql .= " LIMIT " . (int)$options['limit'];
            if (isset($options['offset'])) {
                $sql .= " OFFSET " . (int)$options['offset'];
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crée un nouvel enregistrement
     * @param array $data Données à insérer
     * @return int|false ID du nouvel enregistrement ou false en cas d'erreur
     */
    protected function create($data) {
        // Filtrer les données null et les clés non valides
        $data = array_filter($data, function($value) {
            return $value !== null;
        });

        if (empty($data)) {
            return false;
        }

        $columns = array_keys($data);
        $placeholders = ':' . implode(', :', $columns);
        $columnsStr = implode(', ', $columns);

        $sql = "INSERT INTO {$this->table} ({$columnsStr}) VALUES ({$placeholders})";
        
        $stmt = $this->db->prepare($sql);
        
        // Préparer les paramètres avec préfixe :
        $params = [];
        foreach ($data as $key => $value) {
            $params[':' . $key] = $value;
        }

        if ($stmt->execute($params)) {
            return $this->db->lastInsertId();
        }

        return false;
    }

    /**
     * Met à jour un enregistrement
     * @param int $id ID de l'enregistrement à mettre à jour
     * @param array $data Nouvelles données
     * @return bool True si la mise à jour a réussi
     */
    protected function update($id, $data) {
        // Filtrer les données null
        $data = array_filter($data, function($value) {
            return $value !== null;
        });

        if (empty($data)) {
            return false;
        }

        $updates = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            $updates[] = "{$key} = :{$key}";
            $params[':' . $key] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE {$this->primaryKey} = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Supprime un enregistrement
     * @param int $id ID de l'enregistrement à supprimer
     * @return bool True si la suppression a réussi
     */
    protected function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Construit une clause WHERE à partir de filtres
     * @param array $filters Filtres à appliquer
     * @param array &$params Tableau de paramètres (passé par référence)
     * @param array $mapping Mapping personnalisé des filtres vers les colonnes
     * @return string Clause WHERE construite
     */
    protected function buildWhereClause($filters, &$params, $mapping = []) {
        $conditions = [];
        $paramIndex = 0;

        foreach ($filters as $key => $value) {
            // Ignorer les valeurs vides
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                continue;
            }

            // Utiliser le mapping si fourni, sinon utiliser la clé telle quelle
            $column = $mapping[$key] ?? $key;

            // Gérer différents types de filtres
            if (is_array($value)) {
                // Pour les tableaux, utiliser IN
                $placeholders = [];
                foreach ($value as $val) {
                    $paramKey = ':filter_' . $paramIndex++;
                    $placeholders[] = $paramKey;
                    $params[$paramKey] = $val;
                }
                $conditions[] = "{$column} IN (" . implode(', ', $placeholders) . ")";
            } elseif (strpos($key, '_not_in') !== false) {
                // Filtre NOT IN
                $column = str_replace('_not_in', '', $key);
                $column = $mapping[$key] ?? $column;
                if (is_array($value)) {
                    $placeholders = [];
                    foreach ($value as $val) {
                        $paramKey = ':filter_' . $paramIndex++;
                        $placeholders[] = $paramKey;
                        $params[$paramKey] = $val;
                    }
                    $conditions[] = "{$column} NOT IN (" . implode(', ', $placeholders) . ")";
                }
            } elseif (strpos($key, '_like') !== false) {
                // Filtre LIKE
                $column = str_replace('_like', '', $key);
                $column = $mapping[$key] ?? $column;
                $paramKey = ':filter_' . $paramIndex++;
                $conditions[] = "{$column} LIKE {$paramKey}";
                $params[$paramKey] = '%' . $value . '%';
            } elseif (strpos($key, '_gte') !== false) {
                // Filtre >=
                $column = str_replace('_gte', '', $key);
                $column = $mapping[$key] ?? $column;
                $paramKey = ':filter_' . $paramIndex++;
                $conditions[] = "{$column} >= {$paramKey}";
                $params[$paramKey] = $value;
            } elseif (strpos($key, '_lte') !== false) {
                // Filtre <=
                $column = str_replace('_lte', '', $key);
                $column = $mapping[$key] ?? $column;
                $paramKey = ':filter_' . $paramIndex++;
                $conditions[] = "{$column} <= {$paramKey}";
                $params[$paramKey] = $value;
            } else {
                // Filtre d'égalité simple
                $paramKey = ':filter_' . $paramIndex++;
                $conditions[] = "{$column} = {$paramKey}";
                $params[$paramKey] = $value;
            }
        }

        return implode(' AND ', $conditions);
    }

    /**
     * Compte le nombre d'enregistrements avec filtres optionnels
     * @param array $filters Filtres à appliquer
     * @return int Nombre d'enregistrements
     */
    protected function count($filters = []) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $params = [];

        if (!empty($filters)) {
            $whereClause = $this->buildWhereClause($filters, $params);
            if ($whereClause) {
                $sql .= " WHERE " . $whereClause;
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0);
    }

    /**
     * Vérifie si un enregistrement existe
     * @param int $id ID de l'enregistrement
     * @return bool True si l'enregistrement existe
     */
    protected function exists($id) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0) > 0;
    }

    /**
     * Exécute une requête SQL personnalisée
     * @param string $sql Requête SQL
     * @param array $params Paramètres de la requête
     * @return PDOStatement Statement exécuté
     */
    protected function executeQuery($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
