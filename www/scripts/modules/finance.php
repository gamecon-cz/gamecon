<?php

if(!$u){ echo hlaska('jenPrihlaseni'); return; } //jen přihlášení
if(!REGISTRACE_AKTIVNI){ echo hlaska('prihlaseniVypnuto'); return; } //reg neaktivní

UzivatelskaAktivita::postPrihlasOdhlas($u); //zpracování post požadavků na odhlašování aktivit z přehledu

$fin=$u->finance(array('detail'=>true));
echo $u->finance(array('detail'=>true))->prehledHtml();
if(!$u->gcPrihlasen()) 
  return; //přehled vidí jen přihlášení na gc
if($u->finance()->gamecoruny()<0 || $u->finance()->bonus()<0)
{
  $castka=-$fin->gamecoruny();
  $castkaPozde=-$fin->gamecoruny()-($fin->bonus()<0?$fin->bonus():0);
  if($u->stat()=='CZ')
  {
    $castka.='&thinsp;Kč';
    $castkaPozde.='&thinsp;Kč';
  }
  elseif($u->stat()=='SK')
  {
    $castka=round($castka/KURZ_EURO,2).'&thinsp;€';
    $castkaPozde=round($castkaPozde/KURZ_EURO,2).'&thinsp;€';
  }
  echo '<p>GameCon můžeš nyní <strong>zaplatit převodem</strong> na účet uvedený níž. Jako variabilní symbol slouží tvoje id: '.$u->id().'. ';
  if(SLEVA_AKTIVNI)
    echo 'Při platbě <strong>do '.datum3(SLEVA_DO_DATE).'</strong> platíš celkem <strong>'.$castka.'</strong>, při pozdější platbě beze slevy nebo na místě pak '.$castkaPozde.'.</p><p>';
  else
    echo 'Celkem je potřeba zaplatit <strong>'.$castkaPozde.'</strong>, případný nedoplatek je možné zaplatit i na místě.</p><p>'; 
  if($u->stat()=='CZ')
    echo '<strong>Číslo účtu:</strong> 2800035147/2010';
  elseif($u->stat()=='SK')
    echo '<strong>Číslo účtu pro SR:</strong> 2800035147/8330 (Platba v Eurech)';
  echo '<br /><strong>Variabilní symbol:</strong> '.$u->id().'<br />';
  echo '<strong>Částka k zaplacení:</strong> '.(SLEVA_AKTIVNI?$castka:$castkaPozde).'</p>';
}
elseif($u->finance()->gamecoruny()==0 && $u->finance()->bonus()>=0)
{
  echo '<p>Všechny tvoje pohledávky jsou <strong style="color:green">v pořádku zaplaceny</strong>, není potřeba nic platit. Pokud si ale chceš dokupovat aktivity na místě se slevou nebo bez nutnosti používat hotovost, můžeš si samozřejmě kdykoli převést peníze do zásoby na:</p><p>';
  if($u->stat()=='CZ')
    echo '<strong>Číslo účtu:</strong> 2800035147/2010';
  elseif($u->stat()=='SK')
    echo '<strong>Číslo účtu pro SR:</strong> 2800035147/8330 (Platba v Eurech)';
  echo '<br /><strong>Variabilní symbol:</strong> '.$u->id().'</p>';
}
else
{
  echo '<p>Rezervu na svém účtu můžeš využít pro přihlášení dalších aktivit na místě přes <strong>infopult</strong> nebo na stejném místě vybrat v hotovosti. Pokud ji nevyužiješ ani nevybereš, zůstává ti do dalších ročníků (bonus však propadá).';
}

?>

