<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\RoleMeaning;
use PHPUnit\Framework\TestCase;

class RoleMeaningTest extends TestCase
{
    public function testOrganizerRoles(): void
    {
        $this->assertTrue(RoleMeaning::ORGANIZATOR_ZDARMA->isOrganizer());
        $this->assertTrue(RoleMeaning::VYPRAVEC->isOrganizer());
        $this->assertTrue(RoleMeaning::BRIGADNIK->isOrganizer());
        $this->assertTrue(RoleMeaning::ZAZEMI->isOrganizer());
        $this->assertTrue(RoleMeaning::CFO->isOrganizer());
        $this->assertTrue(RoleMeaning::ADMIN->isOrganizer());
        $this->assertTrue(RoleMeaning::PUL_ORG_UBYTKO->isOrganizer());
        $this->assertTrue(RoleMeaning::MINI_ORG->isOrganizer());
    }

    public function testNonOrganizerRoles(): void
    {
        $this->assertFalse(RoleMeaning::PRIHLASEN->isOrganizer());
        $this->assertFalse(RoleMeaning::PRITOMEN->isOrganizer());
        $this->assertFalse(RoleMeaning::ODJEL->isOrganizer());
        $this->assertFalse(RoleMeaning::HERMAN->isOrganizer());
        $this->assertFalse(RoleMeaning::PARTNER->isOrganizer());
        $this->assertFalse(RoleMeaning::STREDECNI_NOC_ZDARMA->isOrganizer());
    }

    public function testAnyIsOrganizer(): void
    {
        $this->assertTrue(RoleMeaning::anyIsOrganizer([RoleMeaning::PRIHLASEN, RoleMeaning::VYPRAVEC]));
        $this->assertFalse(RoleMeaning::anyIsOrganizer([RoleMeaning::PRIHLASEN, RoleMeaning::HERMAN]));
        $this->assertFalse(RoleMeaning::anyIsOrganizer([]));
    }

    public function testBackedValues(): void
    {
        $this->assertSame('ORGANIZATOR_ZDARMA', RoleMeaning::ORGANIZATOR_ZDARMA->value);
        $this->assertSame('VYPRAVEC', RoleMeaning::VYPRAVEC->value);
        $this->assertSame('PRIHLASEN', RoleMeaning::PRIHLASEN->value);
    }

    public function testFromValue(): void
    {
        $meaning = RoleMeaning::from('ORGANIZATOR_ZDARMA');
        $this->assertSame(RoleMeaning::ORGANIZATOR_ZDARMA, $meaning);
    }

    public function testTryFromUnknownValue(): void
    {
        $meaning = RoleMeaning::tryFrom('NEEXISTUJE');
        $this->assertNull($meaning);
    }

    public function testLabel(): void
    {
        $this->assertSame('Organizátor (zdarma)', RoleMeaning::ORGANIZATOR_ZDARMA->label());
        $this->assertSame('Vypravěč', RoleMeaning::VYPRAVEC->label());
        $this->assertSame('Přihlášen', RoleMeaning::PRIHLASEN->label());
    }
}
