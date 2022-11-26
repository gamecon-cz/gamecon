<?php

class Medailonek extends DbObject
{

    protected static $tabulka = 'medailonky';

    public function drd(): string {
        return markdownNoCache($this->r['drd']);
    }

    public function oSobe(): string {
        return markdownNoCache($this->r['o_sobe']);
    }

}
