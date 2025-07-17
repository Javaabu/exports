---
title: ModelExport
---

## Basic Usage

You can extend the `ModelExport` class to easily create exports for a model. It will automatically include all non-hidden database columns of the model. The headings will be converted to title case.

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

## Including Relations

Use the `relationsToInclude` method to specify which relations to include in the export.
If the related models uses the `IsAdminModel` trait from [`javaabu/helpers`](http://github.com/Javaabu/helpers), it will automatically use the `admin_link_name` attribute to display the model in the export. Otherwise, the model(s) will be serialized. 

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
    
    public function relationsToInclude(): array
    {
        return [
            'roles',            
        ];
    }
}

```

## Modifying / re-ordering included attributes

To modify or re-order the included attributes in the export, override the `allowedAttributes` method. You can include relations as well along these attributes. 

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
    
    public function allowedAttributes(): array
    {
        return [
            'id',
            'name',
            'roles'
        ];   
    }
}

```

## Formatting a specific attribute

To format a specific attribute in the exported file, override the `formatValue` method. Remember to call `parent::formatValue` to still maintain formatting for other attributes. 

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
    
    public function formatValue(string $attribute, mixed $value): mixed
    {
        if ($attribute == 'role' && $value instance of \App\Models\Role::class) {
            return $value->description;
        }

        return parent::formatValue($attribute, $value);
    }
}
```

## Modifying the headings

To modify the headings, override the `headings` method. Remember to include all the headings in the correct order and to include a heading for each allowed attribute.

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
    
    public function headings(): array
    {
        return [
            'Id',
            'Name'
        ];
    }
}
```
