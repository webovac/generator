<?php

declare(strict_types=1);

namespace Webovac\Generator;

use Build\Control\BaseTemplate;
use Nette\PhpGenerator\PhpFile;
use Nette\Utils\FileSystem;
use Stepapo\Model\Definition\Config\Foreign;
use Stepapo\Model\Definition\Config\Table;
use Webovac\Core\Control\BaseControl;
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
		protected string $appNamespace = 'App',
		protected string $appDir = 'app',
		protected string $buildNamespace = 'Build',
		protected string $buildDir = 'build',
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
			$this->createFile("$basePath/$name.php", $generator->createModule());
			$this->createFile("$basePath/Presenter/{$name}Presenter.php", $generator->createPresenterTrait());
			$this->createFile("$basePath/Presenter/{$name}PresenterTemplate.php", $generator->createPresenterTemplateTrait());
			$this->createFile("$basePath/Control/{$name}Template.php", $generator->createTemplateTrait());
			$this->createFile("$basePath/Lib/{$name}TemplateFactory.php", $generator->createTemplateFactoryTrait());
			$this->createFile("$basePath/Control/$name/{$name}Control.php", $generator->createMainComponent());
			$this->createFile("$basePath/Control/$name/I{$name}Control.php", $generator->createMainFactory());
//			$this->createFile("$basePath/Control/$name/{$name}Template.php", $generator->generateMainTemplate());
			$this->createFile("$basePath/Control/$name/$lname.latte", $generator->createMainLatte());
			if ($module->entities) {
				$this->createFile("$basePath/Model/{$name}DataModel.php", $generator->createDataModelTrait());
				$this->createFile("$basePath/Model/{$name}Orm.php", $generator->createModelTrait());
			}
			if ($module->withDIExtension) {
				$this->createFile("$basePath/DI/{$name}Extension.php", $generator->createDIExtension());
				$this->createFile("$basePath/DI/config.neon", $generator->createConfigNeon());
			}
			if ($module->withInstallFile) {
				$this->createFile("$basePath/config/manipulations/insert.$module->type.$lname.neon", $generator->createInstallNeon($module->type));
			}
			if ($module->withMigrationGroup) {
				$this->createFile("$basePath/config/definitions/001-init.neon");
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
		$this->createFile("$basePath/$name/{$name}Template.php", $generator->createTemplate(BaseTemplate::class));
		$this->createFile("$basePath/$name/I{$name}Control.php", $generator->createFactory());
		$this->createFile("$basePath/$name/{$name}Control.php", $generator->createControl(BaseControl::class));
		$this->createFile("$basePath/$name/$templateName.latte", $generator->createLatte());
		if ($component->type === ComponentGenerator::TYPE_DATASET) {
			$this->createFile("$basePath/$name/$lname.neon", $generator->createDatasetNeon());
		}
		if ($component->type === ComponentGenerator::TYPE_MENU) {
			$this->createFile("$basePath/$name/$lname.neon", $generator->createMenuNeon());
		}
		if (!$module) {
			return;
		}
		$this->createFile($mainComponentPath = "$basePath/$module->name/{$module->name}Control.php", $generator->updateMainComponent($mainComponentPath));
	}


	public function createBuildEntity(Entity $entity): void
	{
		$name = $entity->name;
		$generator = new ModelGenerator(
			appNamespace: $this->appNamespace,
			buildNamespace: $this->buildNamespace,
			entity: $entity,
		);
		$modelBasePath = "$this->buildDir/Model";
		$this->createFile("$modelBasePath/$name/$name.php", $generator->createEntity());
		$this->createFile("$modelBasePath/$name/{$name}Mapper.php", $generator->createMapper());
		$this->createFile("$modelBasePath/$name/{$name}Repository.php", $generator->createRepository());
		$this->createFile("$modelBasePath/$name/{$name}Data.php",  $generator->createDataObject());
		$this->createFile("$modelBasePath/$name/{$name}DataRepository.php", $generator->createDataRepository());
	}


	public function updateBuildEntity(Entity $entity, Module $module): void
	{
		$name = $entity->name;
		$generator = new ModelGenerator(
			appNamespace: $this->appNamespace,
			buildNamespace: $this->buildNamespace,
			entity: $entity,
			module: $module,
		);
		$modelBasePath = "$this->buildDir/Model";
		$this->createFile($entityPath = "$modelBasePath/$name/$name.php", $generator->updateEntity($entityPath));
		$this->createFile($mapperPath = "$modelBasePath/$name/{$name}Mapper.php", $generator->updateMapper($mapperPath));
		$this->createFile($repositoryPath = "$modelBasePath/$name/{$name}Repository.php", $generator->updateRepository($repositoryPath));
		$this->createFile($dataObjectPath = "$modelBasePath/$name/{$name}Data.php",  $generator->updateDataObject($dataObjectPath));
		$this->createFile($dataRepositoryPath = "$modelBasePath/$name/{$name}DataRepository.php", $generator->updateDataRepository($dataRepositoryPath));
	}


	public function createModel(
		Entity $entity,
		?Module $module = null,
	): void
	{
		$generator = new ModelGenerator(
			appNamespace: $this->appNamespace,
			buildNamespace: $this->buildNamespace,
			entity: $entity,
			module: $module,
		);
		$basePath = "$this->appDir/" . ($module ? "Module/$module->name/" : '') . "Model";
		if ($module?->isPackage) {
			return;
		}
		$className = $entity->withTraits ? "$module->name$entity->name" : $entity->name;
		$this->createFile("$basePath/$entity->name/$className.php", $generator->createEntityTrait());
		$this->createFile("$basePath/$entity->name/{$className}Mapper.php", $generator->createMapperTrait());
		$this->createFile("$basePath/$entity->name/{$className}Repository.php", $generator->createRepositoryTrait());
		$this->createFile("$basePath/$entity->name/{$className}Data.php", $generator->createDataObjectTrait());
		$this->createFile("$basePath/$entity->name/{$className}DataRepository.php", $generator->createDataRepositoryTrait());
		if ($entity->withConventions) {
			$this->createFile("$basePath/$entity->name/{$className}Conventions.php", $generator->createConventions());
		}
		$modelPath = "$basePath/{$module->name}Orm.php";
		$this->createFile($modelPath, $generator->updateModel($modelPath));
	}


	public function checkEntity(Entity $entity, ?Module $module = null): void
	{
		$modelBasePath = $module && !$entity->withTraits
			? "$this->appDir/Module/$module->name/Model"
			: "$this->buildDir/Model";
		$this->createFile($entityPath = "$modelBasePath/$entity->name/$entity->name.php", $this->writer->checkFileImplements($entityPath, $entity->entityImplements));
		$this->createFile($repositoryPath = "$modelBasePath/$entity->name/{$entity->name}Repository.php", $this->writer->checkFileImplements($repositoryPath, $entity->repositoryImplements));
		if ($entity->withTraits) {
			$paths = [
				"$modelBasePath/$entity->name/$entity->name.php",
				"$modelBasePath/$entity->name/{$entity->name}Data.php",
				"$modelBasePath/$entity->name/{$entity->name}DataRepository.php",
				"$modelBasePath/$entity->name/{$entity->name}Mapper.php",
				"$modelBasePath/$entity->name/{$entity->name}Repository.php",
			];
			foreach ($paths as $path) {
				$this->createFile($path, $this->writer->sortTraits($path));
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
		$basePath = "$this->appDir/" . ($module ? "Module/{$module->name}/" : '') . "Lib";
		$this->createFile("$basePath/{$service->name}.php", $generator->createService());
	}


	public function createCommand(Command $command, ?Module $module = null): void
	{
		$generator = new CommandGenerator(
			appNamespace: $this->appNamespace,
			command: $command,
			module: $module
		);
		$basePath = "$this->appDir/" . ($module ? "Module/{$module->name}/" : '') . "Command";
		$this->createFile("$basePath/{$command->name}.php", $generator->createCommand());
	}


	public function removeModule(Module $module): void
	{
		FileSystem::delete("$this->appDir/Module/$module->name");
	}


	public function removeModel(Entity $entity, ?Module $module = null): void
	{
		FileSystem::delete("$this->appDir/" . ($module ? "Module/$module->name/" : '') . "Model/$entity->name");
	}


	public function removeComponent(Component $component, ?Module $module = null): void
	{
		FileSystem::delete("$this->appDir/" . ($module ? "Module/$module->name/" : '') . "Control/$component->name");
		if (!$module) {
			return;
		}
		$generator = new ComponentGenerator(
			appNamespace: $this->appNamespace,
			component: $component,
			module: $module,
			mode: Generator::MODE_REMOVE,
		);
		$this->createFile($mainComponentPath = "$this->appDir/Module/$module->name/Control/$module->name/{$module->name}Control.php", $generator->updateMainComponent($mainComponentPath));
	}


	public function removeService(Service $service, ?Module $module = null)
	{
		FileSystem::delete("$this->appDir/" . ($module ? "Module/$module->name/" : '') . "Lib/$service->name.php");
	}


	public function removeCommand(Command $command, ?Module $module = null)
	{
		FileSystem::delete("$this->appDir/" . ($module ? "Module/$module->name/" : '') . "Command/$command->name.php");
	}


	public function createBuild(): void
	{
		$generator = new BuildGenerator(
			namespace: $this->buildNamespace,
		);
		$this->createFile("$this->buildDir/Presenter/BasePresenter.php", $generator->createBasePresenter());
		$this->createFile("$this->buildDir/Presenter/BasePresenterTemplate.php", $generator->createBasePresenterTemplate());
		$this->createFile("$this->buildDir/Control/BaseTemplate.php", $generator->createBaseTemplate());
		$this->createFile("$this->buildDir/Lib/BaseTemplateFactory.php", $generator->createTemplateFactory());
		$this->createFile("$this->buildDir/Model/Orm.php", $generator->createModel());
		$this->createFile("$this->buildDir/Model/DataModel.php", $generator->createDataModel());
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
			$this->createFile($path, $this->writer->sortTraits($path));
		}
	}


	public function updateBuild(Module $module): void
	{
		$generator = new ModuleGenerator($module);
		$basePresenterPath = "$this->buildDir/Presenter/BasePresenter.php";
		$this->createFile($basePresenterPath, $generator->updateBasePresenter($basePresenterPath));
		$basePresenterTemplatePath = "$this->buildDir/Presenter/BasePresenterTemplate.php";
		$this->createFile($basePresenterTemplatePath, $generator->updateBasePresenterTemplate($basePresenterTemplatePath));
		$baseTemplatePath = "$this->buildDir/Control/BaseTemplate.php";
		$this->createFile($baseTemplatePath, $generator->updateBaseTemplate($baseTemplatePath));
		$baseTemplateFactoryPath = "$this->buildDir/Lib/BaseTemplateFactory.php";
		$this->createFile($baseTemplateFactoryPath, $generator->updateTemplateFactory($baseTemplateFactoryPath));
		if ($module->entities) {
			$modelPath = "$this->buildDir/Model/Orm.php";
			$this->createFile($modelPath, $generator->updateModel($modelPath));
			$dataModelPath = "$this->buildDir/Model/DataModel.php";
			$this->createFile($dataModelPath, $generator->updateDataModel($dataModelPath));
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
		$this->createFile($entityPath = "$basePath/$name/$name.php", $generator->createEntityProperties($entityPath));
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
		$this->createFile($entityPath = "$basePath/$name/$name.php", $generator->createEntityPropertyManyHasMany($entityPath, $from, $to, $isMain));
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
		$this->createFile($entityPath = "$basePath/$name/$name.php", $generator->createEntityPropertyOneHasMany($entityPath, $foreign));
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
		$entityPath = "$basePath/$name/$name.php";
		$this->createFile($entityPath, $generator->sortEntityProperties($entityPath));
	}


	private function createFile(string $path, PhpFile|string|null $file = null): void
	{
		$this->writer->createFile($path, $file);
	}
}
