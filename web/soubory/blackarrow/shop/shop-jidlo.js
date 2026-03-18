{
    function dejSnidaneBunky(den) {
        var vsechnyBunky = document.querySelectorAll('td[data-den="' + den + '"]');
        var snidaneBunky = [];
        vsechnyBunky.forEach(function (td) {
            if (td.dataset.druh && td.dataset.druh.toLowerCase().indexOf('snídaně') === 0) {
                snidaneBunky.push(td);
            }
        });
        return snidaneBunky;
    }

    function aktualizujSnidane() {
        var shopUbytovaniRadios = document.querySelectorAll('input.shopUbytovani_radio');
        var dnyRadios = {};
        shopUbytovaniRadios.forEach(function (radio) {
            var match = radio.name.match(/\[(\d+)]/);
            if (!match) {
                return;
            }
            var den = match[1];
            if (!dnyRadios[den]) {
                dnyRadios[den] = [];
            }
            dnyRadios[den].push(radio);
        });

        Object.keys(dnyRadios).forEach(function (den) {
            var jeHotel = false;
            dnyRadios[den].forEach(function (radio) {
                if (radio.checked && radio.dataset.podtyp === 'hotel') {
                    jeHotel = true;
                }
            });

            // ubytování den N (noc) → snídaně den N+1 (ráno)
            var snidaneDen = String(Number(den) + 1);
            var bunky = dejSnidaneBunky(snidaneDen);
            bunky.forEach(function (td) {
                var realnyCheckbox = td.querySelector('.shopJidlo_checkbox:not(.shopJidlo_checkbox--hotel)');
                var hotelTooltip = td.querySelector('.shopJidlo_hotelTooltip');
                if (!realnyCheckbox) {
                    return;
                }
                if (jeHotel) {
                    realnyCheckbox.checked = false;
                    realnyCheckbox.disabled = true;
                    realnyCheckbox.style.display = 'none';
                    if (hotelTooltip) {
                        hotelTooltip.style.display = '';
                    }
                } else {
                    realnyCheckbox.disabled = false;
                    realnyCheckbox.style.display = '';
                    if (hotelTooltip) {
                        hotelTooltip.style.display = 'none';
                    }
                }
            });
        });
    }

    document.addEventListener('change', function (event) {
        if (event.target.classList.contains('shopUbytovani_radio')) {
            aktualizujSnidane();
        }
    });

    // click-to-deselect v shop-ubytovani.js programově vybere "Žádné" bez change eventu
    document.addEventListener('click', function (event) {
        if (event.target.classList.contains('shopUbytovani_radio')) {
            aktualizujSnidane();
        }
    });

    document.addEventListener('DOMContentLoaded', aktualizujSnidane);
}
