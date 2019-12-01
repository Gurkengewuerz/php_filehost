<?php
////////////////////////////////////////////////////////////////////////////////
// generate a random string of characters with given length
////////////////////////////////////////////////////////////////////////////////
function rndStr($len)
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-';
    $maxIdx = strlen($chars) - 1;
    $out = '';
    while ($len--) {
        $out .= $chars[mt_rand(0, $maxIdx)];
    }
    return $out;
}

////////////////////////////////////////////////////////////////////////////////
// check php.ini settings and print warnings if anything's not configured
// properly
////////////////////////////////////////////////////////////////////////////////
function checkConfig()
{
    if (!extension_loaded("sqlite3")) {
        die("php module sqlite3 not installed.");
    }

    if (!extension_loaded("curl")) {
        die("php module curl not installed.");
    }

    global $MAX_FILESIZE;
    global $UPLOAD_TIMEOUT;
    warn_config_value('upload_max_filesize', "MAX_FILESIZE", $MAX_FILESIZE);
    warn_config_value('post_max_size', "MAX_FILESIZE", $MAX_FILESIZE);
    warn_config_value('max_input_time', "UPLOAD_TIMEOUT", $UPLOAD_TIMEOUT);
    warn_config_value('max_execution_time', "UPLOAD_TIMEOUT", $UPLOAD_TIMEOUT);
}

function warn_config_value($ini_name, $var_name, $var_val)
{
    $ini_val = intval(ini_get($ini_name));
    if ($ini_val < $var_val)
        printf("<pre>Warning: php.ini: %s (%s) set lower than %s (%s)\n</pre>",
            $ini_name,
            $ini_val,
            $var_name,
            $var_val);
}