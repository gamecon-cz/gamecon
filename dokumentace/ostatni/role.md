

- Práva se dají naklikat v adminu. (Stačí vytvořit roli)
- /model/Role/Role.php


DB tabulky
role_seznam
role_texty_podle_uzivatele

v php
idckaTrvalychRoli
kategoriePodleVyznamu
nazevRolePodleId
dejIdckaRoliSOrganizatory
BfgrReport ???


Jde o to dostat novou roli migrací do databáze a ohlídat, že má v PHP svého zástupce jako konstantu s IDčkem SQL záznamu (je to divné, ale už to tak máme  ).

Můžeš to klidně udělat obráceně - že zlikviduješ roli Dobrovolník senior orga dobrovolníka seniora a podle toho, kde si co odebral, budeš vědět kde máš něco nové roli přidat. (upraveno)




