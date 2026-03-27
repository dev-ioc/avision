<?php
/**
 * Fonctions utilitaires pour les fichiers
 * Formatage de tailles, icônes, limites d'upload
 */

/**
 * Formate une taille de fichier en octets vers une unité lisible
 * @param int $bytes Taille en octets
 * @param int $precision Nombre de décimales
 * @return string Taille formatée
 */
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Retourne la classe CSS de l'icône Bootstrap selon le type de fichier
 * @param string $fileType Extension du fichier
 * @return string Classe CSS de l'icône
 */
function getFileIcon($fileType) {
    if (empty($fileType)) {
        return 'bi bi-file';
    }
    
    $type = strtolower($fileType);
    
    if (in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])) {
        return 'bi bi-file-image';
    }
    if ($type === 'pdf') {
        return 'bi bi-file-pdf';
    }
    if (in_array($type, ['doc', 'docx'])) {
        return 'bi bi-file-word';
    }
    if (in_array($type, ['xls', 'xlsx'])) {
        return 'bi bi-file-excel';
    }
    if (in_array($type, ['zip', 'rar', '7z'])) {
        return 'bi bi-file-zip';
    }
    if ($type === 'txt') {
        return 'bi bi-file-text';
    }
    
    return 'bi bi-file';
}

/**
 * Parse une taille PHP ini (ex: "8M", "2G") en octets
 * @param string $sizeStr La taille au format PHP ini
 * @return int La taille en octets
 */
function parsePhpIniSize($sizeStr) {
    if (empty($sizeStr)) return 0;
    $sizeStr = trim($sizeStr);
    $last = strtolower($sizeStr[strlen($sizeStr)-1]);
    $val = intval($sizeStr);
    switch($last) {
        case 'g': $val *= 1024; // Fall-through
        case 'm': $val *= 1024; // Fall-through
        case 'k': $val *= 1024;
    }
    return $val;
}

/**
 * Récupère la limite effective d'upload du serveur PHP
 * Retourne le minimum entre upload_max_filesize et post_max_size
 * @return int La limite en octets
 */
function getServerMaxUploadSize() {
    $phpMaxUpload = parsePhpIniSize(ini_get('upload_max_filesize'));
    $phpPostMax = parsePhpIniSize(ini_get('post_max_size'));
    return min($phpMaxUpload, $phpPostMax);
}

/**
 * Calcule la taille totale d'un répertoire de manière récursive
 * @param string $directory Chemin du répertoire
 * @return int Taille totale en octets
 */
function getDirectorySize($directory) {
    if (!is_dir($directory)) {
        return 0;
    }
    
    $size = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    
    return $size;
}