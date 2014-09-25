<table class="border" style="width:100%;">
	<!-- entête du tableau -->
	<thead style="background-color:#CCCCCC;">
		<tr>
			<td>Service</td>
			<td>Consultant</td>
			<td>Total<br>jours</td>
			<td>Total<br>heures</td>
			<td>[jours.key;block=td]<br>[jours.val]</td>
			<td>Actions</td>
		</tr>
	</thead>
	
	<tbody>
	<!-- Contenu déjà existant -->
	<tr id="[ligneTimesheet.$;strconv=no;block=tr;sub1]" >
		<td>[ligneTimesheet_sub1.val;block=td;strconv=no]</td>
		<td><a href="#"><img src="img/delete.png"  onclick="document.location.href='?id=[ligneTimesheet.$]&action=deleteligne'"></a></td>
	</tr>
	
	[onshow;block=begin;when [view.mode]=='edittime']
		<!-- Nouvelle ligne de timesheet-->
		<tr id="[timesheet.rowid;strconv=no]">
			<td>[timesheet.services;strconv=no]</td>
			<td>[timesheet.consultants;strconv=no]</td>
			<td><!-- total jours vide en mode création --></td>
			<td><!-- total heures vide en mode création --></td>
			<td>[formjour.val;block=td;strconv=no]</td>
			<td><input type="submit" value="Ajouter" name="add" class="button"></td>
		</tr>
	[onshow;block=end]
	</tbody>
</table>

[onshow;block=begin;when [view.mode]!='edittime']
<div class="tabsAction" style="text-align: center;">
	<a href="?id=[timesheet.id]&action=edittime" class="butAction">Modifier les temps</a>
</div>
[onshow;block=end]	
[onshow;block=begin;when [view.mode]=='edittime']
	<div class="tabsAction" style="text-align:center;">
	<input type="submit" value="Enregistrer" name="save" class="button"> 
	&nbsp; &nbsp; <input type="button" value="Annuler" name="cancel" class="button" onclick="document.location.href='?id=[timesheet.id]'">
</div>
[onshow;block=end]
</div>

<div style="clear:both"></div>