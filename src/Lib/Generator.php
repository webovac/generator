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
use Webovac\Generator\Lib\ModelGenerator\IModelGenerator;
use Webovac\Generator\Lib\ModuleGenerator\IModuleGenerator;
use Webovac\Generator\Lib\PropertyGenerator\IPropertyGenerator;
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
		private IPropertyGenerator $propertyGenerator,
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
		return $this->propertyGenerator->create($table->getPhpName(), $table)->readEntityComments();
	}


	public function updateEntitySimple(Table $table): void
	{
		$this->propertyGenerator->create($table->getPhpName(), $table)->createEntityProperties();
	}


	public function updateEntityManyHasMany(Table $table, Foreign $from, Foreign $to, bool $isMain = false)
	{
		$this->propertyGenerator->create($from->getPhpTable(), $table)->createEntityPropertyManyHasMany($from, $to, $isMain);
	}


	public function updateEntityOneHasMany(Table $table, Foreign $foreign)
	{
		$this->propertyGenerator->create($foreign->getPhpTable(), $table)->createEntityPropertyOneHasMany($foreign);
	}


	public function updateEntitySortComments(Table $table)
	{
		$this->propertyGenerator->create($table->getPhpName(), $table)->sortEntityProperties();
	}
}
