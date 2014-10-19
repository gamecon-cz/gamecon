<?php

/**
 * @todo distinguish between nulls and empty strings
 */
class DbffString extends DbFormField {

  function html() {
    return '<input type="text" name="'.$this->postName().'" value="'.htmlspecialchars($this->value()).'">';
  }

  function loadPost() {
    $this->value($this->postValue());
  }

}
