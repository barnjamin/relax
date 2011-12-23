<?php

class couch{

	private $options = array(
		'host'=>'localhost',
		'port'=>5984,
		'ip' => '127.0.0.1',
        'timeout' => 2,
        'keep-alive'=> true,
        'http-log'=> false,
		'username'=>null,
		'password'=>null
	);

	public static $database;	

	public function __construct($username = null, $password = null, $db = null , $host = null, $port = null, $ip = null) {
        $this->options['host']     = (isset($host))?(string)$host:$this->options['host'];
        $this->options['port']     = (isset($port))?(int)$port:$this->options['port'];
        $this->options['username'] = $username;
        $this->options['password'] = $password;
		
		$this->database = $db;
    }
	
	
	/*
	*Creates a database with the name of whatever is stored in $database
	*/
	public function create_db($name=null){
		$this->database = (!empty($name))?$name:$this->database;
		$url = $this->build_url();
		$result = $this->execute_query('PUT', $url);
		return $result;
	}
	/*
	*Deletes the database currently stored in $database
	*/
	public function delete_db($name=null){
		$this->database = (!empty($name))?$name:$this->database;
		$url = $this->build_url();
		$result = $this->execute_query('DELETE', $url);
		return $result;
	}
	
	//returns database info object
	public function db_info(){
		$url = $this->build_url();
		$result = $this->execute_query('GET', $url);
		return $result;
	}
	//returns the integer value that should be unique by adding the total docs + deleted docs 
	public function next_id(){
		$result = $this->db_info();
		$id = $result['doc_count']+$result['doc_del_count'];
		return $id;
	}
	/*	Creates a document in whatever database is currently set with the document ID that is set
	*	
	*	$data is an array of key value pairs
	*	
	*/
	public function create_doc($data=null, $doc_id=null){
		if(!empty($doc_id)&&!empty($data)){
			$url = $this->build_url($doc_id);
			$data = json_encode($data);
			$result = $this->execute_query('PUT', $url, $data);
			if(isset($result['ok'])){
				return $result;
			}else{
				return false;
			}
		}else if(empty($doc_id)&&!empty($data)){
			$url = $this->build_url();
			$data = json_encode($data);
			$result = $this->execute_query('POST', $url, $data);
			if(isset($result['ok'])){
				return $result;
			}else{
				return false;
			}
		}else  return array('error'=>'Please provide a document ID and data');
	}
	
	
	/*
	* Gets a document given a document id and optionally whatever parameters in the form of an array (i.e. key="ben.guidarelli@newdigs.com")
	*/
	public function get_doc($doc_id=null, $params = null){
		$url = $this->build_url($doc_id, $params);
		$doc = $this->execute_query('GET', $url);
		if(isset($doc['_id'])){
			return $doc;
		}else{
			return false;
		}
	}

	public function get_all($design=false){
		$url = $this->build_url('_all_docs', array('include_docs'=>'true'));
		$doc = $this->execute_query('GET', $url);
		if(isset($doc['rows'])){
			if(!$design){
				$rows = array();
				foreach($doc['rows'] as $row){	
					if(strpos($row['id'], '_design')===0){}
					else $rows[] = $row['doc'];
				}			
				return $rows;
			}
			return $doc['rows'];
		}else{
			return false;
		}
	}
	
	public function get_attributes($doc_id=null, $fields=null, $params=null){
		$doc = $this->get_doc($doc_id, $params);
		$returning = array();
		foreach($fields as $field){
			$returning[$field] = $doc[$field];
		}
		return $returning;
	}
	public function get_attribute($doc_id=null, $field=null, $params=null){
		$doc = $this->get_doc($doc_id, $params);
		return $doc[$field];
	}
	/*
	*Checks to see if a document exists and is accessible with a head request. I don't remember why I added it but I'll leave it anyway
	*/
	public function exists($doc_id = null){
		if($doc_id == null) return;
		$result = $this->get_head($doc_id);
		if(strpos($result['status'], '200')){
			return true;
		}else {
			return false;
		}
	}

	/*
	* So this accepts an array which is the object you want to add or merge with an existing document
	*/
	public function add_obj($doc_id, $data){
		$doc = $this->get_doc($doc_id);
		
		$keys = array_keys($data);
		if(isset($doc[$keys[0]])){
			$doc = array_merge_recursive($doc, $data);
		}else{
			$doc[$keys[0]]=$data[$keys[0]];
		}
		
		$url = $this->build_url($doc_id);
		$data = json_encode($doc);
		$revised = $this->execute_query('PUT', $url, $data);
		if(isset($revised['ok'])){
			return $revised;
		}else{
			return false;
		}
		
	}
	public function set_attribute($doc_id=null, $field=null, $value=null){
		$doc = $this->get_doc($doc_id, $params);
		$doc[$field] = $value;
		$result = $this->save($doc);
		return $doc[$field];
	}
	
	
	/*
	*This takes an array to be turned into an object and PUTS it to the document id you give it.  This will completely overwrite whatever is there.
	*/
	public function revise_doc($doc_id, $data){
		$url = $this->build_url($doc_id);
		$data = json_encode($data);
		$revised = $this->execute_query('PUT', $url, $data);
		if(isset($revised['ok'])){
			return $revised;
		}else{
			return false;
		}
	}
	/*
	*This takes a document that still has its _id and _rev and saves it
	*/
	public function save($data=null){
		if($data==null)return;
		if(!isset($data['_id'])||!isset($data['_rev'])){
			$result = $this->create_doc($data);
			return $result;
		}
		$url = $this->build_url($data['_id']);
		$data = json_encode($data);
		$revised = $this->execute_query('PUT', $url, $data);
		if(isset($revised['ok'])){
			return $revised;
		}else{
			return false;
		}
	}
	
	
	/*
	*Deletes a document or a specific revision of a document
	*/
	public function delete_doc($doc_id=null, $revision=null){
		if($revision=='ALL'){
			$url = $this->build_url($doc_id);
		}else{
			$rev = (!empty($revision))?$revision:trim($this->get_head($doc_id, 'Etag'), '"');;
			$url = $this->build_url($doc_id, array('rev'=>$rev));		
		}
		$result = $this->execute_query('DELETE', $url, null);
		if(isset($result['ok'])){
			return $result;
		}else{
			return false;
		}
	}
		//takes a document id and the number of revisions you want to keep and deletes all but those
	public function delete_revs($doc_id=null, $num=null){
		$data = array('revs_info'=>'true');
		$result = $this->get_doc($doc_id, $data);
		if(count($result['_revs_info'])>$num){
			for($x=$num; $x<count($result['_revs_info']); $x++){
				$this->delete_doc($result['_id'], $result['_revs_info'][$x]['rev']);			
			}
		}
		$resulting = $this->get_doc($doc_id);
		return $resulting;
	}
	
	
	/*
	*Returns a specific revision of a document 
	*/
	public function get_rev($doc_id, $num){
		$data = array('revs_info'=>'true');		
		$result = $this->get_doc($doc_id, $data);		
		
		foreach($result['_revs_info'] as $value){
			$rev_num = (int)substr($value['rev'], 0, 1);
			if($rev_num===$num){
				if($value['status']=='available'){
					$dat = array('rev'=>$value['rev']);
					$frezult = $this->get_doc($doc_id, $dat);
					return $frezult;
				}
			}
		}
	}
	

	//Takes the name of the desgin doc, the view name, and an array of parameters to find	
	public function get_view($design_doc=null, $view=null, $params=null, $raw=false){
		$url = $this->build_url($this->build_view($design_doc, $view), $params);
		$result = $this->execute_query('GET', $url);
		if($raw) return $result;
		if(isset($result['rows'])&&count($result['rows'])>0){
			return $result['rows'];
		}else{
			return false;
		}
	}
	public function get_list($design_doc=null, $list=null, $view=null, $params=null){
		$url = $this->build_url($this->build_view($design_doc, $view), $params);
		$result = $this->execute_query('GET', $url);
		if(isset($result['rows'])&&count($result['rows'])>0){
			return $result['rows'];
		}else{
			return false;
		}
	}
	/*
	*Include an array of parameters like array('since'=>25);
	*/
	public function get_changes($params){
		$url = $this->build_url('_changes', $params);
		$result = $this->execute_query('GET', $url);
		return $result;
	}
	
	/*
	*Uses PHPs Build in curl functionality to make requests to the couchdb when provided an HTTP verb(method), the RESTful URL, and a json object as the $data, the $header is for a head request
	*/
	private function execute_query($method, $url, $data=null, $header=false){

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_NOBODY, $header);
		curl_setopt($ch, CURLOPT_HEADER, $header);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
		//if($this->options['password'])curl_setopt($ch, CURLOPT_USERPWD, $this->options['username'].':'.$this->options['password']);
		if(!empty($data))curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

		
		$result = curl_exec($ch);

		if(!$header){
			$result = json_decode($result, true);
		}
		
		curl_close($ch);
		return $result;
	
	}
	
	
	/*
	*Makes a head request. Useful for just getting a piece of the headers. Second favorite function name.
	*/
	private function get_head($doc_id, $chunk=null){
		$url = $this->build_url($doc_id);
		$head = $this->execute_query('HEAD', $url, null, true);
		$head = $this->http_parse_headers($head);
		if(isset($chunk)){
			return $head[$chunk];
		}else{
			return $head;
		}

	}
	
	/*
	*Builds the view section of the url
	*/
	private function build_view($design_doc=null, $view=null, $include_docs=false){
			$view = "_design/".$design_doc."/_view/".$view;
			$view .= ($include_docs)?"?include_docs=true":"";
			return $view;	
	}
	
	
	/*
	*Builds the RESTful url of what youre trying to access
	*Can accept an array for 'key' and url encodes it correctly. I think. its worked so far for me.
	*/
	private function build_url($doc_id=null, $param=null){
	
		$url = 'http://'.$this->options['host']. ':'. $this->options['port'] . '/' . $this->database;
		$url .= ($doc_id)? '/'.$doc_id:'';
		
		if($param && count($param)>0){
			$url .= '?';
			foreach($param as $key=>$value){
				if($key=='key'||$key=='startkey'||$key=='endkey'){
					if(is_array($value)){
						$url .= $key .'=[';
						foreach($value as $v){
							$url .= '"'.urlencode($v).'",';
						}
						$url = rtrim($url, ",").']';
					}else{
						$url.= $key.'="'.urlencode($value).'"&';
					}
				}else{
					$url.= $key.'='.$value.'&';
				}
			}
			$url = rtrim($url, '&');
		}
		
		return $url;
	
	}
	/*
	*Separates the http headers into an associative array (copied from php docs with a couple changes)
	*/
	private function http_parse_headers($headers=false){
		if($headers === false){
			return false;
			}
		$headers = explode("\r\n",$headers);
		foreach($headers as $value){
			$header = explode(": ",$value);
			if($header[0]&&!isset($header[1])){
				$headerdata['status'] = $header[0];
				}
			else if($header[0] && isset($header[1]) && $header[1]){
				$headerdata[$header[0]] = $header[1];
				}
			}
		return $headerdata;
	}

	
}



?>