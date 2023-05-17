<?php

/**
 * Třída Chyby je speciální typ chyby / výjimky zobrazitelné uživateli, která
 * obaluje více dílčích chyb najednou.
 *
 * Používá se např. pro chyby formulářů.
 *
 * Obsahuje více chyb vztahujících se k různým klíčům (nazvům formulářových
 * polí) a případně jednu "globální" chybu (typicky dotýkající se více / všech
 * polí ve formuláři).
 */
class Chyby extends Chyba
{
    private array $chyby = [];
    private       $globalniChyba;

    public function globalniChyba($val = null)
    {
        if (isset($val)) {
            $this->globalniChyba = $val;
        }
        return $this->globalniChyba;
    }

    public function klic($klic)
    {
        return $this->chyby[$klic] ?? null;
    }

    /**
     * @return string[]
     */
    public function vsechny(): array
    {
        return $this->chyby;
    }

    /**
     * Vytvoří chyby obsahující jednu globální chybu.
     */
    public static function jedna($chybaZprava): static
    {
        $ch                = new static();
        $ch->globalniChyba = $chybaZprava;
        return $ch;
    }

    /**
     * @param string[] $pole
     * Vytvoří chyby z pole řetězců.
     */
    public static function zPole(array $pole): static
    {
        $ch          = new static();
        $ch->chyby   = $pole;
        $ch->message = implode(', ', $pole); // kvůli čitelnosti testů
        return $ch;
    }
}
