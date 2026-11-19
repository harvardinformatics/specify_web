<?php
/*
 * Minimum length for search terms, shared by the specimen, botanist and publication searches.
 *
 * Each checked search field needs at least SEARCH_MIN_CHARS characters, not counting wildcards (* % _)
 * or whitespace.  The search forms apply the same rule in javascript, see js/search_validation.js.
 */

define('SEARCH_MIN_CHARS', 2);

/**
 * Number of characters in a search term, not counting wildcards (* % _) or whitespace.
 *
 * @param string $value the search term.
 * @param string $ignore any other characters that should not be counted, e.g. '"' for phrase quotes.
 */
function search_term_length($value, $ignore = '') {
	$value = preg_replace('/[\s*%_' . preg_quote($ignore, '/') . ']+/', '', (string)$value);
	return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

/**
 * Message shown when search fields have too few characters.
 *
 * @param array $labels the names of the fields that are too short, as shown on the search form.
 * @param string $returnpage page with the search form to link back to.
 */
function search_term_warning($labels, $returnpage) {
	$list = htmlspecialchars(implode(", ", $labels), ENT_QUOTES, 'UTF-8');
	$returnpage = htmlspecialchars($returnpage, ENT_QUOTES, 'UTF-8');
	return "<div class='search-warning' role='alert'>Please enter at least " . SEARCH_MIN_CHARS .
		" characters, not counting wildcards, in: <strong>$list</strong>. " .
		"<a href='$returnpage'>Return to the search form</a>.</div>\n";
}

?>
