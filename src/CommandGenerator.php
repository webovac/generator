<?php

declare(strict_types=1);

namespace Webovac\Generator;

use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\Utils\Arrays;
use Webovac\Generator\Config\Command;
use Webovac\Generator\Config\File;
use Webovac\Generator\Config\Module;


class CommandGenerator
{
	private string $namespace;


	public function __construct(
		private string $appNamespace,
		private Command $command,
		private ?Module $module = null,
	) {
		$this->namespace = $this->appNamespace . ($this->module ? "\Module\\{$this->module->name}" : '') . "\Command";
	}


	public function createCommand(): PhpFile
	{
		$file = File::createPhp(
			name: $this->command->name,
			namespace: $this->namespace,
			implements: [Command::class],
		);
		$namespace = Arrays::first($file->getNamespaces());
		$class = Arrays::first($namespace->getClasses());
		$class
			->addMember((new Method('__construct'))->setPublic())
			->addMember((new Method('run'))->setPublic()->setReturnType('int')->setBody('return 0;'));
		return $file;
	}
}
