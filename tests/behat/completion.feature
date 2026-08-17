@mod @mod_pathway
Feature: Pathway activity completion by making a choice
  In order to gate later activities on a route being chosen
  As a teacher
  I need the choice to satisfy an automatic completion condition

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | course | name              | idnumber | options   | completion | completionchoice |
      | pathway  | C1     | Choose your route | PW1      | Red, Blue | 2          | 1                |

  @javascript
  Scenario: Making a choice completes the activity
    Given I am on the "C1" "Course" page logged in as "student1"
    Then the "Make a choice" completion condition of "Choose your route" is displayed as "todo"
    When I am on the "PW1" "Activity" page
    And I set the field "Red" to "1"
    And I press "Save choice"
    And I am on the "C1" "Course" page
    Then the "Make a choice" completion condition of "Choose your route" is displayed as "done"
