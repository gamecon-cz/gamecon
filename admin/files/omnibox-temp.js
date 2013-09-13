/** temp kopie omniboxu pro první stránku */

function var_dump(obj) {
   if(typeof obj == "object") {
      return "Type: "+typeof(obj)+((obj.constructor) ? "\nConstructor: "+obj.constructor : "")+"\nValue: " + obj;
   } else {
      return "Type: "+typeof(obj)+"\nValue: "+obj;
   }
}//end function var_dump


var omniTimeout=500;

/** nastaví inputům vyvolání omniboxu s určitým spožděním */
$('.omnibox').keyup(omniKeyup);

/** Funkce obsluhující keyup událost omnibox inputu */
function omniKeyup(e)
{
  var key=e.keyCode;
  if((key<37 || key>40) && key!=13) //ignorovat šipky a enter
  {
    //zrušíme aktuální timer, pokud existuje
    if($(this).data('timerId'))
       window.clearTimeout($(this).data('timerId'));
    //pokud je vstup aspoň tříznakový
    //nastavíme nový timer - efektivně se "odloží" odpálení timeru
    if($(this).val().length>=2)
      $(this).data('timerId',window.setTimeout(displayOmni,omniTimeout,$(this)));    
  }
}

/** Zobrazí reálný omnibox pod inputem */
function displayOmni(input)
{
  $.getJSON('/ajax-omnibox?q='+input.val(), function(data){
    //pokud není vidět omnibox, zobrazíme ho
    if(!(input.data('omniVisible')))
    {
      showOmni(input);
      input.data('omniVisible',true);
    }
    fillOmni(input,data);
  });  
}

/** Vyplní (resetuje a vyplní) data do omniboxu */
function fillOmni(input,data)
{
  var box=input.prev();
  box.empty();
  $.each(data,function(i,u){
    box.append(
      '<div class="omniLine">'+
      u.id_uzivatele+' '+
      u.login_uzivatele+' '+
      u.jmeno_uzivatele+' '+
      u.prijmeni_uzivatele+' '+
      u.mesto_uzivatele+' '+
      u.telefon_uzivatele+
      '</div>'
    );
    box.children().last().data('dataOmni',u);
  });
  box.children().first().addClass('active');
}

/** Vykreslí omnibox jako takový */
function showOmni(input)
{
  input.before('<div class="omniSelectbox"></div>');
  //funkce na výběr "focusu"
  input.keydown(function(e){
    //alert(e.keyCode);
    if(e.keyCode==38) //nahoru
    {
      e.preventDefault();
      e.preventDefault();
      var active=input.prev().children('.active');
      if(active.prev('.omniLine').length!=0) //viz dole
      {
        active.removeClass('active');
        active.prev().addClass('active');
      }
    }
    else if(e.keyCode==40) //dolů
    {
      e.preventDefault();
      var active=input.prev().children('.active');
      if(active.next('.omniLine').length!=0) //žádný další element třídy omniLine (=nulový počet elementů v jquery objektu)
      {
        active.removeClass('active');
        active.next().addClass('active');
      }
    }
    else if(e.keyCode==13) //enter
    {
      //e.preventDefault();
      var u=input.prev().children('.active').data('dataOmni');
      input.after('<input type="hidden" name="uzivatele_vybrat" value="1" />');
      input.after('<input type="hidden" name="id_uzivatele" value="'+u.id_uzivatele+'" />');
      //input.hide();
      //input.prev().remove();
      /*
      var u=input.prev().children('.active').data('dataOmni');
      input.prev().remove();
      var row=input.parent().parent();  //specifické, umožnit ruční úpravu
      row.empty();
      row.append(
        '<td>'+u.id_uzivatele+'</td>'+
        '<td>'+u.login_uzivatele+'</td>'+
        '<td>'+u.jmeno_uzivatele+' '+u.prijmeni_uzivatele+'</td>'+
        '<td>'+u.telefon_uzivatele+'</td>'+
        '<td><input type="checkbox" name="dorazil['+u.id_uzivatele+']" checked="checked" /></td>'
      );
      row.after(
        '<tr><td colspan="5"><input type="text" class="omnibox" /></td></tr>'
      );
      row.next().children().first().children().first().keyup(omniKeyup).focus();
      */
    }    
  });
}


