<?php

require_once __DIR__ . '/../app/function.php';
require_once __DIR__ . '/../app/cnb_upload.php';

if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json; charset=utf-8');
    if (!is_who_login('admin')) {
        exit(json_encode(array('code' => 403, 'msg' => '请先登录'), JSON_UNESCAPED_UNICODE));
    }
    if (empty($config['cnb_status'])) {
        exit(json_encode(array('code' => 500, 'msg' => 'CNB未启用'), JSON_UNESCAPED_UNICODE));
    }
    $imgPath = isset($_POST['imgPath']) ? $_POST['imgPath'] : '';
    $filePath = isset($_POST['filePath']) ? $_POST['filePath'] : '';
    $assetId = isset($_POST['assetId']) ? $_POST['assetId'] : '';
    $result = array('code' => 500, 'msg' => '参数错误');

    if (!empty($imgPath)) {
        $resp = cnb_delete_img($imgPath);
        if ($resp['success']) {
            cnb_remove_from_img_index($imgPath);
            cnb_update_asset_count(-1);
            $result = array('code' => 200, 'msg' => '删除成功');
        } else {
            $errMsg = '删除失败';
            if (!empty($resp['message'])) $errMsg .= ': ' . $resp['message'];
            elseif (!empty($resp['httpCode'])) $errMsg .= ': HTTP ' . $resp['httpCode'];
            if (!empty($resp['data']) && is_array($resp['data']) && !empty($resp['data']['message'])) $errMsg .= ': ' . $resp['data']['message'];
            $result = array('code' => 500, 'msg' => $errMsg);
        }
    } elseif (!empty($filePath)) {
        $resp = cnb_delete_file($filePath);
        if ($resp['success']) {
            cnb_remove_from_video_index($filePath);
            cnb_update_asset_count(-1);
            $result = array('code' => 200, 'msg' => '删除成功');
        } else {
            $errMsg = '删除失败';
            if (!empty($resp['message'])) $errMsg .= ': ' . $resp['message'];
            elseif (!empty($resp['httpCode'])) $errMsg .= ': HTTP ' . $resp['httpCode'];
            if (!empty($resp['data']) && is_array($resp['data']) && !empty($resp['data']['message'])) $errMsg .= ': ' . $resp['data']['message'];
            $result = array('code' => 500, 'msg' => $errMsg);
        }
    } elseif (!empty($assetId)) {
        $resp = cnb_delete_asset($assetId);
        if ($resp['success']) {
            cnb_remove_from_all_indexes($imgPath ?: $filePath);
            cnb_update_asset_count(-1);
            $result = array('code' => 200, 'msg' => '删除成功');
        } else {
            $errMsg = '删除失败';
            if (!empty($resp['message'])) $errMsg .= ': ' . $resp['message'];
            elseif (!empty($resp['httpCode'])) $errMsg .= ': HTTP ' . $resp['httpCode'];
            if (!empty($resp['data']) && is_array($resp['data']) && !empty($resp['data']['message'])) $errMsg .= ': ' . $resp['data']['message'];
            $result = array('code' => 500, 'msg' => $errMsg);
        }
    }

    exit(json_encode($result, JSON_UNESCAPED_UNICODE));
}

if (isset($_GET['action']) && $_GET['action'] === 'list_assets') {
    header('Content-Type: application/json; charset=utf-8');
    if (!is_who_login('admin')) {
        exit(json_encode(array('code' => 403, 'msg' => '请先登录'), JSON_UNESCAPED_UNICODE));
    }

    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    if ($page < 1) $page = 1;
    $pageSize = 20;

    $cacheFile = APP_ROOT . '/admin/logs/cnb_asset_count.json';
    $totalCount = 0;
    if (file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
        if ($cache && isset($cache['count'])) {
            $totalCount = intval($cache['count']);
        }
    }

    if ($totalCount <= 0) {
        $allCount = 0;
        $syncPage = 1;
        while ($syncPage <= 100) {
            $syncResp = cnb_list_assets($syncPage, 100);
            if (!$syncResp['success'] || !is_array($syncResp['data'])) break;
            $syncAssets = array();
            if (isset($syncResp['data']['records']) && is_array($syncResp['data']['records'])) {
                $syncAssets = $syncResp['data']['records'];
            } elseif (isset($syncResp['data']['items']) && is_array($syncResp['data']['items'])) {
                $syncAssets = $syncResp['data']['items'];
            } elseif (isset($syncResp['data'][0]) && is_array($syncResp['data'][0])) {
                $syncAssets = $syncResp['data'];
            } else {
                $isAssoc = (is_array($syncResp['data']) && !empty($syncResp['data']) && array_keys($syncResp['data']) !== range(0, count($syncResp['data']) - 1));
                if (!$isAssoc) $syncAssets = $syncResp['data'];
            }
            if (empty($syncAssets)) break;
            $allCount += count($syncAssets);
            if (count($syncAssets) < 100) break;
            $syncPage++;
        }
        $totalCount = $allCount;
        @file_put_contents($cacheFile, json_encode(array('count' => $totalCount, 'updated' => time())), LOCK_EX);
    }

    $totalPages = max(1, ceil($totalCount / $pageSize));
    if ($page > $totalPages && $totalCount > 0) $page = $totalPages;

    $apiPage = ($totalCount > 0) ? ($totalPages - $page + 1) : $page;
    if ($apiPage < 1) $apiPage = 1;

    $resp = cnb_list_assets($apiPage, $pageSize);
    $assets = array();

    if ($resp['success'] && is_array($resp['data'])) {
        if (isset($resp['data']['records']) && is_array($resp['data']['records'])) {
            $assets = $resp['data']['records'];
        } elseif (isset($resp['data']['items']) && is_array($resp['data']['items'])) {
            $assets = $resp['data']['items'];
        } elseif (isset($resp['data'][0]) && is_array($resp['data'][0])) {
            $assets = $resp['data'];
        } else {
            $isAssoc = (is_array($resp['data']) && !empty($resp['data']) && array_keys($resp['data']) !== range(0, count($resp['data']) - 1));
            if (!$isAssoc) $assets = $resp['data'];
        }

        usort($assets, function($a, $b) {
            $ta = 0; $tb = 0;
            foreach (array('created_at', 'created_time', 'upload_time', 'ctime') as $key) {
                if (!empty($a[$key])) { $ta = strtotime($a[$key]); break; }
            }
            foreach (array('created_at', 'created_time', 'upload_time', 'ctime') as $key) {
                if (!empty($b[$key])) { $tb = strtotime($b[$key]); break; }
            }
            return $tb - $ta;
        });

        if (count($assets) >= $pageSize && $page >= $totalPages) {
            $totalPages = $page + 1;
            $totalCount = $totalPages * $pageSize;
        }
    }

    $items = array();
    foreach ($assets as $item) {
        $path = '';
        foreach (array('path', 'file_path', 'url', 'name', 'filename', 'key', 'object_key') as $key) {
            if (!empty($item[$key])) { $path = $item[$key]; break; }
        }
        $assetId = '';
        foreach (array('id', 'asset_id', 'record_id', 'oid') as $key) {
            if (!empty($item[$key])) { $assetId = $item[$key]; break; }
        }
        $size = 0;
        foreach (array('size_in_byte', 'size', 'file_size', 'bytes') as $key) {
            if (!empty($item[$key])) { $size = $item[$key]; break; }
        }
        $createdAt = '';
        foreach (array('created_at', 'created_time', 'upload_time', 'ctime') as $key) {
            if (!empty($item[$key])) { $createdAt = $item[$key]; break; }
        }
        $recordType = '';
        foreach (array('record_type', 'type', 'asset_type', 'kind') as $key) {
            if (!empty($item[$key])) { $recordType = $item[$key]; break; }
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $isImg = in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico', 'svg', 'avif'));
        $isVid = in_array($ext, array('mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'flv', 'wmv', 'm4v', '3gp', 'ts'));

        $resourcePath = $path;
        if (preg_match('/-\/imgs\/(.+)/', $path, $m)) $resourcePath = $m[1];
        elseif (preg_match('/-\/files\/(.+)/', $path, $m)) $resourcePath = $m[1];
        elseif (preg_match('/img-api\/(.+)/', $path, $m)) $resourcePath = $m[1];
        elseif (preg_match('/file-api\/(.+)/', $path, $m)) $resourcePath = $m[1];
        else {
            $parts = explode('/', trim($path, '/'));
            foreach (array('imgs', 'files') as $dir) {
                $idx = array_search($dir, $parts);
                if ($idx !== false && isset($parts[$idx + 1])) {
                    $resourcePath = implode('/', array_slice($parts, $idx + 1));
                    break;
                }
            }
        }

        $proxyDomain = !empty($config['cnb_proxy_domain']) ? rtrim($config['cnb_proxy_domain'], '/') : 'https://cnb.cool';
        $previewUrl = '';
        if ($isImg && !empty($resourcePath)) {
            if (!empty($config['cnb_proxy_domain'])) {
                $previewUrl = $proxyDomain . '/img-api/' . $resourcePath;
            } else {
                $previewUrl = $proxyDomain . '/' . $config['cnb_slug'] . '/-/imgs/' . $resourcePath;
            }
        } elseif ($isVid && !empty($resourcePath)) {
            if (!empty($config['cnb_proxy_domain'])) {
                $previewUrl = $proxyDomain . '/img-api/' . $resourcePath;
            } else {
                $previewUrl = $proxyDomain . '/' . $config['cnb_slug'] . '/-/files/' . $resourcePath;
            }
        } elseif (!empty($path)) {
            $previewUrl = $path;
            if (strpos($previewUrl, 'http') !== 0) {
                $previewUrl = 'https://cnb.cool' . (strpos($previewUrl, '/') === 0 ? '' : '/') . $previewUrl;
            }
        }

        $fileName = basename($path);
        if ($isVid) {
            $delType = 'file';
            $delParam = $resourcePath;
        } elseif ($isImg && !empty($resourcePath)) {
            $delType = 'img';
            $delParam = $resourcePath;
        } elseif (!empty($assetId)) {
            $delType = 'asset';
            $delParam = $assetId;
        } else {
            $delType = 'img';
            $delParam = $resourcePath;
        }

        $typeLabel = $isImg ? '图片' : ($isVid ? '视频' : '文件');
        $typeClass = $isImg ? 'label-primary' : ($isVid ? 'label-success' : 'label-default');

        $sizeStr = '';
        if ($size >= 1073741824) $sizeStr = round($size / 1073741824, 2) . ' GB';
        elseif ($size >= 1048576) $sizeStr = round($size / 1048576, 2) . ' MB';
        elseif ($size >= 1024) $sizeStr = round($size / 1024, 2) . ' KB';
        else $sizeStr = $size . ' B';

        $items[] = array(
            'path' => $path,
            'fileName' => $fileName,
            'resourcePath' => $resourcePath,
            'assetId' => $assetId,
            'previewUrl' => $previewUrl,
            'isImg' => $isImg,
            'isVid' => $isVid,
            'typeLabel' => $typeLabel,
            'typeClass' => $typeClass,
            'size' => $sizeStr,
            'createdAt' => $createdAt,
            'recordType' => $recordType,
            'delType' => $delType,
            'delParam' => $delParam,
            'rowId' => md5($path . $assetId),
        );
    }

    exit(json_encode(array(
        'code' => 200,
        'page' => $page,
        'pageSize' => $pageSize,
        'totalCount' => $totalCount,
        'totalPages' => $totalPages,
        'items' => $items,
        'apiSuccess' => $resp['success'],
        'apiHttpCode' => isset($resp['httpCode']) ? $resp['httpCode'] : 0,
    ), JSON_UNESCAPED_UNICODE));
}

if (isset($_GET['action']) && $_GET['action'] === 'sync_count') {
    header('Content-Type: application/json; charset=utf-8');
    if (!is_who_login('admin')) {
        exit(json_encode(array('code' => 403, 'msg' => '请先登录'), JSON_UNESCAPED_UNICODE));
    }
    $allCount = 0;
    $apiPage = 1;
    while ($apiPage <= 100) {
        $resp = cnb_list_assets($apiPage, 100);
        if (!$resp['success'] || !is_array($resp['data'])) break;
        $pageAssets = array();
        if (isset($resp['data']['records']) && is_array($resp['data']['records'])) {
            $pageAssets = $resp['data']['records'];
        } elseif (isset($resp['data']['items']) && is_array($resp['data']['items'])) {
            $pageAssets = $resp['data']['items'];
        } elseif (isset($resp['data'][0]) && is_array($resp['data'][0])) {
            $pageAssets = $resp['data'];
        } else {
            $isAssoc = (is_array($resp['data']) && !empty($resp['data']) && array_keys($resp['data']) !== range(0, count($resp['data']) - 1));
            if (!$isAssoc) $pageAssets = $resp['data'];
        }
        if (empty($pageAssets)) break;
        $allCount += count($pageAssets);
        if (count($pageAssets) < 100) break;
        $apiPage++;
    }
    $totalCount = $allCount;
    $cacheFile = APP_ROOT . '/admin/logs/cnb_asset_count.json';
    @file_put_contents($cacheFile, json_encode(array('count' => $totalCount, 'updated' => time())), LOCK_EX);
    exit(json_encode(array('code' => 200, 'count' => $totalCount)));
}

if (isset($_GET['action']) && $_GET['action'] === 'debug') {
    header('Content-Type: application/json; charset=utf-8');
    $resp = cnb_list_assets(1, 5);
    exit(json_encode(array(
        'success' => $resp['success'],
        'httpCode' => isset($resp['httpCode']) ? $resp['httpCode'] : 0,
        'headers' => isset($resp['headers']) ? $resp['headers'] : array(),
        'data' => $resp['data'],
        'raw' => isset($resp['raw']) ? substr($resp['raw'], 0, 3000) : '',
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

require_once __DIR__ . '/../app/header.php';

if (!is_who_login('admin')) {
    echo '<div class="alert alert-danger">请使用管理员账户登录</div>';
    header("refresh:2;url=" . $config['domain'] . "/admin/index.php");
    require_once APP_ROOT . '/app/footer.php';
    exit;
}

if (!$config['cnb_status']) {
    echo '<div class="alert alert-danger">CNB图床功能未开启</div>';
    header("refresh:2;url=" . $config['domain'] . "/admin/admin.inc.php");
    require_once APP_ROOT . '/app/footer.php';
    exit;
}

$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($currentPage < 1) $currentPage = 1;
?>

<div class="col-md-12">
    <h3 style="text-align:center;">CNB 资源管理</h3>
    <p style="text-align:center;color:#999;">管理 cnb.cool 上的图片、视频和文件资源，删除操作不可恢复
        <a href="?action=debug" target="_blank" class="btn btn-mini" style="font-size:10px;">调试API</a>
        <button class="btn btn-mini btn-warning" style="font-size:10px;" onclick="syncCount()">同步总数</button>
    </p>

    <div class="table-responsive">
        <table class="table table-hover table-striped table-bordered">
            <thead>
                <tr>
                    <th>预览</th>
                    <th>文件名/路径</th>
                    <th>类型</th>
                    <th>大小</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody id="assetsBody">
                <tr>
                    <td colspan="6" style="text-align:center;color:#999;padding:40px;">
                        <i class="icon icon-spinner icon-spin" style="font-size:24px;"></i>
                        <br><br>正在加载资源列表...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div id="paginationArea" style="text-align:center;margin:15px 0;display:none;">
        <span id="prevBtn"></span>
        <span id="pageNumbers"></span>
        <span id="totalCountInfo" style="margin:0 10px;"></span>
        <span id="nextBtn"></span>
        <span style="margin-left:15px;">
            跳转到 <input type="number" id="pageJumpInput" min="1" max="1" value="<?php echo $currentPage; ?>" style="width:60px;text-align:center;"> / <span id="totalPagesSpan">1</span> 页
            <button class="btn btn-primary btn-mini" onclick="jumpToPage()">跳转</button>
        </span>
    </div>
</div>

<script>
var currentPage = <?php echo $currentPage; ?>;
var totalPages = 1;
var totalCount = 0;
var isLoading = false;

function loadAssets(page) {
    if (isLoading) return;
    isLoading = true;
    currentPage = page;

    var tbody = document.getElementById('assetsBody');
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999;padding:40px;"><i class="icon icon-spinner icon-spin" style="font-size:24px;"></i><br><br>正在加载第 ' + page + ' 页...</td></tr>';

    $.getJSON('?action=list_assets&page=' + page, function(resp) {
        isLoading = false;
        if (resp.code !== 200) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#d9534f;">加载失败: ' + (resp.msg || '未知错误') + '</td></tr>';
            return;
        }

        totalPages = resp.totalPages || 1;
        totalCount = resp.totalCount || 0;
        document.getElementById('totalPagesSpan').textContent = totalPages;
        document.getElementById('pageJumpInput').max = totalPages;
        document.getElementById('pageJumpInput').value = page;

        var items = resp.items || [];
        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999;">暂无数据</td></tr>';
        } else {
            var html = '';
            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                html += '<tr id="row-' + item.rowId + '">';
                html += '<td style="width:120px;">';
                if (item.isImg && item.previewUrl) {
                    html += '<img src="' + escHtml(item.previewUrl) + '" alt="" style="max-width:100px;max-height:70px;border-radius:4px;cursor:pointer;" onclick="window.open(\'' + escJs(item.previewUrl) + '\', \'_blank\')">';
                } else if (item.isVid && item.previewUrl) {
                    html += '<video src="' + escHtml(item.previewUrl) + '" style="max-width:100px;max-height:70px;border-radius:4px;" controls preload="metadata" onclick="window.open(\'' + escJs(item.previewUrl) + '\', \'_blank\')"></video>';
                } else {
                    html += '<span class="text-muted" style="font-size:16px;"><i class="icon icon-file"></i></span>';
                }
                html += '</td>';
                html += '<td style="max-width:280px;word-break:break-all;font-size:12px;" title="' + escHtml(item.path) + '">';
                html += escHtml(item.fileName);
                html += '<br><small class="text-muted">' + escHtml(item.path) + '</small>';
                if (item.recordType) {
                    html += '<br><span class="label label-default" style="font-size:10px;">' + escHtml(item.recordType) + '</span>';
                }
                html += '</td>';
                html += '<td><span class="label ' + item.typeClass + '">' + item.typeLabel + '</span></td>';
                html += '<td>' + item.size + '</td>';
                html += '<td style="font-size:12px;">' + escHtml(item.createdAt) + '</td>';
                html += '<td>';
                html += '<button class="btn btn-mini btn-danger" onclick="doDelete(\'' + item.delType + '\', \'' + escJs(item.delParam) + '\', \'' + item.rowId + '\')">删除</button>';
                if (item.previewUrl) {
                    html += ' <a href="' + escHtml(item.previewUrl) + '" target="_blank" class="btn btn-mini btn-primary">查看</a>';
                    html += ' <button class="btn btn-mini" onclick="copyUrl(\'' + escJs(item.previewUrl) + '\')">复制</button>';
                }
                html += '</td>';
                html += '</tr>';
            }
            tbody.innerHTML = html;
        }

        renderPagination();
        document.getElementById('paginationArea').style.display = '';
    }).fail(function(xhr, status) {
        isLoading = false;
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#d9534f;">请求失败: ' + status + '</td></tr>';
    });
}

function renderPagination() {
    var prevHtml = '';
    if (currentPage > 1) {
        prevHtml = '<a href="javascript:loadAssets(' + (currentPage - 1) + ')" class="btn btn-primary">上一页</a>';
    }
    document.getElementById('prevBtn').innerHTML = prevHtml;

    var nextHtml = '';
    if (currentPage < totalPages) {
        nextHtml = '<a href="javascript:loadAssets(' + (currentPage + 1) + ')" class="btn btn-primary">下一页</a>';
    }
    document.getElementById('nextBtn').innerHTML = nextHtml;

    var startPage = Math.max(1, currentPage - 3);
    var endPage = Math.min(totalPages, currentPage + 3);
    var pageHtml = '';
    if (startPage > 1) pageHtml += '<a href="javascript:loadAssets(1)" class="btn btn-mini">1</a>';
    if (startPage > 2) pageHtml += '<span style="margin:0 4px;">...</span>';
    for (var i = startPage; i <= endPage; i++) {
        if (i == currentPage) {
            pageHtml += '<span class="btn btn-primary btn-mini" style="margin:0 2px;">' + i + '</span>';
        } else {
            pageHtml += '<a href="javascript:loadAssets(' + i + ')" class="btn btn-mini" style="margin:0 2px;">' + i + '</a>';
        }
    }
    if (endPage < totalPages - 1) pageHtml += '<span style="margin:0 4px;">...</span>';
    if (endPage < totalPages) pageHtml += '<a href="javascript:loadAssets(' + totalPages + ')" class="btn btn-mini">' + totalPages + '</a>';
    document.getElementById('pageNumbers').innerHTML = pageHtml;

    document.getElementById('totalCountInfo').innerHTML = '共' + totalCount + '条';
}

function doDelete(type, param, rowId) {
    var typeName = type === 'file' ? '视频文件' : (type === 'img' ? '图片' : '资源');
    if (!confirm('确定要删除这个' + typeName + '吗？删除后不可恢复！')) return;
    if (!confirm('再次确认：删除后不可恢复，确定继续？')) return;

    var postData = {action: 'delete'};
    if (type === 'img') postData.imgPath = param;
    else if (type === 'file') postData.filePath = param;
    else postData.assetId = param;

    $.post('?page=' + currentPage, postData, function(resp) {
        if (resp.code === 200) {
            new $.zui.Messager('删除成功', {type: 'success', icon: 'ok-sign'}).show();
            var row = document.getElementById('row-' + rowId);
            if (row) {
                row.style.opacity = '0.3';
                row.style.textDecoration = 'line-through';
                setTimeout(function() {
                    row.style.display = 'none';
                }, 500);
            }
            totalCount = Math.max(0, totalCount - 1);
            renderPagination();
        } else {
            new $.zui.Messager(resp.msg, {type: 'danger', icon: 'exclamation-sign'}).show();
        }
    }, 'json').fail(function() {
        new $.zui.Messager('删除请求失败', {type: 'danger', icon: 'exclamation-sign'}).show();
    });
}

function copyUrl(url) {
    var tmp = $('<textarea>');
    $('body').append(tmp);
    tmp.val(url).select();
    document.execCommand('copy');
    tmp.remove();
    new $.zui.Messager('链接已复制', {type: 'success', icon: 'ok-sign'}).show();
}

function jumpToPage() {
    var input = document.getElementById('pageJumpInput');
    var page = parseInt(input.value);
    var maxPage = parseInt(input.getAttribute('max'));
    if (isNaN(page) || page < 1) {
        new $.zui.Messager('请输入有效的页码', {type: 'warning', icon: 'exclamation-sign'}).show();
        return;
    }
    if (page > maxPage) {
        new $.zui.Messager('页码不能超过 ' + maxPage, {type: 'warning', icon: 'exclamation-sign'}).show();
        return;
    }
    loadAssets(page);
}

function syncCount() {
    new $.zui.Messager('正在同步总数，请稍候...', {type: 'info', icon: 'info-sign'}).show();
    $.getJSON('?action=sync_count', function(resp) {
        if (resp.code === 200) {
            new $.zui.Messager('同步完成，共 ' + resp.count + ' 条资源', {type: 'success', icon: 'ok-sign'}).show();
            loadAssets(currentPage);
        } else {
            new $.zui.Messager('同步失败: ' + (resp.msg || '未知错误'), {type: 'danger', icon: 'exclamation-sign'}).show();
        }
    }).fail(function() {
        new $.zui.Messager('同步请求失败', {type: 'danger', icon: 'exclamation-sign'}).show();
    });
}

function escHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function escJs(str) {
    if (!str) return '';
    return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

loadAssets(currentPage);
</script>

<?php
if ($config['ad_bot']) echo $config['ad_bot_info'];
require_once APP_ROOT . '/app/footer.php';
