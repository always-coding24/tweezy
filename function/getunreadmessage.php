<?php
include "config.php";
include 'f_auth.php';
function getunreadmessage()
{
    global $pdo;
    $unread =  0;
    $stmt = $pdo->prepare(" SELECT 
            u.id ,
            (SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND sender_id = u.id AND seen IS NULL) AS unread
        FROM 
            users u
        LEFT JOIN 
            messages m ON ( u.id = m.recipient_id)
        WHERE u.id != ?
        ");
    $stmt->execute([$_SESSION["user_id"], $_SESSION["user_id"]]);
    $info = $stmt->fetchAll();
    // print_r($friend_info);
    if (!empty($info)) {
        foreach ($info as $row) {
            # code...


            $unread +=  $row["unread"];
        }
    }
    return $unread;
}
echo getunreadmessage();
