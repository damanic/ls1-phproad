<?php

class labelAddress extends label {

	/**
	 * Template 
	 */
	function template($x, $y, $dataPrint){
	
		$x += $this->labelMargin;
		$y += $this->labelMargin;
		
		$this->setX($x);
		$this->setY($y, false);
		

		// Etiquette
		$w_des = 0;
		
		$aff_border = 0;
		$nom_font = .3 * min($this->labelWidth, $this->labelHeight);
		$addresse_font = .3 * min($this->labelWidth, $this->labelHeight);
		
		$this->SetFont("helvetica", "", $nom_font); 
		$this->Cell($w_des , (0.65*$nom_font) ,$dataPrint["nom"],$aff_border,1,'L',0);
		$this->setX($x);
		$this->Cell($w_des , (0.65*$nom_font) ,$dataPrint["adresse1"],$aff_border,1,'L',0);
		$this->setX($x);
		$this->Cell($w_des , (0.65*$nom_font) ,$dataPrint["adresse2"],$aff_border,1,'L',0);
		$this->setX($x);
		$this->Cell($w_des , (0.65*$nom_font) ,$dataPrint["cp"]."  ".$dataPrint["ville"],$aff_border,1,'L',0);$this->setX($x);
		
	} // end of 'template()'

} // end of 'labelAddress{}'
?>