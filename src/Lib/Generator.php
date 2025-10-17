<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib;

use Stepapo\Model\Definition\Config\Foreign;
use Stepapo\Model\Definition\Config\Table;
use Webovac\Generator\Config\Command;
use Webovac\Generator\Config\Component;
use Webovac\Generator\Config\Entity;
use Webovac\Generator\Config\Module;
use Webovac\Generator\Config\Service;
use Webovac\Generator\Lib\BuildGenerator\IBuildGenerator;
use Webovac\Generator\Lib\BuildModelGenerator\IBuildModelGenerator;
use Webovac\Generator\Lib\CommandGenerator\ICommandGenerator;
use Webovac\Generator\Lib\ComponentGenerator\IComponentGenerator;
use Webovac\Generator\Lib\DataPropertyGenerator\IDataPropertyGenerator;
use Webovac\Generator\Lib\EntityPropertyGenerator\IEntityPropertyGenerator;
use Webovac\Generator\Lib\ModelGenerator\IModelGenerator;
use Webovac\Generator\Lib\ModuleGenerator\IModuleGenerator;
use Webovac\Generator\Lib\ServiceGenerator\IServiceGenerator;


class Generator implements \Stepapo\Utils\Service
{
	public const string MODE_ADD = 'add';
	public const string MODE_REMOVE = 'remove';


	public function __construct(
		private IModuleGenerator $moduleGenerator,
		private IComponentGenerator $componentGenerator,
		private IModelGenerator $modelGenerator,
		private IServiceGenerator $serviceGenerator,
		private ICommandGenerator $commandGenerator,
		private IBuildGenerator $buildGenerator,
		private IBuildModelGenerator $buildModelGenerator,
		private IEntityPropertyGenerator $entityPropertyGenerator,
		private IDataPropertyGenerator $dataPropertyGenerator,
	) {}


	public function createBuild(): void
	{
		$this->buildGenerator->create()->generate();
	}


	public function updateBuild(Module $module): void
	{
		$this->moduleGenerator->create($module)->updateBuild();
	}


	public function checkBuild(): void
	{
		$this->buildGenerator->create()->checkBuild();
	}


	public function removeBuild()
	{
		$this->buildGenerator->create()->remove();
	}


	public function createBuildModel(Entity $entity): void
	{
		$this->buildModelGenerator->create($entity)->generate();
	}


	public function updateBuildModel(Entity $entity, Module $module): void
	{
		$this->modelGenerator->create($entity, $module)->updateBuildModel();
	}


	public function createModule(Module $module): void
	{
		$this->moduleGenerator->create($module)->generate();
	}


	public function removeModule(Module $module): void
	{
		$this->moduleGenerator->create($module)->remove();
	}


	public function createModel(Entity $entity, ?Module $module = null): void
	{
		$this->modelGenerator->create($entity, $module)->generate();
	}


	public function checkBuildModel(Entity $entity, ?Module $module = null): void
	{
		$this->modelGenerator->create($entity, $module)->checkBuildModel();
	}


	public function removeModel(Entity $entity, ?Module $module = null): void
	{
		$this->modelGenerator->create($entity, $module)->remove();
	}


	public function createComponent(Component $component, ?Module $module = null): void
	{
		$this->componentGenerator->create($component, $module)->generate();
	}


	public function removeComponent(Component $component, ?Module $module = null): void
	{
		$this->componentGenerator->create($component, $module)->remove();
	}


	public function createService(Service $service, ?Module $module = null): void
	{
		$this->serviceGenerator->create($service, $module)->generate();
	}


	public function removeService(Service $service, ?Module $module = null)
	{
		$this->serviceGenerator->create($service, $module)->remove();
	}


	public function createCommand(Command $command, ?Module $module = null): void
	{
		$this->commandGenerator->create($command, $module)->generate();
	}


	public function removeCommand(Command $command, ?Module $module = null)
	{
		$this->commandGenerator->create($command, $module)->remove();
	}


	public function getEntityComments(Table $table): ?string
	{
		return $this->entityPropertyGenerator->create($table->getPhpName(), $table)->readComments();
	}


	public function updateEntitySimple(Table $table): void
	{
		$this->entityPropertyGenerator->create($table->getPhpName(), $table)->createSimple();
	}


	public function updateEntityManyHasMany(Table $table, Foreign $from, Foreign $to, bool $isMain = false)
	{
		$this->entityPropertyGenerator->create($from->getPhpTable(), $table)->createManyHasMany($from, $to, $isMain);
	}


	public function updateEntityOneHasMany(Table $table, Foreign $foreign)
	{
		$this->entityPropertyGenerator->create($foreign->getPhpTable(), $table)->createOneHasMany($foreign);
	}


	public function updateEntitySortProperties(Table $table)
	{
		$this->entityPropertyGenerator->create($table->getPhpName(), $table)->sort();
	}


	public function updateDataSimple(Table $table): void
	{
		$this->dataPropertyGenerator->create($table->getPhpName(), $table)->createSimple();
	}


	public function updateDataManyHasMany(Table $table, Foreign $from, Foreign $to, bool $isMain = false)
	{
		$this->dataPropertyGenerator->create($from->getPhpTable(), $table)->createManyHasMany($from, $to, $isMain);
	}


	public function updateDataOneHasMany(Table $table, Foreign $foreign)
	{
		$this->dataPropertyGenerator->create($foreign->getPhpTable(), $table)->createOneHasMany($foreign);
	}


	public function updateDataSortProperties(Table $table)
	{
		$this->dataPropertyGenerator->create($table->getPhpName(), $table)->sort();
	}
}
