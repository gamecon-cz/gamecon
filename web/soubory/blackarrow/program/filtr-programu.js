document.addEventListener('programNacteny', function () {
  function filtrujProgram() {
    var zobrazit = []
    var skryt = []
    Array.from(document.querySelectorAll('.program_legenda_inner .program_legenda_typ')).forEach(function (element) {
      var checkbox = element.querySelector('input[type=checkbox].program_typ_checkbox')
      if (!checkbox || !checkbox.checked) {
        skryt.push(element.dataset.typClass)
        element.getElementsByClassName('before').item(0).innerText = ''
      } else {
        zobrazit.push(element.dataset.typClass)
        element.getElementsByClassName('before').item(0).innerText = '✔'
      }
    })
    if (zobrazit.length === 0) { // nic není zaškrtnuté = nic nefiltrovat = zobrazit všechno
      Array.from(document.querySelectorAll(`.program .aktivita`)).forEach(
        (elementNaZobrazeni) => elementNaZobrazeni.style.display = ''
      )
      document.cookie = `program_legenda_typ=`;
    } else {
      zobrazit.forEach(function (zobrazitClass) {
        Array.from(document.querySelectorAll(`.program .${zobrazitClass}`)).forEach(
          (elementNaZobrazeni) => elementNaZobrazeni.style.display = ''
        )
      })
      document.cookie = `program_legenda_typ=${zobrazit.join(',')}`;
      skryt.forEach(function (skrytClass) {
        Array.from(document.querySelectorAll(`.program .${skrytClass}`)).forEach(
          (elementNaSkryti) => elementNaSkryti.style.display = 'none'
        )
      })
    }
  }

  Array.from(document.querySelectorAll('.program_legenda .program_legenda_typ')).forEach(function (element) {
    element.addEventListener('click', function () {
      var checkbox = this.querySelector('input[type=checkbox].program_typ_checkbox')
      if (!checkbox) {
        var checkboxTemplate = document.createElement('template')
        checkboxTemplate.innerHTML = '<input type="checkbox" class="program_typ_checkbox" style="display: none">'
        this.appendChild(checkboxTemplate.content)
        checkbox = this.querySelector('input[type=checkbox].program_typ_checkbox')
        checkbox.addEventListener('change', filtrujProgram)
        checkbox.checked = true
      } else {
        checkbox.checked = !checkbox.checked
      }
      checkbox.dispatchEvent(new Event('change'))
    })
  })
  var driveVybraneCookie = document.cookie.split('; ').find((row) => row.startsWith('program_legenda_typ='))
  if (driveVybraneCookie) {
    var driveVybraneString = driveVybraneCookie.split('=').at(1)
    var driveVybrane = driveVybraneString.split(',')
    driveVybrane.forEach(function (driveVybranyTyp) {
      document.querySelector(`.program_legenda .program_legenda_typ.${driveVybranyTyp}`).dispatchEvent(new Event('click'))
    })
  }
})
