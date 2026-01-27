<?php

declare(strict_types=1);

namespace Franken\Console\Support;

class Theme
{
    private array $colors;

    public function __construct()
    {
        $themeName = config('franken.theme.name', 'dark');
        $this->colors = config("franken.theme.themes.{$themeName}", config('franken.theme.colors', []));
    }

    public function color(string $name): string
    {
        return $this->colors[$name] ?? 'white';
    }

    public function ansi(string $name): string
    {
        return match($this->color($name)) {
            'black' => "\033[30m",
            'red' => "\033[31m",
            'green' => "\033[32m",
            'yellow' => "\033[33m",
            'blue' => "\033[34m",
            'magenta' => "\033[35m",
            'cyan' => "\033[36m",
            'white' => "\033[37m",
            'gray', 'grey' => "\033[90m",
            'bright_red' => "\033[91m",
            'bright_green' => "\033[92m",
            'bright_yellow' => "\033[93m",
            'bright_blue' => "\033[94m",
            'bright_magenta' => "\033[95m",
            'bright_cyan' => "\033[96m",
            'bright_white' => "\033[97m",
            default => "\033[0m",
        };
    }

    public function styled(string $text, string $color): string
    {
        return $this->ansi($color) . $text . "\033[0m";
    }

    public function dim(string $text): string
    {
        return "\033[2m" . $text . "\033[0m";
    }

    public function bold(string $text): string
    {
        return "\033[1m" . $text . "\033[0m";
    }

    public function underline(string $text): string
    {
        return "\033[4m" . $text . "\033[0m";
    }
}