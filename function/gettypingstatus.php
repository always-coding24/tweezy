<?php
include "config.php";
include "time.php";
include 'f_auth.php';
function gettypingstatus($user = null)
{
    global $pdo;
    if (empty($user)) {
        $user = $_SESSION["user_id"];
    }
    $friendstatus = $pdo->prepare("SELECT  `typing_to`  FROM users WHERE id = ? ");
    $friendstatus->execute([$user]);
    $status = $friendstatus->fetchAll();
    if (!empty($status)) {
        foreach ($status as $row) {

            $typing_to =  $row["typing_to"];
        }

        if ($typing_to === $_SESSION["user_id"]) {
            echo  'true';
        }
    }
}
if (!empty($_GET['u'])) {
    gettypingstatus($_GET['u']);
} else  gettypingstatus();
