<?php
/**
 * Bootstrap del Sistema
 * Sistema Cyberhole            // 5. Configurar JWT

 * 
 * Punto central de inicialización del entorno del sistema.
 * Carga configuraciones, establece zona horaria, configura errores y headers.
 */

// Verificar versión de PHP
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die('Error: Este sistema requiere PHP 7.4.0 o superior. Versión actual: ' . PHP_VERSION);
}

// Establecer zona horaria por defecto
date_default_timezone_set('America/Mexico_City');

// Iniciar buffer de salida
ob_start();

// Configurar manejo de errores personalizado
error_reporting(E_ALL);

/**
 * Clase principal de Bootstrap
 */
class Bootstrap {
    private static $initialized = false;
    private static $startTime;
    private static $memoryStart;
    
    /**
     * Inicializa el sistema completo
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }
        
        self::$startTime = microtime(true);
        self::$memoryStart = memory_get_usage();
        
        try {
            // 1. Cargar variables de entorno
            self::loadEnvironment();
            
            // 2. Configurar entorno de ejecución
            self::configureEnvironment();
            
            // 3. Configurar manejo de errores
            self::configureErrorHandling();
            
            // 4. Configurar sesiones seguras
            self::configureSessions();
            
            // 5. Establecer headers de seguridad
            self::setSecurityHeaders();
            
            // 6. Configurar JWT
            self::configureJWT();
            
            // 7. Configurar base de datos
            self::configurateDatabase();
            
            // 8. Configurar autoloader
            self::configureAutoloader();
            
            // 9. Inicializar logging
            self::initializeLogging();
            
            self::$initialized = true;
            self::logBootstrap();
            
        } catch (Exception $e) {
            self::handleBootstrapError($e);
        }
    }
    
    /**
     * Carga las variables de entorno
     */
    private static function loadEnvironment() {
        $envFile = __DIR__ . '/env.php';
        if (file_exists($envFile)) {
            require_once $envFile;
        } else {
            throw new Exception("Archivo de configuración de entorno no encontrado");
        }
    }
    
    /**
     * Configura el entorno de ejecución
     */
    private static function configureEnvironment() {
        $environment = env('APP_ENV', 'development');
        $debug = env('APP_DEBUG', true);
        
        // Configurar según el entorno
        switch ($environment) {
            case 'production':
                ini_set('display_errors', '0');
                ini_set('display_startup_errors', '0');
                ini_set('log_errors', '1');
                break;
                
            case 'testing':
                ini_set('display_errors', '1');
                ini_set('display_startup_errors', '1');
                ini_set('log_errors', '1');
                break;
                
            case 'development':
            default:
                ini_set('display_errors', $debug ? '1' : '0');
                ini_set('display_startup_errors', $debug ? '1' : '0');
                ini_set('log_errors', '1');
                break;
        }
        
        // Configurar límites de memoria y tiempo
        ini_set('memory_limit', env('PHP_MEMORY_LIMIT', '256M'));
        ini_set('max_execution_time', env('PHP_MAX_EXECUTION_TIME', '30'));
        
        // Configurar zona horaria
        $timezone = env('TIMEZONE', 'America/Mexico_City');
        date_default_timezone_set($timezone);
        
        // Configurar locale
        setlocale(LC_TIME, 'es_MX.UTF-8', 'es_MX', 'Spanish_Mexico');
        setlocale(LC_MONETARY, 'es_MX.UTF-8', 'es_MX', 'Spanish_Mexico');
    }
    
    /**
     * Configura el manejo de errores personalizado
     */
    private static function configureErrorHandling() {
        // Handler para errores fatales
        register_shutdown_function([self::class, 'handleShutdown']);
        
        // Handler para errores no fatales
        set_error_handler([self::class, 'handleError']);
        
        // Handler para excepciones no capturadas
        set_exception_handler([self::class, 'handleException']);
    }
    
    /**
     * Configura las sesiones con parámetros de seguridad
     */
    private static function configureSessions() {
        $securityFile = __DIR__ . '/SecurityConfig.php';
        if (file_exists($securityFile)) {
            require_once $securityFile;
            SecurityConfig::initialize();
        }
    }
    
    /**
     * Establece headers de seguridad
     */
    private static function setSecurityHeaders() {
        $securityFile = __DIR__ . '/SecurityConfig.php';
        if (file_exists($securityFile)) {
            require_once $securityFile;
            // Los headers de seguridad ahora se configuran directamente
        }
        
        // Headers adicionales
        if (!headers_sent()) {
            header('X-Powered-By: Cyberhole Condominios System');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }
    
    /**
     * Configura JWT
     */
    private static function configureJWT() {
        $jwtFile = __DIR__ . '/jwt.php';
        if (file_exists($jwtFile)) {
            require_once $jwtFile;
            try {
                JWTConfig::init();
            } catch (Exception $e) {
                error_log("[BOOTSTRAP ERROR] Error configurando JWT: " . $e->getMessage());
                if (env('APP_ENV') === 'production') {
                    die('Error de configuración del sistema');
                }
            }
        }
    }

    /**
     * Configura la conexión a la base de datos
     */
    private static function configurateDatabase() {
        $dbFile = __DIR__ . '/database.php';
        if (file_exists($dbFile)) {
            require_once $dbFile;
            // En desarrollo, no forzar conexión durante bootstrap
            if (env('APP_ENV') === 'development') {
                error_log("[BOOTSTRAP] Base de datos configurada - conexión diferida para desarrollo");
                return;
            }
            
            // Probar conexión solo en producción/testing
            try {
                $db = DatabaseConfig::getInstance();
                $connection = $db->getConnection();
                if (!$db->isConnected()) {
                    throw new Exception("No se pudo establecer conexión con la base de datos");
                }
            } catch (Exception $e) {
                error_log("[BOOTSTRAP ERROR] Error de base de datos: " . $e->getMessage());
                if (env('APP_ENV') === 'production') {
                    throw new Exception("Error de conexión del sistema");
                } else {
                    // En testing, registrar pero continuar
                    error_log("[BOOTSTRAP WARNING] Conexión de base de datos no disponible en modo testing");
                }
            }
        }
    }
    
    /**
     * Configura el autoloader para clases
     */
    private static function configureAutoloader() {
        spl_autoload_register(function ($className) {
            $directories = [
                __DIR__ . '/../models/',
                __DIR__ . '/../services/',
                __DIR__ . '/../middlewares/',
                __DIR__ . '/../utils/'
            ];
            
            foreach ($directories as $directory) {
                $file = $directory . $className . '.php';
                if (file_exists($file)) {
                    require_once $file;
                    return;
                }
            }
        });
    }
    
    /**
     * Inicializa el sistema de logging
     */
    private static function initializeLogging() {
        $logDir = __DIR__ . '/../logs/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Configurar archivo de log de errores PHP
        ini_set('error_log', $logDir . 'php_errors.log');
        
        // Rotar logs si es necesario
        self::rotateLogs($logDir);
    }
    
    /**
     * Rota los archivos de log antiguos
     */
    private static function rotateLogs($logDir) {
        $maxSize = 10 * 1024 * 1024; // 10MB
        $logFiles = ['php_errors.log', 'app.log', 'security.log'];
        
        foreach ($logFiles as $logFile) {
            $filePath = $logDir . $logFile;
            if (file_exists($filePath) && filesize($filePath) > $maxSize) {
                $backupFile = $logDir . $logFile . '.' . date('Y-m-d-H-i-s') . '.bak';
                rename($filePath, $backupFile);
                touch($filePath);
                chmod($filePath, 0644);
            }
        }
    }
    
    /**
     * Registra el inicio exitoso del bootstrap
     */
    private static function logBootstrap() {
        $executionTime = (microtime(true) - self::$startTime) * 1000;
        $memoryUsage = memory_get_usage() - self::$memoryStart;
        
        $message = sprintf(
            "[BOOTSTRAP] Sistema inicializado - Tiempo: %.2fms, Memoria: %s, Entorno: %s",
            $executionTime,
            self::formatBytes($memoryUsage),
            env('APP_ENV', 'unknown')
        );
        
        error_log($message);
    }
    
    /**
     * Maneja errores del proceso de bootstrap
     */
    private static function handleBootstrapError($e) {
        $message = "[BOOTSTRAP ERROR] " . $e->getMessage();
        error_log($message);
        
        if (env('APP_ENV') === 'production') {
            http_response_code(500);
            die('Error interno del sistema. Por favor, contacte al administrador.');
        } else {
            http_response_code(500);
            die('<h1>Error de Bootstrap</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>');
        }
    }
    
    /**
     * Handler para errores de PHP
     */
    public static function handleError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorMessage = sprintf(
            "[PHP ERROR] %s en %s línea %d: %s",
            self::getSeverityName($severity),
            $file,
            $line,
            $message
        );
        
        error_log($errorMessage);
        
        if (env('APP_DEBUG', false)) {
            echo "<div style='background: #ffebee; border: 1px solid #f44336; padding: 10px; margin: 10px; border-radius: 4px;'>";
            echo "<strong>Error PHP:</strong> " . htmlspecialchars($message) . "<br>";
            echo "<strong>Archivo:</strong> " . htmlspecialchars($file) . "<br>";
            echo "<strong>Línea:</strong> " . $line;
            echo "</div>";
        }
        
        return true;
    }
    
    /**
     * Handler para excepciones no capturadas
     */
    public static function handleException($exception) {
        $message = sprintf(
            "[UNCAUGHT EXCEPTION] %s en %s línea %d: %s",
            get_class($exception),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getMessage()
        );
        
        error_log($message);
        
        http_response_code(500);
        
        if (env('APP_DEBUG', false)) {
            echo "<h1>Excepción no capturada</h1>";
            echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
            echo "<p><strong>Archivo:</strong> " . htmlspecialchars($exception->getFile()) . "</p>";
            echo "<p><strong>Línea:</strong> " . $exception->getLine() . "</p>";
            echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        } else {
            echo "<h1>Error interno del sistema</h1>";
            echo "<p>Ha ocurrido un error. Por favor, contacte al administrador.</p>";
        }
    }
    
    /**
     * Handler para shutdown del script
     */
    public static function handleShutdown() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $message = sprintf(
                "[FATAL ERROR] %s en %s línea %d: %s",
                self::getSeverityName($error['type']),
                $error['file'],
                $error['line'],
                $error['message']
            );
            
            error_log($message);
            
            if (!headers_sent()) {
                http_response_code(500);
            }
            
            if (env('APP_DEBUG', false)) {
                echo "<h1>Error Fatal</h1>";
                echo "<p>" . htmlspecialchars($error['message']) . "</p>";
            }
        }
    }
    
    /**
     * Obtiene el nombre del tipo de error
     */
    private static function getSeverityName($severity) {
        $severities = [
            E_ERROR => 'Error Fatal',
            E_WARNING => 'Advertencia',
            E_PARSE => 'Error de Sintaxis',
            E_NOTICE => 'Aviso',
            E_CORE_ERROR => 'Error del Core',
            E_CORE_WARNING => 'Advertencia del Core',
            E_COMPILE_ERROR => 'Error de Compilación',
            E_COMPILE_WARNING => 'Advertencia de Compilación',
            E_USER_ERROR => 'Error de Usuario',
            E_USER_WARNING => 'Advertencia de Usuario',
            E_USER_NOTICE => 'Aviso de Usuario',
            E_STRICT => 'Error Strict',
            E_RECOVERABLE_ERROR => 'Error Recuperable',
            E_DEPRECATED => 'Deprecado',
            E_USER_DEPRECATED => 'Deprecado por Usuario'
        ];
        
        return $severities[$severity] ?? 'Error Desconocido';
    }
    
    /**
     * Formatea bytes en formato legible
     */
    private static function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Verifica si el sistema está inicializado
     */
    public static function isInitialized() {
        return self::$initialized;
    }
    
    /**
     * Obtiene estadísticas del bootstrap
     */
    public static function getStats() {
        return [
            'initialized' => self::$initialized,
            'execution_time' => self::$startTime ? (microtime(true) - self::$startTime) * 1000 : 0,
            'memory_usage' => self::$memoryStart ? memory_get_usage() - self::$memoryStart : 0,
            'environment' => env('APP_ENV', 'unknown'),
            'debug_mode' => env('APP_DEBUG', false),
            'php_version' => PHP_VERSION,
            'timezone' => date_default_timezone_get()
        ];
    }
}

// Inicializar automáticamente si no se ha hecho
if (!Bootstrap::isInitialized()) {
    Bootstrap::init();
}

// Funciones helper globales
function app_env() {
    return env('APP_ENV', 'development');
}

function is_production() {
    return app_env() === 'production';
}

function is_development() {
    return app_env() === 'development';
}

function is_testing() {
    return app_env() === 'testing';
}

function app_debug() {
    return env('APP_DEBUG', false);
}

function bootstrap_stats() {
    return Bootstrap::getStats();
}