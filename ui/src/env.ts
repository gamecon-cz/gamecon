/*
 * Konstanty předané ze serveru společně se scriptem.
 * Liší se pro testovací server
 */

declare global {
  interface Window { 
    BASE_PATH_PAGE?: string;
    BASE_PATH_API?: string;
    ROK?: number,
  }
}

/**
 * cesta k této stráce v rámci které se preact využívá.
 * například /web/program/
 */
export const BASE_PATH_PAGE = window?.BASE_PATH_PAGE ?? "/";


/**
 * cesta k api
 * například /web/api/
 */
export const BASE_PATH_API = window?.BASE_PATH_API ?? "/api/";


export const ROK = window?.ROK ?? 2022;

