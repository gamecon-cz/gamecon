<?php
/** @var \Gamecon\Admin\Modules\Aktivity\Import\ActivitiesImportResult $vysledekImportuAktivit */
/** @var XTemplate $template */

$naimportovanoPocet = $vysledekImportuAktivit->getImportedCount();
$nazevImportovanehoSouboru = $vysledekImportuAktivit->getProcessedFilename();
$errorMessages = $vysledekImportuAktivit->getErrorMessages();
$warningMessages = $vysledekImportuAktivit->getErrorLikeAndWarningMessagesExceptErrored();
$successMessages = $vysledekImportuAktivit->getSuccessMessages();

$zprava = sprintf("Bylo naimportováno %d aktivit z Google sheet '%s'", $naimportovanoPocet, $nazevImportovanehoSouboru);
if ($naimportovanoPocet > 0) {
    oznameni($zprava, false);
} else {
    chyba($zprava, false);
}
$oznameni = \Chyba::vyzvedniHtml();
$template->assign('oznameni', $oznameni);
$template->parse('import.oznameni');

$parseImportResultMessages = static function (array $messages, string $mainBlockName, XTemplate $template) {
    $parseMessage = static function (string $message, string $mainBlockName) use ($template) {
        $template->assign('message', $message);
        $template->parse("$mainBlockName.message");
    };
    foreach ($messages as $activityDescription => $singleActivityMessages) {
        if (count($singleActivityMessages) > 1) {
            if ($activityDescription) {
                $template->assign('nadpis', $activityDescription);
                $template->parse("$mainBlockName.nadpis");
            }
            foreach ($singleActivityMessages as $message) {
                $parseMessage($message, $mainBlockName);
            }
        } else {
            $message = reset($singleActivityMessages);
            $message = $activityDescription . ': ' . $message;
            $parseMessage($message, $mainBlockName);
        }
    }
    $template->parse($mainBlockName);
};

if ($errorMessages) {
    $parseImportResultMessages($errorMessages, 'import.errors', $template);
}

if ($warningMessages) {
    $parseImportResultMessages($warningMessages, 'import.warnings', $template);
}

if ($successMessages) {
    $parseImportResultMessages($successMessages, 'import.successes', $template);
}
