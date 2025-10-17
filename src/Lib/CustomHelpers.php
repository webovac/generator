<?php

namespace Webovac\Generator\Lib;

use Nette\Utils\Strings;


class CustomHelpers
{
	public static function formatDocComment(string $content, bool $forceMultiLine = false): string
	{
		$s = trim($content);
		$s = str_replace('*/', '* /', $s);
		if ($s === '') {
			return '';
		} elseif ($forceMultiLine || str_contains($content, "\n")) {
			$s = str_replace("\n", "\n * ", "/**\n$s") . "\n */";
			return Strings::normalize($s) . "\n";
		} else {
			return "/** $s */ ";
		}
	}
}