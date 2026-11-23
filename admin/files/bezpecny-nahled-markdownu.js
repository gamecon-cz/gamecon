// Živý náhled textu, který si uživatel píše sám (medailonek): stejná pravidla jako Gamecon\Web\BezpecneHtml v PHP.
// Pravidla posílá PHP (BezpecneHtml::getPravidlaProNahled(), atribut data-bezpecny-nahled), aby byla jen jedna jejich
// verze. Výsledek se skládá znovu z povolených značek, ne mazáním zakázaných; HTML z Markdownu se jen přečte
// v DOMParseru, kde se nic nespustí ani nenačte.
window.gcBezpecneHtml = function (html, pravidla) {
  var zdroj = new DOMParser().parseFromString('<!doctype html><body>' + html, 'text/html').body
  var vysledek = document.createDocumentFragment()

  function rozeber(adresa) {
    // relativní adresa vede na web, stejně jako v PHP
    try {
      return new URL(adresa, 'https://' + pravidla.hostWebu + '/')
    } catch (e) {
      return null
    }
  }

  function jeHostGameconu(host) {
    host = host.replace(/\.$/, '')
    return host === pravidla.domenaGameconu
      || host.slice(-pravidla.domenaGameconu.length - 1) === '.' + pravidla.domenaGameconu
      || host === pravidla.hostWebu
  }

  function jePovolenaHodnota(atribut, hodnota) {
    var adresa
    switch (atribut) {
      case 'href':
        adresa = rozeber(hodnota)
        return adresa !== null && hodnota.trim() !== ''
          && pravidla.schemataOdkazu.indexOf(adresa.protocol.replace(/:$/, '')) !== -1
      case 'src':
        adresa = rozeber(hodnota)
        return adresa !== null && hodnota.trim() !== ''
          && pravidla.schemataObrazku.indexOf(adresa.protocol.replace(/:$/, '')) !== -1
          && jeHostGameconu(adresa.hostname)
      case 'width':
      case 'height':
      case 'colspan':
      case 'rowspan':
      case 'start':
        return /^\d{1,4}$/.test(hodnota)
      case 'align':
        return ['left', 'right', 'center'].indexOf(hodnota) !== -1
      default:
        return true // title, alt: nastaví se jako text
    }
  }

  function prenes(rodic, cil) {
    Array.prototype.forEach.call(rodic.childNodes, function (uzel) {
      if (uzel.nodeType === Node.TEXT_NODE) {
        cil.appendChild(document.createTextNode(uzel.data))
        return
      }
      if (uzel.nodeType !== Node.ELEMENT_NODE) {
        return // komentáře
      }
      var znacka = uzel.nodeName.toLowerCase()
      if (pravidla.bezObsahu.indexOf(znacka) !== -1) {
        return
      }
      var atributy = Object.prototype.hasOwnProperty.call(pravidla.znacky, znacka)
        ? pravidla.znacky[znacka]
        : null
      if (atributy === null) {
        prenes(uzel, cil)
        return
      }
      if (znacka === 'img' && !jePovolenaHodnota('src', uzel.getAttribute('src') || '')) {
        return
      }
      if (znacka === 'a' && !jePovolenaHodnota('href', uzel.getAttribute('href') || '')) {
        prenes(uzel, cil)
        return
      }
      var prvek = document.createElement(znacka)
      atributy.forEach(function (atribut) {
        var hodnota = uzel.getAttribute(atribut)
        if (hodnota !== null && jePovolenaHodnota(atribut, hodnota)) {
          prvek.setAttribute(atribut, hodnota)
        }
      })
      if (znacka === 'a') {
        var odkaz = rozeber(uzel.getAttribute('href'))
        if (/^https?:$/.test(odkaz.protocol) && !jeHostGameconu(odkaz.hostname)) {
          prvek.setAttribute('rel', pravidla.relOdkazuVen)
        }
      }
      if (pravidla.prazdne.indexOf(znacka) === -1) {
        prenes(uzel, prvek)
      }
      cil.appendChild(prvek)
    })
  }

  prenes(zdroj, vysledek)

  return vysledek
}
