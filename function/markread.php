<?php
include "config.php";
include "f_auth.php";

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed',
    ]);
    exit;
}

if (empty($_POST['sender_id']) || !ctype_digit((string)$_POST['sender_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid sender id',
    ]);
    exit;
}

$senderId = (int)$_POST['sender_id'];
$recipientId = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("UPDATE messages SET seen = NOW() WHERE seen IS NULL AND recipient_id = ? AND sender_id = ?");
    $stmt->execute([$recipientId, $senderId]);

    echo json_encode([
        'success' => true,
        'updated' => $stmt->rowCount(),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
    ]);
}
