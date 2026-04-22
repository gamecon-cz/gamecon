import { ProgramStateCreator, useProgramStore } from "..";
import { fetchAktivitaTým, AktivitaKVyberu, ClenTymu, TymVSeznamu } from "../../../api/program";

export type NastaveniTymuData = {
  nazev: string,
  kod: number,
  muzeZalozitNovy: boolean,
  jeTrebaPredpripravit?: boolean,
  aktivityKPriprave?: AktivitaKVyberu[],
  jeKapitan?: boolean,
  verejny?: boolean,
  zamceny?: boolean,
  jeSmazatPoExpiraci?: boolean,
  casText?: string,
  casZalozeniMs?: number,
  casExpiraceMs?: number,
  limitTymu?: number | null,
  minKapacita?: number | null,
  maxKapacita?: number | null,
  clenove?: ClenTymu[],
  vsechnyTymy?: TymVSeznamu[],
};

export type VšeobecnéSlice = {
  všeobecné: {
    chyba?: string,
    filtryOtevřené: boolean,
    zvětšeno: boolean,
    kompaktní: boolean,
    modalOdhlásitAktivitaId?: number,
    nastaveniTymu?: {
      aktivitaId: number,
      nazevAktivity?: string,
      data?: NastaveniTymuData,
    },
  }
}

export const createVšeobecnéSlice: ProgramStateCreator<VšeobecnéSlice> = (_set, _get) => ({
  všeobecné: {
    filtryOtevřené: false,
    zvětšeno: false,
    kompaktní: false,
  },
});

export const nastavModalNastaveníTýmu = (aktivitaId?: number, nazevAktivity?: string) => {
  useProgramStore.setState(s => {
    if (aktivitaId)
      s.všeobecné.nastaveniTymu = { aktivitaId, nazevAktivity };
    else
      delete s.všeobecné.nastaveniTymu;
  }, undefined, "nastav modal nastaveni tymu");
}

export const dotáhniNastaveníTýmuProModal = async () => {
  const aktivitaId = useProgramStore.getState().všeobecné.nastaveniTymu?.aktivitaId;
  if (!aktivitaId) {
    // todo:
    console.warn("");
    return;
  }
  const data = await fetchAktivitaTým(aktivitaId);
  useProgramStore.setState(s => {
    s.všeobecné.nastaveniTymu = {
      aktivitaId,
      nazevAktivity: s.všeobecné.nastaveniTymu?.nazevAktivity,
      data: {
        kod: data.kod,
        muzeZalozitNovy: true,
        nazev: "",
        jeTrebaPredpripravit: data.jeTrebaPredpripravit,
        aktivityKPriprave: data.aktivityKPriprave,
        jeKapitan: data.jeKapitan,
        verejny: data.verejny,
        casText: data.casText,
        casZalozeniMs: data.casZalozeniMs,
        casExpiraceMs: data.casExpiraceMs,
        limitTymu: data.limitTymu,
        minKapacita: data.minKapacita,
        maxKapacita: data.maxKapacita,
        clenove: data.clenove,
        vsechnyTymy: data.vsechnyTymy,
      }
    };
  }, undefined, "dotáhni nastavení týmu");
}

export const nastavFiltryOtevřené = (hodnota: boolean) => {
  useProgramStore.setState(s => {
    s.všeobecné.filtryOtevřené = hodnota;
  }, undefined, "nastav filtry otevřené");
};

export const přepniZvětšeno = () => {
  useProgramStore.setState(s => {
    s.všeobecné.zvětšeno = !s.všeobecné.zvětšeno;
  }, undefined, "přepni zvětšeno");
};

export const nastavZvětšeno = (hodnota: boolean) => {
  useProgramStore.setState(s => {
    s.všeobecné.zvětšeno = hodnota;
  }, undefined, "nastav zvětšeno");
};

export const nastavKompaktní = (hodnota: boolean) => {
  useProgramStore.setState(s => {
    s.všeobecné.kompaktní = hodnota;
  }, undefined, "nastav kompaktní");
};

export const přepniKompaktní = () => {
  useProgramStore.setState(s => {
    s.všeobecné.kompaktní = !s.všeobecné.kompaktní;
  }, undefined, "přepni kompaktní");
};

/**
 * Bez id aktivity se modal skryje
 */
export const nastavModalOdhlásit = (aktivitaId?: number) => {
  useProgramStore.setState(s => {
    s.všeobecné.modalOdhlásitAktivitaId = aktivitaId;
  }, undefined, "nastav modal odhlásit");
};

export const nastavChyba = (chyba?: string) => {
  useProgramStore.setState(s => {
    s.všeobecné.chyba = chyba;
  }, undefined, "nastav chyba");
};
