<!DOCTYPE html>
<html lang="en">
<!-- Head -->

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1, shrink-to-fit=no, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">

    <title>Messenger </title>

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
<?php
include "./function/config.php";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = strtoupper(trim($_POST['username']));
    $password = $_POST['password'];

    // Check for blank inputs
    if (empty($username)) {
        alert('Name is blank', 'error');
    } elseif (empty($password)) {
        alert('Password is blank', 'error');
    } else {
        $stmt = $pdo->prepare("SELECT id, password, username, email FROM users WHERE UCASE(phone) = ? OR UCASE(email) = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user) {
            if (password_verify($password, $user['password'])) {
                alert('Login successful', 'success');
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                header("Location: chat.php");
                exit();
            } else {
                alert("Invalid password", 'error');
            }
        } else {
            alert("User not found", 'error');
        }
    }} 
?>

<body class="bg-light">

    <div class="container">
        <div class="row align-items-center justify-content-center min-vh-100 gx-0">

            <div class="col-12 col-md-5 col-lg-4">
                <div class="card card-shadow border-0">
                    <div class="card-body">
                        <form method="post" class="row g-6">
                            <div class="col-12">
                                <div class="text-center">
                                    <h3 class="fw-bold mb-2">Sign In</h3>
                                    <p>Login to your account</p>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="text" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" name="username" class="form-control" id="signin-email" placeholder="Email">
                                    <label for="signin-email">Phone / Email </label>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="password" name="password" class="form-control" id="signin-password" placeholder="Password">
                                    <label for="signin-password">Password</label>
                                </div>
                            </div>


                            <div class="col-12">
                                <button class="btn btn-block btn-lg btn-primary w-100" type="submit">Sign In</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Text -->
                <div class="text-center mt-8">
                    <p>Don't have an account yet? <a href="signup.php">Sign up</a></p>
                    <p><a href="foget_pass.php">Forgot Password?</a></p>
                </div>
            </div>
        </div> <!-- / .row -->
    </div>

    <!-- Scripts -->
    <script src="assets/js/vendor.js"></script>
    <script src="./assets/js/jquery.js"></script>
    <script src="assets/js/template.js"></script>
    <script src="assets\alert\sweetalert2.js"></script>

</body>


</html>