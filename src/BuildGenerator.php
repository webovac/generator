<?php

declare(strict_types=1);

namespace Webovac\Generator;

use Nette\PhpGenerator\PhpFile;


class BuildGenerator
{
	private FileGenerator $fileGenerator;


	public function __construct(
		private string $namespace = 'Build',
	) {
		$this->fileGenerator = new FileGenerator;
	}


	public function createBasePresenter(): PhpFile
	{
		return $this->fileGenerator->create(__DIR__ . '/files/basePresenter.neon', [
			'namespace' => "$this->namespace\Presenter",
		]);
	}


	public function createBasePresenterTemplate(): PhpFile
	{
		return $this->fileGenerator->create(__DIR__ . '/files/basePresenterTemplate.neon', [
			'namespace' => "$this->namespace\Presenter",
			'extends' => "$this->namespace\Control\BaseTemplate",
		]);
	}


	public function createBaseTemplate(): PhpFile
	{
		return $this->fileGenerator->create(__DIR__ . '/files/baseTemplate.neon', [
			'namespace' => "$this->namespace\Control",
		]);
	}


	public function createTemplateFactory(): PhpFile
	{
		return $this->fileGenerator->create(__DIR__ . '/files/templateFactory.neon', [
			'namespace' => "$this->namespace\Lib",
		]);
	}


	public function createModel(): PhpFile
	{
		return $this->fileGenerator->create(__DIR__ . '/files/model.neon', [
			'namespace' => "$this->namespace\Model",
		]);
	}


	public function createDataModel(): PhpFile
	{
		return $this->fileGenerator->create(__DIR__ . '/files/dataModel.neon', [
			'namespace' => "$this->namespace\Model",
		]);
	}
}