import { ApiUpozorněníNaVěk } from "../../../../api/program";
import { proveďAkciAktivity } from "../../../../store/program/slices/programDataSlice";
import { nastavModalNastaveníTýmu, nastavModalOdhlásit, otevřiUpozorněníNaVěk } from "../../../../store/program/slices/všeobecnéSlice";

export type FormTlačítkoTyp =
  | "prihlasit"
  | "odhlasit"
  | "prihlasSledujiciho"
  | "odhlasSledujiciho"
  | "tym"
  | "zamceno"
  ;

/**
 * Co udělá odkaz v Přihlašovátku. Týmová aktivita otevře nastavení týmu (NastaveniTymuModal, tam se tým zakládá
 * i tam se k němu přidává), ostatní akci provedou rovnou. Kdo se hlásí a v den aktivity mu ještě nebude
 * její minimální věk, dostane napřed upozornění: přihlášení i nastavení týmu přijde až po potvrzení.
 * Bez JSX, aby šlo testovat ve vitestu.
 */
export const klikniNaPřihlašovátko = (
  aktivitaId: number,
  typ: FormTlačítkoTyp,
  tymova: boolean | undefined,
  upozorněníNaVěk: ApiUpozorněníNaVěk | undefined,
) => {
  if (tymova || typ === "tym") {
    přihlašPoUpozorněníNaVěk(typ, upozorněníNaVěk, () => { nastavModalNastaveníTýmu(aktivitaId); });
  } else if (typ === "zamceno") {
    return;
  } else if (typ === "odhlasit") {
    nastavModalOdhlásit(aktivitaId);
  } else {
    const akce = typ;
    přihlašPoUpozorněníNaVěk(typ, upozorněníNaVěk, () => { void proveďAkciAktivity(aktivitaId, akce); });
  }
};

const přihlašPoUpozorněníNaVěk = (
  typ: FormTlačítkoTyp,
  upozorněníNaVěk: ApiUpozorněníNaVěk | undefined,
  přihlaš: () => void,
) => {
  if (typ === "prihlasit" && upozorněníNaVěk) {
    otevřiUpozorněníNaVěk(upozorněníNaVěk, přihlaš);
  } else {
    přihlaš();
  }
};
