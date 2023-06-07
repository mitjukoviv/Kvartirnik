<?php

	class sql{
		
		private $host='';
		private $user='';
		private $pass='';
		private $data='';
		
		public function __construct($host,$user,$pass,$data){
			$this->host = $host;
			$this->user = $user;
			$this->pass = $pass;
			$this->data = $data;
		}
		
		public function insert_record($table,$data){
			$columns = implode(', ', array_keys($data));
			$values  = implode(', ', array_values($data));
			$db=mysqli_connect($this->host,$this->user,$this->pass,$this->data);
			mysqli_set_charset($db,"utf8");
			$sel=mysqli_query($db,'SET NAMES utf8');
			$sql = "INSERT INTO $table ($columns) VALUES ($values)";
			$sel=mysqli_query($db,$sql);
			mysqli_close($db);
		}
		
		public function delete_record($table,$data){
			$columns = array_keys($data);
			$values  = array_values($data);
			$query = '';
			foreach($columns as $key => $val){
				$query.=$val." = '".$values[$key]."' AND ";
			}
			$query.='1=1';
			$db=mysqli_connect($this->host,$this->user,$this->pass,$this->data);
			mysqli_set_charset($db,"utf8");
			$sel=mysqli_query($db,'SET NAMES utf8');
			$sql = "DELETE FROM $table WHERE $query";
			$sel=mysqli_query($db,$sql);
			mysqli_close($db);
		}
		
		public function update_record($table,$data,$field,$value){
			$columns = array_keys($data);
			$values  = array_values($data);
			$query = '';
			foreach($columns as $key => $val){
				$query.=$val." = '".$values[$key]."' AND ";
			}
			$query.='1=1';
			$db=mysqli_connect($this->host,$this->user,$this->pass,$this->data);
			mysqli_set_charset($db,"utf8");
			$sel=mysqli_query($db,'SET NAMES utf8');
			$sql = "UPDATE $table set $field = $value WHERE $query";
			$sel=mysqli_query($db,$sql);
			mysqli_close($db);
			return $sel;
		}
		
		public function get_record($table,$data){
			$columns = array_keys($data);
			$values  = array_values($data);
			$query = '';
			foreach($columns as $key => $val){
				$query.=$val." = '".$values[$key]."' AND ";
			}
			$query.='1=1';
			$db=mysqli_connect($this->host,$this->user,$this->pass,$this->data);
			mysqli_set_charset($db,"utf8");
			$sel=mysqli_query($db,'SET NAMES utf8');
			$sql = "SELECT * FROM $table WHERE $query";
			$sel=mysqli_query($db,$sql);
			mysqli_close($db);
			return $sel;
		}
		
		public function set_sql($sql){
			$db=mysqli_connect($this->host,$this->user,$this->pass,$this->data);
			mysqli_set_charset($db,"utf8");
			$sel=mysqli_query($db,'SET NAMES utf8');
			$sel=mysqli_query($db,$sql);
			mysqli_close($db);
			return $sel;
		}
	}