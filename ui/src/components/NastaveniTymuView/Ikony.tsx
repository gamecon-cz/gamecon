/**
 * Ikony pro Nastavení týmu modal.
 * Inline SVG, žádné dependency.
 */
import { FunctionComponent } from "preact";

type IconProps = { size?: number };

export const IconBrushStroke: FunctionComponent<{ color?: string; className?: string }> = ({ color = "#E12B33", className }) => (
  <svg class={className} viewBox="0 0 220 28" preserveAspectRatio="none" aria-hidden="true">
    <path d="M4,16 C 40,4  90,22  140,12 C 170,8  195,18  216,10"
      stroke={color} stroke-width="14" stroke-linecap="round" fill="none" opacity=".85" />
    <path d="M14,22 C 60,14 130,24 200,16"
      stroke={color} stroke-width="3" stroke-linecap="round" fill="none" opacity=".55" />
  </svg>
);

export const IconDice: FunctionComponent<IconProps> = ({ size = 18 }) => (
  <svg width={size} height={size} viewBox="0 0 16 16" aria-hidden="true">
    <rect x="1.5" y="1.5" width="13" height="13" rx="2" stroke="currentColor" stroke-width="1.5" fill="none" />
    <circle cx="5" cy="5" r="1.2" fill="currentColor" />
    <circle cx="11" cy="5" r="1.2" fill="currentColor" />
    <circle cx="8" cy="8" r="1.2" fill="currentColor" />
    <circle cx="5" cy="11" r="1.2" fill="currentColor" />
    <circle cx="11" cy="11" r="1.2" fill="currentColor" />
  </svg>
);

export const IconCopy: FunctionComponent<IconProps> = ({ size = 14 }) => (
  <svg width={size} height={size} viewBox="0 0 14 14" aria-hidden="true">
    <rect x="3.5" y="3.5" width="8" height="9" stroke="currentColor" stroke-width="1.5" fill="none" />
    <path d="M2,2 L10,2 L10,3" stroke="currentColor" stroke-width="1.5" fill="none" />
  </svg>
);

export const IconArrowRight: FunctionComponent<IconProps> = ({ size = 14 }) => (
  <svg width={size} height={size} viewBox="0 0 14 14" aria-hidden="true">
    <path d="M2,7 L12,7 M8,3 L12,7 L8,11"
      stroke="currentColor" stroke-width="1.8" fill="none"
      stroke-linecap="round" stroke-linejoin="round" />
  </svg>
);
