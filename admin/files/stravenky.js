
const entry = document.querySelector("#entry");

const data = dataStr.map(uživ => ({
  login_uzivatele: uživ.login_uzivatele,
  nazev: uživ.nazev,
  id_uzivatele: +uživ.id_uzivatele,
  poradi_dne: +uživ.poradi_dne,
  poradi_jidla: +uživ.poradi_jidla,
}));

let uživatelé = {};

for (buňka of data) {
  (uživatelé[buňka.id_uzivatele] = uživatelé[buňka.id_uzivatele] ?? []).push(buňka);
}

uživatelé = Object.values(uživatelé);
uživatelé.sort((a, b) => a[0].id_uzivatele - b[0].id_uzivatele);
uživatelé.forEach(uživ => uživ.sort(
  (uživA, uživB) =>
    (uživA.poradi_dne * 10 + uživA.poradi_jidla)
    - (uživB.poradi_dne * 10 + uživB.poradi_jidla)
));


console.log({ entry, data, uživatelé });

const mřížkaVelikost = {
  šířka: 3,
  výška: 8,
};
const buňkyNaSránce = mřížkaVelikost.výška * mřížkaVelikost.šířka;

let stránkaElm = document.createElement("div");
let stránkaZustává = 0;

const vytvořStránku = () => {
  stránkaElm = document.createElement("div");
  stránkaElm.classList.add("stranka");
  stránkaZustává = buňkyNaSránce;
  entry.appendChild(stránkaElm);
};

const vytvořBuňku = (texty) => {
  const buňkaElm = document.createElement("div");
  buňkaElm.classList.add("bunka");
  stránkaZustává -= 1;

  if (!texty.length) {
    stránkaElm.appendChild(buňkaElm);
    return;
  }

  const obrazekElm = document.createElement("img");
  obrazekElm.src = "../files/design/logo_stravenky.png";
  obrazekElm.classList.add("bunka-obrazek");

  const obrazekObalElm = document.createElement("div");
  obrazekObalElm.classList.add("bunka-obrazek-obal");

  obrazekObalElm.appendChild(obrazekElm);
  buňkaElm.appendChild(obrazekObalElm);

  const textyElm = document.createElement("div");
  textyElm.classList.add("texty");
  let i = 0;
  for (text of texty) {
    const textElm = document.createElement("div");
    textElm.classList.add("text");
    if (i === 1)
      textElm.classList.add("text-id");
    if (i === 2)
      textElm.classList.add("text-den");
    textElm.innerText = text;
    textyElm.appendChild(textElm);
    i++;
  }
  buňkaElm.appendChild(textyElm);

  stránkaElm.appendChild(buňkaElm);
}


for (uživ of uživatelé) {
  /*
  if (stránkaZustává < uživ.length) {
    for (let i = stránkaZustává; i--;) {
      vytvořBuňku([
      ]);
    }
    vytvořStránku();
  }
  */

  for (buňka of uživ) {
    if (stránkaZustává === 0)
      vytvořStránku();
    vytvořBuňku([
      buňka.login_uzivatele.slice(0,18) + (buňka.login_uzivatele.length >= 18 ? "..." : ""),
      "ID " + buňka.id_uzivatele,
      buňka.nazev,
    ]);
  }

  if (stránkaZustává === 0)
    vytvořStránku();

  for (let i = stránkaZustává % mřížkaVelikost.šířka; i--;) {
    vytvořBuňku([]);
  }
}

if (stránkaZustává)
  for (let i = stránkaZustává; i--;) {
    vytvořBuňku([]);
  }
