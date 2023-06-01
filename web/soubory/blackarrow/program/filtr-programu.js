document.addEventListener('programNacteny', function () {
  function programTypCheckbox(element) {
    return element.querySelector('input[type=checkbox].program_typ_checkbox')
  }

  /**
   * @param {CustomEvent} customEvent
   */
  function filtrujProgram(customEvent) {
    var zobrazit = []
    var skryt = []
    Array.from(document.querySelectorAll('.program_legenda_inner .program_legenda_typ')).forEach(function (element) {
      var checkbox = programTypCheckbox(element)
      if (!checkbox || !checkbox.checked) {
        skryt.push(element.dataset.typClass)
        element.getElementsByClassName('before').item(0).innerText = '▢'
      } else {
        zobrazit.push(element.dataset.typClass)
        element.getElementsByClassName('before').item(0).innerText = '✔'
      }
    })
    if (zobrazit.length === 0) { // nic není zaškrtnuté = nic nefiltrovat = zobrazit všechno
      Array.from(document.querySelectorAll(`.program .aktivita`)).forEach(
        (elementNaZobrazeni) => elementNaZobrazeni.style.display = ''
      )
      Array.from(document.querySelectorAll(`.program .placeholder-pro-roztazeni-radku`)).forEach(
        (placeholderNaSkryti) => placeholderNaSkryti.style.display = 'none'
      )
      if (customEvent.detail === undefined || !customEvent.detail.initial) {
        document.cookie = `program_legenda_typ=;expires=Thu, 01 Jan 1970 00:00:01 GMT;`;
      }
    } else {
      if (customEvent.detail === undefined || !customEvent.detail.initial) {
        document.cookie = `program_legenda_typ=${zobrazit.join(',')}`;
      }

      // TODO CustomEvent('tagyVybrany') web/soubory/blackarrow/program/vyber-tagu.js

      var zobrazitElementy = []
      zobrazit.forEach(function (zobrazitClass) {
        zobrazitElementy.push(...document.querySelectorAll(`.program .${zobrazitClass}`))
      })
      zobrazitElementy.filter(function (zobrazitElement, index, elementy) {
        return elementy.indexOf(zobrazitElement) === index // pokud je to další výskyt stejné hodnoty, tak současný index bude větší než indexOf, který vrací první a tím duplicitní hodnotu zahodíme
      })

      var skrytElementy = []
      skryt.forEach(function (skrytClass) {
        skrytElementy.push(...document.querySelectorAll(`.program .${skrytClass}`))
      })
      skrytElementy.filter(function (skrytElement, index, elementy) {
        return elementy.indexOf(skrytElement) === index // pokud je to další výskyt stejné hodnoty, tak současný index bude větší než indexOf, který vrací první a tím duplicitní hodnotu zahodíme
      })

      skrytElementy.filter(function (skrytElement) {
        return zobrazitElementy.indexOf(skrytElement) !== -1 // nebudeme skrývat elementy které chceme zobrazit
      })

      skrytElementy.forEach(
        (elementNaSkryti) => elementNaSkryti.style.display = 'none'
      )
      /**
       * (Tohle pořadí už zřejmě není potřeba díky odfiltrování skrytElementy když už jsou v zobrazitElementy).
       * Nejdříve skryjeme, potom zobrazíme, protože se může stát, že aktivitu například oganizuji, ale zároveň má plno a přitom chci zobrazit ty co ogranizuji.
       * Takže nejdřív aktivitu skryjeme, protože je plná a v zápětí ji zobrazíme, protože ji organizuji.
       */
      zobrazitElementy.forEach(
        (elementNaZobrazeni) => elementNaZobrazeni.style.display = ''
      )

      Array.from(document.querySelectorAll(`.program .linie`)).forEach(function (linieElement) {
        if (linieElement.querySelector('.aktivita:not([style*="display:none"]):not([style*="display: none"])')) {
          return // nějaká aktivita se v linii zobrazuje, nechceme placeholder
        }
        linieElement.querySelector('.placeholder-pro-roztazeni-radku').style.display = ''
      })
    }
  }

  Array.from(document.querySelectorAll('.program_legenda .program_legenda_typ')).forEach(function (element) {
    var checkboxTemplate = document.createElement('template')
    checkboxTemplate.innerHTML = '<input type="checkbox" class="program_typ_checkbox" style="display: none">'
    element.appendChild(checkboxTemplate.content)
    var checkbox = programTypCheckbox(element)
    checkbox.addEventListener('change', filtrujProgram)
    checkbox.dispatchEvent(new CustomEvent('change', {detail: {initial: true}}))

    element.addEventListener('click', function () {
      var checkbox = programTypCheckbox(this)
      checkbox.checked = !checkbox.checked
      checkbox.dispatchEvent(new Event('change'))
    })
  })

  var driveVybraneCookie = document.cookie.split('; ').find((row) => row.startsWith('program_legenda_typ='))
  if (driveVybraneCookie) {
    var driveVybraneString = driveVybraneCookie.split('=').at(1)
    var driveVybrane = driveVybraneString.split(',')
    driveVybrane.forEach(function (driveVybranyTyp) {
      driveVybranyTyp = driveVybranyTyp.trim()
      if (driveVybranyTyp !== '') {
        document.querySelector(`.program_legenda .program_legenda_typ.${driveVybranyTyp}`).dispatchEvent(new Event('click'))
      }
    })
  }
})
