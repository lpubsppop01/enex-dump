<?php

// This exex-dump is modified-version by lpubsppop01.
// The following is the original header.

//
// enex-dump by Steven Frank (@stevenf) <http://stevenf.com/>
//
// This script takes an Evernote export (ENEX) file as input
// and exports each individual note as a plain-text file in the
// specified output folder.
//
// All HTML formatting and attachments are stripped out.
//
// The output files are named after the title of the note.
//
// The title of the note is also included as the first line of
// the exported file.
//
// Script will attempt to create the output folder if it doesn't exist.
//
// Configure the variables below before running. Default paths are
// relative to current directory.
//
// Invoke like so:
//
// php enex-dump.php
//
// By default, we look for an input file named "My Notes.enex",
// but you can supply an additional parameter to override this:
//
// php enex-dump.php allnotes.enex
//

require 'vendor/autoload.php';

if ( $argc > 1 ) 
{
	$file = $argv[1];
} 
else 
{
	$file = "My Notes.enex"; // Path of default input file
}

$outdir = "output"; // Path of output folder
$ext = "txt"; // Extension to use for exported notes

//

$pos = 0;
$nodes = array();

@mkdir($outdir);

if ( !($fp = fopen($file, "r")) )
{
	die("could not open XML input");
}

while ( $getline = fread($fp, 4096) )
{
	$data = $data . $getline;
}

$count = 0;
$pos = 0;

while ( $node = getElementByName($data, "<note>", "</note>") )
{
	$nodes[$count] = $node;
	$count++;
	$data = substr($data, $pos);
}

for ( $i = 0; $i < $count; $i++)
{
	$title = cleanup(getElementByName($nodes[$i], "<title>", "</title>"));
	$content = parseContent(getElementByName($nodes[$i], "<content>", "</content>"));

	// Obtain note creation and update timestamp
	$created = cleanup(getElementByName($nodes[$i], "<created>", "</created>"));
	$updated = cleanup(getElementByName($nodes[$i], "<updated>", "</updated>"));

	// Create content header and footer
	$header = createContentHeader($title);
	$footer = createContentFooter($created);

	// sanitize the special charactors in titles for filenames
	$charsToReplace = ['\\', '/', ':', '*', '?', '"', '<', '>', '|'];
	$outfile = sprintf('%s/%s.%s', $outdir, str_replace($charsToReplace, '-', $title), $ext);

	// echo the filename
	echo $outfile . PHP_EOL;

	file_put_contents($outfile, $header . $content . $footer);
	touch($outfile, strtotime($updated)); // Change output file timestamp to match note creation timestamp
}

exit;


function getElementByName($xml, $startPattern, $end, $usesPregOnStart = false)
{
	global $pos;

	$start = "";
	$startpos = false;
	if ($usesPregOnStart) {
		if (preg_match($startPattern, $xml, $matches, PREG_OFFSET_CAPTURE)) {
			$start = $matches[0][0];
			$startpos = $matches[0][1];
		}
	} else {
		$start = $startPattern;
		$startpos = strpos($xml, $start);
	}

	if ( $startpos === false )
	{
		return false;
	}

	$endpos = strpos($xml, $end);
	$endpos = $endpos + strlen($end);
	$pos = $endpos;
	$endpos = $endpos - $startpos;
	$endpos = $endpos - strlen($end);
	$tag = substr($xml, $startpos, $endpos);
	$tag = substr($tag, strlen($start));

	return $tag;
}

function cleanup($str)
{
	$str = strip_tags($str);
	$str = preg_replace('/\]\]>$/', '', $str);
	$str = trim($str);
	$str = html_entity_decode($str);

	return $str;
}

function parseContent($str)
{
	// cf. soundasleep/html2text creates Markdown style links
	$workStr = getElementByName($str, '/<en-note[^>]*>/', "</en-note>", $usesPregOnStart = true);
	$workStr = str_replace('<en-todo checked="true"/>', '- [x] ', $workStr);
	$workStr = str_replace('<en-todo checked="false"/>', '- [ ] ', $workStr);
	$workStr = preg_replace('/<en-media [^>]*\/>/', '', $workStr);
	$workStr = "<html>" . $workStr . "</html>";
	return \Html2Text\Html2Text::convert($workStr);
}

function createContentHeader($title)
{
	$header = <<<EOD
# $title


EOD;
	return $header;
}

function createContentFooter($created)
{
	$createdStr = convertTimestamp($created);
	$footer = <<<EOD


------------------------------------------------------------------------

Converted from Evernote content created at $createdStr
EOD;
	return $footer;
}

function convertTimestamp($enTimestamp)
{
	$dateTime = new DateTime($enTimestamp);
	$dateTime->setTimezone(new DateTimeZone(date_default_timezone_get()));
	$str = $dateTime->format(DateTime::ATOM);
	return $str;
}

