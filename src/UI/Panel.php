<?php

declare(strict_types=1);

namespace Franken\Console\UI;

use Franken\Console\Support\Terminal;

abstract class Panel
{
    protected string $name;
    protected bool $focused = false;
    protected bool $active = true;
    protected ?Terminal $terminal = null;
    protected int $width = 80;
    protected int $height = 24;
    protected array $state = [];

    public function __construct(string $name, ?Terminal $terminal = null)
    {
        $this->name = $name;
        $this->terminal = $terminal ?? new Terminal();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isFocused(): bool
    {
        return $this->focused;
    }

    public function isBlurred(): bool
    {
        return !$this->focused;
    }

    public function focus(): void
    {
        $this->focused = true;
        $this->onFocus();
    }

    public function blur(): void
    {
        $this->focused = false;
        $this->onBlur();
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getStatus(): string
    {
        if (!$this->active) {
            return 'stopped';
        }

        return $this->focused ? 'focused' : 'running';
    }

    public function setDimensions(int $width, int $height): void
    {
        $this->width = $width;
        $this->height = $height;
        $this->onDimensionsChanged($width, $height);
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    protected function setState(string $key, $value): void
    {
        $this->state[$key] = $value;
    }

    protected function getState(string $key, $default = null)
    {
        return $this->state[$key] ?? $default;
    }

    // Abstract methods that panels must implement
    abstract public function render(): string;

    // Hook methods that panels can override
    protected function onFocus(): void
    {
        // Override in subclasses if needed
    }

    protected function onBlur(): void
    {
        // Override in subclasses if needed
    }

    protected function onDimensionsChanged(int $width, int $height): void
    {
        // Override in subclasses if needed
    }

    // Utility methods for panels
    protected function padLine(string $line, int $width = null): string
    {
        $width = $width ?? $this->width;
        $lineLength = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $line));
        
        if ($lineLength >= $width) {
            return $line;
        }
        
        return $line . str_repeat(' ', $width - $lineLength);
    }

    protected function truncateLine(string $line, int $width = null): string
    {
        $width = $width ?? $this->width;
        $cleanLine = preg_replace('/\033\[[0-9;]*m/', '', $line);
        
        if (mb_strlen($cleanLine) <= $width) {
            return $line;
        }
        
        // Simple truncation - could be enhanced to preserve ANSI codes
        return mb_substr($cleanLine, 0, $width - 3) . '...';
    }

    protected function centerLine(string $line, int $width = null): string
    {
        $width = $width ?? $this->width;
        $lineLength = mb_strlen(preg_replace('/\033\[[0-9;]*m/', '', $line));
        
        if ($lineLength >= $width) {
            return $line;
        }
        
        $padding = ($width - $lineLength) / 2;
        return str_repeat(' ', (int) floor($padding)) . $line . str_repeat(' ', (int) ceil($padding));
    }

    // Navigation/scrolling support
    public function navigateUp(): void
    {
        // Override in subclasses that support navigation
    }

    public function navigateDown(): void
    {
        // Override in subclasses that support navigation
    }

    public function pageUp(): void
    {
        // Override in subclasses that support pagination
    }

    public function pageDown(): void
    {
        // Override in subclasses that support pagination
    }

    public function scrollToTop(): void
    {
        // Override in subclasses that support scrolling
    }

    public function scrollToBottom(): void
    {
        // Override in subclasses that support scrolling
    }

    // Search support
    public function enterSearchMode(): void
    {
        // Override in subclasses that support search
    }

    public function exitSearchMode(): void
    {
        // Override in subclasses that support search
    }

    public function addSearchChar(string $char): void
    {
        // Override in subclasses that support search
    }

    public function removeSearchChar(): void
    {
        // Override in subclasses that support search
    }

    public function isInSearchMode(): bool
    {
        return $this->getState('searchMode', false);
    }

    public function isInteractive(): bool
    {
        return false; // Override in subclasses
    }

    public function isPaused(): bool
    {
        return false; // Override in subclasses
    }
}