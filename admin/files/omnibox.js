$(function() {

  // Našeptávátko pro omnibox
  $vyberUzivatele = $('#omnibox');
  $vyberUzivatele.autocomplete({
    source: 'ajax-omnibox',
    minLength: 2,
    autoFocus: true, // automatický výběr první hodnoty, aby uživatel mohl zmáčknout rovnou enter
    focus: function(event,ui) {
      event.preventDefault(); // neměnit text inputu při výběru
    },
    select: function(event,ui) {
      // pokud je součástí formuláře, automaticky odeslat
      if($(this).parent().is('form')){ // TODO pravděpodobně přepsat na třídu, kterou se u formu/inputu tato vlastnost vyvolá
        $(this).val(ui.item.value); // nutno nastavit před submitem
        $(this).parent().submit();
      }
    }
  });

  // Klávesové zkratky
  $(document).bind('keydown', 'alt+u', function(){
    $('#omnibox').focus();
    return false;
  }).bind('keydown', 'alt+z', function(){
    $('#zrusit').submit();
    return false;
  });

});
