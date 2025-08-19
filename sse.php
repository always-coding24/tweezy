<?php
require 'vendor/autoload.php';

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

$cache = new FilesystemAdapter();
$pdo = new PDO('mysql:host=localhost;dbname=chatapp', 'root', '');

while (true) {
    $cacheItem = $cache->getItem('latestMessage');

    if (!$cacheItem->isHit()) {
        $stmt = $pdo->query('SELECT * FROM messages ORDER BY timestamp DESC LIMIT 1');
        $message = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($message) {
            $cacheItem->set(json_encode($message));
            $cacheItem->expiresAfter(10); // Cache for 10 seconds
            $cache->save($cacheItem);
        }
    }

    if ($cacheItem->isHit()) {
        $message = $cacheItem->get();
        echo "data: $message\n\n";
        ob_flush();
        flush();
    }

    sleep(5); // Adjust the interval as needed
}
?>
