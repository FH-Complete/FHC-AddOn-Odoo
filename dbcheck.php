<?php
/* Copyright (C) 2013 FH Technikum-Wien
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
 */
/**
 * FH-Complete Addon Template Datenbank Check
 *
 * Prueft und aktualisiert die Datenbank
 */
require_once('../../config/system.config.inc.php');
require_once('../../include/basis_db.class.php');
require_once('../../include/functions.inc.php');
require_once('../../include/benutzer.class.php');
require_once('../../include/benutzerberechtigung.class.php');
require_once('../../include/appdaten.class.php');
require_once('include/odoo.class.php');

// Datenbank Verbindung
$db = new basis_db();

echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
        "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<link rel="stylesheet" href="../../skin/fhcomplete.css" type="text/css">
	<link rel="stylesheet" href="../../skin/vilesci.css" type="text/css">
	<title>Addon Datenbank Check</title>
</head>
<body>
<h1>Addon Odoo-Sync Datenbank Check</h1>';

$uid = get_uid();
$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($uid);

if(!$rechte->isBerechtigt('basis/addon'))
{
	exit('Sie haben keine Berechtigung für die Verwaltung von Addons');
}

echo '<h2>Configurations</h2>'; // Code fuer die Datenbankanpassungen
// Admin User check
$benutzer = new benutzer();
if ($benutzer->load('odoo'))
	echo 'Admin User for Odoo-Sync is ',$benutzer->uid,'.<br/>';
else
{
	$benutzer->uid='odoo';
	$benutzer->vorname='Odoo-Sync';
	$benutzer->nachname='AddOn-User';
	if ($benutzer->save())
		echo 'Admin User for Odoo-Sync "',$benutzer->uid,'" is created!<br/>';
	else
		die ('Benutzer admin ist nicht vorhanden!'.$benutzer->errormsg);
}
// Config Check
$app = new appdaten();
if ($app->getSetupByUid('odoo','odoo'))
	echo 'AddOn-Data for Odoo-Sync are already set!',$benutzer->uid,'<br/>';
else
{
	$data = new odoo();
	$index=$data->newServer();
	//echo $index;
	//echo var_dump($data);
	$data->server[$index]->name='OdooDB-Server';
	$data->server[$index]->host='192.168.1.100';
	$data->server[$index]->port='5432';
	$data->server[$index]->dbname='fortyseeds';
	$data->server[$index]->dbuser='pam';
	$data->server[$index]->dbpassword='jfie02';
	$app->__set('uid','odoo');
	$app->__set('app','odoo');
	$app->__set('appversion','0.9');
	$app->__set('version','1');
	$app->__set('bezeichnung','setup');
	$app->__set('daten',json_encode($data));
	$app->save();
	echo 'AddOn-Data for Odoo-Sync are created! ',$benutzer->uid,'<br/>';
}


echo '<h2>Aktualisierung der Datenbank</h2>'; // Code fuer die Datenbankanpassungen
//Neue Berechtigung für das Addon hinzufügen
if($result = $db->db_query("SELECT * FROM system.tbl_berechtigung WHERE berechtigung_kurzbz='addon/datenimport'"))
{
	if($db->db_num_rows($result)==0)
	{
		$qry = "INSERT INTO system.tbl_berechtigung(berechtigung_kurzbz, beschreibung) 
				VALUES('addon/odoo','AddOn Odoo-Sync');";

		if(!$db->db_query($qry))
			echo '<strong>Berechtigung: '.$db->db_last_error().'</strong><br>';
		else 
			echo 'Neue Berechtigung addon/datenimport hinzugefuegt!<br>';
	}
}

// Datenimport (di) Quellen
if(!$result = @$db->db_query("SELECT 1 FROM addon.tbl_odoo_sync"))
{

	$qry = 'CREATE TABLE addon.tbl_odoo_sync
			(
				sync_id serial,
				fhc_tablename varchar(64),
				fhc_id bigint,
				odoo_id bigint,
				fhc_lastupdate timestamp,
				odoo_lastupdate timestamp,
				deleted boolean NOT NULL DEFAULT false,
				CONSTRAINT pk_odoo_sync PRIMARY KEY (sync_id)
			);
			GRANT SELECT, UPDATE, INSERT, DELETE ON addon.tbl_odoo_sync TO vilesci;';

	if(!$db->db_query($qry))
		echo '<strong>addon.tbl_odoo_sync: '.$db->db_last_error().'</strong><br>';
	else 
		echo ' addon.tbl_odoo_sync: Tabelle addon.tbl_odoo_sync hinzugefuegt!<br>';

}

/* Datenimport (di) Mapping
if(!$result = @$db->db_query("SELECT 1 FROM addon.tbl_di_mapping"))
{

	$qry = 'CREATE TABLE addon.tbl_di_mapping
			(
				dim_id serial,
				diq_id bigint,
				diq_attribute varchar(256),
				fhc_attribute varchar(256),
				fhc_datatype varchar(32),
				insertamum timestamp,
				insertvon varchar(32),
				updateamum timestamp,
				updatevon varchar(32),
				CONSTRAINT pk_dim PRIMARY KEY (dim_id)
			);
			GRANT SELECT, UPDATE, INSERT, DELETE ON addon.tbl_di_mapping TO vilesci;
			ALTER TABLE addon.tbl_di_mapping ADD CONSTRAINT fk_diq_id FOREIGN KEY (diq_id) 
				REFERENCES addon.tbl_di_quelle(diq_id)  
				ON UPDATE CASCADE ON DELETE RESTRICT';

	if(!$db->db_query($qry))
		echo '<strong>addon.tbl_di_mapping: '.$db->db_last_error().'</strong><br>';
	else 
		echo ' addon.tbl_di_mapping: Tabelle addon.tbl_di_mapping hinzugefuegt!<br>';

}*/

echo '<br>Aktualisierung abgeschlossen<br><br>';
echo '<h2>Gegenprüfung</h2>';

// Liste der verwendeten Tabellen / Spalten des Addons
$tabellen=array(
	"addon.tbl_odoo_sync"  => array("sync_id", "fhc_tablename", "fhc_id", "odoo_id", "fhc_lastupdate", "odoo_lastupdate", "deleted")
//	,"addon.tbl_di_mapping" => array("dim_id","diq_id","diq_attribute","fhc_attribute","fhc_datatype","insertamum","insertvon","updateamum","updatevon")
);
$tabs=array_keys($tabellen);
$i=0;
foreach ($tabellen AS $attribute)
{
	$sql_attr='';
	foreach($attribute AS $attr)
		$sql_attr.=$attr.',';
	$sql_attr=substr($sql_attr, 0, -1);

	if (!@$db->db_query('SELECT '.$sql_attr.' FROM '.$tabs[$i].' LIMIT 1;'))
		echo '<BR><strong>'.$tabs[$i].': '.$db->db_last_error().' </strong><BR>';
	else
		echo $tabs[$i].': OK - ';
	flush();
	$i++;
}
?>
