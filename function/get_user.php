<?php
include "config.php";
include 'f_auth.php';
include 'time.php';

// Ensure session is started and user_id is set
// session_start();
// if (!isset($_SESSION['user_id'])) {
//     echo "User not logged in.";
//     exit;
// }

function getUsers($username = '')
{
    global $mysqli;

    $current_user_id = $_SESSION['user_id'];

    // Prepare SQL statement to fetch users
    $sql = "
        SELECT 
            u.id AS user_id,
            u.username AS user_name,
            u.image AS user_avatar,
            u.typing_to AS typing_to,
            u.status AS status,
             (SELECT COUNT(*) FROM messages WHERE recipient_id = $current_user_id AND sender_id = u.id AND seen IS NULL) AS unread_count,
             (SELECT COUNT(*) FROM messages WHERE recipient_id = $current_user_id AND sender_id = u.id ) AS message_count,
         
             (SELECT max(message_id) FROM messages WHERE (recipient_id =  $current_user_id AND sender_id =  u.id) OR (recipient_id =  $current_user_id AND sender_id =  u.id)= u.id     LIMIT 1) AS last_message_num,
             (SELECT message FROM  messages WHERE  message_id = last_message_num  ) AS last_message
        FROM 
            users u
        LEFT JOIN 
            messages m ON (u.id = m.sender_id OR u.id = m.recipient_id)
        WHERE 
             u.username LIKE  ?

            and u.id != $current_user_id 
             and   (SELECT message_id FROM messages WHERE (recipient_id =  $current_user_id AND sender_id =  u.id) or (recipient_id =  $current_user_id AND sender_id =  u.id) = u.id ORDER BY sent_at DESC LIMIT 1) is not null 
                         GROUP BY u.id
        ORDER BY MAX(m.sent_at) DESC;
    ";

    // Prepare the statement
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        echo "Failed to prepare statement: " . $mysqli->error;
        return;
    }

    $search = "%$username%";
    $stmt->bind_param("s", $search);

    // Execute the query
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if the query executed successfully
    if (!$result) {
        echo "Error executing query: " . $mysqli->error;
        return;
    }

    // Process the result set
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            renderUserCard($row);
        }
    } else {
        echo "No users found.";
    }

    // Close the statement
    $stmt->close();
}

function renderUserCard($user)
{
    $user_id = htmlspecialchars($user['user_id'], ENT_QUOTES, 'UTF-8');
    $user_name = htmlspecialchars(trim($user['user_name']), ENT_QUOTES, 'UTF-8');
    $user_avatar = htmlspecialchars($user['user_avatar'], ENT_QUOTES, 'UTF-8');
    $status = htmlspecialchars(setstatus(strtotime($user['status'])), ENT_QUOTES, 'UTF-8');
    $unread_count = htmlspecialchars($user['unread_count'], ENT_QUOTES, 'UTF-8');
    $typing_to = htmlspecialchars($user["typing_to"], ENT_QUOTES, 'UTF-8');
    $last_message = htmlspecialchars($user["last_message"], ENT_QUOTES, 'UTF-8');

    echo "

    <a href='chat.php?id=$user_id' class='card border-0 text-reset'>
        <div class='card-body'>
            <div class='row gx-5'>
                <div class='col-auto'>
                    <div class='avatar avatar-$status'>";

    if (!empty($user_avatar)) {
        echo "<img src='assets/img/avatars/$user_avatar' alt='#' class='avatar-img'>";
    } else {
        $avatar_text = strtoupper($user_name[0]);
        echo "<span class='avatar-text'>$avatar_text</span>";
    }

    echo "
                    </div>
                </div>
                <div class='col'>
                    <div class='d-flex align-items-center mb-3'>
                        <h5 class='me-auto mb-0'>$user_name</h5>
                        <span class='text-muted extra-small ms-2'></span>
                    </div>
                    <div class='d-flex align-items-center'>
                        <div class='line-clamp me-auto'>";

    if ($typing_to == $_SESSION["user_id"]) {
        echo "is typing<span class='typing-dots'><span>.</span><span>.</span><span>.</span></span>";
    } else {
        if ($unread_count > 0) {
            echo "<b>$last_message</b>";
        } else {
            echo $last_message;
        }
    }

    echo "
                        </div>";

    if ($unread_count > 0) {
        echo "
                        <div class='badge badge-circle bg-primary ms-5'>
                            <span>$unread_count</span>
                        </div>";
    }

    echo "
                    </div>
                </div>
            </div>
        </div>
    </a>";
}

// Fetch users based on the query parameter 'u'
$username = isset($_GET['u']) ? $_GET['u'] : '';
getUsers($username);
