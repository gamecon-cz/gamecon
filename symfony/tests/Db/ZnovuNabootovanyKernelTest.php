<?php

declare(strict_types=1);

namespace App\Tests\Db;

use App\Tests\AbstractDatabaseKernelTestCase;

class ZnovuNabootovanyKernelTest extends AbstractDatabaseKernelTestCase
{
    public function testTransakceTestuPrezijeNovyKernel(): void
    {
        $login = 'api_test_' . uniqid();
        $this->connection()->executeStatement(
            "INSERT INTO uzivatele_hodnoty (login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele, pohlavi)
             VALUES (:login, 'Test', 'Kernel', :email, 'f')",
            [
                'login' => $login,
                'email' => $login . '@example.com',
            ],
        );

        static::bootKernel();

        self::assertTrue($this->connection()->isTransactionActive());
        self::assertSame(1, (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM uzivatele_hodnoty WHERE login_uzivatele = :login',
            [
                'login' => $login,
            ],
        ));
    }
}
