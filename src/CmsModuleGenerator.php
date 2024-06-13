<?php

declare(strict_types=1);

namespace Webovac\Generator;

use App\Control\BaseTemplate;
use Nette\Application\UI\Template;
use Nette\DI\Attributes\Inject;
use Nette\PhpGenerator\Attribute;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\TraitType;
use Nette\Utils\Arrays;
use Nextras\Orm\Entity\IEntity;
use Webovac\Core\Control\BaseControl;
use Webovac\Core\DI\BaseExtension;
use Webovac\Core\InstallGroup;
use Webovac\Core\MainModuleControl;
use Webovac\Core\MigrationGroup;
use Webovac\Core\Module;


class CmsModuleGenerator
{
	private string $lname;
	private string $namespace;
	private string $module;
	private string $mainControl;
	private string $mainControlInterface;
	private string $web;
	private string $language;


	public function __construct(
		private readonly string $name,
		private readonly string $appNamespace,
		private readonly string $moduleNamespace,
		private readonly bool $withMigrationGroup = false,
		private readonly bool $withInstallGroups = false,
		private readonly string $mode = CmsGenerator::MODE_ADD,
	) {
		$this->lname = lcfirst($this->name);
		$this->namespace = "$this->moduleNamespace\\$this->name";
		$this->module = "$this->namespace\\$this->name";
		$this->mainControl = "$this->namespace\Control\\$this->name\\{$this->name}Control";
		$this->mainControlInterface = "$this->namespace\Control\\$this->name\I{$this->name}Control";
		$this->web = "$this->appNamespace\Model\Web\Web";
		$this->language = "$this->appNamespace\Model\Language\Language";
	}


	public function generateModule(): PhpFile
	{
		$getModuleNameMethod = (new Method('getModuleName'))
			->setPublic()
			->setStatic()
			->setReturnType('string')
			->setBody("return '$this->lname';");

		$class = (new ClassType($this->name))
			->setImplements([Module::class])
			->addMember($getModuleNameMethod);

		$namespace = (new PhpNamespace($this->namespace))
			->addUse(Module::class)
			->add($class);

		if ($this->withMigrationGroup) {
			$getMigrationGroupMethod = (new Method('getMigrationGroup'))
				->setPublic()
				->setReturnType(MigrationGroup::class)
				->setBody(<<<EOT
return new MigrationGroup($this->name::getModuleName(), __DIR__ . '/migrations', [$this->name::getModuleName()]);
EOT);
			$class->addMember($getMigrationGroupMethod);
			$namespace->addUse(MigrationGroup::class);
		}

		if ($this->withInstallGroups) {
			$getInstallGroupsMethod = (new Method('getInstallGroups'))
				->setPublic()
				->setReturnType('array')
				->setBody(<<<EOT
return [
	new InstallGroup('', '', []),
];
EOT);
			$class->addMember($getInstallGroupsMethod);
			$namespace->addUse(InstallGroup::class);
		}

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generatePresenterTrait(): PhpFile
	{
		$injectStartupMethod = (new Method("inject{$this->name}Startup"))
			->setPublic()
			->setReturnType('void')
			->setBody(<<<EOT
\$this->onStartup[] = function () {
	\$this->addComponents('$this->lname', \$this->getComponentList({$this->name}Control::class));
};
EOT);

		$injectAttribute = (new Attribute(Inject::class, []));

		$createComponentMethod = (new Method("createComponent$this->name"))
			->setPublic()
			->setReturnType($this->mainControl)
			->setBody("return \$this->$this->lname->create(\$this->web, \$this->language, \$this->entity);");

		$trait = (new TraitType("{$this->name}Presenter"))
			->addMember($injectStartupMethod)
			->addMember($createComponentMethod);

		$trait->addProperty($this->lname)
			->setAttributes([$injectAttribute])
			->setPublic()
			->setType($this->mainControlInterface);

		$trait->addProperty('web')
			->setPrivate()
			->setType($this->web)
			->setNullable();

		$trait->addProperty('language')
			->setPrivate()
			->setType($this->language);

		$trait->addProperty('entity')
			->setPrivate()
			->setType(IEntity::class)
			->setNullable()
			->setValue(null);

		$namespace = (new PhpNamespace("$this->namespace\Presenter"))
			->addUse($this->language)
			->addUse($this->web)
			->addUse($this->mainControlInterface)
			->addUse($this->mainControl)
			->addUse(Inject::class)
			->addUse(IEntity::class)
			->add($trait);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generatePresenterTemplateTrait(): PhpFile
	{
		$trait = (new TraitType("{$this->name}PresenterTemplate"));

		$namespace = (new PhpNamespace("$this->namespace\Presenter"))
			->add($trait);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateTemplateTrait(): PhpFile
	{
		$trait = (new TraitType("{$this->name}Template"));

		$namespace = (new PhpNamespace("$this->namespace\Control"))
			->add($trait);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateTemplateFactoryTrait(): PhpFile
	{
		$injectCreateMethod = (new Method("inject{$this->name}Create"))
			->setPublic()
			->setbody(<<<EOT
\$this->onCreate[] = function (Template \$template) {
	if (\$template instanceof BaseTemplate) {

	}
};
EOT);
		$trait = (new TraitType("{$this->name}TemplateFactory"))
			->addMember($injectCreateMethod);

		$namespace = (new PhpNamespace("$this->namespace\Lib"))
			->add($trait);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace)
			->addUse(BaseTemplate::class)
			->addUse(Template::class);

		return $file;
	}


	public function generateMainComponent(): PhpFile
	{
		$constructMethod = (new Method('__construct'))
			->setPublic();
		$getModuleNameMethod = (new Method('getModuleName'))
			->setPublic()
			->setStatic()
			->setReturnType('string')
			->setBody("return '$this->lname';");
		$renderMethod = (new Method('render'))
			->setPublic()
			->setReturnType('void')
			->setBody("\$this->template->render(__DIR__ . '/$this->lname.latte');");
		$constructMethod
			->addPromotedParameter('web')
			->setPrivate()
			->setType($this->web);
		$constructMethod
			->addPromotedParameter('language')
			->setPrivate()
			->setType($this->language);
		$constructMethod
			->addPromotedParameter('entity')
			->setPrivate()
			->setType(IEntity::class)
			->setNullable();

		$class = (new ClassType("{$this->name}Control"))
			->setExtends(BaseControl::class)
			->setImplements([MainModuleControl::class])
			->addComment("@property {$this->name}Template \$template")
			->addMember($constructMethod)
			->addMember($getModuleNameMethod)
			->addMember($renderMethod);

		$namespace = (new PhpNamespace("$this->namespace\Control\\$this->name"))
			->addUse(BaseControl::class)
			->addUse(MainModuleControl::class)
			->addUse($this->language)
			->addUse($this->web)
			->addUse(IEntity::class)
			->add($class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateMainFactory(): PhpFile
	{
		$createMethod = (new Method('create'))
			->setReturnType("$this->namespace\Control\\$this->name\\{$this->name}Control");

		$createMethod
			->addParameter('web')
			->setType($this->web);

		$createMethod
			->addParameter('language')
			->setType($this->language);

		$createMethod
			->addParameter('entity')
			->setType(IEntity::class)
			->setNullable()
			->setDefaultValue(null);

		$class = (new InterfaceType("I{$this->name}Control"))
			->addMember($createMethod);

		$namespace = (new PhpNamespace("$this->namespace\Control\\$this->name"))
			->addUse($this->language)
			->addUse($this->web)
			->addUse(IEntity::class)
			->add($class);

		$file = (new PhpFile)->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateMainTemplate(): PhpFile
	{
		$class = (new ClassType("{$this->name}Template"))
			->setExtends(BaseTemplate::class);

		$namespace = (new PhpNamespace("$this->namespace\Control\\$this->name"))
			->addUse(BaseTemplate::class)
			->add($class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateMainLatte(): string
	{
		$latte = <<<EOT
{templateType $this->namespace\Control\\$this->name\\{$this->name}Template}
EOT;

		return $latte;
	}


	public function generateModel(): PhpFile
	{
		$trait = (new TraitType("{$this->name}Orm"));

		$namespace = (new PhpNamespace("$this->namespace\Model"))
			->add($trait);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateDataModel(): PhpFile
	{
		$trait = (new TraitType("{$this->name}DataModel"));

		$namespace = (new PhpNamespace("$this->namespace\Model"))
			->add($trait);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateDIExtension(): PhpFile
	{
		$loadConfigurationMethod = (new Method('loadConfiguration'))
			->setPublic()
			->setReturnType('void')
			->setBody(<<<EOT
\$builder = \$this->getContainerBuilder();
\$this->createSearchExtension(\$builder);
\$this->compiler->loadDefinitionsFromConfig(
	(array) \$this->loadFromFile(__DIR__ . '/config.neon')['services'],
);
EOT);
		$getModuleNameMethod = (new Method('getModuleName'))
			->setProtected()
			->setReturnType('string')
			->setBody(<<<EOT
return $this->name::getModuleName();
EOT);

		$getSearchConfigMethod = (new Method('getSearchConfig'))
			->setProtected()
			->setReturnType('array')
			->setBody(<<<EOT
return [
	'main' => [
		'in' => __DIR__ . '/../',
		'implements' => Module::class,
	],
	'command' => [
		'in' => __DIR__ . '/../',
		'classes' => '*Command',
	],
	'control' => [
		'in' => __DIR__ . '/../',
		'classes' => 'I*Control',
	],
	'lib' => [
		'in' => __DIR__ . '/../',
		'classes' => '$this->namespace\**\Lib\**',
	],
];
EOT);

		$class = (new ClassType("{$this->name}Extension"))
			->setExtends(BaseExtension::class)
			->addMember($loadConfigurationMethod)
			->addMember($getModuleNameMethod)
			->addMember($getSearchConfigMethod);

		$namespace = (new PhpNamespace("$this->namespace\DI"))
			->addUse(BaseExtension::class)
			->addUse(Module::class)
			->addUse($this->module)
			->add($class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateInstallNeon(string $type): string
	{
		$homePage = match($type) {
			'module' => $this->name . ':Home',
			'web' => 'Home',
		};
		$neon = <<<EOT
name: $this->name
homePage: {$homePage}
icon:
translations:
	cs: [title: $this->name, basePath: , description: '']
	en: [title: $this->name, basePath: en, description: '']
pages:
	$homePage:
		icon: 
		translations:
			cs: [title: $this->name, path: , content: '<h1>$this->name</h1>']
			en: [title: $this->name, path: , content: '<h1>$this->name</h1>']
tree:
	$homePage:
EOT;
		return $neon;
	}


	public function generateUpdatedBasePresenter(string $path): PhpFile
	{
		return $this->modifyFileWithTrait($path, "$this->namespace\Presenter\\{$this->name}Presenter");
	}


	public function generateUpdatedBasePresenterTemplate(string $path): PhpFile
	{
		return $this->modifyFileWithTrait($path, "$this->namespace\Presenter\\{$this->name}PresenterTemplate");
	}


	public function generateUpdatedBaseTemplate(string $path): PhpFile
	{
		return $this->modifyFileWithTrait($path, "$this->namespace\Control\\{$this->name}Template");
	}


	public function generateUpdatedTemplateFactory(string $path): PhpFile
	{
		return $this->modifyFileWithTrait($path, "$this->namespace\Lib\\{$this->name}TemplateFactory");
	}


	public function generateUpdatedModel(string $path): PhpFile
	{
		return $this->modifyFileWithTrait($path, "$this->namespace\Model\\{$this->name}Orm");
	}


	public function generateUpdatedDataModel(string $path): PhpFile
	{
		return $this->modifyFileWithTrait($path, "$this->namespace\Model\\{$this->name}DataModel");
	}


	private function modifyFileWithTrait(string $path, string $trait): PhpFile
	{
		$file = PhpFile::fromCode(@file_get_contents($path));
		$class = Arrays::first($file->getClasses());
		$traits = $class->getTraits();
		$namespace = Arrays::first($file->getNamespaces());

		if ($this->mode === CmsGenerator::MODE_ADD && !array_key_exists($trait, $traits)) {
			$class->addTrait($trait);
			$namespace->addUse($trait);
		} elseif ($this->mode === CmsGenerator::MODE_REMOVE && array_key_exists($trait, $traits)) {
			$class->removeTrait($trait);
			$namespace->removeUse($trait);
		}

		return $file;
	}
}
