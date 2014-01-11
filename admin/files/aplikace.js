
//staré api
function ukaz(klic) {
  $('#objekt'+klic).toggle();
}

$(function(){ // korekce výšky
  if( $(window).height() < $('.sloupL').height() ){
    $('.sloupL').css('position', 'absolute');
  }
});
