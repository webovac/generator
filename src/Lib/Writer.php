<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib;

use Build\Model\Web\WebData;
use Nette\InvalidArgumentException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\EnumType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\TraitType;
use Nette\PhpGenerator\TraitUse;
use Nette\PhpGenerator\Visibility;
use Nette\Utils\Arrays;
use Nette\Utils\FileSystem;
use Stepapo\FileBuilder\FileBuilder;
use Stepapo\Utils\Service;
use Webovac\Core\Lib\CmsUser;
use Webovac\Generator\Config\Implement;
use Webovac\Generator\Config\Override;
use Webovac\Generator\Config\Requirement;


class Writer implements Service
{
	private FileBuilder $fileBuilder;


	public function __construct()
	{
		$this->fileBuilder = new FileBuilder;
	}


	public function createAndWrite(string $path, string $file, array $params = []): void
	{
		$file = $this->fileBuilder->create($file, $params);
		$this->write($path, $file);
	}


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
		if (!$class instanceof ClassType) {
			return;
		}
		$traits = $class->getTraits();
		usort($traits, function (TraitUse $a, TraitUse $b) {
			if (str_contains($a->getName(), 'Core') xor (str_contains($b->getName(), 'Core'))) {
				return str_contains($b->getName(), 'Core') <=> str_contains($a->getName(), 'Core');
			}
			if (str_contains($a->getName(), 'Style') xor (str_contains($b->getName(), 'Style'))) {
				return str_contains($b->getName(), 'Style') <=> str_contains($a->getName(), 'Style');
			}
			return strcmp($a->getName(), $b->getName());
		});
		$class->setTraits($traits);
		$this->write($path, $file);
	}


	/**
	 * @param Implement[] $implements
	 * @param Requirement[] $requirements
	 */
	public function updateFile(string $path, string $trait, array $implements = [], array $requirements = []): void
	{
		if (!($content = @file_get_contents($path))) {
			throw new InvalidArgumentException("File '$path' does not exist.");
		}
		$file = PhpFile::fromCode($content);
		$class = Arrays::first($file->getClasses());
		$namespace = Arrays::first($file->getNamespaces());
		if (!$class instanceof ClassType) {
			return;
		}
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
		if ($requirements) {
			$namespace->addUse(CmsUser::class);
			$namespace->addUse(WebData::class);
			if (!$class->hasMethod('checkRequirements')) {
				$method = (new Method('checkRequirements'))
					->setBody(<<<PHP
return match(\$tag) {
	null => true,
};
PHP)
					->setVisibility(Visibility::Public)
					->setReturnType('bool');
				$method->addParameter('user')->setType(CmsUser::class);
				$method->addParameter('webData')->setType(WebData::class);
				$method->addParameter('tag')->setType('string')->setNullable()->setDefaultValue(null);
				$class->addMember($method);
			}
			$method = $class->getMethod('checkRequirements');
			$body = $method->getBody();
			$add = '';
			foreach ($requirements as $requirement) {
				$add .= <<<PHP
	'$requirement->tag' => \$this->$requirement->method(\$user, \$webData),

PHP;
			}
			$add .= <<<PHP
};
PHP;
			$newBody = str_replace(<<<PHP
};
PHP, $add, $body);
			$method->setBody($newBody);
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
		if (!$class instanceof ClassType) {
			return;
		}
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


	/** @param Override[] $overrides */
	public function checkFileOverrides(string $path, string $traitName, array $overrides = []): void
	{
		if (!($content = @file_get_contents($path))) {
			throw new InvalidArgumentException("File '$path' does not exist.");
		}
		$file = PhpFile::fromCode($content);
		$class = Arrays::first($file->getClasses());
		if (!$class instanceof ClassType) {
			return;
		}
		$traits = $class->getTraits();
		foreach ($overrides as $override) {
			$trait = $traits[$override->trait];
			$rc = new \ReflectionClass($override->trait);
			$shortName = $rc->getShortName();
			$alias = lcfirst($rc->getShortName()) . ucfirst($override->method);
			$trait->addResolution("$traitName::$override->method insteadof $shortName");
		}
		$this->write($path, $file);
	}


	private function fixConstants(PhpFile $file): void
	{
		foreach ($file->getNamespaces() as $namespace) {
			foreach ($namespace->getClasses() as $class) {
				foreach ($class->getConstants() as $constant) {
					$value = $constant->getValue();
					if (!$value instanceof Literal) {
						continue;
					}
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
