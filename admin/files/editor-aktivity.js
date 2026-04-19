document.addEventListener('DOMContentLoaded', function () {
    const editorAktivityTlacitkoUlozit = document.querySelector('[name="AktivitaOdesilatko"]') || document.querySelector('[name="Aktivita_s_vlastnim_zmenenym_kliceOdesilatko"]') || document.querySelector('input[type="submit"][value="Uložit"]')
    const editorAktivityForm = editorAktivityTlacitkoUlozit ? editorAktivityTlacitkoUlozit.closest('form') : null;
    
    if (!editorAktivityForm) return;

    function normalizujCeleCislo(hodnota) {
        const cislo = Number.parseInt((hodnota ?? '').toString().trim(), 10)
        return Number.isNaN(cislo)
            ? 0
            : cislo
    }

    function normalizujDen(den) {
        const trimnutyDen = (den ?? '').toString().trim()
        return trimnutyDen !== ''
            ? trimnutyDen
            : '0'
    }

    const editorAktivity = document.getElementById('editorAktivity');
    const pocetPrihlasenych = editorAktivity && editorAktivity.dataset.pocetPrihlasenych
        ? parseInt(editorAktivity.dataset.pocetPrihlasenych, 10)
        : 0

    const $potvrzeniZmenyUdajuSPrihlasenymi = $('[name="' + (editorAktivity ? editorAktivity.dataset.klicPotvrzeni : 'potvrditZmenuUdajuSPrihlasenymi') + '"]')

    const puvodniUdaje = {
        den: normalizujDen($('#denAkce').val()),
        zacatek: ($('#zacatekAkce').val() ?? '').toString().trim(),
        konec: ($('#konecAkce').val() ?? '').toString().trim(),
        cena: normalizujCeleCislo($('#cenaAktivityCelkem').val()),
        teamova: $('#teamova').is(':checked'),
        kapacita: normalizujCeleCislo($('[name="Aktivita[kapacita]"]').val()),
        kapacitaF: normalizujCeleCislo($('[name="Aktivita[kapacita_f]"]').val()),
        kapacitaM: normalizujCeleCislo($('[name="Aktivita[kapacita_m]"]').val()),
        teamMin: normalizujCeleCislo($('[name="Aktivita[team_min]"]').val()),
        teamMax: normalizujCeleCislo($('[name="Aktivita[team_max]"]').val()),
    }

    function jeZmenenaKapacita() {
        const teamova = $('#teamova').is(':checked')
        if (teamova !== puvodniUdaje.teamova) {
            return true
        }
        if (teamova) {
            return normalizujCeleCislo($('[name="Aktivita[team_min]"]').val()) !== puvodniUdaje.teamMin
                || normalizujCeleCislo($('[name="Aktivita[team_max]"]').val()) !== puvodniUdaje.teamMax
        }

        return normalizujCeleCislo($('[name="Aktivita[kapacita]"]').val()) !== puvodniUdaje.kapacita
            || normalizujCeleCislo($('[name="Aktivita[kapacita_f]"]').val()) !== puvodniUdaje.kapacitaF
            || normalizujCeleCislo($('[name="Aktivita[kapacita_m]"]').val()) !== puvodniUdaje.kapacitaM
    }

    $(editorAktivityTlacitkoUlozit).click(function () {
        if (typeof overeni === 'function' && !overeni()) {
          return false
        }
        if ($potvrzeniZmenyUdajuSPrihlasenymi.length) {
            $potvrzeniZmenyUdajuSPrihlasenymi.val('0')
        }
        
        if (pocetPrihlasenych > 0) {
            const zmeneneUdaje = []
            if (normalizujDen($('#denAkce').val()) !== puvodniUdaje.den) {
                zmeneneUdaje.push('den')
            }
            const novyZacatek = ($('#zacatekAkce').val() ?? '').toString().trim()
            const novyKonec = ($('#konecAkce').val() ?? '').toString().trim()
            if (novyZacatek !== puvodniUdaje.zacatek || novyKonec !== puvodniUdaje.konec) {
                zmeneneUdaje.push('čas')
            }
            if (normalizujCeleCislo($('#cenaAktivityCelkem').val()) !== puvodniUdaje.cena) {
                zmeneneUdaje.push('cenu')
            }
            if (jeZmenenaKapacita()) {
                zmeneneUdaje.push('kapacitu')
            }

            if (zmeneneUdaje.length > 0) {
                const zprava = 'Tato aktivita už má přihlášené hráče (' + pocetPrihlasenych
                    + '). Opravdu chcete změnit ' + zmeneneUdaje.join(' / ') + '?'
                if (!window.confirm(zprava)) {
                    return false
                }
                if ($potvrzeniZmenyUdajuSPrihlasenymi.length) {
                    $potvrzeniZmenyUdajuSPrihlasenymi.val('1')
                }
            }
        }

        const odesilatko = $(this)
        odesilatko.attr('disabled', 'disabled')
        $.post(document.URL, $(this).closest('form').serialize() + '&ajax=true', function (data) {
            if (data.chyby && data.chyby.length > 0) {
                alert('Nepodařilo se uložit, protože:\n• ' + data.chyby.join('\n• '))
                odesilatko.removeAttr('disabled')
            } else {
                location.reload()
            }
        })
        return false
    })
})