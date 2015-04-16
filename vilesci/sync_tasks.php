<?php
/* Copyright (C) 2015 fhcomplete.org
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307, USA.
 *
 * Authors: Christian Paminger <christian.paminger@gmail.com> 
 *
 */
/**
 * Sync_user
 */
require_once('../../../config/vilesci.config.inc.php');
require_once('../../../include/functions.inc.php');
require_once('../../../include/basis_db.class.php');

require_once('../include/odoo.class.php');

ini_set('display_errors','1');
error_reporting(E_ALL);

$fhc = new basis_db();

//Odoo Verbindung herstellen
$odoo = new odoo();
$odoo->debug=true;
if(!$odoo->connect())
	die($odoo->errormsg);
if(!$odoo->loadCategories())
	die($odoo->errormsg);
	
// Projekte holen
$projekte=array("FHC-BFI");

// Alle Projekte durchlaufen
foreach($projekte AS $projekt)
{
	// ================ PROJEKT_PHASEN ===========================
	$qry = "SELECT 
			 	projektphase_id, projekt_kurzbz, projektphase_fk, bezeichnung, beschreibung, start, ende, budget, insertamum, insertvon, updateamum, updatevon, personentage, farbe,
			 	sync_id, odoo_id, fhc_lastupdate, odoo_lastupdate, deleted
		FROM
			fue.tbl_projektphase LEFT OUTER JOIN addon.tbl_odoo_sync ON (fhc_id=projektphase_id AND fhc_tablename='fue.tbl_projektphase')
		WHERE
			projekt_kurzbz='$projekt'
		ORDER BY
			projektphase_id;";

	if($result = $fhc->db_query($qry))
	{
		echo 'INSERTS/UPDATES ProjektPhase <-> odoo: ';
		while($row = $fhc->db_fetch_object($result))
		{
			unset($odoo_id);
			// Check if synced
			// INSERTS
			if($row->odoo_id == null)
			{
				// not synced! Create new task in odoo
				// Farben fuer odoo: 7->Phasen 5->Pakete 3->Tasks
				$qry="INSERT INTO public.project_task (create_date, color, name, description, date_start, date_end, date_deadline, write_date, 
							project_id, create_uid, user_id, write_uid, company_id, stage_id ) 
						VALUES ('$row->insertamum',7,'$row->bezeichnung','$row->beschreibung',".$fhc->db_add_param($row->start, FHC_STRING, false).",".$fhc->db_add_param($row->ende, FHC_STRING, false).",".$fhc->db_add_param($row->ende, FHC_STRING, false).",'$row->updateamum',
							$odoo->odoo_project_id,$odoo->odoo_user_id,$odoo->odoo_user_id,$odoo->odoo_user_id,1,1);
						SELECT currval('project_task_id_seq'), create_date from public.project_task WHERE id=currval('project_task_id_seq');";
				//echo $qry.'<br/>'; 
				if(!$res=$odoo->db_query($qry))
					echo $odoo->errormsg;
				else
				{
					// Insert Info into Sync-Table
					$odoo_id=pg_fetch_result($res,0,0);
					$odoo_lastupdate=pg_fetch_result($res,0,1);
					$qry2="INSERT INTO addon.tbl_odoo_sync (fhc_tablename, fhc_id, odoo_id, fhc_lastupdate, odoo_lastupdate, deleted) 
						VALUES ('fue.tbl_projektphase',$row->projektphase_id,$odoo_id,'$row->updateamum','$odoo_lastupdate',false);";
					if(!$fhc->db_query($qry2))
						echo $fhc->errormsg;
					else
						echo '<span title="'.htmlspecialchars($qry).'">i</span>';
					// SET OdooTaskCategory
					if (!$odoo->updateCategory($odoo_id,$odoo->getCategoryIdByName('Projektphase')))
						echo $odoo->errormsg;
				}
			} // End of INSERTS
			// UPDATES
			else
			{
				// synced! check for update ...
				// fetch from odoo
				if (!$odoo->loadTask($row->odoo_id))
					die($row->odoo_id.' as project_task.id cannot be loaded.');
				else
				{
					$odoo_task=pg_fetch_object($odoo->res);
					//var_dump($odoo_task);var_dump($row);	die();
					if ($row->updateamum!=$row->fhc_lastupdate && $row->odoo_lastupdate!=$odoo_task->write_date)
						echo '<br/>Task was changed on both sides: odoo_id=',$row->odoo_id,' fhc_id=',$row->fhc_id,' FHC-Table:',$row->fhc_tablename;
					elseif ($row->updateamum!=$row->fhc_lastupdate)
					{
						// FHC has changed
						$qry='UPDATE public.project_task SET ';
						if ($row->bezeichnung!=$odoo_task->name)
							$qry.=" name='$row->bezeichnung',";
						if ($row->beschreibung!=$odoo_task->description)
							$qry.=" description='$row->beschreibung',";
						if ($row->start!=$odoo_task->date_start)
							$qry.=" date_start='$row->start',";
						if ($row->ende!=$odoo_task->date_end)
							$qry.=" date_end='$row->ende',";
						//$qry=substr($qry,0,-1);  //kill last comma
						$qry.=" write_date='$row->updateamum'";
						$qry.=' WHERE id='.$row->odoo_id.';';
						//echo $qry;
						if(!$res=$odoo->db_query($qry))
							echo $odoo->errormsg;
						else
						{
							$qry2="UPDATE addon.tbl_odoo_sync
								SET odoo_lastupdate='$row->updateamum', fhc_lastupdate='$row->updateamum' 
								WHERE sync_id=$row->sync_id;";
							if(!$fhc->db_query($qry2))
								echo $fhc->errormsg;
							else
								echo '<span title="'.htmlspecialchars($qry).'">u</span>';
							
						}
						// SET OdooTaskCategory
						if (!$odoo->updateCategory($row->odoo_id,$odoo->getCategoryIdByName('Projektphase')))
							echo $odoo->errormsg;
					}
					elseif ($row->odoo_lastupdate!=$odoo_task->write_date)
					{
						// Odoo has changed
						$qry='UPDATE fue.tbl_projektphase SET ';
						if ($row->bezeichnung!=$odoo_task->name)
							$qry.=" bezeichnung='$odoo_task->name',";
						if ($row->beschreibung!=$odoo_task->description)
							$qry.=" beschreibung='$odoo_task->description',";
						if ($row->start!=$odoo_task->date_start)
							$qry.=" start='$odoo_task->date_start',";
						if ($row->ende!=$odoo_task->date_end)
							$qry.=" ende='$odoo_task->date_end',";
						//$qry=substr($qry,0,-1);  //kill last comma
						$qry.=" updateamum='$odoo_task->write_date',";
						$qry.=" updatevon='odoo_sync'";
						$qry.=' WHERE projektphase_id='.$row->projektphase_id.';';
						//echo $qry;
						if(!$res=$fhc->db_query($qry))
							echo $fhc->errormsg;
						else
						{
							$qry2="UPDATE addon.tbl_odoo_sync
								SET odoo_lastupdate='$odoo_task->write_date', fhc_lastupdate='$odoo_task->write_date' 
								WHERE sync_id=$row->sync_id;";
							if(!$fhc->db_query($qry2))
								echo $fhc->errormsg;
							else
								echo '<span title="'.htmlspecialchars($qry).'">u</span>';
						}
					}
					else
						echo '.';
				}
			}// End of UPDATES
			
			// Check for parent
			//var_dump($row->projektphase_fk);
			if ($row->projektphase_fk != null)
			{
				// PROJEKT_PHASEN
				$qry = "COMMIT; SELECT odoo_id
					FROM
						fue.tbl_projektphase JOIN addon.tbl_odoo_sync ON (fhc_id=projektphase_id AND fhc_tablename='fue.tbl_projektphase')
					WHERE
						projekt_kurzbz='$projekt' AND projektphase_id=$row->projektphase_fk
					;";
				if($resparent = $fhc->db_query($qry))
				{
					if (!$parent=$fhc->db_fetch_object($resparent))
						echo 'Projektphase_id '.$row->projektphase_fk.' is not synced for ParentConnection<br/>';
					// SET OdooTaskParent
					if (!isset($odoo_id))
						$odoo_id=$row->odoo_id;
					elseif (!$txt=$odoo->updateTaskParent($parent->odoo_id,$odoo_id))
						echo $odoo->errormsg;
					else
						echo $txt;
				}
				else
					echo $fhc->errormsg;
			}// End of Parents
			
		}// End of INSERT/UPDATES

		
	}// End of PROJEKT_PHASEN
	
	// ============== PROJEKT_TASKS ==========================
	$qry = "SELECT projekttask_id, tbl_projekttask.projektphase_id, tbl_projekttask.bezeichnung, tbl_projekttask.beschreibung, aufwand, mantis_id, 
				tbl_projekttask.insertamum, tbl_projekttask.insertvon, tbl_projekttask.updateamum, tbl_projekttask.updatevon, projekttask_fk, erledigt, 
				tbl_projekttask.ende, ressource_id, scrumsprint_id,
			 	sync_id, odoo_id, fhc_lastupdate, odoo_lastupdate, deleted
		FROM
			fue.tbl_projekttask JOIN fue.tbl_projektphase USING (projektphase_id)
			LEFT OUTER JOIN addon.tbl_odoo_sync ON (fhc_id=projekttask_id AND fhc_tablename='fue.tbl_projekttask')
		WHERE
			projekt_kurzbz='$projekt'
		ORDER BY
			projekttask_id;";

	if($result = $fhc->db_query($qry))
	{
		echo '<br/>INSERTS/UPDATES ProjektTasks <-> odoo: ';
		while($row = $fhc->db_fetch_object($result))
		{
			// Check if synced
			// INSERTS
			if($row->odoo_id == null)
			{
				// not synced! Create new task in odoo
				// Farben fuer odoo: 7->Phasen 5->Pakete 3->Tasks
				$qry="INSERT INTO public.project_task (create_date, color, name, description, date_deadline, write_date, 
							project_id, create_uid, user_id, write_uid, company_id, stage_id ) 
						VALUES ('$row->insertamum',3,'$row->bezeichnung','$row->beschreibung',".$fhc->db_add_param($row->ende, FHC_STRING, false).",'$row->updateamum',
							$odoo->odoo_project_id,$odoo->odoo_user_id,$odoo->odoo_user_id,$odoo->odoo_user_id,1,1);
						SELECT currval('project_task_id_seq'), create_date from public.project_task WHERE id=currval('project_task_id_seq');";
				//echo $qry.'<br/>'; 
				if(!$res=$odoo->db_query($qry))
					echo $odoo->errormsg;
				else
				{
					// Insert Info into Sync-Table
					$odoo_id=pg_fetch_result($res,0,0);
					$odoo_lastupdate=pg_fetch_result($res,0,1);
					$qry2="INSERT INTO addon.tbl_odoo_sync (fhc_tablename, fhc_id, odoo_id, fhc_lastupdate, odoo_lastupdate, deleted) 
						VALUES ('fue.tbl_projekttask',$row->projekttask_id,$odoo_id,'$row->updateamum','$odoo_lastupdate',false);";
					if(!$fhc->db_query($qry2))
						echo $fhc->errormsg;
					else
						echo '<span title="'.htmlspecialchars($qry).'">i</span>';
					// SET OdooTaskCategory
					if (!$odoo->updateCategory($odoo_id,$odoo->getCategoryIdByName('Task')))
						echo $odoo->errormsg;
					// SET Parent
					if (!$odoo->updateTaskParent($odoo->getTaskIdByFHCId($fhc,'fue.tbl_projektphase',$row->projektphase_id),$odoo_id))
						echo $odoo->errormsg;
				}
			} // End of INSERTS
			// UPDATES
			else
			{
				// synced! check for update ...
				// fetch from odoo
				if (!$odoo->loadTask($row->odoo_id))
					die($row->odoo_id.' as project_task.id cannot be loaded.');
				else
				{
					$odoo_task=pg_fetch_object($odoo->res);
					//var_dump($odoo_task);var_dump($row);	die();
					if ($row->updateamum!=$row->fhc_lastupdate && $row->odoo_lastupdate!=$odoo_task->write_date)
						echo '<br/>Task was changed on both sides: odoo_id=',$row->odoo_id,' fhc_id=',$row->fhc_id,' FHC-Table:',$row->fhc_tablename;
					elseif ($row->updateamum!=$row->fhc_lastupdate)
					{
						// FHC has changed
						$qry='UPDATE public.project_task SET ';
						if ($row->bezeichnung!=$odoo_task->name)
							$qry.=" name='$row->bezeichnung',";
						if ($row->beschreibung!=$odoo_task->description)
							$qry.=" description='$row->beschreibung',";
						if ($row->ende!=$odoo_task->date_end)
							$qry.=" date_deadline='$row->ende',";
						//$qry=substr($qry,0,-1);  //kill last comma
						$qry.=" write_date='$row->updateamum'";
						$qry.=' WHERE id='.$row->odoo_id.';';
						//echo $qry;
						if(!$res=$odoo->db_query($qry))
							echo $odoo->errormsg;
						else
						{
							$qry2="UPDATE addon.tbl_odoo_sync
								SET odoo_lastupdate='$row->updateamum', fhc_lastupdate='$row->updateamum' 
								WHERE sync_id=$row->sync_id;";
							if(!$fhc->db_query($qry2))
								echo $fhc->errormsg;
							else
								echo '<span title="'.htmlspecialchars($qry).'">u</span>';
							
						}
						// SET OdooTaskCategory
						if (!$odoo->updateCategory($row->odoo_id,$odoo->getCategoryIdByName('Task')))
							echo $odoo->errormsg;
					}
					elseif ($row->odoo_lastupdate!=$odoo_task->write_date)
					{
						// Odoo has changed
						$qry='UPDATE fue.tbl_projekttask SET ';
						if ($row->bezeichnung!=$odoo_task->name)
							$qry.=" bezeichnung='$odoo_task->name',";
						if ($row->beschreibung!=$odoo_task->description)
							$qry.=" beschreibung='$odoo_task->description',";
						if ($row->ende!=$odoo_task->date_deadline)
							$qry.=" ende='$odoo_task->date_end',";
						//$qry=substr($qry,0,-1);  //kill last comma
						$qry.=" updateamum='$odoo_task->write_date',";
						$qry.=" updatevon='odoo_sync'";
						$qry.=' WHERE projekttask_id='.$row->projekttask_id.';';
						//echo $qry;
						if(!$res=$fhc->db_query($qry))
							echo $fhc->errormsg;
						else
						{
							$qry2="UPDATE addon.tbl_odoo_sync
								SET odoo_lastupdate='$odoo_task->write_date', fhc_lastupdate='$odoo_task->write_date' 
								WHERE sync_id=$row->sync_id;";
							if(!$fhc->db_query($qry2))
								echo $fhc->errormsg;
							else
								echo '<span title="'.htmlspecialchars($qry).'">u</span>';
						}
					}
					else
						echo '.';
				}
			}// End of UPDATES
			
			// Check for parent
			if ($row->projekttask_fk != null)
			{
				// PROJEKT_PHASEN
				$qry = "SELECT odoo_id
					FROM
						fue.tbl_projekttask JOIN addon.tbl_odoo_sync ON (fhc_id=projekttask_id AND fhc_tablename='fue.tbl_projekttask')
					WHERE
						projekt_kurzbz='$projekt' AND projekttask_id=$row->projekttask_fk
					;";
				if($resparent = $fhc->db_query($qry))
				{
					if (!$parent=$fhc->db_fetch_object($resparent))
						echo 'Projekttask_id '.$row->projekttask_fk.' is not synced for ParentConnection<br/>';
					// SET OdooTaskParent
					elseif (!$txt=$odoo->updateTaskParent($parent->odoo_id,$row->odoo_id))
						echo $odoo->errormsg;
					else
						echo $txt;
				}
				else
					echo $fhc->errormsg;
			}// End of Parents
			
		}// End of INSERT/UPDATES

		
	}// End of PROJEKT_TASKS
}
?>
