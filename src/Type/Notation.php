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

use Phunkie\Stan\Source\Region;

use PhpToken;

/**
 * Finds phunkie's type notation in a source, and takes it back out.
 *
 * What is left behind is PHP, which is the point: PHP's own parser can then
 * read the file, and everything the notation said has been read by a grammar
 * that knows what it means rather than passed through by one that does not.
 *
 * The notation is replaced by spaces rather than removed. Every byte after it
 * therefore keeps the offset it had, so a position from PHP's parser is already
 * a position in the file the reader wrote, with nothing in between to get
 * wrong. It is the cheapest possible source map: none.
 */
final class Notation
{
    /**
     * The word that declares one of the Cats.
     *
     * It is phunkie's and means nothing to PHP, so the stand-in swaps it for
     * `interface`, which is what a typeclass erases to. The two words are the
     * same length, which is not luck being leaned on but a requirement being
     * met: the swap may not move an offset.
     */
    public const TYPECLASS = 'typeclass';

    private const STANDS_IN = 'interface';

    /**
     * What a type's opening bracket never follows.
     *
     * `fn(...) =>` is an arrow function, `Some(...)` is a call or a pattern, and
     * `$f(...)` is an invocation. Each of those is the language rather than a
     * guess about it, which is why this can exclude without ever hiding a type.
     */
    private const NEVER_BEFORE_A_TYPE = [
        T_FN,
        T_FUNCTION,
        T_STRING,
        T_VARIABLE,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
    ];

    public function __construct(
        private readonly TypeParser $parser = new TypeParser(),
    ) {
    }

    /**
     * Reads every type expression in a source.
     *
     * @param string $source Source, already opened with a PHP tag
     *
     * @return ReadNotation What was found, what could not be read, and the PHP left over
     */
    public function readFrom(string $source): ReadNotation
    {
        $tokens = PhpToken::tokenize($source);
        $types = [];
        $headers = [];
        $errors = [];
        $erasures = [];
        $substitutions = [];
        $blanked = $source;

        // Bodyless classes first, because everything inside one belongs to it:
        // its parameters, its parent, its own semicolon. The main loop is told
        // to pass over their ground.
        $syntheses = [];

        foreach ($this->bodylessClassesIn($tokens) as [$declStart, $classAt]) {
            $scrub = [];

            try {
                $synthesis = $this->synthesis($source, $declStart, $classAt, $types, $headers, $scrub);
            } catch (TypeSyntaxError $error) {
                $errors[] = new TypeSyntaxError($error->getMessage(), $error->offset, $declStart);

                continue;
            }

            if ($synthesis === null) {
                continue;
            }

            $syntheses[] = $synthesis;

            if ($synthesis->bodyOpen === null) {
                // Spaces up to, but not including, the semicolon: alone it is
                // an empty statement, so PHP parses a file in which this class
                // simply does not exist. No body, no bodies to promise.
                $blanked = $this->blank($blanked, $synthesis->region->from, $synthesis->region->to - 1);

                continue;
            }

            // Braces came back, so the class stays in the source: the clause
            // goes, and so do the header's brackets and the parent's
            // arguments, which the synthesis read and therefore blanks. For
            // this form they are ordinary erasures too, the compiler's copy
            // of the same judgement, where the bodyless form replaces the
            // whole declaration and needs none.
            $blanked = $this->blank($blanked, $synthesis->region->from, $synthesis->region->to);

            // The deriving clause is blanked but not erased: the compiler
            // rewrites that ground as the implements it grants, rather than
            // deleting it.
            if ($synthesis->derivingRegion !== null) {
                $blanked = $this->blank($blanked, $synthesis->derivingRegion->from, $synthesis->derivingRegion->to);
            }

            foreach ($scrub as $region) {
                $blanked = $this->blank($blanked, $region->from, $region->to);
                $erasures[] = $region;
            }
        }

        // Block properties next, for the same reason: their arms are theirs,
        // and the main loop must not read patterns as types.
        $blockMethods = [];

        foreach ($this->blockPropertiesIn($tokens) as [$declStart, $variableAt]) {
            try {
                $blockMethods[] = $method = $this->blockMethod($source, $declStart, $variableAt);
            } catch (TypeSyntaxError $error) {
                $errors[] = new TypeSyntaxError($error->getMessage(), $error->offset, $declStart);

                continue;
            }

            // All spaces, semicolon included: a class body has no place for an
            // empty statement, and the method this becomes is the compiler's
            // to write.
            $blanked = $this->blank($blanked, $method->region->from, $method->region->to);
        }

        // Deriving clauses on plain heads, blanked the same way and carried
        // whole: what they grant is the compiler's to write.
        $derivings = $this->derivingClausesIn($tokens);

        foreach ($derivings as $deriving) {
            $blanked = $this->blank($blanked, $deriving->region->from, $deriving->region->to);
        }

        $read = 0;

        foreach ($this->notationIn($tokens) as [$keep, $from, $at, $isHeader, $keyword]) {
            // A nested type is already whole in the type that contains it, so
            // its inner names are candidates that have been read once. Reading
            // them again says the same thing twice, and when the notation is
            // broken it says the same mistake twice.
            if ($at < $read) {
                continue;
            }

            if ($this->insideASynthesis($syntheses, $at) || $this->insideABlockMethod($blockMethods, $at)) {
                continue;
            }

            $cursor = new Cursor(substr($source, $at), $at);

            try {
                // A declaration header is not a type. `class Stack<T>` declares
                // a type constructor and binds a parameter, and reading it as a
                // type would both invent one nobody wrote and put `T` where
                // something is later going to try to look it up.
                if ($isHeader) {
                    $cursor->take($keep);
                    $headers[] = new DeclarationHeader(
                        substr($source, $at, $keep),
                        $this->parser->parameters($cursor),
                        new Region($at, $cursor->offset())
                    );
                    $wrote = $cursor->offset();

                    if ($keyword !== null) {
                        $substitutions[] = new Region($keyword, $keyword + strlen(self::TYPECLASS));
                    }
                } else {
                    $types[] = $this->parser->type($cursor);
                    $wrote = end($types)->region()->to;
                }
            } catch (TypeSyntaxError $error) {
                $read = $error->offset;
                $error = new TypeSyntaxError($error->getMessage(), $error->offset, $at);

                // Reported as a suspicion, not a verdict. Whether this was
                // broken notation or a comparison that was never notation is
                // settled by the checker, which can ask PHP where it gave up on
                // the same source and see whether the two agree.
                $errors[] = $error;

                continue;
            }

            // What follows a completed type is what settles whether it was one.
            // A shift's bracket closes a type that was never opened, and an
            // array key or a match arm reads as a callable that nothing could
            // ever be declared as. Both are arithmetic wearing the notation's
            // shape, and both are only visible from the far end of it.
            if (!$isHeader && !$this->isFollowedProperly($source, $cursor->offset(), end($types))) {
                array_pop($types);

                continue;
            }

            $read = $cursor->offset();

            // A callable type leaves nothing behind for PHP to enforce, so the
            // colon in front of it goes too: `function f():  {` is not PHP,
            // where `function f()    {` is.
            if ($from < $at) {
                $erasures[] = new Region($from, $at);
            }

            $erasures[] = new Region($at + $keep, $wrote);
        }

        // Blanked from the same regions that are handed out, so what this says
        // it took and what it took are one answer rather than two.
        foreach ($erasures as $blank) {
            $blanked = $this->blank($blanked, $blank->from, $blank->to);
        }

        foreach ($substitutions as $substitution) {
            $blanked = substr_replace($blanked, self::STANDS_IN, $substitution->from, $substitution->to - $substitution->from);
        }

        // Companions last: the attribute is legal PHP standing at its own
        // offsets, so nothing above needed to know, and the mirror wants the
        // parameters a synthesis has already read.
        $companions = [];

        foreach ($this->companionAttributesIn($tokens) as [$argsAt, $arguments, $classNameAt, $className]) {
            try {
                $companions[] = $this->companion($className, $classNameAt, $argsAt, $arguments, $syntheses);
            } catch (TypeSyntaxError $error) {
                $errors[] = $error;
            }
        }

        return new ReadNotation($types, $headers, $errors, $blanked, $erasures, $substitutions, $syntheses, $blockMethods, $companions, $derivings);
    }

    /**
     * Every property whose default is a block.
     *
     * @param list<PhpToken> $tokens
     *
     * @return list<array{int, int}> Declaration start (the visibility keyword)
     *                               and the property variable's position
     */
    private function blockPropertiesIn(array $tokens): array
    {
        $found = [];
        $count = count($tokens);

        for ($at = 0; $at < $count; $at++) {
            if (!$tokens[$at]->is(T_VARIABLE)) {
                continue;
            }

            $equals = $this->nextMeaningful($tokens, $at);

            if ($equals === null || $tokens[$equals]->text !== '=') {
                continue;
            }

            $brace = $this->nextMeaningful($tokens, $equals);

            if ($brace === null || $tokens[$brace]->text !== '{') {
                continue;
            }

            // A property has a visibility in front, possibly with a type
            // between them. An assignment in a body has neither, and is a
            // block literal, which is not this feature yet.
            $before = $this->previous($tokens, $at);

            if ($before !== null && $tokens[$before]->is(T_STRING)) {
                $before = $this->previous($tokens, $before);
            }

            if ($before === null || !$tokens[$before]->is([T_PUBLIC, T_PROTECTED, T_PRIVATE])) {
                continue;
            }

            $found[] = [$tokens[$before]->pos, $tokens[$at]->pos];
        }

        return $found;
    }

    /**
     * Reads one block property, and says what method the compiler must write.
     *
     * @throws TypeSyntaxError When the block is not what it started to be
     */
    private function blockMethod(string $source, int $declStart, int $variableAt): BlockMethod
    {
        $cursor = new Cursor(substr($source, $variableAt), $variableAt);
        $cursor->take(1);
        $name = $cursor->takeName();

        if ($name === null) {
            throw new TypeSyntaxError('Expected the property a block hangs on.', $cursor->offset());
        }

        $cursor->skipSpace();
        $cursor->take(1); // =
        $cursor->skipSpace();
        $cursor->take(1); // {

        $bodyStart = $cursor->offset();
        $bodyEnd = $this->closeOfBlock($source, $bodyStart);

        if ($bodyEnd === null) {
            throw new TypeSyntaxError('Expected "}" to close the block.', $cursor->offset());
        }

        $cursor->take($bodyEnd - $bodyStart + 1);
        $cursor->skipSpace();

        if (!$cursor->looksAt(';')) {
            throw new TypeSyntaxError(
                sprintf('Expected ";" after the block, found %s.', $cursor->describeHere()),
                $cursor->offset()
            );
        }

        $cursor->take(1);

        $body = trim(substr($source, $bodyStart, $bodyEnd - $bodyStart));

        // Parameters, where the block declares any, are the call's own. The
        // object is never among them. Only a list of variables with an arrow
        // after it is one: `$this->value` is a body that happens to start
        // with a dollar, because no arrow ever follows it.
        $parameters = [];

        if (preg_match('/^(\$[A-Za-z_]\w*(?:\s*,\s*\$[A-Za-z_]\w*)*)\s*=>\s*(.*)$/s', $body, $split) === 1) {
            $parameters = array_map(
                static fn (string $parameter): string => ltrim(trim($parameter), '$'),
                explode(',', $split[1])
            );
            $body = trim($split[2]);
        }

        return new BlockMethod(
            $name,
            $parameters,
            $body,
            new Region($declStart, $cursor->offset()),
            $this->kindOf($body)
        );
    }

    /**
     * Where the block opened at the current depth closes, counting braces.
     */
    private function closeOfBlock(string $source, int $at): ?int
    {
        $depth = 1;

        for ($length = strlen($source); $at < $length; $at++) {
            $depth += match ($source[$at]) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };

            if ($depth === 0) {
                return $at;
            }
        }

        return null;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function nextMeaningful(array $tokens, int $at): ?int
    {
        for ($at++; $at < count($tokens); $at++) {
            if (!$tokens[$at]->is(T_WHITESPACE)) {
                return $at;
            }
        }

        return null;
    }

    /**
     * @param list<BlockMethod> $blockMethods
     */
    private function insideABlockMethod(array $blockMethods, int $at): bool
    {
        foreach ($blockMethods as $method) {
            if ($method->region->covers($at)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every class declaration that ends in a semicolon rather than a body.
     *
     * @param list<PhpToken> $tokens
     *
     * @return list<array{int, int}> Declaration start and the class keyword's
     *                               position, the first walking back over the
     *                               modifiers the head keeps
     */
    private function bodylessClassesIn(array $tokens): array
    {
        $found = [];
        $count = count($tokens);

        for ($at = 0; $at < $count; $at++) {
            if (!$tokens[$at]->is(T_CLASS)) {
                continue;
            }

            // `Foo::class` is a constant fetch and `new class` has no name a
            // declaration could be addressed to.
            $before = $this->previous($tokens, $at);

            if ($before !== null && $tokens[$before]->is([T_DOUBLE_COLON, T_NEW])) {
                continue;
            }

            $ending = $this->endingOf($tokens, $at);

            if ($ending === null) {
                continue;
            }

            $found[] = [$this->declarationStart($tokens, $at), $tokens[$at]->pos];
        }

        return $found;
    }

    /**
     * How a class declaration ends, where that makes it notation at all.
     *
     * A semicolon is the bodyless form. A body is only notation when a
     * constructor clause sits in the head, a plain PHP class being none of
     * this grammar's business.
     *
     * @param list<PhpToken> $tokens
     */
    private function endingOf(array $tokens, int $at): ?string
    {
        $clause = false;

        for ($at++; $at < count($tokens); $at++) {
            if ($tokens[$at]->text === '(') {
                $clause = true;
            }

            if ($tokens[$at]->text === '{') {
                return $clause ? '{' : null;
            }

            if ($tokens[$at]->text === ';') {
                return ';';
            }
        }

        return null;
    }

    /**
     * Every deriving clause on a plain head.
     *
     * A synthesis reads its own clause, so this walks past anything with a
     * constructor clause, and past anything that is not a class head at all:
     * the walk back from the keyword must reach `class` over nothing but
     * names, commas, brackets and the extends and implements words.
     *
     * @param list<PhpToken> $tokens
     *
     * @return list<DerivingSynthesis>
     */
    private function derivingClausesIn(array $tokens): array
    {
        $found = [];
        $count = count($tokens);

        for ($at = 0; $at < $count; $at++) {
            if (!$tokens[$at]->is(T_STRING) || $tokens[$at]->text !== 'deriving') {
                continue;
            }

            $head = $this->headBehind($tokens, $at);

            if ($head === null) {
                continue;
            }

            [$class, $joins] = $head;
            [$powers, $to, $i] = $this->powersAfter($tokens, $at);

            if ($powers === [] || $to === null) {
                continue;
            }

            // The body's brace is where the powers' methods go; a semicolon
            // first means a bodyless synthesis already owns this clause.
            $bodyOpen = null;

            for (; $i < $count; $i++) {
                if ($tokens[$i]->text === '{') {
                    $bodyOpen = $tokens[$i]->pos;

                    break;
                }

                if ($tokens[$i]->text === ';') {
                    break;
                }
            }

            if ($bodyOpen === null) {
                continue;
            }

            $found[] = new DerivingSynthesis($class, $powers, new Region($tokens[$at]->pos, $to), $joins, $bodyOpen);
        }

        return $found;
    }

    /**
     * What a block body is: arms to match, statements to run, or an
     * expression to answer.
     */
    private function kindOf(string $body): string
    {
        if ($this->hasTopLevelArrow($body)) {
            return 'match';
        }

        return str_ends_with($body, ';') ? 'statements' : 'expression';
    }

    /**
     * Whether an arm arrow sits at the body's own level, outside every
     * bracket and every string, which is what makes the body a match.
     */
    private function hasTopLevelArrow(string $body): bool
    {
        $depth = 0;
        $length = strlen($body);

        for ($at = 0; $at < $length; $at++) {
            $character = $body[$at];

            if ($character === "'" || $character === '"') {
                for ($at++; $at < $length && $body[$at] !== $character; $at++) {
                    if ($body[$at] === '\\') {
                        $at++;
                    }
                }

                continue;
            }

            if (str_contains('([{', $character)) {
                $depth++;

                continue;
            }

            if (str_contains(')]}', $character)) {
                $depth--;

                continue;
            }

            // The spaceship ends in the same two characters; its lesser-than
            // in front is what tells them apart.
            if ($depth === 0 && $character === '=' && $at + 1 < $length && $body[$at + 1] === '>'
                && ($at === 0 || $body[$at - 1] !== '<')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The powers named after the keyword, and where they end.
     *
     * @param list<PhpToken> $tokens
     *
     * @return array{list<string>, int|null, int} Powers, end offset, next token
     */
    private function powersAfter(array $tokens, int $at): array
    {
        $count = count($tokens);
        $powers = [];
        $to = null;
        $i = $at + 1;

        while ($i < $count) {
            while ($i < $count && $tokens[$i]->is([T_WHITESPACE, T_COMMENT])) {
                $i++;
            }

            if ($i >= $count || !$this->readsAsAName($tokens[$i])) {
                break;
            }

            $powers[] = $tokens[$i]->text;
            $to = $tokens[$i]->pos + strlen($tokens[$i]->text);
            $i++;

            while ($i < $count && $tokens[$i]->is(T_WHITESPACE)) {
                $i++;
            }

            if ($i < $count && $tokens[$i]->text === ',') {
                $i++;

                continue;
            }

            break;
        }

        return [$powers, $to, $i];
    }

    /**
     * The class head a clause hangs off, walked to backwards.
     *
     * @param list<PhpToken> $tokens
     *
     * @return array{string, bool}|null Class name, and whether an implements
     *                                  is already there to join
     */
    private function headBehind(array $tokens, int $at): ?array
    {
        $joins = false;
        $count = count($tokens);

        for ($i = $at - 1; $i >= 0; $i--) {
            if ($tokens[$i]->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_EXTENDS])
                || in_array($tokens[$i]->text, [',', '<', '>'], true)) {
                continue;
            }

            if ($tokens[$i]->is(T_IMPLEMENTS)) {
                $joins = true;

                continue;
            }

            if (!$tokens[$i]->is(T_CLASS)) {
                return null;
            }

            for ($j = $i + 1; $j < $count; $j++) {
                if ($tokens[$j]->is(T_WHITESPACE)) {
                    continue;
                }

                return $this->readsAsAName($tokens[$j]) ? [$tokens[$j]->text, $joins] : null;
            }

            return null;
        }

        return null;
    }

    /**
     * Every `#[Companion(...)]` and the class it decorates.
     *
     * @param list<PhpToken> $tokens
     *
     * @return list<array{int, string, int, string}> Where the arguments start,
     *                                               the arguments verbatim, and
     *                                               the class name's position
     *                                               and text
     */
    private function companionAttributesIn(array $tokens): array
    {
        $found = [];
        $count = count($tokens);

        for ($at = 0; $at < $count; $at++) {
            if (!$tokens[$at]->is(T_ATTRIBUTE)) {
                continue;
            }

            $i = $at + 1;

            while ($i < $count && $tokens[$i]->is([T_WHITESPACE, T_COMMENT])) {
                $i++;
            }

            if ($i >= $count || $tokens[$i]->text !== 'Companion') {
                continue;
            }

            $argsAt = $tokens[$i]->pos;
            $arguments = '';
            $i++;

            while ($i < $count && $tokens[$i]->is(T_WHITESPACE)) {
                $i++;
            }

            if ($i < $count && $tokens[$i]->text === '(') {
                $argsAt = $tokens[$i]->pos + 1;
                $depth = 1;
                $i++;

                while ($i < $count && $depth > 0) {
                    if ($tokens[$i]->text === '(') {
                        $depth++;
                    }

                    if ($tokens[$i]->text === ')') {
                        $depth--;
                    }

                    if ($depth > 0) {
                        $arguments .= $tokens[$i]->text;
                    }

                    $i++;
                }
            }

            $class = $this->decoratedClass($tokens, $i);

            if ($class === null) {
                continue;
            }

            $found[] = [$argsAt, $arguments, $class[0], $class[1]];
        }

        return $found;
    }

    /**
     * The class an attribute decorates, walked to over whatever else may
     * legally stand in between: the attribute's own close, neighbouring
     * attributes, comments and modifiers.
     *
     * @param list<PhpToken> $tokens
     *
     * @return array{int, string}|null The class name's position and text
     */
    private function decoratedClass(array $tokens, int $i): ?array
    {
        $count = count($tokens);

        while ($i < $count) {
            if ($tokens[$i]->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_FINAL, T_ABSTRACT, T_READONLY]) || $tokens[$i]->text === ']') {
                $i++;

                continue;
            }

            if ($tokens[$i]->is(T_ATTRIBUTE)) {
                $depth = 1;
                $i++;

                while ($i < $count && $depth > 0) {
                    if ($tokens[$i]->text === '[') {
                        $depth++;
                    }

                    if ($tokens[$i]->text === ']') {
                        $depth--;
                    }

                    $i++;
                }

                continue;
            }

            break;
        }

        if ($i >= $count || !$tokens[$i]->is(T_CLASS)) {
            return null;
        }

        $i++;

        while ($i < $count && $tokens[$i]->is(T_WHITESPACE)) {
            $i++;
        }

        if ($i >= $count || !$this->readsAsAName($tokens[$i])) {
            return null;
        }

        return [$tokens[$i]->pos, $tokens[$i]->text];
    }

    /**
     * Reads one companion declaration from the attribute's own arguments.
     *
     * The mirror's parameters come from the synthesis that read the class's
     * primary constructor; a plain head has none, which is exactly the class
     * a recipe is for.
     *
     * @param list<ClassSynthesis> $syntheses
     *
     * @throws TypeSyntaxError When the arguments are not what the attribute takes
     */
    private function companion(string $className, int $classNameAt, int $argsAt, string $arguments, array $syntheses): CompanionSynthesis
    {
        $parameters = [];

        // By the name the head ends with, not by region: the braces-back
        // form's region is only the constructor clause, which starts after
        // the name the attribute knows.
        foreach ($syntheses as $synthesis) {
            if (str_ends_with($synthesis->head, ' ' . $className)) {
                $parameters = $synthesis->parameters;
            }
        }

        $singleton = false;
        $withArguments = true;
        $variadic = null;
        $nullable = null;
        $named = null;

        $cursor = new Cursor($arguments, $argsAt);
        $cursor->skipSpace();

        while (!$cursor->atEnd()) {
            $name = $cursor->takeName();

            if ($name === null || !$cursor->looksAt(':')) {
                throw new TypeSyntaxError('Expected "name: value" inside "#[Companion(...)]".', $cursor->offset(), $argsAt);
            }

            $cursor->take(1);
            $cursor->skipSpace();

            match ($name) {
                'singleton' => $singleton = $this->boolean($cursor, $name),
                'withArguments' => $withArguments = $this->boolean($cursor, $name),
                'variadic' => $variadic = $this->cases($cursor, $name),
                'nullable' => $nullable = $this->cases($cursor, $name),
                'named' => $named = $cursor->takeName(),
                default => throw new TypeSyntaxError(sprintf('"#[Companion]" has no "%s" argument.', $name), $cursor->offset(), $argsAt),
            };

            $cursor->skipSpace();

            if ($cursor->looksAt(',')) {
                $cursor->take(1);
                $cursor->skipSpace();
            }
        }

        return new CompanionSynthesis($className, $parameters, $singleton, $withArguments, $variadic, $nullable, $named);
    }

    /**
     * @throws TypeSyntaxError When the value is not true or false
     */
    private function boolean(Cursor $cursor, string $argument): bool
    {
        $value = $cursor->takeName();

        if ($value !== 'true' && $value !== 'false') {
            throw new TypeSyntaxError(sprintf('"%s" takes true or false.', $argument), $cursor->offset(), $cursor->offset());
        }

        return $value === 'true';
    }

    /**
     * A recipe's two cases: what builds, and what answers empty.
     *
     * @return list<string>
     *
     * @throws TypeSyntaxError When the list does not name exactly two cases
     */
    private function cases(Cursor $cursor, string $recipe): array
    {
        if (!$cursor->looksAt('[')) {
            throw new TypeSyntaxError(sprintf('A "%s" recipe expects "[Case, Case]".', $recipe), $cursor->offset(), $cursor->offset());
        }

        $cursor->take(1);
        $names = [];

        while (true) {
            $cursor->skipSpace();
            $name = $cursor->takeName();

            if ($name === null) {
                break;
            }

            $names[] = $name;
            $cursor->skipSpace();

            if ($cursor->looksAt(',')) {
                $cursor->take(1);

                continue;
            }

            break;
        }

        if (!$cursor->looksAt(']') || count($names) !== 2) {
            throw new TypeSyntaxError(sprintf('A "%s" recipe names two cases: what builds, and what answers empty.', $recipe), $cursor->offset(), $cursor->offset());
        }

        $cursor->take(1);

        return $names;
    }

    /**
     * Where a declaration begins, its modifiers included.
     *
     * @param list<PhpToken> $tokens
     */
    private function declarationStart(array $tokens, int $at): int
    {
        $start = $tokens[$at]->pos;

        while (($before = $this->previous($tokens, $at)) !== null
            && $tokens[$before]->is([T_FINAL, T_ABSTRACT, T_READONLY])) {
            $start = $tokens[$before]->pos;
            $at = $before;
        }

        return $start;
    }

    /**
     * Reads one bodyless declaration, and says what the compiler must write.
     *
     * @param list<Type>              $types   Uses found on the way, appended to
     * @param list<DeclarationHeader> $headers Headers found on the way, appended to
     * @param list<Region>            $scrub   Regions the synthesis read and must
     *                                         therefore blank, appended to
     *
     * @throws TypeSyntaxError When the declaration is not what it started to be
     */
    private function synthesis(string $source, int $declStart, int $classAt, array &$types, array &$headers, array &$scrub = []): ?ClassSynthesis
    {
        $cursor = new Cursor(substr($source, $classAt), $classAt);
        $cursor->take(strlen('class'));
        $cursor->skipSpace();

        $nameStart = $cursor->offset();
        $name = $cursor->takeName();

        if ($name === null) {
            return null;
        }

        $head = trim((string) preg_replace('/\s+/', ' ', substr($source, $declStart, $cursor->offset() - $declStart)));
        $bound = [];

        if ($cursor->looksAt('<')) {
            $bracketsFrom = $cursor->offset();
            $parameters = $this->parser->parameters($cursor);
            $headers[] = new DeclarationHeader($name, $parameters, new Region($nameStart, $cursor->offset()));
            $scrub[] = new Region($bracketsFrom, $cursor->offset());

            foreach ($parameters as $parameter) {
                $bound[] = $parameter->name;
            }
        }

        $clauseFrom = null;
        $cursor->skipSpace();

        if ($cursor->looksAt('(')) {
            $clauseFrom = $cursor->offset();
        }

        $constructed = $this->constructorParameters($cursor, $types, $bound);
        $clause = $clauseFrom === null ? null : new Region($clauseFrom, $cursor->offset());

        $cursor->skipSpace();
        $parent = null;

        if ($cursor->looksAt('extends')) {
            $cursor->take(strlen('extends'));
            $type = $this->parser->type($cursor);
            $types[] = $type;
            $parent = $this->phpNameOf($type, []);

            // The parent keeps its name, as any use does, and its arguments go.
            $scrub[] = new Region($type->region()->from + strlen((string) $parent), $type->region()->to);
        }

        $cursor->skipSpace();
        [$derivings, $derivingRegion] = $this->derivingClause($cursor);
        $cursor->skipSpace();

        if ($cursor->looksAt('{')) {
            if ($clause === null) {
                return null;
            }

            return new ClassSynthesis($head, $parent, $constructed, $clause, $cursor->offset(), $derivings, $derivingRegion);
        }

        if (!$cursor->looksAt(';')) {
            throw new TypeSyntaxError(
                sprintf('Expected ";" or a body to close the class, found %s.', $cursor->describeHere()),
                $cursor->offset()
            );
        }

        $cursor->take(1);

        return new ClassSynthesis($head, $parent, $constructed, new Region($declStart, $cursor->offset()), null, $derivings, $derivingRegion);
    }

    /**
     * The deriving clause, where the head carries one.
     *
     * @throws TypeSyntaxError When the keyword is not followed by powers
     *
     * @return array{list<string>, Region|null} The powers, and where the clause stood
     */
    private function derivingClause(Cursor $cursor): array
    {
        if (!$cursor->looksAt('deriving')) {
            return [[], null];
        }

        $from = $cursor->offset();
        $cursor->take(strlen('deriving'));
        $powers = [];

        while (true) {
            $cursor->skipSpace();
            $power = $cursor->takeName();

            if ($power === null) {
                throw new TypeSyntaxError('Expected a power after "deriving".', $cursor->offset());
            }

            $powers[] = $power;
            $cursor->skipSpace();

            if ($cursor->looksAt(',')) {
                $cursor->take(1);

                continue;
            }

            break;
        }

        return [$powers, new Region($from, $cursor->offset())];
    }

    /**
     * The primary constructor's parameters, where a declaration has one.
     *
     * @param list<Type>   $types Uses found on the way, appended to
     * @param list<string> $bound Type parameters the header bound
     *
     * @throws TypeSyntaxError
     *
     * @return list<SynthesisParameter>
     */
    private function constructorParameters(Cursor $cursor, array &$types, array $bound): array
    {
        $cursor->skipSpace();

        if (!$cursor->looksAt('(')) {
            return [];
        }

        $cursor->take(1);
        $cursor->skipSpace();
        $parameters = [];

        while (!$cursor->looksAt(')')) {
            $type = $this->parser->type($cursor);
            $types[] = $type;
            $cursor->skipSpace();

            if (!$cursor->looksAt('$')) {
                throw new TypeSyntaxError(
                    sprintf('Expected a variable for the property, found %s.', $cursor->describeHere()),
                    $cursor->offset()
                );
            }

            $cursor->take(1);
            $name = $cursor->takeName();

            if ($name === null) {
                throw new TypeSyntaxError('Expected a name after the dollar.', $cursor->offset());
            }

            $parameters[] = new SynthesisParameter($name, $this->phpNameOf($type, $bound));
            $cursor->skipSpace();

            if ($cursor->looksAt(',')) {
                $cursor->take(1);
                $cursor->skipSpace();
            }
        }

        $cursor->take(1);

        return $parameters;
    }

    /**
     * The name PHP could still enforce for a type, if there is one.
     *
     * A type variable is nothing to PHP, and a function's shape has no name at
     * all, so both answer null and the property they become is typed mixed.
     *
     * @param list<string> $bound Names that are type parameters here
     */
    private function phpNameOf(Type $type, array $bound): ?string
    {
        if ($type instanceof TypeApplication) {
            $type = $type->constructor;
        }

        if (!$type instanceof TypeNameUse || in_array($type->name, $bound, true)) {
            return null;
        }

        return $type->name;
    }

    /**
     * @param list<ClassSynthesis> $syntheses
     */
    private function insideASynthesis(array $syntheses, int $at): bool
    {
        foreach ($syntheses as $synthesis) {
            if ($synthesis->region->covers($at)) {
                return true;
            }

            // A body-form synthesis read its header and its parent itself,
            // so everything up to the body's brace is its ground too.
            if ($synthesis->bodyOpen !== null && $at < $synthesis->bodyOpen && $at >= $synthesis->region->from) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every place the notation appears, as how much of it PHP should keep, what
     * to blank from, and where the type itself begins.
     *
     * A named type PHP could enforce keeps its name, so `ImmList<Int>` leaves
     * `ImmList` behind and only the arguments go. Anything PHP could not accept
     * as a type name goes whole, and so does a callable type, and a return
     * type's colon goes with it.
     *
     * @param list<PhpToken> $tokens
     *
     * @return list<array{int, int, int, bool, int|null}>
     */
    private function notationIn(array $tokens): array
    {
        $found = [];
        $count = count($tokens);

        for ($at = 0; $at < $count; $at++) {
            $token = $tokens[$at];
            $next = $this->skipSpace($tokens, $at + 1);

            // The bracket is only tested for at its start. PHP's lexer runs it
            // together with whatever follows, so an empty group arrives as
            // `<>`, which is the not-equal operator, and never as a bracket at
            // all.
            if ($this->readsAsAName($token) && $next < $count && str_starts_with($tokens[$next]->text, '<')) {
                // Grammar position says unambiguously which this is, which is
                // why the parser can mark binders as declarations and never has
                // to guess.
                if ($this->isHeader($tokens, $at)) {
                    $found[] = [strlen($token->text), $token->pos, $token->pos, true, $this->typeclassBefore($tokens, $at)];

                    continue;
                }

                $keep = $this->keepableLength($token);

                $found[] = [$keep, $keep === 0 ? $this->blankFrom($tokens, $at) : $token->pos, $token->pos, false, null];

                continue;
            }

            if ($this->opensACallableType($tokens, $at)) {
                $found[] = [0, $this->blankFrom($tokens, $at), $token->pos, false, null];
            }
        }

        return $found;
    }

    /**
     * Whether a name is the one being declared rather than one being used.
     *
     * What sits in front settles it and nothing else can: after `class` the
     * brackets introduce parameters, and everywhere else they supply arguments.
     *
     * @param list<PhpToken> $tokens
     */
    private function isHeader(array $tokens, int $at): bool
    {
        $before = $this->previous($tokens, $at);

        if ($before === null) {
            return false;
        }

        return $tokens[$before]->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_FUNCTION])
            || $this->typeclassBefore($tokens, $at) !== null;
    }

    /**
     * Where the typeclass keyword sits in front of a name, if it does.
     *
     * Asked separately from `isHeader` because the keyword is the one part of
     * a header that has to be rewritten as well as recognised: PHP has no idea
     * what a typeclass is, and `interface` is what one erases to.
     *
     * @param list<PhpToken> $tokens
     */
    private function typeclassBefore(array $tokens, int $at): ?int
    {
        $before = $this->previous($tokens, $at);

        if ($before === null || !$tokens[$before]->is(T_STRING) || $tokens[$before]->text !== self::TYPECLASS) {
            return null;
        }

        return $tokens[$before]->pos;
    }

    /**
     * Whether a token reads as a name in this grammar.
     *
     * Asked of the text rather than of the token's kind, because PHP has around
     * forty words it keeps for itself and `Function<A, B>` and `Try<A>` are not
     * unlikely names in a functional language. Enumerating the ones it would be
     * a shame to lose is a list that is never finished; asking the grammar what
     * a name is has no list in it at all.
     */
    private function readsAsAName(PhpToken $token): bool
    {
        return preg_match('/^[A-Za-z_\x80-\xff\\\\][A-Za-z0-9_\x80-\xff\\\\]*$/', $token->text) === 1;
    }

    /**
     * How much of a named type is worth leaving for PHP.
     *
     * A name PHP would accept as a class stays, so `ImmList<Int>` still tells
     * PHP there is an `ImmList` and lets it say when there is not. A name PHP
     * keeps for itself goes with its arguments, because `Function` alone is not
     * a type PHP will read however few brackets follow it.
     *
     * `array` is the exception in both directions: reserved, and a type PHP
     * enforces. Blanking it whole would leave `array<User> $users` weaker than
     * the plain `array $users` it was written to improve on.
     *
     * The token's kind decides this, which is safe where deciding detection by
     * it was not: being wrong here means blanking more than necessary, and
     * being wrong there meant missing notation entirely.
     */
    private function keepableLength(PhpToken $token): int
    {
        $names = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE, T_ARRAY];

        return $token->is($names) ? strlen($token->text) : 0;
    }

    /**
     * Where blanking should start for a type that leaves nothing behind.
     *
     * A return type's colon has to go with it. `function f():  {` is not PHP,
     * where `function f()    {` is, and PHP has nothing left to enforce either
     * way once the name it would have enforced is gone.
     *
     * @param list<PhpToken> $tokens
     */
    private function blankFrom(array $tokens, int $at): int
    {
        $before = $this->previous($tokens, $at);

        if ($before !== null && $tokens[$before]->text === ':') {
            return $tokens[$before]->pos;
        }

        return $tokens[$at]->pos;
    }

    /**
     * Whether a parenthesis might begin a callable type.
     *
     * Only might: what is in front of a bracket cannot tell a type from a call,
     * an arrow function or a match arm, and guessing from it both missed a
     * callable property and reported a parenthesised array key. What settles it
     * is what comes after the whole type, which is not known until it has been
     * read, so the question is asked again once it has.
     *
     * @param list<PhpToken> $tokens
     */
    private function opensACallableType(array $tokens, int $at): bool
    {
        $close = $this->closeOfGroup($tokens, $at);

        if ($close === null) {
            return false;
        }

        // `fn(...) =>` is an arrow function and `function (...) ... {` a closure,
        // always, in every position. That is the language rather than a guess
        // about it, so excluding them can never hide a type. Without this every
        // `fn($n) => ...` in a body reads as broken notation, and one of those
        // is enough to turn the rest of the checks off for the whole file.
        $before = $this->previous($tokens, $at);

        // A bracket straight after a name is a call, and after a variable or a
        // closing bracket it is an expression. None of those is ever a type, in
        // any position, so excluding them cannot hide one. Without this every
        // `Some($value) => ...` in a pattern match reads as broken notation.
        if ($before !== null && $tokens[$before]->is(self::NEVER_BEFORE_A_TYPE)) {
            return false;
        }

        // A type's parameter list names types. A pattern's names the variables
        // it is binding, so `($a, $b) => ...` deconstructing a tuple is not a
        // callable type however much it looks like one.
        if ($this->binds($tokens, $at, $close)) {
            return false;
        }

        $after = $this->skipSpace($tokens, $close + 1);

        return $after < count($tokens) && $tokens[$after]->is(T_DOUBLE_ARROW);
    }

    /**
     * Whether a group names variables rather than types.
     *
     * @param list<PhpToken> $tokens
     */
    private function binds(array $tokens, int $from, int $to): bool
    {
        for ($at = $from; $at <= $to; $at++) {
            if ($tokens[$at]->is(T_VARIABLE)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Where a parenthesised group starting here closes.
     *
     * `(string)` never reaches this as a bracket at all: PHP's lexer reads it
     * as one cast token, because in PHP that is what it is. A parameter list
     * naming PHP's own scalar types therefore looks nothing like a parameter
     * list until the cast is recognised as the group it is written as.
     *
     * @param list<PhpToken> $tokens
     */
    private function closeOfGroup(array $tokens, int $at): ?int
    {
        if ($tokens[$at]->is([T_ARRAY_CAST, T_BOOL_CAST, T_DOUBLE_CAST, T_INT_CAST, T_OBJECT_CAST, T_STRING_CAST, T_UNSET_CAST])) {
            return $at;
        }

        return $tokens[$at]->text === '(' ? $this->matching($tokens, $at) : null;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function matching(array $tokens, int $open): ?int
    {
        $depth = 0;

        for ($at = $open; $at < count($tokens); $at++) {
            $depth += match ($tokens[$at]->text) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            if ($depth === 0) {
                return $at;
            }
        }

        return null;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function previous(array $tokens, int $at): ?int
    {
        $at--;

        while ($at >= 0 && $tokens[$at]->is(T_WHITESPACE)) {
            $at--;
        }

        return $at < 0 ? null : $at;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function skipSpace(array $tokens, int $at): int
    {
        while ($at < count($tokens) && $tokens[$at]->is(T_WHITESPACE)) {
            $at++;
        }

        return $at;
    }

    /**
     * Whether what comes after a type is something a type may be followed by.
     *
     * A callable type is only ever declared somewhere a declaration can go, so
     * a variable, a reference, a variadic, a body or a semicolon follows it.
     * `[1, (FOO) => BAR]` and a match arm read as callables and are followed by
     * a comma, which no declaration ever is.
     *
     * The variadic is spelled in full, all three dots. Accepting one dot
     * accepted concatenation with it, which made `[(FOO) => BAR . "x"]` a
     * callable type, and the compiler removes what this says it found: a
     * working array came apart into `[ . "x"]`.
     *
     * A named type is asked only whether another `>` follows, which would mean
     * the bracket it just consumed belonged to a shift: `MIN < MAX >> 2`.
     */
    private function isFollowedProperly(string $source, int $at, Type|false $type): bool
    {
        $rest = substr($source, $at, 8);

        if ($type instanceof CallableType) {
            return preg_match('/^\s*(\$|&|\.\.\.|\{|;)/', $rest) === 1;
        }

        return preg_match('/^\s*>/', $rest) !== 1;
    }

    /**
     * Replaces a stretch of source with spaces of the same length.
     */
    private function blank(string $source, int $from, int $to): string
    {
        return substr_replace($source, str_repeat(' ', $to - $from), $from, $to - $from);
    }
}
