<?php

namespace Godric\DbMigrations;

class WebGui
{

    private bool  $headerPrinted       = false;
    private array $originalEnvironment = [];

    private static array $environment = [
        'display_errors'  => true,
        'error_reporting' => E_ALL,
        'html_errors'     => false,
    ];

    public function __construct(private readonly bool $exitAfter = true)
    {
    }

    public function cleanupEnvironment(bool $displayOkButton = true): void
    {
        foreach ($this->originalEnvironment as $name => $value) {
            ini_set($name, $value);
        }

        if ($displayOkButton && $this->headerPrinted) {
            // print html page tail
            require __DIR__ . '/../templates/WebGuiTail.php';
            $this->exitIfWanted();
        }
    }

    private function exitIfWanted(bool $exit = null): void
    {
        $exit = $exit ?? $this->exitAfter;
        if ($exit) {
            exit;
        }
    }

    public function configureEnvironment(): void
    {
        foreach (self::$environment as $name => $value) {
            $this->originalEnvironment[$name] = ini_set($name, $value);
        }
    }

    public function answered(): bool
    {
        return !empty($_POST[self::getPostName()]);
    }

    private static function getPostName(): string
    {
        return static::class . '/confirm';
    }

    public function confirm(bool $exitOnRefuse = true): bool
    {
        // variables for template
        $confirmed = $_POST[self::getPostName()] ?? false;

        if (!$this->headerPrinted) {
            // print html page header
            $postName = self::getPostName();
            require __DIR__ . '/../templates/WebGuiHead.php';
            $this->headerPrinted = true;
        }

        if (!$confirmed) {
            $this->exitIfWanted($exitOnRefuse || !$this->answered());
            return false;
        }

        return true;
    }

    public function writeMessage(string $message, string $newLineAfter = "\n"): void
    {
        echo $message . $newLineAfter;
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
    }

}
