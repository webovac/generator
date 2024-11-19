<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib;

use Nette\InvalidArgumentException;
use Nette\Utils\FileInfo;
use Nette\Utils\Finder;
use Webovac\Generator\Config\App;


class Collector
{
	public function getApp(array $folders): App
	{
		$files = [];
		foreach ($folders as $folder) {
			$neonFiles = Finder::findFiles("*.neon")->from($folder);
			$files = array_merge($files, $neonFiles->collect());
		}
		$apps = [];
		/** @var FileInfo $file */
		foreach ($files as $file) {
			$apps[] = App::createFromNeon($file->getPathname());
		}
		return $this->mergeApps($apps);
	}


	/**
	 * @param App[] $apps
	 */
	private function mergeApps(array $apps): App
	{
		foreach ($apps as $app) {
			if (!isset($mergedApp)) {
				$mergedApp = $app;
				continue;
			}
			foreach ($app->modules as $module) {
				if (!isset($mergedApp->modules[$module->name])) {
					$mergedApp->modules[$module->name] = $module;
					continue;
				}
				foreach ($module->components as $component) {
					if (isset($mergedApp->modules[$module->name]->components[$component->name])) {
						throw new InvalidArgumentException("Duplicate definition of component '$component->name' in module '$module->name'.");
					}
					$mergedApp->modules[$module->name]->components[$component->name] = $component;
				}
				foreach ($module->entities as $entity) {
					if (isset($mergedApp->modules[$module->name]->entities[$entity->name])) {
						throw new InvalidArgumentException("Duplicate definition of entity '$entity->name' in module '$module->name'.");
					}
					$mergedApp->modules[$module->name]->entities[$entity->name] = $entity;
				}
				foreach ($module->services as $service) {
					if (isset($mergedApp->modules[$module->name]->services[$service->name])) {
						throw new InvalidArgumentException("Duplicate definition of service '$service->name' in module '$module->name'.");
					}
					$mergedApp->modules[$module->name]->services[$service->name] = $service;
				}
			}
			foreach ($app->components as $component) {
				if (isset($mergedApp->components[$component->name])) {
					throw new InvalidArgumentException("Duplicate definition of component '$component->name' in root.");
				}
				$mergedApp->components[$component->name] = $component;
			}
			foreach ($app->entities as $entity) {
				if (isset($mergedApp->entities[$entity->name])) {
					throw new InvalidArgumentException("Duplicate definition of entity '$entity->name' in root.");
				}
				$mergedApp->entities[$entity->name] = $entity;
			}
			foreach ($app->services as $service) {
				if (isset($mergedApp->services[$service->name])) {
					throw new InvalidArgumentException("Duplicate definition of service '$service->name' in root.");
				}
				$mergedApp->services[$service->name] = $service;
			}
		}
		return $mergedApp;
	}
}