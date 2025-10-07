<?php

declare(strict_types=1);

namespace Webovac\Generator;

use Webovac\Generator\Config\Command;
use Webovac\Generator\Config\Module;


class CommandGenerator
{
	private const string COMMAND = 'command';

	private string $namespace;
	private string $basePath;
	private FileGenerator $fileGenerator;


	public function __construct(
		private string $appNamespace,
		private string $appDir,
		private Command $command,
		private ?Module $module = null,
	) {
		$this->namespace = $this->appNamespace . ($this->module ? "\Module\\{$this->module->name}" : '') . "\Command";
		$this->basePath = "$this->appDir/" . ($module ? "Module/{$module->name}/" : '') . "Command";
		$this->fileGenerator = new FileGenerator;
	}


	public function createCommand(): void
	{
		$this->fileGenerator->write($this->getPath(self::COMMAND), $this->getConfig(self::COMMAND), [
			'name' => $this->command->name,
			'namespace' => $this->namespace,
		]);
	}


	private function getPath(string $key): string
	{
		return match($key) {
			self::COMMAND => "$this->basePath/{$this->command->name}.php",
		};
	}


	private function getConfig(string $key): string
	{
		return match($key) {
			self::COMMAND => __DIR__ . '/files/command.neon',
		};
	}
}
