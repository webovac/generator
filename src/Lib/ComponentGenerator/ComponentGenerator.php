<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\ComponentGenerator;

use JetBrains\PhpStorm\Language;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\Utils\Arrays;
use Webovac\Core\Attribute\RequiresEntity;
use Webovac\Generator\Config\Component;
use Webovac\Generator\Config\Module;
use Webovac\Generator\Lib\BaseGenerator;
use Webovac\Generator\Lib\Generator;
use Webovac\Generator\Lib\ModuleGenerator\ModuleGenerator;
use Webovac\Generator\Lib\SetupProvider\ISetupProvider;


class ComponentGenerator extends BaseGenerator
{
	public const string TEMPLATE = 'template';
	public const string CONTROL = 'control';
	public const string FACTORY = 'factory';
	public const string LATTE = 'latte';
	public const string DATASET_NEON = 'datasetNeon';
	public const string MENU_NEON = 'menuNeon';

	public const string TYPE_FORM = 'form';
	public const string TYPE_DATASET = 'dataset';
	public const string TYPE_MENU = 'menu';

	private string $name;
	private string $lname;
	private string $entityName;
	private string $lentityName;


	public function __construct(
		private Component $component,
		private ?Module $module,
		ISetupProvider $setupProviderFactory,
	) {
		if ($this->component->entity) {
			$parts = explode('\\', $this->component->entity);
			$this->entityName = Arrays::last($parts);
			$this->lentityName = lcfirst($this->entityName);
		}
		$this->name = $this->component->name;
		$this->lname = lcfirst($this->name);
		$this->setupProvider = $setupProviderFactory->create(
			name: $this->component->name,
			module: $this->module,
		);
	}
	
	
	public function generate(): void
	{
		$this->createTemplate();
		$this->createFactory();
		$this->createControl();
		$this->createLatte();
		if ($this->component->type === ComponentGenerator::TYPE_DATASET) {
			$this->createDatasetNeon();
		}
		if ($this->component->type === ComponentGenerator::TYPE_MENU) {
			$this->createMenuNeon();
		}
		if (!$this->module) {
			return;
		}
		$this->updateMainComponent();
	}


	public function remove(): void
	{
		$this->writer->remove($this->setupProvider->getBasePath(self::CONTROL));
		if (!$this->module) {
			return;
		}
		$this->updateMainComponent(Generator::MODE_REMOVE);
	}


	private function createTemplate(): void
	{
		$this->write(self::TEMPLATE, [
			'lentity' => $this->component->entity ? $this->lentityName : 'lentity',
			'entity' => $this->component->entity ?: 'entity',
			'hideEntity' => !$this->component->entity,
		]);
	}


	private function createControl(): void
	{
		$renderMethodBody = [];
		if ($this->component->entity) {
			$renderMethodBody[] = "\$this->template->$this->lentityName = \$this->$this->lentityName;";
		}
		$renderMethodBody[] = $this->component->withTemplateName
			? "\$this->template->renderFile(\$this->moduleClass, self::class, \$this->templateName);"
			: "\$this->template->render(__DIR__ . '/{$this->lname}.latte');";
		$createComponentFormMethodBody = [];
		$createComponentFormMethodBody[] = $this->component->factory
			? "\$form = \$this->{$this->component->type}Factory->create();"
			: "\$form = new Form;";
		$createComponentFormMethodBody[] = <<<PHP
\$form->onSuccess[] = [\$this, 'formSucceeded'];
return \$form;
PHP;
		$dataset = <<<PHP
	__DIR__ . '/$this->lname.neon',
	[
		'collection' => '',
		'repository' => '',
	],
PHP;
		$this->write(self::CONTROL, [
			'comments' => "@property {$this->name}Template \$template",
			'lentity' => $this->component->entity ? $this->lentityName : 'lentity',
			'entity' => $this->component->entity ?: 'entity',
			'hideEntity' => !$this->component->entity,
			'factoryName' => "{$this->component->type}Factory",
			'factory' => $this->component->factory ?: 'factory',
			'hideFactory' => !$this->component->factory,
			'renderMethod.body' => $renderMethodBody,
			'hideTemplateName' => !$this->component->withTemplateName,
			'createComponentFormMethod.body' => $createComponentFormMethodBody,
			'createComponentDatasetMethod.body' => $this->component->factory
				? /* language=PHP */ "return \$this->{$this->component->type}Factory->create(\n$dataset\n);"
				: /* language=PHP */ "return Dataset::createFromNeon(\n$dataset\n);",
			'hideForm' => $this->component->type !== self::TYPE_FORM,
			'hideDataset' => $this->component->type !== self::TYPE_DATASET,
		]);
	}


	private function createFactory(): void
	{
		$this->write(self::FACTORY, [
			'moduleClass' => $this->setupProvider->getFqn(ModuleGenerator::MODULE),
			'lentity' => $this->component->entity ? $this->lentityName : 'lentity',
			'entity' => $this->component->entity ?: 'entity',
			'hideEntity' => !$this->component->entity,
			'hideFactory' => !$this->component->factory,
			'hideTemplateName' => !$this->component->withTemplateName,
			'createMethod.returnType' => $this->setupProvider->getFqn(self::CONTROL),
			'moduleClass.value' => new Literal("{$this->setupProvider->getName(ModuleGenerator::MODULE)}::class"),
			'templateName.value' => new Literal("{$this->setupProvider->getName(self::CONTROL)}::TEMPLATE_DEFAULT"),
		]);
	}


	private function createLatte(): void
	{
		$path = $this->setupProvider->getPath(self::LATTE);
		$latte = <<<LATTE
{templateType {$this->setupProvider->getFqn(self::TEMPLATE)}}


LATTE;
		if ($this->component->type) {
			$latte .= <<<LATTE
{control {$this->component->type}}

LATTE;
		}
		$this->writer->write($path, $latte);
	}


	private function createDatasetNeon(): void
	{
		$path = $this->setupProvider->getPath(self::DATASET_NEON);
		$neon = <<<NEON
collection: %collection%
repository: %repository%
columns:

NEON;
		$this->writer->write($path, $neon);
	}


	private function createMenuNeon(): void
	{
		$path = $this->setupProvider->getPath(self::MENU_NEON);
		$neon = <<<NEON
buttons:

NEON;
		$this->writer->write($path, $neon);
	}


	private function updateMainComponent($mode = Generator::MODE_ADD): void
	{
		$path = $this->setupProvider->getPath(ModuleGenerator::MAIN_COMPONENT);
		$file = PhpFile::fromCode(file_get_contents($path));
		$control = $this->setupProvider->getFqn(self::CONTROL);
		$factory = $this->setupProvider->getFqn(self::FACTORY);

		$namespace = Arrays::first($file->getNamespaces());
		$class = Arrays::first($file->getClasses());

		$constructMethod = $class->getMethod('__construct');
		if ($mode === Generator::MODE_ADD) {
			$constructMethod->addPromotedParameter($this->lname)
				->setPrivate()
				->setType($factory);
			$createComponentMethod = (new Method("createComponent$this->name"));
			$createComponentMethod
				->setPublic()
				->setReturnType($control)
				->setBody($this->component->entity
					? <<<PHP
assert(\$this->entity instanceof $this->entityName);
return \$this->$this->lname->create(\$this->entity);
PHP
					: <<<PHP
return \$this->$this->lname->create();
PHP);
			$class->addMember($createComponentMethod, true);
			$namespace
				->addUse($factory)
				->addUse($control);
			if ($this->component->entity) {
				$createComponentMethod->addAttribute(RequiresEntity::class, [new Literal("$this->entityName::class")]);
				$namespace->addUse(RequiresEntity::class);
				$namespace->addUse($this->component->entity);
			}
		} else {
			$constructMethod->removeParameter($this->lname);
			$class->removeMethod("createComponent$this->name");
			$namespace->removeUse($factory);
			$namespace->removeUse($control);
		}
		$this->writer->write($path, $file);
	}
}