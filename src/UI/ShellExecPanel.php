<?php

declare(strict_types=1);

namespace Franken\Console\UI;

class ShellExecPanel
{
    public function render(): string
    {
        return "\033[32mShell/Exec:\033[0m\nType artisan command and press enter (not fully implemented in TUI)\nExample: cache:clear\n";
    }
}