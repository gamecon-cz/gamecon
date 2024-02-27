/* credits https://www.w3schools.com/howto/howto_js_sort_table.asp (slight changes) */
function sortTable(col, table) {
  var rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
  switching = true;
  dir = "asc"; 
  
  while (switching) {
    switching = false;
    rows = table.rows;
    /*Loop through all table rows (except the first, which contains table headers):*/
    for (i = 1; i < (rows.length - 1); i++) {
      //start by saying there should be no switching:
      shouldSwitch = false;
      /*Get the two elements you want to compare,
      one from current row and one from the next:*/
      if (col == 1) {
        x = $(rows[i]).attr('data-cas-posledni-zmeny-prihlaseni');
        y = $(rows[i+1]).attr('data-cas-posledni-zmeny-prihlaseni');
      } else {
        x = rows[i].getElementsByTagName("TD")[col].innerHTML.toLowerCase();
        y = rows[i + 1].getElementsByTagName("TD")[col].innerHTML.toLowerCase();
      }
      /*check if the two rows should switch place,
      based on the direction, asc or desc:*/
      if (dir == "asc") {
        if (x > y) {
          shouldSwitch= true;
          break;
        }
      } else if (dir == "desc") {
        if (x < y) {
          shouldSwitch = true;
          break;
        }
      }
    }
    if (shouldSwitch) {
      rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
      switching = true;
      switchcount ++;      
    } else {
      if (switchcount == 0 && dir == "asc") {
        dir = "desc";
        switching = true;
      }
    }
  }
} 

/* Logika řadicích šipek (asc/desc) */
(function ($) {
  $(function () {
    $('.lze-radit').each(function () {
      const sipka = this.querySelector('.razeni');
      this.addEventListener('click', function (event) {
        sortTable($(this).attr('data-order'), $(this).closest('table')[0]);
        if (sipka.classList.contains('neaktivni')) {
          $(this).closest('table').find('.razeni.aktivni').each(function () { 
            this.classList.remove('aktivni'); 
            this.classList.add('neaktivni'); 
          })
          sipka.classList.remove('neaktivni');
          sipka.classList.add('aktivni');
        } else if (sipka.classList.contains('aktivni')) {
          if (sipka.classList.contains('fa-chevron-up')) {
            sipka.classList.remove('fa-chevron-up');
            sipka.classList.add('fa-chevron-down');
          } else {
            sipka.classList.remove('fa-chevron-down');
            sipka.classList.add('fa-chevron-up');
          }
        }
      });
    });
  })
})(jQuery)
