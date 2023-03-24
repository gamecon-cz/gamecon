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
          nastaveni.zobrazZmeny(ulozenaData, inputNode)
          nastaveni.oznamUlozeni(inputNode)
        },
        () => nastaveni.oznamChybu(inputNode),
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
   * 		"aktivni": string,
   * 		"nazev": string,
   * 		"popis": string,
   * 		"kdy": string,
   * 		"id_uzivatele": string,
   * 		"posledniZmena": string,
   * 		"zmenil": string,
   * 		"inputType": string
   * 	}
   * } novaData
   * @param {HTMLInputElement} inputNode
   */
  zobrazZmeny(novaData, inputNode) {
    const posledniZmenaElement = document.getElementById(`posledni-zmena-${novaData.klic}`)
    posledniZmenaElement.innerHTML = novaData.posledniZmena

    const zmenilElement = document.getElementById(`zmenil-${novaData.klic}`)
    zmenilElement.innerHTML = novaData.zmenil

    const popisElement = document.getElementById(`popis-${novaData.klic}`)
    popisElement.innerHTML = novaData.popis

    if (inputNode.type === 'checkbox') {
      this.zobrazHodnotuPodleAktivity(Boolean(Number(novaData.aktivni)), novaData.klic)
    }
  }

  /**
   * @param {boolean} aktivni
   * @param {string} klic
   */
  zobrazHodnotuPodleAktivity(aktivni, klic) {
    if (aktivni) {
      const vychoziHodnotaNode = document.getElementById(`vychozi-hodnota-${klic}`)
      vychoziHodnotaNode.style.display = 'none'
      const hodnotaNode = document.getElementById(`hodnota-${klic}`)
      hodnotaNode.style.display = 'inherit'
    } else {
      const vychoziHodnotaNode = document.getElementById(`vychozi-hodnota-${klic}`)
      vychoziHodnotaNode.style.display = 'inherit'
      const hodnotaNode = document.getElementById(`hodnota-${klic}`)
      hodnotaNode.style.display = 'none'
    }
  }

  /**
   * @param {HTMLElement} element
   */
  oznamUlozeni(element) {
    this.oznamVysledek(element, '✔️' /* to nekonci mezerou, to je soucast smajliku, nemazat ji */, 'Změny jsou uloženy')
  }

  /**
   * @param {HTMLElement} element
   */
  oznamChybu(element) {
    this.oznamVysledek(element, '❗', 'Změny se nepodařilo uložit')
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
