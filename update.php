<?php

//get all headers
if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

//curl get function
function curlGet($url, $ua, $auth = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERPWD, $auth);
    curl_setopt($ch, CURLOPT_USERAGENT, $ua);
    $response = curl_exec($ch);
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
        //request failed
        error_log('Curl request failed!' . $response);
        http_response_code(500);
        die('An error occurred when request:' . $url);
        $response = false;
    }
    curl_close($ch);
    return $response;
}

//get the directory
function getDirectory($repo, $path)
{
    $rep = json_decode(curlGet('https://api.github.com/repos/' . $repo . '/contents' . $path, 'HoYoRandom-PHP', $GLOBALS['ghAuth']));
    $files = array();
    foreach ($rep as $file) {
        switch ($file->type) {
            case 'file':
                #add to list
                $files[$file->name] = $file->path;
                break;
            case 'dir':
                if (substr($file->name, 0, 1) != '.') {
                    $files = array_merge($files, getDirectory($repo, '/' . $file->path));
                }
                break;
            default:
                break;
        }
    }
    return $files;
}

//verify the secret
function verifySecret($reqBody, $singature)
{
    $secret = $_ENV['WEBHOOK_SECRECT'];
    $result = 'sha256='.hash_hmac('sha256', $reqBody, $secret, false);
    return ($result == $singature);
}


//verify request
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}
if (isset($_ENV['WEBHOOK_SECRECT']) && !verifySecret(file_get_contents("php://input"), $_SERVER['HTTP_X_HUB_SINGATURE_256'])) {
    http_response_code(403);
    die('Invalid Secret');
}

var_dump($_SERVER);

//get the github auth token
$ghAuth = $_ENV['GITHUB_AUTH'] ?? '';

//get the directory
$repo = $_ENV['RES_REPO_NAME'] ?? http_response_code(500);die('Server error:RES_REPO_NAME no set!');
$files = getDirectory($repo, '/');

//write to file
empty($files) ?  file_put_contents(__DIR__ . '/contents.json', json_encode($files, JSON_UNESCAPED_UNICODE)) : http_response_code(500);die('Unable to get the file list');

//download the *.hitokoto.json
if (!is_dir(__DIR__ . '/hitokoto/')) {
    mkdir(__DIR__ . '/hitokoto/');
}
foreach ($files as $fileName => $filePath) {
    if (preg_match('/(\.hitokoto\.json)$/i', $fileName) == 1) {
        file_put_contents(__DIR__ . '/hitokoto/' . $fileName, curlGet('https://cdn.jsdelivr.net/gh/' . $repo . '/' . $filePath, 'HoYoRandom-PHP'));
    }
}

echo 'All Done!';
