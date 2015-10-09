
<div class="kiwi">
<form class="form5" name='form_article' method='POST' action='example2.php'  target='_blanck' >
		<table border='0'>
			<tr>
			<td><label for="format">Format du support : </label></td><td colspan="6">
			  <select size="1" name="format">
			  <?php // Select : Affichage des type d'&eacute;tiquettes et de planches disponibles
				$labelXml = simplexml_load_file('../labels.xml');

				foreach ($labelXml->label as $labelName=>$label) {
					echo "<option value=\"".$label['id']."\">".strval($label->name)." - ".strval($label->description)."</option>\n";  
				}
					// Fin select
			  ?>
			  </select>
			</td>
		</tr>

		
		<tr>
			<td><label for="decalage">D&eacute;calage : </label></td>
			<td colspan='6'><input name="decalage" type="text" value="0" size="3"></td>
		</tr>
		<tr>
			<td><label for="border">Afficher la bordure : </label></td>
			<td colspan='6'><input type='checkbox' name='border' checked></td>
		</tr>
		<tr>
			<td colspan="8"><hr/></td>
		</tr>



		<!-- Adresse -->
		<tr>
			<td><label for="nom">Pr&eacute;nom / Nom : </label></td><td colspan="3"><input name="nom" type="text" value="Firstname Lastname" size="50"></td>
		</tr>
		<tr>
			<td><label for="adresse1">Address 1 : </label></td><td colspan="3"><input name="adresse1" type="text" value="--- Address 1 ---" size="50"></td>
		</tr>
		<tr>
			<td><label for="adresse2">Address 2 : </label></td><td colspan="3"><input name="adresse2" type="text" value="--- Address 2 ---" size="50"></td>
		</tr>
		<tr>
			<td><label for="cp">Zipcode : </label></td><td colspan="1"><input name="cp" type="text" value="99999" size="5"></td>
			<td><label for="ville">City : </label></td><td colspan="1"><input name="ville" type="text" value="City" size="30"></td>
		</tr>
		<!-- QUANTITES -->
		<tr>
			<td><label for="qteEtiq">Quantit&eacute; : </label></td><td><input name="qteEtiq" type="text" value="10" size="3" maxlength="3"></td>
		</tr>

		<tr>
			<td colspan="3"></td><td></td><td><p class="submit"><button name="creationPDF" type="submit" onSubmit="return true">Cr&eacute;ation PDF</button></p></td>
		</tr>

	</table>
</form>
</div>

