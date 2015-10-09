<?php

define('CLASS_PATH','../../');

require_once(CLASS_PATH.'tcpdf/tcpdf.php');
require_once(CLASS_PATH."label/class.label.php");
require_once(CLASS_PATH."label/examples/class.labelAdresse.php");

//Info du formulaire
$info["format"]		=	isset($_POST["format"]) ? 	$_POST["format"] : '';
$info["decalage"]	=	isset($_POST["decalage"]) ? $_POST["decalage"] : 0;
$info["border"]		=	isset($_POST["border"]) ? 	$_POST["border"] : '';
$decalage = $info["decalage"];

///FORM UNIT
$info["nom"] 		=	isset($_POST["format"]) ? 	stripslashes($_POST['nom']) : '';
$info["adresse1"]	=	isset($_POST["format"]) ? 	stripslashes($_POST['adresse1']) : '';
$info["adresse2"]	=	isset($_POST["format"]) ? 	stripslashes($_POST['adresse2']) : '';
$info["cp"]			=	isset($_POST["format"]) ? 	stripslashes($_POST['cp']) : '';
$info["ville"] 		=	isset($_POST["format"]) ? 	stripslashes($_POST['ville']) : '';

$info["qteEtiq"] 	=	isset($_POST["qteEtiq"]) ? 	stripslashes($_POST['qteEtiq']) : '';

// Creation tableau de données
$data = array();

// Création d'une ligne par étiquette
for ($j=0; $j < $info["qteEtiq"]; $j++){
	array_push($data,$info);
}

	// Ajout décalage
for ($i = 0; $i < $info["decalage"]; $i++){
	array_unshift($data, NULL);
}

/*
echo "<PRE>";
print_r($_POST);
echo "</PRE>";

echo "<PRE>";
print_r($data);
echo "</PRE>";
*/

$pdf = new labelAdresse( $info["format"], $data , CLASS_PATH.'label/', '', true);

// BORDER
if($info["border"]=='on'){
	$pdf->border = true;
}
else{
	$pdf->border = false;
}

// Ajout du décalage
for($j=0; $j < $decalage; $j++){
	array_unshift($data,null);
}	

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Ludovic RIAUDEL');
$pdf->SetTitle("Planche d'etiquettes par kiwi");
$pdf->SetSubject("Creation d'etiquettes avec CAB en publipostage");

// remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(0);

$pdf->SetAutoPageBreak( true, 0);

//set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);  

/****************************************/
// Création
$pdf->Addlabel();
/****************************************/

// Affichage
$pdf->Output("doc.pdf", "I");

?>