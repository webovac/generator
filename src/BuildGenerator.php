<?php

declare(strict_types=1);

namespace Webovac\Generator;


class BuildGenerator
{
	private const string BASE_PRESENTER = 'basePresenter';
	private const string BASE_PRESENTER_TEMPLATE = 'basePresenterTemplate';
	private const string BASE_TEMPLATE = 'baseTemplate';
	private const string TEMPLATE_FACTORY = 'templateFactory';
	private const string MODEL = 'model';
	private const string DATA_MODEL = 'dataModel';

	private FileGenerator $fileGenerator;


	public function __construct(
		private string $namespace = 'Build',
		private string $dir = 'build',
	) {
		$this->fileGenerator = new FileGenerator;
	}


	public function createBasePresenter(): void
	{
		$this->fileGenerator->write($this->getPath(self::BASE_PRESENTER), $this->getConfig(self::BASE_PRESENTER), [
			'namespace' => "$this->namespace\Presenter",
		]);
	}


	public function createBasePresenterTemplate(): void
	{
		$this->fileGenerator->write($this->getPath(self::BASE_PRESENTER_TEMPLATE), $this->getConfig(self::BASE_PRESENTER_TEMPLATE), [
			'namespace' => "$this->namespace\Presenter",
			'extends' => "$this->namespace\Control\BaseTemplate",
		]);
	}


	public function createBaseTemplate(): void
	{
		$this->fileGenerator->write($this->getPath(self::BASE_TEMPLATE), $this->getConfig(self::BASE_TEMPLATE), [
			'namespace' => "$this->namespace\Control",
		]);
	}


	public function createTemplateFactory(): void
	{
		$this->fileGenerator->write($this->getPath(self::TEMPLATE_FACTORY), $this->getConfig(self::TEMPLATE_FACTORY), [
			'namespace' => "$this->namespace\Lib",
		]);
	}


	public function createModel(): void
	{
		$this->fileGenerator->write($this->getPath(self::MODEL), $this->getConfig(self::MODEL), [
			'namespace' => "$this->namespace\Model",
		]);
	}


	public function createDataModel(): void
	{
		$this->fileGenerator->write($this->getPath(self::DATA_MODEL), $this->getConfig(self::DATA_MODEL), [
			'namespace' => "$this->namespace\Model",
		]);
	}


	private function getPath(string $key): string
	{
		return match($key) {
			self::BASE_PRESENTER => "$this->dir/Presenter/BasePresenter.php",
			self::BASE_PRESENTER_TEMPLATE => "$this->dir/Presenter/BasePresenterTemplate.php",
			self::BASE_TEMPLATE => "$this->dir/Control/BaseTemplate.php",
			self::TEMPLATE_FACTORY => "$this->dir/Lib/BaseTemplateFactory.php",
			self::MODEL => "$this->dir/Model/Orm.php",
			self::DATA_MODEL => "$this->dir/Model/DataModel.php",
		};
	}


	private function getConfig(string $key): string
	{
		return match($key) {
			self::BASE_PRESENTER => __DIR__ . '/files/build/basePresenter.neon',
			self::BASE_PRESENTER_TEMPLATE => __DIR__ . '/files/build/basePresenterTemplate.neon',
			self::BASE_TEMPLATE => __DIR__ . '/files/build/baseTemplate.neon',
			self::TEMPLATE_FACTORY => __DIR__ . '/files/build/templateFactory.neon',
			self::MODEL => __DIR__ . '/files/build/model.neon',
			self::DATA_MODEL => __DIR__ . '/files/build/dataModel.neon',
		};
	}
}