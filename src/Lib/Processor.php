<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib;

use Stepapo\Utils\Printer;
use Webovac\Generator\Config\App;
use Webovac\Generator\Config\Command;
use Webovac\Generator\Config\Component;
use Webovac\Generator\Config\Entity;
use Webovac\Generator\Config\Module;
use Webovac\Generator\Config\Service;


class Processor implements \Stepapo\Utils\Service
{
	private int $count = 0;
	private Printer $printer;


	public function __construct(
		private Generator $generator,
		private Collector $collector,
		private Analyzer $analyzer,
	) {
		$this->printer = new Printer;
	}


	public function process(array $folders): int
	{
		$start = microtime(true);
		$this->printer->printBigSeparator();
		$this->printer->printLine('Files', 'aqua');
		$this->printer->printSeparator();
		try {
			$app = $this->collector->getApp($folders);
			$old = $this->analyzer->getApp();
			$this->processApp($app, $old);
			if ($this->count === 0) {
				$this->printer->printLine('No changes');
			}
			$this->printer->printSeparator();
//			$end = microtime(true);
//			$this->printer->printLine(sprintf("%d items | %0.3f s | OK", $this->count, $end - $start), 'lime');
//		} catch (\Exception $e) {
//			$this->printer->printError();
//			$this->printer->printSeparator();
//			$end = microtime(true);
//			$this->printer->printLine(sprintf("%d items | %0.3f s | ERROR", $this->count, $end - $start), 'red');
//			$this->printer->printLine($e->getMessage());
//			$this->printer->printLine($e->getTraceAsString());
//		}
//		$this->printer->printBigSeparator();
//		$this->printer->printLine('Build', 'aqua');
//		$this->printer->printSeparator();
//		try {
			$this->printer->printText('Building... ');
			$this->build($app);
			$this->printer->printLine('OK', 'lime');
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


	private function build(App $app): void
	{
		$this->generator->removeBuild();
		$this->generator->createBuild();
		$entities = [];
		foreach ($app->modules as $module) {
			$this->generator->updateBuild($module);
			foreach ($module->entities as $entity) {
				if (!array_key_exists($entity->name, $entities)) {
					$this->generator->createBuildModel($entity, $module);
					$entities[$entity->name] = $entity->name;
				}
				$this->generator->updateBuildModel($entity, $module);
			}
		}
		$this->generator->checkBuild();
		# CHECK IMPLEMENTS
		$entities = [];
		foreach ($app->modules as $module) {
			foreach ($module->entities as $entity) {
				if (array_key_exists($entity->name, $entities)) {
					continue;
				}
				$this->generator->checkBuildModel($entity, $module);
				$entities[$entity->name] = $entity->name;
			}
		}
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
			if ($module->isPackage) {
				continue;
			}
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
			foreach ($module->commands as $command) {
				if ($reset || !isset($old->modules[$module->name]->commands[$command->name])) {
					$this->createCommand($command, $module);
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
		foreach ($new->commands as $command) {
			if ($reset || !isset($old->commands[$command->name])) {
				$this->createCommand($command);
			}
		}
		# CHECK FOR REMOVAL
		foreach ($old->modules as $module) {
			if ($module->isPackage) {
				continue;
			}
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
//			Zakomentováno kvůli module trait TemplateFactory a dalším servicám v app
//			foreach ($module->services as $service) {
//				if (!isset($new->modules[$module->name]->services[$service->name])) {
//					$this->removeService($service, $module);
//				}
//			}
			foreach ($module->commands as $command) {
				if (!isset($new->modules[$module->name]->commands[$command->name])) {
					$this->removeCommand($command, $module);
				}
			}
		}
		foreach ($old->components as $component) {
			if (!isset($new->components[$component->name])) {
				$this->removeComponent($component);
			}
		}
//		Zakomentováno kvůli module trait TemplateFactory a dalším servicám v app
//		foreach ($old->services as $service) {
//			if (!isset($new->services[$service->name])) {
//				$this->removeService($service);
//			}
//		}
		foreach ($old->commands as $command) {
			if (!isset($new->commands[$command->name])) {
				$this->removeCommand($command);
			}
		}
	}


	private function createModule(Module $module): void
	{
		$this->printer->printText($module->name, 'white');
		$this->printer->printText(': creating module');
		$this->generator->createModule($module);
		$this->count++;
		$this->printer->printOk();
	}


	private function createEntity(Entity $entity, ?Module $module = null): void
	{
		$this->printer->printText($module ? $module->name : 'ROOT', 'white');
		$this->printer->printText(': creating entity ');
		$this->printer->printText($entity->name, 'white');
		$this->generator->createModel($entity, $module);
		$this->count++;
		$this->printer->printOk();
	}


	private function updateEntity(Entity $entity, ?Module $module = null): void
	{
		$this->generator->checkBuildModel($entity, $module);
	}


	private function createComponent(Component $component, ?Module $module = null): void
	{
		$this->printer->printText($module ? $module->name : 'ROOT', 'white');
		$this->printer->printText(': creating component ');
		$this->printer->printText($component->name, 'white');
		$this->generator->createComponent($component, $module ?: 'App');
		$this->count++;
		$this->printer->printOk();
	}


	private function createService(Service $service, ?Module $module = null): void
	{
		$this->printer->printText($module ? $module->name : 'ROOT', 'white');
		$this->printer->printText(': creating service ');
		$this->printer->printText($service->name, 'white');
		$this->generator->createService($service, $module);
		$this->count++;
		$this->printer->printOk();
	}


	private function createCommand(Command $command, ?Module $module = null): void
	{
		$this->printer->printText($module ? $module->name : 'ROOT', 'white');
		$this->printer->printText(': creating command ');
		$this->printer->printText($command->name, 'white');
		$this->generator->createCommand($command, $module);
		$this->count++;
		$this->printer->printOk();
	}


	private function removeModule(Module $module)
	{
		$this->printer->printText($module->name, 'white');
		$this->printer->printText(': removing module ');
		$this->generator->removeModule($module);
		$this->count++;
		$this->printer->printOk();
	}


	private function removeComponent(Component $component, ?Module $module = null)
	{
		$this->printer->printText($module ? $module->name : 'ROOT', 'white');
		$this->printer->printText(': removing component ');
		$this->printer->printText($component->name, 'white');
		$this->generator->removeComponent($component, $module);
		$this->count++;
		$this->printer->printOk();
	}


	private function removeService(Service $service, ?Module $module = null)
	{
		$this->printer->printText($module ? $module->name : 'ROOT', 'white');
		$this->printer->printText(': removing service ');
		$this->printer->printText($service->name, 'white');
		$this->generator->removeService($service, $module);
		$this->count++;
		$this->printer->printOk();
	}


	private function removeCommand(Command $command, ?Module $module = null)
	{
		$this->printer->printText($module ? $module->name : 'ROOT', 'white');
		$this->printer->printText(': removing command ');
		$this->printer->printText($command->name, 'white');
		$this->generator->removeCommand($command, $module);
		$this->count++;
		$this->printer->printOk();
	}


	private function removeEntity(Entity $entity, ?Module $module = null)
	{
		$this->printer->printText($module ? $module->name : 'ROOT', 'white');
		$this->printer->printText(': removing entity ');
		$this->printer->printText($entity->name, 'white');
		$this->generator->removeModel($entity, $module);
		$this->count++;
		$this->printer->printOk();
	}
}