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
     * Adds the opening tag where the source has none.
     *
     * @param string $source Source as the reader wrote it
     *
     * @return string The same source, guaranteed to open a PHP tag
     */
    public function ensure(string $source): string
    {
        if ($this->isOpenedBy($source)) {
            return $source;
        }

        return self::TAG . $source;
    }

    /**
     * How far the first line was pushed along by opening the tag.
     *
     * Only the first line moves, and only sideways, so this is all a position
     * needs to be turned back into one the reader recognises.
     *
     * @param string $source Source as the reader wrote it
     *
     * @return int Columns added to line one, zero where the source opened itself
     */
    public function columnOffsetIn(string $source): int
    {
        return $this->isOpenedBy($source) ? 0 : strlen(self::TAG);
    }

    private function isOpenedBy(string $source): bool
    {
        return preg_match('/^\s*<\?/', $source) === 1;
    }
}
