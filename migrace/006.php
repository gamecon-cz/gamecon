<?php

// Přidání tabulky pro medailonky vypravěčů

$this->q("

ALTER TABLE akce_prihlaseni ENGINE='InnoDB';
ALTER TABLE akce_prihlaseni_spec ENGINE='InnoDB';
ALTER TABLE akce_prihlaseni_stavy ENGINE='InnoDB';
ALTER TABLE akce_lokace ENGINE='InnoDB';

ALTER TABLE akce_prihlaseni ADD FOREIGN KEY (id_akce) REFERENCES akce_seznam(id_akce);
ALTER TABLE akce_prihlaseni ADD FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty(id_uzivatele);
ALTER TABLE akce_prihlaseni ADD FOREIGN KEY (id_stavu_prihlaseni) REFERENCES akce_prihlaseni_stavy(id_stavu_prihlaseni);

ALTER TABLE akce_prihlaseni_spec ADD FOREIGN KEY (id_akce) REFERENCES akce_seznam(id_akce);
ALTER TABLE akce_prihlaseni_spec ADD FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty(id_uzivatele);
ALTER TABLE akce_prihlaseni_spec ADD FOREIGN KEY (id_stavu_prihlaseni) REFERENCES akce_prihlaseni_stavy(id_stavu_prihlaseni);

-- zakomentováno kvůli nekonzistenci reálné DB s migrací
-- alter table akce_seznam
--   add foreign key (lokace) references akce_lokace(id_lokace),
--   add foreign key (typ) references akce_typy(id_typu),
--   add foreign key (zamcel) references uzivatele_hodnoty(id_uzivatele);

alter table akce_organizatori
  engine='InnoDB',
  add foreign key (id_akce) REFERENCES akce_seznam(id_akce),
  add foreign key (id_uzivatele) references uzivatele_hodnoty(id_uzivatele);

alter table tagy engine='InnoDB';

alter table akce_tagy
  engine='InnoDB',
  add foreign key (id_akce) references akce_seznam(id_akce),
  add foreign key (id_tagu) references tagy(id);

alter table stranky engine='InnoDB';
alter table akce_typy
  engine='InnoDb',
  add foreign key (stranka_o) references stranky(id_stranky);

alter table platby
  engine='InnoDB',
  add foreign key (id_uzivatele) references uzivatele_hodnoty(id_uzivatele),
  add foreign key (provedl) references uzivatele_hodnoty(id_uzivatele);

alter table r_prava_soupis engine='InnoDB';
alter table r_zidle_soupis engine='InnoDB';
alter table r_prava_zidle engine='InnoDB',
  add foreign key (id_zidle) references r_zidle_soupis(id_zidle),
  add foreign key (id_prava) references r_prava_soupis(id_prava);
alter table r_uzivatele_zidle engine='InnoDB',
  add foreign key (id_zidle) references r_zidle_soupis(id_zidle),
  add foreign key (id_uzivatele) references uzivatele_hodnoty(id_uzivatele);

alter table shop_predmety engine='InnoDB';
alter table shop_nakupy engine='InnoDB',
  add foreign key (id_uzivatele) references uzivatele_hodnoty(id_uzivatele),
  add foreign key (id_predmetu) references shop_predmety(id_predmetu);

alter table ubytovani engine='InnoDB',
  CHANGE `id_uzivatele` `id_uzivatele` int NOT NULL,
  -- ADD INDEX `id_uzivatele` (`id_uzivatele`),
  add foreign key (id_uzivatele) references uzivatele_hodnoty(id_uzivatele);

-- zakomentováno kvůli nekonzistenci reálné DB s migrací
-- alter table uzivatele_hodnoty
--   add foreign key (guru) references uzivatele_hodnoty(id_uzivatele);

");
