
/* eslint-disable @typescript-eslint/no-unsafe-call */
/* eslint-disable @typescript-eslint/no-unsafe-member-access */
export const requestFullscreen = (element: Element) => {
  if ((element as any)?.requestFullscreen) {
    (element as any)?.requestFullscreen();
  } else if ((element as any)?.webkitRequestFullscreen) { /* Safari */
    (element as any)?.webkitRequestFullscreen();
  } else if ((element as any)?.msRequestFullscreen) { /* IE11 */
    (element as any)?.msRequestFullscreen();
  }
};
/* eslint-enable @typescript-eslint/no-unsafe-call */
/* eslint-enable @typescript-eslint/no-unsafe-member-access */


/* eslint-disable @typescript-eslint/no-unsafe-call */
/* eslint-disable @typescript-eslint/no-unsafe-member-access */
export const exitFullscreen = (element: Element) => {
  if ((element as any)?.exitFullscreen) {
    (element as any)?.exitFullscreen();
  } else if ((element as any)?.webkitExitFullscreen) { /* Safari */
    (element as any)?.webkitExitFullscreen();
  } else if ((element as any)?.msExitFullscreen) { /* IE11 */
    (element as any)?.msExitFullscreen();
  }
};
/* eslint-enable @typescript-eslint/no-unsafe-call */
/* eslint-enable @typescript-eslint/no-unsafe-member-access */
