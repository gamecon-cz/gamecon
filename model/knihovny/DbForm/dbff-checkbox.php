<?php

/**
 * @todo distinguish between nulls and empty strings
 */
class DbffCheckbox extends DbFormField
{

    public function html(): string
    {
        $checked = $this->value() ? 'checked' : '';
        return <<<HTML
            <input type="checkbox" name="{$this->postName()}" value="1" {$checked}>
HTML;
    }

    public function loadPost()
    {
        $this->value($this->postValue());
    }

}
