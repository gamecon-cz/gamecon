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
        detail: {probihaji: probihaji},
      },
    )
  }
}

/**
 * @param {boolean} probihaji
 */
function vypustEventOProbihajicichZmenach(probihaji) {
  const probihajiZmenyEvent = ProbihajiZmeny.vytvor(probihaji)
  document.getElementById('online-prezence').dispatchEvent(probihajiZmenyEvent)
}

export {ZmenaMetadatAktivity, vypustEventOProbihajicichZmenach}
