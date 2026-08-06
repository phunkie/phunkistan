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
use Phunkie\Stan\Diagnostic\Span;
use Phunkie\Stan\Source\OpenedSource;
use Phunkie\Stan\Source\OpeningTag;
use Phunkie\Stan\Source\Source;
use Phunkie\Stan\Type\CallableType;
use Phunkie\Stan\Type\Notation;
use Phunkie\Stan\Type\ReadNotation;
use Phunkie\Stan\Type\Type;
use Phunkie\Stan\Type\TypeApplication;
use Phunkie\Stan\Type\TypeNameUse;

/**
 * Reports a type given more or fewer arguments than it takes.
 *
 * Three things in a file know an arity: phunkie's own types, a class declared
 * here, and a type parameter whose own brackets said what shape it takes. A
 * name from another file knows none, and is left alone until there is a symbol
 * table to ask.
 *
 * A name written with no arguments at all is never wrong. `ImmList $numbers` is
 * opting out of the check rather than failing it, and it is exactly the PHP a
 * guarded signature compiles to.
 */
final class ArityCheck implements Check
{
    public const CODE = 'E0005';
    public const CATEGORY = 'WRONG ARITY';

    public function __construct(
        private readonly Notation $notation = new Notation(),
        private readonly OpeningTag $openingTag = new OpeningTag(),
        private readonly KnownNames $known = new KnownNames(),
    ) {
    }

    /**
     * Checks every type applied to arguments.
     *
     * @param Source $source Source to read
     *
     * @return list<Diagnostic> One for each application of the wrong size
     */
    public function on(Source $source): array
    {
        $code = $source->read();
        $opened = $this->openingTag->open($code);
        $read = $this->notation->readFrom($opened->text());

        if ($read->hasErrors()) {
            return [];
        }

        $diagnostics = [];

        foreach ($this->applicationsIn($read->types) as $application) {
            $diagnostic = $this->judge($application, $read, $source, $code, $opened);

            if ($diagnostic !== null) {
                $diagnostics[] = $diagnostic;
            }
        }

        return $diagnostics;
    }

    private function judge(
        TypeApplication $application,
        ReadNotation $read,
        Source $source,
        string $code,
        OpenedSource $opened
    ): ?Diagnostic {
        $constructor = $application->constructor;

        if (!$constructor instanceof TypeNameUse) {
            return null;
        }

        $expected = $this->arityOf($constructor->name, $read);
        $given = count($application->arguments);

        if ($expected === null || $expected === $given) {
            return null;
        }

        $position = $opened->positionOf($constructor->region()->from);

        return new Diagnostic(
            self::CODE,
            self::CATEGORY,
            sprintf(
                '%s takes %d type %s, %d given.',
                $constructor->name,
                $expected,
                $expected === 1 ? 'argument' : 'arguments',
                $given
            ),
            new Span($source->relativePath, $position->line, $position->column),
            $code
        );
    }

    /**
     * How many arguments a name takes, or null where this file cannot know.
     *
     * A parameter is asked before a class, because a parameter shadows: inside
     * `class Functor<F<_>>` the name `F` means the parameter whatever else in
     * the file is called `F`.
     */
    private function arityOf(string $name, ReadNotation $read): ?int
    {
        foreach ($read->declarations() as $parameter) {
            if ($parameter->name === $name) {
                return $parameter->arity;
            }
        }

        foreach ($read->headers as $header) {
            if ($header->name === $name && $header->arity() > 0) {
                return $header->arity();
            }
        }

        return $this->known->arityOf($name);
    }

    /**
     * @param list<Type> $types
     *
     * @return list<TypeApplication>
     */
    private function applicationsIn(array $types): array
    {
        $found = [];

        foreach ($types as $type) {
            $found = array_merge($found, $this->inside($type));
        }

        return $found;
    }

    /**
     * @return list<TypeApplication>
     */
    private function inside(Type $type): array
    {
        if ($type instanceof TypeApplication) {
            return array_merge([$type], $this->applicationsIn($type->arguments));
        }

        if ($type instanceof CallableType) {
            return array_merge($this->applicationsIn($type->parameters), $this->inside($type->returns));
        }

        return [];
    }
}
