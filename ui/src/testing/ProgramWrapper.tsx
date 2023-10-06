import { FunctionComponent } from "preact";
import { TESTOVACÍ_STAVY, TESTOVACÍ_STAVY_UŽIVATEL } from "./testovacíStavy";

type TProgramWrapperProps = {
  children: JSXElement;
};

/**
 * V dev serveru simuluje okolí gamecon webu pro testování programu
 */
export const ProgramWrapper: FunctionComponent<TProgramWrapperProps> = (
  props
) => {
  const { children } = props;

  return (
    <>
      <div class="menu">
        <div class="menu_obal">
          <div class="menu_obal2">
            <a href="." class="menu_nazev">
              GameCon
            </a>

            <div class="menu_uzivatel">
              <div class="menu_jmeno">uživatel</div>
              <div class="menu_uzivatelpolozky">
                <a href="finance">Finance</a>
                <a href="registrace">Nastavení</a>
                <a href="prihlaska">Přihláška na GC</a>
                <a href="#">Odhlásit</a>
                <form id="odhlasForm" method="post" action="prihlaseni"></form>
              </div>
            </div>

            <div class="menu_menu">
              <a href="program" class="menu_odkaz">
                program
              </a>
              <div class="menu_kategorie">
                <div class="menu_nazevkategorie">NASTAV</div>
                <div class="menu_polozky">
                  {TESTOVACÍ_STAVY.map((x) => (
                    <a
                      onClick={x.setter}
                      class="menu_polozka"
                      style={{ userSelect: "none" }}
                    >
                      {x.název}
                    </a>
                  ))}
                </div>
              </div>
              <div class="menu_kategorie">
                <div class="menu_nazevkategorie">Uživatel</div>
                <div class="menu_polozky">
                  {TESTOVACÍ_STAVY_UŽIVATEL.map((x) => (
                    <a
                      onClick={x.setter}
                      class="menu_polozka"
                      style={{ userSelect: "none" }}
                    >
                      {x.název}
                    </a>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      {children}
    </>
  );
};

ProgramWrapper.displayName = "ProgramWrapper";
