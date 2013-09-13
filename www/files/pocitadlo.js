$(window).bind('load', function(){
  setInterval(function(){document.getElementById('pocitadlo').innerHTML = obnovPocitadlo(sekundyPocitadloKonecOdpoctu);}, 1000);
});

function obnovPocitadlo(sekundyPocitadloKonecOdpoctu){
  var sirkaCisla = 17;
  var vyskaCisla = 28;
  var datum = new Date();
  var rozdilSekund = Math.abs(Math.floor(sekundyPocitadloKonecOdpoctu - datum.getTime()/1000));
  var dny = Math.floor(rozdilSekund / 86400).toString();
  var zbytek = rozdilSekund % 86400;
  var hodiny = Math.floor(zbytek / 3600).toString();
  var zbytek = zbytek % 3600;
  var minuty = Math.floor(zbytek / 60).toString();
  var sekundy = (zbytek % 60).toString();
  var pocitadlo = '';
  
  while(dny.length < 2){
    dny = '0' + dny;
  }
  while(hodiny.length < 2){
    hodiny = '0' + hodiny;
  }
  while(minuty.length < 2){
    minuty = '0' + minuty;
  }
  while(sekundy.length < 2){
    sekundy = '0' + sekundy;
  }
  for(i = 0; i < dny.length; i++){
    pocitadlo += '<img src="http://beta.gamecon.cz/files/styly/styl-aktualni/pocitadlo_' + dny.charAt(i) + '.gif" alt="' + dny.charAt(i) + '" width="' + sirkaCisla + '" height="' + vyskaCisla + '" />';
  }
  pocitadlo += '<img src="http://beta.gamecon.cz/files/styly/styl-aktualni/pocitadlo_tecka.gif" alt="." width="' + sirkaCisla + '" height="' + vyskaCisla + '" />';
  for(i = 0; i < hodiny.length; i++){
    pocitadlo += '<img src="http://beta.gamecon.cz/files/styly/styl-aktualni/pocitadlo_' + hodiny.charAt(i) + '.gif" alt="' + hodiny.charAt(i) + '" width="' + sirkaCisla + '" height="' + vyskaCisla + '" />';
  }
  pocitadlo += '<img src="http://beta.gamecon.cz/files/styly/styl-aktualni/pocitadlo_dvojtecka.gif" alt=":" width="' + sirkaCisla + '" height="' + vyskaCisla + '" />';
  for(i = 0; i < minuty.length; i++){
    pocitadlo += '<img src="http://beta.gamecon.cz/files/styly/styl-aktualni/pocitadlo_' + minuty.charAt(i) + '.gif" alt="' + minuty.charAt(i) + '" width="' + sirkaCisla + '" height="' + vyskaCisla + '" />';
  }
  pocitadlo += '<img src="http://beta.gamecon.cz/files/styly/styl-aktualni/pocitadlo_dvojtecka.gif" alt=":" width="' + sirkaCisla + '" height="' + vyskaCisla + '" />';
  for(i = 0; i < sekundy.length; i++){
    pocitadlo += '<img src="http://beta.gamecon.cz/files/styly/styl-aktualni/pocitadlo_' + sekundy.charAt(i) + '.gif" alt="' + sekundy.charAt(i) + '" width="' + sirkaCisla + '" height="' + vyskaCisla + '" />';
  }
  return pocitadlo;
}
