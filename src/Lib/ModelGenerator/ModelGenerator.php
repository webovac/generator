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
use Stepapo\Model\Data\DataRepository;
use Webovac\Core\Model\CmsMapper;
use Webovac\Core\Model\CmsRepository;
use Webovac\Generator\Config\Entity;
use Webovac\Generator\Config\Module;
use Webovac\Generator\Lib\BaseGenerator;
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


	public function __construct(
		private Entity $entity,
		private ?Module $module,
		ISetupProvider $setupProviderFactory,
	) {
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
		$this->createDataRepositoryTrait();
		if ($this->entity->withConventions) {
			$this->createConventions();
		}
		$this->updateModel();
	}


	public function remove(): void
	{
		$this->writer->remove($this->setupProvider->getBasePath(self::ENTITY_TRAIT));
		$this->updateModel(Generator::MODE_REMOVE);
	}


	public function updateBuildModel(): void
	{
		$this->updateFile(BuildModelGenerator::ENTITY, self::ENTITY_TRAIT, $this->entity->entityImplements);
		$this->updateFile(BuildModelGenerator::MAPPER, self::MAPPER_TRAIT);
		$this->updateFile(BuildModelGenerator::REPOSITORY, self::REPOSITORY_TRAIT, $this->entity->repositoryImplements);
		$this->updateFile(BuildModelGenerator::DATA_OBJECT, self::DATA_OBJECT_TRAIT);
		$this->updateFile(BuildModelGenerator::DATA_REPOSITORY, self::DATA_REPOSITORY_TRAIT);
	}


	public function checkBuildModel(): void
	{
		$entityKey = $this->module && !$this->entity->withTraits ? self::ENTITY_TRAIT : BuildModelGenerator::ENTITY;
		$repositoryKey = $this->module && !$this->entity->withTraits ? self::REPOSITORY_TRAIT : BuildModelGenerator::REPOSITORY;
		$this->writer->checkFileImplements($this->setupProvider->getPath($entityKey), $this->entity->entityImplements);
		$this->writer->checkFileImplements($this->setupProvider->getPath($repositoryKey), $this->entity->repositoryImplements);
		if ($this->entity->withTraits) {
			$paths = [
				$this->setupProvider->getPath(BuildModelGenerator::ENTITY),
				$this->setupProvider->getPath(BuildModelGenerator::DATA_OBJECT),
				$this->setupProvider->getPath(BuildModelGenerator::DATA_REPOSITORY),
				$this->setupProvider->getPath(BuildModelGenerator::MAPPER),
				$this->setupProvider->getPath(BuildModelGenerator::REPOSITORY),
			];
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
		]);
	}


	private function createMapperTrait(): void
	{
		$this->write(self::MAPPER_TRAIT, [
			'extends' => $this->entity->withTraits ? null : CmsMapper::class,
			'type' => $this->entity->withTraits ? TraitType::class : ClassType::class,
		]);
	}


	private function createRepositoryTrait(): void
	{
		$this->write(self::REPOSITORY_TRAIT, [
			'extends' => $this->entity->withTraits ? null : CmsRepository::class,
			'type' => $this->entity->withTraits ? TraitType::class : ClassType::class,
		]);
	}


	private function createDataObjectTrait(): void
	{
		$this->write(self::DATA_OBJECT_TRAIT, [
			'extends' => $this->entity->withTraits ? null : CmsRepository::class,
			'type' => $this->entity->withTraits ? TraitType::class : ClassType::class,
		]);
	}


	private function createDataRepositoryTrait(): void
	{
		$this->write(self::DATA_REPOSITORY_TRAIT, [
			'extends' => $this->entity->withTraits ? null : DataRepository::class,
			'type' => $this->entity->withTraits ? TraitType::class : ClassType::class,
		]);
	}


	private function updateDataModel(string $mode = Generator::MODE_ADD): void
	{
		$path = $this->setupProvider->getPath(ModuleGenerator::DATA_MODEL_TRAIT);
		$file = PhpFile::fromCode(file_get_contents($path));

		/** @var PhpNamespace $namespace */
		$namespace = (Arrays::first($file->getNamespaces()));

		/** @var TraitType|ClassType $class */
		$class = Arrays::first($file->getClasses());
		$name = $this->setupProvider->getName(BuildModelGenerator::DATA_REPOSITORY);
		$propertyName = lcfirst($name);
		$type = $this->setupProvider->getFqn(BuildModelGenerator::DATA_REPOSITORY);
		if ($mode === Generator::MODE_ADD) {
			$property = $class->hasProperty($propertyName)
				? $class->getProperty($propertyName)
				: $class->addProperty($propertyName);
			$property
				->setPublic()
				->setType($type)
				->setAttributes([new Attribute(Inject::class, [])]);
			$namespace->addUse($type);
		} else {
			$class->removeProperty($propertyName);
			$namespace->removeUse($type);
		}

		$this->writer->write($path, $file);
	}


	private function updateModel(string $mode = Generator::MODE_ADD): void
	{
		$path = $this->setupProvider->getPath(ModuleGenerator::MODEL_TRAIT);
		$file = PhpFile::fromCode(file_get_contents($path));

		/** @var PhpNamespace $namespace */
		$namespace = Arrays::first($file->getNamespaces());

		/** @var TraitType|ClassType $trait */
		$class = Arrays::first($file->getClasses());
		$name = $this->setupProvider->getName(BuildModelGenerator::REPOSITORY);
		$type = $this->setupProvider->getFqn(BuildModelGenerator::REPOSITORY);
		$lname = lcfirst($name);
		$comment = "@property-read $name \${$lname}";
		$comments = explode("\n", $class->getComment() ?: '');
		if ($mode === Generator::MODE_ADD) {
			$comments[] = $comment;
			$namespace->addUse($type);
		} else {
			$comments = array_combine($comments, $comments);
			unset($comments[$comment]);
			$namespace->removeUse($type);
		}
		sort($comments);
		$class->setComment(implode("\n", $comments));

		$this->writer->write($path, $file);
	}
}
