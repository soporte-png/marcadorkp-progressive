<?php
/**
 * Conexión segura a la base de datos
 * Lee las credenciales desde un archivo externo protegido
 */

// Rutas posibles para el archivo de credenciales
$credential_paths = [
    '/etc/progressive_dialer/credentials',
    __DIR__ . '/config/credentials',
    __DIR__ . '/../config/credentials'
];

$credentials = null;
foreach ($credential_paths as $path) {
    if (file_exists($path)) {
        $credentials = parse_ini_file($path);
        break;
    }
}

if (!$credentials) {
    die("Error crítico: No se encontró el archivo de credenciales. Consulte la documentación.");
}

$db_host = $credentials['DB_HOST'] ?? 'localhost';
$db_name = $credentials['DB_NAME'] ?? 'progressive_dialer';
$db_user = $credentials['DB_USER'] ?? 'root';
$db_pass = $credentials['DB_PASS'] ?? '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    error_log("Error de conexión a BD: " . $e->getMessage());
    die("Error: No se pudo conectar a la base de datos. Contacte al administrador.");
}
?>
