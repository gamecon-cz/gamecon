<?php

use Gamecon\Web\NahledMarkdownu;
use Gamecon\XTemplate\XTemplate;

class DbffMarkdownDirect extends DbFormField
{
    function display()
    {
        return self::CUSTOM;
    }

    function html()
    {
        $t = new XTemplate(__DIR__ . '/dbff-markdown-direct.xtpl');
        $t->assign([
            //'pnOldId' => $this->postName('oldVal'),
            //'oldId'   => $this->value(),
            'pnText'  => $this->postName(),
            'text'    => htmlspecialchars($this->value()),
            'mdText'  => NahledMarkdownu::obrazkyNaWebu($this->nahledHtml()),
            'urlWebu' => json_encode(URL_WEBU, JSON_THROW_ON_ERROR),
            'bezpecnyNahled' => $this->pravidlaBezpecnehoNahledu() === null
                ? ''
                : ' data-bezpecny-nahled="' . htmlspecialchars(json_encode($this->pravidlaBezpecnehoNahledu(), JSON_THROW_ON_ERROR)) . '"',
        ]);
        $t->parse('md');
        return $t->text('md');
    }

    /**
     * Náhled textu při načtení formuláře
     */
    protected function nahledHtml(): string
    {
        return (string) markdown($this->value());
    }

    /**
     * Pravidla, podle kterých živý náhled v prohlížeči čistí HTML; null = HTML z textu projde celé (text píše organizátor)
     *
     * @return array<string, mixed>|null
     */
    protected function pravidlaBezpecnehoNahledu(): ?array
    {
        return null;
    }

    function loadPost(): void
    {
        $this->value($this->postValue());
    }

    function value()
    {
        if (func_num_args() == 1) {
            parent::value(func_get_arg(0));
        }
        return (string)parent::value();
    }

}
