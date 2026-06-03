/* GameCon static-archive epoch switcher.
 *
 * These per-year sites are frozen static snapshots (no backend, no DB), so the
 * "how did the year end" operational view and the "what was planned" view can
 * only be served as two captured variants of the same page:
 *   X.html       = Po  (post-festival, the default)
 *   X-pred.html  = Před (pre / closest-before festival)
 *
 * Epoch is a GLOBAL mode (both buttons always togglable); it lives in
 * ?epoch=pred|po and is remembered (localStorage) so it carries across
 * navigation. A page may have only the Po variant (data-epoch-pred="none") —
 * then it just keeps its single body under either mode. When Před is selected
 * on a page that DOES have a distinct variant, we fetch the sibling -pred.html
 * and swap its <body> in, keeping this widget.
 *
 * Self-contained, no dependencies; injected verbatim into every archive page.
 */
(function () {
    "use strict";

    var PARAM = "epoch";
    var KEY = "gc-archive-epoch";

    function currentPath() {
        // Path without query/hash, normalized so "/" -> "/index.html".
        var path = location.pathname;
        if (path === "" || path.charAt(path.length - 1) === "/") {
            path += "index.html";
        }
        return path;
    }

    function predVariant(path) {
        return path.replace(/\.html$/, "-pred.html");
    }

    function isPredPath(path) {
        return /-pred\.html$/.test(path);
    }

    // The "Po" (canonical) path for any page, whether we're on a -pred or not.
    function poVariant(path) {
        return path.replace(/-pred\.html$/, ".html");
    }

    function readEpoch() {
        var match = location.search.match(new RegExp("[?&]" + PARAM + "=(pred|po)"));
        if (match) {
            return match[1];
        }
        try {
            return localStorage.getItem(KEY) || "po";
        } catch (error) {
            return "po";
        }
    }

    function rememberEpoch(epoch) {
        try {
            localStorage.setItem(KEY, epoch);
        } catch (error) {
            /* private mode / disabled storage — query param still carries it */
        }
    }

    function urlForEpoch(epoch) {
        var path = poVariant(currentPath());
        return path + "?" + PARAM + "=" + epoch;
    }

    // Epoch is a GLOBAL mode — Před/Po are togglable whenever the archive has a
    // pre-festival snapshot SOMEWHERE. Two distinct "no pred" cases:
    //   - yearHasPred === false  → the WHOLE archive lacks a pre-festival
    //     capture (e.g. a year whose homepage was only ever a rolling feed):
    //     Před is rendered grayed + disabled with an explanatory tooltip.
    //   - this page carries data-epoch-pred="none" but the year HAS pred → the
    //     mode still toggles globally; this page just keeps its single body.
    function buildWidget(epoch, yearHasPred) {
        var box = document.createElement("div");
        box.id = "gc-epoch-switch";
        box.setAttribute("role", "group");
        box.setAttribute("aria-label", "Stav archivu: před nebo po festivalu");

        var clock = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">'
            + '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/>'
            + '<line x1="12" y1="12" x2="12" y2="7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
            + '<line x1="12" y1="12" x2="15.5" y2="13.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
            + "</svg>";

        var predMarkup;
        if (yearHasPred) {
            predMarkup = '<a href="' + urlForEpoch("pred") + '" class="gc-epoch-opt'
                + (epoch === "pred" ? " gc-epoch-active" : "")
                + '" title="Stav webu před festivalem (program, přihlášky)">Před</a>';
        } else {
            predMarkup = '<span class="gc-epoch-opt gc-epoch-disabled" aria-disabled="true"'
                + ' title="Pro tento ročník nebyl zachycen stav webu před festivalem '
                + '(úvodní stránka byla průběžně aktualizovaná).">Před</span>';
        }

        box.innerHTML = clock + predMarkup
            + '<a href="' + urlForEpoch("po") + '" class="gc-epoch-opt'
            + (epoch === "po" ? " gc-epoch-active" : "")
            + '" title="Stav webu po festivalu (výchozí)">Po</a>';

        return box;
    }

    // Replace the page body with the sibling variant's body, keeping the widget.
    function swapToPred(predUrl) {
        fetch(predUrl, { credentials: "same-origin" })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error("no pred variant");
                }
                return response.text();
            })
            .then(function (html) {
                var parsed = new DOMParser().parseFromString(html, "text/html");
                // The fetched variant may carry its own (stale) widget — drop it
                // and keep the live one so state/handlers stay intact.
                var stale = parsed.getElementById("gc-epoch-switch");
                if (stale) {
                    stale.parentNode.removeChild(stale);
                }
                var widget = document.getElementById("gc-epoch-switch");
                document.body.innerHTML = parsed.body.innerHTML;
                if (widget) {
                    document.body.appendChild(widget);
                }
            })
            .catch(function () {
                /* Pre variant missing/unreachable — keep the Po body as-is. The
                   mode stays selected; this page just has no distinct Před. */
            });
    }

    function init() {
        // Does the WHOLE archive have any pre-festival snapshot? Signalled once
        // per year via <html data-epoch-year="po-only"> on every page.
        var yearHasPred = document.documentElement.getAttribute("data-epoch-year") !== "po-only";

        var epoch = readEpoch();
        if (!yearHasPred) {
            epoch = "po"; // no Před anywhere — force the default mode.
        }
        rememberEpoch(epoch);

        var widget = buildWidget(epoch, yearHasPred);
        document.body.appendChild(widget);

        // Does THIS page have a distinct pre-festival body to fetch? Pages that
        // look identical in both epochs carry data-epoch-pred="none" and just
        // keep their single body — the chosen mode still persists.
        var pagePredAvailable = document.documentElement.getAttribute("data-epoch-pred") !== "none";

        // If Před is selected and we're showing the Po body of a page that has a
        // distinct Před, swap it in.
        if (yearHasPred && epoch === "pred" && pagePredAvailable && !isPredPath(currentPath())) {
            swapToPred(predVariant(poVariant(currentPath())));
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
