<?php

/**
 * Zbastlená třída pro vybírátko '1 z N možností', vhodno udělat nějakou obecnou a toto jen oddědit nebo konfigurovat
 */
class DbffSelect extends DbFormField
{

    /**
     * Pokud $c odpovídá popiskům možností stylu číslo-název, rozdělí a vrátí pole s možnostmi, indexy jsou hodnoty
     */
    static function commentSplit($c)
    {
        $rv = '@((^|,? )(\d)-)@';
        $v  = preg_split($rv, $c, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);
        if (count($v) == 1) return null;
        $out = [];
        foreach ($v as $m) {
            $i       = substr($c, $m[1] - 2, 1); // pro víceznakové id nutno opravit
            $out[$i] = $m[0];
        }
        return $out;
    }

    function html()
    {
        $out = '';
        foreach ($this->moznosti() as $v => $m) {
            $sel = $this->value() == $v ? 'selected="true"' : '';
            $out .= '<option value="' . $v . '" ' . $sel . '>' . $m . '</option>';
        }
        $out = '<select name="' . $this->postName() . '">' . $out . '</select>';
        return $out;
    }

    function loadPost()
    {
        $this->value($this->postValue());
    }

    function moznosti()
    {
        return self::commentSplit($this->d['Comment']);
    }

}
