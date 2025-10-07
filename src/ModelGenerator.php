<?php

declare(strict_types=1);

namespace Webovac\Generator;

use Nette\DI\Attributes\Inject;
use Nette\PhpGenerator\Attribute;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\TraitType;
use Nette\Utils\Arrays;
use Nextras\Orm\StorageReflection\StringHelper;
use Stepapo\Model\Data\DataRepository;
use Webovac\Core\Model\CmsMapper;
use Webovac\Core\Model\CmsRepository;
use Webovac\Generator\Config\Entity;
use Webovac\Generator\Config\Module;
use Webovac\Generator\Lib\Writer;


class ModelGenerator
{
	private const string ENTITY = 'entity';
	private const string MAPPER = 'mapper';
	private const string REPOSITORY = 'repository';
	private const string CONVENTIONS = 'conventions';
	private const string DATA_OBJECT = 'dataObject';
	private const string DATA_REPOSITORY = 'dataRepository';
	private const string ENTITY_TRAIT = 'entityTrait';
	private const string MAPPER_TRAIT = 'mapperTrait';
	private const string REPOSITORY_TRAIT = 'repositoryTrait';
	private const string DATA_OBJECT_TRAIT = 'dataObjectTrait';
	private const string DATA_REPOSITORY_TRAIT = 'dataRepositoryTrait';

	private string $namespace;
	private string $basePath;
	private string $buildBasePath;
	private string $modelNamespace;
	private string $name;
	private string $lname;
	private string $uname;
	private string $traitName;
	private Writer $writer;
	private FileGenerator $fileGenerator;


	public function __construct(
		private string $appNamespace,
		private string $appDir,
		private string $buildNamespace,
		private string $buildDir,
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
		$this->basePath = "$this->appDir/" . ($module ? "Module/$module->name/" : '') . "Model";
		$this->buildBasePath = "$this->buildDir/Model/$this->name";
		$this->traitName = $this->entity->withTraits && $this->module ? "{$this->module->name}$this->name" : $this->name;
		$this->writer = new Writer;
		$this->fileGenerator = new FileGenerator;
	}


	public function createEntity(): void
	{
		$this->fileGenerator->write($this->getPath(self::ENTITY), $this->getConfig(self::ENTITY), [
			'name' => $this->name,
			'namespace' => $this->modelNamespace,
			'comments' => "@method {$this->name}Data getData(bool \$neon = false, bool \$forCache = false, ?array \$select = null)",
			'data' => "$this->modelNamespace\\{$this->name}Data",
			'getDataClassMethod.body' => "return {$this->name}Data::class;",
		]);
	}


	public function createMapper(): void
	{
		$this->fileGenerator->write($this->getPath(self::MAPPER), $this->getConfig(self::MAPPER), [
			'name' => "{$this->name}Mapper",
			'namespace' => $this->modelNamespace,
			'hideConventions' => !$this->entity->withConventions,
			'getDataClassMethod.body' => <<<EOT
return new {$this->name}Conventions(
	\$this->createInflector(),
	\$this->connection,
	\$this->getTableName(),
	\$this->getRepository()->getEntityMetadata(),
	\$this->cache,
);
EOT,
		]);
	}


	public function createRepository(): void
	{
		$this->fileGenerator->write($this->getPath(self::REPOSITORY), $this->getConfig(self::REPOSITORY), [
			'name' => "{$this->name}Repository",
			'namespace' => $this->modelNamespace,
			'comments' => [
				"@method $this->name[]|ICollection findAll()",
				"@method $this->name[]|ICollection findBy(array \$conds)",
				"@method $this->name|null getById(mixed \$id)",
				"@method $this->name|null getBy(array \$conds)",
				"@method $this->name createFromData({$this->name}Data \$data, ?$this->name \$original = null, ?CmsEntity \$parent = null, ?string \$parentName = null, ?Person \$person = null, ?\DateTimeInterface \$date = null, bool \$skipDefaults = false, bool \$getOriginalByData = false)",
			],
			'person' => "$this->buildNamespace\Model\Person\Person",
			'hidePerson' => $this->name === 'Person',
			'data' => "$this->modelNamespace\\{$this->name}Data",
			'getEntityClassNamesMethod.body' => "return [$this->name::class];",
		]);
	}


	public function createConventions(): void
	{
		$this->fileGenerator->write($this->getPath(self::CONVENTIONS), $this->getConfig(self::CONVENTIONS), [
			'name' => "{$this->name}Conventions",
			'namespace' => $this->modelNamespace,
			'getDefaultMappingsMethod.body' => <<<EOT
return [
	[
	
	],
	[
	
	],
	[]
];
EOT,
		]);
	}


	public function createDataObject(): void
	{
		$this->fileGenerator->write($this->getPath(self::DATA_OBJECT), $this->getConfig(self::DATA_OBJECT), [
			'name' => "{$this->name}Data",
			'namespace' => $this->modelNamespace,
		]);
	}


	public function createDataRepository(): void
	{
		$this->fileGenerator->write($this->getPath(self::DATA_REPOSITORY), $this->getConfig(self::DATA_REPOSITORY), [
			'name' => "{$this->name}DataRepository",
			'namespace' => $this->modelNamespace,
			'comments' => [
				"@method {$this->name}Data[]|Collection findByKeys(array \$keys)",
				"@method {$this->name}Data|null getByKey(mixed \$key)",
			],
			'data' => "$this->modelNamespace\\{$this->name}Data",
		]);
	}


	public function createEntityTrait(): void
	{
		$this->fileGenerator->write($this->getPath(self::ENTITY_TRAIT), $this->getConfig(self::ENTITY_TRAIT), [
			'name' => $this->traitName,
			'namespace' => $this->namespace,
			'extends' => $this->entity->withTraits ? null : CmsMapper::class,
			'type' => $this->entity->withTraits ? TraitType::class : ClassType::class,
		]);
	}


	public function createMapperTrait(): void
	{
		$this->fileGenerator->write($this->getPath(self::MAPPER_TRAIT),$this->getConfig(self::MAPPER_TRAIT), [
			'name' => "{$this->traitName}Mapper",
			'namespace' => $this->namespace,
			'extends' => $this->entity->withTraits ? null : CmsMapper::class,
			'type' => $this->entity->withTraits ? TraitType::class : ClassType::class,
		]);
	}


	public function createRepositoryTrait(): void
	{
		$this->fileGenerator->write($this->getPath(self::REPOSITORY_TRAIT), $this->getConfig(self::REPOSITORY_TRAIT), [
			'name' => "{$this->traitName}Repository",
			'namespace' => $this->namespace,
			'extends' => $this->entity->withTraits ? null : CmsRepository::class,
			'type' => $this->entity->withTraits ? TraitType::class : ClassType::class,
		]);
	}


	public function createDataObjectTrait(): void
	{
		$this->fileGenerator->write($this->getPath(self::DATA_OBJECT_TRAIT), $this->getConfig(self::DATA_OBJECT_TRAIT), [
			'name' => "{$this->traitName}Data",
			'namespace' => $this->namespace,
			'extends' => $this->entity->withTraits ? null : CmsRepository::class,
			'type' => $this->entity->withTraits ? TraitType::class : ClassType::class,
		]);
	}


	public function createDataRepositoryTrait(): void
	{
		$this->fileGenerator->write($this->getPath(self::DATA_REPOSITORY_TRAIT), $this->getConfig(self::MAPPER_TRAIT), [
			'name' => "{$this->traitName}DataRepository",
			'namespace' => $this->namespace,
			'extends' => $this->entity->withTraits ? null : DataRepository::class,
			'type' => $this->entity->withTraits ? TraitType::class : ClassType::class,
		]);
	}


	public function updateEntity(): void
	{
		$this->updateFile($this->getPath(self::ENTITY), "$this->namespace\\{$this->traitName}", $this->entity->entityImplements);
	}


	public function updateMapper(): void
	{
		$this->updateFile($this->getPath(self::MAPPER), "$this->namespace\\{$this->traitName}Mapper");
	}


	public function updateRepository(): void
	{
		$this->updateFile($this->getPath(self::REPOSITORY), "$this->namespace\\{$this->traitName}Repository", $this->entity->repositoryImplements);
	}


	public function updateDataObject(): void
	{
		$this->updateFile($this->getPath(self::DATA_OBJECT), "$this->namespace\\{$this->traitName}Data");
	}


	public function updateDataRepository(): void
	{
		$this->updateFile($this->getPath(self::DATA_REPOSITORY), "$this->namespace\\{$this->traitName}DataRepository");
	}


	public function updateDataModel(string $path): void
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

		$this->writer->write($path, $file);
	}


	public function updateModel(): void
	{
		$path = "$this->basePath/{$this->module->name}Orm.php";
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

		$this->writer->write($path, $file);
	}


	private function updateFile(string $path, string $trait, array $implements = []): void
	{
		$this->writer->updateFile($path, $trait, $implements);
	}


	private function getPath(string $key): string
	{
		return match($key) {
			self::ENTITY => "$this->buildBasePath/$this->name.php",
			self::MAPPER => "$this->buildBasePath/{$this->name}Mapper.php",
			self::REPOSITORY => "$this->buildBasePath/{$this->name}Repository.php",
			self::CONVENTIONS => "$this->basePath/{$this->name}/{$this->name}Conventions.php",
			self::DATA_OBJECT => "$this->buildBasePath/{$this->name}Data.php",
			self::DATA_REPOSITORY => "$this->buildBasePath/{$this->name}DataRepository.php",
			self::ENTITY_TRAIT => "$this->basePath/{$this->name}/$this->traitName.php",
			self::MAPPER_TRAIT => "$this->basePath/{$this->name}/{$this->traitName}Mapper.php",
			self::REPOSITORY_TRAIT => "$this->basePath/{$this->name}/{$this->traitName}Repository.php",
			self::DATA_OBJECT_TRAIT => "$this->basePath/{$this->name}/{$this->traitName}Data.php",
			self::DATA_REPOSITORY_TRAIT => "$this->basePath/$this->name/{$this->traitName}DataRepository.php",
		};
	}


	private function getConfig(string $key): string
	{
		return match($key) {
			self::ENTITY => __DIR__ . '/files/model/entity.neon',
			self::MAPPER => __DIR__ . '/files/model/mapper.neon',
			self::REPOSITORY => __DIR__ . '/files/model/repository.neon',
			self::CONVENTIONS => __DIR__ . '/files/model/conventions.neon',
			self::DATA_OBJECT => __DIR__ . '/files/model/dataObject.neon',
			self::DATA_REPOSITORY => __DIR__ . '/files/model/dataRepository.neon',
			self::ENTITY_TRAIT => __DIR__ . '/files/model/entityTrait.neon',
			self::MAPPER_TRAIT => __DIR__ . '/files/model/mapperTrait.neon',
			self::REPOSITORY_TRAIT => __DIR__ . '/files/model/repositoryTrait.neon',
			self::DATA_OBJECT_TRAIT => __DIR__ . '/files/model/dataObjectTrait.neon',
			self::DATA_REPOSITORY_TRAIT => __DIR__ . '/files/model/dataRepositoryTrait.neon',
		};
	}
}
