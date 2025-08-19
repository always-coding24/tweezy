<!DOCTYPE html>
<html lang="en">
<!-- Head -->
<head>
    <?php
        include "./function/config.php";
        if ($_SERVER["REQUEST_METHOD"] == "POST" ) {
            if (!empty($_POST['gender']) && !empty($_POST['password']) && !empty($_POST['username']) && !empty($_POST['email']) && !empty($_POST['phone']) && !empty($_POST['dob'])) {
                $ck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? or email = ?");
                $ck->execute([trim($_POST['username']),trim($_POST['email'])]);
                $count = $ck->fetchColumn();

                $username = $_POST['username'];
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

                if ($count > 0) {
                    alert("This username or email has already been used. Please use a different username or email.", "error");
                } elseif (!isEmail($_POST['email'])) {
                    alert("Enter a valid email", "error");
                } else {
                    $stmt = $mysqli->prepare("INSERT INTO users (username, password, email, gender, status, phone, bd) VALUES (?, ?, ?, ?, now(), ?, ?)");
                    $stmt->bind_param("ssssss", ucwords($username), $password, $_POST['email'], $_POST['gender'], $_POST['phone'], $_POST['dob']);
                    $stmt->execute();
                    
                    $saveuser = $mysqli->prepare("SELECT id, username, email FROM users WHERE username = ? ");
                    $saveuser->bind_param("s", $username);
                    $saveuser->execute();
                    $saveuser->bind_result($user_id, $user_name, $email);
                    $saveuser->fetch();
                    $_SESSION["user_id"] = $user_id;
                    $_SESSION["user_name"] = $user_name;
                    $_SESSION["email"] = $email;
                
                    alert("You have successfully created your account", "success");
                    header("Location: chat.php");
                    $stmt->close();
                }
            } else {
                alert("Input all fields", "error");
            }
        }
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1, shrink-to-fit=no, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <title>Messenger</title>
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
                        <form method="post" class="row g-3">
                            <div class="col-12 text-center">
                                <h3 class="fw-bold mb-2 my-1">Sign Up</h3>
                                <p>Follow the easy steps</p>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" required name="username" class="form-control" id="signup-name" placeholder="Name">
                                    <label for="signup-name">Name</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="email" required name="email" class="form-control" id="signup-email" placeholder="Email">
                                    <label for="signup-email">Email</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" required name="phone" class="form-control" id="signup-phone" placeholder="Phone">
                                    <label for="signup-phone">Phone</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="date" required name="dob" class="form-control" id="signup-dob" placeholder="Your birthday">
                                    <label for="signup-dob">Your birthday</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="password" required name="password" class="form-control" id="signup-password" placeholder="Password">
                                    <label for="signup-password">Password</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="btn-group w-100">
                                    <div class="form-check w-50">
                                        <input id="male" name="gender" type="radio" class="form-check-input" value="m" required="">
                                        <label class="form-check-label w-100 text-center" for="male">Male</label>
                                    </div>
                                    <div class="form-check w-50">
                                        <input id="female" name="gender" type="radio" class="form-check-input" value="f" required="">
                                        <label class="form-check-label w-100 text-center" for="female">Female</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-lg btn-primary w-100" type="submit">Create Account</button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Text -->
                <div class="text-center mt-8">
                    <p>Already have an account? <a href="signin.php">Sign in</a></p>
                </div>
            </div>
        </div> <!-- / .row -->
    </div>
    <!-- Scripts -->
    <script src="assets/js/vendor.js"></script>
    <script src="./assets/js/jquery.js"></script>
    <script src="assets/js/template.js"></script>
    <script src="assets/alert/sweetalert2.js"></script>
    <script>
        function readURL(input) {
            if (input.files) {
                for (let i = 0; i < input.files.length; i++) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $(".preview").attr("src", e.target.result);
                    };
                    reader.readAsDataURL(input.files[i]);
                }
            }
        }
    </script>
</body>
</html>
