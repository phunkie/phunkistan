# phunkistan

A type checker for [phunkie](https://github.com/phunkie/phunkie).

It reads `.phunkie` sources and reports what is wrong with them in phunkie's own
terms, at positions in the file you wrote. It does not read compiled PHP, so
there is no generated line number to leak and no source map to keep honest.

```bash
phunkistan src/phunkie
```

## Status

Early. The first thing being built is the **parser**, because the compiler's
worst failure today is not a type error but silence: phunkiec has no grammar for
the language it accepts, so notation it has never heard of is passed through
unchanged and becomes PHP that will not parse.

Nothing here checks anything yet.

## How it fits

- **phunkiec** compiles. It runs the grammar only: local, per file, fast, and it
  refuses to emit when the syntax is wrong, because the output would be broken
  PHP.
- **phunkistan** checks. Whole program, and it does not block compilation,
  because its errors describe valid PHP that will throw later.

One grammar, defined here, used by both.

```bash
phunkiec -o=build src/phunkie   # must pass, or there is no build
phunkistan src/phunkie          # must pass, or the build is wrong
```

## Requirements

PHP 8.2, 8.3, 8.4 or 8.5.

## Licence

MIT. Copyright &copy; Marcello Duarte.
