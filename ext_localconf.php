<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['wt_cart']['addAttachment'][] =
	'EXT:' . $_EXTKEY . '/Classes/Hooks/Render.php:Tx_WtCartPdf_Hooks_Render->createPdfs';

?>