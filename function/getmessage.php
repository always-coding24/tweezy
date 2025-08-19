<?php

// Example usage



include "config.php";
include "time.php";
include 'f_auth.php';
// Fetch current user's details

// $stmt->bind_result($user_name, $user_avatar);

$foundUser = $User->findById($_SESSION["user_id"]);
$user_name = $foundUser->username;
$user_avatar = $foundUser->image;
function getMessage($user)
{
    global $mysqli;
    global $pdo;
    global $User;
    global $user_name;
    global $user_avatar;

    if (!isset($user) || !is_numeric($user)) {
        return;
    }

    // Fetch friend details
    $friend = $pdo->prepare("SELECT `username`, `image` FROM users WHERE id = ?");
    $friend->execute([$user]);
    $friend_info = $friend->fetchAll();

    if (empty($friend_info)) {
        return;
    }

    $friend_username = $friend_info[0]["username"];
    $friend_image = $friend_info[0]["image"];

    // Fetch messages between users
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE (recipient_id = ? AND sender_id = ?) OR (recipient_id = ? AND sender_id = ?) ORDER BY `sent_at` ASC");
    $stmt->execute([$user, $_SESSION['user_id'], $_SESSION['user_id'], $user]);
    $messages = $stmt->fetchAll();

    if (empty($messages)) {
        echo '';
        return;
    }

    $grouped_messages = [];
    foreach ($messages as $message) {
        $date = date("l, F j ", strtotime($message["sent_at"]));
        $grouped_messages[$date][] = $message;
    }

    foreach ($grouped_messages as $date => $msgs) {
        echo '<div class="message-divider"><small class="text-muted">' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '</small></div>';
        foreach ($msgs as $message) {

            $id = $message["message_id"];
            $sender_id = $message["sender_id"];
            $alignment = ($sender_id !== $_SESSION['user_id']) ? "message-in" : "message-out";
            $class = "";

            if (is_emoji($message['message'])) {
                $class = "emoji";
            }
            if ($alignment === "message-in") {
                $avatar = '<a href="#" data-bs-toggle="offcanvas" data-bs-target="#offcanvas-more" aria-controls="offcanvas-more"class="avatar avatar-responsive"><span class="avatar-text">'. strtoupper($friend_username[0]) . '</span></a>';
            } else {
                $avatar = "";
                $clear = $pdo->prepare("UPDATE messages SET seen = NOW() WHERE seen IS NULL AND recipient_id = ? ");
                $clear->execute([$_SESSION['user_id']]);
            }
            $reply = "";
            if (!is_null($message['reply'])) {

                $rep = new DatabaseTable($pdo, 'messages', "message_id");
                $rresult =  $rep->findById($message["reply"]);
                if (is_array($rresult)) {
                    $mrep = $rresult["message"];
                    $sender_id = $rresult["sender_id"];
                    # code...
                }
                if (isset($mrep) and !is_null($mrep)) {


                    $foundUser = $User->findById($sender_id);
                    $class = "";
                    $reply = ' <blockquote  class="blockquote overflow-hidden">
                                                        <h6 class="text-reset text-truncate">' . $foundUser->username . '</h6>
                                                        <p class="small text-truncate">' . htmlspecialchars(substr($mrep, 200)) . '...</p>
                                                    </blockquote>';
                }
            }



            echo '<div id ="message-' . $id . '" class="message  ' . $alignment . ' ' . $class . ' ">
                   ' . $avatar . '
                    <div class="message-inner">
                        <div class="message-body"> 
                            <div class="message-content">
                               
                                ' . ($alignment === "message-out" ? '
                              
                                    <div class="dropdown">
                                       <div class="message-text" style="hyphens: auto;hyphenate-character: auto;" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                ' . $reply . '
                                    <p>' . wrapLinks(nl2br(htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8'))) . '</p> 
                                </div>
                                        <ul class="dropdown-menu">
                                          <li onclick="copy(' . $id . ')">
                                            <span class="dropdown-item d-flex align-items-center" href="#">
                                                <span class="me-auto">Copy</span>
                                                <div class="icon">
                                                
<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-copy"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                                </div>
                                            </span>
                                        </li>
                                        <li>
                                            <li>
                                                <div class="dropdown-item d-flex align-items-center" href="#">
                                                    <span class="me-auto">Edit</span>
                                                    <div class="icon">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-edit-3">
                                                            <path d="M12 20h9"></path>
                                                            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                                                        </svg>
                                                    </div>
                                                </div>
                                            </li>
                                            <li>
                                                <span class="dropdown-item d-flex align-items-center" href="#">
                                                    <span class="me-auto">Reply</span>
                                                    <div class="icon">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-corner-up-left">
                                                            <polyline points="9 14 4 9 9 4"></polyline>
                                                            <path d="M20 20v-7a4 4 0 0 0-4-4H4"></path>
                                                        </svg>
                                                    </div>
                                                </span>
                                            </li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li>
                                                <span class="dropdown-item d-flex align-items-center text-danger" href="#">
                                                    <span class="me-auto">Delete</span>
                                                    <div class="icon">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-trash-2">
                                                            <polyline points="3 6 5 6 21 6"></polyline>
                                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                            <line x1="10" y1="11" x2="10" y2="17"></line>
                                                            <line x1="14" y1="11" x2="14" y2="17"></line>
                                                        </svg>
                                                    </div>
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                ' : '
                                <div class="dropdown">
                                    <div class="message-text" style="hyphens: auto;hyphenate-character: auto;" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                ' . $reply . '
                                    <p>' . wrapLinks(nl2br(htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8'))) . '</p> 
                                </div>
                                    <ul class="dropdown-menu">
                                        
                                        <li onclick="copy(' . $id . ')">
                                            <span class="dropdown-item d-flex align-items-center" href="#">
                                                <span class="me-auto">Copy</span>
                                                <div class="icon">
                                                
<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-copy"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                                </div>
                                            </span>
                                        </li>
                                        <li onclick="reply(' . $id . ')">
                                            <span class="dropdown-item d-flex align-items-center" href="#">
                                                <span class="me-auto">Reply</span>
                                                <div class="icon">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-corner-up-left">
                                                        <polyline points="9 14 4 9 9 4"></polyline>
                                                        <path d="M20 20v-7a4 4 0 0 0-4-4H4"></path>
                                                    </svg>
                                                </div>
                                            </span>
                                        </li>
                                     
                                   
                                    </ul>
                                </div>
                           ') . '
                            <a class="icon text-muted" href="#">
                                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-corner-up-left">
                                                            <polyline points="9 14 4 9 9 4"></polyline>
                                                            <path d="M20 20v-7a4 4 0 0 0-4-4H4"></path>
                                                        </svg>
                                    </a>
                            </div>
                        </div>
                        <div class="message-footer">
                            <span class="extra-small text-muted"> ' . date("h:i A", strtotime($message["seen"])) .
                //  htmlspecialchars(formatTimeAgo(strtotime($message["sent_at"])), ENT_QUOTES, 'UTF-8') .
                ($alignment === "message-out" ? (!empty($message["seen"]) ? ' &nbsp<img width="20" src="assets/img/chat/dbcheck.png">' : ' &nbsp<svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="20" height="20" viewBox="0,0,256,256">
<g fill="#2787f5" fill-rule="nonzero" stroke="none" stroke-width="1" stroke-linecap="butt" stroke-linejoin="miter" stroke-miterlimit="5" stroke-dasharray="" stroke-dashoffset="0" font-family="none" font-weight="none" font-size="none" text-anchor="none" style="mix-blend-mode: normal"><g transform="scale(8,8)"><path d="M28.79688,6.13477c-0.19111,0.00015 -0.37818,0.05505 -0.53906,0.1582c-6.08316,3.795 -11.06875,8.89216 -15.22852,14.92383c-2.10391,-2.88392 -4.44677,-5.40513 -7.3125,-7.08008c-0.30912,-0.19396 -0.69936,-0.20415 -1.01818,-0.02658c-0.31882,0.17757 -0.51561,0.51471 -0.51345,0.87964c0.00216,0.36493 0.20292,0.69971 0.52382,0.8735c2.88895,1.68852 5.3428,4.3223 7.53516,7.55664c0.18759,0.276 0.50048,0.44024 0.83418,0.43789c0.3337,-0.00235 0.64425,-0.17099 0.82793,-0.44961c4.17503,-6.33344 9.22499,-11.55933 15.41016,-15.41797c0.384,-0.23264 0.56591,-0.69268 0.44485,-1.12503c-0.12106,-0.43235 -0.5154,-0.73103 -0.96438,-0.73044z"></path></g></g>
</svg>') : '') .

                '</span>
                        </div >
                    </div>
                </div>';
        }
    }
}
getMessage($_GET["u"]);
