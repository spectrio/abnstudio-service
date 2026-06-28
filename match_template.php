<?php

require_once __DIR__.'/vendor/autoload.php';

try {
    (new Dotenv\Dotenv(__DIR__))->load();
} catch (Dotenv\Exception\InvalidPathException $e) {
}

if ($argc < 2) {
    echo "Usage: php match_template.php <xml_file_or_folder_path>" . PHP_EOL;
    exit(1);
}

$inputPath = $argv[1];

if (!file_exists($inputPath)) {
    echo "Error: Path not found: $inputPath" . PHP_EOL;
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

function processXmlFile($xmlFilePath, $pdo, &$logEntries) {
    echo "\n" . str_repeat("=", 80) . PHP_EOL;
    echo "Processing: $xmlFilePath" . PHP_EOL;
    echo str_repeat("=", 80) . PHP_EOL;

    $inputXml = file_get_contents($xmlFilePath);
    $inputStructure = extractLayerStructure($inputXml);

    if ($inputStructure === null) {
        echo "Error: Failed to parse XML file" . PHP_EOL;
        $logEntries[] = $xmlFilePath . " -> Parse error";
        return;
    }

    echo "Layer Count: " . $inputStructure['layer_count'] . PHP_EOL;

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
        echo "✓ Found " . count($matchingTemplates) . " matching template(s):" . PHP_EOL;
        foreach ($matchingTemplates as $template) {
            echo "  - Template ID: " . $template['tid'] . " | Name: " . $template['name'] . PHP_EOL;
            $logEntries[] = $xmlFilePath . " -> Template ID: " . $template['tid'] . " | Name: " . $template['name'];
        }
    } else {
        echo "✗ No matching templates found" . PHP_EOL;
        $logEntries[] = $xmlFilePath . " -> No match found";
    }
}

$host = env('DB_HOST', 'abn-internal-dev.cochu0wseyli.us-east-1.rds.amazonaws.com');
$db = env('DB_DATABASE', 'wevideo');
$user = env('DB_USERNAME', 'wevideo');
$pass = env('DB_PASSWORD', 'AbnR0ck$!');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $logFile = 'template_match_log.txt';
    $logEntries = [];

    if (file_exists($logFile)) {
        $existingLog = file_get_contents($logFile);
        $lines = explode("\n", trim($existingLog));

        foreach ($lines as $line) {
            if (empty($line)) continue;
            $logEntries[] = $line;
        }
    }

    $xmlFiles = [];

    if (is_dir($inputPath)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($inputPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
                $xmlFiles[] = $file->getPathname();
            }
        }

        echo "Found " . count($xmlFiles) . " XML file(s) in folder: $inputPath" . PHP_EOL;
    } else {
        $xmlFiles[] = $inputPath;
    }

    if (empty($xmlFiles)) {
        echo "No XML files found to process." . PHP_EOL;
        exit(0);
    }

    foreach ($xmlFiles as $xmlFile) {
        $existingIndex = null;
        foreach ($logEntries as $index => $entry) {
            if (strpos($entry, $xmlFile . ' ->') === 0) {
                $existingIndex = $index;
                break;
            }
        }

        if ($existingIndex !== null) {
            unset($logEntries[$existingIndex]);
            $logEntries = array_values($logEntries);
        }

        processXmlFile($xmlFile, $pdo, $logEntries);
    }

    file_put_contents($logFile, implode("\n", $logEntries) . "\n");
    echo "\n" . str_repeat("=", 80) . PHP_EOL;
    echo "All results logged to: $logFile" . PHP_EOL;
    echo str_repeat("=", 80) . PHP_EOL;

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
