@mod @mod_pathway
Feature: Record a choice in a pathway activity
  In order to follow a route through a course
  As a student
  I need to record my choice in a pathway activity

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | course | name              | idnumber | options     | allowupdate | showresults |
      | pathway  | C1     | Choose your route | PW1      | Red, Blue   | 1           | 0           |
      | pathway  | C1     | One way door      | PW2      | Left, Right | 0           | 0           |
      | pathway  | C1     | Poll route        | PW3      | Cats, Dogs  | 1           | 1           |
      | pathway  | C1     | Empty route       | PW4      |             | 1           | 0           |

  Scenario: A student makes and then changes their choice
    Given I am on the "PW1" "Activity" page logged in as "student1"
    When I set the field "Red" to "1"
    And I press "Save choice"
    Then I should see "Your choice has been saved."
    And I should see "Your current choice is: Red"
    When I set the field "Blue" to "1"
    And I press "Save choice"
    Then I should see "Your current choice is: Blue"

  Scenario: A final choice cannot be changed afterwards
    Given I am on the "PW2" "Activity" page logged in as "student1"
    When I set the field "Left" to "1"
    And I press "Save choice"
    Then I should see "Your choice has been saved."
    And I should see "Your current choice is: Left"
    And I should see "Your choice has been recorded and cannot be changed."
    And "Save choice" "button" should not exist

  Scenario: Response counts are shown when the summary is enabled
    Given I am on the "PW3" "Activity" page logged in as "student1"
    When I set the field "Cats" to "1"
    And I press "Save choice"
    Then I should see "Responses"
    And I should see "1" in the "Cats" "table_row"
    And I should see "0" in the "Dogs" "table_row"

  Scenario: An activity with no options explains itself
    Given I am on the "PW4" "Activity" page logged in as "student1"
    Then I should see "No options have been defined for this activity yet."

  Scenario: A teacher without the choose capability sees the responses instead
    Given I am on the "PW1" "Activity" page logged in as "teacher1"
    Then I should see "You do not have permission to make a choice here."
    And I should see "Responses"
    And "Save choice" "button" should not exist

  Scenario: Choosing an option joins its mapped course group
    Given the following "groups" exist:
      | name    | course | idnumber |
      | Group A | C1     | GA       |
      | Group B | C1     | GB       |
    And the following "mod_pathway > options" exist:
      | pathway     | text       | group |
      | Empty route | Left path  | GA    |
      | Empty route | Right path | GB    |
    And I am on the "PW4" "Activity" page logged in as "student1"
    When I set the field "Left path" to "1"
    And I press "Save choice"
    Then I should see "Your current choice is: Left path"
    When I am on the "C1" "enrolled users" page logged in as "teacher1"
    Then I should see "Group A" in the "Sam Student" "table_row"
