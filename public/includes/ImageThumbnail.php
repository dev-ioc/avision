<?php
/**
 * Classe utilitaire pour la génération de miniatures d'images
 */
class ImageThumbnail {
    
    /**
     * Génère une miniature d'une image
     * 
     * @param string $sourcePath Chemin vers l'image source
     * @param string $thumbnailPath Chemin vers la miniature à créer
     * @param int $maxWidth Largeur maximale de la miniature
     * @param int $maxHeight Hauteur maximale de la miniature
     * @param int $quality Qualité JPEG (1-100)
     * @return bool Succès de l'opération
     */
    public static function createThumbnail($sourcePath, $thumbnailPath, $maxWidth = 150, $maxHeight = 150, $quality = 80) {
        try {
            // Vérifier que le fichier source existe
            if (!file_exists($sourcePath)) {
                return false;
            }
            
            // Obtenir les informations de l'image
            $imageInfo = getimagesize($sourcePath);
            if (!$imageInfo) {
                return false;
            }
            
            $sourceWidth = $imageInfo[0];
            $sourceHeight = $imageInfo[1];
            $mimeType = $imageInfo['mime'];
            
            // Créer l'image source selon son type
            $sourceImage = null;
            switch ($mimeType) {
                case 'image/jpeg':
                    $sourceImage = imagecreatefromjpeg($sourcePath);
                    break;
                case 'image/png':
                    $sourceImage = imagecreatefrompng($sourcePath);
                    break;
                case 'image/gif':
                    $sourceImage = imagecreatefromgif($sourcePath);
                    break;
                case 'image/webp':
                    if (function_exists('imagecreatefromwebp')) {
                        $sourceImage = imagecreatefromwebp($sourcePath);
                    }
                    break;
                default:
                    return false;
            }
            
            if (!$sourceImage) {
                return false;
            }
            
            // Calculer les dimensions de la miniature en gardant les proportions
            $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
            $thumbnailWidth = intval($sourceWidth * $ratio);
            $thumbnailHeight = intval($sourceHeight * $ratio);
            
            // Créer la miniature
            $thumbnailImage = imagecreatetruecolor($thumbnailWidth, $thumbnailHeight);
            
            // Préserver la transparence pour les PNG et GIF
            if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                imagealphablending($thumbnailImage, false);
                imagesavealpha($thumbnailImage, true);
                $transparent = imagecolorallocatealpha($thumbnailImage, 255, 255, 255, 127);
                imagefill($thumbnailImage, 0, 0, $transparent);
            }
            
            // Redimensionner l'image
            imagecopyresampled(
                $thumbnailImage, $sourceImage,
                0, 0, 0, 0,
                $thumbnailWidth, $thumbnailHeight,
                $sourceWidth, $sourceHeight
            );
            
            // Créer le dossier de destination s'il n'existe pas
            $thumbnailDir = dirname($thumbnailPath);
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }
            
            // Sauvegarder la miniature
            $success = false;
            switch ($mimeType) {
                case 'image/jpeg':
                    $success = imagejpeg($thumbnailImage, $thumbnailPath, $quality);
                    break;
                case 'image/png':
                    $success = imagepng($thumbnailImage, $thumbnailPath, 9);
                    break;
                case 'image/gif':
                    $success = imagegif($thumbnailImage, $thumbnailPath);
                    break;
                case 'image/webp':
                    if (function_exists('imagewebp')) {
                        $success = imagewebp($thumbnailImage, $thumbnailPath, $quality);
                    }
                    break;
            }
            
            // Libérer la mémoire
            imagedestroy($sourceImage);
            imagedestroy($thumbnailImage);
            
            return $success;
            
        } catch (Exception $e) {
            custom_log("Erreur lors de la création de la miniature : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Génère le chemin de la miniature pour une image
     * 
     * @param string $originalPath Chemin vers l'image originale
     * @return string Chemin vers la miniature
     */
    public static function getThumbnailPath($originalPath) {
        $pathInfo = pathinfo($originalPath);
        return $pathInfo['dirname'] . '/thumbnails/' . $pathInfo['filename'] . '_thumb.' . $pathInfo['extension'];
    }
    
    /**
     * Vérifie si une miniature existe et est à jour
     * 
     * @param string $originalPath Chemin vers l'image originale
     * @param string $thumbnailPath Chemin vers la miniature
     * @return bool True si la miniature existe et est à jour
     */
    public static function isThumbnailUpToDate($originalPath, $thumbnailPath) {
        if (!file_exists($thumbnailPath)) {
            return false;
        }
        
        if (!file_exists($originalPath)) {
            return false;
        }
        
        // Vérifier que la miniature est plus récente que l'original
        return filemtime($thumbnailPath) >= filemtime($originalPath);
    }
    
    /**
     * Génère une miniature si nécessaire
     * 
     * @param string $originalPath Chemin vers l'image originale
     * @param int $maxWidth Largeur maximale
     * @param int $maxHeight Hauteur maximale
     * @return string|null Chemin vers la miniature ou null si échec
     */
    public static function generateThumbnailIfNeeded($originalPath, $maxWidth = 150, $maxHeight = 150) {
        $thumbnailPath = self::getThumbnailPath($originalPath);
        
        // Vérifier si la miniature existe et est à jour
        if (self::isThumbnailUpToDate($originalPath, $thumbnailPath)) {
            return $thumbnailPath;
        }
        
        // Générer la miniature
        if (self::createThumbnail($originalPath, $thumbnailPath, $maxWidth, $maxHeight)) {
            return $thumbnailPath;
        }
        
        return null;
    }
    
    /**
     * Vérifie si un fichier est une image supportée
     * 
     * @param string $filePath Chemin vers le fichier
     * @return bool True si c'est une image supportée
     */
    public static function isSupportedImage($filePath) {
        $imageInfo = getimagesize($filePath);
        if (!$imageInfo) {
            return false;
        }
        
        $supportedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        return in_array($imageInfo['mime'], $supportedTypes);
    }
}
?>
