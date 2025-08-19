<?php
include "function/config.php";
include "function/auth.php";


?>


<!DOCTYPE html>
<html lang="en">
<!-- Head -->


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1, shrink-to-fit=no, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">

    <title>Messenger - 2.2.0</title>

    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/img/favicon/favicon.ico" type="image/x-icon">


    <!-- Template CSS -->
    <link class="css-lt" rel="stylesheet" href="assets/css/template.bundle.css" media="(prefers-color-scheme: light)">
    <link class="css-dk" rel="stylesheet" href="assets/css/template.dark.bundle.css" media="(prefers-color-scheme: dark)">

    <!-- Theme mode -->
    <script>
        if (localStorage.getItem('color-scheme')) {
            let scheme = localStorage.getItem('color-scheme');

            const LTCSS = document.querySelectorAll('link[class=css-lt]');
            const DKCSS = document.querySelectorAll('link[class=css-dk]');

            [...LTCSS].forEach((link) => {
                link.media = (scheme === 'light') ? 'all' : 'not all';
            });

            [...DKCSS].forEach((link) => {
                link.media = (scheme === 'dark') ? 'all' : 'not all';
            });
        }
        function cl(element) {
    document.querySelector(element).click()
}
    </script>
</head>
<body>

<?php include 'function/chatcontent.php';?>

</body>

<script src="assets/js/jquery.js"></script>
<!-- <script src="assets/js/bootstrap.min.js"></script> -->
<script src="assets/js/vendor.js" defer></script>
<script src="assets/js/ajax.js"></script>
<script src="assets/js/chat.js"></script> 
<script src="assets/js/template.js" ></script>
<script src="assets/alert/sweetalert2.js"></script> 
<script src="assets/js/push.min.js"></script> 
<script src="assets\js\misc.js"></script> 


</html>