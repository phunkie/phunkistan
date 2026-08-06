Feature: Developer is told when a name in a type means nothing here

  Scenario: A misspelled type is reported, and the name is what is underlined
    Given there is a source "src/Todo.phunkie" containing:
      """
      use Phunkie\Types\ImmList;

      function doubleAll(ImmList<Itn> $numbers): ImmList<Int>
      {
          return $numbers;
      }
      """
    When I check "src"
    Then it should have failed
    And it should have said "Itn"
    And it should have reported "src/Todo.phunkie" at line 3
    And the caret should sit under the "Itn"

  Scenario: An imported name means something
    Given there is a source "src/Todo.phunkie" containing:
      """
      use App\Model\User;

      function names(array<User> $users): array<String>
      {
          return $users;
      }
      """
    When I check "src"
    Then it should have passed
    And it should have said nothing

  Scenario: A name declared in the same file means something
    Given there is a source "src/Todo.phunkie" containing:
      """
      final class User
      {
      }

      function names(ImmList<User> $users): Int
      {
          return 1;
      }
      """
    When I check "src"
    Then it should have passed
    And it should have said nothing

  # A binder introduces a name rather than looking one up, so a parameter list
  # is never wrong about a name however it is spelled.
  Scenario: A type parameter is declared, not looked up
    Given there is a source "src/Stack.phunkie" containing:
      """
      final class Stack<Itn>
      {
          public function push(Itn $item): Stack<Itn>
          {
              return $this;
          }
      }
      """
    When I check "src"
    Then it should have passed
    And it should have said nothing

  # The name may well be declared in the next file along, and this milestone is
  # not allowed to look. Reporting it would fire on every project with two files
  # in one namespace.
  Scenario: A namespaced file keeps its unknown names to itself
    Given there is a source "src/Todo.phunkie" containing:
      """
      namespace App\Todo;

      function all(TodoList<Itn> $xs): Int
      {
          return 1;
      }
      """
    When I check "src"
    Then it should have passed
    And it should have said nothing
