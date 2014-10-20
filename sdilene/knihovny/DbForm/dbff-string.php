<?php

/**
 * @todo distinguish between nulls and empty strings
 */
class DbffString extends DbFormField {

  function html() {
    $extras = array();
    if($this->d['Null'] == 'NO') $extras[] = 'required="true"';
    $extras = ' '.implode(' ', $extras);
    return '<input type="text" name="'.$this->postName().'" value="'.htmlspecialchars($this->value()).'"'.$extras.'>';
  }

  function loadPost() {
    $this->value($this->postValue());
  }

}
