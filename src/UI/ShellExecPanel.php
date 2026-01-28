<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Franken\Console\Support\Theme;

class ShellExecPanel
{
    private Theme $theme;
    private string $commandInput = '';
    private array $commandHistory = [];
    private array $outputHistory = [];
    private int $historyIndex = -1;
    private bool $inputMode = false;
    private int $scrollOffset = 0;
    private int $terminalHeight = 24;
    private int $terminalWidth = 80;

    public function __construct()
    {
        $this->theme = new Theme();
    }

    public function setTerminalHeight(int $height): void
    {
        $this->terminalHeight = $height;
    }

    public function setTerminalWidth(int $width): void
    {
        $this->terminalWidth = $width;
    }

    private function getVisibleOutputLines(): int
    {
        // Reserve lines for header, quick commands, input, help
        return max(3, $this->terminalHeight - 18);
    }

    public function render(): string
    {
        $width = $this->terminalWidth;
        $height = $this->terminalHeight;
        $lineWidth = max(40, $width - 4);
        
        $output = "\n";
        $output .= '  ' . $this->theme->bold($this->theme->styled('ARTISAN SHELL', 'secondary')) . "\n";
        $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";

        // Quick command buttons (responsive)
        $output .= '  ' . $this->theme->bold('Quick Commands') . "\n";

        $quickCommands = [
            ['1', 'list', 'All commands'],
            ['2', 'route:list', 'Routes'],
            ['3', 'config:show', 'Config'],
            ['4', 'migrate:status', 'Migrations'],
            ['5', 'queue:work --once', 'One job'],
        ];

        if ($width >= 80) {
            // Two rows of commands
            $output .= '  ';
            foreach (array_slice($quickCommands, 0, 3) as $cmd) {
                $output .= $this->theme->styled("[{$cmd[0]}]", 'primary') . ' ' . 
                           str_pad($cmd[1], 18) . $this->theme->dim($cmd[2]) . '  ';
            }
            $output .= "\n  ";
            foreach (array_slice($quickCommands, 3) as $cmd) {
                $output .= $this->theme->styled("[{$cmd[0]}]", 'primary') . ' ' . 
                           str_pad($cmd[1], 18) . $this->theme->dim($cmd[2]) . '  ';
            }
            $output .= "\n";
        } elseif ($width >= 50) {
            // Compact single line per command
            foreach ($quickCommands as $cmd) {
                $output .= '  ' . $this->theme->styled("[{$cmd[0]}]", 'primary') . ' ' . $cmd[1] . "\n";
            }
        } else {
            // Very compact
            $output .= '  ';
            foreach ($quickCommands as $cmd) {
                $output .= $this->theme->styled($cmd[0], 'primary') . ':' . substr($cmd[1], 0, 6) . ' ';
            }
            $output .= "\n";
        }

        $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";

        // Command input
        $output .= '  ' . $this->theme->bold('Command Input') . "\n";
        
        $prompt = $this->theme->styled("  php artisan ", 'info');
        if ($this->inputMode) {
            $output .= $prompt . $this->commandInput . $this->theme->styled("▌", 'primary') . "\n";
        } else {
            $output .= $prompt . $this->theme->dim("(press 'i' to type)") . "\n";
        }

        // Command output (height responsive with page numbers)
        if (!empty($this->outputHistory)) {
            $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";
            $output .= '  ' . $this->theme->bold('Output') . "\n";
            
            $visibleLines = $this->getVisibleOutputLines();
            $totalLines = count($this->outputHistory);
            $start = max(0, $totalLines - $visibleLines - $this->scrollOffset);
            $end = min($totalLines, $start + $visibleLines);
            
            for ($i = $start; $i < $end; $i++) {
                $line = $this->outputHistory[$i];
                if (strlen($line) > $width - 4) {
                    $line = substr($line, 0, $width - 7) . '...';
                }
                $output .= '  ' . $line . "\n";
            }
            
            // Page indicator if scrollable
            if ($totalLines > $visibleLines) {
                $totalPages = max(1, (int)ceil($totalLines / $visibleLines));
                $currentPage = max(1, $totalPages - (int)floor($this->scrollOffset / $visibleLines));
                $output .= '  ' . $this->theme->dim('Page ') . 
                           $this->theme->styled((string)$currentPage, 'info') . 
                           $this->theme->dim('/') . 
                           $this->theme->styled((string)$totalPages, 'info') .
                           $this->theme->dim(' (') .
                           $this->theme->styled((string)$totalLines, 'info') .
                           $this->theme->dim(' lines)') . "\n";
            }
        }

        // Command history (only if there's room)
        if ($height >= 20 && !empty($this->commandHistory)) {
            $output .= '  ' . $this->theme->dim(str_repeat('─', $lineWidth)) . "\n";
            $output .= '  ' . $this->theme->bold('Recent') . "\n";
            
            $recentCommands = array_slice($this->commandHistory, -3);
            $output .= '  ';
            foreach ($recentCommands as $i => $cmd) {
                $shortCmd = strlen($cmd) > 15 ? substr($cmd, 0, 12) . '...' : $cmd;
                $output .= $this->theme->dim('[' . ($i + 1) . ']') . ' ' . $shortCmd . '  ';
            }
            $output .= "\n";
        }

        // Help line (only if there's room)
        if ($height >= 15) {
            $output .= "\n";
            if ($width >= 60) {
                $output .= '  ' . $this->theme->styled('1-5', 'secondary') . $this->theme->dim(' Quick  ') .
                       $this->theme->styled('i', 'secondary') . $this->theme->dim(' Type  ') .
                       $this->theme->styled('⏎', 'secondary') . $this->theme->dim(' Run  ') .
                       $this->theme->styled('↑↓', 'secondary') . $this->theme->dim(' Scroll') . "\n";
            } else {
                $output .= '  ' . $this->theme->styled('i', 'secondary') . $this->theme->dim('Type ') .
                       $this->theme->styled('⏎', 'secondary') . $this->theme->dim('Run') . "\n";
            }
        }

        return $output;
    }

    public function scrollUp(): void
    {
        $maxScroll = max(0, count($this->outputHistory) - $this->getVisibleOutputLines());
        if ($this->scrollOffset < $maxScroll) {
            $this->scrollOffset++;
        }
    }

    public function scrollDown(): void
    {
        if ($this->scrollOffset > 0) {
            $this->scrollOffset--;
        }
    }

    public function enterInputMode(): void
    {
        $this->inputMode = true;
        $this->commandInput = '';
    }

    public function exitInputMode(): void
    {
        $this->inputMode = false;
    }

    public function isInInputMode(): bool
    {
        return $this->inputMode;
    }

    public function addChar(string $char): void
    {
        if ($this->inputMode && ctype_print($char)) {
            $this->commandInput .= $char;
        }
    }

    public function removeChar(): void
    {
        if ($this->inputMode && strlen($this->commandInput) > 0) {
            $this->commandInput = substr($this->commandInput, 0, -1);
        }
    }

    public function executeCommand(?string $command = null): array
    {
        $cmd = $command ?? $this->commandInput;
        
        if (empty($cmd)) {
            return ['error' => 'No command specified'];
        }

        // Add to history
        $this->commandHistory[] = $cmd;
        $this->commandInput = '';
        $this->inputMode = false;
        $this->scrollOffset = 0;

        try {
            $exitCode = \Artisan::call($cmd);
            $output = \Artisan::output();
            
            // Parse output into lines
            $lines = explode("\n", trim($output));
            $this->outputHistory = array_merge($this->outputHistory, $lines);
            
            // Keep only last 100 lines
            if (count($this->outputHistory) > 100) {
                $this->outputHistory = array_slice($this->outputHistory, -100);
            }

            return [
                'success' => $exitCode === 0,
                'output' => $output,
                'exit_code' => $exitCode,
            ];
        } catch (\Exception $e) {
            $errorMsg = 'Error: ' . $e->getMessage();
            $this->outputHistory[] = $this->theme->styled($errorMsg, 'error');
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function runQuickCommand(int $index): array
    {
        $commands = [
            1 => 'list',
            2 => 'route:list --compact',
            3 => 'about',
            4 => 'migrate:status',
            5 => 'queue:work --once',
        ];

        if (isset($commands[$index])) {
            return $this->executeCommand($commands[$index]);
        }

        return ['error' => 'Invalid command index'];
    }
}