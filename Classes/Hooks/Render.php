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

require_once(PATH_tslib . 'class.tslib_pibase.php');
if (t3lib_extMgm::isLoaded('extcode_tcpdf')) {
	require(t3lib_extMgm::extPath('extcode_tcpdf').'class.tx_extcode_tcpdf.php');
}
if (t3lib_extMgm::isLoaded('extcode_fpdi')) {
	require(t3lib_extMgm::extPath('extcode_fpdi').'class.tx_extcode_fpdi.php');
}
if (t3lib_extMgm::isLoaded('user_wtcart_powermailCart')) {
	require_once(t3lib_extMgm::extPath('wt_cart') . 'lib/class.tx_wtcart_powermailCart.php');
}

class Tx_WtCartPdf_Hooks_Render extends tslib_pibase {

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
	 * @param $params
	 * @param $obj
	 */
	public function afterSetOrderNumber(&$params, &$obj) {
		$this->createPdf($params, 'order');
	}

	/**
	 * @param $params
	 * @param $obj
	 */
	public function afterSetInvoiceNumber(&$params, &$obj) {
		$this->createPdf($params, 'invoice');
	}

	/**
	 * @param $params
	 * @param string $type
	 * @internal param $session
	 * @return int
	 */
	public function createPdf(&$params, $type) {
		$this->conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_wtcart_pdf.'][$type . '.'];
		$this->abortOnError = $this->wtcartconf['abortOnError'];

		$cart = $params['cart'];

		$this->getPath( );
		$this->getFilename( $cart );

		if ( ! $this->pdfDirExists() ) {
			return 1;
		}
		if ( $this->pdfFileExists('before') ) {
			return 0;
		}

		$this->pi_loadLL();

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
		if ( file_exists( $this->pdf_path . '/' . $this->pdf_filename ) ) {

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
		$pdf = new FPDI();
		$pdf->AddPage();

		if ($this->conf['include_pdf']) {
			$pdf->setSourceFile($this->conf['include_pdf']);
			$tplIdx = $pdf->importPage(1);
			$pdf->useTemplate($tplIdx, 0, 0, 210);
		}

		$pdf->SetFont('Helvetica','',$this->conf['font-size']);

		$this->renderAddress( $pdf );
		$this->renderSubject( $pdf, $params['cart'] );
		$this->renderAdditionalTextblocks( $pdf );

		$this->renderCart( $pdf );

		$this->renderOptions( $pdf, $params['payment'] );

		$pdf->Output( $this->pdf_path . '/' . $this->pdf_filename, 'F' );
	}

	/**
	 * @param $pdf
	 */
	private function renderCart( &$pdf ) {
		$conf['main.']['template'] = $this->conf['template'];
		$powermailCart = t3lib_div::makeInstance('user_wtcart_powermailCart');
		$html = $powermailCart->showCart($content = '', $conf);

		if ( $html ) {
			$positionX = $this->conf['cart.']['positionX'];
			$positionY = $this->conf['cart.']['positionY'];
			$width = $this->conf['cart.']['width'];

			$pdf->SetLineWidth(1);
			$pdf->writeHTMLCell($width, 0, $positionX, $positionY, $html, 0, 2);
		}
	}

	/**
	 * @param $pdf
	 */
	private function renderAddress( &$pdf ) {
		$content = $GLOBALS['TSFE']->cObj->cObjGetSingle($this->conf['address.']['content'], $this->conf['address.']['content.']);

		if ( $content != "" ) {
			$positionX = $this->conf['address.']['positionX'];
			$positionY = $this->conf['address.']['positionY'];
			if ($this->conf['address.']['width']) {
				$width = $this->conf['address.']['width'];
			} else {
				$width = 80;
			}

			$pdf->writeHtmlCell($width, 0, $positionX, $positionY, $content);
		}
	}

	/**
	 * @param $pdf
	 * @param $cart Tx_WtCart_Domain_Model_Cart
	 */
	private function renderSubject( &$pdf, $cart ) {
		$params = array(
			'ordernumber' => $cart->getOrderNumber(),
			'invoicenumber' => $cart->getInvoiceNumber()
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

			$pdf->writeHtmlCell($width, 0, $positionX, $positionY, $content);
		}
	}

	/**
	 * @param $pdf
	 */
	private function renderAdditionalTextblocks( &$pdf ) {
		foreach ($this->conf['additionaltextblocks.'] as $key => $value) {
			$html = $GLOBALS['TSFE']->cObj->cObjGetSingle($value['content'], $value['content.']);

			$pdf->writeHTMLCell($value['width'], $value['height'], $value['positionX'], $value['positionY'], $html, 0, 2, 0, true, $value['align'] ? $value['align'] : 'L', true);
		}
	}

	/**
	 * @param $pdf
	 * @param $payment_id
	 */
	private function renderOptions(&$pdf, $payment_id) {
		$payment_option = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_wtcart_pi1.']['payment.']['options.'][$payment_id . '.'];
		
		if ($payment_option['note']) {
			$pdf->SetY($pdf->GetY()+20);
			$pdf->SetX($this->conf['cart-position-x']);
			$pdf->Cell('150', '5', $payment_option['title'], 0, 1);
			$pdf->SetX($this->conf['cart-position-x']);
			$pdf->Cell('150', '5', $payment_option['note'], 0, 1);
		}
	}

	private function getOrderNumber( ) {
		$powermailCart = t3lib_div::makeInstance('user_wtcart_powermailCart');
		return $powermailCart->showOrderNumber();
	}

	private function sanitize_file_name( $filename ) {
		$special_chars = array("?", "[", "]", "/", "\\", "=", "<", ">", ":", ";", ",", "'", "\"", "&", "$", "#", "*", "(", ")", "|", "~", "`", "!", "{", "}", chr(0));
		$filename = str_replace($special_chars, '', $filename);
		$filename = preg_replace('/[\s-]+/', '-', $filename);

		return $filename;
	}

	/**
	 * @return bool
	 */
	private function pdfDirExists() {
		if ( !is_dir( $this->pdf_path ) ) {
			$error = array (
				'msg' => 'directory for PDF does not exists',
				'dir' => $this->pdf_path
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
	 * @param $cart Tx_WtCart_Domain_Model_Cart
	 * @return string
	 */
	private function getFilename( $cart ) {
		$params = array(
			'ordernumber' => $cart->getOrderNumber(),
			'invoicenumber' => $cart->getInvoiceNumber()
		);
		$this->cObj = t3lib_div::makeInstance( 'tslib_cObj' );
		$this->cObj->start( $params, $this->conf['pdf_filename'] );

		$this->pdf_filename = $this->cObj->cObjGetSingle($this->conf['pdf_filename'], $this->conf['pdf_filename.']);

		if ( ! preg_match('/\.pdf$/', $this->pdf_filename) ) {
			$this->pdf_filename .= '.pdf';
		}

		return $this->pdf_filename;
	}

}

?>