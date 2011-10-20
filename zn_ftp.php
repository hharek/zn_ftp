<?php
/**
 * Класс для работы с FTP
 */
class ZN_FTP
{
	/**
	 * Дескриптор подключения
	 * 
	 * @var resource
	 */
	private $conn_id;
	
	/**
	 * Хост
	 * 
	 * @var string
	 */
	private $host;
	
	/**
	 * Пользователь
	 * 
	 * @var string
	 */
	private $user;
	
	/**
	 * Пароль
	 * 
	 * @var string
	 */
	private $pass;
	
	/**
	 * Путь по умолчанию (для относительных путей)
	 * 
	 * @var string
	 */
	private $path;
	
	/**
	 * Порт
	 * 
	 * @var int
	 */
	private $port;
	
	/**
	 * Использоваталь ssl
	 * 
	 * @var bool
	 */
	private $ssl;
	
	/**
	 * Таймаут
	 * 
	 * @var int
	 */
	private $timeout = 30;
	
	/**
	 * Назначить данные для соединения
	 * 
	 * @param string $host
	 * @param string $user
	 * @param string $pass
	 * @param string $path
	 * @param int $port
	 * @param bool $ssl
	 * @return bool
	 */
	public function __construct($host, $user, $pass, $path="/", $port=21, $ssl=false)
	{
		/* Проверка */
		if(empty($host))
		{throw new Exception("Хост не задан");}
		
		if(empty($user))
		{throw new Exception("Пользователь не задан");}
		
		if(empty($path))
		{throw new Exception("Коревой путь не задан");}
		
		if(substr($path, strlen($path)-1, 1) != "/")
		{$path = $path."/";}
		
		$port = intval($port);
		if(empty($port))
		{throw new Exception("Порт не задан");}
		
		settype($ssl, "boolean");
		
		/* Назначить */
		$this->host = $host;
		$this->user = $user;
		$this->pass = $pass;
		$this->path = $path;
		$this->port = $port;
		$this->ssl = $ssl;
		
		return true;
	}
	
	/**
	 * Деструктор
	 * @return bool
	 */
	public function __destruct()
	{
		$this->close();
		return true;
	}

		/**
	 * Подключиться
	 * 
	 * @return bool
	 */
	public function connect()
	{
		/* Подключение к хосту */
		if($this->ssl == false)
		{$this->conn_id = @ftp_connect($this->host, $this->port, $this->timeout);}
		else 
		{$this->conn_id = @ftp_ssl_connect($this->host, $this->port, $this->timeout);}
		
		if(!$this->conn_id)
		{
			$error = error_get_last();
			throw new Exception("Не удалось установить соединение. ".$error['message']);
		}
		
		/* Назначить таймаут */
		@ftp_set_option($this->conn_id, FTP_TIMEOUT_SEC, $this->timeout);
		
		/* Идентификация */
		$login = @ftp_login($this->conn_id, $this->user, $this->pass);
		if(!$login)
		{
			$error = error_get_last();
			throw new Exception($error['message']);
		}
		
		/* Включение пассивного режима */
		@ftp_pasv($this->conn_id, true);
		
		/* Текущая категория */
		if(!@ftp_chdir($this->conn_id, $this->path))
		{
    		$error = error_get_last();
			throw new Exception($error['message']);
		}
		
		return true;
	}
	
	/**
	 * Закрыть соединение
	 * 
	 * @return bool
	 */
	public function close()
	{
		if(!empty($this->conn_id))
		{ftp_close($this->conn_id);}
		
		return true;
	}
	
	/**
	 * Назначить таймаут
	 * 
	 * @param int $timeout
	 * @return bool
	 */
	public function set_timeout($timeout)
	{
		$timeout = intval($timeout);
		$this->timeout = $timeout;
		return true;
	}
	
	/**
	 * Проверка на существование файла
	 * 
	 * @param string $file
	 * @return bool
	 */
	public function is_file($file)
	{
		/* Проверка */
		$file = $this->normalize_path($file);
		
		if($file == "/")
		{return false;}
		
		/* Соединение */
		if(empty($this->conn_id))
		{$this->connect();}
		
		/* Проверка файла */
		$type = $this->get_type_file($file);
		if($type == "file")
		{return true;}
		else
		{return false;}
	}
	
	/**
	 * Проверка на существование каталога
	 * 
	 * @param string $path
	 * @return bool
	 */
	public function is_dir($path)
	{
		/* Проверка */
		$path = $this->normalize_path($path);
		
		if($path == "/")
		{return true;}
		
		/* Соединение*/
		if(empty($this->conn_id))
		{$this->connect();}
		
		/* Проверка каталога */
		$type = $this->get_type_file($path);
		if($type == "dir")
		{return true;}
		else
		{return false;}
	}
	
	/**
	 * Список каталогов и файлов в папке
	 * 
	 * @param string $path
	 * @param string $type (all|dir|file)
	 * @param string $ext
	 * @return array
	 */
	public function ls($path, $type="all", $ext="")
	{
		/* Проверка */
		$path = $this->normalize_path($path);
		
		if(!in_array($type, array('all','file','dir')))
		{throw new Exception("Тип задан неверно");}
		
		if($type != "file" and !empty ($ext))
		{throw new Exception("Расширение можно задать только для файлов");}
		
		if(!empty ($ext) and !preg_match("#^[a-zA-Z0-9]{1,5}$#isu", $ext))
		{throw new Exception("Расширение задано неверно");}
		
		/* Соединение */
		if(empty($this->conn_id))
		{$this->connect();}
		
		/* Список */
		if(!$this->is_dir($path))
		{throw new Exception("Папки \"{$path}\" не существует");}
		
		$ls = array();
		$raw_list = ftp_rawlist($this->conn_id, $path);
		if(!empty ($raw_list))
		{
			foreach ($raw_list as $val)
			{
				$file_settings = $this->raw_razbor($val);
				if(empty ($file_settings) or $file_settings['name'] == "." or $file_settings['name'] == "..")
				{continue;}
				
				switch ($type)
				{
					case "all":
					{
						$ls[] = $file_settings;
					}
					break;
					
					case "dir":
					{
						if($file_settings['type'] == "dir")
						{$ls[] = $file_settings;}
					}
					break;
					
					case "file":
					{
						if($file_settings['type'] == "file")
						{
							if(mb_substr($file_settings['name'], mb_strlen($file_settings['name'], "UTF-8")-mb_strlen($ext, "UTF-8"), mb_strlen($ext, "UTF-8"), "UTF-8") == $ext)
							{
								$ls[] = $file_settings;
							}
						}
					}
					break;
				}
				
			}
		}
		
		return $ls;
	}
	
	/**
	 * Получить содержимое файла
	 * 
	 * @param string $file
	 * @return string
	 */
	public function get($file)
	{
		/* Проверка */
		$file = $this->normalize_path($file);
		
		/* Соединение */
		if(empty($this->conn_id))
		{$this->connect();}
		
		/* Содержимое файла */
		if(!$this->is_file($file))
		{throw new Exception("Файла с именем \"{$file}\" не существует.");}
		
		$tmp_file = tmpfile();
		
		if(!@ftp_fget($this->conn_id, $tmp_file, $file, FTP_BINARY))
		{
			$error = error_get_last();
			throw new Exception("Не удалось прочитать файл \"{$file}\". ".$error['message']);
		}
		
		fseek($tmp_file, 0);
		$content = "";
		while (!feof($tmp_file)) 
		{$content .= fread($tmp_file, 1024);}
		fclose($tmp_file);
		
		return $content;
	}
	
	/**
	 * Записать данные в файл
	 * 
	 * @param string $file
	 * @param string $content 
	 * @return bool
	 */
	public function put($file, $content)
	{
		/* Проверка */
		$file = $this->normalize_path($file);	
		settype($create_file, "boolean");
		
		/* Соединение */
		if(empty($this->conn_id))
		{$this->connect();}
		
		/* Проверка папки */
		$file_type = $this->get_type_file($file);
		if($file_type == "dir")
		{
			throw new Exception("Невозможно записать данные в папку");
		}
		elseif($file_type == "null")
		{
			$file_ar = explode("/", $file);
			$file_name = array_pop($file_ar);
			$file_up = implode("/", $file_ar);
			$file_up_type = $this->get_type_file($file_up);
			if($file_up_type != "dir")
			{throw new Exception("Имя файла \"{$file}\" задано неверно");}
		}
		
		/* Записать */
		$tmp_file = tmpfile();
		fwrite($tmp_file, $content);
		fseek($tmp_file, 0);
		
		if(!@ftp_fput($this->conn_id, $file, $tmp_file, FTP_BINARY))
		{
			$error = error_get_last();
			throw new Exception("Не удалось записать в файл \"{$file}\". ".$error['message']);
		}
		fclose($tmp_file);
		
		return true;
	}
	
	/**
	 * Создать папку
	 * 
	 * @param string $path 
	 * @return bool
	 */
	public function mkdir($path)
	{
		/* Проверка */
		$path = $this->normalize_path($path);
		
		/* Соединение и путь */
		if(empty($this->conn_id))
		{$this->connect();}
		
		/* Создать папку */
		if(!@ftp_mkdir($this->conn_id, $path))
		{
			$error = error_get_last();
			throw new Exception("Не удалось создать папку \"{$path}\". ".$error['message']);
		}
		
		return true;
	}
	
	/**
	 * Копировать файлы
	 * 
	 * @param string $source
	 * @param string $dest
	 * @return bool 
	 */
	public function cp($source, $dest)
	{
		/* Проверка */
		$source = $this->normalize_path($source);
		$dest = $this->normalize_path($dest);
		
		if($source == $dest)
		{throw new Exception("Источник и назначение совпадают");}
		
		/* Папка источника не должна входить в папку назначения. (cp /tmp /tmp/new) */
		if(mb_substr($dest, 0, mb_strlen($source, "UTF-8"), "UTF-8") == $source)
		{throw new Exception("Папка источника \"{$source}\" не должна входить в папку назначения \"{$dest}\"");}
		
		$type_source = $this->get_type_file($source);
		if($type_source == "null")
		{throw new Exception("Файл-источника не существует");}
		
		$type_dest = $this->get_type_file($dest);
		if($type_source == "dir" and $type_dest == "null")
		{throw new Exception("Папки назначения не существует");}
		
		/* Новое имя файла */
		if($type_source == "file" and $type_dest == "null")
		{
			$dest_ar = explode("/", $dest);
			$dest_name = array_pop($dest_ar);
			$dest_up = implode("/", $dest_ar);
			
			if($this->get_type_file($dest_up) != "dir")
			{throw new Exception("Папки назначения не существует");}
		}
		
		if($type_source == "dir" and $type_dest == "file")
		{throw new Exception("Нельзя скопировать папку в файл.");}
		
		/* Копирование */
		if($type_source == "file")
		{
			if($type_dest == "dir")
			{
				$dest .= "/".basename($source);
				if($source == $dest)
				{throw new Exception("Файл источник и файл назначения - это один и тот же файл");}
			}
			$this->cp_file($source, $dest);
		}
		elseif ($type_source == "dir")
		{ 
			$dest .= "/".basename($source);
			if($source == $dest)
			{throw new Exception("Папка источник и папка назначения - это одна и та же папка");}
			$this->cp_dir($source, $dest);	
		}
		
		return true;
	}

	/**
	 * Перенести или переименовать файл или папку
	 * 
	 * @param string $source
	 * @param string $dest 
	 * @return bool
	 */
	public function mv($source, $dest)
	{
		/* Проверка */
		$source = $this->normalize_path($source);
		$dest = $this->normalize_path($dest);
		
		if($source == $dest)
		{throw new Exception("Источник и назначение совпадают");}
		
		/* Папка источника не должна входить в папку назначения. (mv /tmp /tmp/new) */
		if(mb_substr($dest, 0, mb_strlen($source, "UTF-8"), "UTF-8") == $source)
		{throw new Exception("Папка источника \"{$source}\" не должна входить в папку назначения \"{$dest}\"");}
		
		$type_source = $this->get_type_file($source);
		if($type_source == "null")
		{throw new Exception("Файл-источника не существует");}
		
		$type_dest = $this->get_type_file($dest);
		if($type_source == "dir" and $type_dest == "null")
		{throw new Exception("Папки назначения не существует");}
		
		/* Новое имя файла */
		if($type_source == "file" and $type_dest == "null")
		{
			$dest_ar = explode("/", $dest);
			$dest_name = array_pop($dest_ar);
			$dest_up = implode("/", $dest_ar);
			
			if($this->get_type_file($dest_up) != "dir")
			{throw new Exception("Папки назначения не существует");}
		}
		
		if($type_source == "dir" and $type_dest == "file")
		{throw new Exception("Нельзя перенести папку в файл.");}
		
		/* Перемещение */
		if($type_dest == "dir")
		{$dest = $dest."/".basename($source);}
		
		if(!@ftp_rename($this->conn_id, $source, $dest))
		{			
			$error = error_get_last();
			throw new Exception("Не удалось перенести \"{$source}\" в \"{$dest}\". ".$error['message']);
		}
		
		return true;
	}
	
	/**
	 * Удалить файл или папку
	 * 
	 * @param string $file 
	 * @return bool
	 */
	public function rm($file)
	{
		/* Проверка */
		$file = $this->normalize_path($file);
		
		$type = $this->get_type_file($file);
		if($type == "null")
		{throw new Exception("Файл с именем \"{$file}\" не существует");}
		
		/* Удаление */
		if($type == "file")
		{
			if(!@ftp_delete($this->conn_id, $file))
			{
				$error = error_get_last();
				throw new Exception("Не удалось удалить \"{$file}\". ".$error['message']);
			}
		}
		elseif($type == "dir")
		{
			$this->rm_dir($file);
		}
		
		return true;
	}
	
	/**
	 * Устанавливает права доступа к файлу или папке
	 * 
	 * @param string $file
	 * @param int $mode
	 * @param bool $recursion 
	 * @return bool
	 */
	public function chmod($file, $mode, $recursion=true)
	{
		/* Проверка */
		$file = $this->normalize_path($file);
		
		$type = $this->get_type_file($file);
		if($type == "null")
		{throw new Exception("Файл с именем \"{$file}\" не существует");}
		
		$mode = intval($mode);
		settype($recursion, "boolean");
		
		/* Установить права доступа */
		if($recursion == false or $type == "file")
		{
			if(!@ftp_chmod($this->conn_id, $mode, $file))
			{
				$error = error_get_last();
				throw new Exception("Не удалось установить права \"{$mode}\" на файл \"{$file}\". ".$error['message']);
			}
		}
		else
		{
			$this->chmod_dir($file, $mode);
		}
		
		return true;
	}
	
	/**
	 * Получить размер файла или папки в байтах
	 * 
	 * @param string $file 
	 * @return int
	 */
	public function size($file)
	{
		/* Проверка */
		$file = $this->normalize_path($file);
		
		$type = $this->get_type_file($file);
		if($type == "null")
		{throw new Exception("Файла с именем \"{$file}\" не существует");}
		
		/* Получить размер */
		if($type == "file")
		{
			$size = $this->size_file($file);
		}
		elseif ($type == "dir")
		{
			$dir_ar = explode("/", $file);
			$dir_name = array_pop($dir_ar);
			$dir_up = implode("/", $dir_ar);
			$raw_list_up = ftp_rawlist($this->conn_id, $dir_up);

			foreach ($raw_list_up as $val)
			{
				$file_settings = $this->raw_razbor($val);
				if($file_settings['name'] == $dir_name and $file_settings['type'] == "dir")
				{
					$size = $file_settings['size'];
					break;
				}
			}
		
			$size += $this->size_dir($file);
		}
		
		return $size;
	}
	
	/**
	 * Загрузить файл на ftp-сервер
	 * 
	 * @param string $file
	 * @param string $ftp_file
	 * @param bool $check_form_upload 
	 * @return bool
	 */
	public function upload($file, $ftp_file, $check_form_upload=false)
	{
		/* Проверка */
		if(!is_file($file))
		{throw new Exception("Файла \"{$file}\" не существует");}
		
		$ftp_file = $this->normalize_path($ftp_file);
		
		$ftp_file_ar = explode("/", $ftp_file);
		$ftp_file_name = array_pop($ftp_file_ar);
		$ftp_file_up = implode("/", $ftp_file_ar);
		
		$ftp_file_type_up = $this->get_type_file($ftp_file_up);
		if($ftp_file_type_up != "dir")
		{throw new Exception("Имя FTP-файла задано неверно");}
		
		settype($check_form_upload, "boolean");
		if($check_form_upload)
		{
			if(!is_uploaded_file($file))
			{throw new Exception("Файл загружен не при помощи HTTP POST");}
		}
		
		/* Загрузить */
		$fp = fopen($file, "r");
		if(!@ftp_fput($this->conn_id, $ftp_file, $fp, FTP_BINARY))
		{
			$error = error_get_last();
			throw new Exception("Не удалось записать в файл \"{$ftp_file}\". ".$error['message']);
		}
		fclose($fp);
		
		return true;
	}
	
	/**
	 * Получить тип файла (null|file|dir)
	 * 
	 * @param string $file
	 * @return string
	 */
	private function get_type_file($file)
	{
		/* Проверка */
		$file = $this->normalize_path($file);
		
		/* Соединение */
		if(empty($this->conn_id))
		{$this->connect();}
		
		/* Тип файла */
		$type = "null";
	
		$file_ar = explode("/", $file);
		$file_name = array_pop($file_ar);
		$file_up = implode("/", $file_ar);

		$raw_list_up = ftp_rawlist($this->conn_id, $file_up);
		if(empty ($raw_list_up))
		{return $type;}

		foreach ($raw_list_up as $val)
		{
			$file_settings = $this->raw_razbor($val);
			if($file_settings['name'] == $file_name)
			{
				$type = $file_settings['type'];
			}
		}

		return $type;
	}
	
	/**
	 * Разбор строки полученной функцией ftp_rawlist
	 * 
	 * @param string $str
	 * @return array 
	 */
	private function raw_razbor($str)
	{
		if(!preg_match("#([-d][rwxstST-]+).* ([0-9]*) ([a-zA-Z0-9]+).* ([a-zA-Z0-9]+).* ([0-9]*) ([a-zA-Z]+[0-9: ]*[0-9])[ ]+(([0-9]{2}:[0-9]{2})|[0-9]{4}) (.+)#isu", $str, $sovpal))
		{return false;}

		$file_settings = array();
		if(substr($sovpal[1], 0, 1)=="d")
		{$file_settings['type'] = "dir";}
		else
		{$file_settings['type'] = "file";}

		$file_settings['line'] = $sovpal[0];
		$file_settings['rights'] = $sovpal[1];
		$file_settings['number'] = $sovpal[2];
		$file_settings['user'] = $sovpal[3];
		$file_settings['group'] = $sovpal[4];
		$file_settings['size'] = $sovpal[5];
		$file_settings['date'] = date("d.m.Y",strtotime($sovpal[6]));
		$file_settings['time'] = $sovpal[7];
		$file_settings['name'] = $sovpal[9];

		return $file_settings;
	}
	
	/**
	 * Привести путь к нормальному виду
	 * 
	 * @param string $file
	 * @return string
	 */
	private function normalize_path($file)
	{
		/* Проверка на пустоту */
		$file = trim($file);
		if(empty ($file))
		{throw new Exception("Не указано имя файла");}
		
		/* Корень */
		if($file == "/")
		{return $file;}
		
		/* Нормализация */
		if(mb_substr($file, 0, 1, "UTF-8") != "/")
		{$file = $this->path.$file;}
		
		if(mb_substr($file, mb_strlen($file, "UTF-8")-1, 1, "UTF-8") == "/")
		{$file = mb_substr($file, 0, mb_strlen($file, "UTF-8")-1);}
		
		/* Проверка пути (/path1///path2) */
		$file_check = $file;
		if(mb_substr($file_check, 0, 1, "UTF-8") == "/")
		{
			$file_check = mb_substr($file_check, 1, mb_strlen($file_check, "UTF-8"));
		}
		
		$ar = explode("/", $file_check);
		foreach ($ar as $val)
		{
			$val = trim($val);
			if(empty($val))
			{throw new Exception("Путь задан неверно");}
		}
		
		return $file;
	}
	
	/**
	 * Копировать файл
	 *
	 * @param string $source
	 * @param string $dest
	 * @return bool
	 */
	private function cp_file($source, $dest)
	{
		$tmp_file = tmpfile();
		if(!@ftp_fget($this->conn_id, $tmp_file, $source, FTP_BINARY))
		{
			$error = error_get_last();
			throw new Exception("Не удалось получить содержимое FTP-файла. ".$error['message']);
		}
		fseek($tmp_file, 0);
		if(!@ftp_fput($this->conn_id, $dest, $tmp_file, FTP_BINARY))
		{
			$error = error_get_last();
			throw new Exception("Не удалось загрузить файл на FTP-сервер. ".$error['message']);
		}
		fclose($tmp_file);
		
		return true;
	}

	/**
	 * Копировать папку
	 *
	 * @param string $source
	 * @param string $dest
	 * @return bool
	 */
	private function cp_dir($source, $dest)
	{
		if(!$this->is_dir($dest))
		{$this->mkdir($dest);}
		
		$files = $this->ls($source);
		if(!empty ($files))
		{
			foreach ($files as $val)
			{
				/* Копировать файл */
				if($val['type'] == "file")
				{
					$this->cp_file($source."/".$val['name'], $dest."/".$val['name']);
				}
				/* Копировать папку */
				elseif($val['type'] == "dir")
				{
					$this->cp_dir($source."/".$val['name'], $dest."/".$val['name']);
				}
			}
		}
		
		return true;
	}
	
	/**
	 * Удалить папку
	 *
	 * @param string $dir
	 * @return bool
	 */
	private function rm_dir($dir)
	{
		/* Список файлов */
		$files = $this->ls($dir);
		
		if(!empty ($files))
		{
			foreach ($files as $val)
			{
				/* Удалить файл */
				if($val['type'] == "file")
				{
					if(!@ftp_delete($this->conn_id, $dir."/".$val['name']))
					{
						$error = error_get_last();
						throw new Exception("Не удалось удалить файл \".".$dir."/".$val['name']."\". ".$error['message']);
					}
				}
				/* Удалить папку */
				elseif($val['type'] == "dir")
				{
					$this->rm_dir($dir."/".$val['name']);
				}
			}
		}
		
		/* Удалить пустую папку */
		if(!@ftp_rmdir($this->conn_id, $dir))
		{
			$error = error_get_last();
			throw new Exception("Не удалось удалить папку \"{$dir}\". ".$error['message']);
		}
		
		return true;
	}
	
	/**
	 * Рекурсивно установить права на папку
	 * 
	 * @param type $dir
	 * @param type $mode
	 * @return bool
	 */
	private function chmod_dir($dir, $mode)
	{
		/* Текущая папка */
		if(!@ftp_chmod($this->conn_id, $mode, $dir))
		{
			$error = error_get_last();
			throw new Exception("Не удалось установить права \"{$mode}\" на папку \"{$dir}\". ".$error['message']);
		}
		
		$files = $this->ls($dir);
		if(!empty ($files))
		{
			foreach ($files as $val)
			{
				/* Файл */
				if($val['type'] == "file")
				{
					if(!@ftp_chmod($this->conn_id, $mode, $dir."/".$val['name']))
					{
						$error = error_get_last();
						throw new Exception("Не удалось установить права \"{$mode}\" на файл \"".$dir."/".$val['name']."\". ".$error['message']);
					}
				}
				/* Папка */
				elseif($val['type'] == "dir")
				{
					$this->chmod_dir($dir."/".$val['name'], $mode);
				}
			}
		}
		
		return true;
	}
	
	/**
	 * Получить размер файла
	 * 
	 * @param string $file
	 * @return int 
	 */
	private function size_file($file)
	{
		$raw_list = ftp_rawlist($this->conn_id, $file);
		$file_raw = array_pop($raw_list);
		$file_settings = $this->raw_razbor($file_raw);
		$size = $file_settings['size'];
		
		return $size;
	}

	/**
	 * Получить размер папки в байтах
	 * 
	 * @param string $dir
	 * @return int
	 */
	private function size_dir($dir)
	{
		$size = 0;
		
		$files = $this->ls($dir);
		if(!empty ($files))
		{
			foreach ($files as $val)
			{
				if($val['type'] == "file")
				{
					$size += $val['size'];
				}
				elseif($val['type'] == "dir")
				{
					$size += $val['size'];
					$size += $this->size_dir($dir."/".$val['name']);
				}
			}
		}
		
		return $size;
	}
}

?>