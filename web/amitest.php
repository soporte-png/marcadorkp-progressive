<?php
// ################# CONFIGURACIÓN #################
$host = '127.0.0.1';
$port = 5038;
$user = 'admin';     // Cambia esto
$secret = 'vonaGe3102iP'; // Cambia esto
$queue = '500';                // La cola que queremos consultar
// #################################################

// Desactivar límite de tiempo de ejecución
set_time_limit(0);

// Conectar al socket de AMI
$socket = @fsockopen($host, $port, $errno, $errstr, 10);
if (!$socket) {
    die("Error al conectar a AMI: $errstr ($errno)\n");
} else {
    echo "Conexión a AMI exitosa.\n\n";
}

// Establecer un timeout para las lecturas
stream_set_timeout($socket, 3);

// --- 1. Autenticación ---
fputs($socket, "Action: Login\r\n");
fputs($socket, "Username: {$user}\r\n");
fputs($socket, "Secret: {$secret}\r\n\r\n");
usleep(100000); // Pequeña pausa

// --- 2. Enviar el comando que queremos probar ---
echo ">>> Enviando comando 'QueueStatus' para la cola {$queue}...\n\n";
fputs($socket, "Action: QueueStatus\r\n");
fputs($socket, "Queue: {$queue}\r\n\r\n");

// --- 3. Leer y mostrar la respuesta completa ---
echo "<<< Respuesta de Asterisk:\n";
echo "----------------------------------------\n";
$response = '';
while (($line = fgets($socket)) !== false) {
    // Imprimimos cada línea que recibimos
    $response .= $line;
    echo $line;

    // El evento 'QueueStatusComplete' marca el final de la respuesta
    if (strpos($line, 'Event: QueueStatusComplete') !== false) {
        break;
    }
}
echo "----------------------------------------\n\n";

// --- 4. Desconexión ---
fputs($socket, "Action: Logoff\r\n\r\n");
fclose($socket);

echo "Prueba finalizada. Desconectado de AMI.\n";

?>
