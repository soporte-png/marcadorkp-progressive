<?php
/**
 * Cliente sencillo para enviar acciones al AMI de Asterisk.
 */

function dialer_load_credentials(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $paths = [
        '/etc/progressive_dialer/credentials',
        __DIR__ . '/config/credentials',
        __DIR__ . '/../config/credentials'
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            $cached = parse_ini_file($path);
            break;
        }
    }

    if (!$cached) {
        throw new RuntimeException('No se encontró el archivo de credenciales.');
    }

    return $cached;
}

function ami_send_action(array $action): array
{
    $credentials = dialer_load_credentials();

    $host = $credentials['AMI_HOST'] ?? '127.0.0.1';
    $port = (int)($credentials['AMI_PORT'] ?? 5038);
    $user = $credentials['AMI_USER'] ?? '';
    $secret = $credentials['AMI_SECRET'] ?? '';

    $socket = @fsockopen($host, $port, $errno, $errstr, 3.0);
    if (!$socket) {
        throw new RuntimeException("No se pudo conectar al AMI: $errstr ($errno)");
    }
    stream_set_timeout($socket, 3);

    $login = "Action: Login\r\nUsername: {$user}\r\nSecret: {$secret}\r\nEvents: off\r\n\r\n";
    fwrite($socket, $login);
    $loginResponse = ami_read_response($socket);
    if (($loginResponse['Response'] ?? '') !== 'Success') {
        fclose($socket);
        $message = $loginResponse['Message'] ?? 'Error de autenticación';
        throw new RuntimeException('AMI Login failed: ' . $message);
    }

    $payload = [];
    foreach ($action as $key => $value) {
        $payload[] = $key . ': ' . $value;
    }
    fwrite($socket, implode("\r\n", $payload) . "\r\n\r\n");
    $response = ami_read_response($socket);

    fwrite($socket, "Action: Logoff\r\n\r\n");
    fclose($socket);

    return $response;
}

function ami_read_response($socket): array
{
    $result = [];
    while (!feof($socket)) {
        $line = fgets($socket, 4096);
        if ($line === false) {
            break;
        }
        $trimmed = rtrim($line, "\r\n");
        if ($trimmed === '') {
            if (!empty($result)) {
                break;
            }
            continue;
        }
        $parts = explode(':', $trimmed, 2);
        if (count($parts) === 2) {
            $result[$parts[0]] = ltrim($parts[1]);
        }
    }
    return $result;
}

function ami_queue_pause(string $queue, string $extension, bool $pause, string $reason = 'Disposition capture'): array
{
    $extension = trim($extension);
    if ($extension === '') {
        return ['success' => false, 'message' => 'Extensión inválida'];
    }

    $patterns = [
        "Local/{$extension}@from-queue/n",
        "PJSIP/{$extension}",
        "SIP/{$extension}",
        "Local/{$extension}@from-internal/n"
    ];

    $lastMessage = null;
    foreach ($patterns as $interface) {
        try {
            $response = ami_send_action([
                'Action' => 'QueuePause',
                'Interface' => $interface,
                'Queue' => $queue,
                'Paused' => $pause ? 'true' : 'false',
                'Reason' => $reason
            ]);
        } catch (Throwable $e) {
            $lastMessage = $e->getMessage();
            continue;
        }

        if (($response['Response'] ?? '') === 'Success') {
            return ['success' => true, 'interface' => $interface];
        }
        $lastMessage = $response['Message'] ?? 'Acción rechazada';
    }

    return ['success' => false, 'message' => $lastMessage ?? 'No se pudo ejecutar QueuePause'];
}
?>
