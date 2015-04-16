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
//require_once(dirname(__FILE__).'/basis_db.class.php');

class odoo //extends basis_db
{
	public $odoo_conn;
	public $res;
	public $cat=array();
	public $errormsg='';
	public $name='OdooDB-Server';
	public $host='192.168.1.100';
	public $port='5432';
	public $dbname='fortyseeds';
	public $dbuser='pam';
	public $dbpassword='jfie02';
	public $odoo_user_id=5;
	public $odoo_project_id=2;
	
	public $server=array();
	
	/**
	 * Stellt eine Verbindung zum Odoo Server her
	 * @param $type Art der Verbindung (starttls | ldaps | plain)
	 * @param $ldap_server IP oder Name des LDAP Servers (ohne ldap:// davor)
	 * @param $ldap_bind_user DN des Users mit dem die Verbindung hergestellt werden soll (null fuer simple bind)
	 * @param $ldap_bind_password Passwort des ldap_bind_users (null fuer simple bind)
	 * @return true wenn erfolgreich, false im Fehlerfall
	 */
	public function newServer()
	{
		$this->server[] = new odoo();
		return count($this->server)-1;
	}
	
	/**
	 * Stellt eine Verbindung zum Odoo Server her
	 * @param $type Art der Verbindung (starttls | ldaps | plain)
	 * @param $ldap_server IP oder Name des LDAP Servers (ohne ldap:// davor)
	 * @param $ldap_bind_user DN des Users mit dem die Verbindung hergestellt werden soll (null fuer simple bind)
	 * @param $ldap_bind_password Passwort des ldap_bind_users (null fuer simple bind)
	 * @return true wenn erfolgreich, false im Fehlerfall
	 */
	public function connect()
	{
		//$this->debug("Odoo-DB Connect $this->host::$this->port");
		//if($this->debug)
		//	ldap_set_option(NULL, LDAP_OPT_DEBUG_LEVEL,7);
		$conn_string="host=$this->host port=$this->port dbname=$this->dbname user=$this->dbuser password=$this->dbpassword";
		if(!$this->odoo_conn = pg_connect($conn_string))
			return false;
		else
			return true;
	}

	/**
	 * Execute Query on Odoo DB
	 * @param $qry Query String
	 * @return result wenn erfolgreich, false im Fehlerfall
	 */
	public function db_query($qry)
	{
		if($res=pg_query($this->odoo_conn,$qry))
			return $res;
		else
		{
			$this->errormsg=pg_last_error();
			return false;
		}
	}
	
	/**
	 * Load ProjectTask from Odoo DB
	 * @param $id project_task.id
	 * @return result wenn erfolgreich, false im Fehlerfall
	 */
	public function loadTask($id)
	{
		$qry='SELECT * FROM project_task WHERE id='.$id.';';
		if($this->res=$this->db_query($qry))
			return $this->res;
		else
		{
			$this->errormsg=pg_last_error();
			return false;
		}
	}
	
	/**
	 * Load ProjectCategories from Odoo DB
	 * @return result wenn erfolgreich, false im Fehlerfall
	 */
	public function loadCategories()
	{
		$qry="SELECT id, name FROM public.project_category WHERE name='Projektphase' OR name='Arbeitspaket' OR name='Task';";
		if($this->res=$this->db_query($qry))
		{
			if (pg_num_rows($this->res)!=3)
				die ('Categories in Odoo are not set (Projektphase,Arbeitspaket,Task)!');
			for($i=0;$row = pg_fetch_object($this->res);$i++)
			{
				//$this->cat[] = new stdClass();
				$this->cat[$i]['id'] = $row->id;
				$this->cat[$i]['name'] = $row->name;
			}
			return true;
		}
		else
		{
			$this->errormsg=pg_last_error();
			return false;
		}
	}
	
	/**
	 * Load ProjectCategories from Tasks Odoo DB
	 * @return result wenn erfolgreich, false im Fehlerfall
	 */
	public function loadCategoriesByTaskId($id)
	{
		$qry='SELECT project_task_id, project_category_id FROM public.project_category_project_task_rel WHERE project_task_id='.$id.';';
		if($this->res=$this->db_query($qry))
		{
			return $this->res;
		}
		else
		{
			$this->errormsg=pg_last_error();
			return false;
		}
	}
	/**
	 * Load ProjectCategories from Odoo DB
	 * @return result wenn erfolgreich, false im Fehlerfall
	 */
	public function getCategoryIdByName($name)
	{
		foreach ($this->cat AS $cat)
			if ($cat['name']==$name)
				return $cat['id'];
		return false;
	}
	/**
	 * Load ProjectCategories from Odoo DB
	 * @return result wenn erfolgreich, false im Fehlerfall
	 */
	public function updateCategory($task_id,$cat_id)
	{
		//echo 'Updating Category: ',$task_id,$cat_id;
		if($res=$this->loadCategoriesByTaskId($task_id))
		{
			if (pg_num_rows($res)==0)
				$qry="INSERT INTO public.project_category_project_task_rel VALUES ($task_id,$cat_id);";
			elseif (pg_num_rows($res)==1)
			{
				$row=pg_fetch_object($res);
				if ($row->project_category_id!=$cat_id)
					$qry="INSERT INTO public.project_category_project_task_rel VALUES ($task_id,$cat_id);";
				else
					$qry="UPDATE public.project_category_project_task_rel SET project_category_id=$cat_id WHERE project_category_id=$row->project_category_id AND project_task_id=$task_id;";
			}
			else
			{
				$this->errormsg="To much categoris on Task $task_id !";
				return false;
			}
			if($this->res=$this->db_query($qry))
				return true;
			else
			{
				$this->errormsg=pg_last_error();
				return false;
			}
		}
		else
			$this->errormsg='Error Loading TaskCategory!';
		return false;
	}
	
	/**
	 * Update the ParentConnection from Tasks in Odoo DB
	 * @return result wenn erfolgreich, false im Fehlerfall
	 */
	public function updateTaskParent($parent_id,$task_id)
	{
		$update=true;
		//echo 'Updating ParentTask: ',$parent_id,$task_id;
		$qry="SELECT parent_id, task_id FROM public.project_task_parent_rel WHERE task_id=$task_id;";
		if($res=$this->db_query($qry))
		{
			if (pg_num_rows($res)==0)
				$qry="INSERT INTO public.project_task_parent_rel (parent_id, task_id) VALUES ($parent_id,$task_id);";
			elseif (pg_num_rows($res)==1)
			{
				$row=pg_fetch_object($res);
				if ($row->parent_id!=$parent_id)
					$qry="UPDATE public.project_task_parent_rel SET parent_id=$parent_id WHERE parent_id=$row->parent_id AND task_id=$task_id;";
				else
					$update=false;
			}
			else
			{
				$this->errormsg="To much parents on Task $task_id !";
				return false;
			}
			if($update)
			{
				if($this->res=$this->db_query($qry))
					return '<span title="'.htmlspecialchars($qry).'">p</span>';
				else
				{
					$this->errormsg=pg_last_error();
					return false;
				}
			}
			else
				return '.';
		}
		else
			$this->errormsg="Error Loading Parents from Task $task_id !";
		return false;
	}
	
	/**
	 * Load OdooTaskId by an FHC Id and Tablename from SyncTable
	 * @return result wenn erfolgreich, false im Fehlerfall
	 */
	public function getTaskIdByFHCId($db,$tablename,$fhcid)
	{
		// Get Id
		$qry = "SELECT odoo_id FROM addon.tbl_odoo_sync
					WHERE fhc_tablename='$tablename' AND fhc_id=$fhcid;";
				
		if($this->res=$db->db_query($qry))
		{
			if (!$row=$db->db_fetch_object($this->res))
				echo 'FHC_id '.$fhcid.' from Table '.$tablename.' is not synced!<br/>';
			else
				return $row->odoo_id;
		}
		else
		{
			$this->errormsg=$db->db_last_error();
			return false;
		}
	}
}

?>
