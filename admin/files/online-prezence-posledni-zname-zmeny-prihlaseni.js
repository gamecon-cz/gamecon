(function ($) {
  document.addEventListener('DOMContentLoaded', function () {

    const onlinePrezence = document.getElementById('online-prezence')

    /**
     * @return {string}
     */
    function dejUrlRazitkaPosledniZmeny() {
      const url = new URL(onlinePrezence.dataset.urlRazitkaPosledniZmeny)
      // přidáme proměnlivé query, abychom obešli cache a dostali vždy aktuální soubor (nebo 404)
      url.searchParams.set('version', Date.now().toString())
      return url.href
    }

    /**
     * @return {string}
     */
    function dejRazitkoPosledniZmeny() {
      return onlinePrezence.dataset.razitkoPosledniZmeny
    }

    let jePozastavenaKontrolaZmen = false
    setInterval(function () {
      if (jePozastavenaKontrolaZmen) {
        return
      }

      const request = new XMLHttpRequest()

      request.addEventListener('loadstart', function () {
        jePozastavenaKontrolaZmen = true
      })

      request.addEventListener('load', function () {
        if (this.status === 404) {
          nahratZmenyPrihlaseni()
        } else if (this.status === 200 && this.responseText) {
          const json = JSON.parse(this.responseText.trim())
          if (json.razitko_posledni_zmeny !== dejRazitkoPosledniZmeny()) {
            nahratZmenyPrihlaseni()
          }
        }
        jePozastavenaKontrolaZmen = false
      })

      request.open('GET', dejUrlRazitkaPosledniZmeny()) // asynchronous
      request.send()
    }, 2000) // každé dvě sekundy kontrolujeme, zda razitko posledni zmeny je patne (zda soubor s nim existuje) - kdyz soubor zmizi, tak se prilaseni na jedne z aktivit zmenilo a my chceme sathnout zmeny

    const urlAkcePosledniZmeny = onlinePrezence.dataset.urlAkcePosledniZmeny

    const $aktivity = $(onlinePrezence).find('.aktivita')

    function nahratZmenyPrihlaseni() {
      const postData = []

      $aktivity.each(function (indexAktivity, aktivita) {
        const $ucastnici = $(aktivita).find('.ucastnik')

        const posledniZnamePrihlaseniUcastniku = []
        $ucastnici.each(function (indexUcastnka, ucastnik) {
          posledniZnamePrihlaseniUcastniku.push({
            'id_uzivatele': ucastnik.dataset.id,
            'cas_posledni_zmeny_prihlaseni': ucastnik.dataset.casPosledniZmenyPrihlaseni,
            'stav_prihlaseni': ucastnik.dataset.stavPrihlaseni,
          })
        })

        postData.push({
          'id_aktivity': aktivita.dataset.id,
          'ucastnici': posledniZnamePrihlaseniUcastniku,
        })
      })

      $.post(urlAkcePosledniZmeny, {
        /**
         * @see \Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax::odbavAjax
         * @see \Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax::ajaxDejPosledniZmeny
         */
        'zname_zmeny_prihlaseni': postData,
      }).done(/** @param {{razitko_posledni_zmeny: string, zmeny: []}} data */function (data) {
        /* napriklad
        {
          zmeny: [
            {
              id_aktivity: 4057,
              id_uzivatele: 4495,
              cas_zmeny: "2022-04-27T16:57:38+02:00",
              stav_prihlaseni: "prihlaseni_nahradnik"
            }
          ],
          razitko_posledni_zmeny: "269b794fa2c0bf1e81d0c60709ddb5d6"
        }
        */
        if (data.zmeny) {
          const zmeny = Zmena.vytvorZmenyZOdpovedi(data.zmeny)
          zmeny.forEach(function (zmena) {
            zapisZmenuPrihlaseni(zmena)
          })
        }
        onlinePrezence.dataset.razitkoPosledniZmeny = data.razitko_posledni_zmeny
      })
    }

    /**
     * @param {Zmena} zmena
     */
    function zapisZmenuPrihlaseni(zmena) {
      const ucastnikNode = document.getElementById(`ucastnik-${zmena.idUzivatele}-na-aktivite-${zmena.idAktivity}`)
      if (!ucastnikNode) {
        pridejNovehoUcastnika(zmena)
      } else {
        zmenStavPrihlaseni(ucastnikNode, zmena)
      }
    }

    /**
     * @param {Zmena} zmena
     */
    function pridejNovehoUcastnika(zmena) {
      if (!dorazilPodleStavu(zmena.stavPrihlaseni)) {
        return // nemá smysl přidávat odstranění
      }
      const htmlUcastnika = zmena.htmlUcastnika.trim()
      if (htmlUcastnika === '') {
        throw new Error(`HTML účastníka je prázdné pro uživatele s ID ${zmena.idUzivatele} a aktivitu s ID ${zmena.idAktivity}`)
      }

      const template = document.createElement('template')
      template.innerHTML = htmlUcastnika

      const nodeNovehoUcastnika = dejNodeUcastniku(dejNodeAktivity(zmena.idAktivity)).appendChild(template.content.firstChild)

      upozorniNaZmenu(nodeNovehoUcastnika, 'lime')
    }

    /**
     * @param {HTMLElement} aktivita
     * @return {HTMLElement}
     */
    function dejNodeUcastniku(aktivita) {
      const ucastnici = aktivita.getElementsByClassName('ucastnici-seznam')[0]
      if (!ucastnici) {
        throw new Error(`Element s účastníky nebyl nalezen v aktivitě ${aktivita.id}`)
      }
      if (ucastnici.tagName.toUpperCase() !== 'TBODY') {
        throw new Error(`Element s účastníky z aktivity ${aktivita.id} měl být tbody, je ${ucastnici.tagName}`)
      }
      return ucastnici
    }

    /**
     * @param {number} idAktivity
     * @return {HTMLElement}
     */
    function dejNodeAktivity(idAktivity) {
      const aktivita = document.getElementById(`aktivita-${idAktivity}`)
      if (!aktivita) {
        throw new Error(`Aktivita s ID ${idAktivity} nebyla na stránce nalezena`)
      }
      return aktivita
    }

    /**
     * @param {HTMLElement} ucastnikNode
     * @param {Zmena} zmena
     */
    function zmenStavPrihlaseni(ucastnikNode, zmena) {
      vypustEventSNovymiMetadatyPrezence(ucastnikNode, zmena)
      const dorazil = dorazilPodleStavu(zmena.stavPrihlaseni)
      zmenZaskrtnutiZdaDorazil(ucastnikNode, dorazil)
    }

    /**
     * Bude zpracováno v event listeneru v online-prezence.js přes zapisMetadataPrezence()
     * @param {HTMLElement} ucastnikNode
     * @param {Zmena} zmena
     */
    function vypustEventSNovymiMetadatyPrezence(ucastnikNode, zmena) {
      const zmenaMetadatPrezence = new CustomEvent(
        'zmenaMetadatPrezence',
        {
          detail: {
            casPosledniZmenyPrihlaseni: zmena.casZmeny,
            stavPrihlaseni: zmena.stavPrihlaseni,
          },
        },
      )
      ucastnikNode.dispatchEvent(zmenaMetadatPrezence)
    }

    /**
     * @param {string} stavPrihlaseni
     * @return {boolean}
     */
    function dorazilPodleStavu(stavPrihlaseni) {
      /** viz \Gamecon\Aktivita\ZmenaStavuPrihlaseni::stavPrihlaseniProJs */
      switch (stavPrihlaseni) {
        // řádek s účastníkem už máme a teď jsme dostali informaci, že se pouze přihlásil, není proto přítomen (nemá být zaškrtnutý)
        case 'ucastnik_se_prihlasil' :
        // řádek s účastníkem už máme a teď jsme dostali informaci, že se odhlásil - nechceme řádek smazat, co kdyby to byla chyba a uživatel tam přeci jen fyzicky byl, tak ho jen označíme jako nepřítomen (nezaškrtnutý)
        case 'ucastnik_se_odhlasil' :
        // poslední jeho stav je, že je přihlášen jako sledující, tedy není přítomen
        case 'sledujici_se_prihlasil' :
        case 'sledujici_se_odhlasil' :
        case 'nahradnik_nedorazil' :
          return false
        case 'ucastnik_dorazil' :
        case 'nahradnik_dorazil' :
          return true
        default :
          throw new Error(`Neznámý stav přihlášení ${stavPrihlaseni}`)
      }
    }

    /**
     * @param {HTMLElement} ucastnikNode
     * @param {boolean} dorazil
     * @return {boolean}
     */
    function zmenZaskrtnutiZdaDorazil(ucastnikNode, dorazil) {
      /** @var HTMLElement dorazilInput */
      const dorazilInput = ucastnikNode.getElementsByClassName('dorazil').item(0)
      if (dorazil === dorazilInput.checked) {
        return false // nebylo co menit
      }
      naChviliZablokuj(dorazilInput)
      dorazilInput.checked = dorazil
      upozorniNaZmenu(
        ucastnikNode,
        dorazil
          ? 'lime'
          : 'orange',
      )
      return true
    }

    /**
     * @param {HTMLElement} inputNode
     */
    function naChviliZablokuj(inputNode) {
      inputNode.disabled = true
      setTimeout(function () {
        inputNode.disabled = false
      }, 1200)
    }

    /**
     * @param {HTMLElement} node
     * @param {string} color
     */
    function upozorniNaZmenu(node, color) {
      blikni(node, color)

      let pocetDalsichBliknuti = 2
      const intervalBarvyId = setInterval(function () {
        if (pocetDalsichBliknuti <= 0) {
          clearInterval(intervalBarvyId)
          return
        }
        blikni(node, color)
        pocetDalsichBliknuti--
      }, 500)
    }

    function blikni(node, color) {
      zmenBarvuNa(node, color, 0.1)

      const intervalTransparentId = setTimeout(function () {
        zmenBarvuNa(node, 'transparent', 0.05)
        clearTimeout(intervalTransparentId)
      }, 100)
    }

    function zmenBarvuNa(node, color, seconds) {
      node.style.backgroundColor = color
      const transition = `background ${seconds}s linear`
      node.style.transition = transition
      node.style.webkitTransition = transition
    }
  })

})(jQuery)

class Zmena {
  /**
   * @param {{id_aktivity: number, id_uzivatele: number, cas_zmeny: string, stav_prihlaseni: string, html_ucastnika: string}[]} dataZmen
   * @return Zmena[]
   */
  static vytvorZmenyZOdpovedi(dataZmen) {
    const zmeny = []
    dataZmen.forEach((dataZmeny) => {
      zmeny.push(
        new Zmena(
          dataZmeny.id_aktivity,
          dataZmeny.id_uzivatele,
          dataZmeny.cas_zmeny,
          dataZmeny.stav_prihlaseni,
          dataZmeny.html_ucastnika,
        ),
      )
    })
    return zmeny
  }

  /** @private @var {number} */
  _idAktivity
  /** @private @var {number} */
  _idUzivatele
  /** @private @var {string} */
  _casZmeny
  /** @private @var {string} */
  _stavPrihlaseni
  /** @private @var {string} */
  _htmlUcastnika

  /**
   * @param {number} idAktivity
   * @param {number} idUzivatele
   * @param {string} casZmeny
   * @param {string} stavPrihlaseni
   * @param {string} htmlUcastnika
   */
  constructor(idAktivity, idUzivatele, casZmeny, stavPrihlaseni, htmlUcastnika) {
    this._idAktivity = idAktivity
    this._idUzivatele = idUzivatele
    this._casZmeny = casZmeny
    this._stavPrihlaseni = stavPrihlaseni
    this._htmlUcastnika = htmlUcastnika
  }

  get idAktivity() {
    return this._idAktivity
  }

  get idUzivatele() {
    return this._idUzivatele
  }

  get casZmeny() {
    return this._casZmeny
  }

  get stavPrihlaseni() {
    return this._stavPrihlaseni
  }

  get htmlUcastnika() {
    return this._htmlUcastnika
  }
}
