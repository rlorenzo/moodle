@tool @tool_uploadcourse @_file_upload
Feature: An admin can create and update existing users using a CSV file
  In order to create/update users using a CSV file
  As an admin
  I need to be able to upload a CSV file and navigate through the import process

  Background:
    Given I log in as "admin"
    And I navigate to "Upload users" node in "Site administration > Users > Accounts"
