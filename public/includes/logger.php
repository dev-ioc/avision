<?php
// includes/Logger.php
class Logger
{
    private static $instance = null;
    private $logDir;

    private function __construct()
    {
        $this->logDir = __DIR__ . '/../logs/';
        if (!file_exists($this->logDir)) {
            mkdir($this->logDir, 0777, true);
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function write($message, $data = null, $type = 'info')
    {
        $logFile = $this->logDir . 'excel_save_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');

        $logEntry = "[{$timestamp}] [{$type}] " . $message;

        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $logEntry .= "\n" . print_r($data, true);
            } else {
                $logEntry .= " " . $data;
            }
        }

        $logEntry .= "\n" . str_repeat('-', 80) . "\n";

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function debug($message, $data = null)
    {
        $this->write("DEBUG: " . $message, $data, 'DEBUG');
    }

    public function error($message, $error = null)
    {
        $this->write("ERREUR: " . $message, $error, 'ERROR');
    }

    public function info($message, $data = null)
    {
        $this->write("INFO: " . $message, $data, 'INFO');
    }
}

// Fonctions helper pour faciliter l'utilisation
if (!function_exists('log_debug')) {
    function log_debug($message, $data = null)
    {
        Logger::getInstance()->debug($message, $data);
    }
}

if (!function_exists('log_error')) {
    function log_error($message, $error = null)
    {
        Logger::getInstance()->error($message, $error);
    }
}

if (!function_exists('log_info')) {
    function log_info($message, $data = null)
    {
        Logger::getInstance()->info($message, $data);
    }
}
?>