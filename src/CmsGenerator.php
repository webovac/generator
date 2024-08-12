<?php

declare(strict_types=1);

namespace Webovac\Generator;

use App\Control\BaseTemplate;
use Nette\PhpGenerator\PhpFile;
use Nette\Utils\Arrays;
use Nette\Utils\FileSystem;
use Stepapo\Generator\ComponentGenerator;
use Stepapo\Generator\CustomPrinter;
use Stepapo\Generator\Generator;
use Webovac\Core\Control\BaseControl;


class CmsGenerator extends Generator
{
	public const string MODE_ADD = 'add';
	public const string MODE_REMOVE = 'remove';


	public function __construct(
		protected string $appNamespace = 'App',
		protected string $appDir = 'app',
		protected string $moduleNamespace = 'Webovac',
	) {
		parent::__construct($appNamespace, $appDir);
	}


	public function createModule(
		string $name,
		bool $withModel = false,
		bool $withDIExtension = false,
		bool $withMigrationGroup = false,
		bool $withInstallGroups = false,
		bool $withInstallFile = false,
		string $type = 'module',
	): void
	{
		$generator = new CmsModuleGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			moduleNamespace: $this->moduleNamespace,
			withMigrationGroup: $withMigrationGroup,
			withInstallGroups: $withInstallGroups,
			withInstallFile: $withInstallFile,
		);
		$basePath = "$this->appDir/Module/$name/";
		$lname = lcfirst($name);

		$this->createFile("$basePath/$name.php", $generator->generateModule());
		$this->createFile("$basePath/Presenter/{$name}Presenter.php", $generator->generatePresenterTrait());
		$this->createFile("$basePath/Presenter/{$name}PresenterTemplate.php", $generator->generatePresenterTemplateTrait());
		$this->createFile("$basePath/Control/{$name}Template.php", $generator->generateTemplateTrait());
		$this->createFile("$basePath/Lib/{$name}TemplateFactory.php", $generator->generateTemplateFactoryTrait());
		$this->createFile("$basePath/Control/$name/{$name}Control.php", $generator->generateMainComponent());
		$this->createFile("$basePath/Control/$name/I{$name}Control.php", $generator->generateMainFactory());
//		$this->createFile("$basePath/Control/$name/{$name}Template.php", $generator->generateMainTemplate());
		$this->createFile("$basePath/Control/$name/$lname.latte", $generator->generateMainLatte());
		if ($withModel) {
			$this->createFile("$basePath/Model/{$name}DataModel.php", $generator->generateDataModel());
			$this->createFile("$basePath/Model/{$name}Orm.php", $generator->generateModel());
		}
		if ($withDIExtension) {
			$this->createFile("$basePath/DI/{$name}Extension.php", $generator->generateDIExtension());
			$this->createFile("$basePath/DI/config.neon", $generator->generateConfigNeon());
		}
		if ($withInstallFile) {
			$this->createFile("$basePath/migrations/manipulations/insert.$type.$lname.neon", $generator->generateInstallNeon($type));
		}
		if ($withMigrationGroup) {
			$this->createFile("$basePath/migrations/definitions/001-init.neon");
		}
		$this->updateAppFiles($name, $withModel);
	}


	public function removeModule(string $name, bool $isPackage = false): void
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
				$this->removeCmsModel($m[1], $name, $isPackage);
			}
		}
		$this->updateAppFiles($name, mode: self::MODE_REMOVE);
		$basePath = "$this->appDir/Module/$name";
		FileSystem::delete($basePath);
	}


	public function createCmsComponent(
		string $name,
		?string $module = null,
		?string $entityName = null,
		bool $withTemplateName = false,
		string $type = null,
		string $factory = null,
	): void
	{
		$generator = new CmsComponentGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			module: $module,
			entityName: $entityName,
			withTemplateName: $withTemplateName,
			type: $type,
			factory: $factory,
		);
		$basePath = "$this->appDir/Module/$module/Control";
		$lname = lcfirst($name);
		$this->createFile("$basePath/$name/{$name}Template.php", $generator->generateTemplate(BaseTemplate::class));
		$this->createFile("$basePath/$name/I{$name}Control.php", $generator->generateFactory());
		$this->createFile("$basePath/$name/{$name}Control.php", $generator->generateControl(BaseControl::class));
		$this->createFile("$basePath/$name/$lname.latte", $generator->generateLatte());
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


	public function removeCmsComponent(
		string $name,
		?string $module = null,
		?string $entityName = null,
		bool $isPackage = false,
	): void
	{
		$generator = new CmsComponentGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			module: $module,
			entityName: $entityName,
			mode: CmsGenerator::MODE_REMOVE,
		);
		$basePath = "$this->appDir/" . ($module ? "Module/$module/" : '') . "Control/$name";
		FileSystem::delete($basePath);
		if (!$module) {
			return;
		}
		$mainComponentPath = "$this->appDir/Module/$module/Control/$module/{$module}Control.php";
		$this->createFile($mainComponentPath, $generator->generateUpdatedMainComponent($mainComponentPath));
	}


	public function removeCmsModel(
		string $name,
		?string $module = null,
		bool $withTraits = false,
		bool $isPackage = false,
	): void
	{
		$this->updateModelFiles($name, $module, withTraits: $withTraits, isPackage: $isPackage, mode: self::MODE_REMOVE);
		$basePath = "$this->appDir/" . ($module ? "Module/$module/" : '') . "Model/$name";
		FileSystem::delete($basePath);
	}


	public function createCmsModel(
		string $name,
		?string $module = null,
		bool $withTraits = false,
		bool $withConventions = false,
		array $implements = [],
		bool $isPackage = false,
	): void
	{
		$this->updateModelFiles($name, $module, $withTraits, $withConventions, $implements, $isPackage);
	}


	private function updateModelFiles(
		string $name,
		?string $module = null,
		bool $withTraits = false,
		bool $withConventions = false,
		array $implements = [],
		bool $isPackage = false,
		string $mode = self::MODE_ADD,
	): void
	{
		$generator = new CmsModelGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			moduleNamespace: $this->moduleNamespace,
			module: $module,
			withTraits: $withTraits,
			withConventions: $withConventions,
			mode: $mode,
		);
		$modelBasePath = "$this->appDir/Model";
		$basePath = "$this->appDir/" . ($module ? "Module/$module/" : '') . "Model";

		if ($withTraits || $isPackage) {
			$entityPath = "$modelBasePath/$name/$name.php";
			$entity = $generator->generateUpdatedEntity($entityPath, $implements);
			$this->createFile($entityPath, $entity);
			$mapperPath = "$modelBasePath/$name/{$name}Mapper.php";
			$this->createFile($mapperPath, $generator->generateUpdatedMapper($mapperPath));
			$repositoryPath = "$modelBasePath/$name/{$name}Repository.php";
			$this->createFile($repositoryPath, $generator->generateUpdatedRepository($repositoryPath));
			$dataObjectPath = "$modelBasePath/$name/{$name}Data.php";
			$this->createFile($dataObjectPath,  $generator->generateUpdatedDataObject($dataObjectPath));
			$dataRepositoryPath = "$modelBasePath/$name/{$name}DataRepository.php";
			$this->createFile($dataRepositoryPath, $generator->generateUpdatedDataRepository($dataRepositoryPath));
		}
		if ($mode === self::MODE_REMOVE && $generator->shouldEntityBeDeleted($entity)) {
			FileSystem::delete("$modelBasePath/$name");
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
//		$modelPath = "$basePath/{$module}Orm.php";
//		$this->createFile($modelPath, $generator->generateUpdatedModel($modelPath));
	}


	public function installModule(
		string $name,
		array $entities = [],
	): void
	{
		foreach ($entities as $key => $value) {
			if (is_numeric($key)) {
				$entityName = $value;
				$implements = [];
			} else {
				$entityName = $key;
				$implements = $value;
			}
			$this->createCmsModel(
				name: $entityName,
				module: ucfirst($name),
				implements: $implements,
				isPackage: true,
			);
		}
		$this->updateAppFiles(ucfirst($name), (bool) $entities);
	}


	public function uninstallModule(string $name): void
	{
		$this->removeModule($name, isPackage: true);
	}


	private function updateAppFiles(string $name, bool $withModel = true, string $mode = self::MODE_ADD): void
	{
		$generator = new CmsModuleGenerator(
			name: $name,
			appNamespace: $this->appNamespace,
			moduleNamespace: $this->moduleNamespace,
			mode: $mode
		);
		$basePresenterPath = "$this->appDir/Presenter/BasePresenter.php";
		$this->createFile("$this->appDir/Presenter/BasePresenter.php", $generator->generateUpdatedBasePresenter($basePresenterPath));
		$basePresenterTemplatePath = "$this->appDir/Presenter/BasePresenterTemplate.php";
		$this->createFile("$this->appDir/Presenter/BasePresenterTemplate.php", $generator->generateUpdatedBasePresenterTemplate($basePresenterTemplatePath));
		$baseTemplatePath = "$this->appDir/Control/BaseTemplate.php";
		$this->createFile("$this->appDir/Control/BaseTemplate.php", $generator->generateUpdatedBaseTemplate($baseTemplatePath));
		$baseTemplateFactoryPath = "$this->appDir/Lib/TemplateFactory.php";
		$this->createFile($baseTemplateFactoryPath, $generator->generateUpdatedTemplateFactory($baseTemplateFactoryPath));

		if ($withModel) {
			$modelPath = "$this->appDir/Model/Orm.php";
			$this->createFile($modelPath, $generator->generateUpdatedModel($modelPath));
			$dataModelPath = "$this->appDir/Model/DataModel.php";
			$this->createFile($dataModelPath, $generator->generateUpdatedDataModel($dataModelPath));
		}
	}


	private function createFile(string $path, PhpFile|string|null $file = null): void
	{
		FileSystem::write($path, $file instanceof PhpFile ? (new CustomPrinter())->printFile($file) : (string) $file, mode: null);
	}
}