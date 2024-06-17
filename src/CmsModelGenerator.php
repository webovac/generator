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
use Nextras\Orm\StorageReflection\StringHelper;
use Stepapo\Generator\ModelGenerator;
use Webovac\Core\Lib\Collection;
use Webovac\Core\Model\CmsData;
use Webovac\Core\Model\CmsDataRepository;
use Webovac\Core\Model\CmsEntity;
use Webovac\Core\Model\CmsMapper;
use Webovac\Core\Model\CmsRepository;


class CmsModelGenerator extends ModelGenerator
{
	protected string $namespace;
	protected string $modelNamespace;
	protected string $lname;
	protected string $uname;


	public function __construct(
		private string $name,
		private string $appNamespace,
		private string $moduleNamespace,
		private ?string $module = null,
		private bool $withConventions = false,
		private string $mode = CmsGenerator::MODE_ADD,
	) {
		parent::__construct($name, $appNamespace, $module, $withConventions);
		$this->lname = lcfirst($this->name);
		$this->uname = StringHelper::underscore($name);
		$this->modelNamespace = "$appNamespace\Model\\$this->name";
		$this->namespace = $this->moduleNamespace . ($this->module ? "\\$this->module" : '') . "\Model\\$this->name";
	}


	public function generateCmsEntity(array $implements): PhpFile
	{
		$getDataClassMethod = (new Method('getDataClass'))
			->setPublic()
			->setReturnType('string')
			->setBody("return {$this->name}Data::class;");
		$class = (new ClassType("$this->name"))
			->setExtends(CmsEntity::class)
			->addMember($getDataClassMethod)
			->addComment("@method {$this->name}Data getData()");
		$namespace = (new PhpNamespace($this->modelNamespace))
			->addUse(CmsEntity::class)
			->addUse("$this->modelNamespace\\{$this->name}Data")
			->add($class);
		if ($this->module) {
			$trait = "$this->namespace\\$this->module$this->name";
			$class->addTrait($trait);
			$namespace->addUse($trait);
		} else {
			$class
				->addComment("@property int \$id {primary}")
				->addComment("@property DateTimeImmutable \$createdAt {default now}")
				->addComment("@property DateTimeImmutable|null \$updatedAt")
				->addComment("@property Person|null \$createdByPerson {m:1 Person, oneSided=true}")
				->addComment("@property Person|null \$updatedByPerson {m:1 Person, oneSided=true}");
			$namespace->addUse(DateTimeImmutable::class);
			if ($this->name !== 'Person') {
				$namespace->addUse("$this->appNamespace\Model\Person\Person");
			}
		}
		foreach ($implements as $implement) {
			$class->addImplement($implement);
			$namespace->addUse($implement);
		}
		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);
		return $file;
	}


	public function generateUpdatedEntity(string $path, array $implements = []): PhpFile
	{
		return $this->modifyFileWithTrait(
			$path,
			"$this->namespace\\$this->module$this->name",
			fn() => $this->generateCmsEntity($implements),
		);
	}


	public function generateEntityTrait(): PhpFile
	{
		$trait = (new TraitType("$this->module$this->name"))
			->addComment("@property int \$id {primary}")
			->addComment("@property DateTimeImmutable \$createdAt {default now}")
			->addComment("@property DateTimeImmutable|null \$updatedAt")
			->addComment("@property Person|null \$createdByPerson {m:1 Person, oneSided=true}")
			->addComment("@property Person|null \$updatedByPerson {m:1 Person, oneSided=true}");

		$namespace = (new PhpNamespace($this->namespace))
			->add($trait)
			->addUse(DateTimeImmutable::class);

		if ($this->name !== 'Person') {
			$namespace->addUse("$this->appNamespace\Model\Person\Person");
		}

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateCmsMapper(): PhpFile
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


	public function generateUpdatedMapper(string $path): PhpFile
	{
		return $this->modifyFileWithTrait(
			$path,
			"$this->namespace\\$this->module{$this->name}Mapper",
			fn() => $this->generateCmsMapper(),
		);
	}


	public function generateMapperTrait(): PhpFile
	{
		$trait = (new TraitType("$this->module{$this->name}Mapper"));

		$namespace = (new PhpNamespace($this->namespace))
			->add($trait);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateCmsRepository(): PhpFile
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
			->addComment("@method $this->name createFromData({$this->name}Data \$data, ?$this->name \$original = null, ?CmsEntity \$parent = null, ?string \$parentName = null, ?Person \$person = null, ?\DateTimeInterface \$date = null, bool \$getOriginalByData = false)");

		$namespace = (new PhpNamespace($this->modelNamespace))
			->addUse(CmsRepository::class)
			->addUse(ICollection::class)
			->addUse("$this->modelNamespace\\{$this->name}Data")
			->addUse(CmsEntity::class)
			->add($class);

		if ($this->name !== 'Person') {
			$namespace->addUse("$this->appNamespace\Model\Person\Person");
		}

		if ($this->module) {
			$trait = "$this->namespace\\$this->module{$this->name}Repository";
			$class->addTrait($trait);
			$namespace->addUse($trait);
		}

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateUpdatedRepository(string $path): PhpFile
	{
		return $this->modifyFileWithTrait(
			$path,
			"$this->namespace\\$this->module{$this->name}Repository",
			fn() => $this->generateCmsRepository(),
		);
	}


	public function generateRepositoryTrait(): PhpFile
	{
		$class = (new TraitType("$this->module{$this->name}Repository"));

		$namespace = (new PhpNamespace($this->namespace))
			->add($class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateDataObject(): PhpFile
	{
		$class = (new ClassType("{$this->name}Data"))
			->setExtends(CmsData::class);
		$namespace = (new PhpNamespace($this->modelNamespace))
			->addUse(CmsData::class)
			->add($class);
		if ($this->module) {
			$trait =  "$this->namespace\\$this->module{$this->name}Data";
			$class->addTrait($trait);
			$namespace->addUse($trait);
		} else {
			$class->setProperties([
				(new Property('id'))->setPublic()->setType('int')->setNullable()->setValue(null),
				(new Property('createdByPerson'))->setPublic()->setType('int|string')->setNullable()->setValue(null),
				(new Property('updatedByPerson'))->setPublic()->setType('int|string')->setNullable()->setValue(null),
				(new Property('createdAt'))->setPublic()->setType(DateTimeInterface::class)->setNullable()->setValue(null),
				(new Property('updatedAt'))->setPublic()->setType(DateTimeInterface::class)->setNullable()->setValue(null),
			]);
		}
		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);
		return $file;
	}


	public function generateUpdatedDataObject(string $path): PhpFile
	{
		return $this->modifyFileWithTrait(
			$path,
			"$this->namespace\\$this->module{$this->name}Data",
			fn() => $this->generateDataObject(),
		);
	}


	public function generateDataObjectTrait(): PhpFile
	{
		$class = (new TraitType("$this->module{$this->name}Data"))
			->setProperties([
				(new Property('id'))->setPublic()->setType('int')->setNullable()->setValue(null),
				(new Property('createdByPerson'))->setPublic()->setType('int|string')->setNullable()->setValue(null),
				(new Property('updatedByPerson'))->setPublic()->setType('int|string')->setNullable()->setValue(null),
				(new Property('createdAt'))->setPublic()->setType(DateTimeInterface::class)->setNullable()->setValue(null),
				(new Property('updatedAt'))->setPublic()->setType(DateTimeInterface::class)->setNullable()->setValue(null),
			]);

		$namespace = (new PhpNamespace($this->namespace))
			->add($class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateDataRepository(): ?PhpFile
	{
		if ($this->mode === CmsGenerator::MODE_REMOVE) {
			return null;
		}
		$class = (new ClassType("{$this->name}DataRepository"))
			->setExtends(CmsDataRepository::class);
		$class
			->addComment("@method {$this->name}Data[]|Collection findAll()")
			->addComment("@method {$this->name}Data[]|Collection findBy(array \$conds)")
			->addComment("@method {$this->name}Data|null getById(\$id)")
			->addComment("@method {$this->name}Data|null getBy(array \$conds)");
		$namespace = (new PhpNamespace($this->modelNamespace))
			->addUse(CmsDataRepository::class)
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


	public function generateUpdatedDataRepository(string $path): PhpFile
	{
		return $this->modifyFileWithTrait(
			$path,
			"$this->namespace\\$this->module{$this->name}DataRepository",
			fn() => $this->generateDataRepository(),
		);
	}


	public function generateDataRepositoryTrait(): PhpFile
	{
		$class = (new TraitType("$this->module{$this->name}DataRepository"));

		$namespace = (new PhpNamespace($this->namespace))
			->add($class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateUpdatedDataModel(string $path): PhpFile
	{
		$file = PhpFile::fromCode(file_get_contents($path));

		/** @var PhpNamespace $namespace */
		$namespace = (Arrays::first($file->getNamespaces()));

		/** @var TraitType|ClassType $class */
		$class = Arrays::first($file->getClasses());
		$propertyName = "{$this->lname}Repository";
		if ($this->mode === CmsGenerator::MODE_ADD) {
			$namespace
				->addUse("$this->modelNamespace\\{$this->name}DataRepository")
				->addUse(Inject::class);
			$property = $class->hasProperty($propertyName)
				? $class->getProperty($propertyName)
				: $class->addProperty($propertyName);
			$property
				->setPublic()
				->setType("$this->modelNamespace\\{$this->name}DataRepository")
				->setAttributes([new Attribute(Inject::class, [])]);
		} elseif ($this->mode === CmsGenerator::MODE_REMOVE && $class->hasProperty($propertyName)) {
			$namespace->removeUse("$this->modelNamespace\\{$this->name}DataRepository");
			$class->removeProperty($propertyName);
		}

		return $file;
	}


	public function generateUpdatedModel(string $path): PhpFile
	{
		$file = PhpFile::fromCode(file_get_contents($path));

		/** @var PhpNamespace $namespace */
		$namespace = Arrays::first($file->getNamespaces());

		/** @var TraitType|ClassType $trait */
		$class = Arrays::first($file->getClasses());
		$comment = "@property-read {$this->name}Repository \${$this->lname}Repository";
		$comments = explode("\n", $class->getComment() ?: '');
		if ($this->mode === CmsGenerator::MODE_ADD && !in_array($comment, $comments, true)) {
			$namespace->addUse("$this->modelNamespace\\{$this->name}Repository");
			$comments[] = $comment;
		} elseif ($this->mode === CmsGenerator::MODE_REMOVE && in_array($comment, $comments, true)) {
			$namespace->removeUse("$this->modelNamespace\\{$this->name}Repository");
			$comments = array_diff($comments, [$comment]);
		}
		sort($comments);
		$class->setComment(implode("\n", $comments));

		return $file;
	}


	private function modifyFileWithTrait(string $path, string $trait, ?callable $create = null): PhpFile
	{
		if (!($content = @file_get_contents($path)) && $create && $this->mode === CmsGenerator::MODE_ADD) {
			return $create();
		}
		if (!$content) {
			throw new InvalidArgumentException("Model with name '$this->name' does not exist.");
		}
		$file = PhpFile::fromCode($content);
		if (!$this->module) {
			return $file;
		}
		$class = Arrays::first($file->getClasses());
		$namespace = Arrays::first($file->getNamespaces());
		if ($this->mode === CmsGenerator::MODE_ADD && !array_key_exists($trait, $class->getTraits())) {
			$class->addTrait($trait);
			$namespace->addUse($trait);
		} elseif ($this->mode === CmsGenerator::MODE_REMOVE && array_key_exists($trait, $class->getTraits())) {
			$class->removeTrait($trait);
			$namespace->removeUse($trait);
		}
		return $file;
	}


	public function shouldEntityBeDeleted(PhpFile $entity): bool
	{
		if (!$this->module) {
			return true;
		}
		/** @var ClassType $class */
		$class = Arrays::first($entity->getClasses());
		$hasPropertyAnnotation = false;
		if (str_contains($class->getComment(), '@property')) {
			$hasPropertyAnnotation = true;
		}
		return !$class->getTraits() && !$class->getProperties() && !$hasPropertyAnnotation;
	}
}