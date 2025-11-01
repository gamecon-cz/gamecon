import { FunctionComponent } from "preact";
import { useEffect } from "preact/hooks";
import { ProgramNáhled } from "./components/ProgramNáhled";
import { ProgramUživatelskéVstupy } from "./components/vstupy/Vstupy";
import { ProgramLegenda } from "./components/ProgramLegenda";
import { ProgramTabulka } from "./components/tabulka/ProgramTabulka";
import { inicializujProgramStore } from "../../store/program/inicializace";
import { načtiPřihlášenýUživatel } from "../../store/program/slices/přihlášenSlice";
import { OdhlasitAktivituModal } from "./components/vstupy/OdhlasitAktivituModal";
import { ChybaBox } from "./components/ChybaBox";

import "./program.less";
import { NastaveniTymuModal } from "./components/vstupy/NastaveniTymuModal";

export const Program: FunctionComponent = () => {
  useEffect(inicializujProgramStore, []);

  useEffect(() => {
    void načtiPřihlášenýUživatel();
  }, []);

  return (
    <>
      <NastaveniTymuModal />
      <OdhlasitAktivituModal />
      <div class={"program-obal"}>
        <ProgramNáhled />
        <ProgramUživatelskéVstupy />
        <ProgramLegenda />
        <ProgramTabulka />
        <ChybaBox/>
      </div>
    </>
  );
};
