<?php

declare(strict_types=1);

namespace Webovac\Generator;

use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Printer;


class CustomPrinter extends Printer
{
	private bool $resolveTypes = true;


	public function printNamespace(PhpNamespace $namespace): string
	{
		$this->namespace = $this->resolveTypes ? $namespace : null;
		$name = $namespace->getName();
		$uses = $this->printUses($namespace)
			. $this->printUses($namespace, PhpNamespace::NameFunction)
			. $this->printUses($namespace, PhpNamespace::NameConstant);

		$items = [];
		foreach ($namespace->getClasses() as $class) {
			$items[] = $this->printClass($class, $namespace);
		}
		foreach ($namespace->getFunctions() as $function) {
			$items[] = $this->printFunction($function, $namespace);
		}
		$body = ($uses ? $uses . "\n" : '')
			. "\n"
			. implode("\n", $items);
		if ($namespace->hasBracketedSyntax()) {
			return 'namespace' . ($name ? " $name" : '') . "\n{\n"
				. $this->indent($body)
				. "}\n";
		} else {
			return ($name ? "namespace $name;\n\n" : '')
				. $body;
		}
	}
}
