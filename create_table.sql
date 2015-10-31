CREATE TABLE devices (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Уникальный идентификатор  записи в таблице',
  deviceID varchar(255) NOT NULL COMMENT 'Идентификатор устройства',
  deviceToken varchar(255) NOT NULL COMMENT 'Токен устройства',
  devicePlatform varchar(255) NOT NULL COMMENT 'Платформа устройства',
  PRIMARY KEY (id),
  UNIQUE INDEX deviceID (deviceID)
)
ENGINE = MYISAM
AUTO_INCREMENT = 20
AVG_ROW_LENGTH = 166
CHARACTER SET utf8
COLLATE utf8_general_ci
COMMENT = 'Зарегистрированные устройства для рассылки PUSH сообщений';