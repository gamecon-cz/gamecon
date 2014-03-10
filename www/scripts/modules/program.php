<?php

$osobniProgram=isset($osobniProgram)?$osobniProgram:false;

$program=new Program($u,$osobniProgram);
if($u) Aktivita::prihlasovatkoZpracuj($u);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="cs" lang="cs">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <?php $program->css(); ?>
    <style>
      body { 
        font-family: tahoma;
        font-size: 11px;
        text-align: center;
        background-color: #f0f0f0; }
    </style>
    <script type="text/javascript" src="files/jquery.js"></script>
    <script type="text/javascript">
      var _gaq = _gaq || [];
      _gaq.push(['_setAccount', 'UA-30024399-1']);
      _gaq.push(['_trackPageview']);
      (function() {
        var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
        ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
        var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
      })();
    </script>
  </head>
  <body>
  
  <?php echo Chyba::vyzvedniHtml(); ?>
  
  <?php $program->tisk(); ?>
  
  </body>
</html>

<?php

//předáme info volajícímu skriptu, že řešíme výstup sami
$VLASTNI_VYSTUP=true;

?>
