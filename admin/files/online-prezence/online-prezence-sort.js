/* credits https://www.w3schools.com/howto/howto_js_sort_table.asp (slight changes) */
function sortTable(col, table) {
  var rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
  // table = document.getElementById("myTable");
  switching = true;
  //Set the sorting direction to ascending:
  dir = "asc"; 
  /*Make a loop that will continue until
  no switching has been done:*/
  while (switching) {
    //start by saying: no switching is done:
    switching = false;
    rows = table.rows;
    /*Loop through all table rows (except the
    first, which contains table headers):*/
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
          //if so, mark as a switch and break the loop:
          shouldSwitch= true;
          break;
        }
      } else if (dir == "desc") {
        if (x < y) {
          //if so, mark as a switch and break the loop:
          shouldSwitch = true;
          break;
        }
      }
    }
    if (shouldSwitch) {
      /*If a switch has been marked, make the switch
      and mark that a switch has been done:*/
      rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
      switching = true;
      //Each time a switch is done, increase this count by 1:
      switchcount ++;      
    } else {
      /*If no switching has been done AND the direction is "asc",
      set the direction to "desc" and run the while loop again.*/
      if (switchcount == 0 && dir == "asc") {
        dir = "desc";
        switching = true;
      }
    }
  }
} 

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

// document.addEventListener('aktivitaVyrenderovana', function (event) {
//   const aktivitaNode = event.detail
//   if (aktivitaNode.dataset.editovatelnaOdTimestamp > 0) {
//     zablokovatAktivituProEditaciSOdpoctem(aktivitaNode)
//   }
// })

// const onlinePrezence = document.getElementById('online-prezence')

// if (onlinePrezence) {

//   const akceAktivity = new AkceAktivity()

//   document.getElementById('online-prezence').addEventListener('uzavritAktivitu', function (event) {
//     const idAktivity = event.detail
//     akceAktivity.uzavritAktivitu(idAktivity)
//   })

//   document.getElementById('online-prezence').addEventListener('zmenitPritomnostUcastnika', function (event) {
//     const {
//       idUcastnika: idUcastnika,
//       idAktivity: idAktivity,
//       checkboxNode: checkboxNode,
//       triggeringNode: triggeringNode,
//     } = event.detail
//     akceAktivity.zmenitPritomnostUcastnika(
//       idUcastnika,
//       idAktivity,
//       checkboxNode,
//       triggeringNode,
//     )
  // })
// }
