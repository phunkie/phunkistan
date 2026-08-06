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

namespace Phunkie\Stan\Cli;

use Phunkie\Stan\Check\Check;
use Phunkie\Stan\Diagnostic\Diagnostic;
use Phunkie\Stan\Diagnostic\JsonRenderer;
use Phunkie\Stan\Diagnostic\PrettyRenderer;
use Phunkie\Stan\Diagnostic\Renderer;
use Phunkie\Stan\Diagnostic\Span;
use Phunkie\Stan\Source\Source;
use Phunkie\Stan\Source\Sources;
use Phunkie\Stan\Source\UnreadablePath;
use Phunkie\Stan\Watch\PollingWatcher;
use Phunkie\Stan\Watch\Watcher;

/**
 * Checking a path, from the outside.
 *
 * This is the whole of the command, so that the binary is a line of wiring and
 * everything with a decision in it can be specified, analysed and styled like
 * the rest of the code.
 */
final class Application
{
    public const OK = 0;
    public const FOUND_PROBLEMS = 1;
    public const MISUSED = 2;

    /**
     * A path that cannot be read is a finding about the path, not a position in
     * a file. It is still a diagnostic, because it has to reach the same
     * terminal and the same editor as everything else, and its span points at
     * the start of the named path by the convention every protocol uses for
     * something that is about a file as a whole.
     */
    public const UNREADABLE_CODE = 'E0002';
    public const UNREADABLE_CATEGORY = 'CANNOT READ';

    /**
     * @param list<Check> $checks  Checks to run over every source, in order
     * @param Watcher     $watcher Notices sources being saved, under --watch
     */
    public function __construct(
        private readonly array $checks,
        private readonly Watcher $watcher = new PollingWatcher(),
    ) {
    }

    /**
     * Runs the command.
     *
     * @param list<string>  $arguments Arguments, without the program name
     * @param resource|null $out       Stream the report is written to, defaults to stdout
     * @param resource|null $error     Stream misuse is reported on, defaults to stderr
     *
     * @return int Zero when there is nothing wrong, one when there is, two when misused
     */
    public function run(array $arguments, $out = null, $error = null): int
    {
        $out ??= STDOUT;
        $error ??= STDERR;

        $format = 'pretty';
        $watching = false;
        $paths = [];

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--format=')) {
                $format = substr($argument, strlen('--format='));

                continue;
            }

            if ($argument === '--watch') {
                $watching = true;

                continue;
            }

            $paths[] = $argument;
        }

        if ($paths === []) {
            fwrite($error, "usage: phunkistan [--watch] [--format=pretty|json] <path>\n");

            return self::MISUSED;
        }

        $renderer = $this->rendererFor($format);

        if ($renderer === null) {
            fwrite($error, sprintf("phunkistan: unknown format \"%s\".\n", $format));

            return self::MISUSED;
        }

        if ($watching) {
            return $this->keepWatching($paths, $renderer, $format, $out);
        }

        $diagnostics = $this->diagnose($paths);

        // An editor wants its list either way, and an empty one is an answer. A
        // person reading a terminal does not: a checker that chatters on a
        // clean run trains you to stop reading it, and is then worth nothing on
        // the day it has something to say.
        $this->report($diagnostics, $renderer, $format, $out);

        return $diagnostics === [] ? self::OK : self::FOUND_PROBLEMS;
    }

    /**
     * Checks now, and again every time a source is saved.
     *
     * Silence is right for a run that ends, where no news is good news. It is
     * wrong here, where it cannot be told apart from a watcher that has died,
     * so a clean pass says so.
     *
     * @param list<string>  $paths
     * @param resource      $out
     *
     * @return int Never, in practice: a watch ends when the process is stopped
     */
    private function keepWatching(array $paths, Renderer $renderer, string $format, $out): int
    {
        $check = function () use ($paths, $renderer, $format, $out): void {
            $diagnostics = $this->diagnose($paths);

            $this->report($diagnostics, $renderer, $format, $out);

            if ($diagnostics === []) {
                fwrite($out, "No problems found.\n");
            }
        };

        $check();

        fwrite($out, sprintf("Watching %s. Press Ctrl+C to stop.\n", implode(', ', $paths)));

        $this->watcher->watch($paths, $check);

        return self::OK;
    }

    /**
     * @param list<Diagnostic> $diagnostics
     * @param resource         $out
     */
    private function report(array $diagnostics, Renderer $renderer, string $format, $out): void
    {
        $report = $renderer->render($diagnostics);

        if ($report === '') {
            return;
        }

        fwrite($out, $report . ($format === 'json' ? "\n" : ''));
    }

    /**
     * @param list<string> $paths
     *
     * @return list<Diagnostic>
     */
    private function diagnose(array $paths): array
    {
        $diagnostics = [];

        foreach ($paths as $path) {
            try {
                $sources = (new Sources($path))->all();
            } catch (UnreadablePath $unreadable) {
                $diagnostics[] = $this->unreadable($path, $unreadable->getMessage());

                continue;
            }

            foreach ($sources as $source) {
                $diagnostics = array_merge($diagnostics, $this->check($source));
            }
        }

        return $diagnostics;
    }

    /**
     * One source that cannot be read stops that source and nothing else. The
     * rest of a project is still worth checking, and the file is still reported
     * rather than passed over in silence.
     *
     * @return list<Diagnostic>
     */
    private function check(Source $source): array
    {
        $diagnostics = [];

        try {
            foreach ($this->checks as $check) {
                $diagnostics = array_merge($diagnostics, $check->on($source));
            }
        } catch (UnreadablePath $unreadable) {
            return [$this->unreadable($source->relativePath, $unreadable->getMessage())];
        }

        return $diagnostics;
    }

    private function unreadable(string $path, string $message): Diagnostic
    {
        return new Diagnostic(
            self::UNREADABLE_CODE,
            self::UNREADABLE_CATEGORY,
            $message,
            new Span($path, 1, 1)
        );
    }

    private function rendererFor(string $format): ?Renderer
    {
        return match ($format) {
            'pretty' => new PrettyRenderer(),
            'json' => new JsonRenderer(),
            default => null,
        };
    }
}
