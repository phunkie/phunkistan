Feature: Developer is told what is wrong with the notation, in phunkie's terms

  # Without a grammar these all reached PHP's parser, which reported them as
  # unexpected tokens somewhere near the mistake, if it reported them at all.
  Scenario: Two type arguments with nothing between them
    Given there is a source "src/Todo.phunkie" containing:
      """
      function names(array<int User> $users): int
      {
          return 1;
      }
      """
    When I check "src"
    Then it should have failed
    And it should have said "between type arguments"
    And it should have reported "src/Todo.phunkie" at line 1

  Scenario: A group that never closes
    Given there is a source "src/Todo.phunkie" containing:
      """
      function doubleAll(ImmList<Int $numbers): int
      {
          return 1;
      }
      """
    When I check "src"
    Then it should have failed
    And it should have said "between type arguments"

  Scenario: A group with nothing in it
    Given there is a source "src/Todo.phunkie" containing:
      """
      function doubleAll(ImmList<> $numbers): int
      {
          return 1;
      }
      """
    When I check "src"
    Then it should have failed
    And it should have said "found an empty group"

  Scenario: A function that answers with nothing
    Given there is a source "src/Todo.phunkie" containing:
      """
      function keep((string) => $matches): int
      {
          return 1;
      }
      """
    When I check "src"
    Then it should have failed
    And it should have said "Expected a type"

  # The mistake is reported once, in the language it was made in. Handing the
  # rest to PHP as well would say it again in PHP's terms, and that second
  # message is the one nobody can act on.
  Scenario: The notation is reported once, not twice
    Given there is a source "src/Todo.phunkie" containing:
      """
      function names(array<int User> $users): int
      {
          return 1;
      }
      """
    When I check "src" as json
    Then it should have failed
    And it should have emitted one diagnostic for "src/Todo.phunkie"
