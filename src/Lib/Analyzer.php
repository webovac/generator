<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib;

use Nette\PhpGenerator\PhpFile;
use Nette\Utils\Arrays;
use Nette\Utils\Finder;
use Webovac\Generator\Config\App;
use Webovac\Generator\Config\Command;
use Webovac\Generator\Config\Component;
use Webovac\Generator\Config\Entity;
use Webovac\Generator\Config\Module;
use Webovac\Generator\Config\Service;


class Analyzer implements \Stepapo\Utils\Service
{
	public function __construct(
		private string $appDir,
		private string $buildDir,
	) {}


	public function getApp(): App
	{
		$app = new App;
		if (file_exists($dir = $this->appDir . '/Module')) {
			foreach (Finder::findDirectories()->from($dir)->limitDepth(0) as $moduleDir) {
				$module = new Module;
				$module->name = $moduleDir->getFilename();
				if (file_exists($dir = $moduleDir . '/Control')) {
					foreach (Finder::findDirectories()->from($dir)->limitDepth(0) as $componentDir) {
						if ($componentDir->getFilename() === $module->name) {
							continue;
						}
						$component = new Component;
						$component->name = $componentDir->getFilename();
						$module->components[$component->name] = $component;
					}
				}
				if (file_exists($dir = $moduleDir . '/Model')) {
					foreach (Finder::findDirectories()->from($dir)->limitDepth(0) as $entityDir) {
						$entity = new Entity;
						$entity->name = $entityDir->getFilename();
						$module->entities[$entity->name] = $entity;
					}
				}
				if (file_exists($dir = $moduleDir . '/Lib')) {
					foreach (Finder::findFiles('*.php')->from($dir) as $serviceFile) {
						$service = new Service;
						$service->name = $serviceFile->getBasename('.php');
						$module->services[$service->name] = $service;
					}
				}
				if (file_exists($dir = $moduleDir . '/Command')) {
					foreach (Finder::findFiles('*.php')->from($dir) as $commandFile) {
						$command = new Command;
						$command->name = $commandFile->getBasename('.php');
						$module->commands[$command->name] = $command;
					}
				}
				$app->modules[$module->name] = $module;
			}
		}
		if (file_exists($dir = $this->appDir . '/Control')) {
			foreach (Finder::findDirectories()->from($dir)->limitDepth(0) as $componentDir) {
				$component = new Component;
				$component->name = $componentDir->getFilename();
				$app->components[$component->name] = $component;
			}
		}
		if (file_exists($dir = $this->appDir . '/Lib')) {
			foreach (Finder::findFiles('*.php')->from($dir) as $serviceFile) {
				$service = new Service;
				$service->name = $serviceFile->getBasename('.php');
				$app->services[$service->name] = $service;
			}
		}
		if (file_exists($dir = $this->appDir . '/Command')) {
			foreach (Finder::findFiles('*.php')->from($dir) as $commandFile) {
				$command = new Command;
				$command->name = $commandFile->getBasename('.php');
				$app->commands[$command->name] = $command;
			}
		}
		if (file_exists($path = $this->buildDir . '/Presenter/BasePresenter.php')) {
			$file = PhpFile::fromCode(file_get_contents($path));
			$class = Arrays::first($file->getClasses());
			foreach ($class->getTraits() as $trait) {
				preg_match('/^(.+)\\\(.+)\\\Presenter\\\(.+)Presenter$/', $trait->getName(), $m);
				$namespace = $m[1] ?? null;
				$moduleName = $m[2] ?? null;
				if ($moduleName && $namespace && !isset($app->modules[$moduleName])) {
					$module = new Module;
					$module->name = $moduleName;
					$module->namespace = $m[1];
					$module->isPackage = true;
					foreach (Finder::findFiles('*Mapper.php')->from($this->buildDir . '/Model') as $repositoryFile) {
						$f = PhpFile::fromCode(file_get_contents($repositoryFile->getPathname()));
						$c = Arrays::first($f->getClasses());
						foreach ($c->getTraits() as $t) {
							$n = Arrays::last(explode('\\', $t->getName()));
							preg_match("/^$module->name(.+)Mapper$/", $n, $m);
							if (isset($m[1])) {
								$entity = new Entity;
								$entity->name = $m[1];
								$module->entities[$entity->name] = $entity;
							}
						}
					}
					$app->modules[$module->name] = $module;
				}
			}
		}
		return $app;
	}
}