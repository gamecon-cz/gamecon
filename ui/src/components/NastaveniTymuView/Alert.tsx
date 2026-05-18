/**
 * Sdílený Alert primitive pro Nastavení týmu modal.
 * Tři typy: warning (žlutý) / danger (červený) / success (zelený).
 * Místo emoji ikony používá iconovaný čtvereček s textem.
 */
import { ComponentChildren, FunctionComponent } from "preact";

type AlertProps = {
  kind?: "warning" | "danger" | "success";
  icon?: string;
  children?: ComponentChildren;
};

export const Alert: FunctionComponent<AlertProps> = ({
  kind = "warning",
  icon = "!",
  children,
}) => {
  return (
    <div class={`gc-tm-alert gc-tm-alert--${kind}`}>
      <div class="gc-tm-alert__ico">{icon}</div>
      <div style={{ flex: 1, minWidth: 0 }}>{children}</div>
    </div>
  );
};
