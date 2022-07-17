class ZmenaMetadatAktivity extends CustomEvent {
  static eventName = 'zmenaMetadatAktivity'

  /**
   * @param {ZmenaStavuAktivity} zmena
   * @return {ZmenaMetadatAktivity}
   */
  static vytvorZeZmeny(zmena) {
    return new ZmenaMetadatAktivity(
      this.eventName,
      {
        detail: {
          casPosledniZmenyStavuAktivity: zmena.casZmeny,
          stavAktivity: zmena.stavAktivity,
          idPoslednihoLogu: zmena.idPoslednihoLogu,
          ucastniciPridatelniDoTimestamp: zmena.ucastniciPridatelniDoTimestamp,
          ucastniciOdebratelniDoTimestamp: zmena.ucastniciOdebratelniDoTimestamp,
        },
      },
    )
  }
}

class ZmenaMetadatUcastnika extends CustomEvent {
  static eventName = 'zmenaMetadatUcastnika'

  /**
   * @param {string} casPosledniZmenyPrihlaseni
   * @param {string} stavPrihlaseni
   * @param {string} idPoslednihoLogu
   * @param {function|undefined} callback
   * @return {ZmenaMetadatUcastnika}
   */
  static vytvor(
    casPosledniZmenyPrihlaseni,
    stavPrihlaseni,
    idPoslednihoLogu,
    callback = undefined,
  ) {
    return new ZmenaMetadatUcastnika(
      this.eventName,
      {
        detail: {
          casPosledniZmenyPrihlaseni: casPosledniZmenyPrihlaseni,
          stavPrihlaseni: stavPrihlaseni,
          idPoslednihoLogu: idPoslednihoLogu,
          callback: callback,
        },
      },
    )
  }
}

class ZmenaMetadatPrezence extends CustomEvent {
  static eventName = 'zmenaMetadatPrezence'

  /**
   * @param {string} razitkoPosledniZmeny
   * @return {ZmenaMetadatPrezence}
   */
  static vytvor(razitkoPosledniZmeny) {
    return new ZmenaMetadatPrezence(
      this.eventName,
      {
        detail: {
          razitkoPosledniZmeny: razitkoPosledniZmeny,
        },
      },
    )
  }
}

class ProbihajiZmeny extends CustomEvent {
  static eventName = 'probihajiZmeny'

  /**
   * @param {boolean} probihaji
   * @return {ProbihajiZmeny}
   */
  static vytvor(probihaji) {
    return new ProbihajiZmeny(
      this.eventName,
      {
        detail: {
          probihaji: probihaji,
        },
      },
    )
  }
}

class NovyUcastnik extends CustomEvent {
  static eventName = 'novyUcastnik'

  /**
   * @param {number} idUzivatele
   * @param {number} idAktivity
   * @return {NovyUcastnik}
   */
  static vytvor(idUzivatele, idAktivity) {
    return new NovyUcastnik(
      this.eventName,
      {
        detail: {
          idUzivatele: idUzivatele,
          idAktivity: idAktivity,
        },
      },
    )
  }
}

class AjaxErrors extends CustomEvent {
  static eventName = 'ajaxErrors'

  /**
   * @param {{errors: string[]|undefined, warnings: string[]|undefined, triggeringNode: HTMLElement|undefined}} problems
   * @return {AjaxErrors}
   */
  static vytvor(problems) {
    return new AjaxErrors(
      this.eventName,
      {
        detail: problems,
      },
    )
  }
}

export {ZmenaMetadatAktivity, ZmenaMetadatUcastnika, ZmenaMetadatPrezence, ProbihajiZmeny, NovyUcastnik, AjaxErrors}
