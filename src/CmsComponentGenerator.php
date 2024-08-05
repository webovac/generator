<?php

declare(strict_types=1);

namespace Webovac\Generator;

use Nette\Application\UI\Form;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\Utils\ArrayHash;
use Nette\Utils\Arrays;
use Stepapo\Dataset\Control\Dataset\DatasetControl;
use Stepapo\Generator\ComponentGenerator;
use Webovac\Core\Attribute\RequiresEntity;
use Webovac\Core\Factory;


class CmsComponentGenerator extends ComponentGenerator
{
	private string $lname;
	private string $namespace;
	private string $lentityName;
	private string $entity;


	public function __construct(
		private string $name,
		private string $appNamespace,
		private ?string $module = null,
		private ?string $entityName = null,
		private bool $withTemplateName = false,
		private ?string $type = null,
		private ?string $factory = null,
		private string $mode = CmsGenerator::MODE_ADD,
	) {
		parent::__construct($name, $appNamespace, $module, $entityName, $withTemplateName, $type, $factory);
		if ($this->entityName) {
			$this->lentityName = lcfirst($this->entityName);
			$this->entity = "$this->appNamespace\Model\\$this->entityName\\$this->entityName";
		}
		$this->lname = lcfirst($name);
		$this->namespace = $this->appNamespace . ($this->module ? "\Module\\$this->module" : '') . "\Control\\$this->name";
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

		if ($this->entityName) {
			$constructMethod
				->addPromotedParameter($this->lentityName)
				->setPrivate()
				->setType($this->entity);
			$renderMethod->addBody("\$this->template->$this->lentityName = \$this->$this->lentityName;");
			$namespace->addUse($this->entity);
		}

		if ($this->withTemplateName) {
			$constructMethod
				->addPromotedParameter('templateName')
				->setType('string');
		}

		$renderMethod->addBody("\$this->template->render(__DIR__ . '/" . ($this->withTemplateName ? "' . \$this->templateName . '" : $this->lname) . ".latte');");

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

		if ($this->entityName) {
			$createMethod
				->addParameter($this->lentityName)
				->setType($this->entity);
			$namespace->addUse($this->entity);
		}

		if ($this->withTemplateName) {
			$createMethod
				->addParameter('templateName')
				->setType('string');
		}

		$file = (new PhpFile)->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function generateUpdatedMainComponent(string $path): PhpFile
	{
		$file = PhpFile::fromCode(file_get_contents($path));
		$control = "$this->namespace\\{$this->name}Control";
		$factory = "$this->namespace\\I{$this->name}Control";

		$namespace = Arrays::first($file->getNamespaces());
		$class = Arrays::first($file->getClasses());

		$constructMethod = $class->getMethod('__construct');
		if ($this->mode === CmsGenerator::MODE_ADD) {
			$constructMethod->addPromotedParameter($this->lname)
				->setPrivate()
				->setType($factory);
			$createComponentMethod = (new Method("createComponent$this->name"));
			$createComponentMethod
				->setPublic()
				->setReturnType($control)
				->setBody($this->entityName
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
			if ($this->entityName) {
				$createComponentMethod->addAttribute(RequiresEntity::class, ["$this->entity::class"]);
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