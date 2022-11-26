<?php

class DbffPkey extends DbFormField
{

    public function display() {
        return self::RAW;
    }

    public function html() {
        return '<input type="hidden" name="' . $this->postName() . '" value="' . $this->value() . '">';
    }

    public function loadPost() {
        $this->value($this->postValue());
    }

}
