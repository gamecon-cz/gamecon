<?php

/**
 * @todo distinguish between nulls and empty strings
 */
class DbffText extends DbFormField
{

    public function html()
    {
        return
            '<textarea name="' . $this->postName() . '">' .
            htmlspecialchars((string)$this->value()) .
            '</textarea>';
    }

    public function loadPost()
    {
        $this->value($this->postValue());
    }

}
