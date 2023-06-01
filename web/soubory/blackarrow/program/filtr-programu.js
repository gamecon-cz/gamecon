document.addEventListener('programNacteny', function () {

  /** @see web/soubory/blackarrow/program/vyber-stitku.js */
  document.addEventListener('stitkyVybrany', filtrujProgram)

  function programTypCheckbox(element) {
    return element.querySelector('input[type=checkbox].program_typ_checkbox')
  }

  /**
   * @return {{skryt: string[], zobrazit: string[]}}
   */
  function dejTridyTypuPodleZobrazeni() {
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

    return {skryt: skryt, zobrazit: zobrazit}
  }

  /**
   * @return {{skryt: string[], zobrazit: string[]}}
   */
  function dejStitkyPodleZobrazeni() {
    var zobrazit = []
    var skryt = []

    Array.from(document.getElementById('vyberStitkuProgram').querySelectorAll('option')).forEach(
      function (optionElement) {
        if (!optionElement.selected) {
          skryt.push(optionElement.value)
        } else {
          zobrazit.push(optionElement.value)
        }
      }
    )

    return {skryt: skryt, zobrazit: zobrazit}
  }

  /**
   * @param {zobrazit: string[]} zobrazit
   * @param {Event|CustomEvent} event
   */
  function zapamatujVyberTypu(zobrazit, event) {

    if (zobrazit.length === 0) { // nic není zaškrtnuté = nic nefiltrovat = zobrazit všechno
      if (event.detail === undefined || !event.detail.initial) {
        document.cookie = `program_legenda_typy=;expires=Thu, 01 Jan 1970 00:00:01 GMT;`;
      }
    } else {
      if (event.detail === undefined || !event.detail.initial) {
        document.cookie = `program_legenda_typy=${zobrazit.join(',')}`;
      }
    }
  }

  /**
   * @param {zobrazit: string[]} zobrazitStitky
   * @param {Event|CustomEvent} event
   */
  function zapamatujVyberStitku(zobrazitStitky, event) {

    if (zobrazitStitky.length === 0) { // nic není zaškrtnuté = nic nefiltrovat = zobrazit všechno
      if (event.detail === undefined || !event.detail.initial) {
        document.cookie = `program_legenda_stitky=;expires=Thu, 01 Jan 1970 00:00:01 GMT;`;
      }
    } else {
      if (event.detail === undefined || !event.detail.initial) {
        document.cookie = `program_legenda_stitky=${zobrazitStitky.join(',')}`;
      }
    }
  }

  /**
   * @param {Event|CustomEvent} event
   */
  function filtrujProgram(event) {

    var tridyTypuPodleZobrazeni = dejTridyTypuPodleZobrazeni();
    var stitkyPodleZobrazeni = dejStitkyPodleZobrazeni();

    if (event.type === 'zmenaTypu') {
      zapamatujVyberTypu(tridyTypuPodleZobrazeni.zobrazit, event)
    } else if (event.type === 'stitkyVybrany') {
      zapamatujVyberStitku(stitkyPodleZobrazeni.zobrazit, event)
    }

    var zobrazitTridyTypu = tridyTypuPodleZobrazeni.zobrazit
    var skrytTridyTypu = tridyTypuPodleZobrazeni.skryt

    var zobrazitStitky = stitkyPodleZobrazeni.zobrazit
    var skrytStitky = stitkyPodleZobrazeni.skryt

    if (zobrazitTridyTypu.length === 0 && zobrazitStitky.length === 0) { // nic není zaškrtnuté = nic nefiltrovat = zobrazit všechno
      Array.from(document.querySelectorAll(`.program .aktivita`)).forEach(
        (elementNaZobrazeni) => elementNaZobrazeni.style.display = ''
      )
      Array.from(document.querySelectorAll(`.program .placeholder-pro-roztazeni-radku`)).forEach(
        (placeholderNaSkryti) => placeholderNaSkryti.style.display = 'none'
      )
    } else {
      var zobrazitElementy = []
      zobrazitTridyTypu.forEach(function (zobrazitClass) {
        zobrazitElementy.push(...document.querySelectorAll(`.program .aktivita.${zobrazitClass}`))
      })
      zobrazitStitky.forEach(function (zobrazitStitek) {
        zobrazitElementy.push(...document.querySelectorAll(`.program .aktivita[data-stitky|='${zobrazitStitek}']`))
      })
      zobrazitElementy.filter(function (zobrazitElement, index, elementy) {
        return elementy.indexOf(zobrazitElement) === index // pokud je to další výskyt stejné hodnoty, tak současný index bude větší než indexOf, který vrací první a tím duplicitní hodnotu zahodíme
      })

      var skrytElementy = []
      skrytTridyTypu.forEach(function (skrytClass) {
        skrytElementy.push(...document.querySelectorAll(`.program .aktivita.${skrytClass}`))
      })
      skrytStitky.forEach(function (skrytStitek) {
        skrytElementy.push(...document.querySelectorAll(`.program .aktivita[data-stitky|='${skrytStitek}']`))
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
    checkbox.addEventListener('zmenaTypu', filtrujProgram)
    checkbox.dispatchEvent(new CustomEvent('zmenaTypu', {detail: {initial: true}}))

    element.addEventListener('click', function () {
      var checkbox = programTypCheckbox(this)
      checkbox.checked = !checkbox.checked
      checkbox.dispatchEvent(new Event('zmenaTypu'))
    })
  })

  var driveVybraneTypyCookie = document.cookie.split('; ').find((row) => row.startsWith(`program_legenda_typy=`))
  if (driveVybraneTypyCookie) {
    var driveVybraneTypyString = driveVybraneTypyCookie.split('=').at(1)
    var driveVybraneTypy = driveVybraneTypyString.split(',')
    driveVybraneTypy.forEach(function (driveVybranyTyp) {
      driveVybranyTyp = driveVybranyTyp.trim()
      if (driveVybranyTyp !== '') {
        document.querySelector(`.program_legenda .program_legenda_typ.${driveVybranyTyp}`).dispatchEvent(new Event('click'))
      }
    })
  }
})

document.addEventListener('stitkyNahrane', function () {
  var necoVybrano = false
  var driveVybraneStitkyCookie = document.cookie.split('; ').find((row) => row.startsWith(`program_legenda_stitky=`))
  if (driveVybraneStitkyCookie) {
    var driveVybraneStitkyString = driveVybraneStitkyCookie.split('=').at(1)
    var driveVybraneStitky = driveVybraneStitkyString.split(',')
    var vyberStitkuProgram = document.getElementById(`vyberStitkuProgram`)
    driveVybraneStitky.forEach(function (driveVybranyStitek) {
      driveVybranyStitek = driveVybranyStitek.trim()
      if (driveVybranyStitek !== '') {
        var driveVybranyStitekElement = vyberStitkuProgram.querySelector(`option[value='${driveVybranyStitek}']`)
        driveVybranyStitekElement.selected = true
        necoVybrano = true
      }
    })
  }

  document.dispatchEvent(new Event('stitkyPripravene'))

  if (necoVybrano) {
    document.dispatchEvent(new Event('stitkyVybrany'))
  }
})
