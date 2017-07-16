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
    global $MAX_FILESIZE;
    global $UPLOAD_TIMEOUT;
    warnConfig('upload_max_filesize', "MAX_FILESIZE", $MAX_FILESIZE);
    warnConfig('post_max_size', "MAX_FILESIZE", $MAX_FILESIZE);
    warnConfig('max_input_time', "UPLOAD_TIMEOUT", $UPLOAD_TIMEOUT);
    warnConfig('max_execution_time', "UPLOAD_TIMEOUT", $UPLOAD_TIMEOUT);
}

function warnConfig($iniName, $varName, $varValue)
{
    $iniValue = return_bytes(ini_get($iniName));
    if ($iniValue < $varValue)
        printf("<pre>Warning: php.ini: %s (%s) set lower than %s (%s)\n</pre>",
            $iniName,
            $iniValue,
            $varName,
            $varValue);
}

function return_bytes($val)
{
    $val = trim($val);
    $last = strtolower($val[strlen($val) - 1]);
    switch ($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }

    return $val;
}