// nahrání, nahrazení i smazání obrázku nebo souboru načtou stránku znovu a neuložený text editoru by se ztratil
(function () {
    var editor = document.querySelector('form.dbForm');
    if (editor === null) {
        return;
    }
    var obsah = function (formular) {
        return new URLSearchParams(new FormData(formular)).toString();
    };
    window.addEventListener('beforeunload', function (event) {
        // db-form.xtpl drží Uložit zablokované, dokud ukládá, a po chybě ho odblokuje
        var ukladaSe = Array.prototype.some.call(editor.querySelectorAll('[type=submit]'), function (tlacitko) {
            return tlacitko.disabled;
        });
        // výchozí obsah dá reset kopie, hodnoty, které po obnovení stránky vrátil prohlížeč, se tak neberou jako uložené
        var vychozi = editor.cloneNode(true);
        vychozi.reset();
        if (!ukladaSe && obsah(editor) !== obsah(vychozi)) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
})();
