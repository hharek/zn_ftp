<?php
require_once ("zn_ftp.php");

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
	
	$files_and_dirs = $ftp->ls("lib");											// показать файлы и каталоги в папке "/public_html/lib"
	$jpg_files = $ftp->ls("/public_html/images", "file", "jpg");				// показать jpg файлы в папке "/public_html/images"
	$dir = $ftp->ls("/public_html", "dir");										// показать папки в папке "/public_html"
	
	echo $ftp->get("css/default.css");											// показать /public_html/css/default.css
	$ftp->put(".htaccess", "Deny from all");									// записать строку в .htaccess
	
	$ftp->mkdir("/public_html/arhiv");											// создать папку arhiv
	$ftp->cp("css", "arhiv");													// копировать /public_html/css /public_html/arhiv/css
	$ftp->cp("index.php", "/public_html/index_old.php");						// копировать index.php в index_old.php
	$ftp->mv("css_bad", "arhiv");												// перенести /public_html/css_bad /public_html/arhiv
	$ftp->rm("/public_html/tmp");												// удалить папку tmp
	$ftp->chmod("cache", 0777);													// установить рекрусивно права 777 на папку cache 
    $ftp->mv("arhiv/css", ".");                                                 // перенести папку css в /public_html
	
	echo $ftp->size("/public_html/upload");										// показать размер папки upload в байтах
	
	$ftp->upload($_FILES['image']['tmp_name'], "/upload/".$_FILES['image']['name'], true); // загрузить файл с формы
    $ftp->upload_dir("/home/user/icons", "/www/images/icons");                  // закачать папку
    
    $ftp->set_path("/www");                                                     // выбрать папку для chroot
    $ftp->chroot_enable();                                                      // включить chroot
    echo $ftp->get("/log/error.log");                                           // выдаст ошибку т.к. за пределами корневой папки
    $ftp->chroot_disable();                                                     // отключить chroot
    
    $ftp->download("/log/access.log");                                          // скачать файл access.log
    $ftp->download("/log/error.log", "/home/user/error.log");                   // скачать файл error.log в локальную папку
    $ftp->download_dir("/www", "/arhiv/site/".date("Y-m-d", time()));           // сделать архив файлов сайта
    
    $dirs_and_files = array
    (
        '/www/img',
        '/log/access.log',
        'favicon.ico',
        '/www/upload'
    );
    $ftp->download_and_zip_paths($dirs_and_files, "main.zip");                  // скачать файлы и папки zip-архивом (окно загрузки)
    $ftp->download_and_zip_paths("/log", null, "/arhiv/".date("Y-m-d", time()).".zip");  // Скачать логи в zip-архив
    
	$ftp_app = clone $ftp;														// клон FTP с тем же соединением
	$ftp_app->set_path("/app");
	
}
catch (Exception $e)
{
	echo $e->getMessage();
}

?>
