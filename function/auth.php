<?php

if (isset($_SESSION['user_id'])) {
  
    run("UPDATE `users` SET `status` = now() WHERE `users`.`id` = '{$_SESSION['user_id']}'  ");
} else {
    // sleep(5);
    header("Location:signin.php");
    exit();
}
