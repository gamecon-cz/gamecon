<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Jazyk;

use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;
use PHPUnit\Framework\TestCase;

class AktualniJazykTest extends TestCase
{
    protected function tearDown(): void
    {
        AktualniJazyk::reset();
        parent::tearDown();
    }

    public function testProvedVJazyceProvedeAkciVDanemJazyceAVratiPuvodni(): void
    {
        AktualniJazyk::nastav(Jazyk::ANGLICTINA);

        $jazykUvnitr = AktualniJazyk::provedVJazyce(Jazyk::CESTINA, static fn (): Jazyk => AktualniJazyk::jazyk());

        self::assertSame(Jazyk::CESTINA, $jazykUvnitr);
        self::assertSame(Jazyk::ANGLICTINA, AktualniJazyk::jazyk());
    }

    public function testProvedVJazyceVratiPuvodniJazykIPoVyjimce(): void
    {
        AktualniJazyk::nastav(Jazyk::ANGLICTINA);

        try {
            AktualniJazyk::provedVJazyce(Jazyk::CESTINA, static function (): never {
                throw new \RuntimeException('selhání generování');
            });
            self::fail('Výjimka z akce se má propsat ven.');
        } catch (\RuntimeException) {
        }

        self::assertSame(Jazyk::ANGLICTINA, AktualniJazyk::jazyk(), 'Jazyk návštěvníka se po chybě nesmí změnit.');
    }

    public function testProvedVJazyceBezNastavenehoJazykaZustaneUVychoziho(): void
    {
        AktualniJazyk::provedVJazyce(Jazyk::ANGLICTINA, static fn () => null);

        self::assertSame(Jazyk::vychozi(), AktualniJazyk::jazyk());
    }
}
