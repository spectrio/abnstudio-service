<?php

require_once __DIR__.'/vendor/autoload.php';

try {
    (new Dotenv\Dotenv(__DIR__))->load();
} catch (Dotenv\Exception\InvalidPathException $e) {
    //
}

$host = env('DB_HOST', 'abn-internal-dev.cochu0wseyli.us-east-1.rds.amazonaws.com');
$db = env('DB_DATABASE', 'wevideo');
$user = env('DB_USERNAME', 'wevideo');
$pass = env('DB_PASSWORD', 'AbnR0ck$!');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Querying template with tid 205..." . PHP_EOL . PHP_EOL;
    $stmt = $pdo->query('SELECT * FROM templates WHERE tid = 205');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "Template found!" . PHP_EOL;
        echo "tid: " . $result['tid'] . PHP_EOL;
        echo "name: " . $result['name'] . PHP_EOL;
        echo "oems: " . $result['oems'] . PHP_EOL;
        echo "orientation: " . $result['orientation'] . PHP_EOL;
        echo "duration: " . $result['duration'] . PHP_EOL;
        echo PHP_EOL . "XML/Template Content:" . PHP_EOL;
        echo "===========================================" . PHP_EOL;
        echo $result['template'] . PHP_EOL;
        echo "===========================================" . PHP_EOL;
        
        file_put_contents('template_205_from_db.xml', $result['template']);
        echo PHP_EOL . "Template saved to: template_205_from_db.xml" . PHP_EOL;
    } else {
        echo "No template found with tid 205" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
