import { GAMECON_KONSTANTY } from "../../env";

/**
 * Authenticated fetch wrapper for Symfony API calls.
 * Adds JWT Authorization header from GAMECON_KONSTANTY.JWT.
 */
export const symfonyFetch = async (
  path: string,
  options: RequestInit = {},
): Promise<Response> => {
  const url = GAMECON_KONSTANTY.BASE_PATH_SYMFONY_API + path;
  const headers: HeadersInit = {
    "Content-Type": "application/ld+json",
    "Accept": "application/ld+json",
    ...(options.headers as Record<string, string> ?? {}),
  };

  if (GAMECON_KONSTANTY.JWT) {
    (headers as Record<string, string>)["Authorization"] = `Bearer ${GAMECON_KONSTANTY.JWT}`;
  }

  return fetch(url, {
    ...options,
    headers,
  });
};
