Feature: Developer writes phunkie notation and the checker understands it

  # Until there is a grammar, the checker reads a source with PHP's own parser
  # and reports type arguments as a syntax error, because to PHP that is what
  # they are. This is the milestone where phunkie stops being wrong PHP and
  # starts being a language.
  Scenario: A type argument on a parameter is understood
    Given there is a source "src/Todo.phunkie" containing:
      """
      function doubleAll(ImmList<Int> $numbers): ImmList<Int>
      {
          return $numbers->map(fn($n) => $n * 2);
      }
      """
    When I check "src"
    Then it should have passed
    And it should have said nothing

  Scenario: Every shape the notation can take is understood
    Given there is a source "src/Todo.phunkie" containing:
      """
      use App\Model\User;

      class Registry
      {
          private array<string, User> $byName;

          private array<User> $all;

          public function rows(ImmMap<String, Int> $counts): ImmList<Option<Int>>
          {
              return ImmList();
          }

          public function deep(ImmList<Option<ImmList<Int>>> $xs): Int
          {
              return 1;
          }

          public function keep((string) => bool $matches, ImmList<String> $names): ImmList<String>
          {
              return $names;
          }

          public function greeter(string $greeting): (string) => string
          {
              return fn($name) => $greeting;
          }
      }
      """
    When I check "src"
    Then it should have passed
    And it should have said nothing

  # A function may answer with a function, so the arrow leans right:
  # `(int) => (int) => string` takes an int and gives back a function.
  Scenario: A function that answers with a function
    Given there is a source "src/Curry.phunkie" containing:
      """
      function curry(Int $a): (Int) => (Int) => String
      {
          return fn($b) => fn($c) => "x";
      }
      """
    When I check "src"
    Then it should have passed
    And it should have said nothing

  # An arrow function body reads exactly like a callable type: a bracketed
  # group followed by an arrow. One of them anywhere in a file used to be
  # enough to turn every other check off for that file.
  Scenario: An arrow function in a body is not notation
    Given there is a source "src/Todo.phunkie" containing:
      """
      function doubleAll(ImmList<Itn> $numbers): Int
      {
          return $numbers->map(fn($n) => $n * 2);
      }
      """
    When I check "src"
    Then it should have failed
    And it should have said "Nothing here is called"

  # A callable type is declared wherever a declaration goes, and is recognised
  # by what follows it rather than by what sits in front, so a comment or an
  # attribute in between makes no difference.
  Scenario: A callable type in every position it may appear
    Given there is a source "src/Registry.phunkie" containing:
      """
      interface Matching
      {
          public function matcher(): (string) => bool;
      }

      class Registry
      {
          private (string) => bool $matches;

          public function __construct(private (string) => bool $also)
          {
          }

          public function keep(#[Att] (string) => bool $m, ImmList<String> $names): ImmList<String>
          {
              return $names;
          }
      }
      """
    When I check "src"
    Then it should have passed
    And it should have said nothing

  Scenario: A class declares the parameters it takes
    Given there is a source "src/Stack.phunkie" containing:
      """
      final class Stack<T>
      {
          public function push(T $item): Stack<T>
          {
              return $this;
          }
      }

      class Functor<F<_>>
      {
      }
      """
    When I check "src"
    Then it should have passed
    And it should have said nothing

  # A name in front of the bracket is what tells notation from arithmetic, and
  # PHP 8 made comparison non-associative, so `X < Y > Z` can never be a
  # comparison somebody meant.
  Scenario: Arithmetic that looks like notation is left alone
    Given there is a source "src/Maths.phunkie" containing:
      """
      $bits = 8 >> 2;
      $shifted = MIN < MAX >> 2;
      $smaller = $a < $b;
      $capped = MAX < 3;
      $tight = MAX<MIN;
      $counted = MAX<count($xs);
      $called = MAX<strlen($s);
      $grouped = MAX<($a + $b);
      $unequal = FOO<>$b;
      $keyed = [1, (2) => 3];
      $matched = match ($x) { 1, (2) => 3, default => 4 };
      $named = [OPEN => 1, (SHUT) => SHUT, 3 => 2];
      $arm = match ($y) { 1, (FOO) => BAR, default => 4 };
      """
    When I check "src"
    Then it should have passed
    And it should have said nothing
