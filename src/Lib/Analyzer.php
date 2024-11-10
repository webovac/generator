<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib;

use Nette\PhpGenerator\PhpFile;
use Nette\Utils\Arrays;
use Nette\Utils\Finder;
use Tracy\Dumper;
use Webovac\Generator\Config\App;
use Webovac\Generator\Config\Component;
use Webovac\Generator\Config\Entity;
use Webovac\Generator\Config\Module;
use Webovac\Generator\Config\Service;


class Analyzer implements \Stepapo\Utils\Service
{
	public function getApp(string $appDir): App
	{
		$app = new App;
		if (file_exists($dir = $appDir . '/Module')) {
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
				$app->modules[$module->name] = $module;
			}
		}
		if (file_exists($dir = $appDir . '/Control')) {
			foreach (Finder::findDirectories()->from($dir)->limitDepth(0) as $componentDir) {
				$component = new Component;
				$component->name = $componentDir->getFilename();
				$app->components[$component->name] = $component;
			}
		}
		if (file_exists($dir = $appDir . '/Lib')) {
			foreach (Finder::findFiles('*.php')->from($dir) as $serviceFile) {
				$service = new Service;
				$service->name = $serviceFile->getBasename('.php');
				$app->services[$service->name] = $service;
			}
		}
		if (file_exists($path = $appDir . '/Presenter/BasePresenter.php')) {
			$file = PhpFile::fromCode(file_get_contents($path));
			$class = Arrays::first($file->getClasses());
			foreach ($class->getTraits() as $trait) {
				$moduleName = str_replace('Presenter', '', Arrays::last(explode('\\', $trait->getName())));
				if (!isset($app->modules[$moduleName])) {
					$module = new Module;
					$module->name = $moduleName;
					$module->isPackage = true;
					$app->modules[$module->name] = $module;
				}
			}
		}
		return $app;
	}
}