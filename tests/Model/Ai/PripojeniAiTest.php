<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Ai;

use Gamecon\Ai\AnthropicDavkyAi;
use Gamecon\Ai\Exceptions\PripojeniAiNelzeUlozit;
use Gamecon\Ai\OpenAiDavkyAi;
use Gamecon\Ai\PoskytovatelAi;
use Gamecon\Ai\PripojeniAi;
use Gamecon\Tests\Db\AbstractTestDb;

class PripojeniAiTest extends AbstractTestDb
{
    private const string TOKEN = 'sk-ant-test-token-1234';

    /**
     * @test
     */
    public function tokenSeUloziZasifrovanyAPouzijeSeAzPoZvoleni(): void
    {
        $pripojeni = new PripojeniAi('gamecon_ostra');

        $pripojeni->uloz(PoskytovatelAi::ANTHROPIC, ' ' . self::TOKEN . ' ', null, \Uzivatel::SYSTEM);

        $ulozeny = (string) dbOneCol("SELECT zasifrovany_token FROM ai_pripojeni WHERE poskytovatel = 'anthropic'");
        self::assertStringNotContainsString(self::TOKEN, $ulozeny, 'Výpis tabulky token neprozradí');
        self::assertSame(self::TOKEN, \Sifrovatko::desifrujNeboNull($ulozeny), 'Uloží se bez mezer okolo');
        $stav = $pripojeni->dejStavy()[PoskytovatelAi::ANTHROPIC->value];
        self::assertTrue($stav->jePouzitelny());
        self::assertSame('1234', $stav->konecTokenu);
        self::assertSame(\Uzivatel::SYSTEM, $stav->zmenil);
        self::assertSame(AnthropicDavkyAi::VYCHOZI_MODEL, $stav->modelKPouziti());
        self::assertNull($pripojeni->dejPouzivaneDavky(), 'Uložený token se nepoužívá, dokud ho vývojář nezvolí');
        self::assertInstanceOf(AnthropicDavkyAi::class, $pripojeni->dejDavky(PoskytovatelAi::ANTHROPIC), 'Ověřit spojení jde i bez zvolení');

        $pripojeni->pouzivej(PoskytovatelAi::ANTHROPIC, \Uzivatel::SYSTEM);

        self::assertSame(PoskytovatelAi::ANTHROPIC, $pripojeni->dejPouzivaneDavky()?->poskytovatel());
    }

    /**
     * @test
     */
    public function pouzivaSeNejvysJedenPoskytovatel(): void
    {
        $pripojeni = new PripojeniAi('gamecon_ostra');
        $pripojeni->uloz(PoskytovatelAi::ANTHROPIC, self::TOKEN, null, \Uzivatel::SYSTEM);
        $pripojeni->uloz(PoskytovatelAi::OPENAI, 'sk-openai-test', 'gpt-test', \Uzivatel::SYSTEM);
        $pripojeni->pouzivej(PoskytovatelAi::ANTHROPIC, \Uzivatel::SYSTEM);

        $pripojeni->pouzivej(PoskytovatelAi::OPENAI, \Uzivatel::SYSTEM);

        self::assertInstanceOf(OpenAiDavkyAi::class, $pripojeni->dejPouzivaneDavky());
        self::assertSame(1, (int) dbOneCol('SELECT COUNT(*) FROM ai_pripojeni WHERE pouzivat = 1'));
        self::assertSame('gpt-test', $pripojeni->dejStavy()[PoskytovatelAi::OPENAI->value]->modelKPouziti());

        $pripojeni->pouzivej(null, \Uzivatel::SYSTEM);

        self::assertNull($pripojeni->dejPouzivaneDavky(), 'Vypnuto, texty jdou jen přes soubor');
        self::assertInstanceOf(AnthropicDavkyAi::class, $pripojeni->dejDavky(PoskytovatelAi::ANTHROPIC), 'Dávky dřívějšího poskytovatele jde dál číst');
        self::assertTrue($pripojeni->dejStavy()[PoskytovatelAi::OPENAI->value]->jePouzitelny(), 'Vypnutí token nesmaže');
    }

    /**
     * A copy of the database (ostrá → beta) brings the row along, beta must not send texts on the account of ostrá.
     *
     * @test
     */
    public function tokenZKopieJineDatabazeSeNepouzije(): void
    {
        $ostra = new PripojeniAi('gamecon_ostra');
        $ostra->uloz(PoskytovatelAi::ANTHROPIC, self::TOKEN, null, \Uzivatel::SYSTEM);
        $ostra->pouzivej(PoskytovatelAi::ANTHROPIC, \Uzivatel::SYSTEM);
        $beta = new PripojeniAi('gamecon_beta');

        self::assertNull($beta->dejPouzivaneDavky());
        self::assertNull($beta->dejDavky(PoskytovatelAi::ANTHROPIC));
        $stav = $beta->dejStavy()[PoskytovatelAi::ANTHROPIC->value];
        self::assertTrue($stav->maToken);
        self::assertFalse($stav->jeZTohotoProstredi);
        self::assertSame('gamecon_ostra', $stav->prostredi);
        self::assertNull($stav->konecTokenu, 'Ani konec cizího tokenu se neukáže');

        try {
            $beta->pouzivej(PoskytovatelAi::ANTHROPIC, \Uzivatel::SYSTEM);
            self::fail('Cizí token nejde zvolit');
        } catch (PripojeniAiNelzeUlozit) {
        }

        $beta->uloz(PoskytovatelAi::ANTHROPIC, 'sk-ant-beta-5678', null, \Uzivatel::SYSTEM);

        self::assertNull($beta->dejPouzivaneDavky(), 'Volba z ostré se s novým tokenem nepřenese, betu zapne až vývojář');
        self::assertSame('5678', $beta->dejStavy()[PoskytovatelAi::ANTHROPIC->value]->konecTokenu);
        self::assertNull($ostra->dejPouzivaneDavky(), 'Řádek teď patří betě');
    }

    /**
     * The Dev page shows who changed the row and when, a choice of the provider is such a change.
     *
     * @test
     */
    public function zvoleniZapiseKdoAKdyZmenilJenRadkyKterychSeTyka(): void
    {
        $pripojeni = new PripojeniAi('gamecon_ostra');
        $pripojeni->uloz(PoskytovatelAi::ANTHROPIC, self::TOKEN, null, \Uzivatel::SYSTEM);
        $pripojeni->uloz(PoskytovatelAi::OPENAI, 'sk-openai-test', null, \Uzivatel::SYSTEM);
        dbQuery("UPDATE ai_pripojeni SET zmeneno_kdy = '2026-01-01 10:00:00', zmenil = NULL");

        $pripojeni->pouzivej(PoskytovatelAi::OPENAI, \Uzivatel::SYSTEM);

        $stavy = $pripojeni->dejStavy();
        self::assertSame(\Uzivatel::SYSTEM, $stavy[PoskytovatelAi::OPENAI->value]->zmenil);
        self::assertGreaterThan(new \DateTimeImmutable('2026-01-02'), $stavy[PoskytovatelAi::OPENAI->value]->zmenenoKdy);
        self::assertNull($stavy[PoskytovatelAi::ANTHROPIC->value]->zmenil, 'Řádek, jehož použití se nezměnilo, zůstane');
        self::assertEquals(new \DateTimeImmutable('2026-01-01 10:00:00'), $stavy[PoskytovatelAi::ANTHROPIC->value]->zmenenoKdy);
    }

    /**
     * @test
     */
    public function modelZFormulareJdeOveritBezUlozeni(): void
    {
        $pripojeni = new PripojeniAi('gamecon_ostra');
        $pripojeni->uloz(PoskytovatelAi::OPENAI, 'sk-openai-test', 'gpt-ulozeny', \Uzivatel::SYSTEM);

        self::assertNotNull($pripojeni->dejDavky(PoskytovatelAi::OPENAI, 'gpt-jiny'));
        self::assertSame('gpt-ulozeny', $pripojeni->dejStavy()[PoskytovatelAi::OPENAI->value]->model, 'Ověření nic neukládá');
    }

    /**
     * @test
     */
    public function prazdnyTokenNechaUlozenyAZmeniJenModel(): void
    {
        $pripojeni = new PripojeniAi('gamecon_ostra');

        try {
            $pripojeni->uloz(PoskytovatelAi::OPENAI, '', 'gpt-test', \Uzivatel::SYSTEM);
            self::fail('Bez tokenu není co uložit');
        } catch (PripojeniAiNelzeUlozit) {
        }

        $pripojeni->uloz(PoskytovatelAi::OPENAI, 'sk-openai-test', 'gpt-test', \Uzivatel::SYSTEM);
        $pripojeni->uloz(PoskytovatelAi::OPENAI, '  ', 'gpt-jiny', \Uzivatel::SYSTEM);

        $stav = $pripojeni->dejStavy()[PoskytovatelAi::OPENAI->value];
        self::assertSame('test', $stav->konecTokenu);
        self::assertSame('gpt-jiny', $stav->model);

        $pripojeni->uloz(PoskytovatelAi::OPENAI, null, '', \Uzivatel::SYSTEM);

        self::assertNull($pripojeni->dejStavy()[PoskytovatelAi::OPENAI->value]->model, 'Prázdný model = výchozí');
    }

    /**
     * @test
     */
    public function smazanyTokenSePrestanePouzivat(): void
    {
        $pripojeni = new PripojeniAi('gamecon_ostra');
        $pripojeni->uloz(PoskytovatelAi::ANTHROPIC, self::TOKEN, null, \Uzivatel::SYSTEM);
        $pripojeni->pouzivej(PoskytovatelAi::ANTHROPIC, \Uzivatel::SYSTEM);

        $pripojeni->smaz(PoskytovatelAi::ANTHROPIC);

        self::assertNull($pripojeni->dejPouzivaneDavky());
        self::assertFalse($pripojeni->dejStavy()[PoskytovatelAi::ANTHROPIC->value]->maToken);
    }
}
