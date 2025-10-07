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


class FileGenerator
{
	public function create(string $file, array $params = []): PhpFile
	{
		return $this->createFile(File::createFromNeon($file, $params));
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
			$class->addImplement($implement);
			$namespace->addUse($implement);
		}
		foreach ($file->attributes as $attribute) {
			$class->addAttribute($attribute->name, $attribute->args);
			$namespace->addUse($attribute->name);
		}
		foreach ($file->methods as $method) {
			$class->addMember($this->createMethod($method));
		}
		foreach ($file->properties as $property) {
			$class->addMember($this->createProperty($property));
		}
		foreach ($file->constants as $constant) {
			$class->addMember($this->createConstant($constant));
		}
		foreach ($file->uses as $use) {
			$namespace->addUse($use);
		}
		$phpFile = (new PhpFile())->setStrictTypes();
		$phpFile->addNamespace($namespace);
		return $phpFile;
	}
	
	
	public function createMethod(Method $method): PhpMethod
	{
		$phpMethod = (new PhpMethod($method->name))
			->setFinal($method->final)
			->setAbstract($method->abstract)
			->setVisibility($method->visibility)
			->setBody($method->body)
			->setReturnType($method->returnType)
			->setComment($method->comment);
		foreach ($method->parameters as $parameter) {
			$phpParameter = $phpMethod->addParameter($parameter->name)
				->setType($parameter->type)
				->setComment($parameter->comment)
				->setNullable($parameter->nullable);
			if ($parameter->hasDefaultValue) {
				$phpParameter->setDefaultValue($parameter->defaultValue);
			}
			foreach ($parameter->attributes as $attribute) {
				$phpParameter->addAttribute($attribute->name, $attribute->args);
			}
		}
		foreach ($method->promotedParameters as $promotedParameter) {
			$phpParameter = $phpMethod->addPromotedParameter($promotedParameter->name)
				->setType($promotedParameter->type)
				->setComment($promotedParameter->comment)
				->setNullable($promotedParameter->nullable)
				->setFinal($promotedParameter->final)
				->setVisibility($promotedParameter->visibility);
			if ($promotedParameter->hasDefaultValue) {
				$phpParameter->setDefaultValue($promotedParameter->defaultValue);
			}
			foreach ($promotedParameter->attributes as $attribute) {
				$phpParameter->addAttribute($attribute->name, $attribute->args);
			}
		}
		foreach ($method->attributes as $attribute) {
			$phpMethod->addAttribute($attribute->name, $attribute->args);
		}
		return $phpMethod;
	}
	
	
	public function createProperty(Property $property): PhpProperty
	{
		$phpProperty = (new PhpProperty($property->name))
			->setVisibility($property->visibility)
			->setFinal($property->final)
			->setAbstract($property->abstract)
			->setValue($property->defaultValue)
			->setType($property->type)
			->setComment($property->comment)
			->setNullable($property->nullable);
		foreach ($property->attributes as $attribute) {
			$phpProperty->addAttribute($attribute->name, $attribute->args);
		}
		return $phpProperty;
	}


	public function createConstant(Constant $constant): PhpConstant
	{
		$phpConstant = (new PhpConstant($constant->name))
			->setVisibility($constant->visibility)
			->setFinal($constant->final)
			->setValue($constant->defaultValue)
			->setType($constant->type)
			->setComment($constant->comment);
		foreach ($constant->attributes as $attribute) {
			$phpConstant->addAttribute($attribute->name, $attribute->args);
		}
		return $phpConstant;
	}
}