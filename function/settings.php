
<?php
include "config.php";
include 'f_auth.php';
// change profile pic of a user
function profile_pic()
{
    global $mysqli;
    $target_dir = "assets/img/users/";
    $user_id = $_SESSION['user_id'];
    $target_file = mysqli_real_escape_string($mysqli, $target_dir . bin2hex(random_bytes(10)));
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    // Check if image file is a actual image or fake image
    if (isset($_POST["submit"])) {
        $check = getimagesize($_FILES["profile"]["tmp_name"]);

        $uploadOk = 1;
    } else {

        $uploadOk = 0;
    }

    // Check if file already exists

    // Check file size
    if ($_FILES["profile"]["size"] > 500000) {
        alert("Sorry, your file is too large.");
        $uploadOk = 0;
    }
    // Allow certain file formats
    if (
        $imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
        && $imageFileType != "gif"
    ) {
        alert("Sorry, only JPG, JPEG, PNG & GIF files are allowed.");
        $uploadOk = 0;
    }
    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 0) {
        alert("Sorry, your file was not uploaded.");
        // if everything is ok, try to upload file
    } else {
        if (move_uploaded_file($_FILES["profile"]["tmp_name"], $target_file)) {
            run("UPDATE `users` SET `image` = '$target_file' WHERE `users`.`id` =$user_id ;");
        } else {
            alert("Sorry, there was an error uploading your file.");
        }
    }
}
// this change profile infomation
//todo 
function profileinfo($name, $email, $phone, $bio)
{
    // i made it easy
    function easymove($where, $arg)
    {
        global $mysqli;

        $set =  mysqli_real_escape_string($mysqli, $arg);
        run("UPDATE `users` SET $where  = '$set' WHERE id = {$_SESSION['user_id']}");
    }
    global $mysqli;
    if (!empty($bio)) {
        easymove('bio', $bio);
    }
    if (!empty($email)) {
        easymove('email', $email);
    }
    if (!empty($phone)) {
        easymove('phone', $phone);
    }
    if (!empty($name)) {
        easymove('username', $name);
    }
}
if (isset($_GET['f'])) {
    if ($_GET['f'] == "profile") {
        # code...
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            profile_pic();
        }
    } elseif ($_GET['f'] == "profileinfo") {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            profileinfo($_POST["name"], $_POST["email"], $_POST["phone"], $_POST["bio"]);
        }
    }
}
header("location:../chat.php");
