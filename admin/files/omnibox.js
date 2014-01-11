$(function() {

  // Našeptávátko pro omnibox
  $vyberUzivatele = $('#omnibox');
  $vyberUzivatele.autocomplete({
    source: '/ajax-omnibox',
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
  $.Shortcuts.add({
    type: 'down',
    mask: 'Alt+U',
    handler: function() { $('#omnibox').focus(); }
  }).add({
    type: 'down',
    mask: 'Alt+Z',
    handler: function() { $('#zrusit').submit(); }
  }).add({
    type: 'down',
    mask: 'Alt+M',
    handler: function() { $('#materialy').submit(); }
  }).start();

});
