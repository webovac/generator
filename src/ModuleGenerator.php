<?php

declare(strict_types=1);

namespace Webovac\Generator;

use AllowDynamicProperties;
use Build\Control\BaseTemplate;
use Nette\Application\UI\Presenter;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Bridges\ApplicationLatte\TemplateFactory;
use Nette\DI\Attributes\Inject;
use Nette\InvalidArgumentException;
use Nette\PhpGenerator\Attribute;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\TraitType;
use Nette\Utils\Arrays;
use Nextras\Orm\Model\Model;
use Stepapo\Model\Definition\DefinitionGroup;
use Stepapo\Model\Definition\HasDefinitionGroup;
use Stepapo\Model\Manipulation\ManipulationGroup;
use Stepapo\Utils\DI\StepapoExtension;
use Stepapo\Utils\Factory;
use Stepapo\Utils\Injectable;
use Webovac\Core\Control\BaseControl;
use Webovac\Core\Core;
use Webovac\Core\MainModuleControl;
use Webovac\Core\Model\CmsEntity;
use Webovac\Core\Module;


class ModuleGenerator
{
	private string $lname;
	private string $namespace;
	private string $module;
	private string $mainControl;
	private string $mainControlInterface;


	public function __construct(
		private readonly string $name,
		private readonly string $buildNamespace,
		private readonly string $moduleNamespace,
		private readonly bool $withDefinitionGroup = false,
		private readonly bool $withManipulationGroup = false,
		private readonly string $mode = Generator::MODE_ADD,
	) {
		$this->lname = lcfirst($this->name);
		$this->namespace = "$this->moduleNamespace\\$this->name";
		$this->module = "$this->namespace\\$this->name";
		$this->mainControl = "$this->namespace\Control\\$this->name\\{$this->name}Control";
		$this->mainControlInterface = "$this->namespace\Control\\$this->name\I{$this->name}Control";
	}


	public function generateModule(): PhpFile
	{
		$getModuleNameMethod = (new Method('getModuleName'))
			->setPublic()
			->setStatic()
			->setReturnType('string')
			->setBody("return '$this->lname';");

		$getCliSetupMethod = (new Method('getCliSetup'))
			->setPublic()
			->setStatic()
			->setReturnType('array')
			->setBody("return ['icon' => '', 'color' => 'white/blue'];");

		$class = (new ClassType($this->name))
			->setImplements([Module::class])
			->addMember($getModuleNameMethod)
			->addMember($getCliSetupMethod);

		$namespace = (new PhpNamespace($this->namespace))
			->addUse(Module::class)
			->add($class);

		if ($this->withDefinitionGroup) {
			$getDefinitionGroupMethod = (new Method('getDefinitionGroup'))
				->setPublic()
				->setReturnType(DefinitionGroup::class)
				->setBody(<<<EOT
return new DefinitionGroup($this->name::getModuleName(), $this->name::class, [Core::getModuleName()]);
EOT);
			$class->addMember($getDefinitionGroupMethod);
			$class->addImplement(HasDefinitionGroup::class);
			$namespace->addUse(DefinitionGroup::class);
			$namespace->addUse(Core::class);
		}

		if ($this->withManipulationGroup) {
			$getManipulationGroups = (new Method('getManipulationGroups'))
				->setPublic()
				->setReturnType('array')
				->setBody(<<<EOT
return [
	'' => new ManipulationGroup('', '', []),
];
EOT);
			$class->addMember($getManipulationGroups);
			$namespace->addUse(ManipulationGroup::class);
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
	
};
EOT);

		$injectAttribute = (new Attribute(Inject::class, []));

		$createComponentMethod = (new Method("createComponent$this->name"))
			->setPublic()
			->setReturnType($this->mainControl)
			->setBody("return \$this->$this->lname->create(\$this->entity);");

		$trait = (new TraitType("{$this->name}Presenter"))
			->addMember($injectStartupMethod)
			->addMember($createComponentMethod);

		$trait->addProperty($this->lname)
			->setAttributes([$injectAttribute])
			->setPublic()
			->setType($this->mainControlInterface);

		$trait->addProperty('entity')
			->setPrivate()
			->setType(CmsEntity::class)
			->setNullable()
			->setValue(null);

		$namespace = (new PhpNamespace("$this->namespace\Presenter"))
			->addUse($this->mainControlInterface)
			->addUse($this->mainControl)
			->addUse(Inject::class)
			->addUse(CmsEntity::class)
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
		$renderMethod = (new Method('render'))
			->setPublic()
			->setReturnType('void')
			->setBody("\$this->template->render(__DIR__ . '/$this->lname.latte');");
		$constructMethod
			->addPromotedParameter('entity')
			->setPrivate()
			->setType(CmsEntity::class)
			->setNullable();

		$class = (new ClassType("{$this->name}Control"))
			->setExtends(BaseControl::class)
			->setImplements([MainModuleControl::class])
//			->addComment("@property {$this->name}Template \$template")
			->addMember($constructMethod)
			->addMember($renderMethod);

		$namespace = (new PhpNamespace("$this->namespace\Control\\$this->name"))
			->addUse(BaseControl::class)
			->addUse(MainModuleControl::class)
			->addUse(CmsEntity::class)
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
			->addParameter('entity')
			->setType(CmsEntity::class)
			->setNullable()
			->setDefaultValue(null);

		$class = (new InterfaceType("I{$this->name}Control"))
			->setExtends(Factory::class)
			->addMember($createMethod);

		$namespace = (new PhpNamespace("$this->namespace\Control\\$this->name"))
			->addUse(Factory::class)
			->addUse(CmsEntity::class)
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
		return <<<EOT
{templateType $this->namespace\Control\\$this->name\\{$this->name}Template}

EOT;
	}


	public function generateModelTrait(): PhpFile
	{
		$trait = (new TraitType("{$this->name}Orm"));

		$namespace = (new PhpNamespace("$this->namespace\Model"))
			->add($trait);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateDataModelTrait(): PhpFile
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
		$class = (new ClassType("{$this->name}Extension"))
			->setExtends(StepapoExtension::class);

		$namespace = (new PhpNamespace("$this->namespace\DI"))
			->addUse(StepapoExtension::class)
			->add($class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateConfigNeon(): string
	{
		return <<<EOT
services:

EOT;
	}


	public function generateInstallNeon(string $type): string
	{
		if ($type === 'module') {
			return <<<EOT
class: Build\Model\Module\ModuleData
items:
	$this->name:
		name: $this->name
		homePage: $this->name:Home
		icon:
		translations:
			cs: [title: $this->name, basePath: $this->lname, description: '']
			en: [title: $this->name, basePath: $this->lname, description: '']
		pages:
			$this->name:Home:
				icon: 
				translations:
					cs: [title: $this->name, path: , content: '<h1>$this->name</h1>']
					en: [title: $this->name, path: , content: '<h1>$this->name</h1>']
		tree:
			$this->name:Home:

EOT;
		} else if ($type === 'web') {
			return <<<EOT
class: Build\Model\Web\WebData
items:
	$this->lname:
		host: %host%
		code: $this->lname
		homePage: Home
		color: ''
		complementaryColor: ''
		iconBackgroundColor: ''
		layout: default 
		translations:
			cs: [title: $this->name]
			en: [title: $this->name]
		pages:
			Home:
				icon: 
				translations:
					cs: [title: $this->name, path: , content: '<h1>$this->name</h1>']
					en: [title: $this->name, path: en, content: '<h1>$this->name</h1>']
		webModules: [Admin, Auth]
		tree:
			Home:

EOT;
		}
		return '';
	}


	public function generateBasePresenter(): PhpFile
	{
		$class = (new ClassType("BasePresenter"))
			->setExtends(Presenter::class);

		$namespace = (new PhpNamespace("$this->buildNamespace\Presenter"))
			->add($class)
			->addUse(Presenter::class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateUpdatedBasePresenter(string $path): PhpFile
	{
		return $this->modifyFileWithTrait(
			$path,
			"$this->namespace\Presenter\\{$this->name}Presenter",
			fn() => $this->generateBasePresenter(),
		);
	}


	public function generateBasePresenterTemplate(): PhpFile
	{
		$baseTemplate = "$this->buildNamespace\Control\BaseTemplate";
		$class = (new ClassType("BasePresenterTemplate"))
			->setExtends($baseTemplate);

		$namespace = (new PhpNamespace("$this->buildNamespace\Presenter"))
			->add($class)
			->addUse($baseTemplate);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateUpdatedBasePresenterTemplate(string $path): PhpFile
	{
		return $this->modifyFileWithTrait(
			$path,
			"$this->namespace\Presenter\\{$this->name}PresenterTemplate",
			fn() => $this->generateBasePresenterTemplate(),
		);
	}


	public function generateBaseTemplate(): PhpFile
	{
		$class = (new ClassType("BaseTemplate"))
			->setExtends(Template::class)
			->addAttribute(AllowDynamicProperties::class);

		$namespace = (new PhpNamespace("$this->buildNamespace\Control"))
			->add($class)
			->addUse(Template::class)
			->addUse(AllowDynamicProperties::class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateUpdatedBaseTemplate(string $path): PhpFile
	{
		return $this->modifyFileWithTrait(
			$path,
			"$this->namespace\Control\\{$this->name}Template",
			fn() => $this->generateBaseTemplate(),
		);
	}


	public function generateTemplateFactory(): PhpFile
	{
		$class = (new ClassType("BaseTemplateFactory"))
			->setExtends(TemplateFactory::class)
			->addImplement(Injectable::class);

		$namespace = (new PhpNamespace("$this->buildNamespace\Lib"))
			->add($class)
			->addUse(TemplateFactory::class)
			->addUse(Injectable::class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateUpdatedTemplateFactory(string $path): PhpFile
	{
		return $this->modifyFileWithTrait(
			$path,
			"$this->namespace\Lib\\{$this->name}TemplateFactory",
			fn() => $this->generateTemplateFactory(),
		);
	}


	public function generateModel(): PhpFile
	{
		$class = (new ClassType("Orm"))
			->setExtends(Model::class);

		$namespace = (new PhpNamespace("$this->buildNamespace\Model"))
			->add($class)
			->addUse(Model::class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateUpdatedModel(string $path): PhpFile
	{
		return $this->modifyFileWithTrait(
			$path,
			"$this->namespace\Model\\{$this->name}Orm",
			fn() => $this->generateModel(),
		);
	}


	public function generateDataModel(): PhpFile
	{
		$class = (new ClassType("DataModel"))
			->addImplement(Injectable::class);

		$namespace = (new PhpNamespace("$this->buildNamespace\Model"))
			->add($class)
			->addUse(Injectable::class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateUpdatedDataModel(string $path): PhpFile
	{
		return $this->modifyFileWithTrait(
			$path,
			"$this->namespace\Model\\{$this->name}DataModel",
			fn() => $this->generateDataModel(),
		);
	}


	private function modifyFileWithTrait(string $path, string $trait, ?callable $create = null): PhpFile
	{
		$content = @file_get_contents($path);
		if (!$content && !$create) {
			throw new InvalidArgumentException("File '$path' does not exist.");
		}
		$file = !$content ? $create() : PhpFile::fromCode($content);
		$class = Arrays::first($file->getClasses());
		$traits = $class->getTraits();
		$namespace = Arrays::first($file->getNamespaces());

		if ($this->mode === Generator::MODE_ADD && !array_key_exists($trait, $traits)) {
			$class->addTrait($trait);
			$namespace->addUse($trait);
		} elseif ($this->mode === Generator::MODE_REMOVE && array_key_exists($trait, $traits)) {
			$class->removeTrait($trait);
			$namespace->removeUse($trait);
		}

		return $file;
	}
}
