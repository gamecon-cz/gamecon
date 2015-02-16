<?php


if($url->cast(1)) {
  $blog = Novinka::zUrl($url->cast(1), Novinka::BLOG);
  if(!$blog) throw new UrlNotFoundException();
  $t->assign('blog', $blog);
  $t->parse('blog.post');
} else {
  $this->bezDekorace(true);
  // TODO možno přidat tagy
  $t->parseEach(Novinka::zTypu(Novinka::BLOG), 'blog', 'blog.seznam.post');
  $t->parse('blog.seznam');
}
