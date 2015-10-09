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
		$nom_font = .22 * min($this->labelWidth, $this->labelHeight);
		$address_font = .22 * min($this->labelWidth, $this->labelHeight);
		
		$this->SetFont("helvetica", "", $nom_font); 
		$this->Cell($w_des , (0.65*$nom_font) ,$dataPrint["name"],$aff_border,1,'L',0);
		$this->setX($x);

            if(is_array($dataPrint["street_addr"])){
              foreach($dataPrint["street_addr"] as $add_line){
                  if(!empty($add_line)){
                  $this->Cell($w_des , (0.65*$nom_font) ,$add_line,$aff_border,1,'L',0);
                  $this->setX($x);
                  }
              }
            }else{
            $this->Cell($w_des , (0.65*$nom_font) ,$dataPrint["street_addr"],$aff_border,1,'L',0);
            $this->setX($x);
            }

            if(!empty($dataPrint["city"])){
                $this->Cell($w_des , (0.65*$nom_font) ,$dataPrint["city"],$aff_border,1,'L',0);
                $this->setX($x);
            }

            if(!empty($dataPrint["state"])){
            $this->Cell($w_des , (0.65*$nom_font) ,$dataPrint["state"],$aff_border,1,'L',0);
            $this->setX($x);
            }

            if(!empty($dataPrint["country"]) || !empty($dataPrint["zip"])){
                $line = "";
                    if(!empty($dataPrint["zip"])){
                    $line .=  $dataPrint["zip"];
                    }

                    if(!empty($dataPrint["country"])){
                        if(!empty($line)){
                        $line .=  ", ";
                        }
                        $line .=  $dataPrint["country"];
                    }
                $this->Cell($w_des , (0.65*$nom_font) ,$line ,$aff_border,1,'L',0);
                $this->setX($x);
            }

	} // end of 'template()'

} // end of 'labelAddress{}'
?>