<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib;

use Nette\PhpGenerator\Attribute;
use Nette\PhpGenerator\ClassLike;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Closure;
use Nette\PhpGenerator\Constant;
use Nette\PhpGenerator\EnumType;
use Nette\PhpGenerator\GlobalFunction;
use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Printer;
use Nette\PhpGenerator\PromotedParameter;
use Nette\PhpGenerator\Property;
use Nette\PhpGenerator\PropertyAccessMode;
use Nette\PhpGenerator\PropertyHook;
use Nette\PhpGenerator\TraitType;
use Nette\Utils\Strings;


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


	public function printClass(
		ClassType|InterfaceType|TraitType|EnumType $class,
		?PhpNamespace $namespace = null,
	): string
	{
		$this->namespace = $this->resolveTypes ? $namespace : null;
		$class->validate();
		$resolver = $this->namespace
			? [$namespace, 'simplifyType']
			: fn($s) => $s;

		$traits = [];
		if ($class instanceof ClassType || $class instanceof TraitType || $class instanceof EnumType) {
			foreach ($class->getTraits() as $trait) {
				$resolutions = implode(";\n", $trait->getResolutions());
				$resolutions = Helpers::simplifyTaggedNames($resolutions, $this->namespace);
				$traits[] = $this->printDocComment($trait)
					. 'use ' . $resolver($trait->getName())
					. ($resolutions
						? " {\n" . $this->indent($resolutions) . ";\n}\n"
						: ";\n");
			}
		}

		$cases = [];
		$enumType = null;
		if ($class instanceof EnumType) {
			$enumType = $class->getType();
			foreach ($class->getCases() as $case) {
				$enumType ??= is_scalar($case->getValue()) ? get_debug_type($case->getValue()) : null;
				$cases[] = $this->printDocComment($case)
					. $this->printAttributes($case->getAttributes())
					. 'case ' . $case->getName()
					. ($case->getValue() === null ? '' : ' = ' . $this->dump($case->getValue()))
					. ";\n";
			}
		}

		$readOnlyClass = $class instanceof ClassType && $class->isReadOnly();
		$consts = [];
		$methods = [];
		if (
			$class instanceof ClassType
			|| $class instanceof InterfaceType
			|| $class instanceof TraitType
			|| $class instanceof EnumType
		) {
			foreach ($class->getConstants() as $const) {
				$consts[] = $this->printConstant($const);
			}

			foreach ($class->getMethods() as $method) {
				if ($readOnlyClass && $method->getName() === Method::Constructor) {
					$method = clone $method;
					array_map(fn($param) => $param instanceof PromotedParameter ? $param->setReadOnly(false) : null, $method->getParameters());
				}
				$methods[] = $this->printMethod($method, $namespace, $class->isInterface());
			}
		}

		$properties = [];
		if ($class instanceof ClassType || $class instanceof TraitType || $class instanceof InterfaceType) {
			foreach ($class->getProperties() as $property) {
				$properties[] = $this->printProperty($property, $readOnlyClass, $class instanceof InterfaceType);
			}
		}

		$members = array_filter([
			implode('', $traits),
			$this->joinProperties($consts),
			$this->joinProperties($cases),
			$this->joinProperties($properties),
			($methods && $properties ? str_repeat("\n", $this->linesBetweenMethods - 1) : '')
			. implode(str_repeat("\n", $this->linesBetweenMethods), $methods),
		]);

		if ($class instanceof ClassType) {
			$line[] = $class->isAbstract() ? 'abstract' : null;
			$line[] = $class->isFinal() ? 'final' : null;
			$line[] = $class->isReadOnly() ? 'readonly' : null;
		}

		$line[] = match (true) {
			$class instanceof ClassType => $class->getName() ? 'class ' . $class->getName() : null,
			$class instanceof InterfaceType => 'interface ' . $class->getName(),
			$class instanceof TraitType => 'trait ' . $class->getName(),
			$class instanceof EnumType => 'enum ' . $class->getName() . ($enumType ? $this->returnTypeColon . $enumType : ''),
		};
		$line[] = ($class instanceof ClassType || $class instanceof InterfaceType) && $class->getExtends()
			? 'extends ' . implode(', ', array_map($resolver, (array) $class->getExtends()))
			: null;
		$line[] = ($class instanceof ClassType || $class instanceof EnumType) && $class->getImplements()
			? 'implements ' . implode(', ', array_map($resolver, $class->getImplements()))
			: null;
		$line[] = $class->getName() ? null : '{';

		return $this->printDocComment($class)
			. $this->printAttributes($class->getAttributes())
			. implode(' ', array_filter($line))
			. ($class->getName() ? "\n{\n" : "\n")
			. ($members ? $this->indent(implode("\n", $members)) : '')
			. '}'
			. ($class->getName() ? "\n" : '');
	}


	private function printConstant(Constant $const): string
	{
		$def = ($const->isFinal() ? 'final ' : '')
			. ($const->getVisibility() ? $const->getVisibility() . ' ' : '')
			. 'const '
			. ltrim($this->printType($const->getType(), nullable: false) . ' ')
			. $const->getName() . ' = ';

		return $this->printDocComment($const)
			. $this->printAttributes($const->getAttributes())
			. $def
			. $this->dump($const->getValue(), strlen($def)) . ";\n";
	}


	private function printProperty(Property $property, bool $readOnlyClass = false, bool $isInterface = false): string
	{
		$property->validate();
		$type = $property->getType();
		$def = ($property->isAbstract() && !$isInterface ? 'abstract ' : '')
			. ($property->isFinal() ? 'final ' : '')
			. $this->printPropertyVisibility($property)
			. ($property->isStatic() ? ' static' : '')
			. (!$readOnlyClass && $property->isReadOnly() && $type ? ' readonly' : '')
			. ' '
			. ltrim($this->printType($type, $property->isNullable()) . ' ')
			. '$' . $property->getName();

		$defaultValue = $property->getValue() === null && !$property->isInitialized()
			? ''
			: ' = ' . $this->dump($property->getValue(), strlen($def) + 3); // 3 = ' = '

		return $this->printDocComment($property)
			. $this->printAttributes($property->getAttributes(), true)
			. $def
			. $defaultValue
			. ($this->printHooks($property, $isInterface) ?: ';')
			. "\n";
	}


	private function printPropertyVisibility(Property|PromotedParameter $param): string
	{
		$get = $param->getVisibility(PropertyAccessMode::Get);
		$set = $param->getVisibility(PropertyAccessMode::Set);
		return $set
			? ($get ? "$get $set(set)" : "$set(set)")
			: $get ?? 'public';
	}


	/** @param  Attribute[]  $attrs */
	protected function printAttributes(array $attrs, bool $inline = false): string
	{
		if (!$attrs) {
			return '';
		}

		$this->dumper->indentation = $this->indentation;
		$items = [];
		foreach ($attrs as $attr) {
			$args = $this->dumper->format('...?:', $attr->getArguments());
			$args = Helpers::simplifyTaggedNames($args, $this->namespace);
			$items[] = $this->printType($attr->getName(), nullable: false) . ($args === '' ? '' : "($args)");
			$inline = $inline && !str_contains($args, "\n");
		}

		return $inline
			? '#[' . implode(', ', $items) . '] '
			: '#[' . implode("]\n#[", $items) . "]\n";
	}


	private function printHooks(Property|PromotedParameter $property, bool $isInterface = false): string
	{
		$hooks = $property->getHooks();
		if (!$hooks) {
			return '';
		}

		$simple = true;
		foreach ($hooks as $type => $hook) {
			$simple = $simple && ($hook->isAbstract() || $isInterface);
			$hooks[$type] = $this->printDocComment($hook)
				. $this->printAttributes($hook->getAttributes())
				. ($hook->isAbstract() || $isInterface
					? ($hook->getReturnReference() ? '&' : '')
					. $type . ';'
					: ($hook->isFinal() ? 'final ' : '')
					. ($hook->getReturnReference() ? '&' : '')
					. $type
					. ($hook->getParameters() ? $this->printParameters($hook) : '')
					. ' '
					. ($hook->isShort()
						? '=> ' . $hook->getBody() . ';'
						: "{\n" . $this->indent($this->printFunctionBody($hook)) . '}'));
		}

		return $simple
			? ' { ' . implode(' ', $hooks) . ' }'
			: " {\n" . $this->indent(implode("\n", $hooks)) . "\n}";
	}


	protected function printDocComment(/*Traits\CommentAware*/ $commentable): string
	{
		$multiLine = $commentable instanceof GlobalFunction
			|| $commentable instanceof Method
			|| $commentable instanceof ClassLike
			|| $commentable instanceof PhpFile;
		return CustomHelpers::formatDocComment((string) $commentable->getComment(), $multiLine);
	}


	private function printFunctionBody(Closure|GlobalFunction|Method|PropertyHook $function): string
	{
		$code = Helpers::simplifyTaggedNames($function->getBody(), $this->namespace);
		$code = Strings::normalize($code);
		return ltrim(rtrim($code) . "\n");
	}


	/** @param  string[]  $props */
	private function joinProperties(array $props): string
	{
		return $this->linesBetweenProperties
			? implode(str_repeat("\n", $this->linesBetweenProperties), $props)
			: implode("", $props);
	}
}
