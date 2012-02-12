<?php
/*
BASIC MODULE EXAMPLE
this is a basic module example. modules can be as basic or as complex
as you want. they are written as a php class and you can add as many
functions as you want, include new classes etc.
  
EXAMPLES OF HOW TO CONNECT TO THE DATABASE
all db functions use mysql queries. 
eg $rows and $params will be somthing like: 
 
$params = "WHERE id = 1"
$rows	= "row_name varchar(255)"
$values = "'val1','val2','val3'" 

google php mysql queries to learn more
  
CREATE TABLE
DBconnect::create($table,$rows);
each table will automatically have the id row added and set as primary.
 
ADD ROW TO TABLE
DBconnect::insert($table,$rows,$values); 
 
UPDATE ROW IN TABLE
DBconnect::update($table,$row,$value,$params);
 
DELETE ROW FROM TABLE
DBconnect::delete($table,$row,$value);

DROP TABLE FROM DB
DBconnect::drop($table);
 
QUERY TABLE
DBconnect::query($row,$table,$params);
this will bring back an array is result is an array or string if not.

QUERY TABLE ARRAY
DBconnect::queryArray($row,$table,$params);
this will always produce an array even if there is only 1 result. 
*/

//-----stackOverflow MODULE-----//

class stackOverflow extends DBconnect {
		
	//THE VARS
	//these vars can be assessed anywhere in
	//the module by using $this->page["name"], or 
	//$this->site["keywords"] etc.
	var $module;
	var $site;
	var $menu;
	var $page;
	var $blog;
	var $news;
	var $brick;
	var $settings;
	
	//SETUP FUNCTION
	//this function is automatically called
	//depending on wether the module is already installed.
	//you should create all your tables within this function.
	public function setupModule(){
			
		//create the stack feed settings table
		$rows = "
			user_id int(11) not null,
			username varchar(30) not null,
			stack_limit int(11) not null default '1',
			rep int(11) not null,
			last_updated int(11) not null default '0'
		";
		$success = DBconnect::create("stackoverflow_settings",$rows);
		
		//create the stack answers table
		$rows = "
			title varchar(255) not null,
			content varchar(5000) not null,
			upcount int(11) not null,
			downcount int(11) not null,
			views int(11) not null,
			score int(11) not null,
			link varchar(100) not null
		";			
		$success = DBconnect::create("stackoverflow_feed",$rows);
		
		//insert a row into our table
		$rows 	= "user_id";
		$values	= "'0'";	
		DBconnect::insert("stackoverflow_settings",$rows,$values);
		
		//if the function returns true the module
		//will be installed and setup will not run
		//again. if not setup will run on every page load.
		if($success){
			return true;
		}	
		
	}
	
	//RUN FUNCTION
	//this is the core of the module which you should 
	//put all the main functionality. feel free to add functions
	//and call them from here.
	public function runModule(){
		
		//set the modual template var to only send the
		//module information to a specific template
		//by default the information will be global.
		//$this->module["template"] = "page.tpl";
		
		//get the stack settings info from the database to send to the page
		$stackSettings 				= DBconnect::query("*","stackoverflow_settings","");
		$this->module["settings"]	= $stackSettings;	
		
		//check if it has been a day since last update
		//if it has request tweets from twitter and store
		//in db else just use db tweets.
		$time = time();
		$diff = $stackSettings["last_updated"]+86400;
		if($time > $diff){
			
			//its been a day - fetch new answers
			$STACK 			= new stackAPI();
			$STACK->userid 	= $stackSettings["user_id"];
			$STACK->limit	= $stackSettings["stack_limit"];
			$STACK->init();
			$answers 		= $STACK->getAnswers();
			$username 		= $STACK->getUser();
			$rep 			= $STACK->getRep();
			
			//update stack settings
			$params = "WHERE id = 1";
			DBconnect::update("stackoverflow_settings","username",$username,$params);
			DBconnect::update("stackoverflow_settings","rep",$rep,$params);
			DBconnect::update("stackoverflow_settings","last_updated",$time,$params);
			
			//delete all old answers
			DBconnect::deleteAll("stackoverflow_feed");  
			
			//loop answers and add to db
			foreach($answers as $a){
				//insert a row into our table
				$rows 	= "title,content,upcount,downcount,views,score,link";
				$values	= "'".$a["title"]."','".$a["body"]."','".$a["up_count"]."','".$a["down_count"]."','".$a["views"]."','".$a["score"]."','".$a["link"]."'";	
				DBconnect::insert("stackoverflow_feed",$rows,$values);
			}

		}
		
		//get the answers from the database to send to the page
		$answers 					= DBconnect::queryArray("*","stackoverflow_feed","");
		$this->module["answers"]	= $answers;	

		//check for post of our save button and run
		//the edit module function. this name must be unique
		//to every module.
		if(isset($_POST["save_stackOverflow"])){
			$this->editModule();
		}
		
	}
	
	//EDIT MODULE
	//this is called when the user has clicked the save
	//module button set in stackOverflow.admin.tpl
	public function editModule(){
		
		//update our table with the new stack settings
		$userId 		= $_POST["user_id"];
		$limit 			= $_POST["stack_limit"];
		$params			= "WHERE id = 1";
		DBconnect::update("stackoverflow_settings","user_id",$userId,$params);
		DBconnect::update("stackoverflow_settings","stack_limit",$limit,$params);
		DBconnect::update("stackoverflow_settings","last_updated",0,$params);
		
		//send message to user and exit
		$_SESSION["messages"][] = "Message: stackOverflow module updated successfully.";
		
		//send user to module page
		header("Location: ".$this->module["url"]);
		exit;
		
	}
	
	//RETURN FUNCTION
	//this is called by the system to collect any
	//info that you want to send to the page.
	//anything put into the module array will be sent
	//to the page in the format {$module.module_name.value}
	public function returnModule(){
		
		return $this->module;
		
	}
	
	//UNINSTALL MODULE
	//the function called when the user chooses 
	//to uninstall the module. you should drop all custom
	//database tables and tidy up here.
	public function uninstallModule(){
		
		//drop custom tables
		DBconnect::drop("stackoverflow_settings");
		DBconnect::drop("stackoverflow_feed");
		
	}

}

//-----BRING IN STACKOVERFLOW INFO FROM STACK API-----//

class stackAPI {
	
	//set defaults
	var $limit = 3;
	var $userid;
	var $username;
	var $rep;
	var $answers = array();
	var $timeout = 10;
	
	//get the users answers from stack overflow
	function init(){
		
		//check curl is installed
		if(!function_exists("curl_init")){
			die("Error: curl not installed");
		}
		
		//init cuel
		$ch = curl_init();
		
		//get feed
		$feed = "http://api.stackoverflow.com/1.1/users/".$this->userid."/answers?body=true&sort=votes&pagesize=".$this->limit;
		curl_setopt($ch,CURLOPT_URL,$feed);
		
		//set encoding to gzip
		curl_setopt($ch,CURLOPT_ENCODING,"gzip");
		
		//return value, dont print
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);

		//settimeout
		curl_setopt($ch,CURLOPT_TIMEOUT,$this->timeout);
		
		//download the feed
		$feed = curl_exec($ch);
		
		//close curl resource
    	curl_close($ch);
		
		//die($feed);

		//decode json
		$feed = json_decode($feed);
		
		//get username
		$this->username = $feed->answers[0]->owner->display_name;
		
		//get rep
		$this->rep = $feed->answers[0]->owner->reputation;
	
		//build answers
		$i = 0;
		foreach($feed->answers as $answer){
			$this->answers[$i] = array();
			$this->answers[$i]["title"] 		= mysql_real_escape_string($answer->title);
			$this->answers[$i]["up_count"] 		= $answer->up_vote_count;
			$this->answers[$i]["down_count"] 	= $answer->down_vote_count;
			$this->answers[$i]["views"] 		= $answer->view_count;
			$this->answers[$i]["score"] 		= $answer->score;
			$this->answers[$i]["body"] 			= mysql_real_escape_string($answer->body);
			$this->answers[$i]["link"] 			= "http://stackoverflow.com/questions/".$answer->answer_id;
			$i++;	
		}

	}
	
	//return the answers
	function getAnswers(){
		return $this->answers;
	}
	
	//return the reputation
	function getRep(){
		return $this->rep;
	}
	
	//return the username
	function getUser(){
		return $this->username;
	}
	
}

?>