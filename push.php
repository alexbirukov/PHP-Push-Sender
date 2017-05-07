<?php
	###########################################################################
	# Подключаем файлы из бибилотеки для отправки Push сообщений на Android
	###########################################################################
	require_once(dirname(__FILE__).'/CodeMonkeysRu/GCM/Exception.php');
	require_once(dirname(__FILE__).'/CodeMonkeysRu/GCM/Message.php');
	require_once(dirname(__FILE__).'/CodeMonkeysRu/GCM/Response.php');
	require_once(dirname(__FILE__).'/CodeMonkeysRu/GCM/Sender.php');
	
	###########################################################################
	# Подключаем автозагрузчик от ApnsPHP
	###########################################################################
	require_once 'ApnsPHP/Autoload.php';
	
	###########################################################################
	# Подключаем файл конфигурации
	###########################################################################
	require_once 'config.php';
	
	# Проверяем, указано ли действие при запуске скрипта
	if (isset($_REQUEST['action'])) {
		
		switch ($_REQUEST['action']) {
			case 'register-device':
				RegisterDevice($_REQUEST['did'], $_REQUEST['token'], $_REQUEST['platform'], $config);
				break;		
			case 'send-push':
				SendPush($_REQUEST['text'], $config);
				break;			
			case 'get-push-xml':
				get_push_to_xml($config);
				break;	
		}
	}
	else 
	{
		# Если никакого действия не задано выводим ошибку
		echo 'Ошибка обработки данных!';
	}
	
	###########################################################################
	# ФУНКЦИЯ РЕГИСТРАЦИИ УСТРОЙСТВА В БД
	# 
	# string $deviceID - идентификатор устройства
	# string $token - токен устройства для отправки push сообщений
	# string $platform - платформа устройства
	# array $config - массив параметров конфигурации: см. файл config.php
	#
	function RegisterDevice($deviceID, $token, $platform, $config) {
		
		# Включение или отключение вывода лога
		$logging = true;
		
		# Получаем данные от пользователя
		# Идентификатор устройства
		$deviceID = htmlspecialchars($deviceID);
		# Токен устройства
		$token = htmlspecialchars($token);
		# Платформа
		$platform = htmlspecialchars($platform);
		
		if ($logging) {

			$message['deviceID'] = $deviceID;
			$message['token'] = $token;
			$message['platform'] = $platform;
			
		}

		# Создаём подключение к БД
		$db = mysql_connect($config['db']['host'], $config['db']['user'], $config['db']['pass']) or die("Ошибка подключения к БД!");

		mysql_select_db($config['db']['name'], $db);
		mysql_query("SET NAMES `utf8`");   
		mysql_query("set character_set_client='utf8'");    
		mysql_query("set character_set_results='utf8'");    
		mysql_query("set collation_connection='utf8'");  
		
		# Проверяем количество записей в БД
		$result = mysql_query("SELECT COUNT(*) AS count FROM devices WHERE deviceID = '$deviceID' AND deviceToken = '$token'", $db);
		$row = mysql_fetch_assoc($result);

		# Если записи с указанным ID  и токеном нет в базе, записываем
		if ($row['count'] == 0) {
			$res = mysql_query("INSERT INTO devices (id, deviceID, deviceToken, devicePlatform) VALUES (DEFAULT, '$deviceID', '$token', '$platform')", $db);
		}
	
	}
	
	###########################################################################
	# ФУНКЦИЯ ОТПРАВКИ PUSH СООБЩЕНИЙ НА ВСЕ УСТРОЙСТВА
	# 
	# string $message - текст сообщения для отправки
	# array $config - массив параметров конфигурации: см. файл config.php
	#
	function SendPush ($message, $config) {

		# Убираем обратные слэши
		$text = stripcslashes($message);
		
		# Создаём подключение к БД
		$db = mysql_connect($config['db']['host'], $config['db']['user'], $config['db']['pass']) or die("Ошибка подключения к БД!");

		mysql_select_db($config['db']['name'], $db);
		mysql_query("SET NAMES `utf8`");   
		mysql_query("set character_set_client='utf8'");    
		mysql_query("set character_set_results='utf8'");    
		mysql_query("set collation_connection='utf8'"); 
		
		###########################################################################
		# ОТПРАВКА СООБЩЕНИЙ НА ANDROID
		# Проверяем, включена ли рассылка
		if ($config['gcm']['send']) {
			# Выборка токенов
			$result = mysql_query("SELECT deviceToken FROM devices WHERE devices.devicePlatform = 'android'", $db);
			# Проверяем количество токенов
			$rows_count = mysql_num_rows($result);
				
			# Отправка сообщений если в БД имеются токены
			if ($rows_count > 0) {
				# Получение токенов устройств
				$andr_tokens = array();
				
				while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
					$andr_tokens[] = $row[0];
				}
				
				# Так как у GCM стоит лимит в 1000 за раз, делим
            			$chunks = array_chunk($andr_tokens,1000);
				
				# Отправка токенов на Android устройства
				foreach( $chunks AS $chunk ) SendAndroid($chunk, $text, $config);
			}
		}
		
		
		###########################################################################
		# ОТПРАВКА СООБЩЕНИЙ НА IOS
		# Проверяем, включена ли рассылка
		if ($config['apn']['send']) {
			# Выборка токенов
			$result = mysql_query("SELECT deviceToken FROM devices WHERE devices.devicePlatform = 'ios'", $db);

			# Проверяем количество токенов
			$rows_count = mysql_num_rows($result);
			
			# Отправка сообщений если в БД имеются токены
			if ($rows_count > 0) {
				# Получение токенов устройств
				$ios_tokens = array();
				
				while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
					$ios_tokens[] = $row[0];
				}
				
				# Отправка токенов на iOS устройства
				SendIOS($ios_tokens, $text, $config);
			}
		}
		
		###########################################################################
		# ЗАПИСЬ СООБЩЕНИЯ В БД
		# Проверяем, включено ли логгирование  сообщений в БД
		if ($config['log']['push']) {
			// Записываем сообщение в БД
			MessageToDB($text);
		}
	}
	
	###########################################################################
	# ФУНКЦИЯ РАССЫЛКИ PUSH СООБЩЕНИЙ НА IOS УСТРОЙСТВА	
	# 
	# array $tokens - одномерный массив токенов устройст
	# string $text - строка текста для рассылки
	# array $config - массив параметров конфигурации: см. файл config.php
	#
	function SendIOS($tokens, $text, $config)
	{
		
		# Создаём экземпляр Push. Необходимо указывать параметр ENVIRONMENT_PRODUCTION или 
		# ENVIRONMENT_SANDBOX в соответствии с сертификатом.
		$push = new ApnsPHP_Push(
			//ApnsPHP_Abstract::ENVIRONMENT_SANDBOX, $config['apn']['sert']);
			($config['apn']['production'] ? 0 : 1), 
			($config['apn']['production'] ? $config['apn']['sert_prod'] : $config['apn']['sert'])
		);
		
		# Указываем пароль сертификата если он имеется
		if ($config['apn']['sertPass'] <> '') {
			$push->setProviderCertificatePassphrase($config['apn']['sertPass']);
		}
		
		# Указываем корневой сертификат
		$push->setRootCertificationAuthority($config['apn']['RootCertificat']);
		
		# Создаём сообщение
		$message = new ApnsPHP_Message();
		# Перебираем массив токенов и добавляем получателей
		$listTokens = array();
		foreach ($tokens as $token) {
			$message->addRecipient($token);
			$listTokens[] = $token;
		}
		
		# Соединяемся с сервером 
		$push->connect();
		
		# Устанавливаем параметры отправки сообщения
		$message->setSound();
		//$message->setBadge(0)
		$message->setText($text);
		
		# Устанавливаем сообщение для отправки
		$push->add($message);
		# Отправляем сообщение
		$push->send();
		# Отключаемся от сервера
		$push->disconnect();

		# Проверяем возникшие ошибки во время отправки
		$aErrorQueue = $push->getErrors();
		# Если имеются ошибки
		if (!empty($aErrorQueue)) {
			echo 'Ошибка отправки ios  -  ' . print_r($aErrorQueue, true);
			if (is_array($aErrorQueue)) {
				foreach($aErrorQueue as $error) {
					if (isset($error['ERRORS']) && is_array($error['ERRORS'])) {
						foreach ($error['ERRORS'] as $m) {
							if (isset($m['statusMessage']) && $m['statusMessage'] == 'Invalid token') {
								$arrayID = $m['identifier'] - 1;
								if (isset($listTokens[$arrayID])) {
									# Если найден недействительный токен, удатяем его из БД
									//echo 'Удаление ошибочного токена';
									DeleteToken($listTokens[$arrayID]);
								}
							}

						}
					}
				}
			}
		}
	}
	
	###########################################################################
	# ФУНКЦИЯ РАССЫЛКИ PUSH СООБЩЕНИЙ НА ANDROID УСТРОЙСТВА	
	# 
	# array $tokens - одномерный массив токенов устройст
	# string $text - строка текста для рассылки
	# array $config - массив параметров конфигурации: см. файл config.php
	#
	function SendAndroid($tokens, $text, $config)
	{
		# Создаём поток для отправки с использование API ключа
		$sender = new \CodeMonkeysRu\GCM\Sender($config['gcm']['apikey']);
		# Создаём сообщение для указаных токенов
		$message = new \CodeMonkeysRu\GCM\Message($tokens, array("message" => $text));

		# Производим попытку рассылки сообщений
		try {
			# Выполняем рассылку
			$response = $sender->send($message);

			# Если возникли ошибки
			if ($response->getFailureCount() > 0) {
				# Проверяем недействительные токены
				$invalidRegistrationIds = $response->getInvalidRegistrationIds();
				foreach($invalidRegistrationIds as $invalidRegistrationId) {
					# Если найден недействительный токен, удатяем его из БД
					DeleteToken($invalidRegistrationId);
				}
			}
			# Выводим информационное сообщение
			if ($response->getSuccessCount()) {
				echo 'Отправлено сообщений на ' . $response->getSuccessCount() . ' устройств(а).';
			}
		} catch (\CodeMonkeysRu\GCM $e) {
			# Вылавливаем возникшие исключения
			switch ($e->getCode()) {
				case CodeMonkeysRu\GCM\Exception::ILLEGAL_API_KEY:
				case CodeMonkeysRu\GCM\Exception::AUTHENTICATION_ERROR:
				case CodeMonkeysRu\GCM\Exception::MALFORMED_REQUEST:
				case CodeMonkeysRu\GCM\Exception::UNKNOWN_ERROR:
				case CodeMonkeysRu\GCM\Exception::MALFORMED_RESPONSE:
					echo 'Ошибка отправки на Android ' . $e->getCode() . ' ' . $e->getMessage();
					break;
			}
		}
	}
	
	###########################################################################
	# ФУНКЦИЯ УДАЛЕНИЯ НЕДЕЙСТВИТЕЛЬНОГО ТОКЕНА ИЗ БД
	# 
	# string $IndalidToken - токен недействительного устройства
	#
	function DeleteToken($IndalidToken) {
		
		# Глобальные переменные
		global $config;
		
		$db = mysql_connect($config['db']['host'], $config['db']['user'], $config['db']['pass']) or die("Ошибка подключения к БД!");

		mysql_select_db($config['db']['name'], $db);
		mysql_query("SET NAMES `utf8`");   
		mysql_query("set character_set_client='utf8'");    
		mysql_query("set character_set_results='utf8'");    
		mysql_query("set collation_connection='utf8'"); 
		
		# Удаление токена из БД
		mysql_query("DELETE FROM devices WHERE deviceToken = '$IndalidToken'", $db);	
		
	}
	
	###########################################################################
	# ФУНКЦИЯ ЗАПИСИ PUSH СООБЩЕНИЯ В БД
	# 
	# string $message - текст push сообщения.
	#
	function MessageToDB($message) {
		
		# Глобальные переменные
		global $config;
		
		$tmp_db = mysql_connect($config['db']['host'], $config['db']['user'], $config['db']['pass']) or die("Ошибка подключения к БД!");

		mysql_select_db($config['db']['name'], $tmp_db);
		mysql_query("SET NAMES `utf8`");   
		mysql_query("set character_set_client='utf8'");    
		mysql_query("set character_set_results='utf8'");    
		mysql_query("set collation_connection='utf8'"); 
				
		# Записываем сообщение в БД
		mysql_query("INSERT INTO messages (ID, DateTime, MessageText) VALUES (DEFAULT, NOW(), '$message')", $tmp_db);
		
	}

	###########################################################################
	# ФУНКЦИЯ ВЫВОДА ПОСЛЕДНИХ PUSH СООБЩЕНИЙ В ФОРМАТЕ XML
	#
	function get_push_to_xml($config) {
		
		# Создаём подключение к БД
		$db = mysql_connect($config['db']['host'], $config['db']['user'], $config['db']['pass']) or die("Ошибка подключения к БД!");

		mysql_select_db($config['db']['name'], $db);
		mysql_query("SET NAMES `utf8`");   
		mysql_query("set character_set_client='utf8'");    
		mysql_query("set character_set_results='utf8'");    
		mysql_query("set collation_connection='utf8'"); 
		
		# Выполняем запрос к БД
		$sql = mysql_query("
			SELECT ID, MessageText
			FROM messages
			ORDER BY DateTime DESC
			LIMIT 3"
		, $db);
		
		# Создаём новый XML документ
		$dom = new DOMDocument('1.0', 'utf-8');
		
		# Создаём корневой элемент сообщений
		$messages = $dom->createElement('messages');

		# Перебираем список треков из БД
		while ($row = mysql_fetch_assoc($sql)) {

			# Создаём элемент message
			$message = $dom->createElement('message');
			
			# Создаём элемент ID
			$id = $dom->createElement('id');
			$id->appendChild($dom->createTextNode($row['ID']));
			$message->appendChild($id);
			
			# Создаём элемент text
			$text = $dom->createElement('text');
			$text->appendChild($dom->createTextNode($row['MessageText']));
			$message->appendChild($text);
			
			# Выводим маркер в общий список
			$messages->appendChild($message);

		}
		
		// Добавляем элемент markers в структуру документа 
		$dom->appendChild($messages);
		// Выводим XML документ
		echo $dom->saveXML();
	}
?>
