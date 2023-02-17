import { FunctionComponent } from "preact";
import { useEffect } from "preact/hooks";
import { ProgramNáhled } from "./components/náhled/ProgramNáhled";
import { ProgramUživatelskéVstupy } from "./components/vstupy/Vstupy";
import { ProgramLegenda } from "./components/ProgramLegenda";
import { ProgramTabulka } from "./components/tabulka/ProgramTabulka";
import { useProgramStore } from "../../store/program";
import { inicializujProgramStore } from "../../store/program/inicializace";

import "./program.less";

export const Program: FunctionComponent = () => {
  const načtiPřihlášenýUživatel = useProgramStore(s => s.načtiPřihlášenýUživatel);

  useEffect(inicializujProgramStore, []);

  useEffect(() => {
    void načtiPřihlášenýUživatel();
  }, []);

  return (
    <div style={{ position: "relative" }}>
      <ProgramNáhled />
      <ProgramUživatelskéVstupy />
      <ProgramLegenda />
      <ProgramTabulka />
    </div>
  );
};
