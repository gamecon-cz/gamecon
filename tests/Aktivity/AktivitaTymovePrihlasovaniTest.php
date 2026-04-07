<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\AktivitaTym;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractUzivatelTestDb;

class AktivitaTymovePrihlasovaniTest extends AbstractUzivatelTestDb
{
    use ProbihaRegistraceAktivitTrait;

    private ?SystemoveNastaveni $systemoveNastaveni = null;
    private ?Aktivita $ctvrtfinale = null;
    private ?Aktivita $semifinaleA = null;
    private ?Aktivita $semifinaleB = null;
    private ?Aktivita $finale = null;
    private ?\Uzivatel $tymlidr = null;
    private ?\Uzivatel $clen1 = null;
    private ?\Uzivatel $clen2 = null;

    protected static bool $disableStrictTransTables = true;

    // Doctrine uses a separate PDO connection, so legacy mysqli transactions
    // would prevent Doctrine from seeing data inserted via legacy dbInsert.
    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    protected static function getInitData(): string
    {
        return file_get_contents(__DIR__ . '/data/aktivita_tymove_prihlasovani_test.csv');
    }

    protected function setUp(): void
    {
        parent::setUp();

        try {
            // Vyčistit registrace a týmy z minulého testu — data persistují
            // protože transakce jsou vypnuté (Doctrine potřebuje committnutá data).
            dbQuery('DELETE FROM akce_prihlaseni WHERE id_akce IN (1, 2, 3, 4, 5)');
            dbQuery('DELETE FROM akce_tym_prihlaseni');
            dbQuery('DELETE FROM akce_tym_akce');
            dbQuery('DELETE FROM akce_tym');

            $this->systemoveNastaveni = self::vytvorSystemoveNastaveni();
            $this->systemoveNastaveni->queryCache()->clear();
            $this->ctvrtfinale = Aktivita::zId(1, false, $this->systemoveNastaveni);
            $this->semifinaleA = Aktivita::zId(2, false, $this->systemoveNastaveni);
            $this->semifinaleB = Aktivita::zId(3, false, $this->systemoveNastaveni);
            $this->finale = Aktivita::zId(4, false, $this->systemoveNastaveni);

            $this->tymlidr = self::prihlasenyUzivatel();
            $this->clen1 = self::prihlasenyUzivatel();
            $this->clen2 = self::prihlasenyUzivatel();

            // Clear Doctrine identity map to avoid stale entities from previous test
            $this->systemoveNastaveni->kernel()->getContainer()
                ->get('doctrine.orm.entity_manager')->clear();
        } catch (\Throwable $throwable) {
            $this->tearDown();
            throw $throwable;
        }
    }

    /**
     * Založí tým na čtvrtfinále a přidá cestu semifinále A → finále.
     * Simuluje dvě API volání: zalozPrazdnyTym + potvrdVyberAktivit.
     */
    private function zalozTymSCestou(\Uzivatel $kapitan): AktivitaTym
    {
        $entityManager = $this->systemoveNastaveni->kernel()->getContainer()->get('doctrine.orm.entity_manager');
        $activityRepo = $entityManager->getRepository(\App\Entity\Activity::class);

        $tym = AktivitaTym::zalozPrazdnyTym($kapitan->id(), $this->ctvrtfinale->id(), true);
        $kodTymu = $tym->getKod();

        // Přidáme cestu semifinále A → finále přímo na Team entitu ještě před flush
        $scheduledInsertions = $entityManager->getUnitOfWork()->getScheduledEntityInsertions();
        $teamEntity = null;
        foreach ($scheduledInsertions as $entity) {
            if ($entity instanceof \App\Entity\Team && $entity->getKod() === $kodTymu) {
                $teamEntity = $entity;
                break;
            }
        }
        self::assertNotNull($teamEntity, 'Team entita musí být ve scheduled insertions');

        $teamEntity->addAktivita($activityRepo->find($this->semifinaleA->id()));
        $teamEntity->addAktivita($activityRepo->find($this->finale->id()));

        $entityManager->flush();
        $entityManager->clear();

        return AktivitaTym::najdiPodleKodu($this->ctvrtfinale->id(), $kodTymu);
    }

    public function testOdhlaseniPredPotvrzenim()
    {
        $tym = $this->zalozTymSCestou($this->tymlidr);
        $this->ctvrtfinale->prihlas($this->tymlidr, $this->tymlidr, tym: $tym);

        $this->ctvrtfinale->odhlas($this->tymlidr, $this->tymlidr, 'test');

        $tymClen1 = $this->zalozTymSCestou($this->clen1);
        $this->ctvrtfinale->prihlas($this->clen1, $this->clen1, tym: $tymClen1);
        self::assertTrue($this->ctvrtfinale->prihlasen($this->clen1));
    }

    public function testPrihlaseniTymlidra()
    {
        $tym = $this->zalozTymSCestou($this->tymlidr);
        $this->ctvrtfinale->prihlas($this->tymlidr, $this->tymlidr, tym: $tym);

        // je přihlášen na první kolo
        self::assertTrue($this->ctvrtfinale->prihlasen($this->tymlidr));

        // je přihlášen i na vybraná další kola (semifinále A + finále)
        $this->semifinaleA->refresh();
        $this->finale->refresh();
        self::assertTrue($this->semifinaleA->prihlasen($this->tymlidr));
        self::assertTrue($this->finale->prihlasen($this->tymlidr));

        // ale není přihlášen na nevybrané semifinále B
        $this->semifinaleB->refresh();
        self::assertFalse($this->semifinaleB->prihlasen($this->tymlidr));
    }

    public function testPrihlaseniTymu()
    {
        $tym = $this->zalozTymSCestou($this->tymlidr);
        $this->ctvrtfinale->prihlas($this->tymlidr, $this->tymlidr, tym: $tym);
        $this->ctvrtfinale->prihlas($this->clen1, $this->clen1, tym: $tym);

        $this->ctvrtfinale->refresh();
        $this->semifinaleA->refresh();
        $this->semifinaleB->refresh();
        $this->finale->refresh();

        foreach ([$this->tymlidr, $this->clen1] as $hrac) {
            self::assertTrue($this->ctvrtfinale->prihlasen($hrac));
            self::assertTrue($this->semifinaleA->prihlasen($hrac));
            self::assertTrue($this->finale->prihlasen($hrac));

            self::assertFalse($this->semifinaleB->prihlasen($hrac));
        }
    }

    public function testPrihlaseniDalsiho()
    {
        $tym = $this->zalozTymSCestou($this->tymlidr);
        $tym->nastavLimit(3);
        $this->ctvrtfinale->prihlas($this->tymlidr, $this->tymlidr, tym: $tym);
        $this->ctvrtfinale->prihlas($this->clen1, $this->clen1, tym: $tym);
        $this->ctvrtfinale->prihlas($this->clen2, $this->clen2, tym: $tym);

        $this->ctvrtfinale->refresh();
        $this->semifinaleA->refresh();
        $this->semifinaleB->refresh();
        $this->finale->refresh();

        self::assertTrue($this->ctvrtfinale->prihlasen($this->clen2));
        self::assertTrue($this->semifinaleA->prihlasen($this->clen2));
        self::assertTrue($this->finale->prihlasen($this->clen2));

        self::assertFalse($this->semifinaleB->prihlasen($this->clen2));
    }

    public function testOmezeniKapacity()
    {
        $tym = $this->zalozTymSCestou($this->tymlidr);
        $tym->nastavLimit(2);
        $this->ctvrtfinale->prihlas($this->tymlidr, $this->tymlidr, tym: $tym);
        $this->ctvrtfinale->prihlas($this->clen1, $this->clen1, tym: $tym);
        $this->expectException(\Chyba::class);
        $this->ctvrtfinale->prihlas($this->clen2, $this->clen2, tym: $tym);
    }

    // TODO další scénáře:
    //  nevalidní ne-první člen
    //    všechno se rollbackne
    //    (že přihlášení jednoho člověka háže výjimku např. při překrytí tady netřeba testovat)
}
