@mod @mod_pathway
Feature: Choose a pathway option directly from the course page
  In order to pick a route without opening the activity
  As a student
  I need the options shown inline on the course page

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | course | name        | idnumber | options      | displaymode | allowupdate |
      | pathway  | C1     | Pick a track | PT1     | Alpha, Beta  | 1           | 1           |
      | pathway  | C1     | Final track  | PT2     | One, Two     | 1           | 0           |
      | pathway  | C1     | Tile track   | PT3     | Gamma, Delta | 2           | 1           |

  Scenario: A student chooses from the option buttons on the course page
    Given I am on the "C1" "Course" page logged in as "student1"
    When I press "Alpha"
    Then I should see "Your choice has been saved."
    And I should see "Pick a track"
    When I press "Beta"
    Then I should see "Your choice has been saved."

  Scenario: A final choice from the course page asks for confirmation first
    Given I am on the "C1" "Course" page logged in as "student1"
    When I press "One"
    Then I should see "Are you sure you want to choose \"One\"? This choice cannot be changed afterwards."
    When I press "Continue"
    Then I should see "Your choice has been saved."
    And the "One" "button" should be disabled
    And the "Two" "button" should be disabled

  Scenario: A student chooses from image tiles on the course page
    Given I am on the "C1" "Course" page logged in as "student1"
    When I press "Gamma"
    Then I should see "Your choice has been saved."
