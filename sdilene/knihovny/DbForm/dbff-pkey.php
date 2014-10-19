<?php

class DbffPkey extends DbFormField {

  function display() {
    return self::RAW;
  }

  function html() {
    return '<input type="hidden" name="'.$this->postName().'" value="'.$this->value().'">';
  }

  function loadPost() {
    $this->value($this->postValue());
  }

}
