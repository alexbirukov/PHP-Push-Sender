CREATE TABLE devices (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Уникальный идентификатор записи в таблице',
  deviceID varchar(255) NOT NULL COMMENT 'Идентификатор устройства',
  deviceToken varchar(255) NOT NULL COMMENT 'Токен устройства',
  devicePlatform varchar(255) NOT NULL COMMENT 'Платформа устройства',
  PRIMARY KEY (id)
)
ENGINE = MYISAM
AUTO_INCREMENT = 1
CHARACTER SET utf8
COLLATE utf8_general_ci
COMMENT = 'Зарегистрированные устройства для рассылки PUSH сообщений';

CREATE TABLE messages (
  ID int(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT COMMENT 'Идентификатор сообщения в БД',
  DateTime datetime NOT NULL COMMENT 'Дата отправки сообщения',
  MessageText text DEFAULT NULL COMMENT 'Текст Push сообщения',
  PRIMARY KEY (ID)
)
ENGINE = MYISAM
AUTO_INCREMENT = 1
CHARACTER SET utf8
COLLATE utf8_general_ci
COMMENT = 'Push сообщения, отправляемые пользователям';