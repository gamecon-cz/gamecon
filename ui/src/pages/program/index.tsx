import { FunctionComponent } from "preact";
import { useEffect } from "preact/hooks";
import { ProgramNáhled } from "./components/ProgramNáhled";
import { ProgramUživatelskéVstupy } from "./components/vstupy/Vstupy";
import { ProgramLegenda } from "./components/ProgramLegenda";
import { ProgramTabulka } from "./components/tabulka/ProgramTabulka";
import { inicializujProgramStore } from "../../store/program/inicializace";
import { načtiPřihlášenýUživatel } from "../../store/program/slices/přihlášenSlice";
import { OdhlasitAktivituModal } from "./components/vstupy/OdhlasitAktivituModal";

import "./program.less";

export const Program: FunctionComponent = () => {
  useEffect(inicializujProgramStore, []);

  useEffect(() => {
    void načtiPřihlášenýUživatel();
  }, []);

  return (
    <>
      <OdhlasitAktivituModal />
      <div class={"program-obal"}>
        <ProgramNáhled />
        <ProgramUživatelskéVstupy />
        <ProgramLegenda />
        <ProgramTabulka />
      </div>
    </>
  );
};
