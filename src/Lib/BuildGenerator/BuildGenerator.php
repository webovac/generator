<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\BuildGenerator;

use Webovac\Generator\Lib\BaseGenerator;
use Webovac\Generator\Lib\SetupProvider\ISetupProvider;
use Webovac\Generator\Lib\SetupProvider\SetupProvider;


class BuildGenerator extends BaseGenerator
{
	public const string BASE_PRESENTER = 'basePresenter';
	public const string BASE_PRESENTER_TEMPLATE = 'basePresenterTemplate';
	public const string BASE_TEMPLATE = 'baseTemplate';
	public const string TEMPLATE_FACTORY = 'templateFactory';
	public const string MODEL = 'model';
	public const string DATA_MODEL = 'dataModel';

	protected SetupProvider $setupProvider;


	public function __construct(
		ISetupProvider $setupProviderFactory,
	) {
		$this->setupProvider = $setupProviderFactory->create();
	}
	
	
	public function generate(): void
	{
		$this->createBasePresenter();
		$this->createBasePresenterTemplate();
		$this->createBaseTemplate();
		$this->createTemplateFactory();
		$this->createModel();
		$this->createDataModel();
	}


	public function remove(): void
	{
		$this->writer->remove($this->setupProvider->getBuildDir());
	}


	public function checkBuild(): void
	{
		$paths = [
			$this->setupProvider->getPath(self::BASE_PRESENTER),
			$this->setupProvider->getPath(self::BASE_PRESENTER_TEMPLATE),
			$this->setupProvider->getPath(self::BASE_TEMPLATE),
			$this->setupProvider->getPath(self::TEMPLATE_FACTORY),
			$this->setupProvider->getPath(self::MODEL),
			$this->setupProvider->getPath(self::DATA_MODEL),
		];
		foreach ($paths as $path) {
			$this->writer->sortTraits($path);
		}
	}


	private function createBasePresenter(): void
	{
		$this->write(self::BASE_PRESENTER);
	}


	private function createBasePresenterTemplate(): void
	{
		$this->write(self::BASE_PRESENTER_TEMPLATE, [
			'extends' => $this->setupProvider->getFqn(self::BASE_TEMPLATE),
		]);
	}


	private function createBaseTemplate(): void
	{
		$this->write(self::BASE_TEMPLATE);
	}


	private function createTemplateFactory(): void
	{
		$this->write(self::TEMPLATE_FACTORY);
	}


	private function createModel(): void
	{
		$this->write(self::MODEL);
	}


	private function createDataModel(): void
	{
		$this->write(self::DATA_MODEL);
	}
}