<?php

/*
 * This file is part of Phunkistan, a type checker for phunkie.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phunkie\Stan\Check;

use Phunkie\Stan\Diagnostic\Diagnostic;
use Phunkie\Stan\Source\Source;

/**
 * One thing worth knowing about a source.
 *
 * Checks are a list rather than a chain: adding one is adding an element, and
 * removing one is removing an element. `SyntaxCheck` is due to be deleted the
 * day phunkiec owns the grammar, and that should cost nothing.
 */
interface Check
{
    /**
     * Checks one source.
     *
     * @param Source $source Source to read
     *
     * @return list<Diagnostic> Everything wrong with it, empty where nothing is
     */
    public function on(Source $source): array;
}
