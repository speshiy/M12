<?php
error_reporting(E_ALL ^ E_NOTICE);
date_default_timezone_set('Asia/Almaty');
if (!isset($_SESSION)) {
    session_start();
}
if (isset($_REQUEST['logout'])) {
    if (isset($_SERVER['PHP_AUTH_PW'])) {
        unset($_SERVER['PHP_AUTH_PW'], $_SERVER['PHP_AUTH_USER']);
        header('HTTP/1.0 401 Unauthorized');
    }
    //header('WWW-Authenticate: Basic realm="My Realm"');
    header("Refresh: 2; url=" . $_SERVER['SCRIPT_NAME']);
    die('Logout');
}
//print_r($_SERVER);
$rootPath = dirname(__FILE__);
$urlPath = dirname($_SERVER['SCRIPT_NAME']);
$urlPath='';
if ($urlPath == "\\")
    $urlPath = '';
require_once $rootPath . "/engine/autoload.php";
$debug = filter_var($_REQUEST['debug'], FILTER_VALIDATE_BOOLEAN);

function debug() {
    $var = null;
    $varName = null;
    $asText = false;
    $args = func_get_args();
    if (count($args) > 0 && $args[0] == 'textarea') {
        $asText = true;
        array_shift($args);
        /* unset($args[0]);
          ksort($args); */
    }
    if (count($args) == 0)
        return;
    if (count($args) == 1) {
        $var = $args[0];
    } else {
        if (gettype($args[0]) == 'string') {
            $varName = $args[0];
            array_shift($args);
            /* unset($args[0]);
              ksort($args); */
        }
        if (count($args) == 1) {
            $var = $args[0];
        } else {
            $var = $args;
        }
    }
    echo '<pre style="border: 2px solid #d96500; color: #d96500; background-color:#f7f4e2; display: inline-block; vertical-align:top;"><small><small>' . "\r\n";
    //echo " :: «".print_r($args,true)."»";
    //echo date("d.m.Y H:i:s")." ";
    $db = debug_backtrace();
    //fputs( $fp, print_r($db,true).NL );
    $path = array();
    $fIndex = 0;
    foreach ($db as $r) {
        if (isset($r['function'])) {
            unset($r['object']);
            unset($r['args']);
            if ($r['file'] == $_SERVER["DOCUMENT_ROOT"] . '/company/warehouse/PHPDebug.php') {
                $fIndex++;
            } elseif ($r['function'] != 'log' && $r['class'] != 'php_logfile') {
                //fputs( $fp, print_r($r,true).NL );
                array_unshift($path, "[" . $r['line'] . "]" . ( isset($r['class']) ? $r['class'] . '::' : '' ) . $r['function']
                );
            }
        }
    }
    /* for ($ii=count($db)-1;$ii>=1;$ii--) {
      if (isset($db[$ii]['function'])) {
      $path[] = ( isset($db[$ii]['class']) ? $db[$ii]['class'].'::' : '' ).$db[$ii]['function']."[".$db[$ii]['line']."]";
      }
      } */
    echo $db[$fIndex]['file'] . ':' . $db[$fIndex]['line'] . (!empty($path) ? NL . implode(' -> ', $path) : '');
    echo '</small></small>';
    /* foreach ($db as &$row) {
      unset($row['object']);
      unset($row['args']);
      }
      echo " :: «".print_r($db,true)."»"; */
    if (!empty($var) || !empty($varName)) {
        echo "\r\n ::";
        if (!empty($varName)) {
            echo " <span style=\"color:blue; white-space: nowrap;\">$varName</span>";
            if (!empty($var))
                echo " =";
        }
        if (!empty($var)) {
            if ($asText && is_string($var)) {
                echo "\r\n <textarea>$var</textarea>";
            } else {
                ob_start();
                //var_dump($var);
                var_export($var);
                $dump = ob_get_contents();
                ob_end_clean();
                $dump = str_replace("=> \n", "=>", $dump);
                //$dump .= print_r($var,true);
                echo " «<span style=\"color:#000\">" . htmlspecialchars($dump) . "</span>»";
            }
        }
    }
    echo '</pre>';
}

if (isset($_REQUEST['method'])) {
    $result = VersionManager::$_REQUEST['method']();
    $r = array_merge($_GET, $_POST);
    //unset($r['PHPSESSID']);
    //unset($r['random']);
    $r['debug'] = 'yes';
    $rURL = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?' . http_build_query($r);
    if (is_object($result)) {
        $result->rURL = $rURL;
    }
    if (is_array($result)) {
        $result['URL'] = $rURL;
    }
    if ($debug)
        debug('$result', $result);
    echo (filter_var($_REQUEST['as_html'], FILTER_VALIDATE_BOOLEAN) && is_string($result) ? $result : json_encode($result));
    exit();
}
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="My Realm"');
}
$appRole = 'none';
$usersRoles = array(
    'full' => array('role' => '', 'passwd' => '',),
    'beta' => array('role' => '', 'passwd' => '',),
    'release' => array('role' => '', 'passwd' => '',),
);
foreach ($usersRoles as $user => $row) {
    if (($_SERVER['PHP_AUTH_USER'] == $user && $_SERVER['PHP_AUTH_PW'] == $row['passwd'])) {
        $appRole = $row['role'];
    }
}
if (!isset($_SERVER['PHP_AUTH_USER']) || $appRole == 'none') {
    header('HTTP/1.0 401 Unauthorized');
    die('Access denied');
}

class VersionManager {

    static $conn = false;
    static $manager = "\\M12_Engine\\Controllers\\Manager";

    static function get() {
        $result = array();
        $db_link = self::connect('', 'updater', '123456', 'db_updater') or die(mysql_error());
        if (is_array($db_link)) {
            $result['errors'] = $db_link;
        } else {
            static::$conn = $db_link; 
            //$db = new \db($db_link);
            $dump = self::get_array("SELECT * FROM `releases` WHERE ISNULL(`production_completed_at`) and production_replication=0");
            //\debug::outecho('$dump',$dump);
            if (is_array($dump) && !empty($dump)) {
                $result['releases'] = array();
                foreach ($dump as $row) {
                    $result['releases'][] = self::updateReleaseRow($row);  
                }
            } 
        }
        return $result;
    }

    function createRelease() {
        $files = static::requestFiles();
        $title = static::strclean($_REQUEST['title']);
        //\debug::outecho('$files',$_REQUEST);
        //\debug::outecho('$files',$files);
        try {
            if (empty($title))
                throw new Exception("Empty title.");
            if (empty($files))
                throw new Exception("Empty files.");
            $manager = new static::$manager();
            $manager->createRelease($files, $title);
        } catch (Exception $e) {
            $output = "Error:\n";
            $output .= "Message: " . $e->getMessage() . "\n";
            $output .= "File: " . $e->getFile() . "\n";
            $output .= "Line: " . $e->getLine() . "\n";
            $output .= "Trace:\n" . $e->getTraceAsString() . "\n";
            return $output;
        }
        return 'Операция завершена';
    }

    function completeRelease() {
        $releaseId = (int) $_REQUEST['releaseId'];
        //\debug::outecho('$files',$_REQUEST);
        //\debug::outecho('$files',$files);
        try {
            if (empty($releaseId) || $releaseId == 0)
                throw new Exception("Empty ID.");
            $manager = new static::$manager();
            $manager->completeRelease($releaseId);
        } catch (Exception $e) {
            $output = "Error:\n";
            $output .= "Message: " . $e->getMessage() . "\n";
            $output .= "File: " . $e->getFile() . "\n";
            $output .= "Line: " . $e->getLine() . "\n";
            $output .= "Trace:\n" . $e->getTraceAsString() . "\n";
            return $output;
        }
        return 'Операция завершена';
    }

    static function requestFiles($key = 'files') {
        $files = array();
        $rf = is_array($_REQUEST[$key]) ? $_REQUEST[$key] : explode("\n", $_REQUEST[$key]);
        if (is_array($rf) && !empty($rf))
            foreach ($rf as $line) {
                //\debug::outecho('$line',$line);
                $line = static::strclean($line);
                if (!empty($line))
                    $files[] = $line;
            }
        //\debug::outecho('$files',$files);
        return $files;
    }

    function strclean($data) {
        $entry = trim($data);
        $entry = strip_tags($entry);
        //$entry = mysql_real_escape_string($entry);
        return $entry;
    }

    function updateReleaseRow($row) {
        //\debug::outecho('$row',$row);
        $row['id'] = (int) $row['id'];
        $row['created_at'] = \dates::fmtSmart($row['created_at']);
        $row['beta_started_at'] = \dates::fmtSmart($row['beta_started_at']);
        $row['beta_completed_at'] = \dates::fmtSmart($row['beta_completed_at']);
        //\debug::outecho('$row',$row);
        return $row;
    }

    static function connect($host, $user, $password, $base) {
        error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
        $errors = array();
        try {
            $db_link = mysql_connect($host, $user, $password);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
        if (!$db_link) {
            $errors[] = "connect failed: " . mysql_error();
            return $errors;
        }
        error_reporting(E_ALL ^ E_NOTICE);
        if (!mysql_select_db($base, $db_link)) {
            $errors[] = 'MySQL error ' . mysql_errno() . ": " . mysql_error();
        }
        if (!empty($errors)) {
            return $errors;
        }
        mysql_query("SET NAMES utf8");
        mysql_query("SET `time_zone` = '" . date('P') . "'");
        return $db_link;
    }

    public function get_array($sql) {
        $result = false;
        $query = mysql_query($sql, static::$conn);
        if ($query) {
            $result = array();
            if (mysql_num_rows($query) > 0) {
                while ($row = mysql_fetch_assoc($query)) {
                    $result[] = $row;
                }
            }
        }
        return $result;
    }

}

class dates {

    function fmtForMysql($dt = false) {
        if ($dt === false)
            return date('Y-m-d H:i:s');
        $dt = gettype($dt) == 'string' ? strtotime($dt) : $dt;
        return date('Y-m-d H:i:s', $dt);
    }

    function fmtRussian($dt, $format = 'j F Y г.') {
        $dt = gettype($dt) == 'string' ? strtotime($dt) : $dt;
        $date = explode(".", date("j.m.Y", $dt));
        switch ($date[1]) {
            case 1: $m = array('января', 'янв');
                break;
            case 2: $m = array('февраля', 'фев');
                break;
            case 3: $m = array('марта', 'мар');
                break;
            case 4: $m = array('апреля', 'апр');
                break;
            case 5: $m = array('мая', 'мая');
                break;
            case 6: $m = array('июня', 'июня');
                break;
            case 7: $m = array('июля', 'июля');
                break;
            case 8: $m = array('августа', 'авг');
                break;
            case 9: $m = array('сентября', 'сен');
                break;
            case 10: $m = array('октября', 'окт');
                break;
            case 11: $m = array('ноября', 'ноя');
                break;
            case 12: $m = array('декабря', 'дек');
                break;
        }
        $format = str_replace(array('F', 'M'), $m, $format);
        return date($format, $dt);
    }

    function fmtSmart($dt) {
        if (empty($dt))
            return '';
        $dt = gettype($dt) == 'string' ? strtotime($dt) : $dt;
        $tm = date('H:i', $dt);
        $date = self::fmtRussian($dt);
        if (date('Y-m-d', $dt) == date('Y-m-d'))
            $date = 'сегодня';
        if (date('Y-m-d', $dt) == date('Y-m-d', strtotime('-1 days')))
            $date = 'вчера';
        return $date . ' ' . $tm;
    }

}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

    <head lang="ru">
        <meta content="text/html; charset=utf-8" http-equiv="Content-Type"/>
        <META HTTP-EQUIV="PRAGMA" CONTENT="NO-CACHE"/>
        <meta http-equiv="Cache-Control" content="no-cache, must-revalidate"/>
        <meta http-equiv="X-UA-Compatible" content="IE=Edge"/>
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1.0, maximum-scale=1.0"/>
        <title><?= $appRole ?> [<?= $_SERVER['PHP_AUTH_USER'] ?>]</title>
        <script type='text/javascript' src='<?= $urlPath ?>/ui/js/jquery-1.12.1.js'></script>
        <script type='text/javascript' src='<?= $urlPath ?>/ui/js/jquery-migrate-1.2.1.js'></script>
        <script type="text/javascript">
            const appRole = '<?= $appRole ?>';
            var appRights = {
                versions_beta: (['full', 'beta'].indexOf(appRole) >= 0),
                versions_release: (['full', 'release'].indexOf(appRole) >= 0)
            };
            $.fn.destroy = function () {
                $(this).each(function () {
//$(this).children().destroy();
                    $(this).trigger('node.destroy');
                    $(this).off();
                    $(this).removeData();
                    $(this).empty();
                    $(this).remove();
                });
            };
            $.isEmpty = function (obj) {
                if (typeof (obj) == 'string')
                    return (obj.length <= 0);
                if ($.isArray(obj))
                    return (obj.length <= 0);
                if (typeof (obj) == 'object')
                    return $.isEmptyObject(obj);
                if (typeof (obj) == 'undefined')
                    return true;
            };
            $.fn.formFieldsData = function () {
                var result = {};
                var store = function () {
                    if (arguments.length === 3) {
                        var obj = arguments[0];
                        var key = arguments[1];
                        var value = arguments[2];
                        if (obj[key] !== undefined) {
                            if (!obj[key].push)
                                obj[key] = [obj[key]];
                            obj[key].push(value || '');
                        } else {
                            obj[key] = value || '';
                        }
                    }
                    if (arguments.length === 4) {
                        var obj = arguments[0];
                        var key = arguments[1];
                        var skey = arguments[2];
                        var value = arguments[3];
                        if (typeof (obj[key]) == 'undefined')
                            obj[key] = {};
                        store(obj[key], skey, value);
                    }
                };
                $(this).each(function () {
                    var $form = $(this);
                    $(this).find('[name]:not([disabled])').each(function () {
                        var $f = $(this);
                        if (!$f.prop('disabled')) {
                            var key = ($f.attr('data-field') ? $f.attr('data-field') : $f.attr('name'));
                            switch ($f.attr('type')) {
                                case 'checkbox':
                                    if ($f.is('[data-type="db_multicheckbox"]')) {
                                        store(result, key, $f.val(), $f.prop('checked') ? 'yes' : 'no');
                                    } else if ($f.is('.checkselect, .chbchecktozero, [value="yes"]')) {
                                        store(result, key, $f.prop('checked') ? 'yes' : 'no');
                                    } else {
                                        var list = $form.find('[name="' + $f.attr('name') + '"]:not([disabled])');
                                        if (list.length > 1) {
                                            if (typeof (result[key]) == 'undefined') {
                                                result[key] = [];
                                            } else if (!$.isArray(result[key])) {
                                                result[key] = [result[key]];
                                            }
                                        }
                                        if ($f.prop('checked')) {
                                            store(result, key, $f.val());
                                        }
                                    }
                                    break;
                                case 'radio':
                                    if ($f.prop('checked'))
                                        store(result, key, $f.val());
                                    break;
                                default:
                                    store(result, key, $f.val());
                                    break;
                            }
                        }
                    });
                });
                return result;
            };
            function doAjaxError(jqXHR, textStatus, errorThrown) {
                console.group('Ошибка: ' + textStatus);
                console.log({errorThrown: errorThrown, jqXHR: jqXHR});
                console.error(errorThrown);
                console.groupEnd();
                var msg_text = 'Ошибка соединения!';
                if (typeof (errorThrown) == 'string' && errorThrown != '')
                    msg_text = 'Ошибка: ' + errorThrown;
                if (textStatus == 'parsererror')
                    msg_text = 'Ошибка обработки данных!';
                if (errorThrown == 'timeout')
                    msg_text = 'Превышено время ожидания ответа';
                if (typeof (jqXHR) != 'undefined' && typeof (jqXHR.responseText) == 'string') {
                    msg_text = msg_text + '<br/><br/><div class="highlight" style="font-size:0.85em;">' + jqXHR.responseText + '</div>';
                }
                console.log(msg_text);
                return msg_text;
            }
            function Server() {
//console.warn({Server:this});
                var indicateLoading = false;
                var Callback = false;
                var sendData = {};
                var urlLine = {
                    url: '<?= $_ENV['SCRIPT_NAME'] ?>',
                    params: {
                    },
                    get: function () {
                        return this.url + '?' + $.param(this.params);
                    }
                };
                var params = urlLine.params;
                var strparams = [];
                for (var key in arguments) {
                    var arg = arguments[key];
                    switch (typeof (arg)) {
                        case 'boolean':
                            indicateLoading = arg;
                            break;
                        case 'string':
                            params.method = arg;
                            break;
                        case 'object':
                            //sendData = $.extend(true,{},sendData,arg);
                            sendData = arg;
                            break;
                        case 'function':
                            Callback = arg;
                            break;
                    }
                }
                params.random = Math.floor(Math.random() * 100000);
                var instance = this;
                var ajaxParams = {
                    url: urlLine.get(),
                    type: 'POST',
                    data: sendData,
                    dataType: "json",
                    error: doAjaxError,
                    success: function (data) {
                        if (indicateLoading)
                            $('#loading').hide();
                        if (typeof (Callback) == 'function') {
                            if (typeof (sendData.idrow) != 'undefined') {
                                Callback.call(instance, data, sendData.idrow);
                            } else {
                                Callback.call(instance, data);
                            }
                        }
                    }
                };
                if (sendData.as_html) {
                    ajaxParams.dataType = 'text';
                }
//console.info({indicateLoading:indicateLoading,Callback:Callback,sendData:sendData,urlLine:urlLine,params:params,ajaxParams:ajaxParams});
                return $.ajax(ajaxParams);
            }
            function Manager(data) {
                this.ajax = Server.bind(this);
                this.$content = $('<div>', {class: 'container flex-grid'}).appendTo('body');
                var block = $('<div>', {class: 'row cell-auto-size'}).appendTo(this.$content);
                console.group('VersionsClass');
                console.info(data);
                if (appRights.versions_beta)
                    this._makeColumnBeta(block, data);
                if (appRights.versions_release)
                    this._makeColumnRelease(block, data);
//$('<pre>',{id:'result',class:'result'}).appendTo(this.$content);
                console.groupEnd();
            }
            Manager.prototype = {
                _makeColumn: function (block, title, fn) {
                    var that = this;
                    var col = $('<div>', {class: 'cell row flex-dir-column'}).css({margin: '6px'}).appendTo(block);
                    var caption = $('<div>', {class: 'window-caption bg-teal fg-white text-shadow'}).appendTo(col);
                    var icon = $('<span>', {class: 'window-caption-icon'}).appendTo(caption);
                    $('<span>', {class: 'mif-cloud-upload'}).appendTo(icon);
                    var title2 = $('<span>', {class: 'window-caption-title padding10'}).html(title).appendTo(caption);
                    if (typeof (fn) == 'function') {
                        var btn = $('<button>', {type: 'button', class: 'image-button bg-pink bg-active-amber fg-white icon-right'}).html('Выгрузить').appendTo(caption);
                        btn.css({
                            float: 'right',
                            'margin-top': '-0.4375rem',
                            'margin-bottom': '-0.4375rem',
                            'margin-right': '-0.3125rem'
                        });
                        $('<span>', {class: 'icon mif-warning bg-darkPink'}).appendTo(btn);
                        btn.on('click', function () {
                            fn.apply(that, arguments);
                        });
                    }
                    return col;
                },
                _makeColumnBeta: function (block, data) {
                    var result, choiceBtn, textarea;
                    var that = this;
                    this.$colBeta = this._makeColumn(block, 'Beta', function () {
                        result.html('');
                        var form = this.$formBeta.formFieldsData();
                        if ($.isEmpty(form.files) && !$.isEmpty(textarea.val())) {
                            var line = textarea.val();
                            if (typeof (line) == 'string') {
                                form.files = line.split('\n');
                            }
                            //choiceBtn.trigger('click');
                            //return;
                        }
                        console.warn($.isEmpty(form.title), $.isEmpty(form.files), form);
                        if ($.isEmpty(form.title) || $.isEmpty(form.files)) {
                            if ($.isEmpty(form.title))
                                result.append('Надобно обозвать как-нибудь.\r\n');
                            if ($.isEmpty(form.files))
                                result.append('Да и файлы бы выбрать.\r\n');
                            return;
                        }
                        form.as_html = true;
                        result.html('Ждём-с...');
                        this.ajax('createRelease', form, function (answer) {
                            result.html(answer);
                        });
                    });
                    result = $('<pre>', {class: 'result'}).appendTo(this.$colBeta);
                    this.$formBeta = $('<form>').appendTo(this.$colBeta);
                    $('<input>', {type: 'text', name: 'title', placeholder: 'Введите название релиза'}).appendTo(
                            $('<div>', {class: 'input-control text full-size'}).appendTo(this.$formBeta)
                            );
                    this.$colBetaFiles = $('<div>').appendTo(this.$formBeta);
                    this.showSelectedFiles = function (filesList) {
                        this.$colBetaFiles.children().destroy();
                        if (typeof (filesList) == 'object' && !$.isEmpty(filesList)) {
                            var div = $('<div>', {class: 'treeview'}).appendTo(this.$colBetaFiles);
                            div.css({'font-size': '13px'});
                            this._buildTree(filesList).appendTo(div);
                            div.attr('data-role', 'treeview');
                            setTimeout(function () {
                                div.off('change', 'input:checkbox');
                                div.off('click', 'input');
                                div.off('click', '.check');
                            }, 1000);
                        }
                    };
                    this.showSelectedFiles(data.files);
                    var tad = $('<div>', {class: 'input-control textarea'}).appendTo(this.$colBeta);
                    textarea = $('<textarea>', {placeholder: 'Введите файлы или папки'}).appendTo(tad);
                    /*choiceBtn = $('<button>',{class:'button'}).appendTo(tad);
                     $('<span>',{class:'mif-folder'}).appendTo(choiceBtn);
                     $('<span>').html('&nbsp;Выбрать файлы').appendTo(choiceBtn);
                     choiceBtn.on('click',function(){
                     that.$colBetaFiles.addClass('loading');
                     that.ajax('getListFiles',{files:textarea.val()},function(answer){
                     that.$colBetaFiles.removeClass('loading');
                     that.showSelectedFiles(answer.files);
                     });
                     });*/
                },
                _makeColumnRelease: function (block, data) {
                    var result, table;
                    var fn = (typeof (data.releases) == 'object' && !$.isEmpty(data.releases)) ? function () {
                        result.html('');
                        var form = table.formFieldsData();
                        if (typeof (form.releaseId) == 'undefined') {
                            result.html('Выбрать что-нибудь не судьба?');
                            return;
                        }
                        form.as_html = true;
                        result.html('Ждём-с...');
                        this.ajax('completeRelease', form, function (answer) {
                            result.html(answer);
                        });
                    } : false;
                    this.$colRelease = this._makeColumn(block, 'Release', fn);
                    result = $('<pre>', {class: 'result'}).appendTo(this.$colRelease);
                    if (typeof (fn) == 'function') {
                        function trRow(tag, datarow) {
                            //console.warn(datarow);
                            var tr = $('<tr>');
                            for (var i in datarow) {
                                //console.log(datarow[i]);
                                if (typeof (datarow[i]) != 'function') {
                                    $('<' + tag + '>').html(datarow[i]).appendTo(tr);
                                }
                            }
                            return tr;
                        }
                        table = $('<table>', {class: 'table bordered striped hovered'}).appendTo(this.$colRelease);
                        var thead = $('<thead>').appendTo(table);
                        trRow('th', ['id, label', 'created', 'beta started', 'beta completed']).appendTo(thead);
                        table = $('<tbody>').appendTo(table);
                        for (var i in data.releases) {
                            var r = data.releases[i];
                            if (typeof (r) == 'object') {
                                var row = [r.created_at, r.beta_started_at, r.beta_completed_at];
                                var label = $('<label>', {class: 'input-control radio small-check'});
                                $('<input>', {type: 'radio', name: 'releaseId', value: r.id}).appendTo(label);
                                $('<span>', {class: 'check'}).appendTo(label);
                                $('<span>', {class: 'caption'}).html('#' + r.id + ' `' + r.label + '`').appendTo(label);
                                row.unshift(label);
                                console.warn('row', row);
                                trRow('td', row).appendTo(table);
                            }
                        }
                    }
                }
            };
            $(document).ready(function () {
                Server('get', function (data) {
                    $('#loading').destroy();
                    var manager = new Manager(data);
                    console.info(manager);
                });
            });
        </script>
        <link href="<?= $urlPath ?>/ui/css/metro.css" rel="stylesheet" type="text/css"/>
        <link href="<?= $urlPath ?>/ui/css/metro-colors.css" rel="stylesheet" type="text/css"/>
        <link href="<?= $urlPath ?>/ui/css/metro-schemes.css" rel="stylesheet" type="text/css"/>
        <link href="<?= $urlPath ?>/ui/css/metro-icons.css" rel="stylesheet" type="text/css"/>
        <link href="<?= $urlPath ?>/ui/css/metro-responsive.css" rel="stylesheet" type="text/css"/>
        <style type="text/css">
            #loading {
                height:100%;
            }
            pre.result:not(:empty) {
                font-family: "Courier New", monospace;
                font-size:1.1em;
                background: black;
                color: greenyellow;
                padding: 1em;
                overflow-x: auto;
            }
            .loading > * {
                display:none;
            }
            .loading {
                min-width:200px;
                min-height:50px;
                text-align: center !important;
                vertical-align: middle !important;
                background: transparent !important;
                position: relative;
            }
            .loading:before {
                content:"Подождите...";
                background-image: URL("<?= $urlPath ?>/ui/loading2.gif");
                background-repeat: no-repeat;
                background-position: center left;
                background-size: contain;
                font-style: italic;
                display: inline-block;
                position:absolute;
                background-color: #fff;
                left:40%;
                top:30%;
                padding: 3px 12px;
                padding-left: 32px;
                border-radius:16px;
                letter-spacing: 1px;
            }
        </style>
    </head>
    <body>
        <div id="loading" class="loading"></div>
    </body>
</html>