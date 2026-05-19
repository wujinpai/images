<?php

function cnb_upload($filePath, $fileName)
{
    global $config;

    if (empty($config['cnb_slug']) || empty($config['cnb_token'])) {
        return array(
            'success' => false,
            'message' => 'cnb.cool 配置不完整,请填写 cnb_slug 和 cnb_token',
        );
    }

    if (!file_exists($filePath)) {
        return array(
            'success' => false,
            'message' => '本地文件不存在: ' . $filePath,
        );
    }

    $fileSize = filesize($filePath);
    $type = $config['cnb_type'] ?: 'imgs';
    $slug = $config['cnb_slug'];
    $token = $config['cnb_token'];

    $metaUrl = 'https://api.cnb.cool/' . $slug . '/-/upload/' . $type;

    $metaBody = json_encode(array('name' => $fileName, 'size' => $fileSize));

    $ch = curl_init($metaUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $metaBody);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ));

    $metaResp = curl_exec($ch);
    $metaHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return array(
            'success' => false,
            'message' => '请求上传元数据失败(curl): ' . $curlError,
        );
    }

    if ($metaHttpCode !== 200) {
        return array(
            'success' => false,
            'message' => '获取上传元数据失败: HTTP ' . $metaHttpCode . ' - ' . $metaResp,
        );
    }

    $metaData = json_decode($metaResp, true);
    if (!$metaData || empty($metaData['upload_url'])) {
        return array(
            'success' => false,
            'message' => '上传元数据解析失败: ' . $metaResp,
        );
    }

    $uploadUrl = $metaData['upload_url'];
    $assets = isset($metaData['assets']) ? $metaData['assets'] : array();
    $assetPath = isset($assets['path']) ? $assets['path'] : '';

    $fileData = file_get_contents($filePath);

    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_PUT, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/octet-stream',
    ));

    $tmpFile = tmpfile();
    fwrite($tmpFile, $fileData);
    fseek($tmpFile, 0);
    curl_setopt($ch, CURLOPT_INFILE, $tmpFile);
    curl_setopt($ch, CURLOPT_INFILESIZE, strlen($fileData));

    $uploadResp = curl_exec($ch);
    $uploadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $uploadError = curl_error($ch);
    curl_close($ch);
    fclose($tmpFile);

    if ($uploadError) {
        return array(
            'success' => false,
            'message' => '上传文件到存储失败(curl): ' . $uploadError,
        );
    }

    if ($uploadHttpCode < 200 || $uploadHttpCode >= 300) {
        return array(
            'success' => false,
            'message' => '上传文件到存储失败: HTTP ' . $uploadHttpCode . ' - ' . $uploadResp,
        );
    }

    $imgPath = cnb_extract_path($assetPath);
    $proxyUrl = cnb_build_proxy_url($imgPath);

    return array(
        'success' => true,
        'url' => $proxyUrl,
        'assets' => $assets,
        'path' => $assetPath,
        'imgPath' => $imgPath,
    );
}

function cnb_extract_path($rawPath)
{
    $path = explode('?', $rawPath);
    $path = explode('#', $path[0]);
    $path = $path[0];
    if (preg_match('/-\/(?:imgs|files)\/(.+)/', $path, $match)) {
        return $match[1];
    }
    return $path;
}

function cnb_build_proxy_url($imgPath)
{
    global $config;
    $proxyDomain = !empty($config['cnb_proxy_domain']) ? rtrim($config['cnb_proxy_domain'], '/') : 'https://cnb.cool';
    if (!empty($config['cnb_proxy_domain'])) {
        return $proxyDomain . '/img-api/' . $imgPath;
    }
    $slug = $config['cnb_slug'];
    $type = $config['cnb_type'] ?: 'imgs';
    return $proxyDomain . '/' . $slug . '/-/' . $type . '/' . $imgPath;
}

function cnb_write_index($fileName, $imgPath, $proxyUrl, $sourceName = '', $fileSize = 0)
{
    global $config;

    $indexDir = APP_ROOT . '/admin/logs/cnb_index/';
    if (!is_dir($indexDir)) {
        mkdir($indexDir, 0755, true);
    }

    $indexFile = $indexDir . date('Y-m-d') . '.json';

    $indexData = array();
    if (file_exists($indexFile)) {
        $content = file_get_contents($indexFile);
        $indexData = json_decode($content, true);
        if (!is_array($indexData)) {
            $indexData = array();
        }
    }

    $indexData[$fileName] = array(
        'url' => $proxyUrl,
        'imgPath' => $imgPath,
        'source' => $sourceName,
        'size' => $fileSize,
        'date' => date('Y-m-d H:i:s'),
    );

    file_put_contents($indexFile, json_encode($indexData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function cnb_read_index($date = null)
{
    if ($date === null) {
        $date = date('Y-m-d');
    }

    $indexFile = APP_ROOT . '/admin/logs/cnb_index/' . $date . '.json';

    if (!file_exists($indexFile)) {
        return array();
    }

    $content = file_get_contents($indexFile);
    $indexData = json_decode($content, true);
    if (!is_array($indexData)) {
        return array();
    }

    return $indexData;
}

function cnb_get_index_dates()
{
    $indexDir = APP_ROOT . '/admin/logs/cnb_index/';
    if (!is_dir($indexDir)) {
        return array();
    }

    $dates = array();
    $files = glob($indexDir . '*.json');
    if ($files) {
        foreach ($files as $file) {
            $basename = basename($file, '.json');
            $dates[] = $basename;
        }
        rsort($dates);
    }

    return $dates;
}

function cnb_get_index_count($date = null)
{
    $indexData = cnb_read_index($date);
    return count($indexData);
}

function cnb_api_request($method, $path, $body = null)
{
    global $config;

    if (empty($config['cnb_slug']) || empty($config['cnb_token'])) {
        return array(
            'success' => false,
            'message' => 'cnb.cool 配置不完整',
        );
    }

    $slug = $config['cnb_slug'];
    $token = $config['cnb_token'];
    $url = 'https://api.cnb.cool/' . ltrim($path, '/');

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $token,
        'Accept: application/vnd.cnb.api+json',
        'Content-Type: application/json',
    ));

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $respHeaders = array();
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) use (&$respHeaders) {
        $len = strlen($header);
        $parts = explode(':', $header, 2);
        if (count($parts) === 2) {
            $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
        }
        return $len;
    });

    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return array(
            'success' => false,
            'message' => '请求失败(curl): ' . $curlError,
        );
    }

    return array(
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'httpCode' => $httpCode,
        'data' => json_decode($resp, true),
        'raw' => $resp,
        'headers' => $respHeaders,
    );
}

function cnb_list_assets($page = 1, $pageSize = 20)
{
    global $config;
    $slug = $config['cnb_slug'];
    $path = '/' . $slug . '/-/list-assets?page=' . intval($page) . '&page_size=' . intval($pageSize);
    return cnb_api_request('GET', $path);
}

function cnb_delete_img($imgPath)
{
    global $config;
    $slug = $config['cnb_slug'];
    $path = '/' . $slug . '/-/imgs/' . ltrim($imgPath, '/');
    return cnb_api_request('DELETE', $path);
}

function cnb_delete_file($filePath)
{
    global $config;
    $slug = $config['cnb_slug'];
    $path = '/' . $slug . '/-/files/' . ltrim($filePath, '/');
    return cnb_api_request('DELETE', $path);
}

function cnb_delete_asset($assetID)
{
    global $config;
    $slug = $config['cnb_slug'];
    $path = '/' . $slug . '/-/assets/' . intval($assetID);
    return cnb_api_request('DELETE', $path);
}

function cnb_upload_file($filePath, $fileName, $type = 'files')
{
    global $config;

    if (empty($config['cnb_slug']) || empty($config['cnb_token'])) {
        return array(
            'success' => false,
            'message' => 'cnb.cool 配置不完整',
        );
    }

    if (!file_exists($filePath)) {
        return array(
            'success' => false,
            'message' => '文件不存在: ' . $filePath,
        );
    }

    $fileSize = filesize($filePath);
    $slug = $config['cnb_slug'];
    $token = $config['cnb_token'];

    $metaUrl = 'https://api.cnb.cool/' . $slug . '/-/upload/' . $type;

    $metaBody = json_encode(array('name' => $fileName, 'size' => $fileSize));

    $ch = curl_init($metaUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $metaBody);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ));

    $metaResp = curl_exec($ch);
    $metaHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return array('success' => false, 'message' => '请求上传元数据失败(curl): ' . $curlError);
    }

    if ($metaHttpCode !== 200) {
        $errDetail = $metaResp;
        $errJson = json_decode($metaResp, true);
        if ($errJson && isset($errJson['errmsg'])) {
            $errDetail = $errJson['errmsg'];
        }
        if ($metaHttpCode == 400 && strpos($errDetail, '64') !== false) {
            return array('success' => false, 'message' => '文件大小超过CNB平台限制(64MiB)，请压缩视频后重新上传');
        }
        return array('success' => false, 'message' => '获取上传元数据失败: HTTP ' . $metaHttpCode . ' - ' . $errDetail);
    }

    $metaData = json_decode($metaResp, true);
    if (!$metaData || empty($metaData['upload_url'])) {
        return array('success' => false, 'message' => '上传元数据解析失败: ' . $metaResp);
    }

    $uploadUrl = $metaData['upload_url'];
    $assets = isset($metaData['assets']) ? $metaData['assets'] : array();
    $assetPath = isset($assets['path']) ? $assets['path'] : '';

    $fileData = file_get_contents($filePath);

    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_PUT, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/octet-stream',
    ));

    $tmpFile = tmpfile();
    fwrite($tmpFile, $fileData);
    fseek($tmpFile, 0);
    curl_setopt($ch, CURLOPT_INFILE, $tmpFile);
    curl_setopt($ch, CURLOPT_INFILESIZE, strlen($fileData));

    $uploadResp = curl_exec($ch);
    $uploadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $uploadError = curl_error($ch);
    curl_close($ch);
    fclose($tmpFile);

    if ($uploadError) {
        return array('success' => false, 'message' => '上传文件到存储失败(curl): ' . $uploadError);
    }

    if ($uploadHttpCode < 200 || $uploadHttpCode >= 300) {
        return array('success' => false, 'message' => '上传文件到存储失败: HTTP ' . $uploadHttpCode . ' - ' . $uploadResp);
    }

    $filePath2 = cnb_extract_path($assetPath);
    $proxyUrl = cnb_build_file_url($filePath2);

    return array(
        'success' => true,
        'url' => $proxyUrl,
        'assets' => $assets,
        'path' => $assetPath,
        'filePath' => $filePath2,
    );
}

function cnb_build_file_url($filePath)
{
    global $config;
    $proxyDomain = !empty($config['cnb_proxy_domain']) ? rtrim($config['cnb_proxy_domain'], '/') : 'https://cnb.cool';
    if (!empty($config['cnb_proxy_domain'])) {
        return $proxyDomain . '/img-api/' . $filePath;
    }
    $slug = $config['cnb_slug'];
    return $proxyDomain . '/' . $slug . '/-/files/' . $filePath;
}

function cnb_write_video_index($fileName, $filePath, $proxyUrl, $sourceName = '', $fileSize = 0)
{
    $indexDir = APP_ROOT . '/admin/logs/cnb_video_index/';
    if (!is_dir($indexDir)) {
        mkdir($indexDir, 0755, true);
    }

    $indexFile = $indexDir . date('Y-m-d') . '.json';

    $indexData = array();
    if (file_exists($indexFile)) {
        $content = file_get_contents($indexFile);
        $indexData = json_decode($content, true);
        if (!is_array($indexData)) {
            $indexData = array();
        }
    }

    $indexData[$fileName] = array(
        'url' => $proxyUrl,
        'filePath' => $filePath,
        'source' => $sourceName,
        'size' => $fileSize,
        'date' => date('Y-m-d H:i:s'),
    );

    file_put_contents($indexFile, json_encode($indexData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function cnb_read_video_index($date = null)
{
    if ($date === null) {
        $date = date('Y-m-d');
    }

    $indexFile = APP_ROOT . '/admin/logs/cnb_video_index/' . $date . '.json';

    if (!file_exists($indexFile)) {
        return array();
    }

    $content = file_get_contents($indexFile);
    $indexData = json_decode($content, true);
    if (!is_array($indexData)) {
        return array();
    }

    return $indexData;
}

function cnb_get_video_index_dates()
{
    $indexDir = APP_ROOT . '/admin/logs/cnb_video_index/';
    if (!is_dir($indexDir)) {
        return array();
    }

    $dates = array();
    $files = glob($indexDir . '*.json');
    if ($files) {
        foreach ($files as $file) {
            $dates[] = basename($file, '.json');
        }
        rsort($dates);
    }

    return $dates;
}

function cnb_get_video_index_count($date = null)
{
    return count(cnb_read_video_index($date));
}

function cnb_remove_from_img_index($imgPath)
{
    $indexDir = APP_ROOT . '/admin/logs/cnb_index/';
    if (!is_dir($indexDir)) return false;
    $files = glob($indexDir . '*.json');
    if (!$files) return false;
    $found = false;
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        if (!is_array($data)) continue;
        foreach ($data as $key => $item) {
            if (isset($item['imgPath']) && $item['imgPath'] === $imgPath) {
                unset($data[$key]);
                $found = true;
            }
        }
        if ($found) {
            file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        }
    }
    return $found;
}

function cnb_remove_from_video_index($filePath)
{
    $indexDir = APP_ROOT . '/admin/logs/cnb_video_index/';
    if (!is_dir($indexDir)) return false;
    $files = glob($indexDir . '*.json');
    if (!$files) return false;
    $found = false;
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        if (!is_array($data)) continue;
        foreach ($data as $key => $item) {
            if (isset($item['filePath']) && $item['filePath'] === $filePath) {
                unset($data[$key]);
                $found = true;
            }
        }
        if ($found) {
            file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        }
    }
    return $found;
}

function cnb_remove_from_all_indexes($resourcePath)
{
    cnb_remove_from_img_index($resourcePath);
    cnb_remove_from_video_index($resourcePath);
}

function cnb_update_asset_count($delta)
{
    $cacheFile = APP_ROOT . '/admin/logs/cnb_asset_count.json';
    $count = 0;
    if (file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
        if ($cache && isset($cache['count'])) $count = intval($cache['count']);
    }
    $count = max(0, $count + $delta);
    @file_put_contents($cacheFile, json_encode(array('count' => $count, 'updated' => time())), LOCK_EX);
}
