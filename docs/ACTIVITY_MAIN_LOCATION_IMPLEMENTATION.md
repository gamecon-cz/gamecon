# Activity Main Location Implementation

## Problem

The `Activity` entity needed access to the main location in Symfony/Doctrine, but the relationship was stored in the `akce_lokace` join table with a `je_hlavni` flag. This table has additional columns (`id_akce_lokace`, `je_hlavni`) that cannot be represented in a simple Doctrine `ManyToMany` relationship.

Previously, when trying to map the ManyToMany relationship, Doctrine generated incorrect foreign keys:
- `FK_FE9439C581C06096` referenced non-existent `akce_seznam.id` instead of `akce_seznam.id_akce`
- `FK_FE9439C564D218E` referenced non-existent `lokace.id` instead of `lokace.id_lokace`

## Solution

Added a dedicated `mainLocation` property to the `Activity` entity with a new database column `id_hlavni_lokace` in the `akce_seznam` table. This provides Doctrine-managed access to the main location while keeping the legacy `akce_lokace` table working for the full location list.

## Implementation Details

### 1. Database Schema Change

**New Column**: `akce_seznam.id_hlavni_lokace`
- Type: `BIGINT UNSIGNED`
- Nullable: Yes
- Foreign Key: References `lokace (id_lokace)` ON DELETE SET NULL
- Index: Yes

### 2. Entity Changes

**File**: `symfony/src/Entity/Activity.php`

Added:
```php
#[ORM\ManyToOne(targetEntity: Location::class)]
#[ORM\JoinColumn(name: 'id_hlavni_lokace', referencedColumnName: 'id_lokace', nullable: true, onDelete: 'SET NULL')]
private ?Location $mainLocation = null;

public function getMainLocation(): ?Location
public function setMainLocation(?Location $mainLocation): self
```

### 3. Legacy Code Integration

**File**: `model/Aktivita/Aktivita.php`

Updated `nastavLokacePodleIds()` method to maintain the `id_hlavni_lokace` column:
```php
// Update id_hlavni_lokace in akce_seznam for Doctrine compatibility
$idHlavniLokaceValue = $hlavniLokaceId !== null ? (int)$hlavniLokaceId : 'NULL';
dbQuery(<<<SQL
    UPDATE akce_seznam
    SET id_hlavni_lokace = {$idHlavniLokaceValue}
    WHERE id_akce = {$this->id()}
    SQL,
);
```

This ensures that whenever locations are set via legacy code, the `id_hlavni_lokace` column is updated to match the main location in `akce_lokace`.

### 4. SQL Structure Class

**File**: `model/Aktivita/SqlStruktura/AkceSeznamSqlStruktura.php`

Added constant:
```php
public const ID_HLAVNI_LOKACE = 'id_hlavni_lokace';
```

### 5. Data Migration

**File**: `migrace/2026-02-01-populate-id-hlavni-lokace.sql`

Populates the new column from existing `akce_lokace.je_hlavni` data:
```sql
UPDATE akce_seznam
SET id_hlavni_lokace = (
    SELECT id_lokace
    FROM akce_lokace
    WHERE akce_lokace.id_akce = akce_seznam.id_akce
    ORDER BY je_hlavni DESC, id_lokace ASC
    LIMIT 1
)
WHERE EXISTS (
    SELECT 1
    FROM akce_lokace
    WHERE akce_lokace.id_akce = akce_seznam.id_akce
);
```

Logic matches legacy `idHlavniLokace()` method: `ORDER BY je_hlavni DESC, id_lokace ASC LIMIT 1`

### 6. Doctrine Configuration

**File**: `symfony/config/packages/doctrine.yaml`

Added schema filter to exclude the `akce_lokace` table from Doctrine schema management:
```yaml
schema_filter: ~^(?!akce_lokace$)~ # Ignore akce_lokace (managed by legacy code)
```

This prevents Doctrine from trying to drop or modify the `akce_lokace` table, which is still managed by legacy code.

## Files Modified

1. ✅ `symfony/src/Entity/Activity.php` - Added mainLocation property and methods
2. ✅ `model/Aktivita/Aktivita.php` - Updated nastavLokacePodleIds() to maintain id_hlavni_lokace
3. ✅ `model/Aktivita/SqlStruktura/AkceSeznamSqlStruktura.php` - Added ID_HLAVNI_LOKACE constant
4. ✅ `symfony/config/packages/doctrine.yaml` - Added schema_filter for akce_lokace
5. ✅ `migrace/2026-02-01-populate-id-hlavni-lokace.sql` - Data migration
6. ✅ `symfony/tests/Entity/ActivityMainLocationTest.php` - Unit tests

## Generated Migration

**File**: `symfony/migrations/structures/2026-02-01-095309-rename-me.sql`

Key changes:
- Adds `id_hlavni_lokace` column to `akce_seznam`
- Creates foreign key: `FK_2EE8EBF09E0F2899 FOREIGN KEY (id_hlavni_lokace) REFERENCES lokace (id_lokace)`
- Creates index: `IDX_2EE8EBF09E0F2899 ON akce_seznam (id_hlavni_lokace)`
- All foreign keys reference correct column names ✅

## Usage

### Symfony/Doctrine Code

```php
use App\Entity\Activity;
use App\Entity\Location;

// Get main location
$activity = $entityManager->find(Activity::class, $activityId);
$mainLocation = $activity->getMainLocation();
if ($mainLocation) {
    echo "Main location: " . $mainLocation->getNazev();
}

// Set main location
$location = $entityManager->find(Location::class, $locationId);
$activity->setMainLocation($location);
$entityManager->flush();
```

### Legacy Code

Legacy code continues to work unchanged. When `nastavLokacePodleIds()` is called, it automatically updates both:
1. The `akce_lokace` table (with `je_hlavni` flags)
2. The `akce_seznam.id_hlavni_lokace` column

```php
use Gamecon\Aktivita\Aktivita;

$aktivita = Aktivita::zId($activityId);
$aktivita->nastavLokacePodleIds([101, 102], 101); // Sets location 101 as main
// Both akce_lokace and akce_seznam.id_hlavni_lokace are updated automatically
```

## Data Consistency

The `id_hlavni_lokace` column is:
- **Source of truth for Doctrine**: Used by `Activity::getMainLocation()`
- **Derived from akce_lokace**: Updated automatically when `nastavLokacePodleIds()` is called
- **Populated from legacy data**: Migration script copies existing `je_hlavni` relationships

This approach maintains backward compatibility while providing Doctrine access.

## Testing

**Tests**: `symfony/tests/Entity/ActivityMainLocationTest.php`
- ✅ Main location can be set
- ✅ Main location can be null
- ✅ Main location is independent (can be changed)

All tests passing: **OK (3 tests, 7 assertions)**

## Benefits

1. **Doctrine Access**: Can query activities with their main location using Doctrine QueryBuilder
2. **Backward Compatible**: Legacy code continues to work unchanged
3. **Performance**: Direct foreign key relationship is faster than subquery with `ORDER BY je_hlavni DESC`
4. **Type Safety**: Doctrine entity provides type-safe access to Location object
5. **DRY**: No need to duplicate the `ORDER BY je_hlavni DESC, id_lokace ASC LIMIT 1` logic

## Future Work

To fully migrate location management to Doctrine, you would need to:
1. Create an `ActivityLocation` entity for the `akce_lokace` table
2. Map the `id_akce_lokace` and `je_hlavni` columns
3. Create a OneToMany relationship from Activity to ActivityLocation
4. Migrate legacy code to use Doctrine entities

For now, the hybrid approach (mainLocation in Doctrine, full list in legacy code) provides the best of both worlds.
