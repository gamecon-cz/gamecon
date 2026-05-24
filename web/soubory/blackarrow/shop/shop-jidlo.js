{
    function dejSnidaneBunky(den) {
        var vsechnyBunky = document.querySelectorAll('td[data-den="' + den + '"]');
        var snidaneBunky = [];
        vsechnyBunky.forEach(function (bunka) {
            if (bunka.dataset.druh && bunka.dataset.druh.toLowerCase().indexOf('snídaně') === 0) {
                snidaneBunky.push(bunka);
            }
        });
        return snidaneBunky;
    }

    function dejSnidaneDny(radio) {
        if (radio.dataset.snidaneDny) {
            return radio.dataset.snidaneDny.split(',').map(function (den) {
                return den.trim();
            }).filter(Boolean);
        }

        var match = radio.name.match(/\[(\d+)]/);
        if (!match) {
            return [];
        }

        return [String(Number(match[1]) + 1)];
    }

    function aktualizujSnidane() {
        var shopUbytovaniRadios = document.querySelectorAll('input.shopUbytovani_radio');
        var spravovaneSnidaneDny = {};
        var hoteloveSnidaneDny = {};
        shopUbytovaniRadios.forEach(function (radio) {
            var snidaneDny = dejSnidaneDny(radio);
            snidaneDny.forEach(function (den) {
                spravovaneSnidaneDny[den] = true;
                if (radio.checked && radio.dataset.podtyp === 'hotel') {
                    hoteloveSnidaneDny[den] = true;
                }
            });
        });

        Object.keys(spravovaneSnidaneDny).forEach(function (snidaneDen) {
            var jeHotel = hoteloveSnidaneDny[snidaneDen] === true;
            var bunky = dejSnidaneBunky(snidaneDen);
            bunky.forEach(function (bunka) {
                var realnyCheckbox = bunka.querySelector('.shopJidlo_checkbox:not(.shopJidlo_checkbox--hotel)');
                var hotelTooltip = bunka.querySelector('.shopJidlo_hotelTooltip');
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
