Feature: Developer is told what is wrong with a source, and where

  Scenario: A source with nothing wrong with it is accepted quietly
    Given there is a source "src/Todo.phunkie" containing:
      """
      function name(): string
      {
          return "ada";
      }
      """
    When I check "src"
    Then it should have passed
    And it should have said nothing

  # The compiler's answer to notation it does not recognise is to pass it
  # through, so a coverage gap and ordinary PHP look identical to it. Being told
  # where the reader lost the thread is the whole point of this tool.
  Scenario: A source that does not parse is reported where it broke
    Given there is a source "src/Todo.phunkie" containing:
      """
      function name(): string
      {
          $todo = ;

          return "ada";
      }
      """
    When I check "src"
    Then it should have failed
    And it should have reported "src/Todo.phunkie" at line 3
    And it should have shown me the line I wrote

  # The same finding, for an editor rather than a terminal. An extension hands
  # this straight to a language client, so the shape is theirs and not ours.
  Scenario: The same failure is available for an editor to read
    Given there is a source "src/Todo.phunkie" containing:
      """
      function name(): string
      {
          $todo = ;
      }
      """
    When I check "src" as json
    Then it should have failed
    And it should have emitted one diagnostic for "src/Todo.phunkie"
