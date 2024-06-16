<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
alter table akce_seznam
    add team_limit int default null null comment 'uživatelem (vedoucím týmu) nastavený limit kapacity menší roven team_max, ale větší roven team_min. Prostřednictvím on update triggeru kontrolována tato vlastnost a je-li non-null, tak je tato kapacita nastavena do sloupce `kapacita`';
SQL
);

$this->q(<<<SQL
update akce_seznam
    set team_limit = kapacita
    where teamova = 1 and kapacita <> team_max;
SQL
);

$this->q(<<<SQL
create trigger trigger_check_and_apply_team_limit
    before update
    on akce_seznam
    for each row
begin
    if new.team_limit is not null then
    if new.team_limit < new.team_min or new.team_limit > new.team_max then
        set new.team_limit = null;
    else
        set new.kapacita = new.team_limit;
    end if;
end if;
end;

SQL
);
