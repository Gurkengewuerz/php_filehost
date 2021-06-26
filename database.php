<?php

class UserDB extends SQLite3
{
    function __construct($location, $filename)
    {
        if (!file_exists($location)) {
            mkdir($location, 0750, true);
        }
        $this->open($location . "/" . $filename);
    }
}

$db = new UserDB($FILE_PATH, "database.db");

////////////////////////////////////////////////////////////////////////////////
// Database functions
////////////////////////////////////////////////////////////////////////////////
function getDatabase()
{
    global $db;
    $handle = $db;

    $handle->exec(
        "CREATE TABLE IF NOT EXISTS `users` (
	`id`	TEXT NOT NULL,
	`username`	TEXT NOT NULL,
	`created`	INTEGER,
	PRIMARY KEY(`id`)
);");

    $handle->exec(
        "CREATE TABLE IF NOT EXISTS `tokens` (
	`tokenid`	INTEGER PRIMARY KEY AUTOINCREMENT,
	`userid`	TEXT NOT NULL,
	`token`	TEXT NOT NULL
);");

    $handle->exec(
        "CREATE TABLE IF NOT EXISTS `files` (
	`fileid`	TEXT NOT NULL,
	`mime`	TEXT NOT NULL,
	`origname`	TEXT NOT NULL,
	`size`	INTEGER,
	`created`	INTEGER
	);");

    return $handle;
}

////////////////////////////////////////////////////////////////////////////////
// File functions
////////////////////////////////////////////////////////////////////////////////
function getFile($fileid)
{
    $db = getDatabase();
    $result = $db->querySingle("SELECT * FROM files WHERE fileid='" . SQLite3::escapeString($fileid) . "';", true);
    if (count($result) > 0) {
        return $result;
    }
    return null;
}

function logUpload($fileid, $mime, $origname, $size)
{
    $db = getDatabase();

    $query = $db->prepare("INSERT INTO files (fileid, mime, size, created, origname) VALUES (?, ?, ?, ?, ?);");
    $query->bindValue(1, $fileid, SQLITE3_TEXT);
    $query->bindValue(2, $mime, SQLITE3_TEXT);
    $query->bindValue(3, $size, SQLITE3_INTEGER);
    $query->bindValue(4, time(), SQLITE3_INTEGER);
    $query->bindValue(5, $origname, SQLITE3_TEXT);
    $query->execute();
    return true;
}

////////////////////////////////////////////////////////////////////////////////
// User functions
////////////////////////////////////////////////////////////////////////////////
function generateToken($id)
{
    $db = getDatabase();
    $userToken = rndStr(32);
    $db->exec("INSERT INTO tokens (userid, token) VALUES ('" . SQLite3::escapeString($id) . "', '" . SQLite3::escapeString($userToken) . "');");
    return $userToken;
}

function deleteToken($token, $id)
{
    $db = getDatabase();
    $db->exec("DELETE FROM tokens WHERE userid='" . SQLite3::escapeString($id) . "' AND token='" . SQLite3::escapeString($token) . "';");
}

function getUserTokens($id, $name)
{
    global $ALLOW_REGISTER;
    $userToken = array();
    $db = getDatabase();

    if ($ALLOW_REGISTER) {
        $result = $db->querySingle("SELECT * FROM users WHERE id='" . SQLite3::escapeString($id) . "';");
        if (count($result) <= 0) {
            $db->exec("INSERT INTO users (id, username, created) VALUES ('" . SQLite3::escapeString($id) . "', '" . SQLite3::escapeString($name) . "', " . time() . ");");
            array_push($userToken, generateToken($id));
            return $userToken;
        }
    }

    $result = $db->query("SELECT * FROM tokens WHERE userid='" . SQLite3::escapeString($id) . "';");
    while ($r = $result->fetchArray(SQLITE3_ASSOC)) {
        array_push($userToken, $r["token"]);
    }

    return $userToken;
}

function checkToken($token)
{
    $db = getDatabase();
    $result = $db->querySingle("SELECT * FROM tokens WHERE token='" . SQLite3::escapeString($token) . "';", true);
    if (count($result) > 0) {
        return true;
    }
    return false;
}