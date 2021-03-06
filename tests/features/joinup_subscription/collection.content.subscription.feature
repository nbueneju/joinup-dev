@api
Feature: Subscribing to community content in collections
  As a member of a collection
  I want to receive a periodic digest listing newly published content
  So that I can stay informed

  Background:
    Given the following collections:
      | title                | state     |
      | Products of Bulgaria | validated |
      | Cities of Bulgaria   | validated |
    And users:
      | Username | E-mail            | First name | Family name  | Notification frequency |
      | hristo   | hristo@example.bg | Hristo     | Draganov     | daily                  |
      | bisera   | bisera@example.bg | Bisera     | Kaloyancheva | weekly                 |
      | kalin    | kalin@primer.bg   | Kalin      | Antov        | monthly                |
    And the following collection user memberships:
      | collection           | user   | roles       |
      | Products of Bulgaria | hristo |             |
      | Products of Bulgaria | bisera |             |
      | Products of Bulgaria | kalin  |             |
      | Cities of Bulgaria   | hristo |             |
      | Cities of Bulgaria   | bisera |             |
      | Cities of Bulgaria   | kalin  | facilitator |
    And the following collection content subscriptions:
      | collection           | user   | subscriptions              |
      | Products of Bulgaria | hristo | discussion, event, news    |
      | Products of Bulgaria | bisera | discussion, document, news |
      | Products of Bulgaria | kalin  | document, event            |
      | Cities of Bulgaria   | hristo | document, event            |
      | Cities of Bulgaria   | bisera | discussion, event, news    |
      | Cities of Bulgaria   | kalin  | discussion, document, news |
    And all message digests have been delivered
    And the mail collector cache is empty

  @email
  Scenario: Receive a digest of content that is published in my collections
    Given discussion content:
      | title      | body                      | collection           | state     | author |
      | Duck liver | Rich buttery and delicate | Products of Bulgaria | validated | hristo |
      | Sofia      | Grows without aging       | Cities of Bulgaria   | validated | kalin  |
      | Ruse       | Little Vienna             | Cities of Bulgaria   | proposed  | kalin  |
    And document content:
      | title           | body                   | collection           | state     | author |
      | Canned cherries | Sour cherries for pies | Products of Bulgaria | validated | bisera |
      | Plovdiv         | Seven hills            | Cities of Bulgaria   | validated | hristo |
    And event content:
      | title           | body           | collection           | state     | author | start date          | end date            |
      | Sunflower seeds | A tasty snack  | Products of Bulgaria | validated | bisera | 2019-11-28T11:12:13 | 2019-11-28T11:12:13 |
      | Varna           | Summer capital | Cities of Bulgaria   | draft     | kalin  | 2019-12-05T12:00:00 | 2019-12-15T12:00:00 |
      | Stara Zagora    | Historic       | Cities of Bulgaria   | validated | hristo | 2020-01-18T18:30:00 | 2020-01-19T00:00:00 |
    And news content:
      | title    | body                        | collection           | state     | author |
      | Rose oil | A widely used essential oil | Products of Bulgaria | validated | bisera |
      | Burgas   | City of dreams              | Cities of Bulgaria   | validated | hristo |

    Then the daily digest for hristo should contain the following message:
      | mail_body | Duck liver |
    And the daily digest for hristo should contain the following message:
      | mail_body | Sunflower seeds |
    And the daily digest for hristo should contain the following message:
      | mail_body | Rose oil |
    And the daily digest for hristo should contain the following message:
      | mail_body | Plovdiv |
    And the daily digest for hristo should contain the following message:
      | mail_body | Stara Zagora |
    And the weekly digest for bisera should contain the following message:
      | mail_body | Duck liver |
    And the weekly digest for bisera should contain the following message:
      | mail_body | Canned cherries |
    And the weekly digest for bisera should contain the following message:
      | mail_body | Rose oil |
    And the weekly digest for bisera should contain the following message:
      | mail_body | Sofia |
    And the weekly digest for bisera should contain the following message:
      | mail_body | Stara Zagora |
    And the weekly digest for bisera should contain the following message:
      | mail_body | Burgas |
    And the monthly digest for kalin should contain the following message:
      | mail_body | Canned cherries |
    And the monthly digest for kalin should contain the following message:
      | mail_body | Sunflower seeds |
    And the monthly digest for kalin should contain the following message:
      | mail_body | Sofia |
    And the monthly digest for kalin should contain the following message:
      | mail_body | Plovdiv |
    And the monthly digest for kalin should contain the following message:
      | mail_body | Burgas |

    # Check that only the user's chosen frequency is digested.
    But the weekly digest for hristo should not contain any messages
    And the monthly digest for hristo should not contain any messages
    And the daily digest for bisera should not contain any messages
    And the monthly digest for bisera should not contain any messages
    And the daily digest for kalin should not contain any messages
    And the weekly digest for kalin should not contain any messages

    # The digest should not include news about content that is not published.
    And the weekly digest for bisera should not contain the following message:
      | mail_body | Ruse |
    And the monthly digest for kalin should not contain the following message:
      | mail_body | Ruse |

    # Publish an existing unpublished community content. It should be included
    # in the next digest.
    When the workflow state of the "Ruse" content is changed to "validated"

    Then the weekly digest for bisera should contain the following message:
      | mail_body | Ruse |
    And the monthly digest for kalin should contain the following message:
      | mail_body | Ruse |

    # Check that the messages are formatted correctly.
    Given all message digests have been delivered
    Then the collection content subscription digest email sent to hristo contains the following sections:
      | title                |
      | Cities of Bulgaria   |
      | Plovdiv              |
      | Stara Zagora         |
      | Products of Bulgaria |
      | Duck liver           |
      | Rose oil             |
      | Sunflower seeds      |
    And the collection content subscription digest email sent to hristo should have the subject "Joinup: Daily digest message"

    And the collection content subscription digest email sent to bisera contains the following sections:
      | title                |
      | Cities of Bulgaria   |
      | Burgas               |
      | Sofia                |
      | Stara Zagora         |
      | Products of Bulgaria |
      | Canned cherries      |
      | Rose oil             |
    And the collection content subscription digest email sent to bisera should have the subject "Joinup: Weekly digest message"

    And the collection content subscription digest email sent to kalin contains the following sections:
      | title                |
      | Cities of Bulgaria   |
      | Burgas               |
      | Plovdiv              |
      | Sofia                |
      | Products of Bulgaria |
      | Canned cherries      |
      | Sunflower seeds      |
    And the collection content subscription digest email sent to kalin should have the subject "Joinup: Monthly digest message"

    # Clean out the message queue for the next test.
    And the mail collector cache is empty

    # Check that if community content is published a second time it is not
    # included in the next digest.
    When the workflow state of the "Ruse" content is changed to "draft"
    Then the weekly digest for bisera should not contain the following message:
      | mail_body | Ruse |
    And the monthly digest for kalin should not contain the following message:
      | mail_body | Ruse |
    When the workflow state of the "Ruse" content is changed to "validated"
    Then the weekly digest for bisera should not contain the following message:
      | mail_body | Ruse |
    And the monthly digest for kalin should not contain the following message:
      | mail_body | Ruse |
