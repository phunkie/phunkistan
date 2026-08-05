Feature: Developer is told as soon as a source stops making sense

  Scenario: What is already there is checked before the watch begins
    Given there is a source "src/Todo.phunkie" containing "$todo = ;"
    When the checker starts watching "src"
    Then the watch log should eventually contain "src/Todo.phunkie:1:9"

  Scenario: Saving a source checks it again
    Given there is a source "src/Todo.phunkie" containing "$todo = 1;"
    And the checker is watching "src"
    When I save "src/Todo.phunkie" containing "$todo = ;"
    Then the watch log should eventually contain "src/Todo.phunkie:1:9"

  # A watch says so either way. Silence is right for a one shot run, where no
  # news is good news, and wrong here, where it is indistinguishable from a
  # watcher that has died.
  Scenario: Fixing a source says so
    Given there is a source "src/Todo.phunkie" containing "$todo = ;"
    And the checker is watching "src"
    When I save "src/Todo.phunkie" containing "$todo = 1;"
    Then the watch log should eventually contain "No problems found"

  Scenario: A single file can be watched on its own
    Given there is a source "src/Todo.phunkie" containing "$todo = 1;"
    And the checker is watching "src/Todo.phunkie"
    When I save "src/Todo.phunkie" containing "$todo = ;"
    Then the watch log should eventually contain "src/Todo.phunkie:1:9"

  # Two saves inside the same second are indistinguishable by modification
  # time, which has one second resolution, so the second one would be missed.
  Scenario: A second save in the same second is noticed
    Given there is a source "src/Todo.phunkie" containing "$todo = 1;"
    And the checker is watching "src"
    When I save "src/Todo.phunkie" containing "$todo = 2;"
    And I save "src/Todo.phunkie" containing "$todo = ;"
    Then the watch log should eventually contain "src/Todo.phunkie:1:9"
