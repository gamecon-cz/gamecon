import { FunctionComponent } from "preact";
import { useEffect } from "preact/hooks";
import { NastaveniTymuModal, registrujDotahováníNastaveníTýmu } from "../program/components/vstupy/NastaveniTymuModal";
import { OdhlasitAktivituModal } from "../program/components/vstupy/OdhlasitAktivituModal";
import { UpozorneniNaVekModal } from "../program/components/vstupy/UpozorneniNaVekModal";
import { nastavModalNastaveníTýmu, otevřiUpozorněníNaVěk } from "../../store/program/slices/všeobecnéSlice";
import { načtiPřihlášenýUživatel } from "../../store/program/slices/přihlášenSlice";
import { načtiRok } from "../../store/program/slices/programDataSlice";
import { GAMECON_KONSTANTY } from "../../env";

export const AktivityApp: FunctionComponent = () => {
  useEffect(() => {
    registrujDotahováníNastaveníTýmu();
    void načtiPřihlášenýUživatel();
  }, []);

  useEffect(() => {
    window.preactMost.prihlaseniTymu.otevri = (aktivitaId, nazevAktivity) => {
      nastavModalNastaveníTýmu(aktivitaId, nazevAktivity);
      void načtiRok(GAMECON_KONSTANTY.ROCNIK);
    };
    window.preactMost.upozorneniNaVek.otevri = otevřiUpozorněníNaVěk;
    return () => {
      window.preactMost.prihlaseniTymu.otevri = undefined;
      window.preactMost.upozorneniNaVek.otevri = undefined;
    };
  }, []);

  return (
    <>
      <NastaveniTymuModal />
      <OdhlasitAktivituModal />
      <UpozorneniNaVekModal />
    </>
  );
};
