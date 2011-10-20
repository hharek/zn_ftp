<?php
require_once ('zn_ftp.php');

try
{
	
	$ftp = new ZN_FTP("example.com", "ftpuser", "ftppass");						// классическое соединение
	$ftp = new ZN_FTP("example.com", "ftpuser", "ftppass", "/public_html");		// путь используемый при относительных путях
	$ftp = new ZN_FTP("example.com", "ftpuser", "ftppass", "/", 10021);			// нестандартный ftp порт 10021
	$ftp = new ZN_FTP("example.com", "ftpuser", "ftppass", "/", 21, true);		// соединение через явный ssl
	$ftp->connect();															// явное соединение
	$ftp->close();																// закрыть соединение
	
	if(!$ftp->is_file("index.php") and !$ftp->is_dir("index.php"))
	{echo "Папки или фаила не существует";}
	
	$files_and_dirs = $ftp->ls("lib");											// показать файл и папки в папку "/public_html/lib"
	$jpg_files = $ftp->ls("/public_html/images", "file", "jpg");				// показать jpg файлы в папке "/public_html/images"
	$dir = $ftp->ls("/public_html", "dir");										// показать папки в папке "/public_html"
	
	echo $ftp->get("css/default.css");											// показать /public_html/css/default.css
	$ftp->put(".htaccess", "Deny from all");									// записать строку в .htaccess
	
	$ftp->mkdir("/public_html/arhiv");											// создать папку css_old
	$ftp->cp("css", "arhiv");													// копировать /public_html/css /public_html/arhiv/css
	$ftp->cp("index.php", "/public_html/index_old.php");						// копировать index.php в index_old.php
	$ftp->mv("css_bad", "arhiv");												// перенести /public_html/css_bad /public_html/arhiv
	$ftp->rm("/public_html/tmp");												// удалить папку tmp
	$ftp->chmod("cache", 0777);													// установить рекрусивно права 777 на папку cache 
	
	echo $ftp->size("/public_html/upload");										// показать размер папки upload в байтах
	
	$ftp->upload($_FILES['image']['tmp_name'], "/upload/".$_FILES['image']['name'], true); // загрузить файл с формы
	
}
catch (Exception $e)
{
	echo $e->getMessage();
}

?>