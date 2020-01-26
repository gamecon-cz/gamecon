$(function() {

  // Našeptávátko pro omnibox
  $vyberUzivatele = $('.omnibox');
  $vyberUzivatele.autocomplete({
    source: 'ajax-omnibox',
    minLength: 2,
    autoFocus: true, // automatický výběr první hodnoty, aby uživatel mohl zmáčknout rovnou enter
    focus: function(event,ui) {
      event.preventDefault(); // neměnit text inputu při výběru
    },
    select: function(event,ui) {
      // automatické odeslání, pokud je nastaveno
      if($(this).hasClass('autosubmit') && $(this).parent().is('form')) {
        $(this).val(ui.item.value); // nutno nastavit před submitem
        $(this).parent().submit();
      }
    }
  });

  // Klávesové zkratky
  $(document).on('keydown', null, 'alt+u', function(){
    $('#omnibox').focus();
    return false;
  }).on('keydown', null, 'alt+z', function(){
    $('#zrusit').submit();
    return false;
  });

});
