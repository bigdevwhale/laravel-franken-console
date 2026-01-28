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

    public function __construct()
    {
        $this->theme = new Theme();
    }

    public function render(): string
    {
        $output = "\n";
        $output .= $this->theme->styled("  Artisan Shell\n", 'secondary');
        $output .= $this->theme->styled("  ─────────────────────────────────────────────────────────────────────────\n", 'muted');

        // Quick command buttons
        $output .= "\n";
        $output .= $this->theme->bold("  Quick Commands\n");
        $output .= "\n";

        $quickCommands = [
            ['1', 'list', 'Show all commands'],
            ['2', 'route:list', 'Show routes'],
            ['3', 'config:show', 'Show config'],
            ['4', 'migrate:status', 'Migration status'],
            ['5', 'queue:work --once', 'Process one job'],
        ];

        foreach ($quickCommands as $cmd) {
            $output .= sprintf(
                "  %s %s %s\n",
                $this->theme->styled("[{$cmd[0]}]", 'primary'),
                str_pad($cmd[1], 25),
                $this->theme->dim($cmd[2])
            );
        }

        $output .= "\n";
        $output .= $this->theme->styled("  ─────────────────────────────────────────────────────────────────────────\n", 'muted');

        // Command input
        $output .= "\n";
        $output .= $this->theme->bold("  Command Input\n");
        $output .= "\n";
        
        $prompt = $this->theme->styled("  php artisan ", 'info');
        if ($this->inputMode) {
            $output .= $prompt . $this->commandInput . $this->theme->styled("▌", 'primary') . "\n";
        } else {
            $output .= $prompt . $this->theme->dim("(press 'i' to enter command)") . "\n";
        }

        // Command history / output
        if (!empty($this->outputHistory)) {
            $output .= "\n";
            $output .= $this->theme->styled("  ─────────────────────────────────────────────────────────────────────────\n", 'muted');
            $output .= $this->theme->bold("  Output\n");
            $output .= "\n";
            
            // Show last 10 lines of output
            $recentOutput = array_slice($this->outputHistory, -10);
            foreach ($recentOutput as $line) {
                $output .= "  " . $line . "\n";
            }
        }

        // Command history
        if (!empty($this->commandHistory)) {
            $output .= "\n";
            $output .= $this->theme->styled("  ─────────────────────────────────────────────────────────────────────────\n", 'muted');
            $output .= $this->theme->bold("  Recent Commands\n");
            $output .= "\n";
            
            $recentCommands = array_slice($this->commandHistory, -5);
            foreach ($recentCommands as $i => $cmd) {
                $output .= sprintf("  %s %s\n", 
                    $this->theme->dim('[' . ($i + 1) . ']'),
                    $cmd
                );
            }
        }

        $output .= "\n";
        $output .= $this->theme->dim("  Press 1-5 for quick commands, 'i' to type command, Enter to execute\n");

        return $output;
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