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

$parseImportResultMessages = static function (array $messages, string $mainBlockName, string $itemBlockName, XTemplate $template) {
    $mainItemBlockName = "$mainBlockName.$itemBlockName";
    foreach ($messages as $activityDescription => $singleActivityMessages) {
        if (count($singleActivityMessages) > 1) {
            if ($activityDescription) {
                $template->assign('nadpis', $activityDescription);
                $template->parse("$mainItemBlockName.nadpis");
            }
            $template->parseEach($singleActivityMessages, 'message', "$mainItemBlockName.message");
        } else {
            $message = reset($singleActivityMessages);
            $message = $activityDescription . ': ' . $message;
            $template->assign('message', $message);
            $template->parse("$mainItemBlockName.message");
        }
        $template->parse($mainItemBlockName);
    }
    $template->parse($mainBlockName);
};

if ($errorMessages) {
    $parseImportResultMessages($errorMessages, 'import.errors', 'error', $template);
}

if ($warningMessages) {
    $parseImportResultMessages($warningMessages, 'import.warnings', 'warning', $template);
}

if ($successMessages) {
    $parseImportResultMessages($successMessages, 'import.successes', 'success', $template);
}
