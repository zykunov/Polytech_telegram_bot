<?php
    require('vendor/autoload.php'); //In this project I'm use this library https://telegram-bot-sdk.readme.io/v2.0/docs
	require('connection.php'); // connection to database
	require('logger.php');// universal simple logger
	require('groups.php');
    use Telegram\Bot\Api; 
	setlocale(LC_ALL, "Russian");

    $telegram = new Api(' '); //your api key, you can take it from @Botfather
    $result = $telegram -> getWebhookUpdates(); //Array with information about users message
	$classes_time = ['1' => '9:00 - 10:30',
							'2' => '10:40 - 12:10',
							'3' => '12:20 - 13:50',
							'4' => '14:30 - 16:00',
							'5' => '16:10 - 17:40',
							'6' => '17:50 - 19:20',
							'7' => '19:30 - 21:00'									   
							];
    
	$words_list = ['1'=>'бедолага',
						'2'=>'бедняга',
						'3'=>'нелюбимчики подъехали',
						'4'=>'приемыш'
						]; // local mem
    
	$text = $result["message"]["text"]; 	//text of message
    $chat_id = $result["message"]["chat"]["id"]; //unique id of chat
    $name = $result["message"]["from"]["username"]; //username 
	$FirstName = $result["message"]["from"]["first_name"];
	$LastName = $result["message"]["from"]["last_name"];
    $keyboard = [["Расписание на сегодня"],["Расписание по дням недели"],["Сменить группу"]]; //keyboard for main menu
	$keyboard2 = [["Понедельник","Вторник"],["Среда","Четверг"],["Пятница","Суббота"],["В главное меню:"]]; 
	// getting  user id
	$sql = "SELECT user_id FROM users WHERE user_id = '$chat_id' ";
	$row = mysqli_query($link, $sql);
	$res = mysqli_fetch_array($row);
	$user_check = $res["user_id"];
	//getting last message
	$sql = "SELECT last_message FROM users WHERE user_id = '$chat_id' ";
	$row = mysqli_query($link, $sql);
	$res= mysqli_fetch_array($row);
	$last_message = $res["last_message"];
	// getting group number
	$sql = "SELECT group_number FROM users WHERE user_id = '$chat_id' ";
	$row = mysqli_query($link, $sql);
	$res = mysqli_fetch_array($row);
	$group_number = $res["group_number"];
	
	// sending request to Polytech servers and getting response. Returns array with full timetable
	function GetRasp ($group_number){
		$json_link = "http://rasp.dmami.ru/site/group?group=" .  $group_number . "&session=0";
		$opts = ["http" => ["method" => "GET","header" => "Referer: http://rasp.dmami.ru"]];
		$context = stream_context_create($opts);
		$arr = file_get_contents ($json_link, false, $context);	
		$arr = json_decode ($arr, true);
		return $arr;
	}
	
	function get_rasp($weekday){
		$x = GetRasp($group_number);
		$one_day = ($x['grid'][$weekday]);
		for ($i = 1; $i <= 7; $i++){	
				$one_day = ($x['grid'][$weekday]); // number after 'grid' - it's a number of the day of the week
				$answer[] = $one_day[$i][0]['subject'] . "\n" . $one_day[$i][0]['teacher'] . "\n" . '09:00-10:40' . "\n" . $one_day[$i][0]['date_from'] . "\n" . $one_day[$i][0]['date_to'] . "\n" . $one_day[$i][0]['auditories'][0]['title'];	
		}
		return $answer;
	}
	

	if($text){
		 // now it's old user, we have record in DB
		if($text == "/start" and !$user_check) {
		$answer = "Привет " . $FirstName . ", в какой группе учишься?" . $last_message;
		$reply_markup = $telegram->replyKeyboardMarkup([ 'keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false ]);
		$telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => $answer ]);
		$last_message = "group question";
		$sql = "INSERT INTO users (user_id, name, last_message) VALUES ($chat_id, '$name', '$last_message') ";
		$res = mysqli_query($link, $sql);
		recAction ($chat_id." ". $FirstName . $LastName, "Зашел первый раз, создана запись в бд");
		sleep(4);
		} // first: action then it's a new user/
		elseif($text == "/start" and $user_check) {
		$answer = " Привет, привет";
		$reply_markup = $telegram->replyKeyboardMarkup([ 'keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false ]);
		$telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => $answer, 'reply_markup' => $reply_markup ]);
		}
		elseif ($text == "/help"){
		$answer = $group_number;
		$reply_markup = $telegram->replyKeyboardMarkup([ 'keyboard' => $keyboard2, 'resize_keyboard' => true, 'one_time_keyboard' => false ]);
		$telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => $answer, 'reply_markup' => $reply_markup ]);
		recAction ($chat_id." ". $FirstName . $LastName, "Ввел help");
		}
		elseif (isset($text) and $last_message == "group question"){
			if($text == "151-363"){
				$answer = $text . "?? Ммм " . $words_list[rand(1,4)] . ", ну ок, понял";
			}else{
				$answer = $text . " Принято:)";
			}
		$reply_markup = $telegram->replyKeyboardMarkup([ 'keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false ]);
		$telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => $answer, 'reply_markup' => $reply_markup ]);
		$sql = "UPDATE users SET last_message = NULL WHERE user_id = $chat_id";
		$res = mysqli_query($link, $sql);
		$sql = "UPDATE users SET group_number = '$text' WHERE user_id = $chat_id";
		$res = mysqli_query($link, $sql);
		$last_message = ' ';
		recAction ($chat_id." ". $FirstName . $LastName, "на вопрос о группе ответил: " . $text);
		}
		elseif ($text == "Расписание по дням недели"){
		$answer = "Какой день?";
		$reply_markup = $telegram->replyKeyboardMarkup([ 'keyboard' => $keyboard2, 'resize_keyboard' => true, 'one_time_keyboard' => false ]);
		$telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => $answer, 'reply_markup' => $reply_markup ]);
		recAction ($chat_id." ". $FirstName . $LastName, "Перешел в расписание по дням недели");
		}
		elseif ($text == "Расписание на сегодня"){
		$weekday= date( "N" );
		$x = GetRasp($group_number);
		$one_day = ($x['grid'][$weekday]); // number after 'grid' - it's a number of the day of the week
			if($weekday == 7){
				$answer = "Сегодня воскресенье - отдохни, дружок.";
				$telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => $answer ]);
			}else{
				for ($i = 1; $i <= 7; $i++){
					if (!empty($one_day[$i][0]['subject'])){
						$answer = $one_day[$i][0]['subject'] . "\n" . $one_day[$i][0]['teacher'] . "\n" . $one_day[$i][0]['date_from'] . "\n" . $one_day[$i][0]['date_to'] . "\n" . $one_day[$i][0]['auditories'][0]['title'] . "\n" .  "<b>".$classes_time[$i]."</b>";
						$telegram->sendMessage([ 'chat_id' => $chat_id, 'parse_mode'=> 'HTML', 'text' => $answer ]);
						time_nanosleep(0, 500000000);
					}else{
						continue;
					}
				}
			}
		recAction ($chat_id." ". $FirstName . $LastName, "Перешел в расписание на сегодня");
		}
		elseif ($text == 'Понедельник'){
		$weekday= 1;
		$x = GetRasp($group_number);
		$one_day = ($x['grid'][$weekday]); // number after 'grid' - it's a number of the day of the week
			for ($i = 1; $i <= 7; $i++){
				if (!empty($one_day[$i][0]['subject'])){
					$answer = "<b>". $one_day[$i][0]['subject'] . "</b>" . "\n" . $one_day[$i][0]['teacher'] . "\n" . $one_day[$i][0]['date_from'] . "\n" . $one_day[$i][0]['date_to'] . "\n" . $one_day[$i][0]['auditories'][0]['title'] . "\n" .  "<b>".$classes_time[$i]."</b>";
					$telegram->sendMessage([ 'chat_id' => $chat_id, 'parse_mode'=> 'HTML', 'text' => $answer ]);
					time_nanosleep(0, 500000000);
				}else{
					continue;
				}
			}
		recAction ($chat_id." ". $FirstName . $LastName, "Перешел в расписание на понедельник");
		}
		elseif ($text == 'Вторник'){
		$weekday= 2;
		$x = GetRasp($group_number);
		$one_day = ($x['grid'][$weekday]); // number after 'grid' - it's a number of the day of the week
			for ($i = 1; $i <= 7; $i++){
				if (!empty($one_day[$i][0]['subject'])){
					$answer = "<b>". $one_day[$i][0]['subject'] . "</b>" . "\n" . $one_day[$i][0]['teacher'] . "\n" . $one_day[$i][0]['date_from'] . "\n" . $one_day[$i][0]['date_to'] . "\n" . $one_day[$i][0]['auditories'][0]['title'] . "\n" .  "<b>".$classes_time[$i]."</b>";
					$telegram->sendMessage([ 'chat_id' => $chat_id, 'parse_mode'=> 'HTML', 'text' => $answer ]);
					time_nanosleep(0, 500000000);
				}else{
					continue;
				}
			}
		recAction ($chat_id." ". $FirstName . $LastName, "Перешел в расписание на вторник");
		}
		elseif ($text == 'Среда'){
		$weekday= 3;
		$x = GetRasp($group_number);
		$one_day = ($x['grid'][$weekday]); // number after 'grid' - it's a number of the day of the week
			for ($i = 1; $i <= 7; $i++){
				if (!empty($one_day[$i][0]['subject'])){
					$answer = "<b>". $one_day[$i][0]['subject'] . "</b>" . "\n" . $one_day[$i][0]['teacher'] . "\n" . $one_day[$i][0]['date_from'] . "\n" . $one_day[$i][0]['date_to'] . "\n" . $one_day[$i][0]['auditories'][0]['title'] . "\n" .  "<b>".$classes_time[$i]."</b>";
					$telegram->sendMessage([ 'chat_id' => $chat_id, 'parse_mode'=> 'HTML', 'text' => $answer ]);
					time_nanosleep(0, 500000000);
				}else{
					continue;
				}
			}
		recAction ($chat_id." ". $FirstName . $LastName, "Перешел в расписание на среду");
		}
		elseif ($text == 'Четверг'){
		$weekday= 4;
		$x = GetRasp($group_number);
		$one_day = ($x['grid'][$weekday]); // number after 'grid' - it's a number of the day of the week
			for ($i = 1; $i <= 7; $i++){
				if (!empty($one_day[$i][0]['subject'])){
					$answer = "<b>". $one_day[$i][0]['subject'] . "</b>" . "\n" . $one_day[$i][0]['teacher'] . "\n" . $one_day[$i][0]['date_from'] . "\n" . $one_day[$i][0]['date_to'] . "\n" . $one_day[$i][0]['auditories'][0]['title'] . "\n" .  "<b>".$classes_time[$i]."</b>";
					$telegram->sendMessage([ 'chat_id' => $chat_id, 'parse_mode'=> 'HTML', 'text' => $answer ]);
					time_nanosleep(0, 500000000);
				}else{
					continue;
				}
			}
		recAction ($chat_id." ". $FirstName . $LastName, "Перешел в расписание на четверг");
		}
		elseif ($text == 'Пятница'){
		$weekday= 5;
		$x = GetRasp($group_number);
		$one_day = ($x['grid'][$weekday]); // number after 'grid' - it's a number of the day of the week
		$filtered = array_filter($one_day);
		if (!empty($filtered)) {
			for ($i = 1; $i <= 7; $i++){
				if (!empty($one_day[$i][0]['subject'])){
					$answer = "<b>". $one_day[$i][0]['subject'] . "</b>" . "\n" . $one_day[$i][0]['teacher'] . "\n" . $one_day[$i][0]['date_from'] . "\n" . $one_day[$i][0]['date_to'] . "\n" . $one_day[$i][0]['auditories'][0]['title'] . "\n" .  "<b>".$classes_time[$i]."</b>";
					$telegram->sendMessage([ 'chat_id' => $chat_id, 'parse_mode'=> 'HTML', 'text' => $answer ]);
					time_nanosleep(0, 500000000);
				}else{
					continue;
				}
			}
		}else{
				$answer = "Вы сегодня не учитесь";
				$telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => $answer ]);
			}
		recAction ($chat_id." ". $FirstName . $LastName, "Перешел в расписание на пятницу");
		}
		elseif ($text == 'Суббота'){
		$weekday= 6;
		$x = GetRasp($group_number);
		$one_day = ($x['grid'][$weekday]); // number after 'grid' - it's a number of the day of the week
		$filtered = array_filter($one_day);
		if (!empty($filtered)) {
				for ($i = 1; $i <= 7; $i++){
					if (!empty($one_day[$i][0]['subject'])){
						$answer = "<b>". $one_day[$i][0]['subject'] . "</b>" . "\n" . $one_day[$i][0]['teacher'] . "\n" . $one_day[$i][0]['date_from'] . "\n" . $one_day[$i][0]['date_to'] . "\n" . $one_day[$i][0]['auditories'][0]['title'] . "\n" .  "<b>".$classes_time[$i]."</b>";
						$telegram->sendMessage([ 'chat_id' => $chat_id, 'parse_mode'=> 'HTML', 'text' => $answer ]);
						time_nanosleep(0, 500000000);
					}else{
						continue;
					}
				}
			}else{
				$answer = "Вы сегодня не учитесь";
				$telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => $answer ]);
			}
		recAction ($chat_id." ". $FirstName . $LastName, "Перешел в расписание на субботу");
		}
		elseif ($text == 'В главное меню:'){
		$answer = "Главное меню";
		$reply_markup = $telegram->replyKeyboardMarkup([ 'keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false ]);
		$telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => $answer, 'reply_markup' => $reply_markup ]);
		}
		elseif ($text == 'Сменить группу'){
			if($group_number =="151-363"){
				$url = "https://botcasino.ru/botrasp/t.gif";
				$telegram->sendDocument([ 'chat_id' => $chat_id, 'document' => $url]);  
				sleep (3);		
				$answer = "Ну лан, какая группа?";
				$sql = "UPDATE users SET last_message = 'group question' WHERE user_id = $chat_id";
				$res = mysqli_query($link, $sql);
				}else{		
				$answer = "На какую группу хотите сменить?";
				$sql = "UPDATE users SET last_message = 'group question' WHERE user_id = $chat_id";
				$res = mysqli_query($link, $sql);
				}
		$reply_markup = $telegram->replyKeyboardMarkup([ 'keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => true ]);
		$telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => $answer, 'reply_markup' => $reply_markup ]);
		}
		elseif ($text == 'Тест'){
		$answer = "Клава";
		$reply_markup = $telegram->replyKeyboardMarkup([ 'keyboard' => $keyboard3, 'resize_keyboard' => false, 'one_time_keyboard' => false ]);
		$telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => $answer, 'reply_markup' => $reply_markup ]);
		}
		else{
        	$reply = "По запросу \"<b>".$text."</b>\" ничего не найдено.";
        	$telegram->sendMessage([ 'chat_id' => $chat_id, 'parse_mode'=> 'HTML', 'text' => $reply ]);
			recAction ($chat_id." ". $FirstName . $LastName, "написал: " . $text);
        }
    }else{
    	$telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => "Отправьте текстовое сообщение." ]);
	}
?>