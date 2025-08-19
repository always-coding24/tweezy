<?php
include "config.php";
include 'f_auth.php';
include "time.php";

function fetchstatus($user = null)
{
    if (empty($user)) {
        $user = $_SESSION["user_id"];
    }
    global $pdo;
    $friendstatus = $pdo->prepare("SELECT  `status`  FROM users WHERE id = ? ");
    $friendstatus->execute([$user]);
    $status = $friendstatus->fetchAll();
    if (!empty($status)) {
        foreach ($status as $row) {

            $friend_status =  $row["status"];
        }
        return setstatus(strtotime($friend_status));
    }
}

if (!empty($_GET['u'])) {
    echo fetchstatus($_GET['u']);
} else echo fetchstatus();
