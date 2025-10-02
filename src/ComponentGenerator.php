<?php

declare(strict_types=1);

namespace Webovac\Generator;

use Nette\Application\UI\Form;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\Utils\ArrayHash;
use Nette\Utils\Arrays;
use Stepapo\Dataset\Control\Dataset\DatasetControl;
use Stepapo\Utils\Factory;
use Webovac\Core\Attribute\RequiresEntity;


class ComponentGenerator
{
	public const TYPE_FORM = 'form';
	public const TYPE_DATASET = 'dataset';
	public const TYPE_MENU = 'menu';

	private string $lname;
	private string $namespace;
	private string $entityName;
	private string $lentityName;


	public function __construct(
		private string $name,
		private string $appNamespace,
		private ?string $module = null,
		private ?string $entity = null,
		private bool $withTemplateName = false,
		private ?string $type = null,
		private ?string $factory = null,
		private string $mode = Generator::MODE_ADD,
	) {
		if ($this->entity) {
			$parts = explode('\\', $this->entity);
			$this->entityName = Arrays::last($parts);
			$this->lentityName = lcfirst($this->entityName);
		}
		$this->lname = lcfirst($name);
		$this->namespace = $this->appNamespace . ($this->module ? "\Module\\$this->module" : '') . "\Control\\$this->name";
	}


	public function generateTemplate(string $base): PhpFile
	{
		$class = (new ClassType("{$this->name}Template"))
			->setExtends($base);

		$namespace = (new PhpNamespace("{$this->namespace}"))
			->addUse($base)
			->add($class);

		if ($this->entity) {
			$class->addProperty($this->lentityName)
				->setPublic()
				->setType($this->entity);
			$namespace->addUse($this->entity);
		}

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateControl(string $base): PhpFile
	{
		$constructMethod = (new Method('__construct'))
			->setPublic();

		$renderMethod = (new Method('render'))
			->setPublic()
			->setReturnType('void');

		$class = (new ClassType("{$this->name}Control"))
			->setExtends($base)
			->addComment("@property {$this->name}Template \$template")
			->addMember($constructMethod)
			->addMember($renderMethod);

		$namespace = (new PhpNamespace($this->namespace))
			->addUse($base)
			->add($class);

		if ($this->entity) {
			$constructMethod
				->addPromotedParameter($this->lentityName)
				->setPrivate()
				->setType($this->entity);
			$renderMethod->addBody("\$this->template->$this->lentityName = \$this->$this->lentityName;");
			$namespace->addUse($this->entity);
		}

		if ($this->withTemplateName) {
			$class->addConstant('TEMPLATE_DEFAULT', 'default')
				->setType('string');
			$constructMethod
				->addPromotedParameter('moduleClass')
				->setType('string');
			$constructMethod
				->addPromotedParameter('templateName')
				->setType('string');
		}

		$renderMethod->addBody(
			$this->withTemplateName
				? "\$this->template->renderFile(\$this->moduleClass, self::class, \$this->templateName);"
				: "\$this->template->render(__DIR__ . '/{$this->lname}.latte');"
		);

		if ($this->type) {
			if ($this->factory) {
				$constructMethod
					->addPromotedParameter($this->type . 'Factory')
					->setPrivate()
					->setType($this->factory);
				$namespace->addUse($this->factory);
			}
			switch ($this->type) {
				case self::TYPE_FORM:
					$this->createFormMethods($namespace, $class);
					break;
				case self::TYPE_DATASET:
					$this->createDatasetMethods($namespace, $class);
					break;
			}
		}


		$file = (new PhpFile)->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateFactory(): PhpFile
	{
		$createMethod = (new Method('create'))
			->setReturnType("$this->namespace\\{$this->name}Control");

		$class = (new InterfaceType("I{$this->name}Control"))
			->setExtends(Factory::class)
			->addMember($createMethod);

		$namespace = (new PhpNamespace($this->namespace))
			->add($class)
			->addUse(Factory::class);

		if ($this->entity) {
			$createMethod
				->addParameter($this->lentityName)
				->setType($this->entity);
			$namespace->addUse($this->entity);
		}

		if ($this->withTemplateName) {
			$createMethod
				->addParameter('moduleClass', new Literal("{$this->module}::class"))
				->setType('string');
			$namespace->addUse("App\\Module\\$this->module\\$this->module");
			$createMethod
				->addParameter('templateName', new Literal("{$this->name}Control::TEMPLATE_DEFAULT"))
				->setType('string');
		}

		$file = (new PhpFile)->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateLatte(): string
	{
		$latte = <<<EOT
{templateType {$this->namespace}\\{$this->name}Template}


EOT;
		if ($this->type) {
			$latte .= <<<EOT
{control {$this->type}}

EOT;
		}

		return $latte;
	}


	public function generateDatasetNeon(): string
	{
		return <<<EOT
collection: %collection%
repository: %repository%
columns:

EOT;
	}


	public function generateMenuNeon(): string
	{
		return <<<EOT
buttons:

EOT;
	}


	public function generateUpdatedMainComponent(string $path): PhpFile
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
				->setBody($this->entity
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
			if ($this->entity) {
				$createComponentMethod->addAttribute(RequiresEntity::class, [new Literal("$this->entityName::class")]);
				$namespace->addUse(RequiresEntity::class);
				$namespace->addUse($this->entity);
			}
		} else {
			$constructMethod->removeParameter($this->lname);
			$class->removeMethod("createComponent$this->name");
			$namespace->removeUse($factory);
			$namespace->removeUse($control);
		}

		return $file;
	}


	private function createFormMethods(PhpNamespace $namespace, ClassType $class): void
	{
		$createComponentMethod = (new Method('createComponentForm'))
			->setPublic()
			->setReturnType(Form::class)
			->addBody(
				$this->factory
					? "\$form = \$this->{$this->type}Factory->create();"
					: "\$form = new Form;"
			)
			->addBody("\$form->onSuccess[] = [\$this, 'formSucceeded'];")
			->addBody("return \$form;");

		$formSucceededMethod = (new Method('formSucceeded'))
			->setPublic()
			->setReturnType('void');
		$formSucceededMethod->addParameter('form')->setType(Form::class);
		$formSucceededMethod->addParameter('values')->setType(ArrayHash::class);

		$class
			->addMember($createComponentMethod)
			->addMember($formSucceededMethod);
		$namespace
			->addUse(Form::class)
			->addUse(ArrayHash::class);
	}


	private function createDatasetMethods(PhpNamespace $namespace, ClassType $class): void
	{
		$factoryBody = <<<EOT
	__DIR__ . '/$this->lname.neon',
	[
		'collection' => '',
		'repository' => '',
	],
EOT;

		$createComponentMethod = (new Method('createComponentDataset'))
			->setPublic()
			->setReturnType(DatasetControl::class)
			->addBody(
				$this->factory
					? "return \$this->{$this->type}Factory->create(\n$factoryBody\n);"
					: "return Dataset::createFromNeon(\n$factoryBody\n);"
			);

		$class
			->addMember($createComponentMethod);
		$namespace->addUse(DatasetControl::class);
	}
}