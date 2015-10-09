<?php
require_once(CLASS_PATH.'tcpdf/tcpdf.php');
require_once(CLASS_PATH."label/class.label.php");

class labelCab extends label{

	/**
	 * Template d'impression étiquette
	 */
	function template($x, $y, $dataPrint){

	$x += $this->labelMargin;
	$y += $this->labelMargin;
	 
        //$this->SetMargins(0, 0, 0);

        $pad_cab = 0;//0.025 * $this->labelWidth;
        $cab_font = 0.2 * min($this->labelWidth, $this->labelHeight);
	
        $barcode_style = array(
          "position" => "S",
          "border" => false,
          "padding" => $pad_cab,
          "fgcolor" => array(0,0,0),
          "bgcolor" => false, //array(255,255,255),
          "text" => true,
          "font" => "helvetica",
          "fontsize" => $cab_font,
          "stretchtext" => 4,
			"stretch" => true
        ); 
        
        // Etiquette

        $aff_border = 0;
        $ref_font = .25 * min($this->labelWidth, $this->labelHeight);
        $des_font = .2 * min($this->labelWidth, $this->labelHeight);
        
        $margin = $this->getMargins();
        
        $cabWidth = $this->labelWidth - (2*$this->labelMargin);
        $cabHeight = ( $this->labelHeight - 2*$margin['top']) ;
        
        
        // Affectation police    
        $this->SetFont("helvetica", "BI", $ref_font); 
        
        // LIGNE REF et CODACT
        //$this->SetFillColor(255,255,255); 

        // int MultiCell( $w, 	$h, $txt, $border,$align, $fill, $ln,$x, $y , $reseth , $stretch , $ishtml, $autopadding, $maxh)
        $this->MultiCell(0, 0, "", false, 'L', 0, 0, $x ,$y, true);


        // LIGNE DESIGNATION
        $this->SetFont("helvetica", "BI", $des_font);
        
        // CAB
        $this->setX($x);
        //write1DBarcode( 		$code, 			$type, 					$x, $y , $w 		, $h = 		$xres, $style , $align )
        $this->write1DBarcode($dataPrint["cab"], $dataPrint["typeCAB"], '', '', $cabWidth, $cabHeight, 0.01, $barcode_style, "M");

	}


}//End of class labels

?>