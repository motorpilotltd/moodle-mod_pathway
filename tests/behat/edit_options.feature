@mod @mod_pathway
Feature: Manage the options of a pathway activity
  In order to offer the right routes
  As a teacher
  I need to edit the option list on the settings form

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | course | name              | idnumber | options   |
      | pathway  | C1     | Choose your route | PW1      | Red, Blue |

  Scenario: The settings form shows the existing options
    Given I am on the "PW1" "Activity editing" page logged in as "teacher1"
    Then the field "Option 1" matches value "Red"
    And the field "Option 2" matches value "Blue"

  Scenario: A teacher adds a new option
    Given I am on the "PW1" "Activity editing" page logged in as "teacher1"
    When I set the field "Option 3" to "Green"
    And I press "Save and display"
    Then I should see "Green"

  Scenario: A teacher renames an option in place
    Given I am on the "PW1" "Activity editing" page logged in as "teacher1"
    When I set the field "Option 1" to "Crimson"
    And I press "Save and display"
    Then I should see "Crimson"
    And I should not see "Red"
