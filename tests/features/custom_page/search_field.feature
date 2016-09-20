@api
Feature:
  In order to make it easy to browse to specific content
  As a moderator
  I need to be able to configure search options in a custom page

  Background:
    Given the following collections:
      | title      | logo     | banner     |
      | Nintendo64 | logo.png | banner.jpg |
      | Emulators  | logo.png | banner.jpg |
    And news content:
      | title                                 | collection | content                          |
      | Rare Nintendo64 disk drive discovered | Nintendo64 | Magnetic drive called 64DD.      |
      | NEC VR4300 CPU                        | Emulators  | Update of the emulation library. |
    And event content:
      | title               | collection | body                                        |
      | 20 year anniversary | Nintendo64 | The console was released in September 1996. |
    # Non UATable step.
    And I commit the solr index

    Scenario: Search field widget should be shown only to moderators
      Given I am logged in as a facilitator of the "Nintendo64" collection
      When I go to the homepage of the "Nintendo64" collection
      And I click "Add custom page"
      Then I should see the heading "Add custom page"
      And the following fields should not be present "Enable the search field, Query presets, Limit"

      Given I am logged in as a moderator
      When I go to the homepage of the "Nintendo64" collection
      And I click "Add custom page"
      Then I should see the heading "Add custom page"
      And the following fields should be present "Enable the search field, Query presets, Limit"

    Scenario: Configure a custom page to show only news of its collection
      Given I am logged in as a moderator
      When I go to the homepage of the "Nintendo64" collection
      And I click "Add custom page"
      Then I should see the heading "Add custom page"
      When I fill in the following:
        | Title | Latest news                        |
        | Body  | Shows all news for this collection |
      And I check "Enable the search field"
      And I fill in "Query presets" with "aggregated_field|news"
      And I press "Save"
      Then I should see the heading "Latest news"
      And I should see the "Rare Nintendo64 disk drive discovered" tile
      # I should not see content that is not a discussion.
      And I should not see the text "20 year anniversary"
      # I should not see the discussions of another collection.
      But I should not see the text "NEC VR4300 CPU"
