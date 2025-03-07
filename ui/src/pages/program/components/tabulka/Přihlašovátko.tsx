import { FunctionComponent } from "preact";
import { useEffect, useRef, useState } from "preact/hooks";
import { useAktivita, useUživatel } from "../../../../store/program/selektory";
import { volnoTypZObsazenost } from "../../../../utils";
import { nastavModalOdhlásit } from "../../../../store/program/slices/všeobecnéSlice";
import { GAMECON_KONSTANTY } from "../../../../env";
import { načtiRok } from "../../../../store/program/slices/programDataSlice";
import { fetchAktivitaAkce } from "../../../../api/program";
import { useProgramStore } from "../../../../store/program";

const zámeček = `🔒`;

type TPřihlašovátkoProps = {
  akitivitaId: number;
};

type FormTlačítkoTyp =
  | "prihlasit"
  | "odhlasit"
  | "prihlasSledujiciho"
  | "odhlasSledujiciho"
  | "zamceno";

type FormTlačítkoProps = {
  id: number;
  typ: FormTlačítkoTyp;
  zamčenaDo?: number;
};

const FormTlačítko: FunctionComponent<FormTlačítkoProps> = ({
  id,
  typ,
  zamčenaDo,
}) => {
  const formRef = useRef<HTMLFormElement>(null);

  const [zbýváText, setZbýváText] = useState("666 hodin");
  const spočítejZbýváText = () => {
    if (!zamčenaDo) return;
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

  const text =
    typ === "prihlasit"
      ? "přihlásit"
      : typ === "odhlasit"
        ? "odhlásit"
        : typ === "prihlasSledujiciho"
          ? "sledovat"
          : typ === "odhlasSledujiciho"
            ? "zrušit sledování"
            : "";

  return (
    <>
      <form method="none" style="display:inline" onSubmit={(e) => { e.preventDefault(); }}>
        <a
          href="#"
          onClick={(e) => {
            e.preventDefault();
            if (typ === "zamceno") {
              return;
            } else if (typ == "odhlasit") {
              nastavModalOdhlásit(id);
            } else {
              useProgramStore.setState(s => { s.všeobecné.načítání = true; });
              fetchAktivitaAkce(typ, id)
                .then(async () => načtiRok(GAMECON_KONSTANTY.ROCNIK))
                .catch(x => { console.error(x); })
                .finally(() => { useProgramStore.setState(s => { s.všeobecné.načítání = false; }); });
            }
          }}
        >
          {text}
          {zámečekViditelný ?
            <span class="hinted">{zámeček}<span class="hint">Kapitánovi týmu zbývá {zbýváText} na vyplnění svého týmu</span></span>
            : undefined}
        </a>
      </form>
    </>
  );
};

export const Přihlašovátko: FunctionComponent<TPřihlašovátkoProps> = (
  props
) => {
  const { akitivitaId } = props;

  const uživatel = useUživatel();
  const aktivita = useAktivita(akitivitaId);

  if (!uživatel.prihlasen) return <></>;

  if (uživatel.gcStav === "nepřihlášen") return <></>;

  if (!aktivita?.prihlasovatelna) return <></>;

  if (aktivita?.jeBrigadnicka && !uživatel.brigadnik) return <></>;

  if (
    aktivita.stavPrihlaseni &&
    aktivita.stavPrihlaseni !== "sledujici"
  ) {
    if (aktivita.stavPrihlaseni === "prihlasen")
      return <FormTlačítko id={akitivitaId} typ={"odhlasit"} zamčenaDo={aktivita.zamcenaDo} />;
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

  if (aktivita.zamcenaDo && (aktivita.zamcenaDo > Date.now()) && !aktivita.zamcenaMnou)
    return <FormTlačítko id={akitivitaId} typ={"zamceno"} zamčenaDo={aktivita.zamcenaDo} />;

  if (aktivita.obsazenost) {
    const volnoTyp = volnoTypZObsazenost(aktivita.obsazenost);

    if (volnoTyp === "u" || volnoTyp === uživatel.pohlavi)
      return <FormTlačítko id={akitivitaId} typ={"prihlasit"} />;
    else if (volnoTyp === "f") return <>pouze ženská místa</>;
    else if (volnoTyp === "m") return <>pouze mužská místa</>;

    const prihlasovatelnaProSledujici =
      !aktivita?.dite?.length && !aktivita?.tymova;
    if (prihlasovatelnaProSledujici) {
      if (aktivita.stavPrihlaseni === "sledujici")
        return <FormTlačítko id={akitivitaId} typ={"odhlasSledujiciho"} />;
      else return <FormTlačítko id={akitivitaId} typ={"prihlasSledujiciho"} />;
    }
  }
  return <></>;
};

Přihlašovátko.displayName = "Přihlašovátko";
