<?php

	class moysklad{
		
		private $login='';
		private $pass='';
		private $DB='';
		private $base_url='https://online.moysklad.ru/api/remap/1.2/';
		private $access_token='';
		private $goods = [];
		private $new_goods = [];
		private $ingredients = [];
		private $day = '2022-09-10';
		
		public function __construct($token){
			$this->access_token = $token;
		}

		public function set_db($DB){
			$this->DB = $DB;
		}
		
		//Получаем текущий склад
		public function get_current_store(){
			$params = [];
			$result = $this->curl_send($params,'report/stock/all/current','GET',true);
			print_r($result);
		}
		
		//Получаем склад на указанную дату
		public function get_store(){
			$params['filter'] = 'moment='.$this->day.' 18:00:00;stockMode=all;quantityMode=all';
			$result = $this->curl_send($params,'report/stock/all','GET',true);
			foreach($result['rows'] as $el){
				$uuid = str_replace('https://online.moysklad.ru/app/#good/edit?id=', '', $el['meta']['uuidHref']);
				$goods[$uuid]['name'] = $el['name'];
				$goods[$uuid]['price'] = $el['price'];
				$goods[$uuid]['stock'] = $el['stock'];
				$goods[$uuid]['uuid'] = $uuid;
				$goods[$uuid]['productid'] = str_replace('?expand=supplier','',str_replace('https://online.moysklad.ru/api/remap/1.2/entity/product/', '', $el['meta']['href']));
			}
			$this->goods = $goods;
		}
		
		//Производим все чего не хватает
		public function create_items(){
			foreach($this->goods as $good){
				if($good['stock']<0){
					print_r($good);
					echo"<br><br>";
					$data['item_uuid'] = $good['uuid'];
					$result = $this->DB->get_record('recipe',$data);
					if($result->num_rows>0){
						$row = $result->fetch_array(MYSQLI_ASSOC);
						$this->create_item($row['id'],$row['item_uuid'],$row['quantity'],$row['name']);
					}
				}
			}
		}
		
		
		//Производим по рецепту
		public function create_item($id,$uuid,$cols,$name){
			$data['recipe_id'] = $id;
			$result = $this->DB->get_record('recipe_ingredients',$data);
			$row = $result->fetch_array(MYSQLI_ASSOC);
			$total_price = 0;
			do{
				if(isset($this->goods[$row['item_uuid']])){
				}else{
					$this->goods[$row['item_uuid']]['stock'] = 0;
					$this->goods[$row['item_uuid']]['uuid'] = $row['item_uuid'];
					$this->goods[$row['item_uuid']]['price'] = 0;
					$this->goods[$row['item_uuid']]['name'] = $row['name'];
				}
				if($this->goods[$row['item_uuid']]['stock']>$row['quantity']){
					//echo $row['name']." на складе достаточно";
				}else{
					$rdata['item_uuid'] = $row['item_uuid'];
					$rresult = $this->DB->get_record('recipe',$rdata);
					if($result->num_rows>0){
						$rrow = $rresult->fetch_array(MYSQLI_ASSOC);
						$this->create_item($rrow['id'],$rrow['item_uuid'],$rrow['quantity'],$rrow['name']);
					}else{
						echo "Рецепт для ".$row['name']." не найдел";
					}
				}
				if(isset($this->ingredients[$row['item_uuid']])){
				}else{
					$this->ingredients[$row['item_uuid']]['uuid'] = $row['item_uuid'];
					$this->ingredients[$row['item_uuid']]['stock'] = 0;
					$this->ingredients[$row['item_uuid']]['price'] = round($this->goods[$row['item_uuid']]['price']/100,2);
					$this->ingredients[$row['item_uuid']]['name'] = $row['name'];
				}
				$this->goods[$row['item_uuid']]['stock'] -= $row['quantity'];
				$this->ingredients[$row['item_uuid']]['stock'] += $row['quantity'];
				$total_price += $row['quantity']*$this->ingredients[$row['item_uuid']]['price'];
				$this->goods[$uuid]['stock'] += $cols;
				if(isset($this->new_goods[$uuid])){
				}else{
					$this->new_goods[$uuid]['uuid'] = $uuid;
					$this->new_goods[$uuid]['stock'] = 0;
					$this->new_goods[$uuid]['price'] = round($total_price/$cols,2);
					$this->new_goods[$uuid]['name'] = $name;
					$this->goods[$uuid]['price'] = $this->new_goods[$uuid]['price']*100;
				}
				echo $name."<br><br>";
				print_r($this->ingredients);
				echo"<br><br>";
				$this->new_goods[$uuid]['stock'] += $cols;
			} while ($this->goods[$uuid]['stock']<0);
		}
		
		//Создаем отгрузку
		public function create_shipment(){
			$params = [];
			if(count($this->ingredients)>0){
				$params['organization']['meta']['href'] = 'https://online.moysklad.ru/api/remap/1.2/entity/organization/95da3bba-3734-11ed-0a80-0bbb00229b6c';
				$params['organization']['meta']['type'] = 'organization';
				$params['organization']['meta']['mediaType'] = 'application/json';
				$params['agent']['meta']['href'] = 'https://online.moysklad.ru/api/remap/1.2/entity/counterparty/7ab8e9af-640e-11ed-0a80-0de90050a353';
				$params['agent']['meta']['type'] = 'counterparty';
				$params['agent']['meta']['mediaType'] = 'application/json';
				$params['store']['meta']['href'] = 'https://online.moysklad.ru/api/remap/1.2/entity/store/97631bb8-373b-11ed-0a80-047f0022e316';
				$params['store']['meta']['type'] = 'store';
				$params['store']['meta']['mediaType'] = 'application/json';
				$params['moment'] = $this->day.' 3:00:00';
				$position = [];
				$count = 0;
				foreach($this->ingredients as $ingredient){
					$position[$count]['quantity'] = $ingredient['stock'];
					$position[$count]['price'] = $ingredient['price']*100;
					$position[$count]['discount'] = 0;
					$position[$count]['vat'] = 0;
					$position[$count]['assortment']['meta']['href'] ='https://online.moysklad.ru/api/remap/1.2/entity/product/'.$this->goods[$ingredient['uuid']]['productid'];
					$position[$count]['assortment']['meta']['type'] ='product';
					$position[$count]['assortment']['meta']['mediaType'] ='application/json';
					$count++;
				}
				$params['positions'] = $position;
				$result = $this->curl_send($params,'entity/demand','POST',true);
			}
		}
		
		
		//Создаем приемку
		public function create_acceptance(){
			$params = [];
			if(count($this->new_goods)>0){
				$params['organization']['meta']['href'] = 'https://online.moysklad.ru/api/remap/1.2/entity/organization/95da3bba-3734-11ed-0a80-0bbb00229b6c';
				$params['organization']['meta']['type'] = 'organization';
				$params['organization']['meta']['mediaType'] = 'application/json';
				$params['agent']['meta']['href'] = 'https://online.moysklad.ru/api/remap/1.2/entity/counterparty/7ab8e9af-640e-11ed-0a80-0de90050a353';
				$params['agent']['meta']['type'] = 'counterparty';
				$params['agent']['meta']['mediaType'] = 'application/json';
				$params['store']['meta']['href'] = 'https://online.moysklad.ru/api/remap/1.2/entity/store/95dc26e2-3734-11ed-0a80-0bbb00229b72';
				$params['store']['meta']['type'] = 'store';
				$params['store']['meta']['mediaType'] = 'application/json';
				$params['moment'] = $this->day.' 2:00:00';
				$position = [];
				$count = 0;
				foreach($this->new_goods as $new_good){
					$position[$count]['quantity'] = $new_good['stock'];
					$position[$count]['price'] = $new_good['price']*100;
					$position[$count]['discount'] = 0;
					$position[$count]['vat'] = 0;
					$position[$count]['assortment']['meta']['href'] ='https://online.moysklad.ru/api/remap/1.2/entity/product/'.$this->goods[$new_good['uuid']]['productid'];
					$position[$count]['assortment']['meta']['type'] ='product';
					$position[$count]['assortment']['meta']['mediaType'] ='application/json';
					$count++;
				}
				$result = $this->curl_send($params,'entity/supply','POST',true);
				$result2 = $this->curl_send($position,str_replace('https://online.moysklad.ru/api/remap/1.2/', '',$result['meta']['href']).'/positions','POST',true);
			}
		}
		
		//Убираем пересорт на складах
		public function check_regrading(){
			$params_ingridients = [];
			$params_ingridients['filter'] = 'moment='.$this->day.' 5:00:00;stockMode=negativeOnly;quantityMode=negativeOnly;store=https://online.moysklad.ru/api/remap/1.2/entity/store/97631bb8-373b-11ed-0a80-047f0022e316';
			$params_goods = [];
			$params_goods['filter'] = 'moment='.$this->day.' 5:00:00;stockMode=negativeOnly;quantityMode=negativeOnly;store=https://online.moysklad.ru/api/remap/1.2/entity/store/95dc26e2-3734-11ed-0a80-0bbb00229b72';
			$result_ingridients = $this->curl_send($params_ingridients,'report/stock/all','GET',true);
			$result_goods = $this->curl_send($params_goods,'report/stock/all','GET',true);
			if(count($result_ingridients['rows'])>0){
				$params_move_ingridients = [];
				$params_move_ingridients['organization']['meta']['href'] = 'https://online.moysklad.ru/api/remap/1.2/entity/organization/95da3bba-3734-11ed-0a80-0bbb00229b6c';
				$params_move_ingridients['organization']['meta']['type'] = 'organization';
				$params_move_ingridients['organization']['meta']['mediaType'] = 'application/json';
				$params_move_ingridients['targetStore']['meta']['href'] = 'https://online.moysklad.ru/api/remap/1.2/entity/store/97631bb8-373b-11ed-0a80-047f0022e316';
				$params_move_ingridients['targetStore']['meta']['type'] = 'store';
				$params_move_ingridients['targetStore']['meta']['mediaType'] = 'application/json';
				$params_move_ingridients['sourceStore']['meta']['href'] = 'https://online.moysklad.ru/api/remap/1.2/entity/store/95dc26e2-3734-11ed-0a80-0bbb00229b72';
				$params_move_ingridients['sourceStore']['meta']['type'] = 'store';
				$params_move_ingridients['sourceStore']['meta']['mediaType'] = 'application/json';
				$params_move_ingridients['moment'] = $this->day.' 4:00:00';
				$position = [];
				$count=0;
				foreach($result_ingridients['rows'] as $ingridients){
					$position[$count]['quantity'] = abs($ingridients['stock']);
					$position[$count]['price'] = $ingridients['price'];
					$position[$count]['discount'] = 0;
					$position[$count]['vat'] = 0;
					$position[$count]['assortment']['meta']['href'] = str_replace('?expand=supplier','',$ingridients['meta']['href']);
					$position[$count]['assortment']['meta']['type'] = 'product';
					$position[$count]['assortment']['meta']['mediaType'] = 'application/json';
					$count++;
				}
				$params_move_ingridients['positions'] = $position;
				$result = $this->curl_send($params_move_ingridients,'entity/move','POST',true);
			}
			if(count($result_goods['rows'])>0){
				$params_move_goods = [];
				$params_move_goods['organization']['meta']['href'] = 'https://online.moysklad.ru/api/remap/1.2/entity/organization/95da3bba-3734-11ed-0a80-0bbb00229b6c';
				$params_move_goods['organization']['meta']['type'] = 'organization';
				$params_move_goods['organization']['meta']['mediaType'] = 'application/json';
				$params_move_goods['targetStore']['meta']['href'] = 'https://online.moysklad.ru/api/remap/1.2/entity/store/95dc26e2-3734-11ed-0a80-0bbb00229b72';
				$params_move_goods['targetStore']['meta']['type'] = 'store';
				$params_move_goods['targetStore']['meta']['mediaType'] = 'application/json';
				$params_move_goods['sourceStore']['meta']['href'] = 'https://online.moysklad.ru/api/remap/1.2/entity/store/97631bb8-373b-11ed-0a80-047f0022e316';
				$params_move_goods['sourceStore']['meta']['type'] = 'store';
				$params_move_goods['sourceStore']['meta']['mediaType'] = 'application/json';
				$params_move_goods['moment'] = $this->day.' 4:00:00';
				$position = [];
				$count=0;
				foreach($result_goods as $goods){
					$position[$count]['quantity'] = abs($goods['stock']);
					$position[$count]['price'] = $goods['price'];
					$position[$count]['discount'] = 0;
					$position[$count]['vat'] = 0;
					$position[$count]['assortment']['meta']['href'] = str_replace('?expand=supplier','',$goods['meta']['href']);
					$position[$count]['assortment']['meta']['type'] = 'product';
					$position[$count]['assortment']['meta']['mediaType'] = 'application/json';
					$count++;
				}
				$params_move_goods['positions'] = $position;
				$result = $this->curl_send($params_move_goods,'entity/move','POST',true);
			}
		}
		
		//Проводим документы
		public function get_created_item(){
			$this->create_shipment();
			$this->create_acceptance();
			$this->check_regrading();
		}
		
		private function curl_send($params,$method,$type,$auth = false){
			$url = $this->base_url.$method;
			$curl = curl_init();
			
			switch($type){
				case 'POST':
					curl_setopt($curl, CURLOPT_CUSTOMREQUEST,$type);
					curl_setopt($curl, CURLOPT_URL, $url);
					curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
					if($auth){
						curl_setopt($curl, CURLOPT_USERPWD, $this->login.':'.$this->pass);
					}
					if(strlen($this->access_token)>0){
						curl_setopt($curl, CURLOPT_HTTPHEADER, array(
							"x-access-token:" . $this->access_token,
							"Content-Type: application/json"
						));
					}else{
						curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
					}
					curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				case 'GET':
					$query = http_build_query($params, '', '&');
					curl_setopt($curl, CURLOPT_URL, $url.'?'.$query);
					curl_setopt($curl, CURLOPT_HTTPHEADER, array(
						"Authorization: Bearer " . $this->access_token,
						"Content-Type: application/json"
					));
					curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			}
			
			$out = curl_exec($curl);
			curl_close($curl);
			
			$response = json_decode($out, true);
			return $response;
		}
	}