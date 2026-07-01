<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\DataPropertyGenerator;

use DateTimeInterface;
use Nette\InvalidArgumentException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Property;
use Nette\PhpGenerator\Visibility;
use Nette\Utils\Arrays;
use Nextras\Orm\StorageReflection\StringHelper;
use Stepapo\Model\Definition\Config\Foreign;
use Stepapo\Model\Definition\Config\Table;
use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Attribute\DefaultValue;
use Stepapo\Utils\Attribute\DontCache;
use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Attribute\SkipInManipulation;
use Stepapo\Utils\Attribute\Type;
use Stepapo\Utils\Attribute\ValueProperty;
use Webovac\Generator\Lib\BuildGenerator\BuildGenerator;
use Webovac\Generator\Lib\BuildModelGenerator\BuildModelGenerator;
use Webovac\Generator\Lib\SetupProvider\ISetupProvider;
use Webovac\Generator\Lib\SetupProvider\SetupProvider;
use Webovac\Generator\Lib\Writer;


class DataPropertyGenerator
{
	private string $namespace;
	private string $path;
	private SetupProvider $setupProvider;


	public function __construct(
		private string $name,
		private Table $table,
		private Writer $writer,
		ISetupProvider $setupProviderFactory,
	) {
		$this->setupProvider = $setupProviderFactory->create(
			name: $this->name
		);
		$this->namespace = $this->setupProvider->getNamespace(BuildGenerator::MODEL);
		$this->path = $this->setupProvider->getPath(BuildModelGenerator::DATA_OBJECT);
	}


	public function createSimple(): void
	{
		if (!($content = @file_get_contents($this->path))) {
			throw new InvalidArgumentException("File '$this->path' does not exist.");
		}
		$file = PhpFile::fromCode($content);
		/** @var PhpNamespace $namespace */
		$namespace = Arrays::first($file->getNamespaces());
		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		foreach ($this->table->columns as $column) {
			$foreign = $this->table->foreignKeys[$column->name] ?? null;
			$name = $column->getPhpName($foreign);
			$property = new Property($name);
			$type = $column->getPhpType($foreign);
			$t = $type;
			if ($this->table->getPhpName() === 'File') {
				$property->setNullable();
			}
			if ($foreign) {
				if (($type === 'File' && $this->name !== 'FileTranslation' && $foreign->schema === null) || $column->showData) {
					$property->addAttribute(Type::class, [new Literal("{$type}Data::class")]);
					$t = "$this->namespace\\$type\\{$type}Data";
					$namespace->addUse($t);
					$namespace->addUse(Type::class);
					if ($type !== 'File') {
						$t .= '|int|string';
					}
				} else {
					$t = 'int|string';
				}
				$property->setNullable();
			}
			if ($column->type === 'datetime') {
				$t = DateTimeInterface::class;
				$namespace->addUse($t);
			}
			$property->setType($t);
			if ($column->null || $name === 'createdAt') {
				$property->setNullable();
			}
			$property->setVisibility(Visibility::Public);
			if (($column->default !== null || $column->dataDefault !== null) && $column->default !== 'now') {
				$property->addAttribute(DefaultValue::class, [$column->default !== null ? $column->default : $column->dataDefault]);
				$namespace->addUse(DefaultValue::class);
			}
			$isPrimary = $this->table->primaryKey && in_array($column->name, $this->table->primaryKey->columns, true);
			if ($isPrimary) {
				$property->setNullable();
			}
			if ($column->keyProperty) {
				$property->addAttribute(KeyProperty::class);
				$namespace->addUse(KeyProperty::class);
			}
			if ($column->valueProperty) {
				$property->addAttribute(ValueProperty::class);
				$namespace->addUse(ValueProperty::class);
			}
			if ($column->skipInManipulation) {
				$property->addAttribute(SkipInManipulation::class);
				$namespace->addUse(SkipInManipulation::class);
			}
			if ($column->dontCache) {
				$property->addAttribute(DontCache::class);
				$namespace->addUse(DontCache::class);
			}
			$class->addMember($property);
		}
		$this->writer->write($this->path, $file);
	}


	public function createManyHasMany(Foreign $from, Foreign $to, bool $isMain = false): void
	{
		$file = PhpFile::fromCode((string) @file_get_contents($this->path));
		/** @var PhpNamespace $namespace */
		$namespace = Arrays::first($file->getNamespaces());
		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		$property = new Property($from->reverseName ?: (StringHelper::camelize($to->table) . "s"));
		$property->setType('array');
		$property->setNullable();
		$property->addComment('@var int[]');
		if ($from->reverseSkipInManipulation) {
			$property->addAttribute(SkipInManipulation::class);
			$namespace->addUse(SkipInManipulation::class);
		}
		if ($from->reverseDontCache) {
			$property->addAttribute(DontCache::class);
			$namespace->addUse(DontCache::class);
		}
		$class->addMember($property);
		$this->writer->write($this->path, $file);
	}


	public function createOneHasMany(Foreign $foreign): void
	{
		$file = PhpFile::fromCode((string) @file_get_contents($this->path));
		/** @var PhpNamespace $namespace */
		$namespace = Arrays::first($file->getNamespaces());
		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		$type = "$this->namespace\\{$this->table->getPhpName()}\\{$this->table->getPhpName()}Data";
		$property = new Property($foreign->reverseName ?: (StringHelper::camelize($this->table->name) . "s"));
		$property->setType('array');
		$property->setNullable();
		$property->addComment("@var {$this->table->getPhpName()}Data[]");
		$property->addAttribute(ArrayOfType::class, [new Literal("{$this->table->getPhpName()}Data::class")]);
		if ($this->table->name !== $foreign->table) {
			$namespace->addUse($type);
		} else {
			$property->setNullable();
		}
		if ($foreign->reverseSkipInManipulation) {
			$property->addAttribute(SkipInManipulation::class);
			$namespace->addUse(SkipInManipulation::class);
		}
		if ($foreign->reverseDontCache) {
			$property->addAttribute(DontCache::class);
			$namespace->addUse(DontCache::class);
		}
		$namespace->addUse(ArrayOfType::class);
		$class->addMember($property);
		$this->writer->write($this->path, $file);
	}


	public function sort(): void
	{
		$file = PhpFile::fromCode((string) @file_get_contents($this->path));
		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		$properties = $class->getProperties();
		$p = [];
		foreach ($properties as $property) {
			if ($property->getName() === 'id') {
				$p['primary'][] = $property;
			} elseif (str_contains($property->getType(), 'DateTime')) {
				$p['date'][] = $property;
			} elseif (str_contains($property->getType(), 'FileData')) {
				$p['file'][] = $property;
			} elseif (str_contains($property->getType(), 'int|string')) {
				$p['m:1'][] = $property;
			} elseif ($property->getComment() === '@var int[]') {
				$p['m:m'][] = $property;
			} elseif (str_contains($property->getType(), 'array')) {
				$p['1:m'][] = $property;
			} else {
				$p['simple'][] = $property;
			}
		}
		$sortedProperties = [];
		foreach (['primary', 'simple', 'date', 'file', 'm:1', '1:m', 'm:m'] as $type) {
			if (isset($p[$type])) {
				usort($p[$type], fn(Property $a, Property $b) => $a->getName() <=> $b->getName());
				foreach ($p[$type] as $property) {
					$sortedProperties[] = $property;
				}
			}
		}
		$class->setProperties($sortedProperties);
		$this->writer->write($this->path, $file);
	}
}
