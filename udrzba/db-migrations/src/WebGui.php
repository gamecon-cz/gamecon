<?php

namespace Godric\DbMigrations;

class WebGui
{

    private $headerPrinted = false;
    private $originalEnvironment = [];

    private static $environment = [
        'display_errors' => true,
        'error_reporting' => E_ALL,
        'html_errors' => false,
    ];

    public function cleanupEnviroment() {
        foreach ($this->originalEnvironment as $name => $value) {
            ini_set($name, $value);
        }

        // print html page tail
        if ($this->headerPrinted) {
            require __DIR__ . '/../templates/WebGuiTail.php';
            exit();
        }
    }

    public function configureEnviroment() {
        foreach (self::$environment as $name => $value) {
            $this->originalEnvironment[$name] = ini_set($name, $value);
        }
    }

    public function confirm() {
        // variables for template
        $postName = get_class() . '/confirm';
        $confirmed = $_POST[$postName] ?? false;

        if (!$this->headerPrinted) {
            require __DIR__ . '/../templates/WebGuiHead.php';
            $this->headerPrinted = true;
        }

        if (!$confirmed) {
            exit();
        }
    }

}
