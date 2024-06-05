import { FunctionComponent } from "preact";
import { MutableRef, useRef } from "preact/hooks";
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


interface PotvrzeniModalProps {
  formRef: MutableRef<HTMLFormElement | null>;
  potvrzovatkoRef: MutableRef<any>;
  aktivitaId: number;
}

const PotvrzeniModal: FunctionComponent<PotvrzeniModalProps> = ({
  formRef,
  potvrzovatkoRef,
    aktivitaId,
}) => {
  const aktivita = useAktivita(aktivitaId);
  return (
    <div className="potvrzeniModalObal" ref={potvrzovatkoRef} onClick={(_) => {
      potvrzovatkoRef.current.style.display = "none";
    }}>
      <div className="potvrzeniModal">
        <h3>Opravdu se chceš odhlásit z aktivity{" " + aktivita?.nazev}?</h3>
        <a href="#" onClick={(e) => {
          formRef.current?.submit?.();
          e.preventDefault();
        }
        }>Odhlásit</a>
      </div>
    </div>
  );
};

const FormTlačítko: FunctionComponent<{ id: number; typ: FormTlačítkoTyp }> = ({
  id,
  typ,
}) => {
  const formRef = useRef<HTMLFormElement>(null);
  const potvrzovatkoRef = useRef<HTMLDivElement>(null);

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
    <PotvrzeniModal formRef={formRef} potvrzovatkoRef={potvrzovatkoRef} aktivitaId={id}/>
    <form ref={formRef} method="post" style="display:inline">
      <input type="hidden" name={typ} value={id}></input>
      <a
        href="#"
        onClick={(e) => {
          if (typ == "odhlasit" && potvrzovatkoRef.current != null) {
            potvrzovatkoRef.current.style.display = "block";
          }
          else {
            formRef.current?.submit?.();
          }
          e.preventDefault();
        }}
      >
        {text}
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
      return <FormTlačítko id={akitivitaId} typ={"odhlasit"} />;
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

  if (aktivita.zamcena) return <>{zámeček}</>;

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
