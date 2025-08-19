<?php
include "config.php";
include 'f_auth.php';
function typingstatus()
{
    global $mysqli, $pdo;
    if (!empty($_POST["recipient_id"])) {
        $recipient_id = $_POST["recipient_id"];

        if (is_numeric($recipient_id)) {
            $friend = $pdo->prepare("SELECT `username`, `image` FROM users WHERE id = ?");
            $friend->execute([$recipient_id]);
            $friend_info = $friend->fetchAll();

            if (!empty($friend_info)) {
                $sender_id = $_SESSION['user_id'];

                if (isset($_POST['nottyping'])) {
                    $stmt = $pdo->prepare("UPDATE `users` SET `typing_to` = null WHERE `users`.`id` = ?");
                    $stmt->execute([$sender_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE `users` SET `typing_to` = ? WHERE `users`.`id` = ?");
                    $stmt->execute([$recipient_id, $sender_id]);
                }
            }
        }
    }
}

typingstatus();
