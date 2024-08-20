---
title: ModelExport
---

You can extend the `ModelExport` class to easily create exports for a model.

```php
<?php

namespace App\Exports;

use Javaabu\Exports\ModelExport;
use App\Models\User;

class UsersExport extends ModelExport
{

    public function modelClass(): string
    {
        return User::class;
    }
}

```
