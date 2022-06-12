(function ($) {
  document.addEventListener('DOMContentLoaded', function () {

    const onlinePrezence = document.getElementById('online-prezence')

    if (!onlinePrezence) {
      return // Nevedeš žádné aktivity 😞
    }

    onlinePrezence.addEventListener(
      'zmenaMetadatPrezence',
      function (/** @param {{detail: {razitkoPosledniZmeny: string}}} */ event) {
        onlinePrezence.dataset.razitkoPosledniZmeny = event.detail.razitkoPosledniZmeny
      },
    )

    /**
     * @return {string}
     */
    function dejUrlRazitkaPosledniZmeny() {
      const url = new URL(onlinePrezence.dataset.urlRazitkaPosledniZmeny)
      // přidáme proměnlivé query, abychom obešli cache a dostali vždy aktuální soubor (nebo 404)
      url.searchParams.set('version', new Date().getTime().toString())
      return url.href
    }

    /**
     * @return {string}
     */
    function dejZnameRazitkoPosledniZmeny() {
      return onlinePrezence.dataset.razitkoPosledniZmeny
    }

    let jePozastavenaKontrolaZmen = false
    setInterval(function () {
      if (onlinePrezence.dataset.probihajiZmeny) {
        return // něco se mění, necháme to na příští interval
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
          if (json.razitko_posledni_zmeny !== dejZnameRazitkoPosledniZmeny()) {
            nahratZmenyPrihlaseni()
          }
        }
      })

      request.addEventListener('loadend', function () {
        jePozastavenaKontrolaZmen = false
      })

      request.open('GET', dejUrlRazitkaPosledniZmeny()) // asynchronous
      if (jePozastavenaKontrolaZmen) {
        return
      }
      request.send()
    }, 3000) // každé tři sekundy kontrolujeme, zda razitko posledni zmeny je patne (zda soubor s nim existuje) - kdyz soubor zmizi, tak se prilaseni na jedne z aktivit zmenilo a my chceme sathnout zmeny

    const urlAkcePosledniZmeny = onlinePrezence.dataset.urlAkcePosledniZmeny
    const posledniLogyUcastnikuAjaxKlic = onlinePrezence.dataset.posledniLogyUcastnikuAjaxKlic

    const $aktivity = $(onlinePrezence).find('.aktivita')

    function nahratZmenyPrihlaseni() {
      /* kvůli testování (viz admin/scripts/modules/moje-aktivity/moje-aktivity.php $testujeme) nelze použít \Uzivatel::organizovaneAktivity protože používáme i aktivity, které tester nemusí organizovat, proto jejich seznam posíláme z frontendu */
      const aktivityUcastniciPosledniZnameLogy = {}

      $aktivity.each(function (indexAktivity, aktivita) {
        const aktivitaUcastniciPosledniZnameLogy = []

        const ucastnici = aktivita.querySelectorAll('.ucastnik')
        if (ucastnici.length > 0) {
          ucastnici.forEach(function (ucastnik) {
            const ucastnikPosledniZnamyLog = {
              idUzivatele: ucastnik.dataset.id,
              idPoslednihoLogu: ucastnik.dataset.idPoslednihoLogu,
            }
            aktivitaUcastniciPosledniZnameLogy.push(ucastnikPosledniZnamyLog)
          })
        } else {
          // prázdné pole by se neposlalo a my chceme posílat ID aktivity i když nemá zatím účastníka
          aktivitaUcastniciPosledniZnameLogy.push({
            idUzivatele: 0,
            idPoslednihoLogu: 0,
          })
        }

        aktivityUcastniciPosledniZnameLogy[aktivita.dataset.id] = aktivitaUcastniciPosledniZnameLogy
      })

      $.post(urlAkcePosledniZmeny, {
        /**
         * @see \Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax::odbavAjax
         * @see \Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax::ajaxDejPosledniZmeny
         */
        /*
         Musíme použít nejstarší ID logu.
         Nemůžeme použít nejnovější ID logu, protože při zmeně jednoho přihlášení přes online prezenci
         o chvililinku později, než někdo jiný změnil jiné přihlášení, tak bychom použili ID posledního logu z naší,
         poslední změny a tím bychom přeskočili nedávnou změnu od jinud.
         */
        [posledniLogyUcastnikuAjaxKlic]: aktivityUcastniciPosledniZnameLogy,
      }).done(/** @param {{razitko_posledni_zmeny: string, zmeny: []}} data */function (data) {
        if (data.zmeny) {
          const zmeny = Zmena.vytvorZmenyZOdpovedi(data.zmeny)
          zmeny.forEach(function (zmena) {
            zapisZmenuPrihlaseni(zmena)
          })
        }
        const zmenaMetadatPrezence = new CustomEvent('zmenaMetadatPrezence', {
          detail: {
            razitkoPosledniZmeny: data.razitko_posledni_zmeny,
          },
        })
        onlinePrezence.dispatchEvent(zmenaMetadatPrezence)
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
        zmenPrihlaseni(ucastnikNode, zmena)
      }
    }

    /**
     * @param {Zmena} zmena
     */
    function pridejNovehoUcastnika(zmena) {
      if (!chceHratPodleStavu(zmena.stavPrihlaseni)) {
        return // nemá smysl přidávat odstranění
      }
      const htmlUcastnika = zmena.htmlUcastnika.trim()
      if (htmlUcastnika === '') {
        throw new Error(`HTML účastníka je prázdné pro uživatele s ID ${zmena.idUzivatele} a aktivitu s ID ${zmena.idAktivity}`)
      }

      const template = document.createElement('template')
      template.innerHTML = htmlUcastnika

      const nodeNovehoUcastnika = dejNodeUcastniku(dejNodeAktivity(zmena.idAktivity)).appendChild(template.content.firstChild)

      vypustEventONovemUcastnikovi(zmena)

      upozorniNaZmenu(nodeNovehoUcastnika, 'lime')
    }

    /**
     * Bude zpracováno v event listeneru v online-prezence.js přes hlidejNovehoUcastnika()
     * @param {Zmena} zmena
     */
    function vypustEventONovemUcastnikovi(zmena) {
      const novyUcastnik = new CustomEvent(
        'novyUcastnik',
        {
          detail: {
            idAktivity: zmena.idAktivity,
            idUzivatele: zmena.idUzivatele,
          },
        },
      )
      document.getElementById(`aktivita-${zmena.idAktivity}`).dispatchEvent(novyUcastnik)
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
    function zmenPrihlaseni(ucastnikNode, zmena) {
      if (!jeZmenaNova(ucastnikNode, zmena)) {
        return
      }
      vypustEventSNovymiMetadatyPrezence(ucastnikNode, zmena)
    }

    /**
     * @param {HTMLElement} ucastnikNode
     * @param {Zmena} zmena
     * @return {boolean}
     */
    function jeZmenaNova(ucastnikNode, zmena) {
      return !ucastnikNode.dataset.idPoslednihoLogu
        || Number(ucastnikNode.dataset.idPoslednihoLogu) < zmena.idPoslednihoLogu
    }

    /**
     * Bude zpracováno v event listeneru v online-prezence.js přes zapisMetadataPrezence()
     * @param {HTMLElement} ucastnikNode
     * @param {Zmena} zmena
     */
    function vypustEventSNovymiMetadatyPrezence(ucastnikNode, zmena) {
      const zmenaMetadatUcastnika = new CustomEvent(
        'zmenaMetadatUcastnika',
        {
          detail: {
            casPosledniZmenyPrihlaseni: zmena.casZmeny,
            stavPrihlaseni: zmena.stavPrihlaseni,
            idPoslednihoLogu: zmena.idPoslednihoLogu,
            callback: function () {
              const dorazil = dorazilPodleStavu(zmena.stavPrihlaseni)
              zmenZaskrtnutiZdaDorazil(ucastnikNode, dorazil)
            },
          },
        },
      )
      ucastnikNode.dispatchEvent(zmenaMetadatUcastnika)
    }

    /**
     * @param {string} stavPrihlaseni
     * @return {boolean}
     */
    function dorazilPodleStavu(stavPrihlaseni) {
      return ['ucastnik_dorazil', 'nahradnik_dorazil'].includes(stavPrihlaseni)
    }

    /**
     * @param {string} stavPrihlaseni
     * @return {boolean}
     */
    function chceHratPodleStavu(stavPrihlaseni) {
      /** viz \Gamecon\Aktivita\ZmenaStavuPrihlaseni::stavPrihlaseniProJs */
      switch (stavPrihlaseni) {
        case 'ucastnik_se_odhlasil' :
        case 'ucastnik_nedorazil' :
        case 'sledujici_se_odhlasil' :
        case 'nahradnik_nedorazil' :
          return false
        case 'sledujici_se_prihlasil' :
        case 'ucastnik_se_prihlasil' :
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
     * @param {HTMLElement} node
     */
    function zablokujInputy(node) {
      zmenZamekInputum(node, true)
    }

    /**
     * @private
     * @param {HTMLElement} node
     * @param {boolean} disabled
     */
    function zmenZamekInputum(node, disabled) {
      const inputs = node.getElementsByTagName('input')
      Array.from(inputs).forEach(function (input) {
        input.disabled = disabled
      })
    }

    /**
     * @param {HTMLElement} node
     */
    function odblokujInputy(node) {
      zmenZamekInputum(node, false)
    }

    /**
     * @param {HTMLElement} node
     * @param {string} color
     */
    function upozorniNaZmenu(node, color) {
      zablokujInputy(node)

      blikni(node, color)

      let pocetDalsichBliknuti = 4
      const intervalBarvyId = setInterval(function () {
        if (pocetDalsichBliknuti <= 0) {
          clearInterval(intervalBarvyId)
          odblokujInputy(node)
          return
        }
        blikni(node, color)
        pocetDalsichBliknuti--
      }, 600)
    }

    /**
     * @param {HTMLElement} node
     * @param {string} color
     */
    function blikni(node, color) {
      vyradZmenuBarvyPriHover(node)
      zmenBarvuNa(node, color, 0.2)

      const intervalTransparentId = setTimeout(function () {
        zmenBarvuNa(
          node,
          /* kvůli zachování střídaní barvy u řádků tabulky, viz
          .main table tbody tr:nth-child(2n) {
            background-color: #f0f0f0;
          } */
          node.parentElement.style.backgroundColor,
          0.1,
        )
        vratZmenuBarvyPriHover(node)
        clearTimeout(intervalTransparentId)
      }, 300)
    }

    /**
     * @param {HTMLElement} node
     */
    function vyradZmenuBarvyPriHover(node) {
      node.classList.add('no-hover')
    }

    /**
     * @param {HTMLElement} node
     */
    function vratZmenuBarvyPriHover(node) {
      node.classList.remove('no-hover')
    }

    /**
     * @param {HTMLElement} node
     * @param {string} color
     * @param {number} seconds
     */
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
   * @param {{id_aktivity: number, id_uzivatele: number, id_logu: number, cas_zmeny: string, stav_prihlaseni: string, html_ucastnika: string}[]} dataZmen
   * @return Zmena[]
   */
  static vytvorZmenyZOdpovedi(dataZmen) {
    const zmeny = []
    dataZmen.forEach((dataZmeny) => {
      zmeny.push(
        new Zmena(
          dataZmeny.id_aktivity,
          dataZmeny.id_uzivatele,
          dataZmeny.id_logu,
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
  /** @private @var {number} */
  _idPoslednihoLogu
  /** @private @var {string} */
  _casZmeny
  /** @private @var {string} */
  _stavPrihlaseni
  /** @private @var {string} */
  _htmlUcastnika

  /**
   * @param {number} idAktivity
   * @param {number} idUzivatele
   * @param {number} idPoslednihoLogu
   * @param {string} casZmeny
   * @param {string} stavPrihlaseni
   * @param {string} htmlUcastnika
   */
  constructor(idAktivity, idUzivatele, idPoslednihoLogu, casZmeny, stavPrihlaseni, htmlUcastnika) {
    this._idAktivity = idAktivity
    this._idUzivatele = idUzivatele
    this._idPoslednihoLogu = idPoslednihoLogu
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

  get idPoslednihoLogu() {
    return this._idPoslednihoLogu
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
