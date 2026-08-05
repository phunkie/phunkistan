<?php

/*
 * This file is part of Phunkistan, a type checker for phunkie.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
  ->exclude(['vendor'])
  ->name('phunkistan')
  ->in(__DIR__);

$rules = [
    '@PHP8x1Migration' => true,
    'trailing_comma_in_multiline' => false,
    'use_arrow_functions' => true,
];

$config = new Config();

return $config
  ->setRules($rules)
  ->setFinder($finder);
