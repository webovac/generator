<?php

declare(strict_types=1);

namespace Webovac\Generator;

use AllowDynamicProperties;
use Nette\Application\UI\Presenter;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Bridges\ApplicationLatte\TemplateFactory;
use Nette\PhpGenerator\PhpFile;
use Nextras\Orm\Model\Model;
use Stepapo\Utils\Injectable;
use Webovac\Generator\Config\File;


class BuildGenerator
{
	public function __construct(
		private string $namespace = 'Build',
	) {}


	public function createBasePresenter(): PhpFile
	{
		return File::createPhp(
			name: 'BasePresenter',
			namespace: "$this->namespace\Presenter",
			extends: Presenter::class,
		);
	}


	public function createBasePresenterTemplate(): PhpFile
	{
		return File::createPhp(
			name: 'BasePresenterTemplate',
			namespace: "$this->namespace\Presenter",
			extends: "$this->namespace\Control\BaseTemplate",
		);
	}


	public function createBaseTemplate(): PhpFile
	{
		return File::createPhp(
			name: 'BaseTemplate',
			namespace: "$this->namespace\Control",
			extends: Template::class,
			attributes: [AllowDynamicProperties::class],
		);
	}


	public function createTemplateFactory(): PhpFile
	{
		return File::createPhp(
			name: 'BaseTemplateFactory',
			namespace: "$this->namespace\Lib",
			extends: TemplateFactory::class,
			implements: [Injectable::class],
		);
	}


	public function createModel(): PhpFile
	{
		return File::createPhp(
			name: 'Orm',
			namespace: "$this->namespace\Model",
			extends: Model::class,
		);
	}


	public function createDataModel(): PhpFile
	{
		return File::createPhp(
			name: 'DataModel',
			namespace: "$this->namespace\Model",
			implements: [Injectable::class],
		);
	}
}