<?php

declare(strict_types=1);

namespace Webovac\Generator;

use Webovac\Generator\Config\Module;
use Webovac\Generator\Config\Service;


class ServiceGenerator
{
	private const string SERVICE = 'service';

	private string $namespace;
	private string $basePath;
	private FileGenerator $fileGenerator;


	public function __construct(
		private string $appNamespace,
		private string $appDir,
		private Service $service,
		private ?Module $module = null,
	) {
		$this->namespace = $this->appNamespace . ($this->module ? "\Module\\{$this->module->name}" : '') . "\Lib";
		$this->basePath = "$this->appDir/" . ($module ? "Module/{$module->name}/" : '') . "Lib";
		$this->fileGenerator = new FileGenerator;
	}


	public function createService(): void
	{
		$this->fileGenerator->write($this->getPath(self::SERVICE), $this->getConfig(self::SERVICE), [
			'name' => $this->service->name,
			'namespace' => $this->namespace,
		]);
	}


	private function getPath(string $key): string
	{
		return match($key) {
			self::SERVICE => "$this->basePath/{$this->service->name}.php",
		};
	}


	private function getConfig(string $key): string
	{
		return match($key) {
			self::SERVICE => __DIR__ . '/files/service.neon',
		};
	}
}
