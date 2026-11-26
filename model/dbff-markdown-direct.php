<?php

use Gamecon\Jazyk\Jazyk;
use Gamecon\Web\EditorTextuPozadavek;
use Gamecon\Web\NahledMarkdownu;
use Gamecon\XTemplate\XTemplate;

/**
 * Text v Markdownu (sloupec s komentářem `markdown`) s editorem a náhledem vedle něj: stránky, novinky a blog,
 * medailonky. Editor je CodeMirror z `ui/src/editorTextu` a náhled mu vykresluje server (@see EditorTextuPozadavek),
 * stejně jako web. Modul, který pole zobrazuje, musí na začátku volat @see EditorTextuPozadavek::obslouzPokudJe().
 */
class DbffMarkdownDirect extends DbFormField
{
    private const SKRIPT_EDITORU = 'files/ui/editor-textu.js';

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
            'pnText'        => $this->postName(),
            'text'          => htmlspecialchars($this->value()),
            'mdText'        => NahledMarkdownu::obrazkyNaWebu($this->nahledHtml()),
            'skriptEditoru' => htmlspecialchars(self::adresaSkriptuEditoru()),
            'odUzivatele'   =>$this->isTextOdUzivatele() ? ' data-editor-textu-od-uzivatele' : '',
            // živý náhled při psaní má stejnou typografii jako náhled při načtení (@see nahledHtml()); bez jazyka česká
            'jazykTextu'    => $this->getJazykTextu()->jeVychozi()
                ? ''
                : ' data-editor-textu-jazyk="' . $this->getJazykTextu()->value . '"',
        ]);
        $t->parse('md');
        return $t->text('md');
    }

    /**
     * S otiskem obsahu, ať prohlížeč po nasazení nového editoru nepoužije starý z cache
     */
    private static function adresaSkriptuEditoru(): string
    {
        $soubor = ADMIN . '/' . self::SKRIPT_EDITORU;

        return self::SKRIPT_EDITORU . (is_file($soubor) ? '?version=' . md5_file($soubor) : '');
    }

    /**
     * Náhled textu při načtení formuláře
     */
    protected function nahledHtml(): string
    {
        return (string) markdown($this->value(), $this->getJazykTextu());
    }

    /**
     * Píše text uživatel sám, ne organizátor? Náhled pak ukáže jen vyčištěné HTML, i ten, který si editor načítá při
     * psaní (pole dostane `data-editor-textu-od-uzivatele`, @see EditorTextuPozadavek); jinak HTML z textu projde celé.
     */
    protected function isTextOdUzivatele(): bool
    {
        return false;
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
