<?php

declare(strict_types=1);

namespace Webovac\Generator;

use Build\Control\BaseTemplate;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;
use Nette\Utils\Arrays;
use Nette\Utils\FileSystem;
use Stepapo\Model\Definition\Config\Foreign;
use Stepapo\Model\Definition\Config\Table;
use Webovac\Core\Control\BaseControl;
use Webovac\Generator\Config\Entity;
use Webovac\Generator\Config\Module;
use Webovac\Generator\Lib\BuildGenerator;


class Generator
{
	public const string MODE_ADD = 'add';
	public const string MODE_REMOVE = 'remove';


	public function __construct(
		protected string $appNamespace = 'App',
		protected string $appDir = 'app',
		protected string $buildNamespace = 'Build',
		protected string $buildDir = 'build',
	) {}


	public function createModule(
		string $name,
		bool $withModel = false,
		bool $withDIExtension = false,
		bool $withMigrationGroup = false,
		bool $withInstallGroups = false,
		bool $withInstallFile = false,
		string $type = 'module',
		bool $isPackage = false,
		string $moduleNamespace = "App\\Module",
	): void
	{
		$generator = new ModuleGenerator(
			name: $name,
			buildNamespace: $this->buildNamespace,
			moduleNamespace: $isPackage ? $moduleNamespace : "$this->appNamespace\\Module",
			withDefinitionGroup: $withMigrationGroup,
			withManipulationGroup: $withInstallGroups,
		);
		$basePath = "$this->appDir/Module/$name/";
		$lname = lcfirst($name);

		if (!$isPackage) {
			$this->createFile("$basePath/$name.php", $generator->createModule());
			$this->createFile("$basePath/Presenter/{$name}Presenter.php", $generator->createPresenterTrait());
			$this->createFile("$basePath/Presenter/{$name}PresenterTemplate.php", $generator->createPresenterTemplateTrait());
			$this->createFile("$basePath/Control/{$name}Template.php", $generator->createTemplateTrait());
			$this->createFile("$basePath/Lib/{$name}TemplateFactory.php", $generator->createTemplateFactoryTrait());
			$this->createFile("$basePath/Control/$name/{$name}Control.php", $generator->createMainComponent());
			$this->createFile("$basePath/Control/$name/I{$name}Control.php", $generator->createMainFactory());
//			$this->createFile("$basePath/Control/$name/{$name}Template.php", $generator->generateMainTemplate());
			$this->createFile("$basePath/Control/$name/$lname.latte", $generator->createMainLatte());
			if ($withModel) {
				$this->createFile("$basePath/Model/{$name}DataModel.php", $generator->createDataModelTrait());
				$this->createFile("$basePath/Model/{$name}Orm.php", $generator->createModelTrait());
			}
			if ($withDIExtension) {
				$this->createFile("$basePath/DI/{$name}Extension.php", $generator->createDIExtension());
				$this->createFile("$basePath/DI/config.neon", $generator->createConfigNeon());
			}
			if ($withInstallFile) {
				$this->createFile("$basePath/config/manipulations/insert.$type.$lname.neon", $generator->createInstallNeon($type));
			}
			if ($withMigrationGroup) {
				$this->createFile("$basePath/config/definitions/001-init.neon");
			}
		}
	}


	public function removeModule(string $name, bool $isPackage = false, string $moduleNamespace = "App\\Module"): void
	{
		$basePath = "$this->appDir/Module/$name/";
		$modelPath = "$basePath/Model/{$name}Orm.php";
		if (file_exists($modelPath)) {
			$model = PhpFile::fromCode(@file_get_contents($modelPath));
			$class = Arrays::first($model->getClasses());
			foreach (explode("\n", $class->getComment() ?: '') as $comment) {
				preg_match('/@property-read (.+)Repository \$(.+)Repository/', $comment, $m);
				if (!isset($m[1])) {
					continue;
				}
				$this->removeModel($m[1], $name);
			}
		}
		$basePath = "$this->appDir/Module/$name";
		FileSystem::delete($basePath);
	}


	public function createComponent(
		string $name,
		?string $module = null,
		?string $entity = null,
		bool $withTemplateName = false,
		?string $type = null,
		?string $factory = null,
	): void
	{
		$generator = new ComponentGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			module: $module,
			entity: $entity,
			withTemplateName: $withTemplateName,
			type: $type,
			factory: $factory,
		);
		$basePath = "$this->appDir/Module/$module/Control";
		$lname = lcfirst($name);
		$templateName = $withTemplateName ? 'default' : $lname;
		$this->createFile("$basePath/$name/{$name}Template.php", $generator->createTemplate(BaseTemplate::class));
		$this->createFile("$basePath/$name/I{$name}Control.php", $generator->createFactory());
		$this->createFile("$basePath/$name/{$name}Control.php", $generator->createControl(BaseControl::class));
		$this->createFile("$basePath/$name/$templateName.latte", $generator->createLatte());
		if ($type === ComponentGenerator::TYPE_DATASET) {
			$this->createFile("$basePath/$name/$lname.neon", $generator->createDatasetNeon());
		}
		if ($type === ComponentGenerator::TYPE_MENU) {
			$this->createFile("$basePath/$name/$lname.neon", $generator->createMenuNeon());
		}
		if (!$module) {
			return;
		}
		$mainComponentPath = "$basePath/$module/{$module}Control.php";
		$this->createFile($mainComponentPath, $generator->updateMainComponent($mainComponentPath));
	}


	public function removeComponent(
		string $name,
		?string $module = null,
		?string $entity = null,
	): void
	{
		$generator = new ComponentGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			module: $module,
			entity: $entity,
			mode: Generator::MODE_REMOVE,
		);
		$basePath = "$this->appDir/" . ($module ? "Module/$module/" : '') . "Control/$name";
		FileSystem::delete($basePath);
		if (!$module) {
			return;
		}
		$mainComponentPath = "$this->appDir/Module/$module/Control/$module/{$module}Control.php";
		$this->createFile($mainComponentPath, $generator->updateMainComponent($mainComponentPath));
	}


	public function removeModel(
		string $name,
		?string $module = null,
	): void
	{
		$basePath = "$this->appDir/" . ($module ? "Module/$module/" : '') . "Model/$name";
		FileSystem::delete($basePath);
	}


	public function createBuildEntity(Entity $entity): void
	{
		$name = $entity->name;
		$generator = new ModelGenerator(
			name: $entity->name,
			appNamespace: $this->appNamespace,
			buildNamespace: $this->buildNamespace,
			withTraits: $entity->withTraits,
			withConventions: $entity->withConventions,
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
			name: $entity->name,
			appNamespace: $this->appNamespace,
			buildNamespace: $this->buildNamespace,
			moduleNamespace: $module->namespace,
			module: $module->name,
			withTraits: $entity->withTraits,
			withConventions: $entity->withConventions,
		);
		$modelBasePath = "$this->buildDir/Model";
		$entityPath = "$modelBasePath/$name/$name.php";
		$this->createFile($entityPath, $generator->updateEntity($entityPath, $entity->entityImplements));
		$mapperPath = "$modelBasePath/$name/{$name}Mapper.php";
		$this->createFile($mapperPath, $generator->updateMapper($mapperPath));
		$repositoryPath = "$modelBasePath/$name/{$name}Repository.php";
		$this->createFile($repositoryPath, $generator->updateRepository($repositoryPath, $entity->repositoryImplements));
		$dataObjectPath = "$modelBasePath/$name/{$name}Data.php";
		$this->createFile($dataObjectPath,  $generator->updateDataObject($dataObjectPath));
		$dataRepositoryPath = "$modelBasePath/$name/{$name}DataRepository.php";
		$this->createFile($dataRepositoryPath, $generator->updateDataRepository($dataRepositoryPath));
	}


	public function createModel(
		string $name,
		?string $module = null,
		bool $withTraits = true,
		bool $withConventions = false,
		bool $isPackage = false,
		?string $moduleNamespace = null,
	): void
	{
		$generator = new ModelGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			buildNamespace: $this->buildNamespace,
			moduleNamespace: $moduleNamespace,
			module: $module,
			withTraits: $withTraits,
			withConventions: $withConventions,
		);
		$basePath = "$this->appDir/" . ($module ? "Module/$module/" : '') . "Model";
		if ($isPackage) {
			return;
		}
		$className = $withTraits ? "$module$name" : $name;
		$this->createFile("$basePath/$name/$className.php", $generator->createEntityTrait());
		$this->createFile("$basePath/$name/{$className}Mapper.php", $generator->createMapperTrait());
		$this->createFile("$basePath/$name/{$className}Repository.php", $generator->createRepositoryTrait());
		$this->createFile("$basePath/$name/{$className}Data.php", $generator->generateDataObjectTrait());
		$this->createFile("$basePath/$name/{$className}DataRepository.php", $generator->createDataRepositoryTrait());
		if ($withConventions) {
			$this->createFile("$basePath/$name/{$className}Conventions.php", $generator->createConventions());
		}
		$modelPath = "$basePath/{$module}Orm.php";
		$this->createFile($modelPath, $generator->updateModel($modelPath));
	}


	public function checkEntity(Entity $entity, ?Module $module = null): void
	{
		$generator = new ModelGenerator(
			name: $entity->name,
			appNamespace: $this->appNamespace,
			buildNamespace: $this->buildNamespace,
			moduleNamespace: $module->namespace,
			module: $module->name,
			withTraits: $entity->withTraits,
			withConventions: $entity->withConventions,
		);
		$modelBasePath = $module && !$entity->withTraits
			? "$this->appDir/Module/$module->name/Model"
			: "$this->buildDir/Model";
		$entityPath = "$modelBasePath/$entity->name/$entity->name.php";
		$this->createFile($entityPath, $generator->checkFileImplements($entityPath, $entity->entityImplements));
		$repositoryPath = "$modelBasePath/$entity->name/{$entity->name}Repository.php";
		$this->createFile($repositoryPath, $generator->checkFileImplements($repositoryPath, $entity->repositoryImplements));
	}


	public function getEntityComments(Table $table, ?string $module = null): ?string
	{
		$name = $table->getPhpName();
		$generator = new ModelGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			buildNamespace: $this->buildNamespace,
			module: $module,
		);
		$basePath = $module ? "$this->appDir/$module/Model" : "$this->buildDir/Model";
		$entityPath = "$basePath/$name/$name.php";
		return $generator->getEntityComments($entityPath, $table);
	}


	public function updateEntity(Table $table, ?string $module = null)
	{
		$name = $table->getPhpName();
		$generator = new ModelGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			buildNamespace: $this->buildNamespace,
			module: $module,
		);
		$basePath = $module ? "$this->appDir/$module/Model" : "$this->buildDir/Model";
		$entityPath = "$basePath/$name/$name.php";
		$this->createFile($entityPath, $generator->createEntityProperties($entityPath, $table));
	}


	public function updateEntityManyHasMany(Foreign $from, Foreign $to, bool $isMain = false, ?string $module = null)
	{
		$name = $from->getPhpTable();
		$generator = new ModelGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			buildNamespace: $this->buildNamespace,
			module: $module,
		);
		$basePath = $module ? "$this->appDir/$module/Model" : "$this->buildDir/Model";
		$entityPath = "$basePath/$name/$name.php";
		$this->createFile($entityPath, $generator->createEntityPropertyManyHasMany($entityPath, $from, $to, $isMain));
	}


	public function updateEntityOneHasMany(Table $table, Foreign $foreign, ?string $module = null)
	{
		$name = $foreign->getPhpTable();
		$generator = new ModelGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			buildNamespace: $this->buildNamespace,
			module: $module,
		);
		$basePath = $module ? "$this->appDir/$module/Model" : "$this->buildDir/Model";
		$entityPath = "$basePath/$name/$name.php";
		$this->createFile($entityPath, $generator->createEntityPropertyOneHasMany($entityPath, $table, $foreign));
	}


	public function updateEntitySortComments(Table $table, ?string $module = null)
	{
		$name = $table->getPhpName();
		$generator = new ModelGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			buildNamespace: $this->buildNamespace,
			module: $module,
		);
		$basePath = $module ? "$this->appDir/$module/Model" : "$this->buildDir/Model";
		$entityPath = "$basePath/$name/$name.php";
		$this->createFile($entityPath, $generator->sortEntityProperties($entityPath));
	}


	public function createService(
		string $name,
		?string $module = null,
	) {
		$generator = new ServiceGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			module: $module
		);
		$basePath = "$this->appDir/" . ($module ? "Module/{$module}/" : '') . "Lib";
		$this->createFile("$basePath/{$name}.php", $generator->createService());
	}


	public function removeService(string $name, ?string $module)
	{
		FileSystem::delete("$this->appDir/" . ($module ? "Module/$module/" : '') . "Lib/$name.php");
	}


	public function createCommand(
		string $name,
		?string $module = null,
	) {
		$generator = new CommandGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			module: $module
		);
		$basePath = "$this->appDir/" . ($module ? "Module/{$module}/" : '') . "Command";
		$this->createFile("$basePath/{$name}.php", $generator->createCommand());
	}


	public function removeCommand(string $name, ?string $module)
	{
		FileSystem::delete("$this->appDir/" . ($module ? "Module/$module/" : '') . "Command/$name.php");
	}


	public function createBuild(): void
	{
		$generator = new BuildGenerator(
			namespace: $this->buildNamespace,
			dir: $this->buildDir,
		);
		$this->createFile("$this->buildDir/Presenter/BasePresenter.php", $generator->createBasePresenter());
		$this->createFile("$this->buildDir/Presenter/BasePresenterTemplate.php", $generator->createBasePresenterTemplate());
		$this->createFile("$this->buildDir/Control/BaseTemplate.php", $generator->createBaseTemplate());
		$this->createFile("$this->buildDir/Lib/BaseTemplateFactory.php", $generator->createTemplateFactory());
		$this->createFile("$this->buildDir/Model/Orm.php", $generator->createModel());
		$this->createFile("$this->buildDir/Model/DataModel.php", $generator->createDataModel());
	}


	public function updateBuild(Module $module, string $moduleNamespace = "App\\Module"): void
	{
		$generator = new ModuleGenerator(
			name: $module->name,
			buildNamespace: $this->buildNamespace,
			moduleNamespace: $module->namespace,
		);
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


	protected function createFile(string $path, PhpFile|string|null $file = null): void
	{
		if ($file instanceof PhpFile) {
			$this->fixConstants($file);
		}
		FileSystem::write($path, $file instanceof PhpFile ? (new CustomPrinter())->printFile($file) : (string) $file, mode: null);
	}


	private function fixConstants(PhpFile $file): void
	{
		foreach ($file->getNamespaces() as $namespace) {
			foreach ($namespace->getClasses() as $class) {
				foreach ($class->getConstants() as $constant) {
					$value = $constant->getValue();
					if ($value instanceof Literal) {
						$lines = explode("\n", (string) $value);
						$correctedLines = [];
						$c = count($lines);
						foreach ($lines as $k => $line) {
							$correctedLines[$k] = ($k === 0 || $k === $c - 1 ? "" : "\t") . trim($line);
						}
						$constant->setValue(new Literal(implode("\n", $correctedLines)));
					}
				}
			}
		}
	}
}
