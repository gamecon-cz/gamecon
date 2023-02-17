import { FunctionComponent } from "preact";
import { useRef } from "preact/hooks";
import { useProgramStore } from "../../../../store/program";
import { volnoTypZObsazenost } from "../../../../utils";

const zámeček = `🔒`;

type TPřihlašovátkoProps = {
  akitivitaId: number
};

type FormTlačítkoTyp =
  | "prihlasit"
  | "odhlasit"
  | "prihlasSledujiciho"
  | "odhlasSledujiciho"
  ;

const FormTlačítko: FunctionComponent<{ id: number, typ: FormTlačítkoTyp }> = ({ id, typ }) => {
  const formRef = useRef<HTMLFormElement>(null);

  const text =
    (typ === "prihlasit") ? "přihlásit" :
      (typ === "odhlasit") ? "odhlásit" :
        (typ === "prihlasSledujiciho") ? "sledovat" :
          (typ === "odhlasSledujiciho") ? "zrušit sledování" :
            "";

  return <form ref={formRef} method="post" style="display:inline">
    <input type="hidden" name={typ} value={id}></input>
    <a href="#" onClick={(e) => {
      formRef.current?.submit?.();
      e.preventDefault();
    }}>{text}</a>
  </form>;
};

export const Přihlašovátko: FunctionComponent<TPřihlašovátkoProps> = (props) => {
  const { akitivitaId } = props;

  const aktivita = useProgramStore(s => s.data.aktivityPodleId[akitivitaId]);
  const uživatel = useProgramStore(s => s.přihlášenýUživatel.data);
  const aktivitaUživatel = useProgramStore(s => s.data.aktivityPřihlášenPodleId[aktivita.id]);

  if (!uživatel.prihlasen)
    return <></>;

  if (uživatel.gcStav === "nepřihlášen")
    return <></>;

  if (!aktivitaUživatel?.prihlasovatelna)
    return <></>;

  if (aktivita.jeBrigadnicka && !uživatel.brigadnik)
    return <></>;

  if (aktivitaUživatel.stavPrihlaseni && aktivitaUživatel.stavPrihlaseni !== "sledujici") {
    if (aktivitaUživatel.stavPrihlaseni === "prihlasen")
      return <FormTlačítko id={aktivita.id} typ={"odhlasit"} />;
    else if (aktivitaUživatel.stavPrihlaseni === "prihlasenADorazil")
      return <em>účast</em>;
    else if (aktivitaUživatel.stavPrihlaseni === "dorazilJakoNahradnik")
      return <em>jako náhradník</em>;
    else if (aktivitaUživatel.stavPrihlaseni === "prihlasenAleNedorazil")
      return <em>neúčast</em>;
    else if (aktivitaUživatel.stavPrihlaseni === "pozdeZrusil")
      return <em>pozdní odhlášení</em>;
  }

  if (aktivitaUživatel.vedu)
    return <></>;

  if (aktivitaUživatel.zamcena)
    return <>{zámeček}</>;

  if (aktivitaUživatel.obsazenost) {
    const volnoTyp = volnoTypZObsazenost(aktivitaUživatel.obsazenost);

    if (volnoTyp === "u" || volnoTyp === uživatel.pohlavi)
      return <FormTlačítko id={aktivita.id} typ={"prihlasit"} />;
    else if (volnoTyp === "f")
      return <>pouze ženská místa</>;
    else if (volnoTyp === "m")
      return <>pouze mužská místa</>;

    const prihlasovatelnaProSledujici = !aktivita.dite?.length && !aktivita.tymova;
    if (prihlasovatelnaProSledujici) {
      if (aktivitaUživatel.stavPrihlaseni === "sledujici")
        return <FormTlačítko id={aktivita.id} typ={"odhlasSledujiciho"} />;
      else
        return <FormTlačítko id={aktivita.id} typ={"prihlasSledujiciho"} />;
    }
  }
  return <></>;
};

Přihlašovátko.displayName = "Přihlašovátko";
