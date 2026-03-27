<?php
// Diagnostic SMTP spécialisé pour serveur Plesk
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 Diagnostic SMTP Plesk</h2>";
echo "<p><strong>Date :</strong> " . date('Y-m-d H:i:s') . "</p>";

try {
    // Charger la configuration
    require_once '../config/database.php';
    require_once '../config/config.php';
    
    $config = Config::getInstance();
    
    echo "<h3>📋 Informations Serveur</h3>";
    echo "<ul>";
    echo "<li><strong>PHP Version :</strong> " . PHP_VERSION . "</li>";
    echo "<li><strong>OS :</strong> " . PHP_OS . "</li>";
    echo "<li><strong>Extensions :</strong> ";
    echo (extension_loaded('curl') ? '✅curl ' : '❌curl ');
    echo (extension_loaded('openssl') ? '✅openssl ' : '❌openssl ');
    echo (extension_loaded('sockets') ? '✅sockets ' : '❌sockets ');
    echo "</li>";
    echo "<li><strong>Max execution time :</strong> " . ini_get('max_execution_time') . "s</li>";
    echo "<li><strong>Memory limit :</strong> " . ini_get('memory_limit') . "</li>";
    echo "</ul>";
    
    echo "<h3>🌐 Test DNS</h3>";
    $hosts = [
        'smtp.office365.com',
        'outlook.office365.com',
        'login.microsoftonline.com'
    ];
    
    foreach ($hosts as $host) {
        $ip = gethostbyname($host);
        if ($ip !== $host) {
            echo "✅ $host → $ip<br>";
        } else {
            echo "❌ $host → Résolution DNS échouée<br>";
        }
    }
    
    echo "<h3>🔌 Test de Ports SMTP</h3>";
    
    // Test des ports SMTP avec timeout court
    $ports = [
        ['smtp.office365.com', 587, 'TLS/STARTTLS'],
        ['smtp.office365.com', 465, 'SSL/TLS'],
        ['outlook.office365.com', 587, 'TLS/STARTTLS'],
        ['outlook.office365.com', 465, 'SSL/TLS']
    ];
    
    foreach ($ports as $test) {
        list($host, $port, $type) = $test;
        echo "Test $host:$port ($type) : ";
        
        $start = microtime(true);
        $connection = @fsockopen($host, $port, $errno, $errstr, 10);
        $end = microtime(true);
        $time = round(($end - $start) * 1000, 2);
        
        if ($connection) {
            echo "✅ Port ouvert ({$time}ms)<br>";
            fclose($connection);
        } else {
            echo "❌ Port fermé/bloqué (Code: $errno - $errstr)<br>";
        }
    }
    
    echo "<h3>📧 Test SMTP avec cURL</h3>";
    
    // Test SMTP avec cURL (plus fiable sur Plesk)
    $smtpHosts = [
        'smtp.office365.com:587',
        'smtp.office365.com:465',
        'outlook.office365.com:587'
    ];
    
    foreach ($smtpHosts as $smtpHost) {
        echo "Test cURL $smtpHost : ";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "smtp://$smtpHost");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
        
        curl_close($ch);
        
        if ($error) {
            echo "❌ Erreur: $error<br>";
        } else {
            echo "✅ Connexion réussie ({$connectTime}s)<br>";
        }
    }
    
    echo "<h3>🔐 Test OAuth2 Endpoints</h3>";
    
    $oauth2Endpoints = [
        'https://login.microsoftonline.com/common/v2.0/.well-known/openid_configuration',
        'https://graph.microsoft.com/v1.0/me'
    ];
    
    foreach ($oauth2Endpoints as $endpoint) {
        echo "Test $endpoint : ";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
        
        curl_close($ch);
        
        if ($error) {
            echo "❌ Erreur: $error<br>";
        } else {
            echo "✅ Code: $httpCode | Temps: {$connectTime}s<br>";
        }
    }
    
    echo "<h3>📋 Configuration OAuth2</h3>";
    echo "<ul>";
    echo "<li><strong>OAuth2 activé :</strong> " . ($config->get('oauth2_enabled') ? '✅ Oui' : '❌ Non') . "</li>";
    echo "<li><strong>Mail Host :</strong> " . $config->get('mail_host', 'Non configuré') . "</li>";
    echo "<li><strong>Mail Port :</strong> " . $config->get('mail_port', 'Non configuré') . "</li>";
    echo "<li><strong>Mail Encryption :</strong> " . $config->get('mail_encryption', 'Non configuré') . "</li>";
    echo "</ul>";
    
    echo "<h3>💡 Recommandations Plesk</h3>";
    echo "<ul>";
    echo "<li><strong>Firewall Plesk :</strong> Vérifiez les règles de pare-feu dans Plesk</li>";
    echo "<li><strong>Ports sortants :</strong> Assurez-vous que les ports 587 et 465 sont ouverts en sortie</li>";
    echo "<li><strong>SMTP Relais :</strong> Considérez l'utilisation du SMTP relais Plesk</li>";
    echo "<li><strong>Logs Plesk :</strong> Vérifiez les logs système pour les erreurs de connexion</li>";
    echo "</ul>";
    
    echo "<h3>🔧 Commandes Plesk utiles</h3>";
    echo "<pre>";
    echo "# Vérifier les règles de pare-feu\n";
    echo "plesk bin firewall --list\n\n";
    echo "# Vérifier les ports ouverts\n";
    echo "netstat -tuln | grep :587\n";
    echo "netstat -tuln | grep :465\n\n";
    echo "# Tester la connectivité SMTP\n";
    echo "telnet smtp.office365.com 587\n";
    echo "telnet smtp.office365.com 465\n";
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Erreur : " . $e->getMessage() . "</div>";
}

echo "<hr>";
echo "<p><em>⚠️ Supprimez ce fichier après diagnostic</em></p>";
?>
