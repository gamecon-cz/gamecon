type Category =
  | "creature"
  | "class"
  | "place"
  | "adjective"
  | "item"
  | "problem"
  | "title";

const words: Record<Category, string[]> = {
  adjective: [
    "Opilí",
    "Zmatení",
    "Prokletí",
    "Chaotičtí",
    "Bezzubí",
    "Mastní",
    "Legendární",
    "Nelegální",
    "Zapomenutí",
    "Přismahlí",
    "Podezřelí",
    "Ultra Temní",
    "Totálně Ztracení",
    "Kriticky Neúspěšní",
    "Finančně Nestabilní",
    "Morálně Flexibilní",
  ],

  creature: [
    "Goblini",
    "Koboldi",
    "Mimíci",
    "Kostlivci",
    "Draci",
    "Trolové",
    "Kultisti",
    "Kuřecí Wyverny",
    "Démoni z Wishe",
    "Daňoví Nekromanti",
    "Loot Goblini",
    "Sklepení Rasové",
    "Orkové na Brigádě",
  ],

  class: [
    "Bardové",
    "Paladini",
    "Warlockové",
    "Barbaři",
    "Rogueové",
    "Klerici",
    "Druidové",
    "Sorcererští Dlužníci",
    "PvP Mágové",
    "Multiclass Tragédie",
  ],

  place: [
    "z Bahenní Lhoty",
    "u Kritfailu",
    "z Dolního Dungeonu",
    "z Taverny U Mimíka",
    "z Koboldího Sklepa",
    "od Posledního Checkpointu",
    "z Příkopu Zapomnění",
  ],

  item: [
    "Rozbitých Kostek",
    "Sedmi Sudů",
    "Jednoho Spellslotu",
    "Pochybného Ležáku",
    "Zakázaného Guláše",
    "Lootu Bílé Kvality",
    "Tří Neúspěšných Saving Throwů",
  ],

  problem: [
    "co nečetli pravidla",
    "co zapálili hospodu",
    "co mají permanentní disadvantage",
    "co bojovali s dveřma 40 minut",
  ],

  title: [
    "Reloaded",
    "Ultimate Edition",
    "s.r.o.",
    "Unlimited",
    "Remastered",
    "HD",
    "No Healer Run",
  ],
};

const random = <T>(arr: T[]): T => arr[Math.floor(Math.random() * arr.length)];

const chance = (percent: number): boolean => Math.random() * 100 < percent;

const templates: (() => string)[] = [
  () => `${random(words.adjective)} ${random(words.creature)}`,
  () => `${random(words.adjective)} ${random(words.class)}`,
  () => `${random(words.creature)} ${random(words.place)}`,
  () => `${random(words.class)} ${random(words.place)}`,
  () => `${random(words.adjective)} ${random(words.creature)} ${random(words.place)}`,
  () => `${random(words.creature)} ${random(words.problem)}`,
  () => `${random(words.class)} ${random(words.problem)}`,
  () => `Bratrstvo ${random(words.item)}`,
  () => `Řád ${random(words.item)}`,
  () => `${random(words.adjective)} ${random(words.class)} ${random(words.problem)}`,
  () => `${random(words.creature)} & ${random(words.class)}`,
  () => `${random(words.creature)} ${random(words.title)}`,
];

export const generujNahodnyNazevTymu = (): string => {
  let name = random(templates)();
  if (chance(35)) {
    name += ` ${random(words.title)}`;
  }
  return name;
};
