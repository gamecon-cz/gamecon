import { FunctionComponent } from "preact";
import { useRef } from "preact/hooks";
import { useAktivita, useUživatel } from "../../../../store/program/selektory";
import { volnoTypZObsazenost } from "../../../../utils";

const zámeček = `🔒`;

type TPřihlašovátkoProps = {
  akitivitaId: number;
};

type FormTlačítkoTyp =
  | "prihlasit"
  | "odhlasit"
  | "prihlasSledujiciho"
  | "odhlasSledujiciho";

const FormTlačítko: FunctionComponent<{ id: number; typ: FormTlačítkoTyp }> = ({
  id,
  typ,
}) => {
  const formRef = useRef<HTMLFormElement>(null);

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
    <form ref={formRef} method="post" style="display:inline">
      <input type="hidden" name={typ} value={id}></input>
      <a
        href="#"
        onClick={(e) => {
          formRef.current?.submit?.();
          e.preventDefault();
        }}
      >
        {text}
      </a>
    </form>
  );
};

export const Přihlašovátko: FunctionComponent<TPřihlašovátkoProps> = (
  props
) => {
  const { akitivitaId } = props;

  const uživatel = useUživatel();
  const { aktivita, aktivitaPřihlášen } = useAktivita(akitivitaId);

  if (!uživatel.prihlasen) return <></>;

  if (uživatel.gcStav === "nepřihlášen") return <></>;

  if (!aktivitaPřihlášen?.prihlasovatelna) return <></>;

  if (aktivita?.jeBrigadnicka && !uživatel.brigadnik) return <></>;

  if (
    aktivitaPřihlášen.stavPrihlaseni &&
    aktivitaPřihlášen.stavPrihlaseni !== "sledujici"
  ) {
    if (aktivitaPřihlášen.stavPrihlaseni === "prihlasen")
      return <FormTlačítko id={akitivitaId} typ={"odhlasit"} />;
    else if (aktivitaPřihlášen.stavPrihlaseni === "prihlasenADorazil")
      return <em>účast</em>;
    else if (aktivitaPřihlášen.stavPrihlaseni === "dorazilJakoNahradnik")
      return <em>jako náhradník</em>;
    else if (aktivitaPřihlášen.stavPrihlaseni === "prihlasenAleNedorazil")
      return <em>neúčast</em>;
    else if (aktivitaPřihlášen.stavPrihlaseni === "pozdeZrusil")
      return <em>pozdní odhlášení</em>;
  }

  if (aktivitaPřihlášen.vedu) return <></>;

  if (aktivitaPřihlášen.zamcena) return <>{zámeček}</>;

  if (aktivitaPřihlášen.obsazenost) {
    const volnoTyp = volnoTypZObsazenost(aktivitaPřihlášen.obsazenost);

    if (volnoTyp === "u" || volnoTyp === uživatel.pohlavi)
      return <FormTlačítko id={akitivitaId} typ={"prihlasit"} />;
    else if (volnoTyp === "f") return <>pouze ženská místa</>;
    else if (volnoTyp === "m") return <>pouze mužská místa</>;

    const prihlasovatelnaProSledujici =
      !aktivita?.dite?.length && !aktivita?.tymova;
    if (prihlasovatelnaProSledujici) {
      if (aktivitaPřihlášen.stavPrihlaseni === "sledujici")
        return <FormTlačítko id={akitivitaId} typ={"odhlasSledujiciho"} />;
      else return <FormTlačítko id={akitivitaId} typ={"prihlasSledujiciho"} />;
    }
  }
  return <></>;
};

Přihlašovátko.displayName = "Přihlašovátko";
