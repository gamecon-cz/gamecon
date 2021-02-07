<?php

/**
 * Testy pokrývající metody na přihlášení a registraci.
 */
class UzivatelPrihlaseniARegistraceTest extends GcDbTest {
    protected static $uzivatelTab = [
        'jmeno_uzivatele'      => 'a',
        'prijmeni_uzivatele'   => 'b',
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
    ];

    function uzivatel($prepis = []) {
        return array_merge(self::$uzivatelTab, $prepis);
    }

    function testRegistrujAPrihlas() {
        Uzivatel::registruj($this->uzivatel());

        $this->assertNotNull(Uzivatel::prihlas('a', 'a'), 'přihlášení loginem');
        $this->assertNotNull(Uzivatel::prihlas('a@b.c', 'a'), 'přihlášení heslem');
        $this->assertNull(Uzivatel::prihlas('a', 'b'), 'nepřihlášení špatnými údaji');
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessageRegExp / nepovolené /
     */
    function testNelzeZadatId() {
        Uzivatel::registruj($this->uzivatel(['id_uzivatele' => 5]));
    }

    function testUprav() {
        $id = Uzivatel::registruj($this->uzivatel());
        $u = Uzivatel::zId($id);

        $this->assertEquals('a b', $u->jmeno());

        $u->uprav(['jmeno_uzivatele' => 'jiné']);

        $this->assertEquals('jiné b', $u->jmeno());

        $u = Uzivatel::zId($id);
        $this->assertEquals('jiné b', $u->jmeno());
    }

    function testUpravNic() {
        $id = Uzivatel::registruj($this->uzivatel());
        $uData1 = Uzivatel::zId($id)->rawDb();

        Uzivatel::zId($id)->uprav([]);
        $uData2 = Uzivatel::zId($id)->rawDb();

        $nemenitHeslo = ['heslo' => null, 'heslo_kontrola' => null];
        Uzivatel::zId($id)->uprav($this->uzivatel($nemenitHeslo));
        $uData3 = Uzivatel::zId($id)->rawDb();

        $this->assertSame($uData1, $uData2, 'prázdná úprava data nezměnila');
        $this->assertSame($uData1, $uData3, 'úprava stejnými daty data nezměnila');
    }
}
