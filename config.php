<?php

	# Переменные для работы  с базой данных
	$config['db']['host'] = "localhost";
	$config['db']['name'] = "Имя БД";
	$config['db']['user'] = "Пользователь БД";
	$config['db']['pass'] = "Пароль БД";
	
	# Логгирование Push сообщений в БД
	$config['log']['push'] = true;
	# Лимит вывода Push сообщений
	$config['log']['limit'] = 3;
	
	# Управление рассылкой. True - рассылка производится, false - рассылка отключена.
	$config['apn']['send'] = true;
	$config['gcm']['send'] = true;
	
	# Ключи для доступа к сервисам
	# Ключ для доступа Google Server API
	$config['gcm']['apikey'] = "ВАШ API КЛЮЧ";
	# Путь к сертификатам APN для подключения к серверу рассылки
	$config['apn']['sert'] = 'Certificates/server_certificates_bundle_sandbox.pem';
	$config['apn']['sert_prod'] = 'Certificates/server_certificates_bundle_prod.pem';
	$config['apn']['RootCertificat'] = 'Certificates/entrust_root_certification_authority_2048.pem';
	# Пароль сертификата, если пароля нет, то оставляем поле пустым. Настоятельно не рекомендуется использовать сертификаты без пароля.
	$config['apn']['sertPass'] = '';
	$config['apn']['production'] = false;

?>