<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\BuildModelGenerator;

use Webovac\Generator\Config\Entity;
use Webovac\Generator\Lib\BaseGenerator;
use Webovac\Generator\Lib\ModelGenerator\ModelGenerator;
use Webovac\Generator\Lib\SetupProvider\ISetupProvider;
use Webovac\Generator\Lib\Writer;


class BuildModelGenerator extends BaseGenerator
{
	public const string ENTITY = 'entity';
	public const string MAPPER = 'mapper';
	public const string REPOSITORY = 'repository';
	public const string DATA_OBJECT = 'dataObject';
	public const string DATA_REPOSITORY = 'dataRepository';

	private string $name;


	public function __construct(
		private Entity $entity,
		ISetupProvider $setupProviderFactory,
	) {
		$this->name = $this->entity->name;
		$this->writer = new Writer;
		$this->setupProvider = $setupProviderFactory->create(
			name: $this->entity->name,
			entity: $this->entity,
		);
	}
	
	
	public function generate(): void
	{
		$this->createEntity();
		$this->createMapper();
		$this->createRepository();
		$this->createDataObject();
		$this->createDataRepository();
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
				"@method {$this->setupProvider->getName(self::DATA_OBJECT)} getData(bool \$neon = false, bool \$forCache = false, ?array \$select = null)",
			],
			'data' => $this->setupProvider->getFqn(self::DATA_OBJECT),
			'getDataClassMethod.body' => "return {$this->setupProvider->getName(self::DATA_OBJECT)}::class;",
		]);
	}


	private function createMapper(): void
	{
		$this->write(self::MAPPER, [
			'hideConventions' => !$this->entity->withConventions,
			'getDataClassMethod.body' => <<<EOT
return new {$this->setupProvider->getName(ModelGenerator::CONVENTIONS)}(
	\$this->createInflector(),
	\$this->connection,
	\$this->getTableName(),
	\$this->getRepository()->getEntityMetadata(),
	\$this->cache,
);
EOT,
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
			'getEntityClassNamesMethod.body' => "return [$this->name::class];",
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
}
