<?php

declare(strict_types=1);

namespace Webovac\Generator;

use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\Utils\Arrays;
use Webovac\Generator\Config\File;
use Webovac\Generator\Config\Module;
use Webovac\Generator\Config\Service;


class ServiceGenerator
{
	private string $namespace;


	public function __construct(
		private string $appNamespace,
		private Service $service,
		private ?Module $module = null,
	) {
		$this->namespace = $this->appNamespace . ($this->module ? "\Module\\{$this->module->name}" : '') . "\Lib";
	}


	public function createService(): PhpFile
	{
		$file = File::createPhp(
			name: $this->service->name,
			namespace: $this->namespace,
			implements: [Service::class],
		);
		$namespace = Arrays::first($file->getNamespaces());
		$class = Arrays::first($namespace->getClasses());
		$class->addMember((new Method('__construct'))->setPublic());
		return $file;
	}
}
