@core @core_group
Feature: Hide suspended users from groups creation
  In order to not have inactive users in my groups
  As a teacher
  I need to not see them when I create groups

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1 | topics |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@asd.com |
      | activestudent | Active | Student | activestudent@asd.com |
      | suspendedstudent | Suspended | Student | suspendedstudent@asd.com |
    And the following "course enrolments" exist:
      | user | course | role | status |
      | teacher1 | C1 | editingteacher | 0 |
      | activestudent | C1 | student | 0 |
      | suspendedstudent | C1 | student | 1 |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I expand "Users" node
    And I follow "Groups"

  Scenario: No suspended users in auto-create groups
    Given I press "Auto-create groups"
    When I set the field "Group/member count" to "1"
    And I press "Preview"
    Then I should see "Active Student"
    And I should not see "Suspended Student"

  Scenario: No suspended users in user select
    Given I press "Create group"
    And I set the field "Group name" to "Group 1"
    And I press "Save changes"
    When I press "Add/remove users"
    Then the "addselect" select box should contain "Teacher 1 (teacher1@asd.com) (0)" 
    And the "addselect" select box should contain "Active Student (activestudent@asd.com) (0)"
    And the "addselect" select box should not contain "Suspended Student (suspendedstudent@asd.com) (0)"