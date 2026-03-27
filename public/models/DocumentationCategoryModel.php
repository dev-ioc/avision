<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';

class DocumentationCategoryModel extends BaseModel {
    public function __construct($db) {
        parent::__construct($db);
        $this->table = 'documentation_categories';
    }

    /**
     * Récupère toutes les catégories
     */
    public function getAllCategories() {
        $query = "SELECT id, name, created_at FROM {$this->table} ORDER BY name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère une catégorie par son ID
     * @param int $id ID de la catégorie
     * @return array|null Données de la catégorie ou null si non trouvée
     */
    public function getCategoryById($id) {
        return $this->find($id);
    }

    /**
     * Ajoute une nouvelle catégorie
     * @param string $name Nom de la catégorie
     * @param string|null $description Description de la catégorie
     * @return int|false ID de la catégorie créée ou false en cas d'erreur
     */
    public function addCategory($name, $description = null) {
        $data = [
            'name' => $name,
            'description' => $description
        ];
        return parent::create($data);
    }

    /**
     * Met à jour une catégorie
     * @param int $id ID de la catégorie
     * @param array $data Nouvelles données
     * @return bool True si la mise à jour a réussi
     */
    public function updateCategory($id, $data) {
        return parent::update($id, $data);
    }

    /**
     * Supprime une catégorie
     * @param int $categoryId ID de la catégorie
     * @return bool True si la suppression a réussi
     */
    public function deleteCategory($categoryId) {
        return parent::delete($categoryId);
    }
} 