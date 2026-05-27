<?php

namespace Godric\DbMigrations;

class WebGui {

    private
        $headerPrinted = false,
        $originalEnviroment = [];

    private static
        $enviroment = [
            'display_errors'    =>  true,
            'error_reporting'   =>  E_ALL,
            'html_errors'       =>  false,
        ];

    function cleanupEnviroment() {
        foreach ($this->originalEnviroment as $name => $value) {
            ini_set($name, $value);
        }

        // print html page tail
        if ($this->headerPrinted) {
            require __DIR__ . '/../templates/WebGuiTail.php';
            die();
        }
    }

    function configureEnviroment() {
        foreach (self::$enviroment as $name => $value) {
            $this->originalEnviroment[$name] = ini_set($name, $value);
        }
    }

    function confirm(Migration $migration) {
        // variables for template
        $postName  = get_class() . '/confirm';
        $confirmed = $_POST[$postName] ?? false;

        if (!$this->headerPrinted) {
            require __DIR__ . '/../templates/WebGuiHead.php';
            $this->headerPrinted = true;
        }

        if (!$confirmed) die();
    }

}
