<?php

$blog = Novinka::zUrl($url->cast(1), Novinka::BLOG);
if(!$blog) throw new UrlNotFoundException();
$t->assign('blog', $blog);
