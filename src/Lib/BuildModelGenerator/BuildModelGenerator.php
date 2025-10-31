<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\BuildModelGenerator;

use Nette\DI\Attributes\Inject;
use Nette\PhpGenerator\Attribute;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\TraitType;
use Nette\Utils\Arrays;
use Nextras\Orm\StorageReflection\StringHelper;
use Webovac\Generator\Config\Entity;
use Webovac\Generator\Config\Module;
use Webovac\Generator\Lib\BaseGenerator;
use Webovac\Generator\Lib\BuildGenerator\BuildGenerator;
use Webovac\Generator\Lib\Generator;
use Webovac\Generator\Lib\ModelGenerator\ModelGenerator;
use Webovac\Generator\Lib\SetupProvider\ISetupProvider;
use Webovac\Generator\Lib\Writer;


class BuildModelGenerator extends BaseGenerator
{
	public const string CONFIG_DIR = 'buildModel';

	public const string ENTITY = 'entity';
	public const string MAPPER = 'mapper';
	public const string REPOSITORY = 'repository';
	public const string DATA_OBJECT = 'dataObject';
	public const string DATA_REPOSITORY = 'dataRepository';

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
		if ($this->entity->withTraits) {
			$this->createEntity();
			$this->createMapper();
			$this->createRepository();
			$this->createDataObject();
			if ($this->entity->withDataRepository) {
				$this->createDataRepository();
			}
		}
		if ($this->entity->withDataRepository) {
			$this->updateDataModel();
		}
		$this->updateModel();
	}


	public function remove(): void
	{
		$this->writer->remove($this->setupProvider->getBasePath(self::ENTITY));
	}


	private function createEntity(): void
	{
		$this->write(self::ENTITY, [
			'comments' => [
				"@property int \$id {primary}",
				"",
				"@method {$this->setupProvider->getName(self::DATA_OBJECT)} getData(bool \$neon = false, bool \$forCache = false)",
			],
			'data' => $this->setupProvider->getFqn(self::DATA_OBJECT),
			'getDataClassMethod.body' => /* language=PHP */ "return {$this->setupProvider->getName(self::DATA_OBJECT)}::class;",
		]);
	}


	private function createMapper(): void
	{
		$schema = $this->entity->schema ?: $this->setupProvider->getDefaultSchema();
		$lname = $this->entity->table ?: lcfirst(StringHelper::underscore($this->name));
		$this->write(self::MAPPER, [
			'hideConventions' => !$this->entity->withConventions,
			'getTableName.body' => /* language=PHP */ "return new Fqn('$schema', '$lname');",
			'getDataClassMethod.body' => <<<PHP
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


	private function createRepository(): void
	{
		$this->write(self::REPOSITORY, [
			'comments' => [
				"@method $this->name[]|ICollection findAll()",
				"@method $this->name[]|ICollection findBy(array \$conds)",
				"@method $this->name|null getById(mixed \$id)",
				"@method $this->name|null getBy(array \$conds)",
				"@method $this->name createFromData({$this->setupProvider->getName(self::DATA_OBJECT)} \$data, ?$this->name \$original = null, ?CmsEntity \$parent = null, ?string \$parentName = null, ?Person \$person = null, ?\DateTimeInterface \$date = null, bool \$skipDefaults = false, bool \$getOriginalByData = false)",
			],
			'person' => "{$this->setupProvider->getBuildNamespace()}\Model\Person\Person",
			'hidePerson' => $this->name === 'Person',
			'data' => $this->setupProvider->getFqn(self::DATA_OBJECT),
			'getEntityClassNamesMethod.body' => /* language=PHP */ "return [$this->name::class];",
		]);
	}


	private function createDataObject(): void
	{
		$this->write(self::DATA_OBJECT);
	}


	private function createDataRepository(): void
	{
		$this->write(self::DATA_REPOSITORY, [
			'comments' => [
				"@method {$this->setupProvider->getName(self::DATA_OBJECT)}[]|Collection findByKeys(array \$keys)",
				"@method {$this->setupProvider->getName(self::DATA_OBJECT)}|null getByKey(mixed \$key)",
			],
			'data' => $this->setupProvider->getFqn(self::DATA_OBJECT),
		]);
	}


	private function updateDataModel(): void
	{
		$path = $this->setupProvider->getPath(BuildGenerator::DATA_MODEL);
		$file = PhpFile::fromCode(file_get_contents($path));

		/** @var PhpNamespace $namespace */
		$namespace = (Arrays::first($file->getNamespaces()));

		/** @var TraitType|ClassType $class */
		$class = Arrays::first($file->getClasses());
		$name = $this->setupProvider->getName(BuildModelGenerator::DATA_REPOSITORY);
		$propertyName = lcfirst($name);
		$type = $this->setupProvider->getFqn(BuildModelGenerator::DATA_REPOSITORY);
		$property = $class->hasProperty($propertyName)
			? $class->getProperty($propertyName)
			: $class->addProperty($propertyName);
		$property
			->setPublic()
			->setType($type)
			->setAttributes([new Attribute(Inject::class, [])]);
		$namespace
			->addUse($type)
			->addUse(Inject::class);

		$this->writer->write($path, $file);
	}



	private function updateModel(): void
	{
		$path = $this->setupProvider->getPath(BuildGenerator::MODEL);
		$file = PhpFile::fromCode(file_get_contents($path));

		/** @var PhpNamespace $namespace */
		$namespace = Arrays::first($file->getNamespaces());

		/** @var TraitType|ClassType $trait */
		$class = Arrays::first($file->getClasses());
		$name = $this->setupProvider->getName(BuildModelGenerator::REPOSITORY);
		$type = $this->setupProvider->getFqn($this->entity->withTraits ? BuildModelGenerator::REPOSITORY : ModelGenerator::REPOSITORY_TRAIT);
		$lname = lcfirst($name);
		$comment = "@property-read $name \${$lname}";
		$comments = explode("\n", $class->getComment() ?: '');
		$comments[] = $comment;
		$namespace->addUse($type);
		sort($comments);
		$class->setComment(implode("\n", $comments));

		$this->writer->write($path, $file);
	}
}
