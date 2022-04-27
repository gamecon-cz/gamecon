(function ($) {
  document.addEventListener('DOMContentLoaded', function () {
    const urlPosledniZmenyPrihlaseni = document.getElementById('online-prezence').dataset.urlPosledniZmenyPrihlaseni

    const $onlinePrezence = $('#online-prezence')

    setInterval(function () {

      const $aktivity = $onlinePrezence.find('.aktivita')

      const postData = []

      $aktivity.each(function (indexAktivity, aktivita) {
        const $ucastnici = $(aktivita).find('.ucastnik')

        const posledniZnamePrihlaseniUcastniku = []
        $ucastnici.each(function (indexUcastnka, ucastnik) {
          posledniZnamePrihlaseniUcastniku.push({
            'id_uzivatele': ucastnik.dataset.id,
            'cas_zmeny_prihlaseni': ucastnik.dataset.casZmenyPrihlaseni,
            'stav_prihlaseni': ucastnik.dataset.stavPrihlaseni,
          })
        })

        postData.push({
          'id_aktivity': aktivita.dataset.id,
          'ucastnici': posledniZnamePrihlaseniUcastniku,
        })
      })

      $.post(urlPosledniZmenyPrihlaseni, {
        /**
         * @see \Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax::odbavAjax
         * @see \Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax::ajaxDejPosledniZmeny
         */
        'zname_zmeny_prihlaseni': postData,
      }).done(function (data) {
        console.log(data)
      })
    }, 10000)
  })
})(jQuery)
