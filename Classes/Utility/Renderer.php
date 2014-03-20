<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Daniel Lorenz <wt-cart-pdf@extco.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

define('TYPO3_DLOG', $GLOBALS['TYPO3_CONF_VARS']['SYS']['enable_DLOG']);

if (t3lib_extMgm::isLoaded('extcode_tcpdf')) {
	require(t3lib_extMgm::extPath('extcode_tcpdf').'class.tx_extcode_tcpdf.php');
}
if (t3lib_extMgm::isLoaded('extcode_fpdi')) {
	require(t3lib_extMgm::extPath('extcode_fpdi').'class.tx_extcode_fpdi.php');
}

/**
 * @property mixed pdf
 */
class Tx_WtCartPdf_Utility_Renderer {

	public $extKey = 'wt_cart_pdf';

	/**
	 * AbortOnError?
	 *
	 * @var boolean
	 */
	protected $abortOnError = FALSE;

	/**
	 * PDF Path
	 *
	 * @var string
	 */
	protected $pdf_path = '';

	/**
	 * PDF Filename
	 *
	 * @var string
	 */
	protected $pdf_filename = '';

	/**
	 * orderItem
	 *
	 * @var Tx_WtCartOrder_Domain_Model_OrderItem
	 */
	protected $orderItem;

	/**
	 *
	 */
	protected $pdf;

	/**
	 * @param array $params
	 * @return int
	 */
	public function createPdf( $params ) {
		t3lib_div::devLog( 'createPdf', 'wt_cart_pdf', 0, $params );

		$this->orderItem = $params['orderItem'];
		$type = $params['type'];

		$this->conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_wtcart_pdf.'][$type . '.'];
		$this->abortOnError = $this->conf['abortOnError'];

		$this->getPath( );
		$this->getFilename( $type );

		if ( ! $this->pdfDirExists() ) {
			return 1;
		}
		if ( $this->pdfFileExists('before') ) {
			return 0;
		}

		$this->renderPdf($params, $type);

		if ( ! $this->pdfFileExists('after') ) {
			return 1;
		}

		$params['files'][ $type ] = $this->pdf_path . '/' . $this->pdf_filename;

		return 0;
	}

	/**
	 * @param string $type
	 * @return bool
	 */
	private function pdfFileExists($type = 'before') {
		if ( file_exists( PATH_site . $this->pdf_path . '/' . $this->pdf_filename ) ) {

			if ($type == 'after') {
				$error = 'file after rendering does not exists';
				$session['error'][] = $error;
			}

			if (TYPO3_DLOG) {
				if ($type == 'after') {
					t3lib_div::devLog($error, $this->extKey, 0, $session['error']);
				} else {
					t3lib_div::devLog('pdf file already exists', $this->extKey, 0);
				}
			}

			return TRUE;
		}

		return FALSE;
	}


	/**
	 * @param $params
	 * @param string $type
	 * @return int
	 */
	private function renderPdf(&$params, $type) {
		$this->pdf = new FPDI();
		$this->pdf->AddPage();

		if ($this->conf['include_pdf']) {
			$templatePath = t3lib_div::getFileAbsFileName( $this->conf['include_pdf'] );
			$this->pdf->setSourceFile( $templatePath );
			$tplIdx = $this->pdf->importPage(1);
			$this->pdf->useTemplate($tplIdx, 0, 0, 210);
		}

		if ($this->conf['font']) {
			$font = $this->conf['font'] ;
		} else {
			$font = 'Helvetica';
		}
		if ($this->conf['fontStyle']) {
			$fontStyle = $this->conf['fontStyle'] ;
		} else {
			$fontStyle = '';
		}
		if ($this->conf['fontSize']) {
			$fontSize = $this->conf['fontSize'] ;
		} else {
			$fontSize = 8;
		}

		$this->pdf->SetFont( $font, $fontStyle, $fontSize );

		$this->renderAddress();
		$this->renderSubject();
		$this->renderAdditionalTextblocks();

		$this->renderCart();

		$this->renderPaymentOptions();

		$this->pdf->Output( PATH_site . $this->pdf_path . '/' . $this->pdf_filename, 'F' );
	}

	/**
	 *
	 */
	private function renderCart() {
		$renderer = $this->getOrderItemRenderer( 'OrderItem/ShowPdf.html' );
		// assign the data to it
		$renderer->assign('orderItem', $this->orderItem);
		// and do the rendering magic
		$html = $renderer->render();

		if ( $html ) {
			$positionX = $this->conf['cart.']['positionX'];
			$positionY = $this->conf['cart.']['positionY'];
			$width = $this->conf['cart.']['width'];

			$this->pdf->SetLineWidth(1);
			$this->pdf->writeHTMLCell($width, 0, $positionX, $positionY, $html, 0, 2);
		}
	}

	/**
	 * This creates another stand-alone instance of the Fluid view to render a plain text e-mail template
	 * @param string $templateName the name of the template to use
	 * @return Tx_Fluid_View_StandaloneView the Fluid instance
	 */
	protected function getOrderItemRenderer( $templateName = 'Default.html' ) {
		$objectManager = t3lib_div::makeInstance('Tx_Extbase_Object_ObjectManager');
		$orderItemView = $objectManager->create('Tx_Fluid_View_StandaloneView');
		$orderItemView->setFormat('html');
		$orderItemView->setTemplatePathAndFilename( t3lib_div::getFileAbsFileName( 'EXT:wt_cart_pdf/Resources/Private/Templates/' . $templateName ) );
		$orderItemView->setPartialRootPath( t3lib_div::getFileAbsFileName( 'EXT:wt_cart_pdf/Resources/Private/Partials/' ) );
		$orderItemView->setLayoutRootPath( t3lib_div::getFileAbsFileName( 'EXT:wt_cart_pdf/Resources/Private/Layouts/' ) );
		$orderItemView->assign( 'settings', $this->settings );

		return $orderItemView;
	}

	/**
	 *
	 */
	private function renderAddress() {
		$positionX = $this->conf['address.']['positionX'];
		$positionY = $this->conf['address.']['positionY'];
		if ($this->conf['address.']['width']) {
			$width = $this->conf['address.']['width'];
		} else {
			$width = 80;
		}

		if ($this->conf['address.']['fontSize']) {
			$this->pdf->setFontSize( $this->conf['address.']['fontSize'] );
		}


		$this->pdf->writeHtmlCell( $width, 0, $positionX, $positionY, $this->orderItem->getBillingAddress() );

		if ($this->conf['address.']['fontSize']) {
			$this->pdf->setFontSize( $this->conf['fontSize'] );
		}
	}

	/**
	 *
	 */
	private function renderSubject() {
		$params = array(
			'ordernumber' => $this->orderItem->getOrderNumber(),
			'invoicenumber' => $this->orderItem->getInvoiceNumber()
		);

		$this->cObj = t3lib_div::makeInstance( 'tslib_cObj' );
		$this->cObj->start( $params, $this->conf['subject.']['content'] );

		$content = $this->cObj->cObjGetSingle($this->conf['subject.']['content'], $this->conf['subject.']['content.']);

		if ( $content != "" ) {
			$positionX = $this->conf['subject.']['positionX'];
			$positionY = $this->conf['subject.']['positionY'];
			if ($this->conf['subject.']['width']) {
				$width = $this->conf['subject.']['width'];
			} else {
				$width = 160;
			}

			if ($this->conf['subject.']['fontSize']) {
				$this->pdf->setFontSize( $this->conf['subject.']['fontSize'] );
			}

			$this->pdf->writeHtmlCell($width, 0, $positionX, $positionY, $content);

			if ($this->conf['subject.']['fontSize']) {
				$this->pdf->setFontSize( $this->conf['fontSize'] );
			}
		}
	}

	/**
	 *
	 */
	private function renderAdditionalTextblocks() {
		foreach ($this->conf['additionaltextblocks.'] as $value) {
			$tsParams = array(
				'ordernumber' => $this->orderItem->getOrderNumber(),
				'invoicenumber' => $this->orderItem->getInvoiceNumber(),
				'paymentName' => $this->orderItem->getOrderPayment()->getName(),
				'paymentNote' => $this->orderItem->getOrderPayment()->getNote(),
				'shippingName' => $this->orderItem->getOrderShipping()->getName(),
				'shippingNote' => $this->orderItem->getOrderShipping()->getNote(),
			);
/*
			if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['wt_cart_pdf']['addParamsToAdditionalTextblocks']) {
				foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['wt_cart_pdf']['addParamsToAdditionalTextblocks'] as $funcRef) {
					if ($funcRef) {
						$params = array(
							'cart' => &$cart,
							'tsParams' => &$tsParams
						);

						t3lib_div::callUserFunction($funcRef, $params, $this);
					}
				}
			}
*/
			$this->cObj = t3lib_div::makeInstance( 'tslib_cObj' );
			$this->cObj->start( $tsParams, $value['content'] );

			$content = $this->cObj->cObjGetSingle($value['content'], $value['content.']);

			if ($value['fontSize']) {
				$this->pdf->setFontSize( $value['fontSize'] );
			}

			$this->pdf->writeHTMLCell($value['width'], $value['height'], $value['positionX'], $value['positionY'], $content, 0, 2, 0, true, $value['align'] ? $value['align'] : 'L', true);

			if ($value['fontSize']) {
				$this->pdf->setFontSize( $this->conf['fontSize'] );
			}
		}
	}

	/**
	 *
	 */
	private function renderPaymentOptions() {
		if ( $this->orderItem->getOrderPayment()->getNote() ) {
			$this->pdf->SetY($this->pdf->GetY()+20);
			$this->pdf->SetX($this->conf['cart-position-x']);
			$this->pdf->Cell('150', '5', $this->orderItem->getOrderPayment()->getName(), 0, 1);
			$this->pdf->SetX($this->conf['cart-position-x']);
			$this->pdf->Cell('150', '5', $this->orderItem->getOrderPayment()->getNote(), 0, 1);
		}
	}

	/**
	 * @return bool
	 */
	private function pdfDirExists() {
		if ( !is_dir( PATH_site . $this->pdf_path ) ) {
			$error = array (
				'msg' => 'directory for PDF does not exists',
				'dir' => PATH_site . $this->pdf_path
			);
			$session['error']['wt_cart_pdf'][] = $error;

			if (TYPO3_DLOG) {
				t3lib_div::devLog($error['msg'], $this->extKey, 0, $session['error']);
			}

			return FALSE;
		}

		return TRUE;
	}

	/**
	 * @return string
	 */
	private function getPath( ) {
		$this->pdf_path = $this->conf['dir'];

		return $this->pdf_path;
	}

	/**
	 * @param string $type
	 * @return string
	 */
	private function getFilename( $type ) {
		switch ($type) {
			case 'order':
				$filename = $this->orderItem->getOrderNumber();
				break;
			case 'invoice':
				$filename = $this->orderItem->getInvoiceNumber();
				break;
			default:
				$filename = md5( $this->orderItem->getUid() );
		}

		if ( ! preg_match('/\.pdf$/', $filename) ) {
			$this->pdf_filename = $filename . '.pdf';
		} else {
			$this->pdf_filename = $filename;
		}
	}

}

?>