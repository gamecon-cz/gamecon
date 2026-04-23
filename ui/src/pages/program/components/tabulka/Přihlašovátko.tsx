import { FunctionComponent } from "preact";
import { useEffect, useState } from "preact/hooks";
import { useAktivita, useUživatel } from "../../../../store/program/selektory";
import { volnoTypZObsazenost } from "../../../../utils";
import { nastavModalNastaveníTýmu, nastavModalOdhlásit } from "../../../../store/program/slices/všeobecnéSlice";
import { proveďAkciAktivity, useStavAkce } from "../../../../store/program/slices/programDataSlice";

const zámeček = `🔒`;

type TPřihlašovátkoProps = {
  akitivitaId: number;
};

type FormTlačítkoTyp =
  | "prihlasit"
  | "odhlasit"
  | "prihlasSledujiciho"
  | "odhlasSledujiciho"
  | "tym"
  | "zamceno"
  ;

type FormTlačítkoProps = {
  akitivitaId: number;
  typ: FormTlačítkoTyp;
  tymova?: boolean;
};

const FormTlačítko: FunctionComponent<FormTlačítkoProps> = ({
  akitivitaId,
  typ,
  tymova,
}) => {
  // todo(tym): nějaký handling na zbývající čas ??
  /*
  const [zbýváText, setZbýváText] = useState("666 hodin");
  const spočítejZbýváText = () => {
      const zbýváMinut = Math.floor((zamčenaDo - Date.now()) / (1_000 * 60));
      const zbýváHodin = Math.floor(zbýváMinut / 60);
      setZbýváText(zbýváHodin >= 1
        ? `${zbýváHodin} hodin${zbýváHodin === 1 ? "a" : ""}`
        : zbýváMinut >= 1
          ? `${zbýváMinut} minut${zbýváMinut === 1 ? "a" : ""}`
          : zbýváMinut >= 0
            ? `méně než minuta`
            : `žádný čas (načti znova stránku)`
      );
  };
  // schov zámeček pokud je zamčenaDo 5 minut v minulosti (výpočet rerenderu komponenty)
  const zámečekViditelný = zamčenaDo && (((zamčenaDo - (Date.now() - 5 * 1_000 * 60)) / (1_000 * 60)) > 0);
  useEffect(() => {
    if (!zamčenaDo) return;
    const interval = setInterval(spočítejZbýváText, 10_000);
    spočítejZbýváText();
    return () => { clearInterval(interval); };
  }, []);
    */

  const text =
    typ === "prihlasit"
      ? "přihlásit"
      : typ === "odhlasit"
        ? "odhlásit"
        : typ === "prihlasSledujiciho"
          ? "sledovat"
          : typ === "odhlasSledujiciho"
            ? "zrušit sledování"
            : typ === "tym"
              ? "tým"
              : "";

  return (
    <>
      <form method="none" style="display:inline" onSubmit={(e) => { e.preventDefault(); }}>
        <a
          href="#"
          onClick={(e) => {
            e.preventDefault();
            if (tymova || typ === "tym") {
              nastavModalNastaveníTýmu(akitivitaId);
            } else if (typ === "zamceno") {
              return;
            } else if (typ == "odhlasit") {
              nastavModalOdhlásit(akitivitaId);
            } else {
              void proveďAkciAktivity(akitivitaId, typ);
            }
          }}
        >
          {text}
          {
            // todo(tym): co se bude zorbazovat když nejsou žádné volné veřejné týmy ?
            /*
              zámečekViditelný ?
              <span class="hinted">{zámeček}<span class="hint">Kapitánovi týmu zbývá {zbýváText} na vyplnění svého týmu</span></span>
              : undefined
            */}
        </a>
      </form>
    </>
  );
};

const NačítáníText = () => {
  const [teček, setTeček] = useState(0);

  useEffect(() => {
    const interval = setInterval(() => {
      setTeček(x => (x + 1) % 3);
    }, 1000);
    return () => clearInterval(interval);
  }, [])

  return <em>Načítání {"".padEnd(teček + 1, ".")}</em>;
}

export const Přihlašovátko: FunctionComponent<TPřihlašovátkoProps> = (
  props
) => {
  const { akitivitaId } = props;

  const uživatel = useUživatel();
  const aktivita = useAktivita(akitivitaId);
  const stavAkce = useStavAkce();

  if (!uživatel.prihlasen) return <></>;

  if (uživatel.gcStav === "nepřihlášen") return <></>;

  if (!aktivita?.prihlasovatelna) return <></>;

  if (aktivita?.jeBrigadnicka && !uživatel.brigadnik) return <></>;


  if (stavAkce === "načítání") {
    return <NačítáníText />;
  }

  if (
    aktivita.stavPrihlaseni &&
    aktivita.stavPrihlaseni !== "sledujici"
  ) {
    if (aktivita.stavPrihlaseni === "prihlasen")
      return <FormTlačítko
        akitivitaId={akitivitaId}
        typ={aktivita.tymova ? "tym" : "odhlasit"}
        tymova={aktivita.tymova}
        />;
    else if (aktivita.stavPrihlaseni === "prihlasenADorazil")
      return <em>účast</em>;
    else if (aktivita.stavPrihlaseni === "dorazilJakoNahradnik")
      return <em>jako náhradník</em>;
    else if (aktivita.stavPrihlaseni === "prihlasenAleNedorazil")
      return <em>neúčast</em>;
    else if (aktivita.stavPrihlaseni === "pozdeZrusil")
      return <em>pozdní odhlášení</em>;
  }

  if (aktivita.vedu) return <></>;

  if (aktivita.obsazenost) {
    const volnoTyp = volnoTypZObsazenost(aktivita.obsazenost);

    if (volnoTyp === "u" || volnoTyp === uživatel.pohlavi)
      return <FormTlačítko akitivitaId={akitivitaId} typ={"prihlasit"} tymova={aktivita.tymova} />;
    else if (volnoTyp === "f") return <>pouze ženská místa</>;
    else if (volnoTyp === "m") return <>pouze mužská místa</>;

    // todo(tym): nahradit kontrolu: !aktivita?.dite?.length && !aktivita?.tymova
    const prihlasovatelnaProSledujici = !aktivita?.tymova;
    if (prihlasovatelnaProSledujici) {
      if (aktivita.stavPrihlaseni === "sledujici")
        return <FormTlačítko akitivitaId={akitivitaId} typ={"odhlasSledujiciho"} tymova={aktivita.tymova} />;
      else return <FormTlačítko akitivitaId={akitivitaId} typ={"prihlasSledujiciho"} tymova={aktivita.tymova} />;
    }
  }
  return <></>;
};

Přihlašovátko.displayName = "Přihlašovátko";
