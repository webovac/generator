# Webovac Generator

Tool for generating empty Webovac modules, Nette components, services and Nextras ORM model files with basic structure.

## Instalation

Install to project with the same file structure as [webovac/project](https://www.github.com/webovac/project).

```bash
composer require webovac/generator
```

## Usage

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
$generator = new CmsGenerator;
$processor = new Processor($generator);
$processor->process(
	folders: [__DIR__ . '/../config/files'],
	appDir: __DIR__ . '/../app',
);
```
