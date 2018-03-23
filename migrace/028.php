<?php

$this->q("
INSERT INTO `stranky` (`id_stranky`, `url_stranky`, `obsah`, `poradi`) VALUES ('28', 'o-prednaskach-na-gc', '#Přednášky\r\n\r\nTěšte se na přednášky, workshopy a panelové diskuze se známými i méně známými promotery.\r\n\r\n**Uvedené časy přednášek jsou orientační**. Upřesnění naleznete v anotaci.\r\n\r\n**Přednášky jsou bezplatné.**', '0');
INSERT INTO `akce_typy` (id_typu,typ_1p,typ_1pmn,typ_2pmn,typ_6p,url_typu,url_typu_mn,stranka_o,titul_orga,poradi) VALUES (3,'Přednáška','Přednášky','přednášek','přednáškách','prednaska','prednasky',28,'přednášející',11);
");
