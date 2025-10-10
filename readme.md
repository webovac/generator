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
    driver: # pgsql|mysql
    database: # for mysql set default db name
```

## Usage

### Files

Analyzes project files in App folder, compares to file configuration and creates, updates or remove files if needed.

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

- `bin/generate.php`

```php
$container->getByType(Webovac\Generator\Lib\Processor::class)->process(
    folders: [__DIR__ . '/../config/files'],
    appDir: __DIR__ . '/../app',
    buildDir: __DIR__ . '/../build',
);
```

### Entity Properties

Analyzes Nextras ORM entities, compares to Definition configs and updates entity properties if needed.

- `config/definitions/db.neon`

See [Stepapo Model](https://github.com/stepapo/model#definitions)

- `Build\Model\Person\Person.php`

```php
namespace Build\Model\Person;

class Person extends Stepapo\Model\Orm\StepapoEntity
{
}
```

- `bin/processEntities.php`

```php
$folders = [__DIR__ . '/../config/definitions'];
$container->getByType(Webovac\Generator\Lib\PropertyProcessor::class)
    ->setCommentsBefore($folders)
    ->process($folders);
```

- results in updated entity file

```php
namespace Build\Model\Person;

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
class Person extends Stepapo\Model\Orm\StepapoEntity
{
}
```
