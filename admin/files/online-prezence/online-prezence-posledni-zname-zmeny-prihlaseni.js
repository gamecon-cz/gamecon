import {ZmenaMetadatAktivity, ZmenaMetadatPrezence, ZmenaMetadatUcastnika} from "./online-prezence-eventy.js"
import {AkceAktivity} from "./online-prezence-akce-aktivity-class.js"

(function ($) {
  document.addEventListener('DOMContentLoaded', function () {

    const onlinePrezence = document.getElementById('online-prezence')
    const akceAktivity = new AkceAktivity()

    if (!onlinePrezence) {
      return // Nevedeš žádné aktivity 😞
    }

    // SEMAFOR
    onlinePrezence.addEventListener('probihajiZmeny', function (/** @param {{detail: probihaji: boolean}} */event) {
      // dataset by stejně převedl boolean na string, například true na 'true'
      onlinePrezence.dataset.probihajiZmeny = event.detail.probihaji.toString()
    })

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

    const urlAkcePosledniZmeny = onlinePrezence.dataset.urlAkcePosledniZmeny // bez domény - jQuery to nevadí
    const urlAkcePocatecniStav = onlinePrezence.dataset.urlAkcePocatecniStav
    const posledniLogyAktivitAjaxKlic = onlinePrezence.dataset.posledniLogyAktivitAjaxKlic
    const posledniLogyUcastnikuAjaxKlic = onlinePrezence.dataset.posledniLogyUcastnikuAjaxKlic

    const $aktivity = $(onlinePrezence).find('.aktivita')

    // POČÁTEČNÍ STAV - načtení dat pro všechny aktivity přes AJAX
    nahratPocatecniStav()

    function nahratPocatecniStav() {
      const idAktivit = []
      $aktivity.each(function (indexAktivity, aktivita) {
        idAktivit.push(aktivita.dataset.id)
      })

      $.post(urlAkcePocatecniStav, {
        id_aktivit: idAktivit,
      }).done(function (data) {
        if (data.aktivity) {
          data.aktivity.forEach(function (aktivitaData) {
            zpracujPocatecniStavAktivity(aktivitaData)
          })
        }

        if (data.razitko_posledni_zmeny) {
          const zmenaMetadatPrezence = ZmenaMetadatPrezence.vytvor(data.razitko_posledni_zmeny)
          onlinePrezence.dispatchEvent(zmenaMetadatPrezence)
        }

        zobrazElementyPodlePosledniVolby()

        // teprve po načtení počátečního stavu spustíme polling
        spustitPolling()
      }).fail(function () {
        $aktivity.each(function (indexAktivity, aktivita) {
          const nacitani = aktivita.querySelector('.nacitani')
          if (nacitani) {
            nacitani.innerHTML = '<td colspan="6"><em>Chyba při načítání</em> 😢</td>'
          }
        })
      })
    }

    /**
     * @param {{id_aktivity: number, editovatelna_od_timestamp: number, konec_aktivity_v_timestamp: number|null, ucastnici_pridatelni_do_timestamp: number, ucastnici_odebratelni_do_timestamp: number, cas_posledni_zmeny_stavu_aktivity: string, stav_aktivity: string, id_logu: number, ucastnici: Array, emaily: string[]}} aktivitaData
     */
    function zpracujPocatecniStavAktivity(aktivitaData) {
      const aktivitaNode = akceAktivity.dejNodeAktivity(aktivitaData.id_aktivity)
      if (!aktivitaNode) {
        return
      }

      // nastavíme data-* atributy
      aktivitaNode.dataset.casPosledniZmenyStavuAktivity = aktivitaData.cas_posledni_zmeny_stavu_aktivity
      aktivitaNode.dataset.stavAktivity = aktivitaData.stav_aktivity
      aktivitaNode.dataset.idPoslednihoLogu = aktivitaData.id_logu.toString()
      aktivitaNode.dataset.konecAktivityVTimestamp = (aktivitaData.konec_aktivity_v_timestamp || '').toString()
      aktivitaNode.dataset.editovatelnaOdTimestamp = aktivitaData.editovatelna_od_timestamp.toString()
      aktivitaNode.dataset.ucastniciPridatelniDoTimestamp = aktivitaData.ucastnici_pridatelni_do_timestamp.toString()
      aktivitaNode.dataset.ucastniciOdebratelniDoTimestamp = aktivitaData.ucastnici_odebratelni_do_timestamp.toString()

      // odstraníme loading indikátor
      const nacitani = aktivitaNode.querySelector('.nacitani')
      if (nacitani) {
        nacitani.remove()
      }

      // vložíme účastníky
      const ucastniciSeznam = aktivitaNode.querySelector('.ucastnici-seznam')
      if (aktivitaData.ucastnici) {
        aktivitaData.ucastnici.forEach(function (ucastnikData) {
          const htmlUcastnika = ucastnikData.html_ucastnika.trim()
          if (htmlUcastnika === '') {
            return
          }
          const template = document.createElement('template')
          template.innerHTML = htmlUcastnika
          ucastniciSeznam.appendChild(template.content.firstChild)
        })
      }

      // naplníme emaily
      const emailyNode = document.getElementById(`emaily-${aktivitaData.id_aktivity}`)
      if (emailyNode && aktivitaData.emaily) {
        const emailyAnchor = emailyNode.querySelector('a')
        emailyAnchor.href = 'mailto:?bcc=' + aktivitaData.emaily.join(',')
        emailyAnchor.innerText = aktivitaData.emaily.join(', ')
      }

      // dispathneme event aktivitaVyrenderovana pro každou aktivitu
      const aktivitaVyrenderovana = new CustomEvent(
        'aktivitaVyrenderovana',
        {
          detail: aktivitaNode,
        },
      )
      document.dispatchEvent(aktivitaVyrenderovana)
    }

    function spustitPolling() {
      let jePozastavenaKontrolaZmen = false
      setInterval(function () {
        if (onlinePrezence.dataset.probihajiZmeny === 'true') {
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
      }, 3000)
    }

    function nahratZmenyPrihlaseni() {
      const aktivityPosledniZnameLogy = {}
      /* kvůli testování (viz admin/scripts/modules/moje-aktivity/moje-aktivity.php $testujeme) nelze použít \Uzivatel::organizovaneAktivity protože používáme i aktivity, které tester nemusí organizovat, proto jejich seznam posíláme z frontendu */
      const aktivityUcastniciPosledniZnameLogy = {}

      $aktivity.each(function (indexAktivity, aktivita) {
        aktivityPosledniZnameLogy[aktivita.dataset.id] = {idPoslednihoLogu: aktivita.dataset.idPoslednihoLogu}

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
        [posledniLogyAktivitAjaxKlic]: aktivityPosledniZnameLogy,
        /*
         Musíme použít nejstarší ID logu.
         Nemůžeme použít nejnovější ID logu, protože při zmeně jednoho přihlášení přes online prezenci
         o chvililinku později, než někdo jiný změnil jiné přihlášení, tak bychom použili ID posledního logu z naší,
         poslední změny a tím bychom přeskočili nedávnou změnu od jinud.
         */
        [posledniLogyUcastnikuAjaxKlic]: aktivityUcastniciPosledniZnameLogy,
      }).done(/** @param {{razitko_posledni_zmeny: string, zmeny_stavu_aktivit: [], zmeny_prihlaseni: []}} data */function (data) {
        if (data.zmeny_stavu_aktivit) {
          const zmenyStavuAktivit = ZmenaStavuAktivity.vytvorZmenyZOdpovedi(data.zmeny_stavu_aktivit)
          zmenyStavuAktivit.forEach(function (zmena) {
            zapisZmenuStavuAktivity(zmena)
          })
        }

        if (data.zmeny_prihlaseni) {
          const zmenyPrihlaseni = ZmenaPrihlaseni.vytvorZmenyZOdpovedi(data.zmeny_prihlaseni)
          zmenyPrihlaseni.forEach(function (zmena) {
            zapisZmenuPrihlaseni(zmena)
          })
        }

        const zmenaMetadatPrezence = ZmenaMetadatPrezence.vytvor(data.razitko_posledni_zmeny)
        onlinePrezence.dispatchEvent(zmenaMetadatPrezence)
      })
    }

    /**
     * @param {ZmenaStavuAktivity} zmena
     */
    function zapisZmenuStavuAktivity(zmena) {
      const aktivitaNode = akceAktivity.dejNodeAktivity(zmena.idAktivity)
      if (aktivitaNode) { // else - přidávání nové aktivity nepodporujeme
        zmenStavAktivity(aktivitaNode, zmena)
      }
    }

    /**
     * @param {HTMLElement} aktivitaNode
     * @param {ZmenaStavuAktivity} zmena
     */
    function zmenStavAktivity(aktivitaNode, zmena) {
      if (!jeZmenaPrihlaseniNova(aktivitaNode, zmena)) {
        return
      }
      vypustEventSNovymiMetadatyAktivity(aktivitaNode, zmena)
    }

    /**
     * Bude zpracováno v event listeneru v online-prezence.js přes zapisMetadataAktivity()
     * @param {HTMLElement} aktivitaNode
     * @param {ZmenaStavuAktivity} zmena
     */
    function vypustEventSNovymiMetadatyAktivity(aktivitaNode, zmena) {
      const zmenaMetadatAktivity = ZmenaMetadatAktivity.vytvorZeZmeny(zmena)
      aktivitaNode.dispatchEvent(zmenaMetadatAktivity)
    }

    /**
     * @param {ZmenaPrihlaseni} zmena
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
     * @param {ZmenaPrihlaseni} zmena
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

      akceAktivity.vypustEventONovemUcastnikovi(zmena.idUzivatele, zmena.idAktivity)

      upozorniNaZmenu(nodeNovehoUcastnika, 'lime')
    }

    /**
     * @param {HTMLElement} aktivita
     * @return {HTMLElement}
     */
    function dejNodeUcastniku(aktivita) {
      const cssClass = 'ucastnici-seznam'
      const ucastnici = aktivita.getElementsByClassName(cssClass)[0]
      if (!ucastnici) {
        throw new Error(`Element s účastníky nebyl nalezen v aktivitě ${aktivita.id} podle CSS třídy '${cssClass}'`)
      }
      if (ucastnici.tagName.toUpperCase() !== 'TBODY') {
        throw new Error(`Element s účastníky v aktivitě ${aktivita.id} podle CSS třídy '${cssClass}' měl být tbody, je ${ucastnici.tagName}`)
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
     * @param {ZmenaPrihlaseni} zmena
     */
    function zmenPrihlaseni(ucastnikNode, zmena) {
      if (!jeZmenaPrihlaseniNova(ucastnikNode, zmena)) {
        return
      }
      vypustEventSNovymiMetadatyUcastnika(ucastnikNode, zmena)
    }

    /**
     * @param {HTMLElement} ucastnikNode
     * @param {ZmenaPrihlaseni} zmena
     * @return {boolean}
     */
    function jeZmenaPrihlaseniNova(ucastnikNode, zmena) {
      return !ucastnikNode.dataset.idPoslednihoLogu
        || Number(ucastnikNode.dataset.idPoslednihoLogu) < zmena.idPoslednihoLogu
    }

    /**
     * Bude zpracováno v event listeneru přes AkceAktivity.zapisMetadataUcastnika()
     * @param {HTMLElement} ucastnikNode
     * @param {ZmenaPrihlaseni} zmena
     */
    function vypustEventSNovymiMetadatyUcastnika(ucastnikNode, zmena) {
      const zmenaMetadatUcastnika = ZmenaMetadatUcastnika.vytvor(
        zmena.casZmeny,
        zmena.stavPrihlaseni,
        zmena.idPoslednihoLogu,
        function () {
          const dorazil = dorazilPodleStavu(zmena.stavPrihlaseni)
          zmenZaskrtnutiZdaDorazil(ucastnikNode, dorazil)
        },
      )
      ucastnikNode.dispatchEvent(zmenaMetadatUcastnika)
    }

    /**
     * viz \Gamecon\Aktivita\ZmenaPrihlaseni::stavPrihlaseniProJs
     * @param {string} stavPrihlaseni
     * @return {boolean}
     */
    function dorazilPodleStavu(stavPrihlaseni) {
      return ['ucastnik_dorazil', 'nahradnik_dorazil'].includes(stavPrihlaseni)
    }

    /**
     * viz \Gamecon\Aktivita\ZmenaPrihlaseni::stavPrihlaseniProJs
     * @param {string} stavPrihlaseni
     * @return {boolean}
     */
    function chceHratPodleStavu(stavPrihlaseni) {
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
     * @param {ChildNode} node
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
  })

})(jQuery)

class ZmenaPrihlaseni {
  /**
   * @param {{id_aktivity: number, id_uzivatele: number, id_logu: number, cas_zmeny: string, stav_prihlaseni: string, html_ucastnika: string}[]} dataZmen
   * @return ZmenaPrihlaseni[]
   */
  static vytvorZmenyZOdpovedi(dataZmen) {
    const zmeny = []
    dataZmen.forEach((dataZmeny) => {
      zmeny.push(
        new ZmenaPrihlaseni(
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

class ZmenaStavuAktivity {
  /**
   * @param {{id_aktivity: number, id_logu: number, cas_zmeny: string, stav_aktivity: string, ucastnici_pridatelni_do_timestamp: number, ucastnici_odebratelni_do_timestamp: number}[]} dataZmen
   * @return ZmenaStavuAktivity[]
   */
  static vytvorZmenyZOdpovedi(dataZmen) {
    const zmeny = []
    dataZmen.forEach((dataZmeny) => {
      zmeny.push(
        new ZmenaStavuAktivity(
          dataZmeny.id_aktivity,
          dataZmeny.id_logu,
          dataZmeny.cas_zmeny,
          dataZmeny.stav_aktivity,
          dataZmeny.ucastnici_pridatelni_do_timestamp,
          dataZmeny.ucastnici_odebratelni_do_timestamp,
        ),
      )
    })
    return zmeny
  }

  /** @private @var {number} */
  _idAktivity
  /** @private @var {number} */
  _idPoslednihoLogu
  /** @private @var {string} */
  _casZmeny
  /** @private @var {string} */
  _stavAktivity
  /** @private @var {number} */
  _ucastniciPridatelniDoTimestamp
  /** @private @var {number} */
  _ucastniciOdebratelniDoTimestamp

  /**
   * @param {number} idAktivity
   * @param {number} idPoslednihoLogu
   * @param {string} casZmeny
   * @param {string} stavAktivity
   * @param {number} ucastniciPridatelniDoTimestamp
   * @param {number} ucastniciOdebratelniDoTimestamp
   */
  constructor(
    idAktivity,
    idPoslednihoLogu,
    casZmeny,
    stavAktivity,
    ucastniciPridatelniDoTimestamp,
    ucastniciOdebratelniDoTimestamp,
  ) {
    this._idAktivity = idAktivity
    this._idPoslednihoLogu = idPoslednihoLogu
    this._casZmeny = casZmeny
    this._stavAktivity = stavAktivity
    this._ucastniciPridatelniDoTimestamp = ucastniciPridatelniDoTimestamp
    this._ucastniciOdebratelniDoTimestamp = ucastniciOdebratelniDoTimestamp
  }

  get idAktivity() {
    return this._idAktivity
  }

  get idPoslednihoLogu() {
    return this._idPoslednihoLogu
  }

  get casZmeny() {
    return this._casZmeny
  }

  get stavAktivity() {
    return this._stavAktivity
  }

  get ucastniciPridatelniDoTimestamp() {
    return this._ucastniciPridatelniDoTimestamp
  }

  get ucastniciOdebratelniDoTimestamp() {
    return this._ucastniciOdebratelniDoTimestamp
  }
}
