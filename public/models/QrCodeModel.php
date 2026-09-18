<?php
class QrCodeModel
{
    private $db;
    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getOrCreateCode($targetType, $targetId)
    {
        $stmt = $this->db->prepare(
            "SELECT code FROM qr_codes WHERE target_type = ? AND target_id = ?"
        );
        $stmt->execute([$targetType, $targetId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row)
            return $row['code'];

        do {
            $code = strtoupper(bin2hex(random_bytes(4)));
            $check = $this->db->prepare("SELECT 1 FROM qr_codes WHERE code = ?");
            $check->execute([$code]);
        } while ($check->fetch());

        $insert = $this->db->prepare(
            "INSERT INTO qr_codes (code, target_type, target_id) VALUES (?, ?, ?)"
        );
        $insert->execute([$code, $targetType, $targetId]);
        return $code;
    }

    public function resolveCode($code)
    {
        $stmt = $this->db->prepare("SELECT * FROM qr_codes WHERE code = ?");
        $stmt->execute([$code]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}