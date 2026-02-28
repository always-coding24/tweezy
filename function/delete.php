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

if (empty($_POST['message_id']) || !ctype_digit((string)$_POST['message_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid message id',
    ]);
    exit;
}

$message_id = (int)$_POST['message_id'];
$user_id = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT sender_id FROM messages WHERE message_id = ?");
    $stmt->execute([$message_id]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$message) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Message not found',
        ]);
        exit;
    }

    if ((int)$message['sender_id'] !== $user_id) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Not allowed',
        ]);
        exit;
    }

    $pdo->beginTransaction();

    // Prevent dangling reply references when a replied message is removed.
    $clearReplies = $pdo->prepare("UPDATE messages SET reply = NULL WHERE reply = ?");
    $clearReplies->execute([$message_id]);

    $delete = $pdo->prepare("DELETE FROM messages WHERE message_id = ? AND sender_id = ?");
    $delete->execute([$message_id, $user_id]);

    if ($delete->rowCount() < 1) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete message',
        ]);
        exit;
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Message deleted',
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
    ]);
}
