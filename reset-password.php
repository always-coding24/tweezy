<?php
if (!empty($_GET['token'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        # code...

        $newPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $token = $_GET['token'];
        // Check if the token exists and has not expired
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if ($reset && (strtotime($reset['created_at']) + 3600 > time())) { // Token valid for 1 hour
            // Update the user's password
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$newPassword, $reset['email']]);

            // Delete the reset token
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);

            echo "Your password has been reset successfully.";
        } else {
            echo "The reset link is invalid or has expired.";
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
                            <div class="row g-6">
                                <div class="col-12">
                                    <div class="text-center">
                                        <h3 class="fw-bold mb-2"> Reset Password</h3>
                                        <p>reset password to Your new password</p>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-floating">
                                        <input type="password"  class="form-control" id="resetpassword-email" placeholder="Email">
                                        <label for="resetpassword-email">password</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-floating">
                                        <input type="password" class="form-control" id="resetpassword-email" placeholder="Email">
                                        <label for="resetpassword-email">Verify password</label>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <button class="btn btn-block btn-lg btn-primary w-100" type="submit">Send Reset Link</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Text -->
                    <div class="text-center mt-8">
                        <p>Already have an account? <a href="signin.html">Sign in</a></p>
                    </div>
                </div>
            </div> <!-- / .row -->
        </div>
    <?php } else {
    header("location:forget_pass.php");
} ?>
    <!-- Scripts -->
    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/template.js"></script>
    </body>

    </html>