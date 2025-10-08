<?php

declare(strict_types=1);

namespace Webovac\Generator\DI;

use Nette\DI\Extensions\DecoratorExtension;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Stepapo\Model\Definition\PropertyProcessor;
use Stepapo\Utils\DI\StepapoExtension;
use Stepapo\Utils\Injectable;
use Webovac\Generator\Lib\SetupProvider\ISetupProvider;


class GeneratorExtension extends StepapoExtension
{
	private DecoratorExtension $decoratorExtension;


	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'appDir' => Expect::string('app'),
			'appNamespace' => Expect::bool('App'),
			'buildDir' => Expect::string('build'),
			'buildNamespace' => Expect::string('Build'),
			'driver' => Expect::string()->required(),
			'database' => Expect::string(),
		]);
	}


	public function loadConfiguration(): void
	{
		parent::loadConfiguration();
		$builder = $this->getContainerBuilder();
		$builder->addFactoryDefinition($this->prefix('setupProvider'))
			->setImplement(ISetupProvider::class)
			->getResultDefinition()
				->setArguments([
					'appDir' => $this->config->appDir,
					'appNamespace' => $this->config->appNamespace,
					'buildDir' => $this->config->buildDir,
					'buildNamespace' => $this->config->buildNamespace,
				]);
		$builder->addDefinition($this->prefix('propertyProcessor'))
			->setFactory(PropertyProcessor::class, [
				['defaultSchema' => $this->config->driver === 'mysql' ? $this->config->database : 'public'],
			]);
		$this->createDecoratorExtension();
	}


	protected function createDecoratorExtension(): void
	{
		$this->decoratorExtension = new DecoratorExtension;
		$this->decoratorExtension->setCompiler($this->compiler, $this->prefix('decorator'));
		$config = $this->processSchema($this->decoratorExtension->getConfigSchema(), $this->getDecoratorConfig());
		$this->decoratorExtension->setConfig($config);
		$this->decoratorExtension->loadConfiguration();
	}


	public function beforeCompile(): void
	{
		parent::beforeCompile();
		$this->decoratorExtension->beforeCompile();
	}


	public function afterCompile(ClassType $class): void
	{
		parent::afterCompile($class);
		$this->decoratorExtension->afterCompile($class);
	}


	private function getDecoratorConfig(): array
	{
		return [
			Injectable::class => ['inject' => true],
		];
	}
}