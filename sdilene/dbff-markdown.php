<?php

class DbffMarkdown extends DbFormField {

  private $oldVal;
  private $text;

  function display() {
    return self::CUSTOM;
  }

  function html() {
    $t = new XTemplate(__DIR__.'/dbff-markdown.xtpl');
    $t->assign(array(
      'pnOldId' => $this->postName('oldVal'),
      'oldId'   => $this->value(),
      'pnText'  => $this->postName('text'),
      'text'    => htmlspecialchars(dbText($this->value())),
      'mdText'  => dbMarkdown($this->value()),
    ));
    $t->parse('md');
    return $t->text('md');
  }

  function loadPost() {
    $this->oldVal = $this->postValue('oldVal');
    $this->text = $this->postValue('text');
  }

  /**
   * Pozor, použití dbText tady sází na vypnutou referenční integritu, jinak
   * by selhalo mazání starého textu. To ale předpokládá i dbText jako takový.
   */
  function preInsert() {
    $hash = dbText($this->oldVal, $this->text);
    $this->value($hash);
  }

}
