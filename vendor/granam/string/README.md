# Base value object with string only

```php
<?php
use Granam\String\StringObject;
use Granam\String\Exceptions\WrongParameterType;

$string = new StringObject(12345.678);
echo $string; // string '12345.678'

try {
  new StringObject(fopen('foo', 'rb'));
} catch (WrongParameterType $stringException) {
  // Expected scalar or object with \_\_toString method on strict mode, got resource.
  die('Something get wrong: ' . $stringException->getMessage());
}
```
