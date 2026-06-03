/* GameCon static-archive epoch switcher.
 *
 * These per-year sites are frozen static snapshots (no backend, no DB), so the
 * "how did the year end" operational view and the "what was planned" view can
 * only be served as two captured variants of the same page:
 *   X.html       = Po  (post-festival, the default)
 *   X-pred.html  = Před (pre / closest-before festival)
 *
 * Epoch is a GLOBAL mode; it lives in ?epoch=pred|po and is remembered
 * (localStorage) so it carries across navigation. A year may have both epochs,
 * or only one (<html data-epoch-year="po-only"|"pred-only">) — the missing
 * epoch's button is grayed/disabled with a tooltip. A page may have only the Po
 * variant (data-epoch-pred="none") — then it keeps its single body under either
 * mode. When Před is selected on a page that DOES have a distinct variant, we
 * fetch the sibling -pred.html and swap its <body> in, keeping this widget.
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

    // One <html data-epoch-year> attribute per archive describes which epochs
    // the year actually has a captured homepage for:
    //   absent      → both Po and Před exist (index.html = Po, index-pred.html = Před)
    //   "po-only"   → only a post/rolling-feed state was ever captured; Před is
    //                 grayed + disabled with a tooltip.
    //   "pred-only" → only a pre-festival state exists (the site never published
    //                 a post-festival page); Po is grayed + disabled with a
    //                 tooltip, Před is the active default.
    // Each button is a real toggle only when that epoch exists; the missing one
    // renders as a disabled <span> carrying the explanation.
    function buildWidget(epoch, yearMode) {
        var box = document.createElement("div");
        box.id = "gc-epoch-switch";
        box.setAttribute("role", "group");
        box.setAttribute("aria-label", "Stav archivu: před nebo po festivalu");

        var clock = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">'
            + '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/>'
            + '<line x1="12" y1="12" x2="12" y2="7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
            + '<line x1="12" y1="12" x2="15.5" y2="13.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
            + "</svg>";

        function opt(which, label, activeTitle, disabledTitle) {
            var disabled = (which === "pred" && yearMode === "po-only")
                || (which === "po" && yearMode === "pred-only");
            if (disabled) {
                // This epoch wasn't captured — non-clickable, explains why on hover.
                return '<span class="gc-epoch-opt gc-epoch-disabled" aria-disabled="true" title="'
                    + disabledTitle + '">' + label + "</span>";
            }
            if (epoch === which) {
                // Already the current epoch — render as active, not a link
                // (clicking would just reload the same view).
                return '<span class="gc-epoch-opt gc-epoch-active" aria-current="true" title="'
                    + activeTitle + '">' + label + "</span>";
            }
            return '<a href="' + urlForEpoch(which) + '" class="gc-epoch-opt" title="'
                + activeTitle + '">' + label + "</a>";
        }

        box.innerHTML = clock
            + opt("pred", "Před", "Stav webu před festivalem (program, přihlášky)",
                "Pro tento ročník nebyl zachycen stav webu před festivalem.")
            + opt("po", "Po", "Stav webu po festivalu (výchozí)",
                "Pro tento ročník nebyl zachycen stav webu po festivalu "
                + "(úvodní stránka zůstala v předfestivalovém znění).");

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
        // Which epochs does this archive have? Signalled once per year via
        // <html data-epoch-year="po-only"|"pred-only"> (absent = both).
        var yearMode = document.documentElement.getAttribute("data-epoch-year") || "both";

        var epoch = readEpoch();
        // A single-epoch year forces the only epoch it actually has, so the
        // disabled button can never become the active view.
        if (yearMode === "po-only") {
            epoch = "po";
        } else if (yearMode === "pred-only") {
            epoch = "pred";
        }
        rememberEpoch(epoch);

        var widget = buildWidget(epoch, yearMode);
        document.body.appendChild(widget);

        // A pred-only year's single body IS the pre-festival state already, so
        // there's nothing to fetch — only swap when a distinct -pred sibling
        // exists (the "both" case). Pages with data-epoch-pred="none" keep their
        // single body; the chosen mode still persists across navigation.
        var pageHasDistinctPred = yearMode === "both"
            && document.documentElement.getAttribute("data-epoch-pred") !== "none";

        if (epoch === "pred" && pageHasDistinctPred && !isPredPath(currentPath())) {
            swapToPred(predVariant(poVariant(currentPath())));
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
