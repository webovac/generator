<?php

declare(strict_types=1);

namespace Webovac\Generator;

use DateTimeInterface;
use Nette\DI\Attributes\Inject;
use Nette\InvalidArgumentException;
use Nette\PhpGenerator\Attribute;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Property;
use Nette\PhpGenerator\TraitType;
use Nette\Utils\Arrays;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\Relationships\ManyHasMany;
use Nextras\Orm\Relationships\OneHasMany;
use Nextras\Orm\StorageReflection\StringHelper;
use Stepapo\Model\Data\Collection;
use Stepapo\Model\Data\DataRepository;
use Stepapo\Model\Data\Item;
use Stepapo\Model\Definition\Config\Foreign;
use Stepapo\Model\Definition\Config\Table;
use Stepapo\Model\Orm\InternalProperty;
use Stepapo\Model\Orm\PrivateProperty;
use Webovac\Core\Model\CmsEntity;
use Webovac\Core\Model\CmsMapper;
use Webovac\Core\Model\CmsRepository;
use Webovac\Generator\Config\Implement;


class ModelGenerator
{
	protected string $namespace;
	protected string $modelNamespace;
	protected string $lname;
	protected string $uname;


	public function __construct(
		private string $name,
		private string $appNamespace,
		private string $buildNamespace,
		private ?string $moduleNamespace = null,
		private ?string $module = null,
		private bool $withTraits = true,
		private bool $withConventions = false,
		private string $mode = Generator::MODE_ADD,
	) {
		$this->lname = lcfirst($this->name);
		$this->uname = StringHelper::underscore($name);
		$this->modelNamespace = "$this->buildNamespace\Model\\$this->name";
		$this->namespace = $this->moduleNamespace . ($this->module ? "\\$this->module" : '') . "\Model\\$this->name";
	}


	public function createEntity(array $implements = []): PhpFile
	{
		$getDataClassMethod = (new Method('getDataClass'))
			->setPublic()
			->setReturnType('string')
			->setBody("return {$this->name}Data::class;");
		$class = (new ClassType("$this->name"))
			->setExtends(CmsEntity::class)
			->addMember($getDataClassMethod)
			->addComment("@method {$this->name}Data getData(bool \$neon = false, bool \$forCache = false, ?array \$select = null)");
		$namespace = (new PhpNamespace($this->modelNamespace))
			->addUse(CmsEntity::class)
			->addUse("$this->modelNamespace\\{$this->name}Data")
			->add($class);
		foreach ($implements as $implement) {
			$class->addImplement($implement->class);
			$namespace->addUse($implement->class);
		}
		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);
		return $file;
	}


	public function updateEntity(string $path, array $implements = []): PhpFile
	{
		return $this->updateFile(
			$path,
			"$this->namespace\\$this->module$this->name",
			$implements,
		);
	}


	public function createEntityTrait(): PhpFile
	{
		$class = ($this->withTraits ? new TraitType("$this->module$this->name") : new ClassType($this->name));

		$namespace = (new PhpNamespace($this->namespace))
			->add($class)
			->addUse(DateTimeImmutable::class);

		if (!$this->withTraits) {
			$class->setExtends(CmsEntity::class);
			$namespace->addUse(CmsEntity::class);
		}

		if ($this->name !== 'Person') {
			$namespace->addUse("$this->buildNamespace\Model\Person\Person");
		}

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function createMapper(): PhpFile
	{
		$getTableNameMethod = (new Method('getTableName'))
			->setPublic()
			->setReturnType('string')
			->setBody("return '$this->uname';");

		$class = (new ClassType("{$this->name}Mapper"))
			->setExtends(CmsMapper::class)
			->addMember($getTableNameMethod);

		$namespace = (new PhpNamespace($this->modelNamespace))
			->addUse(CmsMapper::class)
			->add($class);

		if ($this->module) {
			$trait =  "$this->namespace\\$this->module{$this->name}Mapper";
			$class->addTrait($trait);
			$namespace->addUse($trait);
		}

		if ($this->withConventions) {
			$createConventionsMethod = (new Method("{$this->name}Conventions"))
				->setProtected()
				->setReturnType(IConventions::class)
				->setBody(<<<EOT
return new {$this->name}Conventions(
	\$this->createInflector(),
	\$this->connection,
	\$this->getTableName(),
	\$this->getRepository()->getEntityMetadata(),
	\$this->cache,
);
EOT
				);
			$class->addMember($createConventionsMethod);
			$namespace->addUse(IConventions::class);
		}

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function updateMapper(string $path): PhpFile
	{
		return $this->updateFile(
			$path,
			"$this->namespace\\$this->module{$this->name}Mapper",
		);
	}


	public function createMapperTrait(): PhpFile
	{
		$class = ($this->withTraits ? new TraitType("$this->module{$this->name}Mapper") : new ClassType("{$this->name}Mapper"));

		$namespace = (new PhpNamespace($this->namespace))
			->add($class);

		if (!$this->withTraits) {
			$class->setExtends(CmsMapper::class);
			$namespace->addUse(CmsMapper::class);
		}

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function createRepository(array $implements = []): PhpFile
	{
		$getEntityClassNamesMethod = (new Method('getEntityClassNames'))
			->setPublic()
			->setStatic()
			->setReturnType('array')
			->setBody("return [$this->name::class];");

		$class = (new ClassType("{$this->name}Repository"))
			->setExtends(CmsRepository::class)
			->addMember($getEntityClassNamesMethod);

		$class
			->addComment("@method $this->name[]|ICollection findAll()")
			->addComment("@method $this->name[]|ICollection findBy(array \$conds)")
			->addComment("@method $this->name|null getById(mixed \$id)")
			->addComment("@method $this->name|null getBy(array \$conds)")
			->addComment("@method $this->name createFromData({$this->name}Data \$data, ?$this->name \$original = null, ?CmsEntity \$parent = null, ?string \$parentName = null, ?Person \$person = null, ?\DateTimeInterface \$date = null, bool \$skipDefaults = false, bool \$getOriginalByData = false)");

		$namespace = (new PhpNamespace($this->modelNamespace))
			->addUse(CmsRepository::class)
			->addUse(ICollection::class)
			->addUse("$this->modelNamespace\\{$this->name}Data")
			->addUse(CmsEntity::class)
			->add($class);

		if ($this->name !== 'Person') {
			$namespace->addUse("$this->buildNamespace\Model\Person\Person");
		}

		if ($this->module) {
			$trait = "$this->namespace\\$this->module{$this->name}Repository";
			$class->addTrait($trait);
			$namespace->addUse($trait);
		}
		foreach ($implements as $implement) {
			$class->addImplement($implement->class);
			$namespace->addUse($implement->class);
		}
		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function updateRepository(string $path, array $implements = []): PhpFile
	{
		return $this->updateFile(
			$path,
			"$this->namespace\\$this->module{$this->name}Repository",
			$implements,
		);
	}


	public function createRepositoryTrait(): PhpFile
	{
		$class = ($this->withTraits ? new TraitType("$this->module{$this->name}Repository") : new ClassType("{$this->name}Repository"));

		$namespace = (new PhpNamespace($this->namespace))
			->add($class);

		if (!$this->withTraits) {
			$class->setExtends(CmsRepository::class);
			$namespace->addUse(CmsRepository::class);
		}

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function createConventions(): PhpFile
	{
		$conventions = 'Nextras\Orm\Mapper\Dbal\Conventions\Conventions';
		$getStoragePrimaryKeyMethod = (new Method('getStoragePrimaryKey'))
			->setPublic()
			->setReturnType('array')
			->setBody("return [];");
		$getDefaultMappingsMethod = (new Method('getDefaultMappings'))
			->setPublic()
			->setReturnType('array')
			->setBody(<<<EOT
return [
	[
	
	],
	[
	
	],
	[]
];
EOT
			);
		$class = (new ClassType("{$this->name}Conventions"))
			->setExtends($conventions)
			->addMember($getStoragePrimaryKeyMethod)
			->addMember($getDefaultMappingsMethod);
		$namespace = (new PhpNamespace($this->namespace))
			->addUse($conventions)
			->add($class);
		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);
		return $file;
	}


	public function createDataObject(): PhpFile
	{
		$class = (new ClassType("{$this->name}Data"))
			->setExtends(Item::class);
		$namespace = (new PhpNamespace($this->modelNamespace))
			->addUse(Item::class)
			->add($class);
		if ($this->module) {
			$trait =  "$this->namespace\\$this->module{$this->name}Data";
			$class->addTrait($trait);
			$namespace->addUse($trait);
		}
		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);
		return $file;
	}


	public function updateDataObject(string $path): PhpFile
	{
		return $this->updateFile(
			$path,
			"$this->namespace\\$this->module{$this->name}Data",
		);
	}


	public function generateDataObjectTrait(): PhpFile
	{
		$class = ($this->withTraits ? new TraitType("$this->module{$this->name}Data") : new ClassType("{$this->name}Data"))
			->setProperties([
				(new Property('id'))->setPublic()->setType('int')->setNullable(),
				(new Property('createdByPerson'))->setPublic()->setType('int|string')->setNullable(),
				(new Property('updatedByPerson'))->setPublic()->setType('int|string')->setNullable(),
				(new Property('createdAt'))->setPublic()->setType(DateTimeInterface::class)->setNullable(),
				(new Property('updatedAt'))->setPublic()->setType(DateTimeInterface::class)->setNullable(),
			]);

		$namespace = (new PhpNamespace($this->namespace))
			->add($class);

		if (!$this->withTraits) {
			$class->setExtends(Item::class);
			$namespace->addUse(Item::class);
		}

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function createDataRepository(): ?PhpFile
	{
		if ($this->mode === Generator::MODE_REMOVE) {
			return null;
		}
		$class = (new ClassType("{$this->name}DataRepository"))
			->setExtends(DataRepository::class);
		$class
			->addComment("@method {$this->name}Data[]|Collection findByKeys(array \$keys)")
			->addComment("@method {$this->name}Data|null getByKey(mixed \$key)");
		$namespace = (new PhpNamespace($this->modelNamespace))
			->addUse(DataRepository::class)
			->addUse(Collection::class)
			->addUse("$this->modelNamespace\\{$this->name}Data")
			->add($class);
		if ($this->module) {
			$trait = "$this->namespace\\$this->module{$this->name}DataRepository";
			$class->addTrait($trait);
			$namespace->addUse($trait);
		}
		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function updateDataRepository(string $path): PhpFile
	{
		return $this->updateFile(
			$path,
			"$this->namespace\\$this->module{$this->name}DataRepository",
		);
	}


	public function createDataRepositoryTrait(): PhpFile
	{
		$class = ($this->withTraits ? new TraitType("$this->module{$this->name}DataRepository") : new ClassType("{$this->name}DataRepository"));

		$namespace = (new PhpNamespace($this->namespace))
			->add($class);

		if (!$this->withTraits) {
			$class->setExtends(DataRepository::class);
			$namespace->addUse(DataRepository::class);
		}

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function updateDataModel(string $path): PhpFile
	{
		$file = PhpFile::fromCode(file_get_contents($path));

		/** @var PhpNamespace $namespace */
		$namespace = (Arrays::first($file->getNamespaces()));

		/** @var TraitType|ClassType $class */
		$class = Arrays::first($file->getClasses());
		$propertyName = "{$this->lname}Repository";
		$type = $this->withTraits ? "$this->modelNamespace\\{$this->name}DataRepository" : "$this->namespace\\{$this->name}DataRepository";
		if ($this->mode === Generator::MODE_ADD) {
			$namespace
				->addUse($type)
				->addUse(Inject::class);
			$property = $class->hasProperty($propertyName)
				? $class->getProperty($propertyName)
				: $class->addProperty($propertyName);
			$property
				->setPublic()
				->setType($type)
				->setAttributes([new Attribute(Inject::class, [])]);
		} elseif ($this->mode === Generator::MODE_REMOVE && $class->hasProperty($propertyName)) {
			$namespace->removeUse($type);
			$class->removeProperty($propertyName);
		}

		return $file;
	}


	public function updateModel(string $path): PhpFile
	{
		$file = PhpFile::fromCode(file_get_contents($path));

		/** @var PhpNamespace $namespace */
		$namespace = Arrays::first($file->getNamespaces());

		/** @var TraitType|ClassType $trait */
		$class = Arrays::first($file->getClasses());
		$comment = "@property-read {$this->name}Repository \${$this->lname}Repository";
		$comments = explode("\n", $class->getComment() ?: '');
		$type = $this->withTraits ? "$this->modelNamespace\\{$this->name}Repository" : "$this->namespace\\{$this->name}Repository";
		if ($this->mode === Generator::MODE_ADD && !in_array($comment, $comments, true)) {
			$namespace->addUse($type);
			$comments[] = $comment;
		} elseif ($this->mode === Generator::MODE_REMOVE && in_array($comment, $comments, true)) {
			$namespace->removeUse($type);
			$comments = array_diff($comments, [$comment]);
		}
		sort($comments);
		$class->setComment(implode("\n", $comments));

		return $file;
	}


	private function updateFile(string $path, string $trait, array $implements = []): PhpFile
	{
		if (!($content = @file_get_contents($path))) {
			throw new InvalidArgumentException("Model with name '$this->name' does not exist.");
		}
		$file = PhpFile::fromCode($content);
		if (!$this->module) {
			return $file;
		}
		$class = Arrays::first($file->getClasses());
		$namespace = Arrays::first($file->getNamespaces());
		if ($this->mode === Generator::MODE_ADD && !array_key_exists($trait, $class->getTraits())) {
			$class->addTrait($trait);
			$namespace->addUse($trait);
		} elseif ($this->mode === Generator::MODE_REMOVE && array_key_exists($trait, $class->getTraits())) {
			$class->removeTrait($trait);
			$namespace->removeUse($trait);
		}
		$alreadyImplements = $class->getImplements();
		foreach ($implements as $implement) {
			if (in_array($implement->class, $alreadyImplements, true)) {
				continue;
			}
			$class->addImplement($implement->class);
			$namespace->addUse($implement->class);
		}
		return $file;
	}


	/** @param Implement[] $implements */
	public function checkFileImplements(string $path, array $implements = []): PhpFile
	{
		if (!($content = @file_get_contents($path))) {
			throw new InvalidArgumentException("Model with name '$this->name' does not exist.");
		}
		$file = PhpFile::fromCode($content);
		$class = Arrays::first($file->getClasses());
		$namespace = Arrays::first($file->getNamespaces());
		$alreadyImplements = $class->getImplements();
		foreach ($implements as $implement) {
			foreach ($implement->requires as $require) {
				if (!in_array($require, $alreadyImplements, true)) {
					$class->removeImplement($implement->class);
					$namespace->removeUse($implement->class);
				}
			}
		}
		$sortedImplements = $class->getImplements();
		sort($sortedImplements);
		$class->setImplements($sortedImplements);
		return $file;
	}


	public function getEntityComments(string $path, Table $table): ?string
	{
		if (!($content = @file_get_contents($path))) {
			return null;
		}
		$file = PhpFile::fromCode($content);
		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		return $class->getComment();
	}


	public function createEntityProperties(string $path, Table $table): PhpFile
	{
		if (!($content = @file_get_contents($path))) {
			throw new InvalidArgumentException("Model with name '$this->name' does not exist.");
		}
		$file = PhpFile::fromCode($content);
		/** @var PhpNamespace $namespace */
		$namespace = Arrays::first($file->getNamespaces());
		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		$comments = explode("\n", $class->getComment() ?: '');
		foreach ($comments as $key => $comment) {
			if (str_contains($comment, '@property')) {
				unset($comments[$key]);
			}
		}
		foreach ($table->columns as $column) {
			$foreign = $table->foreignKeys[$column->name] ?? null;
			$c = [];
			$c['property'] = '@property';
			$c['type'] = $column->getPhpType($foreign);
			if ($column->type === 'datetime') {
				$namespace->addUse(DateTimeImmutable::class);
			}
			$c['name'] = "\${$column->getPhpName($foreign)}";
			if (($default = $column->getPhpDefault()) !== null) {
				$c['default'] = "{default $default}";
			}
			$isPrimary = $table->primaryKey && in_array($column->name, $table->primaryKey->columns, true);
			if ($isPrimary) {
				$c['primary'] = "{primary}";
			}
			if ($foreign) {
				$c['foreign'] = "{m:1 {$foreign->getPhpTable()}" . ($foreign->reverseName ? "::\$$foreign->reverseName" : ", oneSided=true") . "}";
				if ($table->name !== $foreign->table) {
					$use = $this->module
						? "$this->appNamespace\\Module\\{$this->module}\\Model\\{$foreign->getPhpTable()}\\{$foreign->getPhpTable()}"
						: "$this->buildNamespace\\Model\\{$foreign->getPhpTable()}\\{$foreign->getPhpTable()}";
					$namespace->addUse($use);
				}
			}
			if ($column->private) {
				$namespace->addUse(PrivateProperty::class);
			}
			if ($column->internal) {
				$namespace->addUse(InternalProperty::class);
			}
			$comment = implode(' ', $c);
			if (!in_array($c, $comments, true)) {
				$comments[] = $comment;
			}
		}
		$class->setComment(implode("\n", $comments));
		return $file;
	}


	public function createEntityPropertyManyHasMany(string $path, Foreign $from, Foreign $to, bool $isMain = false): PhpFile
	{
		$file = PhpFile::fromCode(@file_get_contents($path));
		/** @var PhpNamespace $namespace */
		$namespace = Arrays::first($file->getNamespaces());
		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		$comments = explode("\n", $class->getComment() ?: '');
		$c = [];
		$c['property'] = '@property';
		$c['type'] = "ManyHasMany|{$to->getPhpTable()}[]";
		$c['name'] = "\$" . ($from->reverseName ? "$from->reverseName" : (StringHelper::camelize($to->table) . "s"));
		$c['foreign'] = "{m:m {$to->getPhpTable()}" . ($to->reverseName ? "::\$$to->reverseName" : "") . ($to->reverseOrder ? ", orderBy=$to->reverseOrder" : "") . ($isMain ? ", isMain=true" : "") . ($to->reverseName ? "" : ", oneSided=true") ."}";
		if ($from->table !== $to->table) {
			$use = $this->module
				? "$this->appNamespace\\Module\\{$this->module}\\Model\\{$to->getPhpTable()}\\{$to->getPhpTable()}"
				: "$this->buildNamespace\\Model\\{$to->getPhpTable()}\\{$to->getPhpTable()}";
			$namespace->addUse($use);
		}
		$comment = implode(' ', $c);
		if (!in_array($c, $comments, true)) {
			$comments[] = $comment;
		}
		$class->setComment(implode("\n", $comments));
		$namespace->addUse(ManyHasMany::class);
		return $file;
	}


	public function createEntityPropertyOneHasMany(string $path, Table $table, Foreign $foreign): PhpFile
	{
		$file = PhpFile::fromCode(@file_get_contents($path));
		/** @var PhpNamespace $namespace */
		$namespace = Arrays::first($file->getNamespaces());
		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		$comments = explode("\n", $class->getComment() ?: '');
		$c = [];
		$c['property'] = '@property';
		$c['type'] = "OneHasMany|{$table->getPhpName()}[]";
		$c['name'] = "\$" . ($foreign->reverseName ? "$foreign->reverseName" : (StringHelper::camelize($table->name) . "s"));
		$c['foreign'] = "{1:m {$table->getPhpName()}::$" . StringHelper::camelize(str_replace('_id', '', $foreign->keyColumn)) . ($foreign->reverseOrder ? ", orderBy=$foreign->reverseOrder" : "") . "}";
		if ($table->name !== $foreign->table) {
			$use = $this->module
				? "$this->appNamespace\\Module\\{$this->module}\\Model\\{$table->getPhpName()}\\{$table->getPhpName()}"
				: "$this->buildNamespace\\Model\\{$table->getPhpName()}\\{$table->getPhpName()}";
			$namespace->addUse($use);
		}
		$comment = implode(' ', $c);
		if (!in_array($c, $comments, true)) {
			$comments[] = $comment;
		}
		$class->setComment(implode("\n", $comments));
		$namespace->addUse(OneHasMany::class);
		return $file;
	}


	public function sortEntityProperties(string $path): PhpFile
	{
		$file = PhpFile::fromCode(@file_get_contents($path));
		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		$comments = explode("\n", $class->getComment() ?: '');
		$c = [];
		foreach ($comments as $comment) {
			if (str_contains($comment, '{primary}')) {
				$c['primary'][] = $comment;
			} elseif (str_contains($comment, 'DateTimeImmutable')) {
				$c['date'][] = $comment;
			} elseif (str_contains($comment, 'm:1')) {
				$c['m:1'][] = $comment;
			} elseif (str_contains($comment, '1:m')) {
				$c['1:m'][] = $comment;
			} elseif (str_contains($comment, 'm:m')) {
				$c['m:m'][] = $comment;
			} elseif (str_contains($comment, '@property')) {
				$c['simple'][] = $comment;
			} else {
				$c['other'][] = $comment;
			}
		}
		$comments = [];
		foreach (['primary', 'simple', 'date', 'm:1', '1:m', 'm:m', 'other'] as $type) {
			if (isset($c[$type])) {
				sort($c[$type]);
				$comments = $comments ? array_merge($comments, [''], $c[$type]) : $c[$type];
			}
		}
		$class->setComment(implode("\n", $comments));
		return $file;
	}
}
