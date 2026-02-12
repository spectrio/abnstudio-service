<?php

require_once __DIR__.'/vendor/autoload.php';

try {
    (new Dotenv\Dotenv(__DIR__))->load();
} catch (Dotenv\Exception\InvalidPathException $e) {
}

if ($argc < 2) {
    echo "Usage: php match_template.php <xml_file_path>" . PHP_EOL;
    exit(1);
}

$xmlFilePath = $argv[1];

if (!file_exists($xmlFilePath)) {
    echo "Error: File not found: $xmlFilePath" . PHP_EOL;
    exit(1);
}

function extractLayerStructure($xmlContent) {
    $xml = simplexml_load_string($xmlContent);
    if ($xml === false) {
        return null;
    }
    
    $layers = [];
    foreach ($xml->layers->layer as $layer) {
        $title = urldecode((string)$layer['title']);
        
        $firstChild = null;
        foreach ($layer->children() as $child) {
            $firstChild = $child->getName();
            break;
        }
        
        $layers[] = [
            'title' => $title,
            'first_child' => $firstChild
        ];
    }
    
    return [
        'layer_count' => count($layers),
        'layers' => $layers
    ];
}

$host = env('DB_HOST', 'abn-internal-dev.cochu0wseyli.us-east-1.rds.amazonaws.com');
$db = env('DB_DATABASE', 'wevideo');
$user = env('DB_USERNAME', 'wevideo');
$pass = env('DB_PASSWORD', 'AbnR0ck$!');

try {
    $inputXml = file_get_contents($xmlFilePath);
    $inputStructure = extractLayerStructure($inputXml);
    
    if ($inputStructure === null) {
        echo "Error: Failed to parse input XML file" . PHP_EOL;
        exit(1);
    }
    
    echo "Input XML Structure:" . PHP_EOL;
    echo "  Layer Count: " . $inputStructure['layer_count'] . PHP_EOL;
    echo "  Layers:" . PHP_EOL;
    foreach ($inputStructure['layers'] as $idx => $layer) {
        echo "    " . ($idx + 1) . ". Title: " . $layer['title'] . PHP_EOL;
        echo "       First Child: " . $layer['first_child'] . PHP_EOL;
    }
    echo PHP_EOL;
    
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Searching for matching templates in database..." . PHP_EOL . PHP_EOL;
    
    $stmt = $pdo->query('SELECT tid, name, template FROM templates');
    $matchingTemplates = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dbStructure = extractLayerStructure($row['template']);
        
        if ($dbStructure === null) {
            continue;
        }
        
        if ($dbStructure['layer_count'] !== $inputStructure['layer_count']) {
            continue;
        }
        
        $matches = true;
        for ($i = 0; $i < $dbStructure['layer_count']; $i++) {
            if ($dbStructure['layers'][$i]['title'] !== $inputStructure['layers'][$i]['title'] ||
                $dbStructure['layers'][$i]['first_child'] !== $inputStructure['layers'][$i]['first_child']) {
                $matches = false;
                break;
            }
        }
        
        if ($matches) {
            $matchingTemplates[] = [
                'tid' => $row['tid'],
                'name' => $row['name']
            ];
        }
    }
    
    if (count($matchingTemplates) > 0) {
        echo "Found " . count($matchingTemplates) . " matching template(s):" . PHP_EOL;
        foreach ($matchingTemplates as $template) {
            echo "  - Template ID: " . $template['tid'] . " | Name: " . $template['name'] . PHP_EOL;
        }
    } else {
        echo "No matching templates found in database." . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
