<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

echo "Checking birthday templates in database...\n\n";

$templates = DB::table('templates')
    ->select('tid', 'name', 'template')
    ->where('name', 'like', '%birthday%')
    ->get();

foreach ($templates as $template) {
    echo "========================================\n";
    echo "TID: {$template->tid}\n";
    echo "Name: {$template->name}\n";
    echo "----------------------------------------\n";
    
    if (empty($template->template)) {
        echo "No template XML found\n";
        continue;
    }
    
    // Parse XML and extract layer titles
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($template->template);
    
    if ($xml === false) {
        echo "Failed to parse XML\n";
        continue;
    }
    
    $layers = $xml->xpath('//layer');
    echo "Layers found:\n";
    foreach ($layers as $layer) {
        $title = (string) $layer['title'];
        $decodedTitle = urldecode($title);
        echo "  - {$title}\n";
        if ($title !== $decodedTitle) {
            echo "    Decoded: {$decodedTitle}\n";
        }
    }
    echo "\n";
}

echo "========================================\n";
