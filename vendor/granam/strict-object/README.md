[![Build Status](https://travis-ci.org/jaroslavtyc/granam-strict-object.svg?branch=travis-support-forced)](https://travis-ci.org/jaroslavtyc/granam-strict-object)

**A base object, throwing an exception in case of access to undefined property or method.**

1. [***You deserve to know...***](#you-deserve-to-know)
2. [**Usage**](#usage)
3. [**Install**](#Install)

## *You deserve to know...*

*Be lazy. Be smart.*

*To achieve that, you need to know.*

*Know about access to an undefined property, to an undefined method.*

*It can be anything, but at first it is a problem.*

*And you should be lazy enough to want to know that happened immediately, rather than searching logs after.*

## Usage

Just extend the object...

```php
use Granam\StrictObject\StrictObject;

class Foo extends StrictObject {

    public $everythingOk = true;
    // body
}
```

...and your code then immediately stops on mistakes like

```php
// test.php
$foo = new Foo();

if (!$foo->everythinkOk) {
    // Did you noticed the typo? Maybe not, but StrictObject will!
}
```
...which results into *PHP Fatal error: Uncaught exception 'ReadingAccess' on line 4 in file test.php*
Think twice about catching something like that! Remember, **you need to know**...

## Install

```
composer require granam/strict-object
```
