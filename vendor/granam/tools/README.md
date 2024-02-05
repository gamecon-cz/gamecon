# Value describer

```php
use namespace Granam\Tools\ValueDescriber;

// "instance of \stdClass'
echo ValueDescriber::describe(new \stdClass());

// "array {}"
echo ValueDescriber::describe([]);

// "resource"
echo ValueDescriber::describe(tmpfile());

// "123,123.45,'123','123.45',array {\n  0 => string(3) "bar"},instance of \stdClass"
echo ValueDescriber::describe(123, 123.45, '123', '123.45', ['bar'], new \stdClass());
```

# File upload exception

```php
if ($_FILES['user_attachment1']['error'] === UPLOAD_ERR_OK) {
    // upload successful
} else {
// File of name 'damn_big.png' has not been uploaded (The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form: 2000)
    throw new FileUploadException("File of name '{$_FILES['user_attachment1']['name']}' has not been uploaded", $_FILES['user_attachment1']['error']);
}
```

## Installation
```bash
composer require granam/tools
```