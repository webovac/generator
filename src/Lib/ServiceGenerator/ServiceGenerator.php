<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\ServiceGenerator;

use Webovac\Generator\Config\Module;
use Webovac\Generator\Config\Service;
use Webovac\Generator\Lib\BaseGenerator;
use Webovac\Generator\Lib\SetupProvider\ISetupProvider;


class ServiceGenerator extends BaseGenerator
{
	public const string SERVICE = 'service';


	public function __construct(
		private Service $service,
		private ?Module $module,
		ISetupProvider $setupProviderFactory,
	) {
		$this->setupProvider = $setupProviderFactory->create(
			name: $this->service->name,
			service: $this->service,
			module: $this->module,
		);
	}


	public function generate(): void
	{
		$this->createService();
	}


	public function remove(): void
	{
		$this->writer->remove($this->setupProvider->getBasePath(self::SERVICE));
	}


	private function createService(): void
	{
		$this->write(self::SERVICE);
	}
}
