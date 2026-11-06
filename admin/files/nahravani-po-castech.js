/**
 * Velký soubor z formuláře s atributem `data-nahravani-po-castech` pošle po částech na adresu stránky
 * (viz model/Web/NahravaniPoCastech.php) a pak formulář odešle s `nahranoId` místo souboru. Menší soubor
 * (`data-nahravani-prah` bajtů a méně) a prohlížeč bez tohoto skriptu odesílají formulář jako dřív.
 */
(() => {
  const HLAVICKY = {'X-Gamecon-Nahravani': '1'}
  const POKUSY_NA_CAST = 3
  const PAUZA_PRED_OPAKOVANIM_MS = 500

  document.querySelectorAll('form[data-nahravani-po-castech]').forEach(pripravFormular)

  // stránka vrácená z mezipaměti prohlížeče (tlačítko Zpět) má formulář ve stavu po odeslání, s nepoužitelným id
  window.addEventListener('pageshow', (udalost) => {
    if (udalost.persisted) {
      document.querySelectorAll('form[data-nahravani-po-castech]').forEach(obnovFormular)
    }
  })

  function pripravFormular (formular) {
    formular.addEventListener('submit', async (udalost) => {
      const vstup = formular.querySelector('input[type=file]')
      const soubor = vstup?.files?.[0]
      if (!soubor || soubor.size <= Number(formular.dataset.nahravaniPrah) || formular.dataset.nahrano === '1') {
        return
      }
      udalost.preventDefault()
      if (formular.dataset.nahrava === '1') {
        return
      }
      formular.dataset.nahrava = '1'
      try {
        await nahrajAOdesli(formular, vstup, soubor)
      } finally {
        delete formular.dataset.nahrava
      }
    })
  }

  function obnovFormular (formular) {
    formular.querySelector('input[name=nahranoId]')?.remove()
    formular.querySelector('.nahravani-po-castech')?.remove()
    formular.querySelector('input[type=file]')?.removeAttribute('disabled')
    formular.querySelector('button[type=submit]')?.removeAttribute('disabled')
    delete formular.dataset.nahrano
    delete formular.dataset.nahrava
  }

  async function nahrajAOdesli (formular, vstup, soubor) {
    const tlacitko = formular.querySelector('button[type=submit]')
    // po chybě jde nahrávat znovu, starý ukazatel s chybou by tu zůstal vedle nového
    formular.querySelector('.nahravani-po-castech')?.remove()
    const ukazatel = vytvorUkazatel(formular)
    const zrusit = new AbortController()
    ukazatel.tlacitkoZrusit.addEventListener('click', () => zrusit.abort())
    const nezavirat = (udalost) => {
      udalost.preventDefault()
      udalost.returnValue = ''
    }

    if (tlacitko) {
      tlacitko.disabled = true
    }
    window.addEventListener('beforeunload', nezavirat)
    let id = null
    try {
      // zahájení je krátké a bez signálu: přerušené po tom, co ho server založil, by nechalo nahrávání bez id
      // a to by pak zabíralo jedno z rozpracovaných, dokud ho server po dni neuklidí
      const zacatek = await pozadavek(formular, 'zahaj', {}, {
        body: new URLSearchParams({velikost: String(soubor.size), nazev: soubor.name}),
      })
      id = zacatek.id
      const pocetCasti = Math.ceil(soubor.size / zacatek.castBajtu)
      for (let index = 0; index < pocetCasti; index++) {
        zrusit.signal.throwIfAborted()
        const od = index * zacatek.castBajtu
        await poslCast(formular, id, index, soubor.slice(od, od + zacatek.castBajtu), zrusit.signal)
        ukazatel.nastav(Math.round((index + 1) / pocetCasti * 100))
      }
      zrusit.signal.throwIfAborted()
    } catch (chyba) {
      if (id !== null) {
        // nedokončené nahrávání na serveru zbytečně nezůstává do dalšího úklidu
        pozadavek(formular, 'zrus', {}, {body: new URLSearchParams({id})}).catch(() => {})
      }
      window.removeEventListener('beforeunload', nezavirat)
      ukazatel.chyba(chyba.name === 'AbortError' ? 'Nahrávání je zrušené.' : chyba.message)
      if (tlacitko) {
        tlacitko.disabled = false
      }

      return
    }
    window.removeEventListener('beforeunload', nezavirat)

    ukazatel.hotovo()
    const skryte = document.createElement('input')
    skryte.type = 'hidden'
    skryte.name = 'nahranoId'
    skryte.value = id
    formular.append(skryte)
    // soubor už je na serveru, přes formulář by putoval podruhé
    vstup.disabled = true
    formular.dataset.nahrano = '1'
    formular.submit()
  }

  async function poslCast (formular, id, index, cast, signal) {
    let posledniChyba = null
    for (let pokus = 1; pokus <= POKUSY_NA_CAST; pokus++) {
      try {
        return await pozadavek(formular, 'cast', {nahrani: id, index}, {
          body: cast,
          headers: {'Content-Type': 'application/octet-stream'},
          signal,
        })
      } catch (chyba) {
        // chyba, kterou server vysvětlil, se opakováním nespraví; výpadek spojení nebo chyba serveru ano
        if (!chyba.opakovatelna || chyba.name === 'AbortError') {
          throw chyba
        }
        posledniChyba = chyba
        await new Promise((hotovo) => setTimeout(hotovo, PAUZA_PRED_OPAKOVANIM_MS * 2 ** (pokus - 1)))
      }
    }

    throw posledniChyba
  }

  /**
   * @returns {Promise<object>} odpověď serveru s `ok: true`; chybu serveru a výpadek spojení vyhodí jako `Error`,
   *                            `opakovatelna` říká, jestli má smysl totéž poslat znovu
   */
  async function pozadavek (formular, krok, parametry, volby) {
    const adresa = new URL(formular.action, location.href)
    adresa.searchParams.set('nahravaniPoCastech', krok)
    Object.entries(parametry).forEach(([nazev, hodnota]) => adresa.searchParams.set(nazev, String(hodnota)))

    let odpoved
    try {
      odpoved = await fetch(adresa, {
        method: 'POST',
        credentials: 'same-origin',
        ...volby,
        headers: {...HLAVICKY, ...(volby.headers ?? {})},
      })
    } catch (chyba) {
      if (chyba.name === 'AbortError') {
        throw chyba
      }
      throw chybaPozadavku('Spojení se serverem se přerušilo.', true)
    }
    let telo = null
    try {
      telo = await odpoved.json()
    } catch (chyba) {
      // odpověď bez JSON je chyba serveru nebo proxy
    }
    if (odpoved.ok && telo?.ok === true) {
      return telo
    }

    throw chybaPozadavku(
      telo?.zprava ?? `Server odpověděl chybou ${odpoved.status}.`,
      telo?.ok === undefined && odpoved.status >= 500,
    )
  }

  function chybaPozadavku (zprava, opakovatelna) {
    const chyba = new Error(zprava)
    chyba.opakovatelna = opakovatelna

    return chyba
  }

  function vytvorUkazatel (formular) {
    const kontejner = document.createElement('div')
    kontejner.className = 'nahravani-po-castech'
    const ukazatel = document.createElement('progress')
    ukazatel.max = 100
    ukazatel.value = 0
    const stav = document.createElement('span')
    stav.className = 'nahravani-po-castech__stav'
    stav.textContent = 'Nahrávám soubor po částech, nezavírej prosím stránku.'
    const tlacitkoZrusit = document.createElement('button')
    tlacitkoZrusit.type = 'button'
    tlacitkoZrusit.textContent = 'Zrušit'
    kontejner.append(ukazatel, ' ', stav, ' ', tlacitkoZrusit)
    formular.append(kontejner)

    return {
      tlacitkoZrusit,
      nastav (procent) {
        ukazatel.value = procent
        stav.textContent = `Nahráno ${procent} %, nezavírej prosím stránku.`
      },
      hotovo () {
        ukazatel.value = 100
        stav.textContent = 'Nahráno, zpracovávám…'
        tlacitkoZrusit.remove()
      },
      chyba (zprava) {
        stav.textContent = zprava
        stav.classList.add('nahravani-po-castech__chyba')
        ukazatel.remove()
        tlacitkoZrusit.remove()
      },
    }
  }
})()
