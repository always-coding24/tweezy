<?php
include "config.php";
include 'f_auth.php';
function sendmessage()
{
    global $mysqli, $pdo;
    if (!empty($_POST["message"]) and !empty($_POST["recipient_id"])) {
        $recipient_id = $_POST["recipient_id"];

        if (is_numeric($recipient_id)) {

            $friend = $pdo->prepare("SELECT  `username`,  `image`  FROM users WHERE id = ? ");
            $friend->execute([$recipient_id]);
            $friend_info = $friend->fetchAll();
            // print_r($friend_info);
            if (!empty($friend_info)) {

                $sender_id = $_SESSION['user_id'];
                $message = trim($_POST['message']);

                $stmt = $mysqli->prepare('INSERT INTO `messages`( `message_id`, `sender_id`, `recipient_id`, `message`, `type`, `class`, `reply`,    `sent_at` ) VALUES( NULL, ?, ?, ?, null, NULL, NULL,    CURRENT_TIMESTAMP())');
                $stmt->bind_param("sss", $sender_id, $recipient_id, $message);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}
sendmessage();
