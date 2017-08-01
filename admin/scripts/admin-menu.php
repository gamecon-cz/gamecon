<?php

/**
 * Třída administračního menu. Načte si stránky z dané složky podle zadaného
 * klíče, sestaví menu.
 */

class AdminMenu
{
  var $menu=array();
  
  function __construct($src)
  {
    if(substr($src,0,2)=='./') $src=substr($src,2);
    $d=opendir('./'.$src); 
    while(($file=readdir($d))!==FALSE)
    { 
      //echo($file.' '.is_dir($src.$file).'<br />');
      if(strpos($file,'.php') && substr($file,0,1)!='_')
      { //načtení souboru, vyhledání proměnných v hlavičkách
        $url=substr($file,0,-4);
        $fc=file_get_contents('./'.$src.$file,false,null,0,2048);
        preg_match('@\* nazev: (.*)@',$fc,$m);
        $this->menu[$url]['nazev']=$m[1];
        preg_match('@\* pravo: (.*)@',$fc,$m);
        $this->menu[$url]['pravo']=(int)$m[1];
        $this->menu[$url]['soubor']=$src.$file;
      }
      elseif(strpos($file,'.')===false && strpos($file,'_')===false 
        && is_dir($src.$file))
      {
        $url=$file;
        $fc=file_get_contents($src.$file.'/'.$file.'.php',false,null,0,2048);
        preg_match('@\* nazev: (.*)@',$fc,$m);
        $this->menu[$url]['nazev']=$m[1];
        preg_match('@\* pravo: (.*)@',$fc,$m);
        $this->menu[$url]['pravo']=(int)$m[1];
        $this->menu[$url]['soubor']=$src.$file.'/'.$file.'.php';
        $this->menu[$url]['submenu']=1;
        /*while(($sf=readdir($sd))!==FALSE)
        { 
          if(strpos($sf,'.php'))
          { //načtení souboru, vyhledání proměnných v hlavičkách
            $url=substr($sf,0,-4);
            $fc=file_get_contents($src.$file.$sf,false,null,-1,2048);
            preg_match('@\* nazev: (.*)@',$fc,$m);
            $this->menu[$url]['nazev']=$m[1];
            preg_match('@\* pravo: (.*)@',$fc,$m);
            $this->menu[$url]['pravo']=(int)$m[1];
          }
        }*/
      }
    }
    closedir($d);
    uasort($this->menu,function($a,$b){ //třídění dle práva, pak názvu
      $diff=$a['pravo']-$b['pravo'];
      if($diff<>0)
        return $diff;
      else
        return strcmp($a['nazev'],$b['nazev']);
    });
  }
  
  /** Export menu do pole. Iterátory a věci (?) */
  function pole()
  { return $this->menu; }
  
}
