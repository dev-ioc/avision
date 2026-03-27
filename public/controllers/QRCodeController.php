<?php
require_once __DIR__ . '/../models/SiteModel.php';
require_once __DIR__ . '/../models/RoomModel.php';
require_once __DIR__ . '/../models/MaterielModel.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class QRCodeController {
    use AccessControlTrait;
    private $db;
    private $siteModel;
    private $roomModel;
    private $materielModel;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->siteModel = new SiteModel($this->db);
        $this->roomModel = new RoomModel($this->db);
        $this->materielModel = new MaterielModel($this->db);
    }


    /**
     * Génère les fiches QR codes pour un site complet
     */
    public function generateSite($siteId) {
        $this->checkAccess();

        // Récupérer les informations du site
        $site = $this->siteModel->getSiteById($siteId);
        if (!$site) {
            $_SESSION['error'] = "Site non trouvé.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Récupérer toutes les salles du site
        $salles = $this->roomModel->getRoomsBySiteId($siteId, true); // activeOnly = true

        // Récupérer le nombre de matériel par salle
        $materielCounts = [];
        foreach ($salles as $salle) {
            $count = $this->materielModel->getMaterielCountBySalle($salle['id']);
            $materielCounts[$salle['id']] = $count;
        }

        $pageTitle = "QR Codes - " . $site['name'];
        require_once VIEWS_PATH . '/qrcode/site.php';
    }

    /**
     * Génère une fiche QR code pour une salle spécifique
     */
    public function generateSalle($salleId) {
        $this->checkAccess();

        // Récupérer les informations de la salle
        $salle = $this->roomModel->getRoomById($salleId);
        if (!$salle) {
            $_SESSION['error'] = "Salle non trouvée.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Récupérer les informations du site
        $site = $this->siteModel->getSiteById($salle['site_id']);

        // Récupérer le nombre de matériel dans cette salle
        $materielCount = $this->materielModel->getMaterielCountBySalle($salleId);

        $pageTitle = "QR Code - " . $site['name'] . " - " . $salle['name'];
        require_once VIEWS_PATH . '/qrcode/salle.php';
    }

    /**
     * Gère la redirection après authentification via QR code
     */
    public function redirect() {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Récupérer les paramètres QR de la session
        $qrSalle = $_SESSION['qr_salle'] ?? null;
        $qrType = $_SESSION['qr_type'] ?? null;

        // Nettoyer la session
        unset($_SESSION['qr_salle']);
        unset($_SESSION['qr_type']);

        if ($qrSalle) {
            // Rediriger vers la page appropriée selon le type d'utilisateur
            if (isClient()) {
                header('Location: ' . BASE_URL . 'materiel_client/salle/' . $qrSalle);
            } else {
                header('Location: ' . BASE_URL . 'materiel/salle/' . $qrSalle);
            }
            exit;
        }

        // Si pas de paramètres QR, redirection normale vers dashboard
        header('Location: ' . BASE_URL . 'dashboard');
        exit;
    }

    /**
     * Génère l'URL pour un QR code
     */
    public function generateQRUrl($salleId, $type = 'staff') {
        // URL simplifiée pour éviter les problèmes de longueur
        return BASE_URL . 'auth/login?qr=' . $salleId . '&t=' . $type;
    }

    /**
     * Génère les données pour les QR codes (pour les vues)
     */
    public function generateQRData($salleId) {
        return [
            'staff_url' => $this->generateQRUrl($salleId, 'staff'),
            'client_url' => $this->generateQRUrl($salleId, 'client')
        ];
    }

    /**
     * Génère un QR code en base64 pour l'affichage
     */
    public function generateQRCodeBase64($text, $size = 200) {
        try {
            $qrCode = new QrCode(
                data: $text,
                size: $size,
                margin: 10
            );
            
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            
            return 'data:image/png;base64,' . base64_encode($result->getString());
        } catch (Exception $e) {
            error_log('Erreur génération QR code: ' . $e->getMessage());
            // Retourner une image placeholder en base64
            return $this->generatePlaceholderQR($text, $size);
        }
    }

    /**
     * Génère un placeholder QR code simple en base64
     */
    private function generatePlaceholderQR($text, $size = 200) {
        // Créer une image simple avec le texte
        $image = imagecreate($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        // Dessiner un rectangle blanc
        imagefill($image, 0, 0, $white);
        
        // Ajouter du texte
        $fontSize = max(8, $size / 20);
        $text = substr($text, 0, 20) . '...';
        $textWidth = strlen($text) * $fontSize * 0.6;
        $textX = ($size - $textWidth) / 2;
        $textY = $size / 2;
        
        imagestring($image, 1, $textX, $textY, $text, $black);
        
        // Convertir en base64
        ob_start();
        imagepng($image);
        $imageData = ob_get_contents();
        ob_end_clean();
        imagedestroy($image);
        
        return 'data:image/png;base64,' . base64_encode($imageData);
    }
}
