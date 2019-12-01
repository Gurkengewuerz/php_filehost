<?php
require_once 'config.php';
require_once 'utils.php';
require_once 'database.php';
require_once 'github_api.php';

session_start();

////////////////////////////////////////////////////////////////////////////////
// decide what to do, based on POST parameters etc.
////////////////////////////////////////////////////////////////////////////////
if (isset($_SESSION["access_token"])) {
    $user = apiRequest('https://api.github.com/user');
    $uid = $user->id;
    $tokens = getUserTokens($uid, $user->login);

    if (empty($tokens)) {
        die("registration is disabled in this instance\r\nlogout <a href='?logout'>here</a>");
    }

    if (isset($_GET['sharex'])) {
        sendShareXConfig($tokens[0]);
    } else if (isset($_GET['hupl'])) {
        sendHuplConfig($tokens[0]);
    } else if (isset($_GET['kshare'])) {
        sendKShareConfig($tokens[0]);
    } else if (isset($_GET["add"])) {
        generateToken($uid);
        header('Location: ' . $_SERVER['PHP_SELF']);
    } else if (isset($_GET["del"]) && !empty($_GET["del"])) {
        $token = $_GET["del"];
        deleteToken($token, $uid);
        header('Location: ' . $_SERVER['PHP_SELF']);
    } else if (isset($_GET["logout"])) {
        session_destroy();
        header('Location: ' . $_SERVER['PHP_SELF']);
    }
} else {
    if (isset($_GET['login'])) {
        getLogin();
    } else if (isset($_GET["error"])) {
        echo '<pre>' . var_export($_GET, true) . '</pre>';
        die();
    } else if (isset($_GET["code"])) {
        if (!isset($_GET['state']) || $_SESSION['state'] != $_GET['state']) {
            header('Location: ' . $_SERVER['PHP_SELF']);
            die();
        }
        // Exchange the auth code for a token
        $token = apiRequest("https://github.com/login/oauth/access_token", array(
            'client_id' => $GITHUB_CLIENT_ID,
            'client_secret' => $GITHUB_CLIENT_SECRET,
            'redirect_uri' => $HTTP_PROTO . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'],
            'state' => $_SESSION['state'],
            'code' => $_GET['code']
        ));
        $_SESSION['access_token'] = $token->access_token;
        header('Location: ' . $_SERVER['PHP_SELF']);
    }
}

if (isset($_FILES["file"]["name"]) && isset($_FILES["file"]["tmp_name"]) && is_uploaded_file($_FILES["file"]["tmp_name"])) {  //file was uploaded, store it
    $formatted = isset($_GET["formatted"]) || isset($_POST["formatted"]);
    $token = "";

    if (isset($_SERVER['PHP_AUTH_USER'])) $token = $_SERVER['PHP_AUTH_USER'];
    if (isset($_GET["token"]) || isset($_POST["token"])) $token = (isset($_GET["token"]) ? $_GET["token"] : $_POST["token"]);
    storeFile($_FILES["file"]["name"], $_FILES["file"]["tmp_name"], $formatted, $token);
    die();
}

if (isset($_GET["file"])) {
    $file = getFile($_GET["file"]);
    if ($file != null) {
        header('Content-Type: ' . $file["mime"]);
        header('Content-Disposition: inline; filename="' . $file["origname"] . '"');
        echo file_get_contents($FILE_PATH . "/" . $file["fileid"] . "." . $file["origname"]);
    } else {
        header("HTTP/1.0 404 Not Found");
    }
}

if (count($_GET) == 0) {
    checkConfig();
    printInfo();
}


////////////////////////////////////////////////////////////////////////////////
// store an uploaded file, given its name and temporary path, (e.g. values 
// straight out of $_FILES)
// files are stored wit a randomised name, but with their original extension
////////////////////////////////////////////////////////////////////////////////
function storeFile($name, $tmpFile, $formatted = false, $token)
{
    global $ID_LENGTH;
    global $HTTP_PROTO;
    global $HTTP_PATH;
    global $FILE_PATH;
    global $MAX_FILESIZE;

    if (!checkToken($token)) {
        header("HTTP/1.0 401 Unauthorized");
        die("Unauthorized\n");
    }

    if (!file_exists($FILE_PATH)) {
        if (!mkdir($FILE_PATH, 0750, true)) {
            header("HTTP/1.0 500 Internal Server Error");
            die("Internal Server Error\n");
        }
    }

    //check file size
    $filesize = filesize($tmpFile);
    if ($filesize > $MAX_FILESIZE * 1024 * 1024) {
        header("HTTP/1.0 507 Max File Size Exceeded");
        die("Max File Size Exceeded\n");
    }

    $mime = mime_content_type($tmpFile);
    $len = $ID_LENGTH;
    do //generate filenames until we get one, that doesn't already exist
    {
        $id = rndStr($len++);
        $target_file = $id;
    } while ($target_file == null || getFile($target_file) != null);

    $res = move_uploaded_file($tmpFile, $FILE_PATH . "/" . $target_file . "." . $name) && logUpload($target_file, $mime, $name, $filesize);
    if ($res) {
        //print the download link of the file
        $url = sprintf("%s://%s%s%s",
            $HTTP_PROTO,
            $_SERVER["HTTP_HOST"],
            $HTTP_PATH,
            $target_file);
        if ($formatted) {
            printf("<pre>Access your file here:\n<a href=\"%s\">%s</a></pre>",
                $url, $url);
        } else {
            printf($url);
        }
    } else {
        header("HTTP/1.0 520 Unknown Error");
        die("Unknown Error\n");
    }
    unset($tmpFile);
}

////////////////////////////////////////////////////////////////////////////////
// send a ShareX custom uploader config as .json
////////////////////////////////////////////////////////////////////////////////
function sendShareXConfig($token)
{
    global $HTTP_PROTO;
    $host = $_SERVER["HTTP_HOST"];
    $path = $_SERVER['PHP_SELF'];
    $filename = $host . ".sxcu";
    $content = <<<EOT
{
  "Name": "$host",
  "DestinationType": "TextUploader, ImageUploader, FileUploader",
  "RequestType": "POST",
  "RequestURL": "$HTTP_PROTO://$host/$path",
  "FileFormName": "file",
  "Arguments": {
    "token": "$token"
  },
  "ResponseType": "Text"
}
EOT;
    header("Content-type: application/octet-stream");
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header("Content-Length: " . strlen($content));
    print($content);
}

////////////////////////////////////////////////////////////////////////////////
// send a Hupl uploader config as .hupl (which is just JSON)
////////////////////////////////////////////////////////////////////////////////
function sendHuplConfig($token)
{
    global $HTTP_PROTO;
    $host = $_SERVER["HTTP_HOST"];
    $path = $_SERVER['PHP_SELF'];
    $filename =  $host.".hupl";
    $content = <<<EOT
{
  "name": "$host",
  "type": "http",
  "targetUrl": "$HTTP_PROTO://$host/$path?token=$token",
  "fileParam": "file"
}
EOT;
    header("Content-type: application/octet-stream");
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header("Content-Length: ".strlen($content));
    print($content);
}

////////////////////////////////////////////////////////////////////////////////
// send a KSHare uploader config as .uploader (which is just JSON)
////////////////////////////////////////////////////////////////////////////////
function sendKShareConfig($token)
{
    global $HTTP_PROTO;
    $host = $_SERVER["HTTP_HOST"];
    $path = $_SERVER['PHP_SELF'];
    $filename =  $host.".uploader";
    $content = <<<EOT
{
    "name": "$host",
    "target": "$HTTP_PROTO://$host/$path?token=$token",
    "format": "multipart-form-data",
    "body": [
        {
            "__Content-Type": "/%contenttype/",
            "filename": "/%filename/",
            "name": "file",
            "body": "/%imagedata/"
        }
    ],
    "return": "|"
}
EOT;
    header("Content-type: application/octet-stream");
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header("Content-Length: ".strlen($content));
    print($content);
}

////////////////////////////////////////////////////////////////////////////////
// print a plaintext info page, explaining what this script does and how to
// use it, how to upload, etc.
// essentially the homepage
////////////////////////////////////////////////////////////////////////////////
function printInfo()
{
    global $ADMIN_EMAIL;
    global $HTTP_PROTO;
    global $MAX_FILESIZE;

    $if = function ($condition, $true, $false) {
        return $condition ? $true : $false;
    };
    $url = $HTTP_PROTO . "://" . $_SERVER["HTTP_HOST"] . $_SERVER['PHP_SELF'];
    $sharexUrl = $url . "?sharex";
    $huplUrl = $url . "?hupl";
    $kshareUrl = $url . "?kshare";
    $authenticated = false;
    $tokens = [""]; // prevent Undefined variable

    $text = 'First Login via GitHub to generate a token. <a href="?login">Login here</a>.';
    if (isset($_SESSION["access_token"])) {
        $user = apiRequest('https://api.github.com/user');
        $uid = $user->id;
        $tokens = getUserTokens($uid, $user->login);
        if (count($tokens) > 0) {
            $authenticated = true;
            $text = "Hello, " . $user->login . " (#" . $uid . "). Your auth keys:\n";
            if (count($tokens) > 1) {
                foreach ($tokens as $token) {
                    $text .= "<a href='?del=" . $token . "'>[X]</a> " . $token . "\n";
                }
            } else {
                $text .= $tokens[0] . "\n";
            }
            $text .= "<a href='?add'>[Generate another key]</a> <a href='?logout'>[Logout]</a>";
        }
    }

    echo <<<EOF
<html>
<head>
    <title>File Share</title>
    <meta name="description" content="Private minimalistic service for sharing files." />
    <style>
        input[type=submit] {
            border-radius: 1px;
            border: black solid 1px;
            background: white;
            font-family: monospace;
            padding: 4px 10px;
            cursor: pointer;
            margin-left: 20px;
        }
        
        input[type=submit]:hover {
            font-weight: bold;
        }
        
        .pre-upload {
            border-radius: 1px;
            border: black solid 1px;
            background: white;
            font-family: monospace;
            padding: 4px 10px;
            cursor: pointer;
        }
        
        input[type=file] {
            display: none;
        }
        
    </style>
</head>
<body>
<pre>
 === How To Upload ===
$text
{$if($authenticated, '
You can upload files to this site via a simple HTTP POST, e . g . using curl:
curl -F "file=@/path/to/your/file.jpg" -u ' . $tokens[0] . ': \
 ' . $url . '

Token authentication is possible via username in basic-auth or with the
POST/GET field "token"

On Windows, you can use <a href="https://getsharex.com/">ShareX</a> and import <a href="' . $sharexUrl . '" target="_blank">this</a> custom uploader.
On Android, you can use an app called <a href="https://play.google.com/store/apps/details?id=eu.imouto.hupl">Hupl</a> with <a href="' . $huplUrl . '" target="_blank">this</a> custom uploader.
On Linux or Mac, you can compile <a href="https://github.com/Gurkengewuerz/KShare">KShare</a> and add <a href="' . $kshareUrl . '" target="_blank">this</a> config to ~/.config/KShare/uploader.

Or simply choose a file and click "Upload" below:
</pre>
<form id="frm" action="" method="post" enctype="multipart/form-data">
<label for="file" class="pre-upload">. . .</label>
<input type="file" name="file" id="file" />
<input type="hidden" name="formatted" value="true" />
<input type="hidden" name="token" value="' . $tokens[0] . '" />
<input type="submit" value="Upload"/>
</form>
<pre>
', '')}
 === File Sizes etc. ===
The maximum allowed file size is $MAX_FILESIZE MiB.


 === Source ===
The PHP script used to provide this service is open source and available on 
<a href="https://github.com/Gurkengewuerz/php_filehost">GitHub</a>

The main PHP script is from <a href="https://github.com/Rouji/single_php_filehost">Rouji</a> on GitHub


 === Contact ===
If you want to report abuse of this service, or have any other inquiries, 
please write an email to $ADMIN_EMAIL
</pre>
</body>
</html>
EOF;
}

$db->close();
