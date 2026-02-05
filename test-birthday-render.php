
<?php

require_once __DIR__.'/bootstrap/app.php';

use App\ModifiedProcessXml;
use App\ModifiedTemplate;

// Get the birthday template
$load = new ModifiedTemplate;
$template = $load->getTemplate(205); // Birthday template ID from logs
$templateData = $load->getTemplateData(205);

// Simulate the request data from the logs
$data = [
    'templateFields' => [
        [
            'layer' => 'Names%20%7B%22cie%22%3A%22/1800%22%2C%20%22bts%22%3A%22p%22%2C%22bto%22%3A%7B%22chevrolet%22%3A%22w%22%2C%22honda%22%3A%22w%22%7D%7D',
            'content' => '{{8/4 – Jose C.J}}{{8/11 – Richard C.}}{{8/18 – Chris F.}}{{8/19 – Carla A.}}{{8/24 – Destiny G.}}{{8/24 – Nick P.}}{{8/25 – Manny L.}}{{8/25 – Jeffrey B.}}{{8/3 - Alex L.}}{{8/10 - Angela D.}}{{8/17 - Aaron P.}}{{8/21 - Bradley P.}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}{{}}',
        ],
        [
            'layer' => 'Line%201%20%7B%22bts%22%3A%22h1%22%2C%22bto%22%3A%7B%22chevrolet%22%3A%22w%22%2C%22buick%22%3A%22w%22%2C%22gmc%22%3A%22w%22%2C%22honda%22%3A%221%22%7D%2C%20%22bta%22%3A%7B%22chevrolet%22%3A%220%2C-5%22%2C%22honda%22%3A%220%2C-7%22%7D%7D',
            'content' => 'August',
        ],
        [
            'layer' => 'Line%202%20%7B%22bts%22%3A%22h1%22%2C%22bto%22%3A%7B%22chevrolet%22%3A%22w%22%2C%22buick%22%3A%22w%22%2C%22gmc%22%3A%22w%22%2C%22honda%22%3A%221%22%7D%2C%20%22bta%22%3A%7B%22chevrolet%22%3A%220%2C-5%22%2C%22honda%22%3A%220%2C-7%22%7D%7D',
            'content' => 'Birthdays',
        ],
    ],
    'template' => [
        'tid' => 205,
        'acct' => 33333,
        'acctName' => 'ABN HQ',
        'oem' => 'CHEVROLET',
        'logotype' => 'dark',
    ],
];

echo "=== TESTING BIRTHDAY TEMPLATE RENDER ===\n";
echo "Template ID: 205\n";
echo "Thumbnail Time: {$templateData['thumbnail_time']}\n";
echo "Orientation: {$templateData['orientation']}\n\n";

// Process the template
$process = new ModifiedProcessXml;
$result = $process->process($template, $data, $templateData['thumbnail_time'], $templateData['orientation'], 0);

echo "\n=== RENDER RESULT ===\n";
echo $result;
echo "\n";
