<?php

declare(strict_types=1);

namespace Webovac\Generator;

use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\Utils\Arrays;
use Webovac\Core\Attribute\RequiresEntity;
use Webovac\Generator\Config\Component;
use Webovac\Generator\Config\Module;
use Webovac\Generator\Lib\Writer;


class ComponentGenerator
{
	public const TYPE_FORM = 'form';
	public const TYPE_DATASET = 'dataset';
	public const TYPE_MENU = 'menu';

	private string $name;
	private string $lname;
	private string $namespace;
	private string $entityName;
	private string $lentityName;
	private FileGenerator $fileGenerator;
	private Writer $writer;


	public function __construct(
		private string $appNamespace,
		private Component $component,
		private ?Module $module = null,
		private string $mode = Generator::MODE_ADD,
	) {
		if ($this->component->entity) {
			$parts = explode('\\', $this->component->entity);
			$this->entityName = Arrays::last($parts);
			$this->lentityName = lcfirst($this->entityName);
		}
		$this->name = $this->component->name;
		$this->lname = lcfirst($this->name);
		$this->namespace = $this->appNamespace . ($this->module ? "\Module\\{$this->module->name}" : '') . "\Control\\$this->name";
		$this->fileGenerator = new FileGenerator;
		$this->writer = new Writer;
	}


	public function createTemplate(string $path): void
	{
		$this->fileGenerator->write($path, __DIR__ . '/files/component/template.neon', [
			'name' => "{$this->name}Template",
			'namespace' => $this->namespace,
			'lentity' => $this->component->entity ? $this->lentityName : 'lentity',
			'entity' => $this->component->entity ?: 'entity',
			'hideEntity' => !$this->component->entity,
		]);
	}


	public function createControl(string $path): void
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
		$createComponentFormMethodBody[] = <<<EOT
\$form->onSuccess[] = [\$this, 'formSucceeded'];
return \$form;
EOT;
		$dataset = <<<EOT
	__DIR__ . '/$this->lname.neon',
	[
		'collection' => '',
		'repository' => '',
	],
EOT;
		$this->fileGenerator->write($path, __DIR__ . '/files/component/control.neon', [
			'name' => "{$this->name}Control",
			'namespace' => $this->namespace,
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
				? "return \$this->{$this->component->type}Factory->create(\n$dataset\n);"
				: "return Dataset::createFromNeon(\n$dataset\n);",
			'hideForm' => $this->component->type !== self::TYPE_FORM,
			'hideDataset' => $this->component->type !== self::TYPE_DATASET,
		]);
	}


	public function createFactory(string $path): void
	{
		$this->fileGenerator->write($path, __DIR__ . '/files/component/factory.neon', [
			'name' => "I{$this->name}Control",
			'namespace' => $this->namespace,
			'moduleClass' => "App\\Module\\{$this->module->name}\\{$this->module->name}",
			'lentity' => $this->component->entity ? $this->lentityName : 'lentity',
			'entity' => $this->component->entity ?: 'entity',
			'hideEntity' => !$this->component->entity,
			'hideFactory' => !$this->component->factory,
			'hideTemplateName' => !$this->component->withTemplateName,
			'createMethod.returnType' => "$this->namespace\\{$this->name}Control",
			'moduleClass.value' => new Literal("{$this->module->name}::class"),
			'templateName.value' => new Literal("{$this->name}Control::TEMPLATE_DEFAULT"),
		]);
	}


	public function createLatte(string $path): void
	{
		$latte = <<<EOT
{templateType {$this->namespace}\\{$this->name}Template}


EOT;
		if ($this->component->type) {
			$latte .= <<<EOT
{control {$this->component->type}}

EOT;
		}
		$this->writer->write($path, $latte);
	}


	public function createDatasetNeon(string $path): void
	{
		$neon = <<<EOT
collection: %collection%
repository: %repository%
columns:

EOT;
		$this->writer->write($path, $neon);
	}


	public function createMenuNeon(string $path): void
	{
		$neon = <<<EOT
buttons:

EOT;
		$this->writer->write($path, $neon);
	}


	public function updateMainComponent(string $path): void
	{
		$file = PhpFile::fromCode(file_get_contents($path));
		$control = "$this->namespace\\{$this->name}Control";
		$factory = "$this->namespace\\I{$this->name}Control";

		$namespace = Arrays::first($file->getNamespaces());
		$class = Arrays::first($file->getClasses());

		$constructMethod = $class->getMethod('__construct');
		if ($this->mode === Generator::MODE_ADD) {
			$constructMethod->addPromotedParameter($this->lname)
				->setPrivate()
				->setType($factory);
			$createComponentMethod = (new Method("createComponent$this->name"));
			$createComponentMethod
				->setPublic()
				->setReturnType($control)
				->setBody($this->component->entity
					? <<<EOT
assert(\$this->entity instanceof $this->entityName);
return \$this->$this->lname->create(\$this->entity);
EOT
					: <<<EOT
return \$this->$this->lname->create();
EOT);
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