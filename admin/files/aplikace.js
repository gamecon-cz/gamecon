
//staré api
function ukaz(klic) {
  $('#objekt'+klic).toggle();
}

$(function(){ // korekce výšky
  var preteklo = $(window).height() < $('.sloupL').height();
  var mobilni = (/iPhone|iPod|iPad|Android|BlackBerry/).test(navigator.userAgent);
  if( mobilni || preteklo ){
    $('.sloupL').css('position', 'absolute');
  }
});
