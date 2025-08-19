<?php


include "config.php";
include 'time.php';
include 'f_auth.php';
function getfriend($username = '')
{
    global $pdo;
    $current_user_id = $_SESSION['user_id']; // Ensure session is started and user_id is set

    // Query to fetch users ordered by name
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id != ? AND username LIKE ?  ORDER BY username");
    $stmt->execute([$current_user_id, "%$username%"]);

    $categorizedUsers = [];

    // Fetch all users
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $name = $row['username'];
        $image = $row['image'];
        $status = formatTimeAgo(strtotime($row['status']));
        $id = $row['id'];
        $firstLetter = strtoupper($name[0]); // Get the first letter and convert to uppercase

        // Categorize by the first letter
        if (!isset($categorizedUsers[$firstLetter])) {
            $categorizedUsers[$firstLetter] = [];
        }
        $categorizedUsers[$firstLetter][] = [$name, $image, $id, $status];
    }

    // Close the connection
    $pdo = null;

    // Display the categorized users
    foreach ($categorizedUsers as $letter => $data) {
        echo '
       <div class="my-5"><small class="text-uppercase text-muted">' . $letter . '</small></div>
      ';
        foreach ($data as $datas) {
            // echo "<pre>";
            // echo print_r($datas);
            // echo "</pre>";
            echo '   <div class="card border-0">
                                        <div class="card-body">

                                            <div class="row align-items-center gx-5">
                                                <div class="col-auto">
                                                    <a href="#" class="avatar ">';
            if (!empty($datas[1])) {
                echo "<img src='assets/img/avatars/{$datas[1]}' alt='#' class='avatar-img'>";
            } else {
                $avatar_text = strtoupper($datas[0][0]);
                echo "<span class='avatar-text'>{$avatar_text}</span>";
            }

            echo '

                                                    </a>
                                                </div>

                                                <div class="col">
                                                    <h5>' . $datas[0] . '</h5>
                                                    <p>last seen ' . $datas[3] . '</p>
                                                </div>

                                                <div class="col-auto">
                                                    <!-- Dropdown -->
                                                    <div class="dropdown">
                                                        <a class="icon text-muted" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-more-vertical">
                                                                <circle cx="12" cy="12" r="1"></circle>
                                                                <circle cx="12" cy="5" r="1"></circle>
                                                                <circle cx="12" cy="19" r="1"></circle>
                                                            </svg>
                                                        </a>

                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item"  href="chat.php?id=' . $datas[2] . '">New message</a></li>
                                                            <li><a class="dropdown-item" href="#">Edit contact</a>
                                                            </li>
                                                            <li>
                                                                <hr class="dropdown-divider">
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="#">Block
                                                                    user</a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>

                                            </div>

                                        </div>
                                    </div>';
        }
    }
}

if (isset($_GET["u"])) getfriend($_GET["u"]);
else getfriend();
