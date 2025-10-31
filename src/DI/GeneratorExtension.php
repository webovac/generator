<?php

declare(strict_types=1);

namespace Webovac\Generator\DI;

use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Stepapo\Utils\DI\StepapoExtension;
use Webovac\Generator\Lib\Analyzer;
use Webovac\Generator\Lib\PropertyProcessor;
use Webovac\Generator\Lib\SetupProvider\ISetupProvider;
use Webovac\Generator\Lib\SetupProvider\SetupProvider;


class GeneratorExtension extends StepapoExtension
{
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
					'defaultSchema' => $this->config->driver === 'mysql' ? $this->config->database : 'public',
				]);
		$builder->addDefinition($this->prefix('propertyProcessor'))
			->setFactory(PropertyProcessor::class, [
				['defaultSchema' => $this->config->driver === 'mysql' ? $this->config->database : 'public'],
			]);
		$builder->addDefinition($this->prefix('analyzer'))
			->setFactory(Analyzer::class, [
				'appDir' => $this->config->appDir,
				'buildDir' => $this->config->buildDir,
			]);
	}
}