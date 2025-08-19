<?php
include "config.php";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['email'])) {
    $email = $_POST['email'];

    // Check if the email exists in your user database
    // Assuming you have a PDO connection $pdo
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Generate a unique token
        $token = bin2hex(random_bytes(50));

        // Store the token in the database
        $stmt = $pdo->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)");
        $stmt->execute([$email, $token]);
        file_put_contents("token.txt", $token);
        // Send the token to the user's email
        $resetLink = "http://yourwebsite.com/reset_password.php?token=" . $token;
        // mail($email, "Password Reset Request", "Click the following link to reset your password: " . $resetLink);

        echo '<script >
        function countdown(to) {
            console.log("vbfvf");
            let count = document.querySelector(".count")
            let min =0
            let sec =0
            setInterval(() => {
                sec++
                if (min <= to) {
                    
                    if(sec >= 60){
                        sec = 0
                        min++
                    } 
                }
                console.log(`${min}:${sec}`)   
            }, 1);
        }
        countdown(4)</script>';
    } else {
       alert("No account found with that email address.");
    }
}
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
    </script>
</head>

<body class="bg-light">

    <div class="container">
        <div class="row align-items-center justify-content-center min-vh-100 gx-0">

            <div class="col-12 col-md-5 col-lg-4">
                <div class="card card-shadow border-0">
                    <div class="card-body">
                        <form method="post" class="row g-6">
                            <div class="col-12">
                                <div class="text-center">
                                    <h3 class="fw-bold mb-2">Password Reset</h3>
                                    <p>Enter your email to reset password</p>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="email" name="email" class="form-control" id="resetpassword-email" placeholder="Email">
                                    <label for="resetpassword-email">Email</label>
                                </div>
                                <div class="count">The Token valid for 1 hour</div>
                            </div>

                            <div class="col-12">
                                <button class="btn btn-block btn-lg btn-primary w-100" type="submit">Send Reset Link</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Text -->
                <div class="text-center mt-8">
                    <p>Already have an account? <a href="signin.html">Sign in</a></p>
                </div>
            </div>
        </div> <!-- / .row -->
    </div>
    <script>
     
    </script>
    <!-- Scripts -->
    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/template.js"></script>
</body>

</html>