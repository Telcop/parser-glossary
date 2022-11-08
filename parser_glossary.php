<?php
//$log = 'Началась работа скрипта. Время: ' . locTimeString() . '<br>';
$log = 'Началась работа скрипта.<br>';
//запись в лог
file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . "log.txt", $log);

include 'simple_html_dom.php';

set_time_limit(0);
header('Content-type: text/html; charset=utf-8');
// устанавливаем московское время
date_default_timezone_set("Europe/Moscow");

/* Загрузка страницы при помощи cURL */
function curl_get_contents($page_url, $base_url, $pause_time = 0, $retry = 0)
{
	/*
	$page_url - адрес страницы-источника
	$base_url - адрес страницы для поля REFERER
	$pause_time - пауза между попытками парсинга
	$retry - 0 - не повторять запрос, 1 - повторить запрос при неудаче
	*/
	$error_page = array();
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0");
	curl_setopt($ch, CURLOPT_COOKIEJAR, str_replace("\\", "/", getcwd()) . __DIR__ . '/glossary_cook.txt');
	curl_setopt($ch, CURLOPT_COOKIEFILE, str_replace("\\", "/", getcwd()) . __DIR__ . '/glossary_cook.txt');
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Автоматом идём по редиректам
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // Не проверять SSL сертификат
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // Не проверять Host SSL сертификата
	curl_setopt($ch, CURLOPT_URL, $page_url); // Куда отправляем
	curl_setopt($ch, CURLOPT_REFERER, $base_url); // Откуда пришли
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Возвращаем, но не выводим на экран результат
	$response['html'] = curl_exec($ch);
	$info = curl_getinfo($ch);
	if ($info['http_code'] != 200 && $info['http_code'] != 404) {
		$error_page[] = array(1, $page_url, $info['http_code']);
		if ($retry) {
			sleep($pause_time);
			$response['html'] = curl_exec($ch);
			$info = curl_getinfo($ch);
			if ($info['http_code'] != 200 && $info['http_code'] != 404)
				$error_page[] = array(2, $page_url, $info['http_code']);
		}
	}
	$response['code'] = $info['http_code'];
	$response['errors'] = $error_page;
	curl_close($ch);
	return $response;
}

// Инициализация библиотеки simple_html_dom.php без использования команды file_get_html
function init_html($page)
{
	// Создаем класс для работы с библиотекой simple_html_dom.php
	$dom = new simple_html_dom(
		null,
		true,
		true,
		DEFAULT_TARGET_CHARSET,
		true,
		DEFAULT_BR_TEXT,
		DEFAULT_SPAN_TEXT
	);
	$dom->load($page, true, true);
	return $dom;
}

function create_descr_xml($descr)
{
	foreach ($descr as $tag => $txt) {
		$out .= "<" . $tag . ">" . $txt . "</" . $tag . ">" . "\r\n";
	}
	return $out;
}

function create_item_xml($item)
{
	$out = "<item>" . "\r\n";
	foreach ($item as $key => $var) {
		if ($key != 'description') {
			if ($key == "meta_description")
				$out .= "<description>" . $var . "</description>" . "\r\n";
			else
				$out .= "<" . $key . ">" . $var . "</" . $key . ">" . "\r\n";
		}
		// else {
		// 	$out .= "<" . $key . ">" . "\r\n";
		// 	foreach ($var as $descr) {
		// 		$out .= create_descr_xml($descr);
		// 	}
		// 	$out .= "</" . $key . ">" . "\r\n";
		// }
	}
	$out .= "</item>" . "\r\n";
	$out = str_replace("&#x27;&#x27;", "\"", $out);
	$out = str_replace("&#x27;", "'", $out);
	$out = str_replace(" ", " ", $out);
	file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . "glossary.xml", $out, FILE_APPEND);
}


function create_descr_txt($descr)
{
	$out = "";
	$h = array("h1", "h2", "h3", "h4", "h5", "h6");
	$i = 0;
	foreach ($descr as $item) {
		if (in_array(key($item), $h)) {
			$out .= key($item) . " - \"" . $item[key($item)] . "\"" . "\r\n";
		} else {
			if ($i > 0 && in_array(key($descr[$i - 1]), $h))
				$out .= "Текст \"";
			$out .= $item[key($item)];
			if ($i >= count($descr) - 1)
				$out .= "\"";
			elseif (in_array(key($descr[$i + 1]), $h))
				$out .= "\"";
			$out .= "\r\n";
		}
		$i++;
	}
	return $out;
}


function create_item_txt($item)
{
	// $out = $create_descr_txt($item['description']);
	$out = "h1 - \"" . $item['h1'] . "\"\r\n" . "Превью \"" . $item['preview'] . "\"\r\n";
	$out .= create_descr_txt($item['description']);
	$out = str_replace("&#x27;&#x27;", "\"", $out);
	$out = str_replace("&#x27;", "'", $out);
	$out = str_replace(" ", " ", $out);
	file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . $item['file'], $out);
}


// Сохраняем время начала парсинга
$time_start = time();

$urls = array(); // ссылки на определения
$url = 'https://coinmarketcap.com/alexandria/glossary';
$namepage  = '';
$url_base = "https://coinmarketcap.com/alexandria";

// Парсим ссылки на глоссарий
// $time = (rand(5, 20) / 20);
$time = 0.001;
$content = curl_get_contents($url, $url_base, $time, 1);
$html = init_html($content['html']);
// sleep(rand(5, 20) / 20);
echo "Ссылки на определения спарсен<br>";
$i = 0;
foreach ($html->find('a.Entry-qnfygj-0') as $items) {
	$i++;
	$urls[] = "https://coinmarketcap.com" . $items->href;
}
// записываем в лог сколько определений спарсено
$log = 'Спарсено ' . $i . ' определений.<br>';
file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . "log.txt", $log, FILE_APPEND);

// формируем начало xml файл
$out = '<?xml version="1.0" encoding="UTF-8"?>' . "\r\n";
$out .= '<glossary date="' . date('Y-m-d H:i') . '">' . "\r\n";
file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . "glossary.xml", $out);


$url_base = $url;
// Парсим определения
$i = 0;
foreach ($urls as $url) {
	$i++;
	$time = (rand(5, 20) / 20);
	//$time = 0;
	$content = curl_get_contents($url, $url_base, $time, 1);
	$html = init_html($content['html']);
	file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . "html.txt", $html);
	sleep(rand(5, 20) / 20);
	$item['h1'] =  trim($html->find('h1', 0)->plaintext);
	$item['url'] = $url;
	$item['file'] = str_replace("/alexandria/glossary/", "", parse_url($url)['path']) . ".txt";
	$item['title'] = trim($html->find('title', 0)->plaintext);
	$item['meta_description'] = trim($html->find('meta[name=description]', 0)->attr['content']);
	$item['tag'] = trim($html->find('div.Label__StyledLabel-sc-1t4rrpc-0', 0)->plaintext);
	$item['preview'] = trim($html->find('div.GlossaryContent__SummaryBox-k7dren-0 p', 0)->plaintext);

	echo $i, ") url: ", $item['url'], " title: ", $item['title'], " tag: ", $item['tag'], "<br>";
	echo "Превью:<br>", $item['preview'], "<br>";
	echo "title: ", $item['title'], "<br>";
	echo "desciption: ", $item['meta_description'], "<br>";
	// description
	$descr = array();
	$description = $html->find('div.Container-sc-4c5vqs-0', 0)->find('h2.iLpgJa, .Renderer___StyledText-mfgg8t-5');
	foreach ($description as $tag) {
		$descr[] = array($tag->tag => trim($tag->plaintext));
	}
	$item['description'] = $descr;

	// сохраняеем спарсенное определение в xml файл
	create_item_xml($item);
	// сохраняем определение в файл txt
	// create_item_txt($item);

	// if ($i >= 4) break;
}

// формируем конец xml файл
$out = '</glossary>' . "\r\n";
file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . "glossary.xml", $out, FILE_APPEND);


$html->clear(); // подчищаем за собой
unset($html);


$log = 'Потраченное время на скрипт: - ' . (time() - $time_start) . ' сек.<br>';
echo '<br>Потраченное время на скрипт: - ', (time() - $time_start), ' сек.<br>';
file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . "log.txt", $log, FILE_APPEND);
