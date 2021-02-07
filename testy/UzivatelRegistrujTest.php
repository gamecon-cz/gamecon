<?php

class UzivatelRegistrujTest extends GcDbTest {

    function testRegistrujAPrihlas() {
        Uzivatel::registruj([
            'jmeno_uzivatele'      => 'a',
            'prijmeni_uzivatele'   => 'a',
            'login_uzivatele'      => 'a',
            'email1_uzivatele'     => 'a@b.c',
            'pohlavi'              => 'f',
            'ulice_a_cp_uzivatele' => 'a 1',
            'mesto_uzivatele'      => 'a',
            'psc_uzivatele'        => '1',
            'stat_uzivatele'       => '1',
            'telefon_uzivatele'    => '1',
            'datum_narozeni'       => '2000-01-01',
            'heslo'                => 'a',
            'heslo_kontrola'       => 'a',
        ]);

        $this->assertNotNull(Uzivatel::prihlas('a', 'a'), 'přihlášení loginem');
        $this->assertNotNull(Uzivatel::prihlas('a@b.c', 'a'), 'přihlášení heslem');
        $this->assertNull(Uzivatel::prihlas('a', 'b'), 'nepřihlášení špatnými údaji');
    }

}
