<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib;

use Nette\InvalidArgumentException;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\TraitUse;
use Nette\Utils\Arrays;
use Nette\Utils\FileSystem;
use Webovac\Generator\Config\Implement;
use Webovac\Generator\CustomPrinter;


class Writer
{
	public function write(string $path, PhpFile|string|null $file = null): void
	{
		if ($file instanceof PhpFile) {
			$this->fixConstants($file);
		}
		FileSystem::write($path, $file instanceof PhpFile ? (new CustomPrinter())->printFile($file) : (string) $file, mode: null);
	}


	public function remove(string $path): void
	{
		FileSystem::delete($path);
	}


	public function sortTraits(string $path): void
	{
		if (!($content = @file_get_contents($path))) {
			throw new InvalidArgumentException("File '$path' does not exist.");
		}
		$file = PhpFile::fromCode($content);
		$class = Arrays::first($file->getClasses());
		$traits = $class->getTraits();
		uasort($traits, function (TraitUse $a, TraitUse $b) {
			if (str_contains($a->getName(), 'Core') xor (str_contains($b->getName(), 'Core'))) {
				return str_contains($a->getName(), 'Core') <=> str_contains($b->getName(), 'Core');
			}
			return strcmp($a->getName(), $b->getName());
		});
		$class->setTraits($traits);
		$this->write($path, $file);
	}


	/** @param Implement[] $implements */
	public function updateFile(string $path, string $trait, array $implements = []): void
	{
		if (!($content = @file_get_contents($path))) {
			throw new InvalidArgumentException("File '$path' does not exist.");
		}
		$file = PhpFile::fromCode($content);
		$class = Arrays::first($file->getClasses());
		$namespace = Arrays::first($file->getNamespaces());
		$class->addTrait($trait);
		$namespace->addUse($trait);
		$alreadyImplements = $class->getImplements();
		foreach ($implements as $implement) {
			if (in_array($implement->class, $alreadyImplements, true)) {
				continue;
			}
			$class->addImplement($implement->class);
			$namespace->addUse($implement->class);
		}
		$this->write($path, $file);
	}


	/** @param Implement[] $implements */
	public function checkFileImplements(string $path, array $implements = []): void
	{
		if (!($content = @file_get_contents($path))) {
			throw new InvalidArgumentException("File '$path' does not exist.");
		}
		$file = PhpFile::fromCode($content);
		$class = Arrays::first($file->getClasses());
		$namespace = Arrays::first($file->getNamespaces());
		$alreadyImplements = $class->getImplements();
		foreach ($implements as $implement) {
			foreach ($implement->requires as $require) {
				if (!in_array($require, $alreadyImplements, true)) {
					$class->removeImplement($implement->class);
					$namespace->removeUse($implement->class);
				}
			}
		}
		$sortedImplements = $class->getImplements();
		sort($sortedImplements);
		$class->setImplements($sortedImplements);
		$this->write($path, $file);
	}


	private function fixConstants(PhpFile $file): void
	{
		foreach ($file->getNamespaces() as $namespace) {
			foreach ($namespace->getClasses() as $class) {
				foreach ($class->getConstants() as $constant) {
					$value = $constant->getValue();
					if ($value instanceof Literal) {
						$lines = explode("\n", (string) $value);
						$correctedLines = [];
						$c = count($lines);
						foreach ($lines as $k => $line) {
							$correctedLines[$k] = ($k === 0 || $k === $c - 1 ? "" : "\t") . trim($line);
						}
						$constant->setValue(new Literal(implode("\n", $correctedLines)));
					}
				}
			}
		}
	}
}