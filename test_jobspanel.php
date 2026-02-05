<?php

require 'vendor/autoload.php';

use Franken\Console\UI\JobsPanel;
use Franken\Console\Adapters\QueueAdapter;

$panel = new JobsPanel('Jobs', new QueueAdapter());
$panel->setDimensions(80, 24);
echo 'JobsPanel instantiated and dimensions set successfully' . PHP_EOL;

try {
    $output = $panel->render();
    echo 'Render successful, output length: ' . strlen($output) . PHP_EOL;
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}