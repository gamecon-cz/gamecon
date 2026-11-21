<?php

use Gamecon\Jazyk\Jazyk;
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
        $popisek = $this->label();
        if ($popisek !== '') {
            $t->assign('popisek', htmlspecialchars($popisek));
            $t->parse('md.popisek');
        }
        $t->assign([
            //'pnOldId' => $this->postName('oldVal'),
            //'oldId'   => $this->value(),
            'pnText'  => $this->postName(),
            'text'    => htmlspecialchars($this->value()),
            'mdText'  => NahledMarkdownu::obrazkyNaWebu((string) markdown($this->value(), $this->getJazykTextu())),
            'urlWebu' => json_encode(URL_WEBU, JSON_THROW_ON_ERROR),
        ]);
        $t->parse('md');
        return $t->text('md');
    }

    /**
     * Anglický sloupec (`obsah_en`) má v náhledu anglickou typografii jako na webu, ostatní českou.
     */
    private function getJazykTextu(): Jazyk
    {
        return str_ends_with((string) $this->name(), '_en')
            ? Jazyk::ANGLICTINA
            : Jazyk::CESTINA;
    }

    /**
     * Popisek je text komentáře sloupce za slovem markdown (`markdown, Obsah anglicky (…)`). Sloupec jen s `markdown`
     * popisek nemá, editor je pak jediný hlavní text formuláře.
     */
    public function label(): string
    {
        return mb_ucfirst(trim((string) preg_replace('~^markdown\s*(?:,|--)?~u', '', (string) ($this->d['Comment'] ?? ''))));
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
