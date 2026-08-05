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

/**
 * Opens a source that did not open itself.
 *
 * A `.phunkie` file is only ever PHP, so it need not say `<?php`, but a parser
 * built for PHP will not read a word until it does.
 *
 * The tag is put on the line that was already there rather than on one of its
 * own. Everything downstream reports positions, and a reader sent one line away
 * from their mistake is worse served than one sent nowhere: the line they are
 * shown is usually fine, so they doubt the tool rather than the code.
 */
final class OpeningTag
{
    public const TAG = '<?php ';

    /**
     * Opens a source, and remembers what that cost.
     *
     * The offset is handed to the result rather than back to the caller, so
     * there is no way to open a source and then forget to correct a position
     * taken from it.
     *
     * @param string $source Source as the reader wrote it
     *
     * @return OpenedSource The source a parser can read, and where things are in it
     */
    public function open(string $source): OpenedSource
    {
        if ($this->isOpenedBy($source)) {
            return new OpenedSource($source, 0);
        }

        return new OpenedSource(self::TAG . $source, strlen(self::TAG));
    }

    private function isOpenedBy(string $source): bool
    {
        return preg_match('/^\s*<\?/', $source) === 1;
    }
}
