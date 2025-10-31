<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\ModelGenerator;

use Nette\DI\Attributes\Inject;
use Nette\PhpGenerator\Attribute;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\TraitType;
use Nette\Utils\Arrays;
use Nextras\Orm\StorageReflection\StringHelper;
use Stepapo\Model\Data\DataRepository;
use Stepapo\Model\Data\Item;
use Webovac\Core\Model\CmsMapper;
use Webovac\Core\Model\CmsRepository;
use Webovac\Generator\Config\Entity;
use Webovac\Generator\Config\Module;
use Webovac\Generator\Lib\BaseGenerator;
use Webovac\Generator\Lib\BuildGenerator\BuildGenerator;
use Webovac\Generator\Lib\BuildModelGenerator\BuildModelGenerator;
use Webovac\Generator\Lib\Generator;
use Webovac\Generator\Lib\ModuleGenerator\ModuleGenerator;
use Webovac\Generator\Lib\SetupProvider\ISetupProvider;
use Webovac\Generator\Lib\Writer;


class ModelGenerator extends BaseGenerator
{
	public const string CONFIG_DIR = 'model';

	public const string ENTITY_TRAIT = 'entityTrait';
	public const string MAPPER_TRAIT = 'mapperTrait';
	public const string REPOSITORY_TRAIT = 'repositoryTrait';
	public const string DATA_OBJECT_TRAIT = 'dataObjectTrait';
	public const string DATA_REPOSITORY_TRAIT = 'dataRepositoryTrait';
	public const string CONVENTIONS = 'conventions';

	private string $name;


	public function __construct(
		private Entity $entity,
		private ?Module $module,
		ISetupProvider $setupProviderFactory,
	) {
		$this->name = $this->entity->name;
		$this->writer = new Writer;
		$this->setupProvider = $setupProviderFactory->create(
			name: $this->entity->name,
			entity: $this->entity,
			module: $this->module,
		);
	}
	
	
	public function generate(): void
	{
		if ($this->module?->isPackage) {
			return;
		}
		$this->createEntityTrait();
		$this->createMapperTrait();
		$this->createRepositoryTrait();
		$this->createDataObjectTrait();
		if ($this->entity->withDataRepository) {
			$this->createDataRepositoryTrait();
		}
		if ($this->entity->withConventions) {
			$this->createConventions();
		}
	}


	public function remove(): void
	{
		$this->writer->remove($this->setupProvider->getBasePath(self::ENTITY_TRAIT));
	}


	public function updateBuildModel(): void
	{
		if (!$this->entity->withTraits) {
			return;
		}
		$this->updateFile(BuildModelGenerator::ENTITY, self::ENTITY_TRAIT, $this->entity->entityImplements);
		$this->updateFile(BuildModelGenerator::MAPPER, self::MAPPER_TRAIT);
		$this->updateFile(BuildModelGenerator::REPOSITORY, self::REPOSITORY_TRAIT, $this->entity->repositoryImplements);
		$this->updateFile(BuildModelGenerator::DATA_OBJECT, self::DATA_OBJECT_TRAIT);
		if ($this->entity->withDataRepository) {
			$this->updateFile(BuildModelGenerator::DATA_REPOSITORY, self::DATA_REPOSITORY_TRAIT);
		}
	}


	public function checkBuildModel(): void
	{
		if (!$this->entity->withTraits) {
			return;
		}
		$entityKey = $this->module && !$this->entity->withTraits ? self::ENTITY_TRAIT : BuildModelGenerator::ENTITY;
		$repositoryKey = $this->module && !$this->entity->withTraits ? self::REPOSITORY_TRAIT : BuildModelGenerator::REPOSITORY;
		$this->writer->checkFileImplements($this->setupProvider->getPath($entityKey), $this->entity->entityImplements);
		$this->writer->checkFileImplements($this->setupProvider->getPath($repositoryKey), $this->entity->repositoryImplements);
		if ($this->entity->withTraits) {
			$paths = [
				$this->setupProvider->getPath(BuildModelGenerator::ENTITY),
				$this->setupProvider->getPath(BuildModelGenerator::DATA_OBJECT),
				$this->setupProvider->getPath(BuildModelGenerator::MAPPER),
				$this->setupProvider->getPath(BuildModelGenerator::REPOSITORY),
			];
			if ($this->entity->withDataRepository) {
				$paths[] = $this->setupProvider->getPath(BuildModelGenerator::DATA_REPOSITORY);
			}
			foreach ($paths as $path) {
				$this->writer->sortTraits($path);
			}
		}
	}


	private function createConventions(): void
	{
		$this->write(self::CONVENTIONS, [
			'getDefaultMappingsMethod.body' => <<<PHP
return [
	[
	
	],
	[
	
	],
	[]
];
PHP,
		]);
	}


	private function createEntityTrait(): void
	{
		$this->write(self::ENTITY_TRAIT, [
			'extends' => $this->entity->withTraits ? null : CmsMapper::class,
			'type' => $this->entity->withTraits ? TraitType::class : ClassType::class,
			'isTrait' => $this->entity->withTraits,
			'comments' => $this->entity->withTraits ? [] : [
				"@property int \$id {primary}",
				"",
				"@method {$this->setupProvider->getName(self::DATA_OBJECT_TRAIT)} getData(bool \$neon = false, bool \$forCache = false)",
			],
			'data' => $this->setupProvider->getFqn(self::DATA_OBJECT_TRAIT),
			'getDataClassMethod.body' => /* language=PHP */ "return {$this->setupProvider->getName(self::DATA_OBJECT_TRAIT)}::class;",
		]);
	}


	private function createMapperTrait(): void
	{
		$schema = $this->entity->schema ?: $this->setupProvider->getDefaultSchema();
		$lname = $this->entity->table ?: lcfirst(StringHelper::underscore($this->name));
		$this->write(self::MAPPER_TRAIT, [
			'extends' => $this->entity->withTraits ? null : CmsMapper::class,
			'type' => $this->entity->withTraits ? TraitType::class : ClassType::class,
			'isTrait' => $this->entity->withTraits,
			'hideConventions' => $this->entity->withTraits || !$this->entity->withConventions,
			'getTableName.body' => /* language=PHP */ "return new Fqn('$schema', '$lname');",
			'createConventionsMethod.body' => <<<PHP
return new {$this->setupProvider->getName(ModelGenerator::CONVENTIONS)}(
	\$this->createInflector(),
	\$this->connection,
	\$this->getTableName(),
	\$this->getRepository()->getEntityMetadata(),
	\$this->cache,
);
PHP,
		]);
	}


	private function createRepositoryTrait(): void
	{
		$this->write(self::REPOSITORY_TRAIT, [
			'extends' => $this->entity->withTraits ? null : CmsRepository::class,
			'type' => $this->entity->withTraits ? TraitType::class : ClassType::class,
			'isTrait' => $this->entity->withTraits,
			'comments' => $this->entity->withTraits ? [] : [
				"@method $this->name[]|ICollection findAll()",
				"@method $this->name[]|ICollection findBy(array \$conds)",
				"@method $this->name|null getById(mixed \$id)",
				"@method $this->name|null getBy(array \$conds)",
				"@method $this->name createFromData({$this->setupProvider->getName(self::DATA_OBJECT_TRAIT)} \$data, ?$this->name \$original = null, ?CmsEntity \$parent = null, ?string \$parentName = null, ?Person \$person = null, ?\DateTimeInterface \$date = null, bool \$skipDefaults = false, bool \$getOriginalByData = false)",
			],
			'person' => "{$this->setupProvider->getBuildNamespace()}\Model\Person\Person",
			'hidePerson' => $this->entity->withTraits || $this->name === 'Person',
			'data' => $this->setupProvider->getFqn(self::DATA_OBJECT_TRAIT),
			'getEntityClassNamesMethod.body' => /* language=PHP */ "return [$this->name::class];",
		]);
	}


	private function createDataObjectTrait(): void
	{
		$this->write(self::DATA_OBJECT_TRAIT, [
			'extends' => $this->entity->withTraits ? null : Item::class,
			'type' => $this->entity->withTraits ? TraitType::class : ClassType::class,
		]);
	}


	private function createDataRepositoryTrait(): void
	{
		$this->write(self::DATA_REPOSITORY_TRAIT, [
			'extends' => $this->entity->withTraits ? null : DataRepository::class,
			'type' => $this->entity->withTraits ? TraitType::class : ClassType::class,
			'isTrait' => $this->entity->withTraits,
			'comments' => $this->entity->withTraits ? [] : [
				"@method {$this->setupProvider->getName(self::DATA_OBJECT_TRAIT)}[]|Collection findByKeys(array \$keys)",
				"@method {$this->setupProvider->getName(self::DATA_OBJECT_TRAIT)}|null getByKey(mixed \$key)",
			],
			'data' => $this->setupProvider->getFqn(self::DATA_OBJECT_TRAIT),
		]);
	}
}
