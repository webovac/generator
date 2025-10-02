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
			$this->createFile("$basePath/$name.php", $generator->generateModule());
			$this->createFile("$basePath/Presenter/{$name}Presenter.php", $generator->generatePresenterTrait());
			$this->createFile("$basePath/Presenter/{$name}PresenterTemplate.php", $generator->generatePresenterTemplateTrait());
			$this->createFile("$basePath/Control/{$name}Template.php", $generator->generateTemplateTrait());
			$this->createFile("$basePath/Lib/{$name}TemplateFactory.php", $generator->generateTemplateFactoryTrait());
			$this->createFile("$basePath/Control/$name/{$name}Control.php", $generator->generateMainComponent());
			$this->createFile("$basePath/Control/$name/I{$name}Control.php", $generator->generateMainFactory());
//			$this->createFile("$basePath/Control/$name/{$name}Template.php", $generator->generateMainTemplate());
			$this->createFile("$basePath/Control/$name/$lname.latte", $generator->generateMainLatte());
			if ($withModel) {
				$this->createFile("$basePath/Model/{$name}DataModel.php", $generator->generateDataModelTrait());
				$this->createFile("$basePath/Model/{$name}Orm.php", $generator->generateModelTrait());
			}
			if ($withDIExtension) {
				$this->createFile("$basePath/DI/{$name}Extension.php", $generator->generateDIExtension());
				$this->createFile("$basePath/DI/config.neon", $generator->generateConfigNeon());
			}
			if ($withInstallFile) {
				$this->createFile("$basePath/config/manipulations/insert.$type.$lname.neon", $generator->generateInstallNeon($type));
			}
			if ($withMigrationGroup) {
				$this->createFile("$basePath/config/definitions/001-init.neon");
			}
		}
		$this->updateBuildFiles($name, $withModel, isPackage: $isPackage, moduleNamespace: $moduleNamespace);
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
				$this->removeModel($m[1], $name, isPackage: $isPackage, moduleNamespace: $moduleNamespace);
			}
		}
		$this->updateBuildFiles($name, isPackage: $isPackage, mode: self::MODE_REMOVE, moduleNamespace: $moduleNamespace);
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
		$this->createFile("$basePath/$name/{$name}Template.php", $generator->generateTemplate(BaseTemplate::class));
		$this->createFile("$basePath/$name/I{$name}Control.php", $generator->generateFactory());
		$this->createFile("$basePath/$name/{$name}Control.php", $generator->generateControl(BaseControl::class));
		$this->createFile("$basePath/$name/$templateName.latte", $generator->generateLatte());
		if ($type === ComponentGenerator::TYPE_DATASET) {
			$this->createFile("$basePath/$name/$lname.neon", $generator->generateDatasetNeon());
		}
		if ($type === ComponentGenerator::TYPE_MENU) {
			$this->createFile("$basePath/$name/$lname.neon", $generator->generateMenuNeon());
		}
		if (!$module) {
			return;
		}
		$mainComponentPath = "$basePath/$module/{$module}Control.php";
		$this->createFile($mainComponentPath, $generator->generateUpdatedMainComponent($mainComponentPath));
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
		$this->createFile($mainComponentPath, $generator->generateUpdatedMainComponent($mainComponentPath));
	}


	public function removeModel(
		string $name,
		?string $module = null,
		bool $withTraits = true,
		bool $isPackage = false,
		?string $moduleNamespace = null
	): void
	{
		$this->updateModelFiles($name, $module, withTraits: $withTraits, isPackage: $isPackage, moduleNamespace: $moduleNamespace, mode: self::MODE_REMOVE);
		$basePath = "$this->appDir/" . ($module ? "Module/$module/" : '') . "Model/$name";
		FileSystem::delete($basePath);
	}


	public function createModel(
		string $name,
		?string $module = null,
		bool $withTraits = true,
		bool $withConventions = false,
		array $entityImplements = [],
		array $repositoryImplements = [],
		bool $isPackage = false,
		?string $moduleNamespace = null,
	): void
	{
		$this->updateModelFiles($name, $module, $withTraits, $withConventions, $entityImplements, $repositoryImplements, $isPackage, $moduleNamespace);
	}


	public function checkModel(
		Entity $entity,
		?Module $module = null,
	): void
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


	private function updateModelFiles(
		string $name,
		?string $module = null,
		bool $withTraits = true,
		bool $withConventions = false,
		array $entityImplements = [],
		array $repositoryImplements = [],
		bool $isPackage = false,
		?string $moduleNamespace = null,
		string $mode = self::MODE_ADD,
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
			mode: $mode,
		);
		$modelBasePath = "$this->buildDir/Model";
		$basePath = "$this->appDir/" . ($module ? "Module/$module/" : '') . "Model";

		if ($withTraits || $isPackage) {
			$entityPath = "$modelBasePath/$name/$name.php";
			$entity = $generator->generateUpdatedEntity($entityPath, $entityImplements);
			$this->createFile($entityPath, $entity);
			$mapperPath = "$modelBasePath/$name/{$name}Mapper.php";
			$this->createFile($mapperPath, $generator->generateUpdatedMapper($mapperPath));
			$repositoryPath = "$modelBasePath/$name/{$name}Repository.php";
			$this->createFile($repositoryPath, $generator->generateUpdatedRepository($repositoryPath, $repositoryImplements));
			$dataObjectPath = "$modelBasePath/$name/{$name}Data.php";
			$this->createFile($dataObjectPath,  $generator->generateUpdatedDataObject($dataObjectPath));
			$dataRepositoryPath = "$modelBasePath/$name/{$name}DataRepository.php";
			$this->createFile($dataRepositoryPath, $generator->generateUpdatedDataRepository($dataRepositoryPath));
			if ($mode === self::MODE_REMOVE && $generator->shouldEntityBeDeleted($entity)) {
				FileSystem::delete("$modelBasePath/$name");
			}
		}
		if ($isPackage && $mode === self::MODE_ADD) {
			return;
		}
		if ($module && $mode === self::MODE_ADD) {
			$className = $withTraits ? "$module$name" : $name;
			$this->createFile("$basePath/$name/$className.php", $generator->generateEntityTrait());
			$this->createFile("$basePath/$name/{$className}Mapper.php", $generator->generateMapperTrait());
			$this->createFile("$basePath/$name/{$className}Repository.php", $generator->generateRepositoryTrait());
			$this->createFile("$basePath/$name/{$className}Data.php", $generator->generateDataObjectTrait());
			$this->createFile("$basePath/$name/{$className}DataRepository.php", $generator->generateDataRepositoryTrait());
			if ($withConventions) {
				$this->createFile("$basePath/$name/{$className}Conventions.php", $generator->generateConventions());
			}
		}
//		$dataModelPath = "$basePath/{$module}DataModel.php";
//		$this->createFile($dataModelPath, $generator->generateUpdatedDataModel($dataModelPath));
		$modelPath = "$basePath/{$module}Orm.php";
		$this->createFile($modelPath, $generator->generateUpdatedModel($modelPath));
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
		$this->createFile($entityPath, $generator->generateEntityProperties($entityPath, $table));
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
		$this->createFile($entityPath, $generator->generateEntityPropertyManyHasMany($entityPath, $from, $to, $isMain));
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
		$this->createFile($entityPath, $generator->generateEntityPropertyOneHasMany($entityPath, $table, $foreign));
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
		$this->createFile("$basePath/{$name}.php", $generator->generateService());
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
		$this->createFile("$basePath/{$name}.php", $generator->generateCommand());
	}


	public function removeCommand(string $name, ?string $module)
	{
		FileSystem::delete("$this->appDir/" . ($module ? "Module/$module/" : '') . "Command/$name.php");
	}


	private function updateBuildFiles(string $name, bool $withModel = true, bool $isPackage = false, string $mode = self::MODE_ADD, string $moduleNamespace = "App\\Module"): void
	{
		$generator = new ModuleGenerator(
			name: $name,
			buildNamespace: $this->buildNamespace,
			moduleNamespace: $isPackage ? $moduleNamespace : "$this->appNamespace\\Module",
			mode: $mode,
		);
		$basePresenterPath = "$this->buildDir/Presenter/BasePresenter.php";
		$this->createFile("$this->buildDir/Presenter/BasePresenter.php", $generator->generateUpdatedBasePresenter($basePresenterPath));
		$basePresenterTemplatePath = "$this->buildDir/Presenter/BasePresenterTemplate.php";
		$this->createFile("$this->buildDir/Presenter/BasePresenterTemplate.php", $generator->generateUpdatedBasePresenterTemplate($basePresenterTemplatePath));
		$baseTemplatePath = "$this->buildDir/Control/BaseTemplate.php";
		$this->createFile("$this->buildDir/Control/BaseTemplate.php", $generator->generateUpdatedBaseTemplate($baseTemplatePath));
		$baseTemplateFactoryPath = "$this->buildDir/Lib/BaseTemplateFactory.php";
		$this->createFile($baseTemplateFactoryPath, $generator->generateUpdatedTemplateFactory($baseTemplateFactoryPath));

		if ($withModel) {
			$modelPath = "$this->buildDir/Model/Orm.php";
			$this->createFile($modelPath, $generator->generateUpdatedModel($modelPath));
			$dataModelPath = "$this->buildDir/Model/DataModel.php";
			$this->createFile($dataModelPath, $generator->generateUpdatedDataModel($dataModelPath));
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
