<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib;

use AllowDynamicProperties;
use Nette\Application\UI\Presenter;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Bridges\ApplicationLatte\TemplateFactory;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nextras\Orm\Model\Model;
use Stepapo\Utils\Injectable;


class BuildGenerator
{
	public function __construct(
		private string $namespace = 'Build',
		private string $dir = 'build',
	) {}


	public function createBasePresenter(): PhpFile
	{
		$class = (new ClassType("BasePresenter"))
			->setExtends(Presenter::class);

		$namespace = (new PhpNamespace("$this->namespace\Presenter"))
			->add($class)
			->addUse(Presenter::class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function createBasePresenterTemplate(): PhpFile
	{
		$baseTemplate = "$this->namespace\Control\BaseTemplate";
		$class = (new ClassType("BasePresenterTemplate"))
			->setExtends($baseTemplate);

		$namespace = (new PhpNamespace("$this->namespace\Presenter"))
			->add($class)
			->addUse($baseTemplate);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function createBaseTemplate(): PhpFile
	{
		$class = (new ClassType("BaseTemplate"))
			->setExtends(Template::class)
			->addAttribute(AllowDynamicProperties::class);

		$namespace = (new PhpNamespace("$this->namespace\Control"))
			->add($class)
			->addUse(Template::class)
			->addUse(AllowDynamicProperties::class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function createTemplateFactory(): PhpFile
	{
		$class = (new ClassType("BaseTemplateFactory"))
			->setExtends(TemplateFactory::class)
			->addImplement(Injectable::class);

		$namespace = (new PhpNamespace("$this->namespace\Lib"))
			->add($class)
			->addUse(TemplateFactory::class)
			->addUse(Injectable::class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function createModel(): PhpFile
	{
		$class = (new ClassType("Orm"))
			->setExtends(Model::class);

		$namespace = (new PhpNamespace("$this->namespace\Model"))
			->add($class)
			->addUse(Model::class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}


	public function createDataModel(): PhpFile
	{
		$class = (new ClassType("DataModel"))
			->addImplement(Injectable::class);

		$namespace = (new PhpNamespace("$this->namespace\Model"))
			->add($class)
			->addUse(Injectable::class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}
}