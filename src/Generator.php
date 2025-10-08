<?php

declare(strict_types=1);

namespace Webovac\Generator;

use Nette\PhpGenerator\PhpFile;
use Nette\Utils\FileSystem;
use Stepapo\Model\Definition\Config\Foreign;
use Stepapo\Model\Definition\Config\Table;
use Webovac\Generator\Config\Command;
use Webovac\Generator\Config\Component;
use Webovac\Generator\Config\Entity;
use Webovac\Generator\Config\Module;
use Webovac\Generator\Config\Service;
use Webovac\Generator\Lib\Writer;


class Generator
{
	public const string MODE_ADD = 'add';
	public const string MODE_REMOVE = 'remove';
	private Writer $writer;


	public function __construct(
		private string $appNamespace = 'App',
		private string $appDir = 'app',
		private string $buildNamespace = 'Build',
		private string $buildDir = 'build',
	) {
		$this->writer = new Writer;
	}


	public function createModule(Module $module): void
	{
		$generator = new ModuleGenerator($module);
		$basePath = "$this->appDir/Module/$module->name/";
		$name = $module->name;
		$lname = lcfirst($name);

		if (!$module->isPackage) {
			$generator->createModule("$basePath/$name.php");
			$generator->createPresenterTrait("$basePath/Presenter/{$name}Presenter.php");
			$generator->createPresenterTemplateTrait("$basePath/Presenter/{$name}PresenterTemplate.php");
			$generator->createTemplateTrait("$basePath/Control/{$name}Template.php");
			$generator->createTemplateFactoryTrait("$basePath/Lib/{$name}TemplateFactory.php");
			$generator->createMainComponent("$basePath/Control/$name/{$name}Control.php");
			$generator->createMainFactory("$basePath/Control/$name/I{$name}Control.php");
//			$generator->generateMainTemplate("$basePath/Control/$name/{$name}Template.php");
			$generator->createMainLatte("$basePath/Control/$name/$lname.latte");
			if ($module->entities) {
				$generator->createDataModelTrait("$basePath/Model/{$name}DataModel.php");
				$generator->createModelTrait("$basePath/Model/{$name}Orm.php");
			}
			if ($module->withDIExtension) {
				$generator->createDIExtension("$basePath/DI/{$name}Extension.php");
				$generator->createConfigNeon("$basePath/DI/config.neon");
			}
			if ($module->withInstallFile) {
				$generator->createInstallNeon("$basePath/config/manipulations/insert.$module->type.$lname.neon", $module->type);
			}
			if ($module->withMigrationGroup) {
				$this->writer->write("$basePath/config/definitions/001-init.neon");
			}
		}
	}


	public function createComponent(Component $component, ?Module $module = null): void
	{
		$generator = new ComponentGenerator(
			appNamespace: $this->appNamespace,
			component: $component,
			module: $module,
		);
		$basePath = $module ? "$this->appDir/Module/$module->name/Control" : "$this->appDir/Control";
		$name = $component->name;
		$lname = lcfirst($name);
		$templateName = $component->withTemplateName ? 'default' : $lname;
		$generator->createTemplate("$basePath/$name/{$name}Template.php");
		$generator->createFactory("$basePath/$name/I{$name}Control.php");
		$generator->createControl("$basePath/$name/{$name}Control.php");
		$generator->createLatte("$basePath/$name/$templateName.latte");
		if ($component->type === ComponentGenerator::TYPE_DATASET) {
			$generator->createDatasetNeon("$basePath/$name/$lname.neon");
		}
		if ($component->type === ComponentGenerator::TYPE_MENU) {
			$generator->createMenuNeon("$basePath/$name/$lname.neon");
		}
		if (!$module) {
			return;
		}
		$generator->updateMainComponent("$basePath/$module->name/{$module->name}Control.php");
	}


	public function createBuildEntity(Entity $entity): void
	{
		$generator = new ModelGenerator(
			appNamespace: $this->appNamespace,
			appDir: $this->appDir,
			buildNamespace: $this->buildNamespace,
			buildDir: $this->buildDir,
			entity: $entity,
		);
		$generator->createEntity();
		$generator->createMapper();
		$generator->createRepository();
		$generator->createDataObject();
		$generator->createDataRepository();
	}


	public function updateBuildEntity(Entity $entity, Module $module): void
	{
		$generator = new ModelGenerator(
			appNamespace: $this->appNamespace,
			appDir: $this->appDir,
			buildNamespace: $this->buildNamespace,
			buildDir: $this->buildDir,
			entity: $entity,
			module: $module,
		);
		$generator->updateEntity();
		$generator->updateMapper();
		$generator->updateRepository();
		$generator->updateDataObject();
		$generator->updateDataRepository();
	}


	public function createModel(Entity $entity, ?Module $module = null): void
	{
		$generator = new ModelGenerator(
			appNamespace: $this->appNamespace,
			appDir: $this->appDir,
			buildNamespace: $this->buildNamespace,
			buildDir: $this->buildDir,
			entity: $entity,
			module: $module,
		);
		if ($module?->isPackage) {
			return;
		}
		$generator->createEntityTrait();
		$generator->createMapperTrait();
		$generator->createRepositoryTrait();
		$generator->createDataObjectTrait();
		$generator->createDataRepositoryTrait();
		if ($entity->withConventions) {
			$generator->createConventions();
		}
		$generator->updateModel();
	}


	public function checkEntity(Entity $entity, ?Module $module = null): void
	{
		$modelBasePath = $module && !$entity->withTraits
			? "$this->appDir/Module/$module->name/Model/$entity->name"
			: "$this->buildDir/Model/$entity->name";
		$this->writer->checkFileImplements("$modelBasePath/$entity->name.php", $entity->entityImplements);
		$this->writer->checkFileImplements("$modelBasePath/{$entity->name}Repository.php", $entity->repositoryImplements);
		if ($entity->withTraits) {
			$paths = [
				"$modelBasePath/$entity->name.php",
				"$modelBasePath/{$entity->name}Data.php",
				"$modelBasePath/{$entity->name}DataRepository.php",
				"$modelBasePath/{$entity->name}Mapper.php",
				"$modelBasePath/{$entity->name}Repository.php",
			];
			foreach ($paths as $path) {
				$this->writer->sortTraits($path);
			}
		}
	}


	public function createService(Service $service, ?Module $module = null): void
	{
		$generator = new ServiceGenerator(
			appNamespace: $this->appNamespace,
			service: $service,
			module: $module
		);
		$generator->createService();
	}


	public function createCommand(Command $command, ?Module $module = null): void
	{
		$generator = new CommandGenerator(
			appNamespace: $this->appNamespace,
			appDir: $this->appDir,
			command: $command,
			module: $module
		);
		$generator->createCommand();
	}


	public function createBuild(): void
	{
		$generator = new BuildGenerator(
			namespace: $this->buildNamespace,
			dir: $this->buildDir,
		);
		$generator->createBasePresenter();
		$generator->createBasePresenterTemplate();
		$generator->createBaseTemplate();
		$generator->createTemplateFactory();
		$generator->createModel();
		$generator->createDataModel();
	}


	public function checkBuild(): void
	{
		$paths = [
			"$this->buildDir/Presenter/BasePresenter.php",
			"$this->buildDir/Presenter/BasePresenterTemplate.php",
			"$this->buildDir/Control/BaseTemplate.php",
			"$this->buildDir/Lib/BaseTemplateFactory.php",
			"$this->buildDir/Model/Orm.php",
			"$this->buildDir/Model/DataModel.php",
		];
		foreach ($paths as $path) {
			$this->writer->sortTraits($path);
		}
	}


	public function updateBuild(Module $module): void
	{
		$generator = new ModuleGenerator($module);
		$generator->updateBasePresenter("$this->buildDir/Presenter/BasePresenter.php");
		$generator->updateBasePresenterTemplate("$this->buildDir/Presenter/BasePresenterTemplate.php");
		$generator->updateBaseTemplate("$this->buildDir/Control/BaseTemplate.php");
		$generator->updateTemplateFactory("$this->buildDir/Lib/BaseTemplateFactory.php");
		if ($module->entities) {
			$generator->updateModel("$this->buildDir/Model/Orm.php");
			$generator->updateDataModel("$this->buildDir/Model/DataModel.php");
		}
	}


	public function getEntityComments(Table $table): ?string
	{
		$generator = new PropertyGenerator(
			appNamespace: $this->appNamespace,
			buildNamespace: $this->buildNamespace,
			table: $table,
		);
		$name = $table->getPhpName();
		$basePath = $table->module ? "$this->appDir/$table->module/Model" : "$this->buildDir/Model";
		return $generator->readEntityComments("$basePath/$name/$name.php");
	}


	public function updateEntitySimple(Table $table): void
	{
		$generator = new PropertyGenerator(
			appNamespace: $this->appNamespace,
			buildNamespace: $this->buildNamespace,
			table: $table,
		);
		$name = $table->getPhpName();
		$basePath = $table->module ? "$this->appDir/$table->module/Model" : "$this->buildDir/Model";
		$generator->createEntityProperties("$basePath/$name/$name.php");
	}


	public function updateEntityManyHasMany(Table $table, Foreign $from, Foreign $to, bool $isMain = false)
	{
		$generator = new PropertyGenerator(
			appNamespace: $this->appNamespace,
			buildNamespace: $this->buildNamespace,
			table: $table,
		);
		$name = $from->getPhpTable();
		$basePath = $table->module ? "$this->appDir/$table->module/Model" : "$this->buildDir/Model";
		$generator->createEntityPropertyManyHasMany("$basePath/$name/$name.php", $from, $to, $isMain);
	}


	public function updateEntityOneHasMany(Table $table, Foreign $foreign)
	{
		$generator = new PropertyGenerator(
			appNamespace: $this->appNamespace,
			buildNamespace: $this->buildNamespace,
			table: $table,
		);
		$name = $foreign->getPhpTable();
		$basePath = $table->module ? "$this->appDir/$table->module/Model" : "$this->buildDir/Model";
		$generator->createEntityPropertyOneHasMany("$basePath/$name/$name.php", $foreign);
	}


	public function updateEntitySortComments(Table $table)
	{
		$generator = new PropertyGenerator(
			appNamespace: $this->appNamespace,
			buildNamespace: $this->buildNamespace,
			table: $table,
		);
		$name = $table->getPhpName();
		$basePath = $table->module ? "$this->appDir/$table->module" : "$this->buildDir/Model";
		$generator->sortEntityProperties("$basePath/$name/$name.php");
	}


	public function removeModule(Module $module): void
	{
		$this->writer->remove("$this->appDir/Module/$module->name");
	}


	public function removeModel(Entity $entity, ?Module $module = null): void
	{
		$this->writer->remove("$this->appDir/" . ($module ? "Module/$module->name/" : '') . "Model/$entity->name");
	}


	public function removeComponent(Component $component, ?Module $module = null): void
	{
		$this->writer->remove("$this->appDir/" . ($module ? "Module/$module->name/" : '') . "Control/$component->name");
		if (!$module) {
			return;
		}
		$generator = new ComponentGenerator(
			appNamespace: $this->appNamespace,
			component: $component,
			module: $module,
			mode: Generator::MODE_REMOVE,
		);
		$generator->updateMainComponent("$this->appDir/Module/$module->name/Control/$module->name/{$module->name}Control.php");
	}


	public function removeService(Service $service, ?Module $module = null)
	{
		$this->writer->remove("$this->appDir/" . ($module ? "Module/$module->name/" : '') . "Lib/$service->name.php");
	}


	public function removeCommand(Command $command, ?Module $module = null)
	{
		$this->writer->remove("$this->appDir/" . ($module ? "Module/$module->name/" : '') . "Command/$command->name.php");
	}
}
