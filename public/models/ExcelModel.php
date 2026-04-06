<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';
class ExcelModel extends BaseModel
{
    public function __construct($db)
    {
        parent::__construct($db);
        $this->table = 'excel_tables';
    }

    // Récupérer tous les enregistrements
    public function getAll()
    {
        $stmt = $this->db->prepare("SELECT id, designation, quantity, prix, montant FROM excel_tables");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert($designation, $quantity, $prix, $montant)
    {
        $check = $this->db->prepare("SELECT id FROM excel_tables WHERE designation = ?");
        $check->execute([trim($designation)]);
        if ($check->fetchColumn())
            return false;

        $stmt = $this->db->prepare(
            "INSERT INTO excel_tables (designation, quantity, prix, montant) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$designation, $quantity, $prix, $montant]);
        return $this->db->lastInsertId();
    }

    public function deleteByIds(array $ids)
    {
        if (empty($ids))
            return false;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("DELETE FROM excel_tables WHERE id IN ($placeholders)");
        $stmt->execute(array_values($ids));
        return true;
    }
    public function update($id, $data)
    {
        $stmt = $this->db->prepare(
            "UPDATE excel_tables SET designation=?, quantity=?, prix=?, montant=? WHERE id=?"
        );
        $stmt->execute([
            $data['designation'],
            $data['quantity'],
            $data['prix'],
            $data['montant'],
            $id
        ]);
        return true;
    }
}