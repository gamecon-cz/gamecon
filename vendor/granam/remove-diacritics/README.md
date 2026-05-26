# Diacritics and special characters to ASCII

```php
<?php

use Granam\RemoveDiacritics\RemoveDiacritics;

// SIMPLE DIACRITICS REMOVAL
$portuguese = 'Luís argüia à Júlia que «brações, fé, chá, óxido, pôr, zângão» eram palavras do português.';
echo RemoveDiacritics::removeDiacritics($portuguese); // 'Luis arguia a Julia que «bracoes, fe, cha, oxido, por, zangao» eram palavras do portugues.'

$greece = 'Α α άλφα';
echo RemoveDiacritics::removeDiacritics($greece); // 'A a alpha'

// CONSTANT-LIKE CONVERSIONS
$danish = 'Høj bly gom vandt fræk sexquiz på wc';
echo RemoveDiacritics::toConstantLikeValue($danish); // 'hoj_bly_gom_vandt_fraek_sexquiz_pa_wc'

// NAMESPACED-NAMES TO snake_case
$classLikeName = 'Foo\\Bar::IHave_VIPCombinationsBAZ';
echo RemoveDiacritics::camelCaseToSnakeCasedBasename($classLikeName); // 'i_have_vip_combinations_baz'

```

And more, see [\Granam\Tests\RemoveDiacritics\RemoveDiacriticsTest](tests/RemoveDiacritics/RemoveDiacriticsTest.php) for
capabilities and results.
