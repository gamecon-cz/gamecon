document.addEventListener('DOMContentLoaded', function () {
  const nastaveniElement = document.getElementById('nastaveni')
  const nastaveni = new SystemoveNastaveni(nastaveniElement.dataset.ajaxKlic)

  const inputNodes = document.getElementsByClassName('hodnota-nastaveni')
  Array.from(inputNodes).forEach(/** @param {HTMLElement} inputNode */function (inputNode) {
    nastaveni.odesilejPriZmnene(inputNode, ['keyup', 'change'])
  })

  const checkboxNodes = document.getElementsByClassName('aktivace-nastaveni')
  Array.from(checkboxNodes).forEach(/** @param {HTMLElement} checkboxNode */function (checkboxNode) {
    nastaveni.odesilejPriZmnene(checkboxNode, ['click'])
  })

  $('.hodnota-nastaveni[data-tag-input-type=date]').each(function (index, element) {
    element.type = 'text'
    $(element).datepicker({
      dateFormat: 'd. m. yy',
      onSelect: function () {
        const changeEvent = new Event('change')
        element.dispatchEvent(changeEvent)
      },
    })
  })

  $('.hodnota-nastaveni[data-tag-input-type=datetime-local]').each(function (index, element) {
    element.type = 'text'
    $(element).datetimepicker({
      dateFormat: 'd. m. yy',
      timeFormat: 'HH:mm:ss',
      onSelect: function () {
        const changeEvent = new Event('change')
        element.dispatchEvent(changeEvent)
      },
    })
  })

  if (window.location.hash) {
    const urlHash = window.location.hash
    const idFromHash = urlHash.trim().replace('#', '').toUpperCase()
    const elementProZvyrazneni = document.getElementById(idFromHash)
    if (elementProZvyrazneni) {
      elementProZvyrazneni.classList.add('zvyrazni')
    }
  }

  // class pro zvýraznění může také přijít z PHP, viz \Gamecon\SystemoveNastaveni\SystemoveNastaveniHtml::vypisSkupinu
  Array.from(nastaveniElement.getElementsByClassName('zvyrazni')).forEach(function (zvyrazniElement) {
    zablikej(zvyrazniElement)
  })

})

class SystemoveNastaveni {

  /**
   * {string}
   * @private
   */
  _postUrl

  /**
   * @param {string} ajaxKlic
   */
  constructor(ajaxKlic) {
    const url = new URL(window.location.href)
    url.searchParams.set(ajaxKlic, '1')
    this._postUrl = url.toString()
  }

  /**
   * @param {HTMLInputElement} inputNode
   */
  nastavPosledniUlozenouHodnotu(inputNode) {
    if (inputNode.type === 'checkbox') {
      inputNode.dataset.lastSavedValue = inputNode.checked.toString()
    } else {
      inputNode.dataset.lastSavedValue = inputNode.value
    }
  }

  /**
   * @param {HTMLInputElement} inputNode
   * @param {string[]} eventsNames
   */
  odesilejPriZmnene(inputNode, eventsNames) {
    this.nastavPosledniUlozenouHodnotu(inputNode)
    const nastaveni = this

    const ulozNastaveni = function () {
      inputNode = this // protože funkce bude volána v kontextu eventu na inputNode
      nastaveni.ulozNastaveni(
        inputNode,
        () => nastaveni.zrusPredchoziPotvrzeniUlozeni(inputNode),
        function (ulozenaData) {
          nastaveni.nastavZmeny(ulozenaData, inputNode)
          nastaveni.zobrazZmeny(ulozenaData, inputNode)
          nastaveni.oznamUlozeni(inputNode)
        },
        (odpoved) => nastaveni.oznamChybu(inputNode, odpoved),
      )
    }
    eventsNames.forEach(function (eventName) {
      inputNode.addEventListener(eventName, ulozNastaveni)
    })
  }

  /**
   * @param {HTMLInputElement} inputNode
   * @param {function} callableOnStart
   * @param {function} callableOnSuccess
   * @param {function} callableOnFailure
   */
  ulozNastaveni(inputNode, callableOnStart, callableOnSuccess, callableOnFailure) {
    if (this.jeHodnotaBezeZmeny(inputNode)) {
      return // tahle změna už byla zpracována
    }
    callableOnStart()

    const nastaveni = this
    const currentTimeoutId = setTimeout(function () {
      // byla tohle poslední zmněna za posledních 150 milisekund?
      if (inputNode.dataset.lastTimeoutId === currentTimeoutId.toString()) {
        delete inputNode.dataset.lastTimeoutId
        nastaveni.odesliNastaveni(
          inputNode,
          callableOnSuccess /* ukládání je asynchronní - skutečný konec zná až tahle funkce */,
          callableOnFailure,
        )
      }
    }, 150)
    inputNode.dataset.lastTimeoutId = currentTimeoutId.toString()
  }

  /**
   * @param {HTMLInputElement} inputNode
   * @param {function} callableOnSuccess
   * @param {function} callableOnFailure
   */
  odesliNastaveni(inputNode, callableOnSuccess, callableOnFailure) {
    if (this.jeHodnotaBezeZmeny(inputNode)) {
      return // tahle data už byla odeslána
    }
    this.nastavPosledniUlozenouHodnotu(inputNode)

    const request = new XMLHttpRequest()
    request.addEventListener('loadend', function () {
      if (request.status >= 200 && request.status < 300) {
        callableOnSuccess(JSON.parse(request.responseText))
      } else {
        callableOnFailure(JSON.parse(request.responseText))
      }
    })

    const formData = new FormData
    formData.set(
      inputNode.name,
      inputNode.type === 'checkbox'
        ? inputNode.checked
        : inputNode.value,
    )
    request.open('POST', this._postUrl)
    request.send(formData)
  }

  /**
   * @param {HTMLInputElement} inputNode
   * @return {boolean}
   */
  jeHodnotaBezeZmeny(inputNode) {
    if (inputNode.type === 'checkbox') {
      return inputNode.dataset.lastSavedValue === inputNode.checked.toString()
    }
    return inputNode.dataset.lastSavedValue === inputNode.value
  }

  /**
   * @param {HTMLElement} element
   */
  zrusPredchoziPotvrzeniUlozeni(element) {
    const vysledekNodes = element.parentElement.getElementsByClassName('vysledek')
    Array.from(vysledekNodes).forEach(function (savedNode) {
      savedNode.remove()
    })
  }

  /**
   * @param {
   * 	{
   * 		"klic": string,
   * 		"hodnota": string,
   * 		"datovy_typ": string,
   * 		"vlastni": string,
   * 		"nazev": string,
   * 		"popis": string,
   * 		"kdy": string,
   * 		"id_uzivatele": string,
   * 		"posledniZmena": string,
   * 		"zmenil": string,
   * 		"inputType": string,
   * 		"inputValue": string,
   * 	}
   * } novaData
   * @param {HTMLInputElement} inputNode
   */
  nastavZmeny(novaData, inputNode) {
    const hodnotaNode = this.dejNodeHodnoty(novaData.klic)
    hodnotaNode.value = this.dekodujHtml(novaData.inputValue) // vzácně ji může backend změnit, například úpravou formátu
  }

  /**
   * @param {string} html
   * @return {string}
   */
  dekodujHtml(html) {
    var htmlTextAreaElement = document.createElement("textarea");
    htmlTextAreaElement.innerHTML = html;
    return htmlTextAreaElement.value;
  }

  /**
   * @param {
   * 	{
   * 		"klic": string,
   * 		"hodnota": string,
   * 		"datovy_typ": string,
   * 		"vlastni": string,
   * 		"nazev": string,
   * 		"popis": string,
   * 		"kdy": string,
   * 		"id_uzivatele": string,
   * 		"posledniZmena": string,
   * 		"zmenil": string,
   * 		"inputType": string,
   *  	"inputValue": string,
   * 	}
   * } novaData
   * @param {HTMLInputElement} inputNode
   */
  zobrazZmeny(novaData, inputNode) {
    const posledniZmenaElement = this.getElementById(`posledni-zmena-${novaData.klic}`)
    posledniZmenaElement.innerHTML = novaData.posledniZmena

    const zmenilElement = this.getElementById(`zmenil-${novaData.klic}`)
    zmenilElement.innerHTML = novaData.zmenil

    const popisElement = this.getElementById(`popis-${novaData.klic}`)
    popisElement.innerHTML = novaData.popis

    if (inputNode.type === 'checkbox') {
      this.zobrazHodnotuPodleAktivity(Boolean(Number(novaData.vlastni)), novaData.klic)
    }
  }

  /**
   * @param {string} id
   * @return {HTMLElement}
   */
  getElementById(id) {
    var element = document.getElementById(id)
    if (element === null) {
      throw new Error(`No element by ID '${id}'`)
    }
    return element
  }

  /**
   * @param {boolean} vlastni
   * @param {string} klic
   */
  zobrazHodnotuPodleAktivity(vlastni, klic) {
    if (vlastni) {
      const vychoziHodnotaNode = this.getElementById(`vychozi-hodnota-${klic}`)
      vychoziHodnotaNode.style.display = 'none'
      const hodnotaNode = this.dejNodeHodnoty(klic)
      hodnotaNode.style.display = 'inherit'
    } else {
      const vychoziHodnotaNode = this.getElementById(`vychozi-hodnota-${klic}`)
      vychoziHodnotaNode.style.display = 'inherit'
      const hodnotaNode = this.getElementById(`hodnota-${klic}`)
      hodnotaNode.style.display = 'none'
    }
  }

  /**
   * @param {string} klic
   * @return {HTMLInputElement}
   */
  dejNodeHodnoty(klic) {
    return this.getElementById(`hodnota-${klic}`)
  }

  /**
   * @param {HTMLElement} element
   */
  oznamUlozeni(element) {
    this.oznamVysledek(element, '✔️' /* to nekonci mezerou, to je soucast smajliku, nemazat ji */, 'Změny jsou uloženy')
  }

  /**
   * @param {HTMLElement} element
   * @param {{error: string}} odpoved
   */
  oznamChybu(element, odpoved) {
    this.oznamVysledek(element, '❗', `Změny se nepodařilo uložit: <b>${odpoved.error}</b>`)
  }

  /**
   * @param {HTMLElement} element
   * @param {string} text
   * @param {string} hint
   */
  oznamVysledek(element, text, hint) {
    const parent = element.parentNode
    /** https://developer.mozilla.org/en-US/docs/Web/HTML/Element/template */
    const vysledek = document.createElement('template')
    vysledek.innerHTML = '<span class="vysledek hinted" style="position: absolute; margin-left: 2px">' + text + '<span class="hint">' + hint + '</span></span>'
    parent.appendChild(vysledek.content)
  }
}
