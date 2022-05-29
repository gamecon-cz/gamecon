<?php

use Gamecon\XTemplate\XTemplate;

class DbffMarkdownDirect extends DbFormField
{

    function display() {
        return self::CUSTOM;
    }

    function html() {
        $t = new XTemplate(__DIR__ . '/dbff-markdown.xtpl');
        $t->assign([
            //'pnOldId' => $this->postName('oldVal'),
            //'oldId'   => $this->value(),
            'pnText' => $this->postName(),
            'text' => htmlspecialchars($this->value()),
            'mdText' => markdownNoCache($this->value()),
        ]);
        $t->parse('md');
        return $t->text('md');
    }

    function loadPost() {
        $this->value($this->postValue());
    }

}
