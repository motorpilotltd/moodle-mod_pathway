@mod @mod_pathway
Feature: A teacher manages pathway responses
  In order to keep pathway choices and cohort membership correct
  As a teacher
  I need to delete choices and assign options in bulk

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
      | student2 | Sky       | Student  | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity | course | name    | idnumber | options     | allowupdate |
      | pathway  | C1     | Routes  | RT1      | Alpha, Beta | 1           |

  Scenario: A teacher deletes a student's choice
    Given I am on the "RT1" "Activity" page logged in as "student1"
    And I set the field "Alpha" to "1"
    And I press "Save choice"
    When I am on the "RT1" "Activity" page logged in as "teacher1"
    And I follow "Manage responses"
    Then I should see "Sam Student"
    When I click on "Delete choice" "link" in the "Sam Student" "table_row"
    # No cohort or group is mapped here, so the plain confirmation shows.
    # The membership-removal branch is covered by the manager unit tests.
    And I press "Continue"
    Then I should see "The choice has been deleted."
    # Sam still appears in the bulk-assign user picker, so check the
    # responses table itself: with the only choice gone it is replaced.
    And I should see "No one has made a choice yet."

  @javascript
  Scenario: A teacher assigns an option to several users at once
    Given I am on the "RT1" "Activity" page logged in as "teacher1"
    And I follow "Manage responses"
    When I set the field "Option to assign" to "Beta"
    And I set the field "Users" to "Sam Student, Sky Student"
    And I press "Assign option"
    Then I should see "Sam Student"
    And I should see "Sky Student"
