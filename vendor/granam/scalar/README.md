# Wrapping object for scalar (or null) only

[![Build Status](https://travis-ci.org/jaroslavtyc/granam-scalar.svg?branch=master)](https://travis-ci.org/jaroslavtyc/granam-scalar)

First of all, isn't sufficient PHP native scalar type hinting for you? It is available since [PHP 7.0](https://wiki.php.net/rfc/scalar_type_hints#proposed_php_version_s).

Sadly there is **no native** function able to cast or sanitize a value to scalar with **warning on value lost**.

For that reason, if we want to be sure about scalar type, a scalar converter and optionally a type-checking class are the only chance.

*Warning: The converter and so the wrapper class do not cast null - **null remains null**.*

```php
<?php
namespace Granam\Scalar;

use Granam\Scalar\Tools\ToScalar;

$scalar = new Scalar('foo');

// foo
echo $scalar;

$nullScalar = new Scalar(null);
// false
echo is_scalar($nullScalar->getValue());
// true
echo $nullScalar->getValue() === null;

// NULL
var_dump(ToScalar::toScalar(null));

try {
  ToScalar::toScalar(null, true /* explicitly strict */);
} catch (Tools\Exceptions\WrongParameterType $scalarException) {
  // Something get wrong: Expected scalar or object with __toString method on strict mode, got NULL.
  die('Something get wrong: ' . $scalarException->getMessage());
}
```

### The evil null

Why the NULL remains NULL by default?

Because it does lesser harm. Forcing an unknown value to has a specific type causes loss of previous "I do not know what it is" information.

If you needs scalar only, without NULL, you will have to do it by yourself by native PHP casting:

```php 
$variable = null;
$variable = (string)$variable;
```

So it is only up to you what type will the NULL become.