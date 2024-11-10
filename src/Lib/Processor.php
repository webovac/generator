<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib;

use Stepapo\Utils\Printer;
use Stepapo\Generator\ComponentGenerator;
use Webovac\Core\Lib\Dataset\CmsDatasetFactory;
use Webovac\Generator\CmsGenerator;
use Webovac\Generator\Config\App;
use Webovac\Generator\Config\Component;
use Webovac\Generator\Config\Entity;
use Webovac\Generator\Config\Module;
use Webovac\Generator\Config\Service;


class Processor
{
	private int $count = 0;
	private Printer $printer;
	private Collector $collector;
	private Analyzer $analyzer;


	public function __construct(
		private CmsGenerator $generator,
	) {
		$this->printer = new Printer;
		$this->collector = new Collector;
		$this->analyzer = new Analyzer;
	}


	public function process(array $folders, string $appDir): int
	{
		$start = microtime(true);
		$this->printer->printBigSeparator();
		$this->printer->printLine('Files', 'aqua');
		$this->printer->printSeparator();
		try {
			$new = $this->collector->getApp($folders);
			$old = $this->analyzer->getApp($appDir);
			$this->processApp($new, $old);
			if ($this->count === 0) {
				$this->printer->printLine('No changes');
			}
			$this->printer->printSeparator();
			$end = microtime(true);
			$this->printer->printLine(sprintf("%d items | %0.3f s | OK", $this->count, $end - $start), 'lime');
		} catch (\Exception $e) {
			$this->printer->printError();
			$this->printer->printSeparator();
			$end = microtime(true);
			$this->printer->printLine(sprintf("%d items | %0.3f s | ERROR", $this->count, $end - $start), 'red');
			$this->printer->printLine($e->getMessage());
			$this->printer->printLine($e->getTraceAsString());
		}
		return $this->count;
	}


	private function processApp(App $new, App $old): void
	{
		$reset = false;
		foreach ($_SERVER['argv'] as $arg) {
			if ($arg === '--reset') {
				$reset = true;
				break;
			}
		}
		# CHECK FOR CREATION
		foreach ($new->modules as $module) {
			if ($reset || !isset($old->modules[$module->name])) {
				$this->createModule($module);
			}
			foreach ($module->entities as $entity) {
				if ($reset || !isset($old->modules[$module->name]->entities[$entity->name])) {
					$this->createEntity($entity, $module);
				}
			}
			foreach ($module->components as $component) {
				if ($reset || !isset($old->modules[$module->name]->components[$component->name])) {
					$this->createComponent($component, $module);
				}
			}
			foreach ($module->services as $service) {
				if ($reset || !isset($old->modules[$module->name]->services[$service->name])) {
					$this->createService($service, $module);
				}
			}
		}
		foreach ($new->components as $component) {
			if ($reset || !isset($old->components[$component->name])) {
				$this->createComponent($component);
			}
		}
		foreach ($new->services as $service) {
			if ($reset || !isset($old->services[$service->name])) {
				$this->createService($service);
			}
		}
		# CHECK FOR REMOVAL
		foreach ($old->modules as $module) {
			if (!isset($new->modules[$module->name])) {
				$this->removeModule($module);
				continue;
			}
			foreach ($module->components as $component) {
				if (!isset($new->modules[$module->name]->components[$component->name])) {
					$this->removeComponent($component, $module);
				}
			}
			foreach ($module->entities as $entity) {
				if (!isset($new->modules[$module->name]->entities[$entity->name])) {
					$this->removeEntity($entity, $module);
				}
			}
		}
		foreach ($old->components as $component) {
			if (!isset($new->components[$component->name])) {
				$this->removeComponent($component);
			}
		}
	}


	private function createModule(Module $module): void
	{
		$this->printer->printText($module->name, 'white');
		$this->printer->printText(': creating module');
		$this->generator->createModule(
			$module->name,
			(bool) $module->entities,
			$module->withDIExtension,
			$module->withMigrationGroup,
			$module->withInstallGroups,
			$module->withInstallFile,
			$module->type,
			$module->isPackage,
		);
		$this->count++;
		$this->printer->printOk();
	}


	private function createEntity(Entity $entity, ?Module $module = null): void
	{
		$this->printer->printText($module ? $module->name : 'ROOT', 'white');
		$this->printer->printText(': creating entity ');
		$this->printer->printText($entity->name, 'white');
		$this->generator->createCmsModel(
			$entity->name,
			$module?->name,
			$entity->withTraits,
			$entity->withConventions,
			$entity->entityImplements,
			$entity->repositoryImplements,
			isPackage: $module?->isPackage ?: false,
		);
		$this->count++;
		$this->printer->printOk();
	}


	private function createComponent(Component $component, ?Module $module = null): void
	{
		$this->printer->printText($module ? $module->name : 'ROOT', 'white');
		$this->printer->printText(': creating component ');
		$this->printer->printText($component->name, 'white');
		$this->generator->createCmsComponent(
			$component->name,
			$module?->name ?: 'App',
			$component->entityName,
			$component->withTemplateName,
			$component->type,
			factory: match ($component->type) {
				ComponentGenerator::TYPE_DATASET => CmsDatasetFactory::class,
				default => null,
			},
		);
		$this->count++;
		$this->printer->printOk();
	}


	private function createService(Service $service, ?Module $module = null): void
	{
		$this->printer->printText($module ? $module->name : 'ROOT', 'white');
		$this->printer->printText(': creating service ');
		$this->printer->printText($service->name, 'white');
		$this->generator->createService($service->name, $module?->name);
		$this->count++;
		$this->printer->printOk();
	}


	private function removeModule(Module $module)
	{
		$this->printer->printText($module->name, 'white');
		$this->printer->printText(': removing module ');
		$this->generator->removeModule($module->name, $module->isPackage);
		$this->count++;
		$this->printer->printOk();
	}


	private function removeComponent(Component $component, ?Module $module = null)
	{
		$this->printer->printText($module ? $module->name : 'ROOT', 'white');
		$this->printer->printText(': removing component ');
		$this->printer->printText($component->name, 'white');
		$this->generator->removeCmsComponent($component->name, $module?->name);
		$this->count++;
		$this->printer->printOk();
	}


	private function removeEntity(Entity $entity, ?Module $module = null)
	{
		$this->printer->printText($module ? $module->name : 'ROOT', 'white');
		$this->printer->printText(': removing entity ');
		$this->printer->printText($entity->name, 'white');
		$this->generator->removeCmsModel($entity->name, $module?->name, $module?->isPackage ?: false);
		$this->count++;
		$this->printer->printOk();
	}
}