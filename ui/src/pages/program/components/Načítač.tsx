import { useState } from "preact/hooks";
import React from "react";

export const PřekrývacíNačítač: React.FC<{
  zobrazit: boolean
}> = ({
  zobrazit
}) => {
  if (zobrazit)
    return <div style={{
      position: "absolute", top: 0, bottom: 0, right: 0, left: 0, backgroundColor: "rgba(255,255,255,.6)",
      display: "flex",
      flexDirection: "row",
      justifyContent: "center"
    }}>
      <div style={{ marginTop: 50 }}>
        <Načítač />
      </div>
    </div>;
  else
    return <></>;
};

export const Načítač: React.FC = () => {

  const [alterace] = useState(loaders[Date.now() % loaders.length]);

  return <>
    <div class="loader" />
    <style>
      {alterace}
    </style>
  </>;
};


// zdroj: css-loaders bars
const loader10css = `
.loader {
  height: 45px;
  aspect-ratio: 1.2;
  --c:no-repeat repeating-linear-gradient(90deg,#e22630 0 20%,#e2263000 0 40%);
  background:
    var(--c) 50% 0,
    var(--c) 50% 100%;
  background-size: calc(500%/6) 50%;
  animation: l10 1s infinite linear;
}
@keyframes l10 {
  33%  {background-position: 0   0   ,100% 100%}
  66%  {background-position: 0   100%,100% 0   }
  100% {background-position: 50% 100%,50%  0   }
}`;
const loader18css = `
.loader {
  width: 45px;
  aspect-ratio: 1;
  --c:no-repeat linear-gradient(#e22630 0 0);
  background: var(--c), var(--c), var(--c);
  animation:
    l18-1 1s infinite,
    l18-2 1s infinite;
}
@keyframes l18-1 {
 0%,100% {background-size:20% 100%}
 33%,66% {background-size:20% 20%}
}
@keyframes l18-2 {
 0%,33%   {background-position: 0    0,50% 50%,100% 100%}
 66%,100% {background-position: 100% 0,50% 50%,0    100%}
}`;
const loader19css = `
.loader {
  width: 45px;
  aspect-ratio: 1;
  --c: conic-gradient(from -90deg,#e22630 90deg,#e2263000 0);
  background: var(--c), var(--c);
  background-size: 40% 40%;
  animation: l19 1s infinite alternate;
}
@keyframes l19 {
 0%,
 10%  {background-position: 0 0,0            calc(100%/3)}
 50%  {background-position: 0 0,calc(100%/3) calc(100%/3)}
 90%,
 100% {background-position: 0 0,calc(100%/3) 0}
}`;
const loader20css = `
.loader {
  width: 45px;
  aspect-ratio: 1;
  --c: conic-gradient(from -90deg,#e22630 90deg,#e2263000 0);
  background: var(--c), var(--c);
  background-size: 40% 40%;
  animation: l20 1.5s infinite;
}
@keyframes l20 {
 0%,
 20%  {background-position: 0 0           ,0            calc(100%/3)}
 33%  {background-position: 0 0           ,calc(100%/3) calc(100%/3)}
 66%  {background-position: 0 calc(100%/3),calc(100%/3) 0  }
 80%,
 100% {background-position: 0 calc(100%/3),0            0  }
}`;
const loader24css = `
.loader {
  width: 45px;
  aspect-ratio: .8;
  --c:no-repeat repeating-linear-gradient(90deg,#e22630 0 20%,#e2263000 0 40%);
  background: var(--c),var(--c),var(--c),var(--c);
  background-size: 100% 21%;
  animation: l24 .75s infinite alternate;
}
@keyframes l24 {
 0%,
 10% {background-position:0 calc(0*100%/4),0 calc(1*100%/4),0 calc(2*100%/4),0 calc(3*100%/4)}
 25% {background-position:0 calc(0*100%/4),0 calc(1*100%/4),0 calc(2*100%/4),0 calc(4*100%/4)}
 50% {background-position:0 calc(0*100%/4),0 calc(1*100%/4),0 calc(3*100%/4),0 calc(4*100%/4)}
 75% {background-position:0 calc(0*100%/4),0 calc(2*100%/4),0 calc(3*100%/4),0 calc(4*100%/4)}
 90%,
 100%{background-position:0 calc(1*100%/4),0 calc(2*100%/4),0 calc(3*100%/4),0 calc(4*100%/4)}
}`;
const loader27css = `
.loader {
  width: 45px;
  aspect-ratio: 1;
  --c:no-repeat repeating-linear-gradient(90deg,#e22630 0 calc(100%/7),#e2263000 0 calc(200%/7));
  background: var(--c),var(--c),var(--c),var(--c);
  background-size: 140% 26%;
  animation: l27 .75s infinite linear;
}
@keyframes l27 {
 0%,20%   {background-position:0    calc(0*100%/3),100% calc(1*100%/3),0    calc(2*100%/3),100% calc(3*100%/3)}
 80%,100% {background-position:100% calc(0*100%/3),0    calc(1*100%/3),100% calc(2*100%/3),0    calc(3*100%/3)}
}`;

const loaders = [
  loader10css,
  loader18css,
  loader19css,
  loader20css,
  loader24css,
  loader27css,
];


