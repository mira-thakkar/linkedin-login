<?php

//Configuration settings
define('API_KEY', 'YOUR_APP_KEY');
define('API_SECRET','YOUR_APP_SECRET' );
define('REDIRECT_URI','APP_REDIRECT_URI');
define('SCOPE','r_basicprofile r_emailaddress');

session_name('linkedin');
session_start();

// OAuth2 Control Flow
if (isset($_GET['error'])) {
     //LinkedIn returned an error
     print $_GET['error'] . ': ' . $_GET['error_description'];
     exit;
} 
elseif (isset($_GET['code'])) {
     // User authorized your application
     if ($_SESSION['state'] == $_GET['state']) {
           getAccessToken();
     }
     else {
          exit;
     }
} 
else {
     if ((empty($_SESSION['expires_at'])) || (time() > $_SESSION['expires_at'])) {
         // Token has expired, clear the state
         $_SESSION = array();
     }
     if (empty($_SESSION['access_token'])) {
         // Start authorization process
         getAuthorizationCode();
     }
}

//You have a valid token. Now fetch your profile
$user = fetch('GET', '/v1/people/~:(id,firstName,lastName,headline,public-profile-url,picture-url,emailAddress)');
echo '<img src="'.$user->pictureUrl.'" style="height:100px;width:100px">';
print "<br>"."Hello $user->firstName $user->lastName";
print "<br>"."Email: ".$user->emailAddress;
print "<br>"."Headline: ".$user->headline;
print "<br>"."<a href='".$user->publicProfileUrl."'>Visit Profile</a>";
exit;

function getAuthorizationCode() {
     $params = array(
              'response_type' => 'code',
              'client_id' => API_KEY,
              'scope' => SCOPE,
              'state' => uniqid('', true), // unique long string
              'redirect_uri' => REDIRECT_URI,
              );

     // Authentication request
     $url = 'https://www.linkedin.com/uas/oauth2/authorization?' . http_build_query($params);

     // Needed to identify request when it returns to us
     $_SESSION['state'] = $params['state'];

     // Redirect user to authenticate
     header("Location: $url");
     exit;
}

function getAccessToken() {
     $params = array(
     'grant_type' => 'authorization_code',
     'client_id' => API_KEY,
     'client_secret' => API_SECRET,
     'code' => $_GET['code'],
     'redirect_uri' => REDIRECT_URI,
     );

// Access Token request
$url = 'https://www.linkedin.com/uas/oauth2/accessToken?' . http_build_query($params);

// Tell streams to make a POST request
$context = stream_context_create(
         array('http' =>
               array('method' => 'POST')
               )
         );

// Retrieve access token information
$response = file_get_contents($url, false, $context);

// Native PHP object, please
$token = json_decode($response);

// Store access token and expiration time
$_SESSION['access_token'] = $token->access_token; // guard this!
$_SESSION['expires_in']   = $token->expires_in; // relative time (in seconds)
$_SESSION['expires_at']   = time() + $_SESSION['expires_in']; // absolute time

return true;
}

function fetch($method, $resource, $body = '') {

$params=[];
$opts = array(
'http'=>array(
'method' => $method,
'header' => "Authorization: Bearer " . $_SESSION['access_token'] . "\r\n" . "x-li-format: json\r\n"
)
);

// Need to use HTTPS
$url = 'https://api.linkedin.com' . $resource;

// Append query parameters (if there are any)
if (count($params))
{ $url .= '?' . http_build_query($params); }

// Tell streams to make a (GET, POST, PUT, or DELETE) request
// And use OAuth 2 access token as Authorization
$context = stream_context_create($opts);

$response = file_get_contents($url, false, $context);
return json_decode($response);
}