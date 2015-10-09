<html>
<head>
	<title>Kiwi label Examples</title>
	<link href="css/style.css" rel="stylesheet" type="text/css"/>
	<link href="css/form.css" rel="stylesheet" type="text/css"/>
	<style>
	 code {
		background: none repeat scroll 0 0 #FAFAFA;
		border: 1px solid #EEEEEE;
		color: #666666;
		display: block;
		font-size: 12px;
		margin-bottom: 18px;
		padding: 8px;
	}
	 code {
		font-size: 12px;
		padding: 2px;
		width: 100%;
	}
	pre, code {
		font: 1.1em 'Courier New',Courier,Fixed;
		width: 100%;
	}
	cite{
		font-weight:bold;
	}
	</style>
</head>
<body>
	<h1>Kiwi label Examples</h1>
	
	<p>The class.label.php uses TCPDF to create a page of labels from a xml file configuration. Then the label class apply a template.</p>
	
	<b>List :</b>
	<ul>
		<li><a href="#example1">Example 1</a></li>
		<li><a href="#example2">Example 2</a></li>
		<li><a href="#example3">Example 3</a></li>
	</ul>
	

	<h2 id="example1">Example 1</h2>
	A simple example with text to display : <a href="example1.php" target="_blanck">Example 1</a>
	
	<ol>
		<li><h3>Create your template</h3></li>
		
		<p>The template herited of <cite>class.label.php</cite> and <cite>tcpdf.php</cite>. Construct your template width the <a href="http://www.tcpdf.org/doc/code/annotated.html">tcpdf documentation</a></p>
		
		<?php 
	highlight_file("class.labelExample.php");
		?>

		<li><h3>Load your source</h3></li>
		
		<?php 
	highlight_string("
	<?php
		define('CLASS_PATH','../../');
		require_once(CLASS_PATH.'tcpdf/tcpdf.php');
		require_once(CLASS_PATH.'label/class.label.php');
		require_once(CLASS_PATH.'label/examples/class.labelExample.php');
		...
	?>
	");
		?>	


		
		<li><h3>Your label id configuration in labels.xml</h3></li>
		
		<p>For the label ID, see <a href="../labels.xml">labels.xml</a>.</p>
		
		<?php 
		$label_id = '$label_id';
		$data = '$data';
	highlight_string("
	<?php
		...
		// Id of label format (see <a href='qqsd'>labels.xml</a>)
		$label_id = '1';
		
		// Data to give to the template (null for this example)
		$data = null;
		...
	?>");
		?>

		<li><h3>Create your pdf object</h3></li>
		
		<p>The label class parameter :
<table width="100%">
	<tbody>
		<tr>
			<th>Param&egrave;tres</th>
			<th>Type</th>
			<th> </th>
			<th>Description</th>
		</tr>
		<tr>
			<td><cite>$label_id</cite> </a></td>
			<td>string</td>
			<td>obligatoire</td>
			<td><p>Identifiant d'un format d'&eacute;tiquette : attribut <cite><a href="index.php?id=label.xml#id">id</a></cite> de la balise <cite><a href="index.php?id=label.xml#label">label</a></cite> .</p></td>
		</tr>
		<tr class="alt">
			<td><cite>$data</cite> </a></td>
			<td>array</td>
			<td>obligatoire</td>
			<td><p>Tableau de donn&eacute;es pour la r&eacute;alisation du gabarit. Chaque entr&eacute;e dans le tableau correspond &agrave; une &eacute;tiquette (nombre d'&eacute;tiquettes = count($data) ).</p></td>
		</tr>
		<tr>
			<td><cite>$pathConfig</cite> </a></td>
			<td>string</td>
			<td>obligatoire</td>
			<td><p>Chemin vers le r&eacute;pertoire ou est situ&eacute; le fichier de param&eacute;trage <a href="index.php?id=label.xml">label.xml</a> (avec le "/" &agrave; la fin).</p></td>
		</tr>
		<tr class="alt">
			<td><cite>$configFile</cite> </a></td>
			<td>string</td>
			<td>obligatoire</td>
			<td><p>Nom du fichier de param&eacute;trage (Par d&eacute;faut : "<a href="index.php?id=label.xml">label.xml</a>" )</p></td>
		</tr>
		<tr>
			<td><cite>$border</cite> </a></td>
			<td>boolean</td>
			<td>facultatif</td>
			<td><p>Affiche ou non les traits de s&eacute;paration de chaque &eacute;tiquette (peut servir au d&eacute;coupage papier) (Par d&eacute;faut : false).</p></td>
		</tr>
	</tbody>
</table>
		</p>
		
		<?php 
		$pdf = '$pdf';
	highlight_string("
	<?php
		...
		$pdf = new labelExemple( $label_id, $data , CLASS_PATH.'label/', 'labels.xml', true);
		...
	?>");
		?>
		
		<li><h3>Others Tcpdf parameters</h3></li>
		<?php
	highlight_string('
		<?php
		...
		$pdf->SetCreator(PDF_CREATOR);
		$pdf->SetAuthor("Madvic");
		$pdf->SetTitle("Etiquettes par kiwi");
		$pdf->SetSubject("Création d\'étiquettes Code Barre");
		$pdf->SetKeywords("TCPDF, PDF, example, test, guide, kiwi");

		// remove default header/footer
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);

		// remove default margin
		$pdf->SetHeaderMargin(0);
		$pdf->SetFooterMargin(0);

		$pdf->SetAutoPageBreak( true, 0);

		//set image scale factor
		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO); 
		...
		?>');
		?>
		
		
		<li><h3>Labels creation and output the pdf</h3></li>
		<p>The <cite>Addlabel</cite> method create all labels in pdf document. <br/>
		And <cite>Output</cite> is a TCPDF method for send the pdf in the browser.</p>
		<?php
	highlight_string('
		<?php
		
		$pdf->Addlabel();

		// Affichage du document dans le navigateur
		$pdf->Output("doc.pdf", "I");
		?>
		');
		?>
		
	
<p>&nbsp;</p>

	<h2 id="example2">Example 2</h2>
	<p>A simple example of the utility of the class : Address labels</p>
	<?php
	include("ex_form_adresse.php");
	?>
<p>&nbsp;</p>
	
	<h2 id="example3">Exemple 3</h2>
	<p>A codebar example : <a href="example3.php" target="_blanck">Example 3</a></p>
	<?php
		highlight_file("example3.php");
	?>
	
	</ol>
	
<br/>
<a href="http://kiwi.madvic.net/">kiwi.madvic.net</a>
<br/><br/>
</body>
</html>