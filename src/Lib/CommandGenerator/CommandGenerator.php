<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\CommandGenerator;

use Webovac\Generator\Config\Command;
use Webovac\Generator\Config\Module;
use Webovac\Generator\Lib\BaseGenerator;
use Webovac\Generator\Lib\SetupProvider\ISetupProvider;


class CommandGenerator extends BaseGenerator
{
	public const string CONFIG_DIR = 'command';

	public const string COMMAND = 'command';


	public function __construct(
		private Command $command,
		private ?Module $module,
		ISetupProvider $setupProviderFactory,
	) {
		$this->setupProvider = $setupProviderFactory->create(
			name: $this->command->name,
			module: $this->module,
		);
	}


	public function generate(): void
	{
		$this->createCommand();
	}


	public function remove(): void
	{
		$this->writer->remove($this->setupProvider->getBasePath(self::COMMAND));
	}


	private function createCommand(): void
	{
		$this->write(self::COMMAND);
	}
}
