<?php
require_once __DIR__ . '/app/header.php';
/** 顶部广告 */
if ($config['ad_top']) echo $config['ad_top_info'];
/** 检查登陆 */
mustLogin();
?>
<div class="col-md-12">
  <!-- 公告 -->
  <?php if (!empty($config['tips'])) : ?>
    <div class="marquee">
      <div class="wrap">
        <div id="marquee2">
          <?php echo $config['tips']; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- 图片/视频上传切换 -->
  <ul class="nav nav-pills" style="margin-bottom:10px;">
    <li class="active"><a href="#uploadImage" data-toggle="tab"><i class="icon icon-picture"></i> 图片上传</a></li>
    <?php if ($config['cnb_status']): ?>
    <li><a href="#uploadVideo" data-toggle="tab"><i class="icon icon-film"></i> 视频上传</a></li>
    <?php endif; ?>
  </ul>

  <div class="tab-content">
    <!-- 图片上传 -->
    <div class="tab-pane fade active in" id="uploadImage">
  <div id='upShowID' class="uploader col-md-12 clo-xs-12" data-ride="uploader" data-url="/app/upload.php">
    <div class="uploader-message text-center">
      <div class="content"></div>
      <button type="button" class="close">x</button>
    </div>
    <div class="uploader-files file-list file-list-lg file-rename-by-click" data-drag-placeholder="选择图片/Ctrl+V粘贴/拖拽至此处" style="min-height: 188px; border-style: dashed;"></div>
    <div class="uploader-actions">
      <button type="button" class="btn btn-link uploader-btn-browse"><i class="icon icon-plus"></i> 选择文件</button>
      <button type="button" class="btn btn-link uploader-btn-start"><i class="icon icon-cloud-upload"></i> 开始上传</button>
      <button type="button" class="btn btn-link uploader-btn-stop"><i class="icon icon-pause"></i> 暂停上传</button>
      <div class="uploader-status pull-right text-muted hidden-xs"></div>
      <div class="uploader-status pull-right text-muted col-xs-12 text-ellipsis visible-xs"></div>
    </div>
  </div>
    </div>

    <?php if ($config['cnb_status']): ?>
    <!-- 视频上传 -->
    <div class="tab-pane fade" id="uploadVideo">
  <div class="col-md-12" style="padding:0;">
    <div id="videoDropZone" style="min-height:188px;border:2px dashed #ccc;border-radius:6px;text-align:center;padding:40px 20px;cursor:pointer;transition:border-color 0.3s;" onclick="document.getElementById('videoFileInput').click()">
      <i class="icon icon-film" style="font-size:40px;color:#999;"></i>
      <p style="color:#999;margin-top:10px;">点击选择视频文件 或 拖拽视频至此处</p>
      <p style="color:#bbb;font-size:12px;">支持 mp4, webm, ogg, mov, avi, mkv, flv, wmv, m4v, 3gp, ts</p>
    </div>
    <input type="file" id="videoFileInput" accept="video/*,.mp4,.webm,.ogg,.mov,.avi,.mkv,.flv,.wmv,.m4v,.3gp,.ts" multiple style="display:none;" onchange="handleVideoFiles(this.files)">
    <div id="videoFileList" style="margin-top:10px;"></div>
    <div id="videoUploadBtn" style="margin-top:10px;text-align:center;display:none;">
      <button class="btn btn-primary" onclick="startVideoUpload()"><i class="icon icon-cloud-upload"></i> 开始上传</button>
    </div>
  </div>
    </div>
    <?php endif; ?>
  </div>
  <div class="col-md-12 clo-xs-12">
    <ul class="nav nav-tabs">
      <li <?php if ($config['upload_first_show'] == 1) echo 'class="active"'; ?> data-toggle="tooltip" title="图片直链">
        <a href="#" data-target="#tab2Content1" data-toggle="tab"><i class="icon icon-picture"></i></a>
      </li>
      <li <?php if ($config['upload_first_show'] == 2) echo 'class="active"'; ?> data-toggle="tooltip" title="论坛代码">
        <a href="#" data-target="#tab2Content2" data-toggle="tab"><i class="icon icon-chat"></i></a>
      </li>
      <li <?php if ($config['upload_first_show'] == 3) echo 'class="active"'; ?> data-toggle="tooltip" title="Markdown">
        <a href="#" data-target="#tab2Content3" data-toggle="tab"><i class="icon icon-code"></i></a>
      </li>
      <li <?php if ($config['upload_first_show'] == 4) echo 'class="active"'; ?> data-toggle="tooltip" title="HTML链接">
        <a href="#" data-target="#tab2Content4" data-toggle="tab"><i class="icon icon-html5"></i></a>
      </li>
      <li <?php if ($config['upload_first_show'] == 5) echo 'class="active"'; ?> data-toggle="tooltip" title="缩略图">
        <a href="#" data-target="#tab2Content5" data-toggle="tab"><i class="icon icon-camera"></i></a>
      </li>
      <li <?php if ($config['upload_first_show'] == 6) echo 'class="active"';
          if ($config['show_user_hash_del'] == 0) echo 'style="display:none;"' ?> data-toggle="tooltip" title="删除链接">
        <a href="#" data-target="#tab2Content6" data-toggle="tab"><i class="icon icon-trash"></i></a>
      </li>
    </ul>
    <div class="tab-content" style="text-align:right;">
      <div class="tab-pane fade <?php if ($config['upload_first_show'] == 1) echo 'active in';  ?>" id="tab2Content1">
        <textarea class="form-control" rows="5" id="links" readonly></textarea>
        <button class="btn btn-primary" data-toggle="tooltip" data-original-title="刷新" style="margin-top:5px;" onclick="location.reload()"><i class="icon icon-refresh"></i></button>
        <button class="btn btn-primary btnLinks" onclick="uploadCopy('links','.btnLinks')" data-toggle="tooltip" data-original-title="复制" data-loading-text="已复制" style="margin-top:5px;"><i class="icon icon-copy"></i></button>
      </div>
      <div class="tab-pane fade <?php if ($config['upload_first_show'] == 2) echo 'active in'; ?>" id="tab2Content2">
        <textarea class="form-control" rows="5" id="bbscode" readonly></textarea>
        <button class="btn btn-primary" data-toggle="tooltip" data-original-title="刷新" style="margin-top:5px;" onclick="location.reload()"><i class="icon icon-refresh"></i></button>
        <button class="btn btn-primary btnBbscode" onclick="uploadCopy('bbscode','.btnBbscode')" data-toggle="tooltip" data-original-title="复制" data-loading-text="已复制" style="margin-top:5px;"><i class="icon icon-copy"></i></button>
      </div>
      <div class="tab-pane fade <?php if ($config['upload_first_show'] == 3) echo 'active in'; ?>" id="tab2Content3">
        <textarea class="form-control" rows="5" id="markdown" readonly></textarea>
        <button class="btn btn-primary" data-toggle="tooltip" data-original-title="刷新" style="margin-top:5px;" onclick="location.reload()"><i class="icon icon-refresh"></i></button>
        <button class="btn btn-primary btnMarkDown" onclick="uploadCopy('markdown','.btnMarkDown')" data-toggle="tooltip" data-original-title="复制" data-loading-text="已复制" style="margin-top:5px;"><i class="icon icon-copy"></i></button>
      </div>
      <div class="tab-pane fade <?php if ($config['upload_first_show'] == 4) echo 'active in';  ?>" id="tab2Content4">
        <textarea class="form-control" rows="5" id="html" readonly></textarea>
        <button class="btn btn-primary" data-toggle="tooltip" data-original-title="刷新" style="margin-top:5px;" onclick="location.reload()"><i class="icon icon-refresh"></i></button>
        <button class="btn btn-primary btnHtml" onclick="uploadCopy('html','.btnHtml')" data-toggle="tooltip" data-original-title="复制" data-loading-text="已复制" style="margin-top:5px;"><i class="icon icon-copy"></i></button>
      </div>
      <div class="tab-pane fade <?php if ($config['upload_first_show'] == 5) echo 'active in';  ?>" id="tab2Content5">
        <textarea class="form-control" rows="5" id="thumb" readonly></textarea>
        <button class="btn btn-primary" data-toggle="tooltip" data-original-title="刷新" style="margin-top:5px;" onclick="location.reload()"><i class="icon icon-refresh"></i></button>
        <button class="btn btn-primary btnThumb" onclick="uploadCopy('thumb','.btnThumb')" data-toggle="tooltip" data-original-title="复制" data-loading-text="已复制" style="margin-top:5px;"><i class="icon icon-copy"></i></button>
      </div>
      <div class="tab-pane fade <?php if ($config['upload_first_show'] == 6) echo 'active in';  ?>" id="tab2Content6">
        <textarea class="form-control" rows="5" id="del" readonly></textarea>
        <button class="btn btn-primary" data-toggle="tooltip" data-original-title="刷新" style="margin-top:5px;" onclick="location.reload()"><i class="icon icon-refresh"></i></button>
        <button class="btn btn-primary btnDel" onclick="uploadCopy('del','.btnDel')" data-toggle="tooltip" data-original-title="复制" data-loading-text="已复制" style="margin-top:5px;"><i class="icon icon-copy"></i></button>
      </div>
    </div>
  </div>
</div>
<link rel="stylesheet" href="<?php static_cdn(); ?>/public/static/marquee/marquee.css">
<link rel="stylesheet" href="<?php static_cdn(); ?>/public/static/zui/lib/uploader/zui.uploader.min.css">
<script type="application/javascript" src="<?php static_cdn(); ?>/public/static/zui/lib/uploader/zui.uploader.min.js"></script>
<script type="application/javascript" src="<?php static_cdn(); ?>/public/static/marquee/marquee.min.js"></script>
<script type="application/javascript" src="<?php static_cdn(); ?>/public/static/EasyImage.js"></script>
<script>
  // 公告
  (function() {
    new Marquee({
      // 要滚动的元素
      elem: document.getElementById("marquee2"),
      // 每次滚动的步长(px)，默认0
      step: 30,
      // 滚动效果执行时间(ms)，默认400
      stepInterval: 400,
      // 每次滚动间隔时间(ms)，默认3000
      interval: 3000,
      // 滚动方向，up、down、left、right，默认为"left" 当前只支持上下
      dir: 'up',
      // 是否自动滚动，默认为true
      autoPlay: true,
      // 是否在鼠标滑过低级元素时暂停滚动，默认为true
      hoverPause: true
    });
  })();

  // 上传控制
  $('#upShowID').uploader({
    // 自动上传
    autoUpload: false,
    // 文件上传提交地址
    url: './app/upload.php',
    // 最大支持的上传文件
    max_file_size: <?php echo $config['maxSize']; ?>,
    // 分片上传 0为不分片 分片容易使图片上传失败
    chunk_size: <?php echo $config['chunks']; ?>,
    // 点击文件列表上传文件
    browseByClickList: true,
    // flash 上传组件地址
    flash_swf_url: '<?php static_cdn(); ?>/public/static/zui/lib/uploader/Moxie.swf',
    // silverlight 上传组件地址
    flash_swf_url: '<?php static_cdn(); ?>/public/static/zui/lib/uploader/Moxie.xap',
    // sign
    multipart_params: {
      'sign': new Date().getTime() / 1000 | 0,
    },
    // 预览图尺寸
    previewImageSize: {
      'width': 80,
      'height': 80
    },
    // 上传格式过滤
    filters: { // 只允许上传图片或图标（.ico）
      mime_types: [{
          title: '图片',
          extensions: '<?php echo $config['extensions']; ?>'
        },
        {
          title: '图标',
          extensions: 'ico'
        }
      ],
      prevent_duplicates: true
    },
    // 限制文件上传数目
    limitFilesCount: <?php echo $config['maxUploadFiles']; ?>,
    // 移除文件进行确认
    deleteConfirm: true,
    // 重置上传失败的文件
    autoResetFails: true,
    // 当文件上传进度发送变化时触发，此回调函数会在上传文件的过程中反复触发
    onUploadProgress: function(file) {
      NProgress.configure({
        barColor: '<?php echo $config['NProgress_Progress']; ?>'
      });
      NProgress.set(0)
      NProgress.set(file.percent / 100)
    },
    // 显示上传成功消息
    uploadedMessage: '已上传 {uploaded} 个文件，{failed} 个文件上传失败',
    // 当启用分片上传选项后，每个文件片段上传完成时触发
    onChunkUploaded: function(file, responseObject) {
      NProgress.set(responseObject.offset / responseObject.total);
    },
    <?php echo imgRatio(); ?>,
    responseHandler: function(responseObject, file) {
      var obj = JSON.parse(responseObject.response);
      console.log(file);
      console.log(obj);
      if (obj.code === 200) {
        $("#links").append(obj.url + "\r\n");
        if (obj.fileType === 'video') {
          $("#bbscode").append("[video]" + obj.url + "[/video]\r\n");
          $("#markdown").append("[" + obj.srcName + "](" + obj.url + ")\r\n");
          $("#html").append('&lt;video src="' + obj.url + '" controls width="100%"&gt;&lt;/video&gt;\r\n');
        } else {
          $("#bbscode").append("[img]" + obj.url + "[/img]\r\n");
          $("#markdown").append("![" + obj.srcName + "](" + obj.url + ")\r\n");
          $("#html").append('&lt;img src="' + obj.url + '" alt="' + obj.srcName + '" /&gt;\r\n');
        }
        $("#thumb").append(obj.thumb + "\r\n");
        $("#del").append(obj.del + "\r\n");

        new $.zui.Messager(obj.srcName + " 上传成功", {
          type: "primary",
          placement: 'bottom-right',
          icon: "check"
        }).show();

        try {
          console.log('history localStorage success');
          $.zui.store.set(obj.srcName, obj)
        } catch (err) {
          $.zui.messager.show('存储上传记录失败' + err, {
            icon: 'bell',
            time: 4000,
            type: 'danger',
            placement: 'top'
          });
          console.log('localStorage failed:' + err);
        }
      } else {
        new $.zui.Messager(obj.message, {
          type: "danger",
          placement: 'bottom-right',
          icon: "times"
        }).show();
        return;
      }
    },
  });

  <?php if ($config['cnb_status']): ?>
  var pendingVideos = [];

  var CNB_MAX_SIZE = 60 * 1024 * 1024;

  function compressVideo(file, callback) {
    var sizeMB = (file.size / 1048576).toFixed(1);
    if (file.size <= CNB_MAX_SIZE) {
      callback(file);
      return;
    }
    new $.zui.Messager('文件 ' + file.name + ' (' + sizeMB + 'MB) 超过64MB限制，正在压缩...', {type: 'warning', icon: 'compress', placement: 'bottom-right'}).show();

    var video = document.createElement('video');
    video.muted = true;
    video.playsInline = true;
    var url = URL.createObjectURL(file);
    video.src = url;

    video.onloadedmetadata = function() {
      var duration = video.duration;
      if (!duration || duration <= 0) {
        URL.revokeObjectURL(url);
        callback(file);
        return;
      }
      var targetBitrate = (CNB_MAX_SIZE * 8 * 0.85) / duration;
      if (targetBitrate < 200000) targetBitrate = 200000;

      var canvas = document.createElement('canvas');
      var scale = 1;
      if (video.videoWidth > 1280) scale = 1280 / video.videoWidth;
      canvas.width = Math.round(video.videoWidth * scale);
      canvas.height = Math.round(video.videoHeight * scale);
      var ctx = canvas.getContext('2d');

      var stream = canvas.captureStream(30);
      if (video.captureStream) {
        var audioStream = video.captureStream();
        audioStream.getAudioTracks().forEach(function(t) { stream.addTrack(t); });
      }

      var mimeType = 'video/webm;codecs=vp8,opus';
      if (!MediaRecorder.isTypeSupported(mimeType)) {
        mimeType = 'video/webm';
      }
      if (!MediaRecorder.isTypeSupported(mimeType)) {
        mimeType = '';
      }

      if (!mimeType) {
        URL.revokeObjectURL(url);
        new $.zui.Messager('浏览器不支持视频压缩，请手动压缩后上传', {type: 'danger', icon: 'times'}).show();
        callback(null);
        return;
      }

      var recorder = new MediaRecorder(stream, {
        mimeType: mimeType,
        videoBitsPerSecond: targetBitrate
      });
      var chunks = [];
      recorder.ondataavailable = function(e) {
        if (e.data && e.data.size > 0) chunks.push(e.data);
      };
      recorder.onstop = function() {
        var blob = new Blob(chunks, {type: mimeType.split(';')[0]});
        URL.revokeObjectURL(url);
        var newName = file.name.replace(/\.\w+$/, '.webm');
        var compressed = new File([blob], newName, {type: blob.type});
        var newSizeMB = (compressed.size / 1048576).toFixed(1);
        if (compressed.size > CNB_MAX_SIZE) {
          new $.zui.Messager('压缩后仍超过64MB (' + newSizeMB + 'MB)，请缩短视频或降低分辨率', {type: 'danger', icon: 'times'}).show();
          callback(null);
          return;
        }
        new $.zui.Messager('压缩完成: ' + sizeMB + 'MB → ' + newSizeMB + 'MB', {type: 'success', icon: 'ok-sign'}).show();
        callback(compressed);
      };
      recorder.onerror = function() {
        URL.revokeObjectURL(url);
        new $.zui.Messager('视频压缩失败，请手动压缩后上传', {type: 'danger', icon: 'times'}).show();
        callback(null);
      };

      video.currentTime = 0;
      video.onseeked = function() {
        video.play();
        recorder.start(1000);
      };

      video.onended = function() {
        recorder.stop();
      };
    };

    video.onerror = function() {
      URL.revokeObjectURL(url);
      new $.zui.Messager('无法读取视频文件', {type: 'danger', icon: 'times'}).show();
      callback(null);
    };
  }

  function handleVideoFiles(files) {
    var videoExts = ['mp4','webm','ogg','mov','avi','mkv','flv','wmv','m4v','3gp','ts'];
    for (var i = 0; i < files.length; i++) {
      var ext = files[i].name.split('.').pop().toLowerCase();
      if (videoExts.indexOf(ext) === -1) {
        new $.zui.Messager(files[i].name + ' 不是视频文件', {type: 'danger', icon: 'times'}).show();
        continue;
      }
      pendingVideos.push(files[i]);
    }
    renderVideoList();
    document.getElementById('videoFileInput').value = '';
  }

  function renderVideoList() {
    var html = '';
    for (var i = 0; i < pendingVideos.length; i++) {
      var f = pendingVideos[i];
      var sizeMB = (f.size / 1048576).toFixed(1);
      html += '<div class="alert alert-info" style="padding:8px 12px;margin-bottom:5px;" id="vitem-' + i + '">';
      html += '<i class="icon icon-film"></i> ' + f.name + ' <small class="text-muted">(' + sizeMB + ' MB)</small>';
      html += '<button class="btn btn-mini btn-danger pull-right" onclick="removeVideo(' + i + ')"><i class="icon icon-remove"></i></button>';
      html += '<div class="progress" style="display:none;margin-top:5px;margin-bottom:0;" id="vprog-' + i + '"><div class="progress-bar" style="width:0%"></div></div>';
      html += '</div>';
    }
    document.getElementById('videoFileList').innerHTML = html;
    document.getElementById('videoUploadBtn').style.display = pendingVideos.length > 0 ? 'block' : 'none';
  }

  function removeVideo(idx) {
    pendingVideos.splice(idx, 1);
    renderVideoList();
  }

  function startVideoUpload() {
    if (pendingVideos.length === 0) return;
    var videos = pendingVideos.slice();
    pendingVideos = [];
    renderVideoList();

    var idx = 0;
    function uploadNext() {
      if (idx >= videos.length) return;
      var file = videos[idx];
      var curIdx = idx;
      idx++;

      compressVideo(file, function(compressedFile) {
        if (!compressedFile) {
          uploadNext();
          return;
        }

        var formData = new FormData();
        formData.append('file', compressedFile);
        formData.append('sign', new Date().getTime() / 1000 | 0);

        var progEl = document.getElementById('vprog-' + curIdx);
        if (progEl) {
          progEl.style.display = 'block';
        }

        var xhr = new XMLHttpRequest();
        xhr.withCredentials = true;
        xhr.timeout = 600000;
        xhr.upload.onprogress = function(e) {
          if (e.lengthComputable && progEl) {
            var pct = Math.round((e.loaded / e.total) * 100);
            progEl.querySelector('.progress-bar').style.width = pct + '%';
            NProgress.set(pct / 100);
          }
        };
        xhr.onload = function() {
          if (progEl) {
            progEl.querySelector('.progress-bar').style.width = '100%';
            progEl.querySelector('.progress-bar').className = 'progress-bar progress-bar-success';
          }
          try {
            var obj = JSON.parse(xhr.responseText);
            if (obj.code === 200) {
              $("#links").append(obj.url + "\r\n");
              $("#bbscode").append("[video]" + obj.url + "[/video]\r\n");
              $("#markdown").append("[" + obj.srcName + "](" + obj.url + ")\r\n");
              $("#html").append('&lt;video src="' + obj.url + '" controls width="100%"&gt;&lt;/video&gt;\r\n');
              $("#thumb").append(obj.url + "\r\n");
              $("#del").append(obj.del + "\r\n");

              new $.zui.Messager(obj.srcName + " 视频上传成功", {
                type: "primary", placement: 'bottom-right', icon: "check"
              }).show();

              try { $.zui.store.set(obj.srcName, obj); } catch (err) {}
            } else {
              new $.zui.Messager(obj.message || '上传失败', {
                type: "danger", placement: 'bottom-right', icon: "times"
              }).show();
              if (progEl) {
                progEl.querySelector('.progress-bar').className = 'progress-bar progress-bar-danger';
              }
            }
          } catch (err) {
            new $.zui.Messager('上传响应解析失败', {type: 'danger', icon: 'times'}).show();
          }
          uploadNext();
        };
        xhr.onerror = function() {
          new $.zui.Messager(file.name + ' 上传失败(网络错误)', {type: 'danger', icon: 'times'}).show();
          if (progEl) {
            progEl.querySelector('.progress-bar').className = 'progress-bar progress-bar-danger';
          }
          uploadNext();
        };
        xhr.ontimeout = function() {
          new $.zui.Messager(file.name + ' 上传超时，文件可能过大', {type: 'danger', icon: 'times'}).show();
          if (progEl) {
            progEl.querySelector('.progress-bar').className = 'progress-bar progress-bar-danger';
          }
          uploadNext();
        };
        xhr.open('POST', './app/video_upload.php', true);
        xhr.send(formData);
      });
    }
    uploadNext();
  }

  var dropZone = document.getElementById('videoDropZone');
  var videoTab = document.getElementById('uploadVideo');
  if (videoTab) {
    videoTab.addEventListener('dragover', function(e) { e.preventDefault(); e.stopPropagation(); });
    videoTab.addEventListener('drop', function(e) { e.preventDefault(); e.stopPropagation(); });
  }
  if (dropZone) {
    dropZone.addEventListener('dragover', function(e) {
      e.preventDefault();
      e.stopPropagation();
      this.style.borderColor = '#3280fc';
    });
    dropZone.addEventListener('dragleave', function(e) {
      e.preventDefault();
      e.stopPropagation();
      this.style.borderColor = '#ccc';
    });
    dropZone.addEventListener('drop', function(e) {
      e.preventDefault();
      e.stopPropagation();
      this.style.borderColor = '#ccc';
      if (e.dataTransfer.files.length > 0) {
        handleVideoFiles(e.dataTransfer.files);
      }
    });
  }
  <?php endif; ?>
</script>
<?php
/** 环境检测 */
require_once APP_ROOT . '/app/check.php';
/** 底部广告 */
if ($config['ad_bot']) echo $config['ad_bot_info'];
/** 引入底部 */
require_once __DIR__ . '/app/footer.php';
