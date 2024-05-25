
const LOCAL_STORAGE_KLÍČ_CACHE_ZÁKLAD = "fetch_cache_";
const LOCAL_STORAGE_KLÍČ_CACHE_ETAG = "etag_";
const LOCAL_STORAGE_KLÍČ_CACHE_DATA = "data_";

const vytvořCacheLocalStorageKlíčeProUrl = (url: string) => {
  return {
    klíčEtag: LOCAL_STORAGE_KLÍČ_CACHE_ZÁKLAD
      + LOCAL_STORAGE_KLÍČ_CACHE_ETAG
      + url,
    klíčData: LOCAL_STORAGE_KLÍČ_CACHE_ZÁKLAD
      + LOCAL_STORAGE_KLÍČ_CACHE_DATA
      + url,
  };
};

const nastavCache = (url: string, etag: string | undefined, data: any) => {
  const { klíčData, klíčEtag } = vytvořCacheLocalStorageKlíčeProUrl(url);
  localStorage.removeItem(klíčEtag);
  localStorage.removeItem(klíčData);
  if (etag) {
    localStorage.setItem(klíčData, JSON.stringify(data));
    localStorage.setItem(klíčEtag, etag);
  } else {
    console.warn(`pokus o uložení dat bez etagu pro URL: ${url}`);
  }
};

const získejEtagZCache = (url: string) => {
  const { klíčEtag, klíčData } = vytvořCacheLocalStorageKlíčeProUrl(url);

  return (
    // Pokud by se nějak stalo že nemám data, 
    //   tak se budu tvářit že nemám i etag protože nemám z čeho obnovit cache
    localStorage.getItem(klíčData) !== null
      ? localStorage.getItem(klíčEtag)
      : undefined
  ) ?? undefined;
};

const získejDataZCache = (url: string) => {
  const { klíčData } = vytvořCacheLocalStorageKlíčeProUrl(url);
  const str = localStorage.getItem(klíčData);
  if (!str) {
    console.error(`nezdařený pokus o získání dat z cache s url: ${url}`);
    return undefined;
  }
  try {
    return JSON.parse(str);
  } catch (e) {
    console.error(`nezdařený pokus o rozparsování dat z cache s url: ${url} začatek dat: ${str.slice(0, 100)}`);
  }
};

/**
 * Server musí na daný endpoint mít implementované Etagy (viz api/aktivityProgram.php).
 * Na straně serveru stejně dojde ke všem operacím ale pokud nedojde ke změně dat (pozná se podle hashe)
 *   tak server neposílá žádné dana a pošle jen nezměněno což nám říká, že můžeme využít cache.
 */
export const fetchCachedJson = async (url: string, init?: RequestInit | undefined) => {
  const headers = {} as Record<string, string>;

  const etag = získejEtagZCache(url);
  if (etag) { headers["if-none-match"] = etag; }

  const response = await fetch(url, { ...init, headers: { ...init?.headers, ...headers } });

  if (response.status === 200) {
    const data = await response.json();
    const etag = response.headers.get("etag") ?? undefined;
    nastavCache(url, etag, data);
    return data;
  }

  if (response.status === 304) {
    // console.log(`data pro url nezměněna, načteno z cache. Url: ${url}`);
    return získejDataZCache(url);
  }

  console.error(`nepodařilo se získat json z url: ${url}`);
  return;
};

