
## Quickstart

Instalace phpunitu globálně:

```bash
mkdir ~/bin # nemusí projít - nevadí
wget -O ~/phpunit https://phar.phpunit.de/phpunit-7.phar
chmod +x ~/phpunit
```

Instalace DbTestu pomocí Composeru TODO.

Vytvoření `phpunit.xml` v rootu projektu:

```xml
<phpunit bootstrap="tests/_bootstrap.php" colors="true">
    <testsuites>
        <testsuite>
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

Vytvoření zavaděče `tests/_bootstrap.php`:

```php
require_once __DIR__ . '/../vendor/autoload.php';

// přístup k DB - begin a rollback musí podporovat zanořování transakcí pomocí
// savepointů - viz kapitola DB wrapper
class MujDbWrapper implements Godric\DbTest\DbWrapper {
    function begin() { mujBegin(); }
    function escape($value) { return mujQuote($value); }
    function query($sql) { return mujQuery($sql); }
    function rollback() { mujRollback(); }
}

// pro zjednodušení vytvoříme alias třídy pro globální namespace
class DbTest extends Godric\DbTest\DbTest {}

Godric\DbTest\DbTest::setConnection(new MujDbWrapper);
```

Vytvoření testu `tests/FirstTest.php`:

```php
class FirstTest extends DbTest {

    // nepovinné - data pro naplnění DB
    static $initData = '
        # users
        id, name
        1,  John Doe
    ';

    function testExample() {
        // sem vložte práci s databází
        $this->assertTrue(true);
    }

}
```

Na závěr je potřeba vytvořit (prázdnou) testovací databázi se schématem odpovídajícím reálné databázi a nastavit k ní přístupy v `test/_bootstrap.php`. Stejné spojení musí využívat testované třídy i `MujDbWrapper`!

Hotovo! Po v rootu projektu stačí zavolat `phpunit` a testy se spustí:

```
godric@godric-laptop:~/mujprojekt$ phpunit
PHPUnit 7.0.1 by Sebastian Bergmann and contributors.

.                                                                   1 / 1 (100%)

Time: 182 ms, Memory: 10.00MB

OK (1 test, 1 assertion)
```

## DB wrapper

TODO: Viz SAVEPOINT v GameConu. Obdobný mechanismus podporuje jak postgres tak mysql.

Transakce chceme, protože nechceme čekání na fsync a nechceme zápisy na disk (resp. řešení jejich vypínání).

## TODO & nápady

- (?) Může být užitečné upravit tak, aby na začátku byl truncate všeho jednou a v případě failu commit dat kvůli debugování.
- (?) Místo ručního vytváření DB brát spojení a vytvářet na začátku kopii schématu zvolené databáze automaticky. Viz také, že obvykle chceme nějaká data (např. židle a práva v GC, typy uzlu v Hackers) v db mít respektive vytvořit na začátku.
