<?php

use Gamecon\XTemplate\XTemplate;

class DbffMarkdown extends DbFormField
{

    private $oldVal;
    private $text;

    function display()
    {
        return self::CUSTOM;
    }

    function html()
    {
        $t = new XTemplate(__DIR__ . '/dbff-markdown.xtpl');
        $t->assign([
            'pnOldId' => $this->postName('oldVal'),
            'oldId'   => $this->value(),
            'pnText'  => $this->postName('text'),
            'text'    => htmlspecialchars(dbText($this->value())),
            'mdText'  => dbMarkdown($this->value()),
        ]);
        $t->parse('md');
        return $t->text('md');
    }

    function loadPost()
    {
        $this->oldVal = $this->postValue('oldVal');
        $this->text   = $this->postValue('text');
    }

    function preInsert()
    {
        $this->value(dbTextHash($this->text));
    }

    function postInsert()
    {
        $this->value(dbTextClean($this->oldVal));
    }

}
