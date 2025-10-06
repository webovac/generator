<?php

namespace Webovac\Generator\Config;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\TraitType;
use Stepapo\Utils\Config;


class File extends Config
{
	public function __construct(
		private string $name,
		private string $namespace,
		private ?string $extends = null,
		private array $implements = [],
		private array $attributes = [],
		private string $type = ClassType::class,
	) {}


	public static function createPhp(
		string $name,
		string $namespace,
		?string $extends = null,
		array $implements = [],
		array $attributes = [],
		string $type = ClassType::class,
	) {
		return (new self($name, $namespace, $extends, $implements, $attributes, $type))->getPhpFile();
	}


	public function getPhpFile(): PhpFile
	{
		/** @var ClassType|TraitType $class */
		$class = new ($this->type)($this->name);
		$namespace = new PhpNamespace($this->namespace);
		$namespace->add($class);
		if ($this->extends) {
			$class->setExtends($this->extends);
			$namespace->addUse($this->extends);
		}
		foreach ($this->implements as $implement) {
			$class->addImplement($implement);
			$namespace->addUse($implement);
		}
		foreach ($this->attributes as $attribute) {
			$class->addAttribute($attribute);
			$namespace->addUse($attribute);
		}
		$file = (new PhpFile())->setStrictTypes();
		$file->addNamespace($namespace);
		return $file;
	}
}