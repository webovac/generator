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
use Stepapo\Model\Definition\Collector;
use Stepapo\Model\Definition\MysqlAnalyzer;
use Stepapo\Model\Definition\MysqlProcessor;
use Stepapo\Model\Definition\PgsqlAnalyzer;
use Stepapo\Model\Definition\PgsqlProcessor;
use Stepapo\Utils\DI\StepapoExtension;


class GeneratorExtension extends StepapoExtension
{
}