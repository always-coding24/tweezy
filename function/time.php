<?php

function formatTimeAgo($timestamp) {
    // Get current time in UTC
    $now = new DateTime('now', new DateTimeZone('UTC'));
    // Convert timestamp to DateTime object in UTC
    $time = DateTime::createFromFormat('U', $timestamp, new DateTimeZone('UTC'));

    // Check if the time conversion was successful
    if (!$time) {
        return 'Invalid timestamp';
    }

    // Calculate the difference
    $diff = $now->diff($time);

    // Get the difference in various units
    $years = $diff->y;
    $months = $diff->m;
    $days = $diff->d;
    $hours = $diff->h;
    $minutes = $diff->i;
    $seconds = $diff->s;

    // Return the appropriate string based on the difference
    if ($years > 0) {
        return ($years > 1) ? "{$years} years ago" : '1 year ago';
    } elseif ($months > 0) {
        return ($months > 1) ? "{$months} months ago" : '1 month ago';
    } elseif ($days > 0) {
        if ($days >= 7) {
            $weeks = floor($days / 7);
            return ($weeks > 1) ? "{$weeks} weeks ago" : '1 week ago';
        } else {
            return ($days > 1) ? "{$days} days ago" : '1 day ago';
        }
    } elseif ($hours > 0) {
        return ($hours > 1) ? "{$hours} hours ago" : '1 hour ago';
    } elseif ($minutes > 0) {
        return ($minutes > 1) ? "{$minutes} minutes ago" : '1 minute ago';
    } else {
        return ($seconds > 1) ? "{$seconds} seconds ago" : 'just now';
    }
}


function formaltimeago($timestamp) {
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $time = DateTime::createFromFormat('U', $timestamp, new DateTimeZone('UTC'));

    $diff = $now->diff($time);

    $years = $diff->y;
    $months = $diff->m;
    $days = $diff->d;
    $hours = $diff->h;
    $minutes = $diff->i;
    $seconds = $diff->s;

    if ($years > 1) {
        return "{$years} years, {$months} months, {$days} days";
    } elseif ($years == 1) {
        return "1 year, {$months} months, {$days} days";
    } elseif ($months > 1) {
        return "{$months} months, {$days} days";
    } elseif ($months == 1) {
        return "1 month, {$days} days";
    } elseif ($days > 1) {
        return "{$days} days, {$hours} hours";
    } elseif ($days == 1) {
        return "1 day, {$hours} hours";
    } elseif ($hours > 1) {
        return "{$hours} hours, {$minutes} minutes";
    } elseif ($hours == 1) {
        return "1 hour, {$minutes} minutes";
    } elseif ($minutes > 1) {
        return "{$minutes} minutes";
    } elseif ($minutes == 1) {
        return "1 minute";
    } else {
        return "{$seconds} seconds";
    }
}

?>
<?php
function status($timestamp) {
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $time = DateTime::createFromFormat('U', $timestamp, new DateTimeZone('UTC'));

    // Ensure $time is correctly created before proceeding
    if (!$time) {
        return "error"; // Handle the case where $time creation fails
    }

    $diff = $now->getTimestamp() - $time->getTimestamp(); // Difference in seconds

    // Define a threshold for online status (e.g., 5 minutes)
    $onlineThreshold = 5 * 60; // 5 minutes in seconds

    if ($diff <= $onlineThreshold) {
        return "online";
    } else {
        return "offline";
    }
}
function setstatus( $timestamp){
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $time = DateTime::createFromFormat('U', $timestamp, new DateTimeZone('UTC'));
   
    $diff = $now->diff($time);
   
   $minutes = $diff->i;
   if ($minutes<=1) {
     return "online";
   }else{
       return "Last time seen " . formatTimeAgo($timestamp);
    }
}
?>
    

<?php function formatFullTimeAgo($timestamp) {
    $now = time(); 
       $difference = $now - $timestamp;

    $periods = [
        'year' => 31536000,
        'month' => 2592000,
        'week' => 604800,
        'day' => 86400,
        'hour' => 3600,
        'minute' => 60,
        'second' => 1
    ];

    $output = '';
    foreach ($periods as $period => $seconds) {
        if ($difference >= $seconds) {
            $count = floor($difference / $seconds);
            $difference %= $seconds;
            $output .= ($output ? ', ' : '') . $count . ' ' . $period . ($count > 1 ? 's' : '');
        }
    }

    if (empty($output)) {
        return 'just now';
    }

    return $output . ' ago';
}
?>