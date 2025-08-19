<?php 
if (isset($_SESSION['user_id'])) {
   $upsta = $User->customQuery("UPDATE `users` SET `status` = now() WHERE id = '{$_SESSION['user_id']}'");
}else {
    exit();
}