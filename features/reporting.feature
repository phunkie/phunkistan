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
