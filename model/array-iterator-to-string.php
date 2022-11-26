<?php

class ArrayIteratorToString extends ArrayIterator
{

    public function __toString() {
        return implode(', ', (array)$this);
    }

}
