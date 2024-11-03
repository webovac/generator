<?php

declare(strict_types=1);

namespace Webovac\Generator\DI;

use Nette\DI\CompilerExtension;
use Nette\DI\Extensions\SearchExtension;
use Nette\DI\InvalidConfigurationException;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\Schema\Schema;
use Nette\Schema\ValidationException;
use Stepapo\Definition\Lib\Collector;
use Stepapo\Definition\Lib\MysqlAnalyzer;
use Stepapo\Definition\Lib\MysqlProcessor;
use Stepapo\Definition\Lib\PgsqlAnalyzer;
use Stepapo\Definition\Lib\PgsqlProcessor;
use Stepapo\Utils\DI\StepapoExtension;


class GeneratorExtension extends StepapoExtension
{
}