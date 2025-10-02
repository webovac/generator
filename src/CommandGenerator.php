<?php

declare(strict_types=1);

namespace Webovac\Generator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Stepapo\Utils\Command\Command;


class CommandGenerator
{
	private string $namespace;


	public function __construct(
		private string $name,
		private string $appNamespace,
		private ?string $module = null,
	) {
		$this->namespace = $this->appNamespace . ($this->module ? "\Module\\{$this->module}" : '') . "\Command";
	}


	public function generateCommand(): PhpFile
	{
		$class = (new ClassType("{$this->name}"))
			->addImplement(Command::class)
			->addMember((new Method('__construct'))->setPublic())
			->addMember((new Method('run'))->setPublic()->setReturnType('int')->setBody('return 0;'));

		$namespace = (new PhpNamespace("{$this->namespace}"))
			->add($class)
			->addUse(Command::class);

		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);

		return $file;
	}
}
