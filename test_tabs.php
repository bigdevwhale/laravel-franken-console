<?php

// Test file to demonstrate Solo-style tabs and content display
// Run with: php test_tabs.php

require_once 'vendor/autoload.php';

use Franken\Console\Support\Theme;

// Mock terminal dimensions
$width = 80;
$height = 24;

// Create theme
$theme = new Theme();

// Mock panel data (similar to Solo's commands)
$panels = [
    ['name' => 'Overview', 'status' => 'focused', 'focused' => true],
    ['name' => 'Queues', 'status' => 'running', 'focused' => false],
    ['name' => 'Jobs', 'status' => 'running', 'focused' => false],
    ['name' => 'Logs', 'status' => 'running', 'focused' => false],
    ['name' => 'Cache', 'status' => 'stopped', 'focused' => false],
    ['name' => 'Scheduler', 'status' => 'stopped', 'focused' => false],
    ['name' => 'Metrics', 'status' => 'running', 'focused' => false],
    ['name' => 'Shell', 'status' => 'stopped', 'focused' => false],
    ['name' => 'Settings', 'status' => 'stopped', 'focused' => false],
];

// Render Solo-style tab bar
echo "\n";
echo "Solo-style Tab Bar:\n";
echo str_repeat('=', 80) . "\n";

$tabBar = '';
foreach ($panels as $panel) {
    $displayName = ' ' . $panel['name'] . ' ';
    
    if ($panel['focused']) {
        $tabBar .= $theme->tabFocused($displayName, $panel['status']);
    } else {
        $tabBar .= $theme->tabBlurred($displayName, $panel['status']);
    }
}

echo $tabBar . "\n";
echo $theme->dim(str_repeat('─', 80)) . "\n";

// Show content area placeholder
echo "Content area for focused panel would go here...\n";
echo $theme->styled("Current panel: Overview", 'primary') . "\n";
echo "\n";

// Show hotkey bar (Solo-style)
$hotkeyBar = '';
$hotkeys = [
    'q' => 'Quit',
    'r' => 'Refresh', 
    '←/→' => 'Switch Tabs',
    '↑/↓' => 'Navigate',
    '/' => 'Search',
];

foreach ($hotkeys as $key => $label) {
    $hotkeyBar .= $theme->hotkey($key) . ' ' . $theme->hotkeyLabel($label) . '  ';
}

// Center the hotkey bar
$cleanLength = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $hotkeyBar));
if ($cleanLength < 80) {
    $padding = (80 - $cleanLength) / 2;
    $hotkeyBar = str_repeat(' ', (int) floor($padding)) . $hotkeyBar;
}

echo $hotkeyBar . "\n";
echo str_repeat('=', 80) . "\n";

echo "\nKey Features Implemented:\n";
echo "✓ Solo-style tab rendering with focus states\n";
echo "✓ Status indicators (●) with colors\n";
echo "✓ Tab overflow handling (← 2, 3 →)\n";
echo "✓ Centered hotkey bar\n";
echo "✓ Panel base class with focus/blur states\n";
echo "✓ Dashboard with proper tab navigation\n";
echo "✓ Theme system matching Solo's approach\n";
echo "\nUse ←/→ arrow keys to switch tabs when running the actual console!\n";