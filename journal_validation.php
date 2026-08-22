<?php
// journal_validation.php
// Shared validation logic for adding and editing Diary Journal entries.
// Used by add_journal.php and edit_journal.php to avoid duplicating the same checks.

define('JOURNAL_ALLOWED_MOODS', ['Happy', 'Excited', 'Neutral', 'Sad', 'Anxious']);
define('JOURNAL_TITLE_MAX_LENGTH', 100);   // matches journal_entries.title VARCHAR(100)
define('JOURNAL_CONTENT_MAX_LENGTH', 5000); // sanity cap; column is TEXT

/**
 * Validates journal entry input.
 *
 * @param string $title
 * @param string $content
 * @param string $mood
 * @param string $date
 * @return array List of human-readable error messages. Empty array = valid input.
 */
function validateJournalEntry($title, $content, $mood, $date)
{
    $errors = [];

    if ($title === '' || $content === '' || $mood === '' || $date === '') {
        $errors[] = "All fields are required.";
        return $errors; // no point checking format rules on missing input
    }

    if (mb_strlen($title) > JOURNAL_TITLE_MAX_LENGTH) {
        $errors[] = "Title cannot exceed " . JOURNAL_TITLE_MAX_LENGTH . " characters.";
    }

    if (mb_strlen($content) > JOURNAL_CONTENT_MAX_LENGTH) {
        $errors[] = "Content cannot exceed " . JOURNAL_CONTENT_MAX_LENGTH . " characters.";
    }

    if (!in_array($mood, JOURNAL_ALLOWED_MOODS, true)) {
        $errors[] = "Please select a valid mood.";
    }

    // Reject malformed or non-existent dates (e.g. 2026-02-31) instead of
    // trusting the browser's date picker, which does nothing for direct
    // POST requests made outside the form.
    $parsedDate = DateTime::createFromFormat('Y-m-d', $date);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
        $errors[] = "Please enter a valid date.";
    }

    return $errors;
}
