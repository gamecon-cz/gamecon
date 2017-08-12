
/**
 * Jednoduchý hack který v programu vyvolá ajaxové načítání změněných políček.
 * Jde o specifické rozšíření k admin. programu, obecné řešení musí vypadat
 * jinak.
 */

$(function(){

  var setHandler = function(element) {
    element.on('submit', function(e){
      var id = $(this).find('input').val();
      var blok = $(this).closest('td');
      blok.find('a').replaceWith('…');
      $.post(document.URL, $(this).serialize(), function(data){
        var a = $(data).find(
          'input[name="odhlasit"][value="'+id+'"], ' +
          'input[name="prihlasit"][value="'+id+'"], ' +
          'input[name="cAktivitaPlusminusm"][value="'+id+'"], ' +
          'input[name="cAktivitaPlusminusp"][value="'+id+'"]'
        );
        var err = $(data).find('#chybovaZprava');
        var fin = $(data).find('#stavUctu');
        blok.replaceWith(a.closest('td'));
        $('#stavUctu').replaceWith(fin);
        setHandler(a.closest('td').find('form'));
        if(err.length) {
          alert(err.html());
        }
      });
      return false;
    });
  }

  setHandler($('form'));

});
