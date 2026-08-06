Feature: Developer is told when a type is given the wrong number of arguments

  Scenario: A phunkie type given too few
    Given there is a source "src/Todo.phunkie" containing:
      """
      function firstYear(ImmMap<String> $born): Int
      {
          return 1;
      }
      """
    When I check "src"
    Then it should have failed
    And it should have said "ImmMap takes 2 type arguments, 1 given"
    And it should have reported "src/Todo.phunkie" at line 1

  Scenario: A phunkie type given too many
    Given there is a source "src/Todo.phunkie" containing:
      """
      function doubleAll(ImmList<Int, String> $numbers): Int
      {
          return 1;
      }
      """
    When I check "src"
    Then it should have failed
    And it should have said "ImmList takes 1 type argument, 2 given"

  # A bare name is opting out rather than getting it wrong. `ImmList $numbers`
  # is what you write when you do not want the argument checked, and it is the
  # PHP a signature compiles to.
  Scenario: A type given no arguments at all is left alone
    Given there is a source "src/Todo.phunkie" containing:
      """
      function doubleAll(ImmList $numbers): ImmMap
      {
          return $numbers;
      }
      """
    When I check "src"
    Then it should have passed
    And it should have said nothing

  Scenario: A class in this file is held to what it declared
    Given there is a source "src/Stack.phunkie" containing:
      """
      final class Stack<T>
      {
          public function push(T $item): Stack<Int, String>
          {
              return $this;
          }
      }
      """
    When I check "src"
    Then it should have failed
    And it should have said "Stack takes 1 type argument, 2 given"

  # A parameter's own brackets say what shape it takes, so a use of it is held
  # to that too.
  Scenario: A type parameter is held to the arity it declared
    Given there is a source "src/Functor.phunkie" containing:
      """
      class Functor<F<_>>
      {
          public function map(F<Int, String> $fa): Int
          {
              return 1;
          }
      }
      """
    When I check "src"
    Then it should have failed
    And it should have said "F takes 1 type argument, 2 given"

  Scenario: A type from another file is not guessed at
    Given there is a source "src/Todo.phunkie" containing:
      """
      namespace App\Todo;

      function all(TodoList<Int, String, Bool> $xs): Int
      {
          return 1;
      }
      """
    When I check "src"
    Then it should have passed
    And it should have said nothing
