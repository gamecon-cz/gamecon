$(function(){

  $('.prezence .omnibox').on('autocompleteselect', function(event, ui){
    var uid = ui.item.value;
    $(this).closest('tr').before('<tr><td></td><td>' + uid + '</td><td>' + ui.item.label + '</td><td></td><td><input type="checkbox" name="dorazil[' + uid + ']" checked></td></tr>');
    // vyrušení default výběru do boxu
    event.preventDefault();
    $(this).val('');
  });

  $('.formAktivita').submit(function(){
    var aktivita = $(this).closest('.blokAktivita');
    // test na vyplnění políček / potvrzení
    var policek = aktivita.find('[type=checkbox]').size();
    var vybrano = aktivita.find('[type=checkbox]:checked').size();
    if(vybrano < policek / 2) {
      if(!confirm('Opravdu uložit s účastí menší jak polovina?')) return false;
    }
    // odeslání
    aktivita.find('[type=submit]').attr('disabled', true);
    aktivita.load(
      document.URL + ' .blokAktivita[data-id=' + aktivita.data('id') + '] > *',
      $(this).serializeObject()
    );
    return false;
  });

});
