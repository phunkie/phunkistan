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

namespace Phunkie\Stan\Type;

/**
 * Every name written inside a type, however deeply.
 *
 * A use is only reportable one name at a time. `ImmList<Itn>` is wrong about
 * `Itn` and right about `ImmList`, so a diagnostic that underlined the whole
 * expression would point at the part that is fine as well as the part that is
 * not.
 */
final class Names
{
    /**
     * @param list<Type> $types Types to walk
     *
     * @return list<TypeNameUse> Every name used, in the order it was written
     */
    public function usedIn(array $types): array
    {
        $names = [];

        foreach ($types as $type) {
            $names = array_merge($names, $this->inside($type));
        }

        return $names;
    }

    /**
     * @return list<TypeNameUse>
     */
    private function inside(Type $type): array
    {
        if ($type instanceof TypeNameUse) {
            return [$type];
        }

        if ($type instanceof TypeApplication) {
            return array_merge($this->inside($type->constructor), $this->usedIn($type->arguments));
        }

        if ($type instanceof CallableType) {
            return array_merge($this->usedIn($type->parameters), $this->inside($type->returns));
        }

        return [];
    }
}
