<?php

declare(strict_types=1);

namespace Webovac\Generator;

use DateTimeInterface;
use Nette\DI\Attributes\Inject;
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
use Nextras\Orm\Mapper\Dbal\Conventions\Conventions;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\StorageReflection\StringHelper;
use Stepapo\Model\Data\Collection;
use Stepapo\Model\Data\DataRepository;
use Stepapo\Model\Data\Item;
use Tracy\Dumper;
use Webovac\Core\Model\CmsEntity;
use Webovac\Core\Model\CmsMapper;
use Webovac\Core\Model\CmsRepository;
use Webovac\Generator\Config\Entity;
use Webovac\Generator\Config\File;
use Webovac\Generator\Config\Module;
use Webovac\Generator\Lib\Writer;


class ModelGenerator
{
	protected string $namespace;
	protected string $modelNamespace;
	protected string $name;
	protected string $lname;
	protected string $uname;
	protected string $traitName;
	private Writer $writer;


	public function __construct(
		private string $appNamespace,
		private string $buildNamespace,
		private Entity $entity,
		private ?Module $module = null,
	) {
		$this->name = $this->entity->name;
		$this->lname = lcfirst($this->name);
		$this->uname = StringHelper::underscore($this->name);
		$this->modelNamespace = "$this->buildNamespace\Model\\$this->name";
		$this->namespace = $this->module?->namespace
			? "{$this->module->namespace}\\{$this->module->name}\Model\\$this->name"
			: "$this->appNamespace\Model\\$this->name";
		$this->traitName = $this->entity->withTraits && $this->module ? "{$this->module->name}$this->name" : $this->name;
		$this->writer = new Writer;
	}


	public function createEntity(): PhpFile
	{
		$file = File::createPhp(
			name: $this->name,
			namespace: $this->modelNamespace,
			extends: CmsEntity::class,
		);
		$getDataClassMethod = (new Method('getDataClass'))
			->setPublic()
			->setReturnType('string')
			->setBody("return {$this->name}Data::class;");
		$namespace = Arrays::first($file->getNamespaces());
		$class = Arrays::first($namespace->getClasses());
		$namespace
			->addUse("$this->modelNamespace\\{$this->name}Data");
		$class
			->addMember($getDataClassMethod)
			->addComment("@method {$this->name}Data getData(bool \$neon = false, bool \$forCache = false, ?array \$select = null)");
		return $file;
	}


	public function createMapper(): PhpFile
	{
		$file = File::createPhp(
			name: "{$this->name}Mapper",
			namespace: $this->modelNamespace,
			extends: CmsMapper::class,
		);
		$namespace = Arrays::first($file->getNamespaces());
		$class = Arrays::first($namespace->getClasses());
		$getTableNameMethod = (new Method('getTableName'))
			->setPublic()
			->setReturnType('string')
			->setBody("return '$this->uname';");
		$class->addMember($getTableNameMethod);
		if ($this->entity->withConventions) {
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
		return $file;
	}


	public function createRepository(): PhpFile
	{
		$file = File::createPhp(
			name: "{$this->name}Repository",
			namespace: $this->modelNamespace,
			extends: CmsRepository::class,
		);
		$namespace = Arrays::first($file->getNamespaces());
		$class = Arrays::first($namespace->getClasses());
		$getEntityClassNamesMethod = (new Method('getEntityClassNames'))
			->setPublic()
			->setStatic()
			->setReturnType('array')
			->setBody("return [$this->name::class];");

		$class->addMember($getEntityClassNamesMethod);
		$class
			->addComment("@method $this->name[]|ICollection findAll()")
			->addComment("@method $this->name[]|ICollection findBy(array \$conds)")
			->addComment("@method $this->name|null getById(mixed \$id)")
			->addComment("@method $this->name|null getBy(array \$conds)")
			->addComment("@method $this->name createFromData({$this->name}Data \$data, ?$this->name \$original = null, ?CmsEntity \$parent = null, ?string \$parentName = null, ?Person \$person = null, ?\DateTimeInterface \$date = null, bool \$skipDefaults = false, bool \$getOriginalByData = false)");

		$namespace
			->addUse(ICollection::class)
			->addUse("$this->modelNamespace\\{$this->name}Data")
			->addUse(CmsEntity::class);

		if ($this->name !== 'Person') {
			$namespace->addUse("$this->buildNamespace\Model\Person\Person");
		}

		return $file;
	}


	public function createConventions(): PhpFile
	{
		$file = File::createPhp(
			name: "{$this->name}Repository",
			namespace: $this->modelNamespace,
			extends: Conventions::class,
		);
		$namespace = Arrays::first($file->getNamespaces());
		$class = Arrays::first($namespace->getClasses());
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
		$class
			->addMember($getStoragePrimaryKeyMethod)
			->addMember($getDefaultMappingsMethod);
		return $file;
	}


	public function createDataObject(): PhpFile
	{
		return File::createPhp(
			name: "{$this->name}Data",
			namespace: $this->modelNamespace,
			extends: Item::class,
		);
	}


	public function createDataRepository(): ?PhpFile
	{
		$file = File::createPhp(
			name: "{$this->name}DataRepository",
			namespace: $this->modelNamespace,
			extends: DataRepository::class,
		);
		$namespace = Arrays::first($file->getNamespaces());
		$class = Arrays::first($namespace->getClasses());
		$class
			->addComment("@method {$this->name}Data[]|Collection findByKeys(array \$keys)")
			->addComment("@method {$this->name}Data|null getByKey(mixed \$key)");
		$namespace
			->addUse(Collection::class)
			->addUse("$this->modelNamespace\\{$this->name}Data");
		return $file;
	}


	public function createEntityTrait(): PhpFile
	{
		$file = File::createPhp(
			name: $this->traitName,
			namespace: $this->modelNamespace,
			extends: !$this->entity->withTraits ? null : CmsEntity::class,
			type: $this->entity->withTraits ? TraitType::class : ClassType::class,
		);
		$namespace = Arrays::first($file->getNamespaces());
		$namespace
			->addUse(DateTimeImmutable::class);

		if ($this->name !== 'Person') {
			$namespace->addUse("$this->buildNamespace\Model\Person\Person");
		}
		return $file;
	}


	public function createMapperTrait(): PhpFile
	{
		return File::createPhp(
			name: "{$this->traitName}Mapper",
			namespace: $this->modelNamespace,
			extends: !$this->entity->withTraits ? null : CmsMapper::class,
			type: $this->entity->withTraits ? TraitType::class : ClassType::class,
		);
	}


	public function createRepositoryTrait(): PhpFile
	{
		return File::createPhp(
			name: "{$this->traitName}Repository",
			namespace: $this->modelNamespace,
			extends: !$this->entity->withTraits ? null : CmsRepository::class,
			type: $this->entity->withTraits ? TraitType::class : ClassType::class,
		);
	}


	public function createDataObjectTrait(): PhpFile
	{
		$file = File::createPhp(
			name: "{$this->traitName}Data",
			namespace: $this->modelNamespace,
			extends: !$this->entity->withTraits ? null : Item::class,
			type: $this->entity->withTraits ? TraitType::class : ClassType::class,
		);
		$namespace = Arrays::first($file->getNamespaces());
		$class = Arrays::first($namespace->getClasses());
		$class
			->setProperties([
				(new Property('id'))->setPublic()->setType('int')->setNullable(),
				(new Property('createdByPerson'))->setPublic()->setType('int|string')->setNullable(),
				(new Property('updatedByPerson'))->setPublic()->setType('int|string')->setNullable(),
				(new Property('createdAt'))->setPublic()->setType(DateTimeInterface::class)->setNullable(),
				(new Property('updatedAt'))->setPublic()->setType(DateTimeInterface::class)->setNullable(),
			]);
		return $file;
	}


	public function createDataRepositoryTrait(): PhpFile
	{
		return File::createPhp(
			name: "{$this->traitName}DataRepository",
			namespace: $this->modelNamespace,
			extends: !$this->entity->withTraits ? null : DataRepository::class,
			type: $this->entity->withTraits ? TraitType::class : ClassType::class,
		);
	}


	public function updateEntity(string $path): PhpFile
	{
		return $this->updateFile($path, "$this->namespace\\{$this->traitName}", $this->entity->entityImplements);
	}


	public function updateMapper(string $path): PhpFile
	{
		return $this->updateFile($path, "$this->namespace\\{$this->traitName}Mapper");
	}


	public function updateRepository(string $path): PhpFile
	{
		return $this->updateFile($path, "$this->namespace\\{$this->traitName}Repository", $this->entity->repositoryImplements);
	}


	public function updateDataObject(string $path): PhpFile
	{
		return $this->updateFile($path, "$this->namespace\\{$this->traitName}Data");
	}


	public function updateDataRepository(string $path): PhpFile
	{
		return $this->updateFile($path, "$this->namespace\\{$this->traitName}DataRepository");
	}


	public function updateDataModel(string $path): PhpFile
	{
		$file = PhpFile::fromCode(file_get_contents($path));

		/** @var PhpNamespace $namespace */
		$namespace = (Arrays::first($file->getNamespaces()));

		/** @var TraitType|ClassType $class */
		$class = Arrays::first($file->getClasses());
		$propertyName = "{$this->lname}Repository";
		$type = $this->entity->withTraits ? "$this->modelNamespace\\{$this->name}DataRepository" : "$this->namespace\\{$this->name}DataRepository";
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
		$type = $this->entity->withTraits ? "$this->modelNamespace\\{$this->name}Repository" : "$this->namespace\\{$this->name}Repository";
		$namespace->addUse($type);
		$comments[] = $comment;
		sort($comments);
		$class->setComment(implode("\n", $comments));

		return $file;
	}


	private function updateFile(string $path, string $trait, array $implements = []): PhpFile
	{
		return $this->writer->updateFile($path, $trait, $implements);
	}
}
