<?php

define('URL', 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
//функция для записи событий в лог-файл
function writeToLog($data, $title = '') {
    $log = "\n------------------------\n";
    $log .= date("Y.m.d G:i:s") . "\n";
    $log .= (strlen($title) > 0 ? $title : 'DEBUG') . "\n";
    $log .= print_r($data, 1);
    $log .= "\n------------------------\n";
    file_put_contents(getcwd() . '/hook.log', $log, FILE_APPEND);
    return true;
}
//функция для обновления токенов в базе данных
function DBUpdater($auth, $refresh, $domain) {
    $linkDB = mysqli_connect("localhost", "id13621142_chernyak", "Iu9UgY_?)72G-2#m", "id13621142_amocrmtask");
    $linkDB -> query("UPDATE auth SET AUTH_TOKEN = '$auth', REFRESH_TOKEN = '$refresh' WHERE DOMAIN = '$domain'");
    return true;
}
//функция отправки метода для получения списка сделок
function curlQueryGet($link, $header){
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-API-client/1.0');
    curl_setopt($curl, CURLOPT_URL, $link);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    $out = curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $code = (int) $code;
    $errors = array(
        301 => 'Moved permanently',
        400 => 'Bad request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not found',
        500 => 'Internal server error',
        502 => 'Bad gateway',
        503 => 'Service unavailable',
    );
    try
    {
        if ($code != 200 && $code != 204) {
            throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undescribed error', $code);
        }
    } catch (Exception $E) {
        die('Ошибка: ' . $E->getMessage() . PHP_EOL . 'Код ошибки: ' . $E->getCode());
    }
    
    $Response = json_decode($out, true);
    $Response = $Response['_embedded']['items'];
    return $Response;
    
}
//функция для отправки POST-данных
function curlQuery($method, $query, $header = array('Content-Type: application/json')){
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-API-client/1.0');
    curl_setopt($curl, CURLOPT_URL, $method);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($query));
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
    $out = curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $code = (int) $code;
    $errors = array(
        301 => 'Moved permanently',
        400 => 'Bad request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not found',
        500 => 'Internal server error',
        502 => 'Bad gateway',
        503 => 'Service unavailable',
    );
    try
    {
        if ($code != 200 && $code != 204) {
            throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undescribed error', $code);
        }
    
    } catch (Exception $E) {
        writeToLog($E, 'Ошибка выполнения запроса:');
        die('Ошибка: ' . $E->getMessage() . PHP_EOL . 'Код ошибки: ' . $E->getCode());
    }
    return $out;
}
/*функция для получения токена доступа
(предусмотрен функционал для первого доступа без обновляемого токена)*/
function getAccessToken($domain) {
    $linkDB = mysqli_connect("localhost", "id13621142_chernyak", "Iu9UgY_?)72G-2#m", "id13621142_amocrmtask");
    $auth = $linkDB -> query ("SELECT * FROM auth WHERE DOMAIN = '$domain'") -> fetch_assoc();
    $method = $domain . '/oauth2/access_token';
    $grantType = $auth['REFRESH_TOKEN'] != null ? 'refresh_token' : 'authorization_code';
    $authType = $auth['REFRESH_TOKEN'] != null ? ['refresh_token' => $auth['REFRESH_TOKEN']] 
        : ['code' => $auth['AUTH_TOKEN']];
    $data = [
    	'client_id' => $auth['INTEGRATION_ID'],
    	'client_secret' => $auth['SECRET_CODE'],
    	'grant_type' => $grantType,
    	'redirect_uri' => URL,
    ];
    $fields = array_merge($data, $authType);
    $auth = curlQuery($method, $fields);
    $response = json_decode($auth, true);
    $access_token = $response['access_token'];
    $refresh_token = $response['refresh_token'];
    DBUpdater($access_token, $refresh_token, $domain);
    return $access_token;
}
//основная функция обновления сделок
function allDealsUpdater($domain){
    $linkDB = mysqli_connect("localhost", "id13621142_chernyak", "Iu9UgY_?)72G-2#m", "id13621142_amocrmtask");
    $query = ['filter' => [
                'tasks' => 1,
            ]];
    $method = $domain . '/api/v2/leads?' . http_build_query($query);
    $access_token = getAccessToken($domain);
    $header = [
        'Authorization: Bearer ' . $access_token
    ];
    $out = curlQueryGet($method, $header);
    foreach ($out as $deal){
        $tasks['add'] = array(
            array(
                'element_id' => $deal['id'],
                'element_type' => 2,
                'task_type' => 1,
                'text' => 'Сделка без задачи',
                'responsible_user_id' => $deal['responsible_user_id'],
                'complete_till_at' => time() + (24 * 60 * 60),
            )
        );
        $apiMethod = $domain . '/api/v2/tasks';
        curlQuery($apiMethod, $tasks, $header);
    }
    return 'Все сделки обновлены';
}
?>
<html>
    <head>
        <title>Создание пустых задач для сделок</title>
    </head>
    <body>
        <pre>
            <?php
            $domain = 'https://chernyakartyom.amocrm.ru';
            if(!$_REQUEST){print_r(allDealsUpdater($domain));}
            ?>
        </pre>
    </body>
</html>
