<?php
////////////////////////////////////////////////////////////////////////////////
// redirect user to github to login
// https://gist.github.com/aaronpk/3612742
////////////////////////////////////////////////////////////////////////////////
function getLogin()
{
    global $GITHUB_CLIENT_ID;
    global $HTTP_PROTO;

    $_SESSION['state'] = hash('sha256', microtime(TRUE) . rand() . $_SERVER['REMOTE_ADDR']);
    unset($_SESSION['access_token']);
    $params = array(
        'client_id' => $GITHUB_CLIENT_ID,
        'redirect_uri' => $HTTP_PROTO . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'],
        'scope' => 'user:email',
        'state' => $_SESSION['state']
    );
    // Redirect the user to Github's authorization page
    header('Location: https://github.com/login/oauth/authorize?' . http_build_query($params));
    die();
}

function apiRequest($url, $post = FALSE, $headers = array())
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    if ($post)
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    $headers[] = 'Accept: application/json';
    if ($_SESSION['access_token'])
        $headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    return json_decode($response);
}