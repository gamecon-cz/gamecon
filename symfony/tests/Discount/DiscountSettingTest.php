<?php

declare(strict_types=1);

namespace App\Tests\Discount;

use App\Discount\DiscountSetting;
use App\Tests\AbstractDatabaseKernelTestCase;

/**
 * The stored JSON refers to a setting by DiscountSetting case name, so a rename of a
 * settings key is a change in one typed place rather than a string nobody checks.
 *
 * That only holds while every case still points at a setting that exists — otherwise
 * the rule resolves to nothing and the discount quietly stops applying, which is the
 * failure the enum was introduced to prevent.
 */
class DiscountSettingTest extends AbstractDatabaseKernelTestCase
{
    public function testEverySettingReferencedByARuleResolves(): void
    {
        // Not "has a row in systemove_nastaveni": MODRE_TRICKO_ZDARMA_OD is derived
        // (3 × the activity bonus) and has no row of its own. What matters is that
        // dejHodnotu() returns a value, which is how a rule will read it.
        $systemoveNastaveni = \Gamecon\SystemoveNastaveni\SystemoveNastaveni::zGlobals(
            ROCNIK,
            new \Gamecon\Cas\DateTimeImmutableStrict(),
        );

        foreach (DiscountSetting::cases() as $setting) {
            $value = $systemoveNastaveni->dejHodnotu($setting->settingKey());

            $this->assertNotNull(
                $value,
                sprintf(
                    'DiscountSetting::%s ukazuje na nastavení "%s", které se nedá načíst. '
                    . 'Sleva by tiše přestala platit.',
                    $setting->name,
                    $setting->settingKey(),
                ),
            );
        }
    }

    public function testCaseNamesDoNotLookLikeSettingKeys(): void
    {
        // camelCase on purpose: a reader must not assume the JSON value has to match a
        // SCREAMING_CASE settings key, because it deliberately does not.
        foreach (DiscountSetting::cases() as $setting) {
            $this->assertNotSame(
                $setting->settingKey(),
                $setting->value,
                sprintf('Hodnota %s se shoduje s klíčem nastavení, to je matoucí', $setting->name),
            );
        }
    }
}
