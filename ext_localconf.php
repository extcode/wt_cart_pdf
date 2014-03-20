<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

$signalSlotDispatcher = t3lib_div::makeInstance('Tx_Extbase_SignalSlot_Dispatcher');
$signalSlotDispatcher->connect('Tx_WtCartOrder_Hooks_OrderHook', 'slotAfterSaveOrderNumberToOrderItem', 'Tx_WtCartPdf_Utility_Renderer', 'createPdf', FALSE);
$signalSlotDispatcher->connect('Tx_WtCartOrder_Hooks_OrderHook', 'slotAfterSaveInvoiceNumberToOrderItem', 'Tx_WtCartPdf_Utility_Renderer', 'createPdf', FALSE);

?>