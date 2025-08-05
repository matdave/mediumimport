# Medium Blog Importer

This is a simple helper library to import posts from a Medium blog export.

## Usage

Install via composer:
```bash
composer require matdave/mediumimport
```

Create an import script
```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$mi = new \MatDave\MediumImport\Import("/path/to/core/", "config_key");
$mi->import(
    "/path/to/export/", // path to the unzipped export
    2, // ID of the template to use
    3 // ID of the parent to use
);
```