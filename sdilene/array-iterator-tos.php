<?php

class ArrayIteratorTos extends ArrayIterator {

  function __toString() {
    return implode(', ', (array)$this);
  }

}
