<?php
// public/models/ExcelModel.php
class ExcelModel
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAll()
    {
        $stmt = $this->pdo->query("SELECT id, designation, quantity, prix, montant FROM excel_tables");
        return $stmt->fetchAll();
    }

    public function insert($designation, $quantity, $prix, $montant)
    {
        $stmt = $this->pdo->prepare("INSERT INTO excel_tables (designation, quantity, prix, montant) VALUES (?, ?, ?, ?)");
        $stmt->execute([$designation, $quantity, $prix, $montant]);
        return $this->pdo->lastInsertId();
    }

    public function update($id, $designation, $quantity, $prix, $montant)
    {
        $stmt = $this->pdo->prepare("UPDATE excel_tables SET designation=?, quantity=?, prix=?, montant=? WHERE id=?");
        $stmt->execute([$designation, $quantity, $prix, $montant, $id]);
    }

    public function deleteByIds($ids)
    {
        if (empty($ids))
            return;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("DELETE FROM excel_tables WHERE id IN ($placeholders)");
        $stmt->execute(array_values($ids));
    }

    public function exists($designation)
    {
        $stmt = $this->pdo->prepare("SELECT id FROM excel_tables WHERE designation=?");
        $stmt->execute([$designation]);
        return $stmt->fetchColumn();
    }
}