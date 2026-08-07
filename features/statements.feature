Feature: Developer is told what is wrong with a comprehension, where they wrote it

  # A comprehension is not PHP, so until the grammar reads one the only thing
  # that ever complained was PHP's parser, looking at the file the macros had
  # rewritten. It reported line 3 of a source two lines long, because that is
  # where the mistake had moved to.
  Scenario: A comprehension that is read is not complained about
    Given there is a source "src/Todo.phunkie" containing:
      """
      $r = for {
          $a <- Some(1)
      } yield $a;
      """
    When I check "src"
    Then it should have passed

  Scenario: A generator that binds nothing
    Given there is a source "src/Todo.phunkie" containing:
      """
      $r = for {
          <- Some(1)
      } yield $a;
      """
    When I check "src"
    Then it should have failed
    And it should have said "Expected a variable to bind"

  Scenario: A comprehension with nothing to yield
    Given there is a source "src/Todo.phunkie" containing:
      """
      $r = for {
          $a <- Some(1)
      } yield;
      """
    When I check "src"
    Then it should have failed
    And it should have said "Expected something to yield"

  Scenario: A comprehension that never closes
    Given there is a source "src/Todo.phunkie" containing:
      """
      $r = for {
          $a <- Some(1)
      yield $a;
      """
    When I check "src"
    Then it should have failed
    And it should have said "Expected \"}\" to close the generators"

  # The whole point of the milestone. The line is the line in the file that was
  # written, not in the one the macros produced.
  Scenario: The mistake is named where the reader put it
    Given there is a source "src/Todo.phunkie" containing:
      """
      $ok = 1;
      $r = for {
          <- Some(1)
      } yield $a;
      """
    When I check "src"
    Then it should have failed
    And it should have said "Todo.phunkie:3"
