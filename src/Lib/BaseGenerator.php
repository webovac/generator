<?php

namespace Webovac\Generator\Lib;

use Nette\DI\Attributes\Inject;
use Stepapo\Utils\Injectable;
use Webovac\Generator\Lib\SetupProvider\SetupProvider;


abstract class BaseGenerator implements Injectable
{
	#[Inject] public FileCreator $fileGenerator;
	#[Inject] public Writer $writer;
	protected SetupProvider $setupProvider;


	abstract public function generate(): void;
	abstract public function remove(): void;


	protected function write(string $key, array $params = []) : void
	{
		$this->fileGenerator->write(
			path: $this->setupProvider->getPath($key),
			file: $this->setupProvider->getConfig($key),
			params: [
				'name' => $this->setupProvider->getName($key),
				'namespace' => $this->setupProvider->getNamespace($key),
			] + $params,
		);
	}


	protected function updateFile(string $key, string $trait, array $implements = []): void
	{
		$this->writer->updateFile(
			path: $this->setupProvider->getPath($key),
			trait: $this->setupProvider->getFqn($trait),
			implements: $implements,
		);
	}
}