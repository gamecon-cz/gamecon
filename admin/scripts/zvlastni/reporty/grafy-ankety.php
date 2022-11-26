<?php

require __DIR__ . '/sdilene-hlavicky.php';

$hodnoty='';
$graf='';

if(post('hodnoty'))
{
  $hodnoty=explode("\n",post('hodnoty'));
  $matice=[]; //matice[typ][hodnoceni]=pocet
  $maticeSum=[];
  $hlavicky=explode("\t",$hodnoty[0]);
  $relevanceSlovne=[1=>'velmi důležité', 2=>'důležité a víc', 3=>'středně důležité a víc', 4=>'není jim to jedno', 5=>'(ignorovat)'];
  unset($hlavicky[count($hlavicky)-1]);//poslední prvek zrušit, je nějak vadný (nové řádky or)
  unset($hodnoty[0]);
  foreach($hodnoty as $r)
  {
    foreach(explode("\t",$r) as $i=>$h)
    {
      //$hlav=substr($hlavicky[$i],strpos($hlavicky[$i],'[')+1,-1);
      if($i==13) continue; //poslední sloupec je nějak debilní, ingorujeme ho
      if($h=='Nedůležité') $h=5;
      elseif($h=='Velmi důležité') $h=1;
      elseif($h=='') continue;
      @$matice[$h][$i]++;
    }
  }
  ksort($matice);
  //var_dump($hlavicky);
  //var_dump($matice);
  $graf.='["relevance",';
  foreach($hlavicky as $h)
    $graf.='"'.substr($h,strpos($h,'[')+1,-1).'",';
  $graf=substr($graf,0,-1);
  $graf.='],'."\n";
  foreach($matice as $relevance => $set)
  {
    if($relevance==5) break; //poslední relevanci nekreslit
    ksort($set);
    $graf.='["'.$relevanceSlovne[$relevance].'",';
    for($i=0;$i<13;$i++)
    {
      $prvek=$set[$i];
      $graf.=($prvek+(@$minuly[$i])).',';
      $minuly[$i]=$prvek+(@$minuly[$i]); //sčítání
    }
    $graf=substr($graf,0,-1);
    $graf.="],\n";
  }
}

?>
<html>
  <head>
    <!--Load the AJAX API-->
    <script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript">

      // Load the Visualization API and the piechart package.
      google.load('visualization', '1.0', {'packages':['corechart']});

      // Set a callback to run when the Google Visualization API is loaded.
      google.setOnLoadCallback(drawChart);

      // Callback that creates and populates a data table,
      // instantiates the pie chart, passes in the data and
      // draws it.
      function drawChart() {
        var data = google.visualization.arrayToDataTable([
          <?php echo $graf ?>
        ]);

        var options = {
          title: 'Prioritizace'
        };

        var chart = new google.visualization.LineChart(document.getElementById('chart_div'));
        chart.draw(data, options);
      }
    </script>
  </head>
  <body>

    <p>Zkopírujte do boxu sloupce z aknekty</p>

    <form method="post">
      <textarea style="width:600px;height:400px" name="hodnoty"><?php echo post('hodnoty') ?></textarea>
      <br /><input type="submit" value="Načíst">
    </form>

    <div id="chart_div" style="height:800px;margin-left:-200px"></div>
  </body>
</html>

