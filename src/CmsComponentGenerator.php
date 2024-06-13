<?php

declare(strict_types=1);

namespace Webovac\Generator;

use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\Utils\Arrays;
use Stepapo\Generator\ComponentGenerator;
use Webovac\Core\Attribute\RequiresEntity;


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
				$createComponentMethod->addAttribute(RequiresEntity::class, [$this->entity]);
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
}