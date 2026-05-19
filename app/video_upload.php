<?php

require __DIR__ . '/function.php';
require __DIR__ . '/cnb_upload.php';
header("Content-type: application/json; charset=utf-8");

ini_set('max_execution_time', '600');
ini_set('upload_max_filesize', '500M');
ini_set('post_max_size', '500M');

if ($config['mustLogin']) {
    if (!is_who_login('status')) {
        exit(json_encode(
            array(
                "result"  => "failed",
                "code"    => 401,
                "message" => "本站已开启登陆上传,您尚未登陆",
            ),
            JSON_UNESCAPED_UNICODE
        ));
    }
}

if (empty($_FILES['file'])) {
    exit(json_encode(
        array(
            "result"  => "failed",
            "code"    => 204,
            "message" => "没有选择上传的文件",
        ),
        JSON_UNESCAPED_UNICODE
    ));
}

if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errMsg = '上传出错';
    switch ($_FILES['file']['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errMsg = '文件大小超过服务器限制(upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size') . ')，请联系管理员调整';
            break;
        case UPLOAD_ERR_PARTIAL:
            $errMsg = '文件上传不完整，请重试';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $errMsg = '服务器缺少临时目录';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $errMsg = '服务器写入失败';
            break;
    }
    exit(json_encode(
        array(
            "result"  => "failed",
            "code"    => 400,
            "message" => $errMsg,
        ),
        JSON_UNESCAPED_UNICODE
    ));
}

if (empty($_POST['sign']) || time() - $_POST['sign'] > 12306) {
    exit(json_encode(
        array(
            "result"  => "failed",
            "code"    => 403,
            "systime" => time(),
            "message" => "上传签名错误,请刷新重试",
        ),
        JSON_UNESCAPED_UNICODE
    ));
}

$videoExts = array('mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'flv', 'wmv', 'm4v', '3gp', 'ts');
$originalName = $_FILES['file']['name'];
$fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (empty($fileExt)) {
    $nameParts = explode('.', $originalName);
    if (count($nameParts) > 1) {
        $fileExt = strtolower(end($nameParts));
    }
}

if (!in_array($fileExt, $videoExts)) {
    exit(json_encode(
        array(
            "result"  => "failed",
            "code"    => 406,
            "message" => "仅支持视频文件: " . implode(', ', $videoExts) . " (检测到: " . $fileExt . ")",
        ),
        JSON_UNESCAPED_UNICODE
    ));
}

if (!$config['cnb_status']) {
    exit(json_encode(
        array(
            "result"  => "failed",
            "code"    => 500,
            "message" => "视频上传需要开启CNB图床功能",
        ),
        JSON_UNESCAPED_UNICODE
    ));
}

$tmpFile = $_FILES['file']['tmp_name'];
$fileSize = $_FILES['file']['size'];
$sourceName = pathinfo($originalName, PATHINFO_FILENAME);
if (empty($sourceName)) $sourceName = $originalName;

if ($fileSize > 64 * 1024 * 1024) {
    exit(json_encode(
        array(
            "result"  => "failed",
            "code"    => 400,
            "message" => "文件大小超过CNB平台限制(64MiB)，请压缩视频后重新上传",
        ),
        JSON_UNESCAPED_UNICODE
    ));
}

$newName = date('YmdHis') . mt_rand(1000, 9999) . '.' . $fileExt;

$cnbResult = cnb_upload_file($tmpFile, $newName, 'files');

if (isset($tmpFile) && file_exists($tmpFile)) {
    @unlink($tmpFile);
}

if (!$cnbResult['success']) {
    exit(json_encode(
        array(
            "result"  => "failed",
            "code"    => 500,
            "message" => "视频上传到 cnb.cool 失败: " . $cnbResult['message'],
        ),
        JSON_UNESCAPED_UNICODE
    ));
}

$videoUrl = $cnbResult['url'];
$videoPath = $cnbResult['filePath'];

cnb_write_video_index($newName, $videoPath, $videoUrl, $sourceName, $fileSize);
cnb_update_asset_count(1);

$reJson = array(
    "result"  => "success",
    "code"    => 200,
    "url"     => $videoUrl,
    "srcName" => $sourceName,
    "thumb"   => $videoUrl,
    "del"     => "Admin closed user delete",
    "fileType" => "video",
);
echo json_encode($reJson, JSON_UNESCAPED_UNICODE);

if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
