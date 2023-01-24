<?php

/**
 * @todo distinguish between nulls and empty strings
 */
class DbffString extends DbFormField
{

    public function html(): string {
        $extras = [];
        if ($this->d['Null'] == 'NO') {
            $extras[] = 'required="true"';
        }
        if ($this->d['Type'] == 'datetime') {
            $extras[] = 'placeholder="2010-01-31 05:00:00"';
        }
        $extras = ' ' . implode(' ', $extras);
        return '<input type="text" name="' . $this->postName() . '" value="' . htmlspecialchars((string)$this->value()) . '"' . $extras . '>';
    }

    public function loadPost() {
        $this->value($this->postValue());
    }

}
