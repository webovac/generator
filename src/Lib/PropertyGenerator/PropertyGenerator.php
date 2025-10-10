<?php

namespace Webovac\Generator\Lib\PropertyGenerator;

use Nette\InvalidArgumentException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\Utils\Arrays;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Orm\Relationships\ManyHasMany;
use Nextras\Orm\Relationships\OneHasMany;
use Nextras\Orm\StorageReflection\StringHelper;
use Stepapo\Model\Definition\Config\Foreign;
use Stepapo\Model\Definition\Config\Table;
use Stepapo\Model\Orm\InternalProperty;
use Stepapo\Model\Orm\PrivateProperty;
use Webovac\Generator\Lib\BuildGenerator\BuildGenerator;
use Webovac\Generator\Lib\BuildModelGenerator\BuildModelGenerator;
use Webovac\Generator\Lib\SetupProvider\ISetupProvider;
use Webovac\Generator\Lib\SetupProvider\SetupProvider;
use Webovac\Generator\Lib\Writer;


class PropertyGenerator
{
	private string $namespace;
	private string $path;
	private SetupProvider $setupProvider;


	public function __construct(
		private Table $table,
		private string $name,
		private Writer $writer,
		ISetupProvider $setupProviderFactory,
	) {
		$this->setupProvider = $setupProviderFactory->create(
			name: $this->name
		);
		$this->namespace = $this->setupProvider->getNamespace(BuildGenerator::MODEL);
		$this->path = $this->setupProvider->getPath(BuildModelGenerator::ENTITY);
	}


	public function readEntityComments(): ?string
	{
		if (!($content = @file_get_contents($this->path))) {
			return null;
		}
		$file = PhpFile::fromCode($content);
		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		return $class->getComment();
	}


	public function createEntityProperties(): void
	{
		if (!($content = @file_get_contents($this->path))) {
			throw new InvalidArgumentException("File '$this->path' does not exist.");
		}
		$file = PhpFile::fromCode($content);
		/** @var PhpNamespace $namespace */
		$namespace = Arrays::first($file->getNamespaces());
		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		$comments = explode("\n", $class->getComment() ?: '');
		foreach ($comments as $key => $comment) {
			if (str_contains($comment, '@property')) {
				unset($comments[$key]);
			}
		}
		foreach ($this->table->columns as $column) {
			$foreign = $this->table->foreignKeys[$column->name] ?? null;
			$c = [];
			$c['property'] = '@property';
			$c['type'] = $column->getPhpType($foreign);
			if ($column->type === 'datetime') {
				$namespace->addUse(DateTimeImmutable::class);
			}
			$c['name'] = "\${$column->getPhpName($foreign)}";
			if (($default = $column->getPhpDefault()) !== null) {
				$c['default'] = "{default $default}";
			}
			$isPrimary = $this->table->primaryKey && in_array($column->name, $this->table->primaryKey->columns, true);
			if ($isPrimary) {
				$c['primary'] = "{primary}";
			}
			if ($foreign) {
				$c['foreign'] = "{m:1 {$foreign->getPhpTable()}" . ($foreign->reverseName ? "::\$$foreign->reverseName" : ", oneSided=true") . "}";
				if ($this->table->name !== $foreign->table) {
					$use = "$this->namespace\\{$foreign->getPhpTable()}\\{$foreign->getPhpTable()}";
					$namespace->addUse($use);
				}
			}
			if ($column->private) {
				$namespace->addUse(PrivateProperty::class);
			}
			if ($column->internal) {
				$namespace->addUse(InternalProperty::class);
			}
			$comment = implode(' ', $c);
			if (!in_array($c, $comments, true)) {
				$comments[] = $comment;
			}
		}
		$class->setComment(implode("\n", $comments));
		$this->writer->write($this->path, $file);
	}


	public function createEntityPropertyManyHasMany(Foreign $from, Foreign $to, bool $isMain = false): void
	{
		$file = PhpFile::fromCode(@file_get_contents($this->path));
		/** @var PhpNamespace $namespace */
		$namespace = Arrays::first($file->getNamespaces());
		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		$comments = explode("\n", $class->getComment() ?: '');
		$c = [];
		$c['property'] = '@property';
		$c['type'] = "ManyHasMany|{$to->getPhpTable()}[]";
		$c['name'] = "\$" . ($from->reverseName ? "$from->reverseName" : (StringHelper::camelize($to->table) . "s"));
		$c['foreign'] = "{m:m {$to->getPhpTable()}" . ($to->reverseName ? "::\$$to->reverseName" : "") . ($to->reverseOrder ? ", orderBy=$to->reverseOrder" : "") . ($isMain ? ", isMain=true" : "") . ($to->reverseName ? "" : ", oneSided=true") ."}";
		if ($from->table !== $to->table) {
			$use = "$this->namespace\\{$to->getPhpTable()}\\{$to->getPhpTable()}";
			$namespace->addUse($use);
		}
		$comment = implode(' ', $c);
		if (!in_array($c, $comments, true)) {
			$comments[] = $comment;
		}
		$class->setComment(implode("\n", $comments));
		$namespace->addUse(ManyHasMany::class);
		$this->writer->write($this->path, $file);
	}


	public function createEntityPropertyOneHasMany(Foreign $foreign): void
	{
		$file = PhpFile::fromCode(@file_get_contents($this->path));
		/** @var PhpNamespace $namespace */
		$namespace = Arrays::first($file->getNamespaces());
		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		$comments = explode("\n", $class->getComment() ?: '');
		$c = [];
		$c['property'] = '@property';
		$c['type'] = "OneHasMany|{$this->table->getPhpName()}[]";
		$c['name'] = "\$" . ($foreign->reverseName ? "$foreign->reverseName" : (StringHelper::camelize($this->table->name) . "s"));
		$c['foreign'] = "{1:m {$this->table->getPhpName()}::$" . StringHelper::camelize(str_replace('_id', '', $foreign->keyColumn)) . ($foreign->reverseOrder ? ", orderBy=$foreign->reverseOrder" : "") . "}";
		if ($this->table->name !== $foreign->table) {
			$use = "$this->namespace\\{$this->table->getPhpName()}\\{$this->table->getPhpName()}";
			$namespace->addUse($use);
		}
		$comment = implode(' ', $c);
		if (!in_array($c, $comments, true)) {
			$comments[] = $comment;
		}
		$class->setComment(implode("\n", $comments));
		$namespace->addUse(OneHasMany::class);
		$this->writer->write($this->path, $file);
	}


	public function sortEntityProperties(): void
	{
		$file = PhpFile::fromCode(@file_get_contents($this->path));
		/** @var ClassType $class */
		$class = Arrays::first($file->getClasses());
		$comments = explode("\n", $class->getComment() ?: '');
		$c = [];
		foreach ($comments as $comment) {
			if (str_contains($comment, '{primary}')) {
				$c['primary'][] = $comment;
			} elseif (str_contains($comment, 'DateTimeImmutable')) {
				$c['date'][] = $comment;
			} elseif (str_contains($comment, 'm:1')) {
				$c['m:1'][] = $comment;
			} elseif (str_contains($comment, '1:m')) {
				$c['1:m'][] = $comment;
			} elseif (str_contains($comment, 'm:m')) {
				$c['m:m'][] = $comment;
			} elseif (str_contains($comment, '@property')) {
				$c['simple'][] = $comment;
			} else {
				$c['other'][] = $comment;
			}
		}
		$comments = [];
		foreach (['primary', 'simple', 'date', 'm:1', '1:m', 'm:m', 'other'] as $type) {
			if (isset($c[$type])) {
				sort($c[$type]);
				$comments = $comments ? array_merge($comments, [''], $c[$type]) : $c[$type];
			}
		}
		$class->setComment(implode("\n", $comments));
		$this->writer->write($this->path, $file);
	}
}