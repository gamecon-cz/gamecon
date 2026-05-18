
<style>

.blok.stranka {
    max-width: unset;
    margin: unset;
}

/* ───────────────────────── TOKENS ───────────────────────── */
:root{
  --cream:#F5EFE6; --cream-2:#EEE6D7;
  --paper:#FFFFFF; --paper-2:#FAF6EE;
  --ink:#1A1916;   --ink-2:#3A3833; --ink-3:#6B675F; --ink-4:#9A958B;
  --red:#E12B33;   --red-2:#C41E26; --red-soft:#FBE0E0; --red-bg:#FFF3F3;
  --peach:#FFC9A8; --peach-soft:#FFE9DA;
  --sand:#F2D9A5;  --sand-soft:#FBEDD0;
  --green:#3F8A4E; --green-soft:#E1F0DC; --green-deep:#2C6438;
  --indigo:#2B3A67; --indigo-soft:#DDE2F2;
  --shadow-hard:4px 4px 0 var(--ink);
  --shadow-hard-sm:3px 3px 0 var(--ink);
  --shadow-hard-lg:6px 6px 0 var(--ink);
  --shadow-soft:0 22px 50px -18px rgba(40,30,20,.30), 0 4px 12px rgba(40,30,20,.08);

  --f-display: 'Helvetica Neue', Helvetica, Arial, system-ui, -apple-system, sans-serif;
  --f-body:    -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Helvetica, Arial, sans-serif;
  --f-mono:    ui-monospace, 'SF Mono', Menlo, Consolas, 'Courier New', monospace;
}

*,*::before,*::after{ box-sizing:border-box; }
html,body{ margin:0; padding:0; }
html{ scroll-behavior:smooth; }
body{
  background:var(--cream);
  color:var(--ink);
  font-family:var(--f-body);
  font-size:15.5px; line-height:1.55;
  -webkit-font-smoothing:antialiased;
  text-rendering:optimizeLegibility;
}
img,svg{ display:block; max-width:100%; }
a{ color:inherit; }
button{ font:inherit; }

/* Subtle paper grain */
body::before{
  content:""; position:fixed; inset:0; pointer-events:none; z-index:0;
  background:
    radial-gradient(rgba(26,25,22,.025) 1px, transparent 1.4px) 0 0 / 4px 4px;
  opacity:.7;
}

/* ───────────────────────── TYPE ───────────────────────── */
.display{
  font-family:var(--f-display);
  font-weight:900;
  letter-spacing:-.025em;
  line-height:1;
  font-stretch:condensed;
}
.eyebrow{
  font-size:11px; font-weight:800;
  letter-spacing:.18em; text-transform:uppercase;
  color:var(--ink-3);
}
.eyebrow .dot{ display:inline-block; width:7px; height:7px; background:var(--red); border-radius:50%; vertical-align:middle; margin-right:8px; transform:translateY(-1px); }
.mono{ font-family:var(--f-mono); font-variant-numeric:tabular-nums; }

::selection{ background:var(--red); color:#fff; }

/* ───────────────────────── NAV BAR ───────────────────────── */
.topbar{
  position:relative; z-index:5;
  background:var(--red); color:#fff;
  border-bottom:2.5px solid var(--ink);
  font-weight:700; letter-spacing:.04em; font-size:13px;
}
.topbar-inner{
  max-width:1280px; margin:0 auto;
  display:flex; align-items:center; gap:28px;
  padding:0 28px; height:56px;
}
.topbar .logo{
  font-family:var(--f-display); font-weight:900;
  background:#fff; color:var(--red);
  padding:5px 11px 4px;
  transform:rotate(-2.5deg);
  letter-spacing:-.03em; font-size:18px;
  border:2px solid var(--ink);
  box-shadow:2px 2px 0 var(--ink);
}
.topbar nav{ display:flex; gap:24px; }
.topbar nav a{ text-decoration:none; opacity:.85; }
.topbar nav a:hover, .topbar nav a.is-here{ opacity:1; text-decoration:underline; text-underline-offset:5px; text-decoration-thickness:2px; }
.topbar .me{ margin-left:auto; display:flex; align-items:center; gap:14px; }
.topbar .me .name{ font-size:13px; opacity:.95; }
.topbar .me .av{
  width:30px; height:30px; border-radius:50%;
  background:var(--ink); color:#fff;
  display:grid; place-items:center;
  font-weight:800; font-size:11.5px;
  border:2px solid #fff; box-shadow:2px 2px 0 var(--ink);
}

/* Breadcrumb under the bar */
.crumb{
  background:var(--cream-2);
  border-bottom:2px solid var(--ink);
  font-size:12px; font-weight:700; letter-spacing:.06em;
  color:var(--ink-2);
}
.crumb-inner{
  max-width:1280px; margin:0 auto;
  padding:10px 28px;
  display:flex; align-items:center; gap:10px;
  text-transform:uppercase;
}
.crumb .sep{ opacity:.4; }
.crumb .here{ color:var(--ink); }

/* ───────────────────────── PAGE WRAP ───────────────────────── */
.wrap{ max-width:1280px; margin:0 auto; padding:0 28px; position:relative; z-index:1; }

/* Section heading shared */
.s-head{ display:flex; align-items:flex-end; justify-content:space-between; gap:24px; margin-bottom:28px; }
.s-head .left{ max-width:780px; }
.s-head h2{
  font-family:var(--f-display); font-weight:900;
  font-size:clamp(34px, 4.4vw, 54px); letter-spacing:-.028em; line-height:1.02;
  margin:.35em 0 .15em;
}
.s-head p{ font-size:16px; color:var(--ink-2); margin:0; max-width:62ch; }
.s-head .right{ display:flex; align-items:center; gap:10px; }

section{ position:relative; }
section + section{ border-top:2.5px solid var(--ink); }

/* ───────────────────────── HERO ───────────────────────── */
.hero{
  background:var(--cream);
  padding:64px 0 88px;
  overflow:hidden;
  position:relative;
}
.hero-inner{
  display:grid; grid-template-columns: 1.05fr .95fr;
  gap:64px; align-items:center;
}
.hero h1{
  font-family:var(--f-display); font-weight:900;
  font-size:clamp(58px, 8vw, 112px);
  letter-spacing:-.035em; line-height:.92;
  margin:.18em 0 .35em;
}
.hero h1 .b{ display:block; }
.hero h1 .red{ color:var(--red); position:relative; display:inline-block; }
.hero h1 .red svg.brush{
  position:absolute; left:-6px; right:-10px; bottom:-8px; width:calc(100% + 22px); height:.32em;
  z-index:-1; opacity:.92;
}
.hero h1 .ink-fill{
  background:var(--ink); color:var(--cream); padding:0 .12em .04em;
  display:inline-block; transform:translateY(-.04em);
}
.hero .lede{
  font-size:19px; color:var(--ink-2);
  max-width:48ch; line-height:1.5;
}
.hero .lede b{ color:var(--ink); font-weight:700; }

.hero-cta{ display:flex; gap:14px; margin-top:28px; flex-wrap:wrap; }
.hero-meta{
  display:flex; gap:24px; margin-top:38px;
  font-size:13px; color:var(--ink-3); font-weight:600;
}
.hero-meta b{ color:var(--ink); display:block; font-family:var(--f-display); font-weight:900; font-size:22px; letter-spacing:-.02em; }

/* Hero right – stacked floating cards */
.hero-stage{
  position:relative; aspect-ratio: 1 / 1.02;
  min-height:520px;
}
.float{
  position:absolute;
  background:var(--paper);
  border:2.5px solid var(--ink);
  box-shadow:var(--shadow-hard-lg);
  font-size:13px;
  transition:transform .35s cubic-bezier(.2,.7,.2,1);
}
.float:hover{ transform:translate(-2px,-2px); }

/* Big team card */
.f-team{
  top:6%; left:0; width:78%;
  padding:18px 18px 16px;
  z-index:3;
}
.f-team .hd{ display:flex; align-items:flex-start; gap:10px; }
.f-team .hd .av{
  width:44px; height:44px; background:var(--red); color:#fff; display:grid; place-items:center;
  font-family:var(--f-display); font-weight:900; font-size:18px;
  border:2px solid var(--ink); box-shadow:2px 2px 0 var(--ink); flex-shrink:0;
}
.f-team .hd .meta{ min-width:0; }
.f-team .hd .ttl{ font-family:var(--f-display); font-weight:900; font-size:19px; letter-spacing:-.015em; line-height:1.1; }
.f-team .hd .sub{ font-size:12px; color:var(--ink-3); margin-top:2px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; }
.f-team .hd .badge{ margin-left:auto; padding:3px 9px; background:var(--green); color:#fff; font-size:10px; font-weight:800; letter-spacing:.12em; text-transform:uppercase; border:1.5px solid var(--ink); }

.f-team .members{ display:flex; flex-direction:column; gap:6px; margin-top:14px; }
.f-team .row{ display:flex; align-items:center; gap:10px; padding:7px 9px; border:1.5px solid var(--ink); background:var(--paper); font-size:12.5px; }
.f-team .row.cap{ background:var(--sand-soft); }
.f-team .row .av2{
  width:24px; height:24px; background:var(--ink); color:#fff; display:grid; place-items:center;
  font-weight:800; font-size:10px;
}
.f-team .row.cap .av2{ background:var(--red); }
.f-team .row .nm{ font-weight:700; }
.f-team .row .nick{ color:var(--ink-3); font-weight:500; }
.f-team .row .tag{ margin-left:auto; font-size:9.5px; font-weight:800; letter-spacing:.12em; text-transform:uppercase; padding:2px 6px; background:var(--red); color:#fff; border:1.5px solid var(--ink); }
.f-team .row.empty{ border-style:dashed; color:var(--ink-3); font-style:italic; background:transparent; }
.f-team .row.empty .av2{ background:transparent; border:1.5px dashed var(--ink-3); color:var(--ink-3); }

/* Activity chip */
.f-activity{
  bottom:18%; right:0; width:62%;
  padding:14px 16px 14px;
  z-index:2;
}
.f-activity .lab{ font-size:10px; font-weight:800; letter-spacing:.18em; text-transform:uppercase; color:var(--ink-3); }
.f-activity .nm{ font-family:var(--f-display); font-weight:900; font-size:22px; letter-spacing:-.02em; line-height:1.05; margin-top:4px; }
.f-activity .slots{ display:flex; gap:6px; flex-wrap:wrap; margin-top:10px; }
.f-activity .slot{ display:inline-flex; align-items:center; gap:6px; padding:3px 8px; border:1.5px solid var(--ink); background:var(--cream); font-size:11.5px; font-weight:700; font-family:var(--f-mono); }
.f-activity .slot::before{ content:""; width:5px; height:5px; border-radius:50%; background:var(--red); }

/* Countdown badge */
.f-count{
  top:42%; right:6%; width:auto;
  padding:12px 14px;
  background:var(--sand-soft);
  z-index:4;
  display:flex; align-items:center; gap:12px;
}
.f-count .ico{
  width:34px; height:34px; background:var(--sand); border:2px solid var(--ink);
  display:grid; place-items:center; font-weight:800;
  box-shadow:2px 2px 0 var(--ink);
}
.f-count .num{ font-family:var(--f-mono); font-size:22px; font-weight:800; color:var(--red-2); letter-spacing:.02em; }
.f-count .lab2{ font-size:10.5px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; color:var(--ink-2); }

/* Code chip */
.f-code{
  bottom:2%; left:8%; width:46%;
  background:var(--ink); color:#fff;
  padding:12px 14px;
  z-index:3;
  display:flex; align-items:center; gap:12px;
}
.f-code .lab{ font-size:10px; font-weight:800; letter-spacing:.16em; text-transform:uppercase; color:var(--ink-4); }
.f-code .val{ font-family:var(--f-mono); font-weight:800; font-size:24px; letter-spacing:.16em; color:#fff; line-height:1; }
.f-code .copy{ margin-left:auto; background:#fff; color:var(--ink); border:2px solid var(--ink); width:32px; height:32px; display:grid; place-items:center; cursor:pointer; box-shadow:2px 2px 0 rgba(0,0,0,.5); }
.f-code .copy:hover{ background:var(--sand); }

/* Pin scribble */
.f-pin{
  top:-2%; right:8%; transform:rotate(8deg);
  background:var(--red); color:#fff; padding:8px 12px;
  font-family:var(--f-display); font-weight:900; font-size:14px; letter-spacing:-.01em;
  border:2.5px solid var(--ink); box-shadow:var(--shadow-hard-sm);
  z-index:5;
}

/* Faint dotted grid behind stage */
.hero-stage::before{
  content:""; position:absolute; inset:-20px; pointer-events:none;
  background:
    radial-gradient(rgba(26,25,22,.18) 1.2px, transparent 1.6px) 0 0 / 14px 14px;
  mask: radial-gradient(ellipse 70% 70% at 55% 45%, #000 30%, transparent 75%);
  opacity:.5;
}

/* Ambient float keyframes */
@keyframes drift1 { 0%,100%{ transform:translateY(0) rotate(0); } 50%{ transform:translateY(-6px) rotate(.4deg); } }
@keyframes drift2 { 0%,100%{ transform:translateY(0) rotate(0); } 50%{ transform:translateY(5px) rotate(-.5deg); } }
@keyframes drift3 { 0%,100%{ transform:translateY(0) rotate(0); } 50%{ transform:translateY(-3px) rotate(.3deg); } }
.hero .f-team{ animation:drift1 9s ease-in-out infinite; }
.hero .f-activity{ animation:drift2 11s ease-in-out infinite; }
.hero .f-count{ animation:drift3 7s ease-in-out infinite; }

/* ───────────────────────── BUTTONS ───────────────────────── */
.btn{
  font-family:var(--f-body);
  font-weight:800; letter-spacing:.04em;
  padding:14px 20px;
  border:2.5px solid var(--ink); background:var(--paper);
  color:var(--ink); cursor:pointer;
  text-transform:uppercase; font-size:12.5px;
  display:inline-flex; align-items:center; gap:10px;
  box-shadow:var(--shadow-hard);
  transition:transform .1s ease, box-shadow .1s ease, background .15s ease;
  text-decoration:none;
}
.btn:hover{ transform:translate(-1px,-1px); box-shadow:5px 5px 0 var(--ink); }
.btn:active{ transform:translate(2px,2px); box-shadow:2px 2px 0 var(--ink); }
.btn.primary{ background:var(--red); color:#fff; }
.btn.dark{ background:var(--ink); color:#fff; }
.btn.ghost{ background:transparent; box-shadow:var(--shadow-hard-sm); }
.btn.ghost:hover{ background:var(--cream-2); }
.btn.success{ background:var(--green); color:#fff; }
.btn.lg{ padding:18px 26px; font-size:13.5px; }
.btn .arr{ font-size:14px; line-height:1; transition:transform .2s ease; }
.btn:hover .arr{ transform:translateX(3px); }
.btn:disabled{ opacity:.4; cursor:not-allowed; transform:none; box-shadow:var(--shadow-hard-sm); }

/* ───────────────────────── INTRO / WHAT IS IT ───────────────────────── */
.intro{ background:var(--paper-2); padding:80px 0; }
.intro-cards{
  display:grid; grid-template-columns:repeat(3, 1fr); gap:22px;
  margin-top:14px;
}
.icard{
  position:relative;
  background:var(--paper);
  border:2.5px solid var(--ink);
  box-shadow:var(--shadow-hard);
  padding:22px 22px 22px;
  transition:transform .18s ease, box-shadow .18s ease;
}
.icard:hover{ transform:translate(-2px,-2px); box-shadow:var(--shadow-hard-lg); }
.icard .num{
  position:absolute; top:-18px; left:-18px;
  width:46px; height:46px; background:var(--red); color:#fff;
  border:2.5px solid var(--ink); box-shadow:var(--shadow-hard-sm);
  display:grid; place-items:center;
  font-family:var(--f-display); font-weight:900; font-size:22px;
  transform:rotate(-4deg);
}
.icard.b .num{ background:var(--ink); }
.icard.c .num{ background:var(--green); }
.icard .illo{
  height:120px; margin:6px 0 18px;
  background:var(--cream); border:1.5px solid var(--ink);
  display:grid; place-items:center;
  position:relative; overflow:hidden;
}
.icard h3{
  font-family:var(--f-display); font-weight:900;
  font-size:22px; letter-spacing:-.02em; margin:0 0 6px;
  line-height:1.1;
}
.icard p{ margin:0; font-size:14.5px; color:var(--ink-2); line-height:1.5; }

/* illustration internals */
.illo-dice{ display:flex; gap:8px; }
.illo-dice .d{ width:36px; height:36px; border:2px solid var(--ink); background:var(--paper); box-shadow:2px 2px 0 var(--ink); position:relative; }
.illo-dice .d.s{ background:var(--red); }
.illo-dice .d span{ position:absolute; width:6px; height:6px; border-radius:50%; background:var(--ink); }
.illo-dice .d.s span{ background:#fff; }

.illo-crown{ display:flex; align-items:center; gap:14px; }
.illo-crown .av{
  width:56px; height:56px; border-radius:50%;
  background:var(--red); color:#fff; display:grid; place-items:center;
  font-family:var(--f-display); font-weight:900; font-size:22px;
  border:2.5px solid var(--ink); box-shadow:3px 3px 0 var(--ink);
  position:relative;
}
.illo-crown .av::after{
  content:""; position:absolute; top:-14px; left:50%; transform:translateX(-50%);
  width:32px; height:14px;
  background:var(--sand);
  border:2px solid var(--ink);
  clip-path:polygon(0 100%, 0 50%, 20% 50%, 30% 0, 40% 50%, 50% 20%, 60% 50%, 70% 0, 80% 50%, 100% 50%, 100% 100%);
}
.illo-crown .chain{ display:flex; flex-direction:column; gap:4px; }
.illo-crown .chain .ln{ height:8px; background:var(--ink-3); width:80px; }
.illo-crown .chain .ln.s{ width:50px; background:var(--ink); }

.illo-code{ display:flex; align-items:center; gap:10px; }
.illo-code .key{
  font-family:var(--f-mono); font-weight:800; font-size:28px;
  background:var(--ink); color:#fff; padding:6px 12px;
  letter-spacing:.18em;
  border:2px solid var(--ink); box-shadow:3px 3px 0 var(--ink);
}
.illo-code .arr2{ font-size:22px; font-weight:900; color:var(--ink); }
.illo-code .door{
  width:42px; height:54px; background:var(--green-soft);
  border:2px solid var(--ink); box-shadow:2px 2px 0 var(--ink);
  position:relative;
}
.illo-code .door::after{
  content:""; position:absolute; top:50%; right:5px; width:6px; height:6px; border-radius:50%; background:var(--ink);
}

/* ───────────────────────── CAPTAIN FLOW ───────────────────────── */
.captain{ background:var(--cream); padding:96px 0 100px; }
.captain .s-head h2 .accent{ color:var(--red); }

.steps-rail{
  position:relative;
  display:grid; grid-template-columns:repeat(5,1fr); gap:0;
  margin:30px 0 14px;
}
.steps-rail::before{
  content:""; position:absolute; left:40px; right:40px; top:32px; height:3px;
  background: repeating-linear-gradient(90deg, var(--ink) 0 8px, transparent 8px 14px);
  z-index:0;
}
.step-btn{
  position:relative; z-index:1;
  display:flex; flex-direction:column; align-items:center; gap:10px;
  background:transparent; border:0; cursor:pointer; padding:0 6px;
  text-align:center;
}
.step-btn .pip{
  width:64px; height:64px;
  background:var(--paper); border:2.5px solid var(--ink);
  display:grid; place-items:center;
  font-family:var(--f-display); font-weight:900; font-size:24px;
  box-shadow:var(--shadow-hard-sm);
  transition:all .15s ease;
}
.step-btn .lab{
  font-size:11.5px; font-weight:800; letter-spacing:.1em; text-transform:uppercase;
  color:var(--ink-2);
  max-width:14ch;
}
.step-btn.is-active .pip{
  background:var(--red); color:#fff;
  transform:translate(-2px,-2px); box-shadow:5px 5px 0 var(--ink);
}
.step-btn.is-active .lab{ color:var(--ink); }
.step-btn.is-done .pip{ background:var(--green); color:#fff; }
.step-btn.is-done .pip::after{ content:"✓"; font-size:24px; line-height:1; }
.step-btn.is-done .pip span{ display:none; }
.step-btn:hover:not(.is-active) .pip{ transform:translate(-1px,-1px); box-shadow:4px 4px 0 var(--ink); }

/* Step viewer (two-column: explanation + mock) */
.step-view{
  margin-top:38px;
  background:var(--paper);
  border:2.5px solid var(--ink); box-shadow:var(--shadow-hard-lg);
  display:grid; grid-template-columns: .85fr 1.15fr;
  min-height:460px;
  overflow:hidden;
}
.step-info{
  padding:36px 36px 32px;
  display:flex; flex-direction:column;
  border-right:2.5px solid var(--ink);
  background:var(--paper);
  position:relative;
}
.step-info .kicker{ font-size:11px; font-weight:800; letter-spacing:.18em; text-transform:uppercase; color:var(--red); }
.step-info h3{ font-family:var(--f-display); font-weight:900; font-size:38px; letter-spacing:-.025em; line-height:1.02; margin:.3em 0 .25em; }
.step-info p{ font-size:15.5px; color:var(--ink-2); margin:0 0 14px; }
.step-info ul{ list-style:none; padding:0; margin:6px 0 0; display:flex; flex-direction:column; gap:8px; }
.step-info li{ display:flex; gap:10px; align-items:flex-start; font-size:14px; }
.step-info li .ck{ flex-shrink:0; width:20px; height:20px; background:var(--green); color:#fff; display:grid; place-items:center; font-weight:900; font-size:12px; border:1.5px solid var(--ink); }
.step-info .nav-row{ margin-top:auto; padding-top:24px; display:flex; gap:10px; justify-content:flex-end; }

.step-mock{
  background:var(--paper-2);
  padding:30px;
  position:relative;
  display:grid; place-items:center;
}
.step-mock::before{
  content:""; position:absolute; inset:0;
  background:
    radial-gradient(rgba(26,25,22,.13) 1px, transparent 1.3px) 0 0 / 10px 10px;
  opacity:.65; pointer-events:none;
}

/* Mock window chrome – mini modals */
.mini-modal{
  position:relative; z-index:1;
  width:100%; max-width:420px;
  background:var(--paper); border:2.5px solid var(--ink);
  box-shadow:var(--shadow-hard-lg);
  font-size:13px; line-height:1.45;
}
.mini-modal .mh{
  padding:12px 14px 10px; border-bottom:2px solid var(--ink); position:relative;
}
.mini-modal .mh .eb{ font-size:9.5px; font-weight:800; letter-spacing:.16em; text-transform:uppercase; color:var(--ink-3); }
.mini-modal .mh .tt{ font-family:var(--f-display); font-weight:900; font-size:18px; letter-spacing:-.015em; margin-top:2px; }
.mini-modal .mh .x{ position:absolute; top:8px; right:10px; width:24px; height:24px; border:1.5px solid var(--ink); background:var(--paper); display:grid; place-items:center; font-size:12px; }
.mini-modal .mb{ padding:14px; }
.mini-modal .mf{ padding:10px 14px; border-top:2px solid var(--ink); background:var(--paper-2); display:flex; justify-content:flex-end; gap:8px; }

/* small btn in mock */
.mbtn{
  border:2px solid var(--ink); background:var(--paper);
  padding:8px 12px; font-size:11px; font-weight:800; letter-spacing:.04em;
  text-transform:uppercase; box-shadow:2px 2px 0 var(--ink); cursor:default;
  display:inline-flex; align-items:center; gap:6px;
}
.mbtn.p{ background:var(--red); color:#fff; }
.mbtn.d{ background:var(--ink); color:#fff; }
.mbtn.s{ background:var(--green); color:#fff; }
.mbtn.full{ width:100%; justify-content:center; }
.mbtn.lg{ padding:11px 14px; font-size:12px; }

/* annotation arrow over mock */
.annot{
  position:absolute; z-index:3;
  background:var(--ink); color:#fff;
  font-size:11.5px; font-weight:700;
  padding:6px 10px;
  font-family:var(--f-mono); letter-spacing:.02em;
  white-space:nowrap;
}
.annot::after{
  content:""; position:absolute;
  border:6px solid transparent;
}
.annot.tl{ top:14px; left:14px; }
.annot.tr{ top:14px; right:14px; }
.annot.br{ bottom:14px; right:14px; }

/* step indicator dots inside the mock */
.mini-modal .mini-slots{ display:flex; flex-direction:column; gap:6px; margin-top:10px; }
.mini-modal .mini-slot{
  display:flex; align-items:center; gap:8px;
  padding:8px 10px; border:1.5px solid var(--ink); background:var(--paper);
  font-size:12px; font-weight:600;
}
.mini-modal .mini-slot.sel{ background:var(--green-soft); box-shadow:3px 3px 0 var(--ink); transform:translate(-1px,-1px); }
.mini-modal .mini-slot .dot{ width:14px; height:14px; border:2px solid var(--ink); border-radius:50%; background:var(--paper); flex-shrink:0; display:grid; place-items:center; }
.mini-modal .mini-slot.sel .dot{ background:var(--green); }
.mini-modal .mini-slot.sel .dot::after{ content:""; width:5px; height:5px; background:#fff; border-radius:50%; }
.mini-modal .mini-slot .dy{ width:24px; font-weight:800; font-size:11px; letter-spacing:.06em; text-transform:uppercase; }
.mini-modal .mini-slot .tm{ font-family:var(--f-mono); }
.mini-modal .mini-slot .meta2{ margin-left:auto; font-size:10px; color:var(--ink-3); font-weight:700; letter-spacing:.06em; text-transform:uppercase; }

.mini-modal .mini-mem{ display:flex; flex-direction:column; gap:6px; }
.mini-modal .mini-mem .r{
  display:flex; align-items:center; gap:8px; padding:7px 9px;
  border:1.5px solid var(--ink); background:var(--paper); font-size:12px;
}
.mini-modal .mini-mem .r.cap{ background:var(--sand-soft); }
.mini-modal .mini-mem .a{ width:22px; height:22px; background:var(--ink); color:#fff; display:grid; place-items:center; font-weight:800; font-size:9.5px; }
.mini-modal .mini-mem .r.cap .a{ background:var(--red); }
.mini-modal .mini-mem .b{ font-weight:700; }
.mini-modal .mini-mem .t{ margin-left:auto; font-size:9px; font-weight:800; padding:1.5px 5px; background:var(--red); color:#fff; letter-spacing:.1em; }

/* warning blocks reused in mocks */
.mini-alert{ display:flex; gap:8px; padding:9px 10px; border:1.5px solid var(--ink); margin-bottom:10px; }
.mini-alert .i{ width:22px; height:22px; border:1.5px solid var(--ink); display:grid; place-items:center; font-weight:800; font-size:11px; flex-shrink:0; }
.mini-alert.warn{ background:var(--sand-soft); }
.mini-alert.warn .i{ background:var(--sand); }
.mini-alert.danger{ background:var(--red-bg); border-color:var(--red-2); }
.mini-alert.danger .i{ background:var(--red); color:#fff; border-color:var(--red-2); }
.mini-alert.ok{ background:var(--green-soft); }
.mini-alert.ok .i{ background:var(--green); color:#fff; }
.mini-alert .ttl{ font-weight:800; font-size:12px; }
.mini-alert .dsc{ font-size:11px; color:var(--ink-2); line-height:1.45; margin-top:1px; }
.mini-alert .cd{ color:var(--red); font-weight:800; font-family:var(--f-mono); }

/* Advanced collapsible bar */
.advanced{
  margin-top:28px;
  background:var(--paper);
  border:2.5px solid var(--ink); box-shadow:var(--shadow-hard-sm);
}
.advanced + .advanced{ margin-top:14px; }
.adv-head{
  display:flex; align-items:center; gap:14px; padding:18px 22px;
  cursor:pointer; user-select:none;
}
.adv-head .tag{
  font-size:10px; font-weight:800; letter-spacing:.14em; text-transform:uppercase;
  padding:4px 8px; border:1.5px solid var(--ink); background:var(--sand);
}
.adv-head.danger .tag{ background:var(--red); color:#fff; border-color:var(--red-2); }
.adv-head h4{ margin:0; font-family:var(--f-display); font-weight:900; font-size:20px; letter-spacing:-.015em; }
.adv-head .sub{ font-size:13px; color:var(--ink-3); margin-left:auto; padding-right:14px; }
.adv-head .chev{ width:28px; height:28px; border:2px solid var(--ink); background:var(--paper); display:grid; place-items:center; font-weight:800; transition:transform .2s ease; }
.advanced.open .adv-head .chev{ transform:rotate(45deg); background:var(--ink); color:#fff; }
.adv-body{
  display:none; padding:0 22px 22px;
  border-top:1.5px solid var(--ink);
}
.advanced.open .adv-body{ display:block; }
.adv-body p{ font-size:14px; color:var(--ink-2); margin:14px 0 0; }
.adv-grid{
  display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:18px; align-items:start;
}
.adv-grid .copy h5{
  font-family:var(--f-display); font-weight:900; font-size:17px; letter-spacing:-.01em; margin:0 0 6px;
}
.adv-grid .copy p{ margin:0 0 10px; font-size:13.5px; color:var(--ink-2); }
.adv-grid .copy ul{ list-style:none; padding:0; margin:8px 0 0; display:flex; flex-direction:column; gap:6px; font-size:13px; }
.adv-grid .copy li{ display:flex; gap:8px; align-items:flex-start; }
.adv-grid .copy li::before{ content:""; flex-shrink:0; width:7px; height:7px; background:var(--ink); margin-top:8px; }

.adv-grid .demo{
  background:var(--paper-2); border:1.5px solid var(--ink); padding:16px;
}

/* Temporary state callout */
.draft-callout{
  margin-top:42px;
  background:var(--ink); color:#fff;
  border:2.5px solid var(--ink); box-shadow:var(--shadow-hard-lg);
  padding:28px 32px;
  display:grid; grid-template-columns: 1fr auto; gap:32px; align-items:center;
}
.draft-callout .eb{ font-size:11px; font-weight:800; letter-spacing:.18em; text-transform:uppercase; color:var(--peach); }
.draft-callout h4{ font-family:var(--f-display); font-weight:900; font-size:30px; letter-spacing:-.02em; margin:.2em 0 .25em; line-height:1.05; }
.draft-callout p{ margin:0; font-size:14.5px; color:#d6d3cd; max-width:60ch; }
.draft-callout .meter{
  display:flex; align-items:center; gap:18px; padding:14px 20px;
  background:var(--cream); color:var(--ink); border:2.5px solid var(--cream);
  box-shadow:4px 4px 0 var(--red);
  font-family:var(--f-display); font-weight:900;
}
.draft-callout .meter .big{ font-size:42px; letter-spacing:-.02em; line-height:1; }
.draft-callout .meter .lbl{ font-size:10.5px; font-weight:800; letter-spacing:.16em; text-transform:uppercase; color:var(--red-2); }
.draft-callout .meter .lbl2{ font-size:11px; font-weight:700; color:var(--ink-3); margin-top:2px; font-family:var(--f-body); letter-spacing:.04em; text-transform:none; }

/* ───────────────────────── MANAGEMENT ───────────────────────── */
.manage{ background:var(--paper-2); padding:96px 0; position:relative; }
.manage-grid{
  display:grid; grid-template-columns: 1.2fr 1fr; gap:48px; align-items:start;
  margin-top:18px;
}

/* annotated team mock */
.team-screen{
  position:relative;
  background:var(--paper); border:2.5px solid var(--ink); box-shadow:var(--shadow-hard-lg);
  padding:24px 26px 22px;
}
.team-screen .ts-eb{ font-size:11px; font-weight:800; letter-spacing:.16em; text-transform:uppercase; color:var(--ink-3); }
.team-screen .ts-ttl{ font-family:var(--f-display); font-weight:900; font-size:26px; letter-spacing:-.02em; line-height:1.05; margin:.2em 0 6px; }
.team-screen .ts-sub{ display:flex; flex-wrap:wrap; gap:6px; }
.team-screen .ts-sub .slot{ display:inline-flex; align-items:center; gap:6px; padding:3px 8px; border:1.5px solid var(--ink); background:var(--cream); font-size:11.5px; font-weight:700; font-family:var(--f-mono); }
.team-screen .ts-sub .slot::before{ content:""; width:5px; height:5px; border-radius:50%; background:var(--red); }

.ts-section{ margin-top:18px; }
.ts-section .lbl{ font-size:10.5px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; color:var(--ink-3); margin-bottom:8px; display:flex; align-items:center; gap:8px; }
.ts-section .lbl::after{ content:""; flex:1; height:1.5px; background:var(--ink); opacity:.15; }

.ts-name{ display:grid; grid-template-columns:1fr auto auto; gap:8px; }
.ts-name input{
  border:2px solid var(--ink); background:var(--paper); padding:9px 12px;
  font:inherit; font-weight:700; color:var(--ink);
}
.ts-name input:focus{ outline:none; box-shadow:3px 3px 0 var(--red); border-color:var(--red); }
.ts-name .ibtn{ width:38px; height:auto; border:2px solid var(--ink); background:var(--paper); display:grid; place-items:center; box-shadow:2px 2px 0 var(--ink); cursor:pointer; }
.ts-name .ibtn:hover{ background:var(--sand-soft); }

.ts-code{
  display:flex; align-items:center; gap:14px; padding:12px 14px;
  background:var(--cream); border:2px solid var(--ink);
}
.ts-code .lab{ font-size:10px; font-weight:800; letter-spacing:.16em; text-transform:uppercase; color:var(--ink-3); }
.ts-code .val{ font-family:var(--f-mono); font-weight:800; font-size:26px; letter-spacing:.18em; line-height:1; }
.ts-code .right{ margin-left:auto; display:flex; gap:6px; }

.ts-vis{ display:flex; gap:8px; }
.ts-vis .pill{ flex:1; padding:9px 12px; border:2px solid var(--ink); background:var(--paper); font-weight:800; font-size:11.5px; letter-spacing:.04em; text-align:center; cursor:pointer; text-transform:uppercase; }
.ts-vis .pill.on{ background:var(--ink); color:#fff; }

.ts-members{ display:flex; flex-direction:column; gap:6px; }
.ts-members .m{
  display:flex; align-items:center; gap:10px;
  padding:9px 12px; border:1.5px solid var(--ink); background:var(--paper);
  font-size:13px;
}
.ts-members .m.cap{ background:var(--sand-soft); }
.ts-members .m.empty{ background:transparent; border-style:dashed; color:var(--ink-3); font-style:italic; }
.ts-members .m .a{ width:28px; height:28px; background:var(--ink); color:#fff; display:grid; place-items:center; font-weight:800; font-size:11px; }
.ts-members .m.cap .a{ background:var(--red); }
.ts-members .m.empty .a{ background:transparent; border:1.5px dashed var(--ink-3); color:var(--ink-3); }
.ts-members .m .nm{ font-weight:700; }
.ts-members .m .nk{ color:var(--ink-3); font-weight:500; }
.ts-members .m .bg{ margin-left:auto; padding:2px 7px; background:var(--red); color:#fff; font-weight:800; font-size:9.5px; letter-spacing:.12em; text-transform:uppercase; }

/* Lock button highlight */
.ts-lock{
  margin-top:18px;
  width:100%;
  padding:18px 22px;
  border:2.5px solid var(--ink); background:var(--green); color:#fff;
  box-shadow:var(--shadow-hard);
  font-family:var(--f-display); font-weight:900; font-size:18px; letter-spacing:-.01em;
  display:flex; align-items:center; justify-content:center; gap:12px;
  cursor:pointer;
  position:relative;
  text-transform:none;
}
.ts-lock:hover{ transform:translate(-1px,-1px); box-shadow:5px 5px 0 var(--ink); }
.ts-lock .key{ font-size:22px; }

/* Annotation callouts pointing at the screen */
.callout{
  position:absolute;
  display:flex; align-items:flex-start; gap:10px;
  background:var(--ink); color:#fff;
  padding:10px 14px;
  box-shadow:var(--shadow-hard-sm);
  font-size:12.5px; line-height:1.4;
  z-index:3;
  max-width:230px;
}
.callout .pn{ flex-shrink:0; width:22px; height:22px; background:#fff; color:var(--ink); display:grid; place-items:center; font-family:var(--f-display); font-weight:900; font-size:13px; }
.callout::after{ content:""; position:absolute; border:7px solid transparent; }
.callout.right{ }
.callout.right::after{ left:-14px; top:14px; border-right-color:var(--ink); }
.callout.left::after{ right:-14px; top:14px; border-left-color:var(--ink); }
.callout.bottom::after{ left:18px; bottom:-14px; border-top-color:var(--ink); }
.callout.red{ background:var(--red); }
.callout.red.right::after{ border-right-color:var(--red); }
.callout.red.left::after{ border-left-color:var(--red); }

/* Right column: lock spotlight */
.lock-card{
  background:var(--ink); color:#fff;
  border:2.5px solid var(--ink); box-shadow:var(--shadow-hard-lg);
  padding:32px 32px 28px;
  position:relative;
}
.lock-card .eb{ font-size:11px; font-weight:800; letter-spacing:.18em; text-transform:uppercase; color:var(--peach); }
.lock-card h3{ font-family:var(--f-display); font-weight:900; font-size:38px; letter-spacing:-.025em; line-height:1.02; margin:.25em 0 .2em; }
.lock-card .lede{ font-size:15px; color:#d6d3cd; margin:0 0 18px; max-width:42ch; line-height:1.55; }
.lock-card .stamp{
  position:absolute; top:24px; right:24px;
  border:3px solid var(--red); color:var(--red); font-family:var(--f-display); font-weight:900;
  font-size:13px; letter-spacing:.12em; text-transform:uppercase; padding:5px 10px;
  transform:rotate(-8deg); background:transparent;
}
.lock-card .lock-ico{
  display:flex; align-items:center; gap:14px; margin-bottom:16px;
}
.lock-card .lock-ico .key2{
  width:54px; height:54px; background:var(--green); color:#fff; display:grid; place-items:center;
  font-size:30px;
  border:2.5px solid var(--cream); box-shadow:4px 4px 0 var(--red);
}
.lock-card .lock-ico .ttl{ font-family:var(--f-display); font-weight:900; font-size:22px; letter-spacing:-.015em; line-height:1; }
.lock-card .lock-ico .sb{ font-size:12px; color:var(--peach); margin-top:4px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }

.lock-list{ list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px; }
.lock-list li{
  display:flex; align-items:center; gap:12px;
  padding:11px 14px; background:rgba(255,255,255,.06);
  border:1.5px solid rgba(255,255,255,.18);
  font-size:13.5px;
}
.lock-list li .x{ width:22px; height:22px; background:var(--red); color:#fff; display:grid; place-items:center; font-weight:900; font-size:13px; border:1.5px solid var(--cream); flex-shrink:0; }

.lock-warn{
  margin-top:18px; padding:14px 16px;
  background:var(--sand-soft); color:var(--ink);
  border:2px solid var(--sand);
  font-size:13px;
  display:flex; gap:12px;
}
.lock-warn b{ font-weight:800; }

/* Manage features small grid */
.feat-grid{ display:grid; grid-template-columns:repeat(2,1fr); gap:14px; margin-top:24px; }
.feat{
  padding:16px 18px; background:var(--paper); border:2px solid var(--ink);
  display:flex; flex-direction:column; gap:6px;
}
.feat .num{ font-family:var(--f-mono); font-size:11px; font-weight:800; color:var(--ink-3); letter-spacing:.08em; }
.feat h5{ margin:0; font-family:var(--f-display); font-weight:900; font-size:16px; letter-spacing:-.01em; }
.feat p{ margin:0; font-size:13px; color:var(--ink-2); }

/* ───────────────────────── STATES ───────────────────────── */
.states{ background:var(--cream); padding:96px 0; }
.states-grid{
  display:grid; grid-template-columns:repeat(4,1fr); gap:18px; margin-top:14px;
}
.scard{
  position:relative;
  background:var(--paper); border:2.5px solid var(--ink);
  box-shadow:var(--shadow-hard);
  padding:22px 22px 22px;
  display:flex; flex-direction:column; gap:14px;
  transition:transform .18s ease, box-shadow .18s ease;
}
.scard:hover{ transform:translate(-2px,-2px); box-shadow:var(--shadow-hard-lg); }
.scard .badge{
  align-self:flex-start;
  font-size:10px; font-weight:800; letter-spacing:.16em; text-transform:uppercase;
  padding:4px 9px; border:1.5px solid var(--ink); background:var(--cream);
}
.scard.draft .badge{ background:var(--sand); }
.scard.ready .badge{ background:var(--green-soft); }
.scard.public .badge{ background:var(--peach-soft); }
.scard.locked .badge{ background:var(--ink); color:#fff; border-color:var(--ink); }

.scard h3{ font-family:var(--f-display); font-weight:900; font-size:24px; letter-spacing:-.02em; line-height:1.05; margin:0; }
.scard p{ margin:0; font-size:13.5px; color:var(--ink-2); line-height:1.5; }

.scard .visual{
  height:120px; background:var(--cream); border:1.5px solid var(--ink);
  position:relative; overflow:hidden;
  display:grid; place-items:center;
}

/* draft visual: ticking countdown */
.viz-draft{
  background:var(--sand-soft);
  display:flex; align-items:center; justify-content:center; gap:10px;
}
.viz-draft .clk{
  width:50px; height:50px; border:2.5px solid var(--ink); border-radius:50%;
  background:var(--paper); position:relative;
  box-shadow:3px 3px 0 var(--ink);
}
.viz-draft .clk::before, .viz-draft .clk::after{
  content:""; position:absolute; left:50%; top:50%; background:var(--ink); transform-origin:center bottom;
}
.viz-draft .clk::before{ width:2.5px; height:18px; transform:translate(-50%,-100%) rotate(0deg); animation:hand 4s linear infinite; }
.viz-draft .clk::after{ width:2px; height:14px; transform:translate(-50%,-100%) rotate(180deg); animation:hand2 24s linear infinite; }
@keyframes hand{ to{ transform:translate(-50%,-100%) rotate(360deg); } }
@keyframes hand2{ to{ transform:translate(-50%,-100%) rotate(540deg); } }
.viz-draft .cd{ font-family:var(--f-mono); font-weight:800; font-size:18px; color:var(--red-2); }

/* ready visual: green check + slot dots */
.viz-ready{ background:var(--green-soft); flex-direction:column; gap:6px; display:flex; }
.viz-ready .dots{ display:flex; gap:6px; }
.viz-ready .dt{ width:14px; height:14px; border:2px solid var(--ink); background:var(--paper); }
.viz-ready .dt.f{ background:var(--red); }
.viz-ready .dt.c{ background:var(--ink); }
.viz-ready .lab{ font-family:var(--f-mono); font-size:11px; font-weight:800; color:var(--green-deep); }

/* public visual: door open */
.viz-public{ background:var(--peach-soft); }
.viz-public .ico{ font-family:var(--f-display); font-weight:900; font-size:50px; color:var(--ink); position:relative; }
.viz-public .ico::after{
  content:""; position:absolute; left:-22px; top:50%; width:18px; height:2px; background:var(--ink);
  box-shadow:0 -10px 0 var(--ink), 0 10px 0 var(--ink);
}

/* locked visual: chunky lock */
.viz-locked{ background:var(--ink); color:#fff; }
.viz-locked .lk{ display:flex; align-items:center; gap:10px; }
.viz-locked .lk .l{
  width:48px; height:48px; background:var(--green); border:2.5px solid var(--cream);
  display:grid; place-items:center; font-size:24px;
  box-shadow:3px 3px 0 var(--red);
}
.viz-locked .lk .t{ font-family:var(--f-display); font-weight:900; font-size:13px; letter-spacing:.04em; text-transform:uppercase; color:var(--peach); }
.viz-locked .lk .t b{ display:block; color:#fff; font-size:18px; letter-spacing:-.01em; text-transform:none; margin-top:2px; }

.scard .meta-row{
  display:flex; align-items:center; gap:8px; margin-top:auto;
  font-size:11.5px; font-weight:700; color:var(--ink-3); letter-spacing:.04em;
}
.scard .meta-row .dt{ width:8px; height:8px; border-radius:50%; background:var(--ink); }
.scard.draft .meta-row .dt{ background:var(--red); animation:blink 1.4s ease-in-out infinite; }
.scard.ready .meta-row .dt{ background:var(--green); }
.scard.public .meta-row .dt{ background:var(--peach); border:1.5px solid var(--ink); }
.scard.locked .meta-row .dt{ background:var(--ink); }
@keyframes blink{ 50%{ opacity:.3; } }

/* ───────────────────────── JOIN ───────────────────────── */
.join{ background:var(--paper); padding:96px 0; }
.join-cols{
  display:grid; grid-template-columns: 1fr 1fr; gap:24px; margin-top:14px;
}
.join-card{
  background:var(--paper); border:2.5px solid var(--ink); box-shadow:var(--shadow-hard);
  padding:32px 32px 28px;
  position:relative;
  display:flex; flex-direction:column;
}
.join-card.alt{ background:var(--cream); }
.join-card h3{ font-family:var(--f-display); font-weight:900; font-size:30px; letter-spacing:-.025em; margin:.2em 0 6px; line-height:1.05; }
.join-card .eb{ font-size:11px; font-weight:800; letter-spacing:.18em; text-transform:uppercase; color:var(--red); }
.join-card .lede{ font-size:14.5px; color:var(--ink-2); margin:0 0 22px; }
.join-card .stamp{
  position:absolute; top:18px; right:20px;
  font-family:var(--f-display); font-weight:900; font-size:54px; color:rgba(26,25,22,.06);
  letter-spacing:-.04em; line-height:1;
}

.join-input{
  display:grid; grid-template-columns:1fr auto; gap:10px; align-items:stretch;
  margin-bottom:18px;
}
.join-input .field{
  border:2.5px solid var(--ink); background:var(--paper);
  font-family:var(--f-mono); letter-spacing:.5em;
  text-align:center; font-size:30px; font-weight:800;
  padding:14px 10px;
  color:var(--ink);
  font-variant-numeric:tabular-nums;
}
.join-input .field:focus{ outline:none; box-shadow:4px 4px 0 var(--red); border-color:var(--red); }

.join-list{ display:flex; flex-direction:column; gap:8px; margin:6px 0 18px; }
.join-list .row{
  display:flex; align-items:center; gap:10px; padding:11px 14px;
  border:1.5px solid var(--ink); background:var(--paper);
  font-size:13.5px;
}
.join-list .row .nm{ font-weight:800; }
.join-list .row .ct{ font-family:var(--f-mono); font-weight:600; color:var(--ink-2); }
.join-list .row .v{ font-size:9.5px; font-weight:800; letter-spacing:.12em; text-transform:uppercase; padding:2px 6px; border:1.5px solid var(--ink); background:var(--green-soft); }
.join-list .row .sp{ flex:1; }
.join-list .row .jn{ font-size:10.5px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; padding:6px 9px; border:1.5px solid var(--ink); background:var(--paper); box-shadow:2px 2px 0 var(--ink); cursor:pointer; }
.join-list .row .jn:hover{ background:var(--green); color:#fff; }

.join-tip{ margin-top:auto; font-size:12.5px; color:var(--ink-3); display:flex; gap:8px; align-items:flex-start; padding-top:14px; border-top:1.5px dashed var(--ink-3); }
.join-tip b{ color:var(--ink); }

.join-done{
  margin-top:36px; padding:24px 28px;
  background:var(--green); color:#fff;
  border:2.5px solid var(--ink); box-shadow:var(--shadow-hard-lg);
  display:flex; align-items:center; gap:18px;
}
.join-done .big{
  width:54px; height:54px; background:#fff; color:var(--green-deep);
  display:grid; place-items:center;
  font-family:var(--f-display); font-weight:900; font-size:30px;
  border:2.5px solid var(--ink); box-shadow:3px 3px 0 var(--ink);
}
.join-done h4{ font-family:var(--f-display); font-weight:900; font-size:24px; letter-spacing:-.02em; margin:0; line-height:1.05; }
.join-done p{ margin:4px 0 0; font-size:13.5px; opacity:.95; }

/* ───────────────────────── FAQ ───────────────────────── */
.faq{ background:var(--paper-2); padding:96px 0 110px; }
.faq-list{ margin-top:18px; display:flex; flex-direction:column; gap:10px; }
.qa{
  background:var(--paper); border:2.5px solid var(--ink); box-shadow:var(--shadow-hard-sm);
}
.qa-q{
  display:flex; align-items:center; gap:18px;
  padding:18px 22px; cursor:pointer; user-select:none;
}
.qa-q .n{ font-family:var(--f-mono); font-size:12px; font-weight:800; color:var(--ink-3); letter-spacing:.08em; min-width:32px; }
.qa-q h4{ margin:0; font-family:var(--f-display); font-weight:900; font-size:18px; letter-spacing:-.01em; flex:1; }
.qa-q .chev{ width:26px; height:26px; border:2px solid var(--ink); display:grid; place-items:center; font-weight:800; transition:transform .2s ease; }
.qa.open .qa-q .chev{ transform:rotate(45deg); background:var(--ink); color:#fff; }
.qa-a{ display:none; padding:0 22px 22px 72px; font-size:14.5px; color:var(--ink-2); line-height:1.55; }
.qa-a p{ margin:0 0 8px; }
.qa.open .qa-a{ display:block; }

/* ───────────────────────── FOOTER ───────────────────────── */
footer{
  background:var(--ink); color:var(--cream);
  padding:42px 0 36px;
  border-top:2.5px solid var(--ink);
}
footer .row{
  display:flex; align-items:center; gap:32px; flex-wrap:wrap;
  padding:0 28px; max-width:1280px; margin:0 auto;
}
footer .logo{
  font-family:var(--f-display); font-weight:900;
  background:var(--cream); color:var(--ink);
  padding:5px 11px 4px; transform:rotate(-2deg);
  letter-spacing:-.03em; font-size:18px;
  border:2px solid var(--cream);
}
footer .links{ display:flex; gap:22px; font-size:13px; font-weight:700; letter-spacing:.04em; }
footer .links a{ color:#d6d3cd; text-decoration:none; }
footer .links a:hover{ color:#fff; text-decoration:underline; text-underline-offset:4px; }
footer .meta{ margin-left:auto; font-size:12px; color:#9a958b; font-family:var(--f-mono); }

/* ───────────────────────── RESPONSIVE ───────────────────────── */
@media (max-width: 1100px){
  .hero-inner{ grid-template-columns:1fr; gap:36px; }
  .hero-stage{ min-height:480px; max-width:560px; }
  .intro-cards{ grid-template-columns:1fr; gap:32px; }
  .step-view{ grid-template-columns:1fr; }
  .step-info{ border-right:0; border-bottom:2.5px solid var(--ink); }
  .manage-grid{ grid-template-columns:1fr; }
  .states-grid{ grid-template-columns:repeat(2,1fr); }
  .join-cols{ grid-template-columns:1fr; }
  .draft-callout{ grid-template-columns:1fr; }
  .callout{ display:none; }
}
@media (max-width: 720px){
  .topbar nav{ display:none; }
  .crumb-inner{ font-size:11px; }
  .hero{ padding:40px 0 60px; }
  .hero h1{ font-size:clamp(46px, 12vw, 78px); }
  .hero-meta{ gap:18px; flex-wrap:wrap; }
  .s-head h2{ font-size:36px; }
  .steps-rail{ grid-template-columns:repeat(5,1fr); }
  .step-btn .pip{ width:48px; height:48px; font-size:18px; }
  .step-btn .lab{ font-size:9.5px; }
  .steps-rail::before{ left:24px; right:24px; top:24px; }
  .states-grid{ grid-template-columns:1fr; }
  .feat-grid{ grid-template-columns:1fr; }
  .wrap{ padding:0 18px; }
  .topbar-inner, .crumb-inner, footer .row{ padding-left:18px; padding-right:18px; }
}
</style>



<!-- HERO -->
<section class="hero" id="hero">
  <div class="wrap hero-inner">
    <div class="hero-left">
      <div class="eyebrow"><span class="dot"></span>Průvodce · čtení na 3 minuty</div>
      <h1 class="display">
        <span class="b">Sestav</span>
        <span class="b"><span class="red">družinu<svg class="brush" viewBox="0 0 220 12" preserveAspectRatio="none" aria-hidden="true"><path d="M2,6 C40,1 90,11 140,5 C170,2 195,9 218,4" stroke="#E12B33" stroke-width="6" stroke-linecap="round" fill="none"/></svg></span>,</span>
        <span class="b">jdi na <span class="ink-fill">výpravu.</span></span>
      </h1>
      <p class="lede">Některé aktivity na GameConu se hrají v týmu. <b>Kapitán</b> založí družinu, vybere termíny a pozve spoluhráče kódem. Tady je celý postup &mdash; od přihlášky až po zamčený tým, který už nikdo nerozhodí.</p>
      <div class="hero-cta">
        <a href="#captain" class="btn primary lg">Vést družinu <span class="arr">→</span></a>
        <a href="#join" class="btn lg">Připojit se kódem</a>
      </div>
      <div class="hero-meta">
        <div><b>2–6</b>hráčů v týmu</div>
        <div><b>15–30 min</b>na rozpracovaný tým</div>
        <div><b>72 h</b>do automatického otevření</div>
      </div>
    </div>

    <div class="hero-stage" aria-hidden="true">
      <div class="float f-pin">Družina č. 562</div>

      <div class="float f-team">
        <div class="hd">
          <div class="av">OB</div>
          <div class="meta">
            <div class="ttl">Orkové na Brigádě</div>
            <div class="sub">Lords of Ragnarok · čt 9–12</div>
          </div>
          <span class="badge">Připraveno</span>
        </div>
        <div class="members">
          <div class="row cap">
            <span class="av2">AE</span>
            <span class="nm">Aragorn</span>
            <span class="nick">„Strider" Elessar</span>
            <span class="tag">Kapitán</span>
          </div>
          <div class="row">
            <span class="av2">GM</span>
            <span class="nm">Gandalf</span>
            <span class="nick">„The Grey" Mithrandir</span>
          </div>
          <div class="row">
            <span class="av2">SG</span>
            <span class="nm">Samwise</span>
            <span class="nick">„Potato Master" Gamgee</span>
          </div>
          <div class="row empty">
            <span class="av2">+</span>
            <span class="nm">volné místo</span>
          </div>
        </div>
      </div>

      <div class="float f-count">
        <div class="ico">⏱</div>
        <div>
          <div class="lab2">Zveřejnění za</div>
          <div class="num" id="heroCountdown">71:42 h</div>
        </div>
      </div>

      <div class="float f-activity">
        <div class="lab">Aktivita</div>
        <div class="nm">Mines of Moria</div>
        <div class="slots">
          <span class="slot">čt 9:00–12:00</span>
          <span class="slot">čt 14:00–17:00</span>
        </div>
      </div>

      <div class="float f-code">
        <div>
          <div class="lab">Kód družiny</div>
          <div class="val">9964</div>
        </div>
        <button class="copy" aria-label="Kopírovat">⧉</button>
      </div>
    </div>
  </div>
</section>

<!-- INTRO -->
<section class="intro" id="intro">
  <div class="wrap">
    <div class="s-head">
      <div class="left">
        <div class="eyebrow"><span class="dot"></span>O co jde</div>
        <h2>Co jsou týmové aktivity?</h2>
        <p>Některé turnaje a kooperativní hry na GameConu se hrají v týmu. Jeden hráč jde dovnitř jako <b>kapitán</b>, ostatní se přidají &mdash; přes kód nebo z veřejného seznamu družin.</p>
      </div>
    </div>

    <div class="intro-cards">
      <div class="icard a">
        <div class="num">1</div>
        <div class="illo">
          <div class="illo-dice">
            <div class="d"><span style="top:6px;left:6px"></span><span style="bottom:6px;right:6px"></span></div>
            <div class="d s"><span style="top:6px;left:6px"></span><span style="top:14px;left:14px"></span><span style="bottom:6px;right:6px"></span></div>
            <div class="d"><span style="top:14px;left:14px"></span></div>
          </div>
        </div>
        <h3>Týmová aktivita</h3>
        <p>U aktivit pro družiny se nepřihlašuješ sám &mdash; nastoupíte celý tým a všichni dostanete stejné kombo termínů.</p>
      </div>

      <div class="icard b">
        <div class="num">2</div>
        <div class="illo">
          <div class="illo-crown">
            <div class="av">K</div>
            <div class="chain">
              <div class="ln"></div>
              <div class="ln s"></div>
              <div class="ln"></div>
            </div>
          </div>
        </div>
        <h3>Kapitán zakládá tým</h3>
        <p>Jeden z vás klikne na <b>Založit nový tým</b>. Vybere termíny, vyřeší konflikty a tím rezervuje místo pro celou družinu.</p>
      </div>

      <div class="icard c">
        <div class="num">3</div>
        <div class="illo">
          <div class="illo-code">
            <span class="key">9964</span>
            <span class="arr2">→</span>
            <span class="door"></span>
          </div>
        </div>
        <h3>Ostatní se připojí</h3>
        <p>Kapitán pošle <b>4místný kód</b>. Spoluhráči ho zadají a jsou v týmu. Nebo si vyberou veřejnou družinu ze seznamu.</p>
      </div>
    </div>
  </div>
</section>

<!-- CAPTAIN FLOW -->
<section class="captain" id="captain">
  <div class="wrap">
    <div class="s-head">
      <div class="left">
        <div class="eyebrow"><span class="dot"></span>Cesta kapitána · hlavní flow</div>
        <h2>Vést družinu <span style="color:var(--red)">od kliku po zamčený tým.</span></h2>
        <p>Pět kroků. Mezi krokem 3 a 4 systém pohlídá kola a konflikty &mdash; pokud nějaké nastanou. Nejjednodušší případ proletíš za <b>30 sekund</b>.</p>
      </div>
      <div class="right">
        <div class="eyebrow" style="text-align:right">Krok <span id="stepN">1</span> / 5</div>
      </div>
    </div>

    <!-- Step rail -->
    <div class="steps-rail" role="tablist" id="stepRail">
      <button class="step-btn is-active" data-step="1"><div class="pip"><span>1</span></div><div class="lab">Klik na Přihlásit</div></button>
      <button class="step-btn" data-step="2"><div class="pip"><span>2</span></div><div class="lab">Založit nový tým</div></button>
      <button class="step-btn" data-step="3"><div class="pip"><span>3</span></div><div class="lab">Termíny &amp; kola</div></button>
      <button class="step-btn" data-step="4"><div class="pip"><span>4</span></div><div class="lab">Konflikty</div></button>
      <button class="step-btn" data-step="5"><div class="pip"><span>5</span></div><div class="lab">Tým žije</div></button>
    </div>

    <!-- Step viewer -->
    <div class="step-view" id="stepView">
      <!-- Filled by JS -->
    </div>

    <!-- Advanced collapsibles -->
    <div class="advanced" data-acc>
      <div class="adv-head">
        <span class="tag">Pokročilé</span>
        <h4>Když má aktivita víc termínů nebo kol</h4>
        <span class="sub">Týká se asi 1 ze 4 aktivit</span>
        <span class="chev">+</span>
      </div>
      <div class="adv-body">
        <div class="adv-grid">
          <div class="copy">
            <h5>Kapitán vybírá kombinaci</h5>
            <p>Většina aktivit má jen jeden lineární průběh &mdash; tam systém vybere termíny za tebe. Pokud má ale aktivita víc kol nebo paralelních běhů:</p>
            <ul>
              <li>Pro každé kolo zvolíš, kdy ho odehrajete.</li>
              <li>Tvoje volba platí pro celou družinu &mdash; všichni dostanou stejné sloty.</li>
              <li>Změna termínu jde, jen dokud tým není zamčený.</li>
            </ul>
          </div>
          <div class="demo">
            <div class="mini-modal" style="max-width:none;">
              <div class="mb" style="padding:14px;">
                <div style="font-size:10.5px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--ink-3);margin-bottom:8px;">Kolo 1 · vyber termín</div>
                <div class="mini-slots">
                  <div class="mini-slot sel"><span class="dot"></span><span class="dy">st</span><span class="tm">9:00–12:00</span></div>
                  <div class="mini-slot"><span class="dot"></span><span class="dy">čt</span><span class="tm">9:00–12:00</span></div>
                </div>
                <div style="font-size:10.5px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--ink-3);margin:14px 0 8px;">Kolo 2 · vyber termín</div>
                <div class="mini-slots">
                  <div class="mini-slot"><span class="dot"></span><span class="dy">st</span><span class="tm">15:00–17:00</span></div>
                  <div class="mini-slot sel"><span class="dot"></span><span class="dy">čt</span><span class="tm">14:00–17:00</span></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="advanced" data-acc>
      <div class="adv-head danger">
        <span class="tag">Konflikty</span>
        <h4>Co když mám v termínu jinou aktivitu</h4>
        <span class="sub">Stane se to. Není to drama.</span>
        <span class="chev">+</span>
      </div>
      <div class="adv-body">
        <div class="adv-grid">
          <div class="copy">
            <h5>Buď odhlas, nebo vyber jiný termín</h5>
            <p>Systém nedovolí přihlásit kapitána, který sedí ve dvou aktivitách najednou. Konflikt se ti ukáže červeně a budeš mít dvě možnosti:</p>
            <ul>
              <li><b>Odhlásit se</b> z konfliktní aktivity přímo z téhle obrazovky.</li>
              <li><b>Vrátit se</b> a vybrat jiný termín v Kroku 3.</li>
            </ul>
            <p style="margin-top:12px;">Konflikt pohlídá jen kapitána &mdash; ostatní členové si svoje konflikty řeší v okamžiku připojení do týmu.</p>
          </div>
          <div class="demo">
            <div class="mini-modal" style="max-width:none;">
              <div class="mb" style="padding:14px;">
                <div class="mini-alert danger">
                  <div class="i">!</div>
                  <div>
                    <div class="ttl">Konflikt termínů</div>
                    <div class="dsc">Pro přihlášení se musíš odhlásit z těchto aktivit:</div>
                  </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;padding:9px 10px;border:1.5px solid var(--red-2);background:var(--red-bg);font-size:12px;">
                  <div>
                    <div style="font-weight:800;">Council of Elrond</div>
                    <div style="font-size:11px;color:var(--ink-3);font-family:var(--f-mono);">čt 14:00–17:00</div>
                  </div>
                  <span style="margin-left:auto;"></span>
                  <span class="mbtn" style="background:var(--paper);color:var(--red-2);border-color:var(--red-2);box-shadow:2px 2px 0 var(--red-2);">Odhlásit</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Big draft callout -->
    <div class="draft-callout" id="draft">
      <div>
        <div class="eb">Mezistav · platí 15–30 minut</div>
        <h4>Rozpracovaný tým ti drží místo. Ale ne navždy.</h4>
        <p>Dokud nemáš vybrané termíny a vyřešené konflikty, tým <b style="color:#fff">existuje jen dočasně</b>. Drží ti rezervaci kapacity, aby ti místo nikdo nevyfoukl &mdash; ale po krátkém timeoutu se sám smaže a sloty uvolní dalším hráčům. Dokonči nastavení a tým přejde do stavu <b style="color:#fff">připraven</b>.</p>
      </div>
      <div class="meter" aria-hidden="true">
        <div>
          <div class="big mono" id="draftCd">28:42</div>
          <div class="lbl">do smazání</div>
          <div class="lbl2">rozpracovaný tým</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- MANAGEMENT -->
<section class="manage" id="manage">
  <div class="wrap">
    <div class="s-head">
      <div class="left">
        <div class="eyebrow"><span class="dot"></span>Po vytvoření · správa týmu</div>
        <h2>Tým žije <span style="color:var(--red)">a ty mu velíš.</span></h2>
        <p>Modal přepne do správy. Tady ladíš jméno, kód, viditelnost a kdo je v družině. Jedno tlačítko je důležitější než ostatní &mdash; <b>Zamknout tým</b>.</p>
      </div>
    </div>

    <div class="manage-grid">
      <div style="position:relative;">
        <!-- Annotated mock -->
        <div class="callout right" style="top:54px; left:-28px;">
          <div class="pn">1</div>
          <div>Změň jméno nebo si nech vygenerovat náhodné kostkou.</div>
        </div>
        <div class="callout left red" style="top:170px; right:-32px;">
          <div class="pn">2</div>
          <div>Kód týmu &mdash; pošli ho spoluhráčům. Můžeš ho i přegenerovat.</div>
        </div>
        <div class="callout right" style="top:290px; left:-28px;">
          <div class="pn">3</div>
          <div>Soukromý jen na kód, nebo veřejný v seznamu.</div>
        </div>
        <div class="callout left" style="top:430px; right:-32px;">
          <div class="pn">4</div>
          <div>Počet volných slotů upravíš krokovačem.</div>
        </div>

        <div class="team-screen">
          <div class="ts-eb">Tvůj tým</div>
          <div class="ts-ttl">Orkové na Brigádě</div>
          <div class="ts-sub">
            <span class="slot">čt 9:00–12:00</span>
            <span class="slot">čt 14:00–17:00</span>
          </div>

          <div class="ts-section">
            <div class="lbl">Název týmu</div>
            <div class="ts-name">
              <input type="text" value="Orkové na Brigádě" />
              <button class="ibtn" title="Vygenerovat" aria-label="Náhodné jméno">⚄</button>
              <button class="ibtn" title="Uložit" aria-label="Uložit" style="width:auto;padding:0 14px;font-weight:800;font-size:11px;letter-spacing:.06em;text-transform:uppercase;">Uložit</button>
            </div>
          </div>

          <div class="ts-section">
            <div class="lbl">Kód týmu</div>
            <div class="ts-code">
              <div>
                <div class="lab">Sdílej se spoluhráči</div>
                <div class="val">9964</div>
              </div>
              <div class="right">
                <button class="ibtn" aria-label="Kopírovat">⧉</button>
                <button class="ibtn" aria-label="Přegenerovat" style="width:auto;padding:0 12px;font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;">Přegenerovat</button>
              </div>
            </div>
          </div>

          <div class="ts-section">
            <div class="lbl">Viditelnost</div>
            <div class="ts-vis">
              <div class="pill on">🔒 Soukromý (jen na kód)</div>
              <div class="pill">🌐 Veřejný (otevřený)</div>
            </div>
          </div>

          <div class="ts-section">
            <div class="lbl">Členové <span style="font-weight:700;text-transform:none;letter-spacing:0;color:var(--ink-2);">(3/5, min. 2)</span></div>
            <div class="ts-members">
              <div class="m cap">
                <div class="a">AE</div>
                <div><span class="nm">Aragorn</span> <span class="nk">„Strider" Elessar</span></div>
                <div class="bg">Kapitán</div>
              </div>
              <div class="m">
                <div class="a">GM</div>
                <div><span class="nm">Gandalf</span> <span class="nk">„The Grey" Mithrandir</span></div>
              </div>
              <div class="m">
                <div class="a">SG</div>
                <div><span class="nm">Samwise</span> <span class="nk">„Potato Master" Gamgee</span></div>
              </div>
              <div class="m empty">
                <div class="a">+</div>
                <div>volné místo</div>
              </div>
            </div>
          </div>

          <button class="ts-lock">
            <span class="key">🔒</span> Zamknout tým a začít hrát
          </button>
        </div>
      </div>

      <!-- LOCK spotlight -->
      <div>
        <div class="lock-card">
          <div class="stamp">Final</div>
          <div class="eb">Nejdůležitější tlačítko</div>
          <h3>Zamknout&nbsp;tým.</h3>
          <p class="lede">Jakmile naplníš minimální kapacitu, můžeš tým zamknout. Tím přepneš celou družinu do <b style="color:#fff">finálního stavu</b> &mdash; připravenou na turnaj.</p>

          <div class="lock-ico">
            <div class="key2">🔒</div>
            <div>
              <div class="ttl">Tým je zamčen</div>
              <div class="sb">Připraveno k hraní</div>
            </div>
          </div>

          <ul class="lock-list">
            <li><span class="x">×</span> Nelze přidat ani odebrat hráče</li>
            <li><span class="x">×</span> Nelze změnit termíny ani kola</li>
            <li><span class="x">×</span> Nelze měnit nastavení týmu</li>
            <li style="background:rgba(63,138,78,.2); border-color:var(--green);"><span class="x" style="background:var(--green);">✓</span> Tým je hotový &mdash; můžete hrát</li>
          </ul>

          <div class="lock-warn">
            <span style="font-family:var(--f-display);font-weight:900;font-size:22px;line-height:1;">!</span>
            <div><b>Bez zamčení se tým po 72 h sám zveřejní nebo smaže.</b> Když máš plnou družinu, neváhej &mdash; zamknout neznamená nic dramatického, jen že už nikdo nic nerozhází.</div>
          </div>
        </div>

        <div class="feat-grid">
          <div class="feat">
            <div class="num">A</div>
            <h5>Předat kapitána</h5>
            <p>Hodí se, když nemůžeš dorazit a chceš, aby tým vedl někdo jiný.</p>
          </div>
          <div class="feat">
            <div class="num">B</div>
            <h5>Odebrat hráče</h5>
            <p>Kapitán může kohokoliv z týmu vyřadit, dokud není zamčeno.</p>
          </div>
          <div class="feat">
            <div class="num">C</div>
            <h5>Změnit počet slotů</h5>
            <p>V rámci min/max kapacity aktivity. Volných míst můžeš mít víc i míň.</p>
          </div>
          <div class="feat">
            <div class="num">D</div>
            <h5>Zveřejnit tým</h5>
            <p>Otevřeš družinu pro náhodné spoluhráče &mdash; objeví se v seznamu veřejných týmů.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- TEAM STATES -->
<section class="states" id="states">
  <div class="wrap">
    <div class="s-head">
      <div class="left">
        <div class="eyebrow"><span class="dot"></span>Čtyři stavy týmu</div>
        <h2>Život družiny od založení po turnaj.</h2>
        <p>Tým prochází jasnými stavy. Každý má jinou barvu, jiná pravidla a jinou věc, kterou s ním můžeš udělat.</p>
      </div>
    </div>

    <div class="states-grid">
      <div class="scard draft">
        <div class="badge">Rozpracovaný</div>
        <div class="visual viz-draft">
          <div class="clk" aria-hidden="true"></div>
          <div class="cd mono" id="stateCd">14:52</div>
        </div>
        <h3>Drží ti místo &mdash; nakrátko.</h3>
        <p>Tým ti rezervuje slot, ale po <b>15–30 minutách</b> se sám smaže, pokud nedokončíš výběr kol a konfliktů.</p>
        <div class="meta-row"><span class="dt"></span>Dokonči nastavení</div>
      </div>

      <div class="scard ready">
        <div class="badge">Připravený</div>
        <div class="visual viz-ready">
          <div class="dots">
            <span class="dt c"></span><span class="dt f"></span><span class="dt f"></span><span class="dt"></span><span class="dt"></span>
          </div>
          <div class="lab mono">2 / 5 · kapitán</div>
        </div>
        <h3>Soukromá družina čeká na hráče.</h3>
        <p>Vše je validní. Sdílíš kód a pomalu plníš týmu sloty. Bez zamčení se po <b>72 h</b> tým otevře nebo smaže.</p>
        <div class="meta-row"><span class="dt"></span>Sdílej kód · 71:42 h</div>
      </div>

      <div class="scard public">
        <div class="badge">Veřejný</div>
        <div class="visual viz-public">
          <div class="ico">→</div>
        </div>
        <h3>Otevřeno všem.</h3>
        <p>Družina je v seznamu veřejných týmů. <b>Kdokoli</b> se může přidat bez kódu, dokud nezamkneš nebo nedojde kapacita.</p>
        <div class="meta-row"><span class="dt"></span>Vidí všichni · 3 / 5</div>
      </div>

      <div class="scard locked">
        <div class="badge">Zamčený</div>
        <div class="visual viz-locked">
          <div class="lk">
            <div class="l">🔒</div>
            <div class="t">Final<b>Připraveno hrát</b></div>
          </div>
        </div>
        <h3>Družina je hotová.</h3>
        <p>Žádné změny už nepřijdou. Sloty jsou jisté, termíny zafixované &mdash; <b>vidíme se na turnaji</b>.</p>
        <div class="meta-row"><span class="dt"></span>Final · 5 / 5</div>
      </div>
    </div>
  </div>
</section>

<!-- JOIN -->
<section class="join" id="join">
  <div class="wrap">
    <div class="s-head">
      <div class="left">
        <div class="eyebrow"><span class="dot"></span>Cesta hráče · jen krátký flow</div>
        <h2>Připojit se? <span style="color:var(--red)">Stačí pár vteřin.</span></h2>
        <p>Pokud nezakládáš tým ty, máš to jednodušší. Buď ti někdo pošle <b>4místný kód</b>, nebo si vybereš ze seznamu veřejných družin.</p>
      </div>
    </div>

    <div class="join-cols">
      <div class="join-card">
        <div class="stamp">A</div>
        <div class="eb">Cesta 1 · máš kód</div>
        <h3>Zadej kód a jsi tam.</h3>
        <p class="lede">Kapitán ti pošle 4místné číslo. V detailu aktivity klikni na <b>Přihlásit</b>, do políčka kód zapiš a hotovo.</p>
        <div class="join-input">
          <input class="field mono" type="text" maxlength="4" placeholder="9964" />
          <button class="btn dark">Připoj se</button>
        </div>
        <div class="join-tip">
          <span style="font-family:var(--f-display);font-weight:900;color:var(--red);font-size:18px;line-height:1;">i</span>
          <div><b>Tip:</b> kód si zkopíruj ze zprávy &mdash; vložení proběhne automaticky. Pokud má aktivita konflikt s tvojí jinou aktivitou, systém ti to ukáže před připojením.</div>
        </div>
      </div>

      <div class="join-card alt">
        <div class="stamp">B</div>
        <div class="eb">Cesta 2 · veřejné týmy</div>
        <h3>Najdi otevřenou družinu.</h3>
        <p class="lede">Pokud nemáš s kým jít, podívej se na seznam <b>veřejných týmů</b>. Vyber si volné místo a přidej se.</p>
        <div class="join-list">
          <div class="row">
            <span class="nm">Hobiti &amp; spol.</span>
            <span class="ct">3/5</span>
            <span class="v">veřejný</span>
            <span class="sp"></span>
            <button class="jn">Připojit se</button>
          </div>
          <div class="row">
            <span class="nm">Družina ze Severu</span>
            <span class="ct">2/5</span>
            <span class="v">veřejný</span>
            <span class="sp"></span>
            <button class="jn">Připojit se</button>
          </div>
          <div class="row" style="opacity:.55;">
            <span class="nm">Stříbrná Liška</span>
            <span class="ct">5/5</span>
            <span class="v" style="background:var(--cream);">plný</span>
            <span class="sp"></span>
            <button class="jn" disabled style="opacity:.4;cursor:not-allowed;">Plný</button>
          </div>
        </div>
        <div class="join-tip">
          <span style="font-family:var(--f-display);font-weight:900;color:var(--red);font-size:18px;line-height:1;">i</span>
          <div>Kapitán určuje, jestli je tým veřejný. Jakmile zamkne, mizí ze seznamu.</div>
        </div>
      </div>
    </div>

    <div class="join-done">
      <div class="big">✓</div>
      <div>
        <h4>Hotovo. Vítej v družině.</h4>
        <p>Kapitán dokončí přihlašování za celý tým. Drž se připravený &mdash; stačí dorazit na čas.</p>
      </div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="faq" id="faq">
  <div class="wrap">
    <div class="s-head">
      <div class="left">
        <div class="eyebrow"><span class="dot"></span>Otázky, které dostáváme často</div>
        <h2>Detaily, na které se ptáte.</h2>
        <p>Hlavně pro staré matadory předchozího systému &mdash; kde se co změnilo a proč.</p>
      </div>
    </div>

    <div class="faq-list">
      <div class="qa" data-qa>
        <div class="qa-q"><span class="n mono">01</span><h4>Co se změnilo oproti starému systému týmů?</h4><span class="chev">+</span></div>
        <div class="qa-a">
          <p>Místo zakládání týmu mimo aktivitu se teď tým zakládá <b>přímo z aktivity</b>. První klik je vždy <b>Přihlásit</b> &mdash; modal pak rozhodne, jestli zakládáš tým, připojuješ se kódem, nebo jen klasicky podáváš přihlášku.</p>
          <p>Druhá změna: <b>4místný kód</b>. Ten ti vznikne automaticky a sdílíš ho spoluhráčům. Žádné odkazy, žádné e-maily.</p>
        </div>
      </div>

      <div class="qa" data-qa>
        <div class="qa-q"><span class="n mono">02</span><h4>Jak dlouho tým žije, když ho nedokončím?</h4><span class="chev">+</span></div>
        <div class="qa-a">
          <p><b>Rozpracovaný tým</b> (bez vybraných kol nebo s nevyřešeným konfliktem) se po <b>15–30 minutách</b> sám smaže a uvolní slot zpátky.</p>
          <p><b>Připravený, ale nezamčený</b> tým žije <b>72 hodin</b>. Po nich se buď automaticky zveřejní (jestli jsi tu možnost povolil), nebo se smaže.</p>
        </div>
      </div>

      <div class="qa" data-qa>
        <div class="qa-q"><span class="n mono">03</span><h4>Co dělá &bdquo;zamknutí&ldquo; přesně?</h4><span class="chev">+</span></div>
        <div class="qa-a">
          <p>Zamčený tým je finální stav. Nelze měnit členy, termíny ani nic dalšího. <b>Sloty jsou rezervované napevno</b>, kapacitu nikdo nepřebere, družina jde rovnou na turnaj.</p>
          <p>Kapitán může tým zamknout, jakmile je splněna <b>minimální kapacita</b> aktivity.</p>
        </div>
      </div>

      <div class="qa" data-qa>
        <div class="qa-q"><span class="n mono">04</span><h4>Můžu odejít z týmu, když jsem se přidal?</h4><span class="chev">+</span></div>
        <div class="qa-a">
          <p>Ano, dokud tým není zamčený. Ve správě týmu klikni na <b>Opustit tým</b>. Slot, který jsi obsazoval, se uvolní.</p>
          <p>Když odchází <b>kapitán</b>, musí nejdřív někomu předat kapitánství &mdash; jinak by tým osiřel.</p>
        </div>
      </div>

      <div class="qa" data-qa>
        <div class="qa-q"><span class="n mono">05</span><h4>Co se stane při konfliktu termínů?</h4><span class="chev">+</span></div>
        <div class="qa-a">
          <p>Pokud se tvoje vybrané termíny překrývají s jinou aktivitou, na kterou jsi přihlášený, systém <b>nedovolí dokončit přihlášení</b>. Konfliktní aktivitu buď ukončíš přímo z konflikt obrazovky, nebo se vrátíš a zvolíš jiné kolo.</p>
        </div>
      </div>

      <div class="qa" data-qa>
        <div class="qa-q"><span class="n mono">06</span><h4>Co je &bdquo;veřejný tým&ldquo;?</h4><span class="chev">+</span></div>
        <div class="qa-a">
          <p>Veřejný tým se objeví v seznamu pro hráče, kteří hledají družinu. Můžou se přidat <b>bez znalosti kódu</b>. Hodí se, když máš volné místo a nikoho na něj.</p>
          <p>Veřejnost zapneš jedním kliknutím ve správě týmu &mdash; a kdykoli zase vypneš.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<footer>
  <div class="row">
    <div class="logo">GameCon</div>
    <div class="links">
      <a href="#hero">Návod nahoru</a>
      <a href="#">Program 2026</a>
      <a href="#">Pravidla</a>
      <a href="#">Kontakt</a>
    </div>
    <div class="meta">v2026.1 · /napoveda/tymy</div>
  </div>
</footer>

<script>
  var COPY = {
    activity: "Mines of Moria",
    teamName: "Orkové na Brigádě",
    code: "9964",
    captain: { full: "Aragorn", nick: "„Strider“", last: "Elessar", initials: "AE" },
    members: [
      { full: "Aragorn",  nick: "„Strider“",        last: "Elessar",      initials: "AE", captain:true },
      { full: "Gandalf",  nick: "„The Grey“",       last: "Mithrandir",   initials: "GM" },
      { full: "Samwise",  nick: "„Potato Master“",  last: "Gamgee",       initials: "SG" },
      { full: "Gimli",    nick: "„Axe Crit“",       last: "Gloinsson",    initials: "GG" }
    ],
    conflict: { name: "Council of Elrond", time: "čt 14:00–17:00" }
  };

  // ─── Hero countdown ───────────────────────────────────────────────
  (function(){
    var el = document.getElementById('heroCountdown');
    if(!el) return;
    var h=71, m=42, s=0;
    setInterval(function(){
      s--;
      if(s<0){ s=59; m--; }
      if(m<0){ m=59; h--; }
      if(h<0){ h=71; m=42; s=0; }
      el.textContent = (h<10?'0':'')+h+':'+(m<10?'0':'')+m+' h';
    }, 1000);
  })();

  // ─── Draft countdown ──────────────────────────────────────────────
  (function(){
    var el = document.getElementById('draftCd');
    if(!el) return;
    var m=28, s=42;
    setInterval(function(){
      s--;
      if(s<0){ s=59; m--; }
      if(m<0){ m=28; s=42; }
      el.textContent = (m<10?'0':'')+m+':'+(s<10?'0':'')+s;
    }, 1000);
  })();

  // ─── Accordions ───────────────────────────────────────────────────
  document.querySelectorAll('[data-acc]').forEach(function(acc){
    acc.querySelector('.adv-head').addEventListener('click', function(){
      acc.classList.toggle('open');
    });
  });

  // ─── Step viewer ──────────────────────────────────────────────────
  var STEPS = [
    {
      kicker: "Krok 1 · jsi na stránce aktivity",
      title: "Klikni na „Přihlásit“.",
      body: "Žádné dlouhé formuláře. Tlačítko v detailu aktivity ti otevře tým modal &mdash; tam se dějí všechny věci kolem družiny.",
      bullets: ["Funguje to stejně pro sólo i týmové aktivity.", "Pokud nemáš účet, systém tě nejdřív přihlásí."],
      mockHtml: stepMockApply
    },
    {
      kicker: "Krok 2 · v týmovém modalu",
      title: "Vyber „Založit nový tým“.",
      body: "Jako kapitán dostaneš první volbu. Můžeš taky zadat kód existující družiny nebo si vybrat z veřejných týmů &mdash; ale to je už cesta hráče.",
      bullets: ["Tým vznikne hned po kliku &mdash; nemusíš nic potvrzovat.", "Stáváš se automaticky kapitánem.", "Rezervace slotu se ti začne počítat."],
      mockHtml: stepMockCreate
    },
    {
      kicker: "Krok 3 · termíny",
      title: "Když má aktivita víc kol, vyber kombinaci.",
      body: "U většiny aktivit tenhle krok přeskočíš &mdash; je jen jeden termín a systém ho zvolí za tebe. Pokud má aktivita víc kol, klikneš si je tady.",
      bullets: ["Volba platí pro celou družinu.", "Změnit jde, dokud tým není zamčený."],
      mockHtml: stepMockRounds
    },
    {
      kicker: "Krok 4 · pokud nastal konflikt",
      title: "Vyřeš konflikt s jinou aktivitou.",
      body: "Pokud sedíš v jiné aktivitě, která se časově překrývá, systém tě nepustí dál. Odhlas se z ní rovnou tady, nebo se vrať a vyber jiný termín.",
      bullets: ["Konflikt pohlídá jen kapitána.", "Členové si svoje konflikty řeší při připojení."],
      mockHtml: stepMockConflict
    },
    {
      kicker: "Krok 5 · hotovo",
      title: "Tým žije. Pošli kód spoluhráčům.",
      body: "Modal přepne do správy týmu &mdash; přibude kód, jméno týmu a tlačítko pro zamčení. Sdílej kód a družina se začne plnit.",
      bullets: ["Po naplnění minimální kapacity můžeš tým zamknout.", "Bez zamčení se po 72 h tým automaticky zveřejní nebo smaže.", "Až do zamčení můžeš všechno editovat."],
      mockHtml: stepMockManage
    }
  ];

  function el(tag, attrs, kids){
    var n = document.createElement(tag);
    if(attrs){ for(var k in attrs){
      if(k==='class') n.className=attrs[k];
      else if(k==='html') n.innerHTML=attrs[k];
      else n.setAttribute(k, attrs[k]);
    }}
    (kids||[]).forEach(function(c){ if(c) n.appendChild(typeof c==='string'?document.createTextNode(c):c); });
    return n;
  }

  // ─── Step mocks (DOM-built mini modals) ───────────────────────────
  function modalShell(eb, tt, bodyHtml, footHtml){
    return '<div class="mini-modal">'
      + '<div class="mh"><div class="eb">'+eb+'</div><div class="tt">'+tt+'</div><div class="x">×</div></div>'
      + '<div class="mb">'+bodyHtml+'</div>'
      + (footHtml ? '<div class="mf">'+footHtml+'</div>' : '')
      + '</div>';
  }

  function stepMockApply(){
    return ''
      + '<div class="mini-modal" style="overflow:visible;">'
      + '<div class="mh" style="background:var(--red);color:#fff;border-color:var(--ink);">'
      +   '<div class="eb" style="color:#ffd9da;">Aktivita</div>'
      +   '<div class="tt" style="color:#fff;">'+COPY.activity+'</div>'
      + '</div>'
      + '<div class="mb">'
      +   '<div style="font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--ink-3);margin-bottom:8px;">Termíny</div>'
      +   '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;">'
      +     '<span class="mbtn" style="cursor:default;box-shadow:none;padding:5px 9px;font-size:10.5px;background:var(--cream);">čt 9:00–12:00</span>'
      +     '<span class="mbtn" style="cursor:default;box-shadow:none;padding:5px 9px;font-size:10.5px;background:var(--cream);">čt 14:00–17:00</span>'
      +   '</div>'
      +   '<div style="font-size:13px;color:var(--ink-2);line-height:1.5;">Kooperativní průzkum hlubin. <b>Hraje se v týmu</b> &mdash; přihlas se jako kapitán nebo se přidej kódem.</div>'
      + '</div>'
      + '<div class="mf"><span class="mbtn p lg" style="position:relative;">Přihlásit <span style="position:absolute;top:-30px;right:-22px;background:var(--ink);color:#fff;font-family:var(--f-mono);font-size:10.5px;padding:4px 8px;font-weight:700;letter-spacing:.04em;transform:rotate(-3deg);box-shadow:2px 2px 0 var(--red);">klikni sem ↓</span></span></div>'
      + '</div>';
  }

  function stepMockCreate(){
    return modalShell(
      'Tým modal',
      COPY.activity,
      '<div style="font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--ink-3);margin-bottom:8px;">Nový tým</div>'
      + '<div style="margin-bottom:14px;position:relative;"><span class="mbtn p full lg">✱ Založit nový tým →</span><span style="position:absolute;top:50%;right:-86px;transform:translateY(-50%);font-family:var(--f-mono);font-size:11px;color:var(--ink);font-weight:700;background:var(--sand);padding:3px 8px;border:1.5px solid var(--ink);box-shadow:2px 2px 0 var(--ink);">↤ ty</span></div>'
      + '<div style="text-align:center;font-size:10.5px;font-weight:800;letter-spacing:.2em;color:var(--ink-3);margin:14px 0;">— nebo —</div>'
      + '<div style="display:grid;grid-template-columns:1fr auto;gap:8px;"><div style="border:2px solid var(--ink);padding:9px 10px;font-family:var(--f-mono);letter-spacing:.4em;text-align:center;color:var(--ink-3);font-size:14px;font-weight:800;">XXXX</div><span class="mbtn d">Připoj se</span></div>'
    );
  }

  function stepMockRounds(){
    return modalShell(
      'Vyber termíny',
      COPY.activity,
      '<div class="mini-alert warn"><div class="i">⏱</div><div><div class="ttl">Zbývá <span class="cd">29:12</span> na výběr</div><div class="dsc">Vyber kola a přihlas se, jinak se rozpracovaný tým smaže.</div></div></div>'
      + '<div style="font-size:10.5px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--ink-3);margin:6px 0 8px;">Kolo 1</div>'
      + '<div class="mini-slots">'
      +   '<div class="mini-slot sel"><span class="dot"></span><span class="dy">st</span><span class="tm">9:00–12:00</span></div>'
      +   '<div class="mini-slot"><span class="dot"></span><span class="dy">čt</span><span class="tm">9:00–12:00</span></div>'
      + '</div>'
      + '<div style="font-size:10.5px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--ink-3);margin:14px 0 8px;">Kolo 2</div>'
      + '<div class="mini-slots">'
      +   '<div class="mini-slot"><span class="dot"></span><span class="dy">st</span><span class="tm">15:00–17:00</span></div>'
      +   '<div class="mini-slot sel"><span class="dot"></span><span class="dy">čt</span><span class="tm">14:00–17:00</span></div>'
      + '</div>',
      '<span class="mbtn p">Potvrdit výběr →</span>'
    );
  }

  function stepMockConflict(){
    return modalShell(
      'Konflikt termínů',
      COPY.activity,
      '<div class="mini-alert danger"><div class="i">!</div><div><div class="ttl">Konflikt termínů</div><div class="dsc">Pro přihlášení jako kapitán se odhlas z těchto aktivit:</div></div></div>'
      + '<div style="display:flex;align-items:center;gap:8px;padding:9px 10px;border:1.5px solid var(--red-2);background:var(--red-bg);font-size:12px;margin-bottom:10px;">'
      +   '<div><div style="font-weight:800;">'+COPY.conflict.name+'</div><div style="font-size:11px;color:var(--ink-3);font-family:var(--f-mono);">'+COPY.conflict.time+'</div></div>'
      +   '<span style="margin-left:auto;"></span>'
      +   '<span class="mbtn" style="background:var(--paper);color:var(--red-2);border-color:var(--red-2);box-shadow:2px 2px 0 var(--red-2);padding:5px 9px;font-size:10px;">Odhlásit</span>'
      + '</div>'
      + '<div style="font-size:12px;color:var(--ink-3);">Tip: nebo se vrať o krok zpátky a vyber jiný termín.</div>',
      '<span class="mbtn" style="opacity:.45;">✓ Přihlásit jako kapitán</span>'
    );
  }

  function stepMockManage(){
    var rows = COPY.members.slice(0,3).map(function(m){
      return '<div class="r '+(m.captain?'cap':'')+'"><div class="a">'+m.initials+'</div><span class="b">'+m.full+' '+m.nick+' '+m.last+'</span>'
        + (m.captain?'<span class="t">Kapitán</span>':'')
        + '</div>';
    }).join('');
    return modalShell(
      'Tvůj tým',
      COPY.teamName,
      '<div class="mini-alert ok"><div class="i">✓</div><div><div class="ttl">Tým je rozpracovaný &mdash; pošli kód</div><div class="dsc">Po naplnění můžeš tým zamknout.</div></div></div>'
      + '<div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--cream);border:1.5px solid var(--ink);margin-bottom:12px;">'
      +   '<div><div style="font-size:9.5px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:var(--ink-3);">Kód týmu</div><div style="font-family:var(--f-mono);font-weight:800;font-size:22px;letter-spacing:.16em;line-height:1;">'+COPY.code+'</div></div>'
      +   '<span style="margin-left:auto;"></span>'
      +   '<span class="mbtn" style="padding:5px 8px;font-size:10px;">Kopírovat</span>'
      + '</div>'
      + '<div class="mini-mem">'+rows+'</div>',
      '<span class="mbtn s">🔒 Zamknout tým</span>'
    );
  }

  function renderStep(i){
    var s = STEPS[i];
    var view = document.getElementById('stepView');
    view.innerHTML = ''
      + '<div class="step-info">'
      +   '<div class="kicker">'+s.kicker+'</div>'
      +   '<h3>'+s.title+'</h3>'
      +   '<p>'+s.body+'</p>'
      +   '<ul>'+s.bullets.map(function(b){ return '<li><span class="ck">✓</span><span>'+b+'</span></li>'; }).join('')+'</ul>'
      +   '<div class="nav-row">'
      +     '<button class="btn ghost" id="prevStep" '+(i===0?'disabled':'')+'>← Předchozí</button>'
      +     '<button class="btn primary" id="nextStep" '+(i===STEPS.length-1?'disabled':'')+'>Další krok →</button>'
      +   '</div>'
      + '</div>'
      + '<div class="step-mock">'+s.mockHtml()+'</div>';

    document.querySelectorAll('#stepRail .step-btn').forEach(function(b,bi){
      b.classList.remove('is-active','is-done');
      if(bi===i) b.classList.add('is-active');
      else if(bi<i) b.classList.add('is-done');
    });
    document.getElementById('stepN').textContent = (i+1);

    var p = document.getElementById('prevStep');
    var n = document.getElementById('nextStep');
    if(p) p.onclick = function(){ if(i>0) renderStep(i-1); };
    if(n) n.onclick = function(){ if(i<STEPS.length-1) renderStep(i+1); };
  }
  document.querySelectorAll('#stepRail .step-btn').forEach(function(b){
    b.addEventListener('click', function(){
      var idx = parseInt(b.getAttribute('data-step'),10) - 1;
      renderStep(idx);
    });
  });
  renderStep(0);

  // ─── State card countdown ─────────────────────────────────────────
  (function(){
    var el = document.getElementById('stateCd');
    if(!el) return;
    var m=14, s=52;
    setInterval(function(){
      s--;
      if(s<0){ s=59; m--; }
      if(m<0){ m=14; s=52; }
      el.textContent = (m<10?'0':'')+m+':'+(s<10?'0':'')+s;
    }, 1000);
  })();

  // ─── FAQ accordion ────────────────────────────────────────────────
  document.querySelectorAll('[data-qa]').forEach(function(qa, i){
    if(i===0) qa.classList.add('open');
    qa.querySelector('.qa-q').addEventListener('click', function(){
      qa.classList.toggle('open');
    });
  });

  // ─── Lock button celebration ──────────────────────────────────────
  (function(){
    var btn = document.querySelector('.ts-lock');
    if(!btn) return;
    var locked = false;
    btn.addEventListener('click', function(){
      locked = !locked;
      if(locked){
        btn.innerHTML = '<span class="key">✓</span> Tým je zamčený &mdash; uvidíme se na turnaji';
        btn.style.background = 'var(--green-deep)';
      } else {
        btn.innerHTML = '<span class="key">🔒</span> Zamknout tým a začít hrát';
        btn.style.background = '';
      }
    });
  })();

  // ─── Visibility pill toggle (mock) ────────────────────────────────
  document.querySelectorAll('.ts-vis .pill').forEach(function(p){
    p.addEventListener('click', function(){
      document.querySelectorAll('.ts-vis .pill').forEach(function(x){ x.classList.remove('on'); });
      p.classList.add('on');
    });
  });

  // ─── Hero copy code button (mock) ─────────────────────────────────
  (function(){
    var btn = document.querySelector('.f-code .copy');
    if(!btn) return;
    btn.addEventListener('click', function(){
      var orig = btn.innerHTML;
      btn.innerHTML = '✓';
      btn.style.background = 'var(--green)';
      btn.style.color = '#fff';
      setTimeout(function(){
        btn.innerHTML = orig;
        btn.style.background = '';
        btn.style.color = '';
      }, 1400);
    });
  })();
</script>




