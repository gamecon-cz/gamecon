<?php

class DbffTextAutoresize extends DbffText
{

    function html()
    {
        ob_start();
        ?>
        <script src="files/jquery.autosize-1.18.13.js"></script>
        <script>
            $('textarea').last().autosize();
        </script>
        <?php
        return parent::html() . ob_get_clean();
    }

}
