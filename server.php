<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(400);
    echo "Run this file from CLI: php server.php [port]\n";
    exit(1);
}

set_time_limit(0);
error_reporting(E_ALL);

$host = '0.0.0.0';
$port = isset($argv[1]) && ctype_digit((string)$argv[1]) ? (int)$argv[1] : 8080;

$server = stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "WebSocket server failed: {$errstr} ({$errno})\n");
    exit(1);
}

stream_set_blocking($server, false);
fwrite(STDOUT, "WebSocket server listening on {$host}:{$port}\n");

$clients = [];

function countUserConnections(array $clients, int $userId): int
{
    $count = 0;
    foreach ($clients as $client) {
        if (($client['user_id'] ?? 0) === $userId) {
            $count++;
        }
    }
    return $count;
}

function parseRoomUsers(string $room): array
{
    $parts = array_filter(explode(':', $room), static fn($part) => ctype_digit((string)$part));
    return array_map('intval', $parts);
}

function disconnectClient(array &$clients, int $clientId): void
{
    if (!isset($clients[$clientId])) {
        return;
    }

    @fclose($clients[$clientId]['socket']);
    unset($clients[$clientId]);
}

function performHandshake($socket, string $buffer): bool
{
    if (!preg_match('/Sec-WebSocket-Key:\s*(.+)\r\n/i', $buffer, $matches)) {
        return false;
    }

    $key = trim($matches[1]);
    $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

    $response = "HTTP/1.1 101 Switching Protocols\r\n";
    $response .= "Upgrade: websocket\r\n";
    $response .= "Connection: Upgrade\r\n";
    $response .= "Sec-WebSocket-Accept: {$accept}\r\n\r\n";

    return @fwrite($socket, $response) !== false;
}

function encodeFrame(string $payload, int $opcode = 0x1): string
{
    $finAndOpcode = 0x80 | ($opcode & 0x0F);
    $length = strlen($payload);

    if ($length <= 125) {
        return chr($finAndOpcode) . chr($length) . $payload;
    }

    if ($length <= 65535) {
        return chr($finAndOpcode) . chr(126) . pack('n', $length) . $payload;
    }

    return chr($finAndOpcode) . chr(127) . pack('NN', 0, $length) . $payload;
}

function decodeFrame(string $data): ?array
{
    if (strlen($data) < 2) {
        return null;
    }

    $first = ord($data[0]);
    $second = ord($data[1]);
    $opcode = $first & 0x0F;
    $masked = ($second & 0x80) === 0x80;
    $length = $second & 0x7F;
    $offset = 2;

    if ($length === 126) {
        if (strlen($data) < $offset + 2) {
            return null;
        }
        $length = unpack('n', substr($data, $offset, 2))[1];
        $offset += 2;
    } elseif ($length === 127) {
        if (strlen($data) < $offset + 8) {
            return null;
        }
        $parts = unpack('N2', substr($data, $offset, 8));
        $length = ($parts[1] << 32) | $parts[2];
        $offset += 8;
    }

    if ($masked) {
        if (strlen($data) < $offset + 4 + $length) {
            return null;
        }

        $mask = substr($data, $offset, 4);
        $offset += 4;
        $payload = substr($data, $offset, $length);
        $decoded = '';

        for ($i = 0; $i < $length; $i++) {
            $decoded .= $payload[$i] ^ $mask[$i % 4];
        }
    } else {
        if (strlen($data) < $offset + $length) {
            return null;
        }
        $decoded = substr($data, $offset, $length);
    }

    return [
        'opcode' => $opcode,
        'payload' => $decoded,
    ];
}

function broadcastToRoom(array $clients, string $room, array $payload, ?int $excludeClientId = null): void
{
    $encoded = encodeFrame(json_encode($payload));

    foreach ($clients as $clientId => $client) {
        if ($excludeClientId !== null && $clientId === $excludeClientId) {
            continue;
        }

        if (($client['room'] ?? null) !== $room) {
            continue;
        }

        @fwrite($client['socket'], $encoded);
    }
}

function sendToClient($socket, array $payload): void
{
    @fwrite($socket, encodeFrame(json_encode($payload)));
}

while (true) {
    $readSockets = [$server];
    foreach ($clients as $client) {
        $readSockets[] = $client['socket'];
    }

    $write = null;
    $except = null;
    $selected = @stream_select($readSockets, $write, $except, 1);
    if ($selected === false) {
        continue;
    }

    foreach ($readSockets as $socket) {
        if ($socket === $server) {
            $connection = @stream_socket_accept($server, 0);
            if (!$connection) {
                continue;
            }

            stream_set_blocking($connection, false);
            $clientId = (int)$connection;
            $clients[$clientId] = [
                'socket' => $connection,
                'handshake' => false,
                'room' => null,
                'user_id' => null,
            ];
            continue;
        }

        $clientId = (int)$socket;
        if (!isset($clients[$clientId])) {
            continue;
        }

        $buffer = @fread($socket, 8192);
        if ($buffer === false || $buffer === '') {
            $leaving = $clients[$clientId];
            disconnectClient($clients, $clientId);

            $leavingUserId = (int)($leaving['user_id'] ?? 0);
            $leavingRoom = (string)($leaving['room'] ?? '');
            if ($leavingUserId > 0 && $leavingRoom !== '' && countUserConnections($clients, $leavingUserId) === 0) {
                broadcastToRoom($clients, $leavingRoom, [
                    'type' => 'typing:update',
                    'room' => $leavingRoom,
                    'user_id' => $leavingUserId,
                    'typing' => false,
                    'timestamp' => time(),
                ]);

                broadcastToRoom($clients, $leavingRoom, [
                    'type' => 'presence:update',
                    'room' => $leavingRoom,
                    'user_id' => $leavingUserId,
                    'online' => false,
                    'timestamp' => time(),
                ]);
            }
            continue;
        }

        if (!$clients[$clientId]['handshake']) {
            $ok = performHandshake($socket, $buffer);
            if (!$ok) {
                disconnectClient($clients, $clientId);
                continue;
            }

            $clients[$clientId]['handshake'] = true;
            continue;
        }

        $frame = decodeFrame($buffer);
        if ($frame === null) {
            continue;
        }

        if ($frame['opcode'] === 0x8) {
            $leaving = $clients[$clientId];
            disconnectClient($clients, $clientId);

            $leavingUserId = (int)($leaving['user_id'] ?? 0);
            $leavingRoom = (string)($leaving['room'] ?? '');
            if ($leavingUserId > 0 && $leavingRoom !== '' && countUserConnections($clients, $leavingUserId) === 0) {
                broadcastToRoom($clients, $leavingRoom, [
                    'type' => 'typing:update',
                    'room' => $leavingRoom,
                    'user_id' => $leavingUserId,
                    'typing' => false,
                    'timestamp' => time(),
                ]);

                broadcastToRoom($clients, $leavingRoom, [
                    'type' => 'presence:update',
                    'room' => $leavingRoom,
                    'user_id' => $leavingUserId,
                    'online' => false,
                    'timestamp' => time(),
                ]);
            }
            continue;
        }

        if ($frame['opcode'] === 0x9) {
            @fwrite($socket, encodeFrame($frame['payload'], 0xA));
            continue;
        }

        if ($frame['opcode'] !== 0x1) {
            continue;
        }

        $payload = json_decode($frame['payload'], true);
        if (!is_array($payload) || empty($payload['type'])) {
            continue;
        }

        if ($payload['type'] === 'subscribe') {
            $room = isset($payload['room']) ? preg_replace('/[^0-9:]/', '', (string)$payload['room']) : '';
            if ($room === '') {
                continue;
            }

            $clients[$clientId]['room'] = $room;
            $clients[$clientId]['user_id'] = isset($payload['user_id']) ? (int)$payload['user_id'] : null;
            fwrite(STDOUT, "client {$clientId} subscribed room {$room}\n");

            $roomUsers = parseRoomUsers($room);
            $presence = [];
            foreach ($roomUsers as $roomUserId) {
                $presence[(string)$roomUserId] = countUserConnections($clients, $roomUserId) > 0;
            }

            sendToClient($socket, [
                'type' => 'subscribed',
                'room' => $room,
                'client_id' => $clientId,
                'timestamp' => time(),
            ]);

            sendToClient($socket, [
                'type' => 'presence:snapshot',
                'room' => $room,
                'users' => $presence,
                'timestamp' => time(),
            ]);

            $userId = (int)($clients[$clientId]['user_id'] ?? 0);
            if ($userId > 0) {
                broadcastToRoom($clients, $room, [
                    'type' => 'presence:update',
                    'room' => $room,
                    'user_id' => $userId,
                    'online' => true,
                    'timestamp' => time(),
                ]);
            }
            continue;
        }

        if ($payload['type'] === 'chat:update') {
            $room = isset($payload['room']) ? preg_replace('/[^0-9:]/', '', (string)$payload['room']) : '';
            if ($room === '') {
                continue;
            }

            if (($clients[$clientId]['room'] ?? null) !== $room) {
                continue;
            }

            fwrite(STDOUT, "room {$room} event " . ($payload['event'] ?? 'refresh') . " from client {$clientId}\n");
            broadcastToRoom($clients, $room, [
                'type' => 'chat:update',
                'room' => $room,
                'event' => $payload['event'] ?? 'refresh',
                'sender_id' => $clients[$clientId]['user_id'],
                'timestamp' => time(),
            ]);
            continue;
        }

        if ($payload['type'] === 'typing:update') {
            $room = isset($payload['room']) ? preg_replace('/[^0-9:]/', '', (string)$payload['room']) : '';
            if ($room === '') {
                continue;
            }

            if (($clients[$clientId]['room'] ?? null) !== $room) {
                continue;
            }

            broadcastToRoom($clients, $room, [
                'type' => 'typing:update',
                'room' => $room,
                'user_id' => $clients[$clientId]['user_id'],
                'typing' => !empty($payload['typing']),
                'timestamp' => time(),
            ]);
        }
    }
}
