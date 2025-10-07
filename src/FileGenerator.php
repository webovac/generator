<?php

declare(strict_types=1);

namespace Webovac\Generator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\EnumType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\TraitType;
use Nette\PhpGenerator\Method as PhpMethod;
use Nette\PhpGenerator\Property as PhpProperty;
use Nette\PhpGenerator\Constant as PhpConstant;
use Webovac\Generator\File\Constant;
use Webovac\Generator\File\File;
use Webovac\Generator\File\Method;
use Webovac\Generator\File\Property;
use Webovac\Generator\Lib\Writer;


class FileGenerator
{
	private Writer $writer;


	public function __construct()
	{
		$this->writer = new Writer;
	}


	public function create(string $file, array $params = []): PhpFile
	{
		return $this->createFile(File::createFromNeon($file, $params));
	}


	public function write(string $path, string $file, array $params = []): void
	{
		$file = $this->create($file, $params);
		$this->writer->write($path, $file);
	}


	public function createFile(File $file): PhpFile
	{
		/** @var ClassType|TraitType|InterfaceType|EnumType $class */
		$class = new ($file->type)($file->name);
		$namespace = new PhpNamespace($file->namespace);
		$namespace->add($class);
		if ($file->extends) {
			$class->setExtends($file->extends);
			$namespace->addUse($file->extends);
		}
		foreach ($file->implements as $implement) {
			if ($implement->hide) {
				continue;
			}
			$class->addImplement($implement->name);
			$namespace->addUse($implement->name);
		}
		foreach ($file->attributes as $attribute) {
			if ($attribute->hide) {
				continue;
			}
			$class->addAttribute($attribute->name, $attribute->args);
			$namespace->addUse($attribute->name);
		}
		foreach ($file->comments as $comment) {
			$class->addComment($comment);
		}
		foreach ($file->constants as $constant) {
			if ($constant->hide) {
				continue;
			}
			$class->addMember($this->createConstant($constant));
		}
		foreach ($file->properties as $property) {
			if ($property->hide) {
				continue;
			}
			$class->addMember($this->createProperty($property));
		}
		foreach ($file->methods as $method) {
			if ($method->hide) {
				continue;
			}
			$class->addMember($this->createMethod($method));
		}
		foreach ($file->uses as $use) {
			if ($use->hide) {
				continue;
			}
			$namespace->addUse($use->name);
		}
		$phpFile = (new PhpFile())->setStrictTypes();
		$phpFile->addNamespace($namespace);
		return $phpFile;
	}
	
	
	public function createMethod(Method $method): PhpMethod
	{
		$phpMethod = (new PhpMethod($method->name))
			->setFinal($method->final)
			->setStatic($method->static)
			->setAbstract($method->abstract)
			->setVisibility($method->visibility)
			->setReturnType($method->returnType);
		foreach ($method->body as $body) {
			$phpMethod->addBody($body);
		}
		foreach ($method->comments as $comment) {
			$phpMethod->addComment($comment);
		}
		foreach ($method->parameters as $parameter) {
			if ($parameter->hide) {
				continue;
			}
			$phpParameter = $phpMethod->addParameter($parameter->name)
				->setType($parameter->type)
				->setNullable($parameter->nullable);
			foreach ($parameter->comments as $comment) {
				$phpParameter->addComment($comment);
			}
			if ($parameter->hasValue) {
				$phpParameter->setDefaultValue($parameter->value);
			}
			foreach ($parameter->attributes as $attribute) {
				$phpParameter->addAttribute($attribute->name, $attribute->args);
			}
		}
		foreach ($method->promotedParameters as $promotedParameter) {
			if ($promotedParameter->hide) {
				continue;
			}
			$phpPromotedParameter = $phpMethod->addPromotedParameter($promotedParameter->name)
				->setType($promotedParameter->type)
				->setNullable($promotedParameter->nullable)
				->setFinal($promotedParameter->final)
				->setVisibility($promotedParameter->visibility);
			foreach ($promotedParameter->comments as $comment) {
				$phpPromotedParameter->addComment($comment);
			}
			if ($promotedParameter->hasValue) {
				$phpPromotedParameter->setDefaultValue($promotedParameter->value);
			}
			foreach ($promotedParameter->attributes as $attribute) {
				if ($attribute->hide) {
					continue;
				}
				$phpPromotedParameter->addAttribute($attribute->name, $attribute->args);
			}
		}
		foreach ($method->attributes as $attribute) {
			if ($attribute->hide) {
				continue;
			}
			$phpMethod->addAttribute($attribute->name, $attribute->args);
		}
		return $phpMethod;
	}
	
	
	public function createProperty(Property $property): PhpProperty
	{
		$phpProperty = (new PhpProperty($property->name))
			->setVisibility($property->visibility)
			->setFinal($property->final)
			->setStatic($property->static)
			->setAbstract($property->abstract)
			->setType($property->type)
			->setNullable($property->nullable);
		if ($property->hasValue) {
			$phpProperty->setValue($property->value);
		}
		foreach ($property->comments as $comment) {
			$phpProperty->addComment($comment);
		}
		foreach ($property->attributes as $attribute) {
			if ($attribute->hide) {
				continue;
			}
			$phpProperty->addAttribute($attribute->name, $attribute->args);
		}
		return $phpProperty;
	}


	public function createConstant(Constant $constant): PhpConstant
	{
		$phpConstant = (new PhpConstant($constant->name))
			->setVisibility($constant->visibility)
			->setFinal($constant->final)
			->setValue($constant->value)
			->setType($constant->type);
		if ($constant->hasValue) {
			$phpConstant->setValue($constant->value);
		}
		foreach ($constant->comments as $comment) {
			$phpConstant->addComment($comment);
		}
		foreach ($constant->attributes as $attribute) {
			if ($attribute->hide) {
				continue;
			}
			$phpConstant->addAttribute($attribute->name, $attribute->args);
		}
		return $phpConstant;
	}
}