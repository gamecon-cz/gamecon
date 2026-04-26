import { FunctionComponent } from "preact";
import { useEffect } from "preact/hooks";
import { NastaveniTymuModal, registrujDotahováníNastaveníTýmu } from "../program/components/vstupy/NastaveniTymuModal";
import { OdhlasitAktivituModal } from "../program/components/vstupy/OdhlasitAktivituModal";
import { nastavModalNastaveníTýmu } from "../../store/program/slices/všeobecnéSlice";
import { načtiPřihlášenýUživatel } from "../../store/program/slices/přihlášenSlice";

registrujDotahováníNastaveníTýmu();

export const AktivityApp: FunctionComponent = () => {
  useEffect(() => {
    void načtiPřihlášenýUživatel();
  }, []);

  useEffect(() => {
    window.preactMost.prihlaseniTymu.otevri = (aktivitaId, nazevAktivity) => {
      nastavModalNastaveníTýmu(aktivitaId, nazevAktivity);
    };
    return () => {
      window.preactMost.prihlaseniTymu.otevri = undefined;
    };
  }, []);

  return (
    <>
      <NastaveniTymuModal />
      <OdhlasitAktivituModal />
    </>
  );
};
