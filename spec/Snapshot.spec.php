<?php

/*
 * This file is part of Phunkistan, a type checker for phunkie.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Phunkie\Stan\Watch\Snapshot;

describe("Snapshot", function () {
    it("sees nothing changed between two of the same", function () {
        $one = new Snapshot(["a.phunkie" => "x", "b.phunkie" => "y"]);

        expect($one->changedSince(new Snapshot(["a.phunkie" => "x", "b.phunkie" => "y"])))->toBe([]);
    });

    it("names a source whose contents moved", function () {
        $before = new Snapshot(["a.phunkie" => "x", "b.phunkie" => "y"]);
        $after = new Snapshot(["a.phunkie" => "x", "b.phunkie" => "z"]);

        expect($after->changedSince($before))->toBe(["b.phunkie"]);
    });

    it("names a source that has appeared", function () {
        $after = new Snapshot(["a.phunkie" => "x", "b.phunkie" => "y"]);

        expect($after->changedSince(new Snapshot(["a.phunkie" => "x"])))->toBe(["b.phunkie"]);
    });

    // A source that has gone is a change worth acting on: whatever it said
    // about the rest of the project has gone with it.
    it("names a source that has been taken away", function () {
        $after = new Snapshot(["a.phunkie" => "x"]);

        expect($after->changedSince(new Snapshot(["a.phunkie" => "x", "b.phunkie" => "y"])))->toBe(["b.phunkie"]);
    });
});
