<?php
/**
 * Punto de Entrada Principal - Sistema Cyberhole Condominios
 * 
 * Este archivo es el punto de entrada principal del sistema.
 * Inicializa el bootstrap, maneja el enrutamiento básico y 
 * dirige las solicitudes a los controladores apropiados.
 */

// Incluir el sistema de bootstrap
require_once __DIR__ . '/config/bootstrap.php';

try {
    // Obtener la ruta solicitada
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // Limpiar la URI de parámetros
    $route = strtok($requestUri, '?');
    $route = rtrim($route, '/') ?: '/';
    
    // Headers básicos de respuesta
    header('Content-Type: application/json; charset=utf-8');
    
    // Enrutamiento básico
    switch ($route) {
        case '/':
        case '/index':
            handleHome();
            break;
            
        case '/api/health':
            handleHealth();
            break;
            
        case '/api/info':
            handleInfo();
            break;
            
        default:
            if (str_starts_with($route, '/api/')) {
                handleApiRequest($route, $requestMethod);
            } else {
                handleNotFound();
            }
            break;
    }

} catch (Exception $e) {
    handleError($e);
}

/**
 * Maneja la página principal
 */
function handleHome() {
    header('Content-Type: text/html; charset=utf-8');
    
    $stats = bootstrap_stats();
    
    echo '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . env('APP_NAME', 'Cyberhole Condominios') . '</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; line-height: 1.6; }
        .header { text-align: center; margin-bottom: 30px; }
        .status { background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .info { background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .endpoint { background: #f9f9f9; padding: 10px; border-left: 4px solid #007cba; margin: 10px 0; }
        code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏢 ' . env('APP_NAME', 'Cyberhole Condominios') . '</h1>
        <p>Sistema de Gestión de Condominios</p>
    </div>
    
    <div class="status">
        <h3>✅ Sistema Iniciado Correctamente</h3>
        <ul>
            <li><strong>Entorno:</strong> ' . $stats['environment'] . '</li>
            <li><strong>Depuración:</strong> ' . ($stats['debug_mode'] ? 'Habilitada' : 'Deshabilitada') . '</li>
            <li><strong>Tiempo de carga:</strong> ' . round($stats['execution_time'], 2) . 'ms</li>
            <li><strong>Zona horaria:</strong> ' . $stats['timezone'] . '</li>
            <li><strong>PHP:</strong> ' . $stats['php_version'] . '</li>
        </ul>
    </div>
    
    <div class="info">
        <h3>🔧 API Endpoints Disponibles</h3>
        <div class="endpoint">
            <strong>GET /api/health</strong><br>
            <small>Verificación del estado del sistema</small>
        </div>
        <div class="endpoint">
            <strong>GET /api/info</strong><br>
            <small>Información detallada del sistema</small>
        </div>
    </div>
    
    <div class="info">
        <h3>📚 Documentación</h3>
        <p>Para más información sobre el uso del sistema, consulte la documentación de la API.</p>
        <p><strong>Hora actual del sistema:</strong> ' . date('Y-m-d H:i:s T') . '</p>
    </div>
</body>
</html>';
}

/**
 * Endpoint de salud del sistema
 */
function handleHealth() {
    $dbInfo = null;
    try {
        $dbInfo = getDatabaseInfo();
    } catch (Exception $e) {
        $dbInfo = ['error' => 'No se pudo conectar a la base de datos: ' . $e->getMessage()];
    }
    
    $response = [
        'status' => 'ok',
        'timestamp' => date('c'),
        'system' => [
            'app_name' => env('APP_NAME'),
            'version' => '1.0.0',
            'environment' => env('APP_ENV'),
            'debug' => env('APP_DEBUG'),
            'timezone' => date_default_timezone_get()
        ],
        'database' => $dbInfo,
        'bootstrap' => bootstrap_stats()
    ];
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Información detallada del sistema
 */
function handleInfo() {
    if (!env('APP_DEBUG')) {
        handleNotFound();
        return;
    }
    
    $response = [
        'system_info' => [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'loaded_extensions' => get_loaded_extensions(),
            'memory_usage' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => ini_get('memory_limit')
            ]
        ],
        'environment' => EnvironmentLoader::getStatus(),
        'bootstrap' => bootstrap_stats(),
        'request_info' => [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri' => $_SERVER['REQUEST_URI'] ?? '/',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]
    ];
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Maneja solicitudes API no implementadas
 */
function handleApiRequest($route, $method) {
    $response = [
        'success' => false,
        'message' => 'Endpoint no implementado',
        'route' => $route,
        'method' => $method,
        'available_endpoints' => [
            'GET /api/health',
            'GET /api/info'
        ]
    ];
    
    http_response_code(501);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Maneja rutas no encontradas
 */
function handleNotFound() {
    $response = [
        'success' => false,
        'message' => 'Ruta no encontrada',
        'code' => 404,
        'available_routes' => [
            '/',
            '/api/health',
            '/api/info'
        ]
    ];
    
    http_response_code(404);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Maneja errores del sistema
 */
function handleError($exception) {
    error_log("Error en index.php: " . $exception->getMessage());
    
    $response = [
        'success' => false,
        'message' => 'Error interno del servidor',
        'code' => 500
    ];
    
    if (env('APP_DEBUG')) {
        $response['debug'] = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
    }
    
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}