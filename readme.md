# Webovac Generator

Tool for generating empty Webovac modules, Nette components, services and Nextras ORM model files with basic structure.

## Instalation

Install to project with the same file structure as [webovac/project](https://www.github.com/webovac/project).

1. run composer

```bash
composer require webovac/generator
```

2. `config/generator.neon`

```neon
extensions:
    webovac.generator: Webovac\Generator\DI\GeneratorExtension

webovac.generator:
    appDir: app
    appNamespace: App
    buildDir: build
    buildNamespace: Build
    driver: # pgsql|mysql
    database: # for mysql set default db name
```

## Usage

Goes through all generator definitions and generates corresponding classes and traits in Build and App namespaces.

```bash
php bin/generate.php
```

### Files

Analyzes project files in App and Build folders, compares to file configuration and creates, updates or remove files if needed.

- `config/files/app.neon`

```neon
modules:
    Person:
        components:
            PersonForm: [type: form]
        entities:
            Person:
        services:
            PersonMailer:
```

### Entity Properties

Analyzes Nextras ORM entities, compares to Definition configs and updates entity properties if needed.

- `config/definitions/db.neon`

See [Stepapo Model](https://github.com/stepapo/model#definitions)

- `Build\Model\Person\Person.php`

```php
class Person extends CmsEntity
{
}
```

Results in updated entity file

```php
/**
 * @property int $id {primary}
 *
 * @property string $firstName
 * @property string $lastName
 * @property string|null $email
 *
 * @property DateTimeImmutable $createdAt {default now}
 * @property DateTimeImmutable|null $updatedAt
 */
class Person extends CmsEntity
{
}
```
