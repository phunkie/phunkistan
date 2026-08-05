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

namespace Phunkie\Stan\Source;

use RuntimeException;
use Throwable;

/**
 * A path the checker was asked about and could not read.
 *
 * Thrown rather than returned as an empty list, because the two are the same
 * shape and mean opposite things. Somebody has to tell them apart, and it is
 * cheaper to do it here than to remember at every call site.
 */
final class UnreadablePath extends RuntimeException
{
    /**
     * @param string $path Path the caller named
     *
     * @return self An error naming the path that is not there
     */
    public static function notFound(string $path): self
    {
        return new self(sprintf('There is no file or directory at "%s".', $path));
    }

    /**
     * @param string    $path  Path the caller named
     * @param Throwable $cause Why the filesystem refused it
     *
     * @return self An error naming the path that could not be opened
     */
    public static function cannotOpen(string $path, Throwable $cause): self
    {
        return new self(sprintf('The directory "%s" could not be read.', $path), 0, $cause);
    }
}
